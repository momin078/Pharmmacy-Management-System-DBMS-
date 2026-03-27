<?php
// checkout.php — Select customer → place order → atomic stock update → ALWAYS insert into sales
require_once __DIR__ . "/config.php";
if (session_status() === PHP_SESSION_NONE) session_start();

/* ===================== Helpers ===================== */
if (!function_exists('h')) {
  function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
}
if (!function_exists('money')) {
  function money($n){ $n=(float)$n; $d=(floor($n)===$n)?0:2; return '৳ '.number_format($n,$d); }
}
if (!function_exists('hash_equals')) {
  function hash_equals($a,$b){ if(!is_string($a)||!is_string($b)||strlen($a)!==strlen($b)) return false;
    $x=$a^$b; $r=0; for($i=strlen($x)-1;$i>=0;$i--) $r|=ord($x[$i]); return $r===0; }
}
if (!function_exists('demo_trx')) {
  function demo_trx(string $method): string {
    $prefix = ($method === 'Card') ? 'CARD' : 'MBK';
    try { $rand = bin2hex(random_bytes(4)); } catch (Throwable $e) { $rand = dechex(mt_rand()); }
    return $prefix.'-'.strtoupper($rand);
  }
}
if (!function_exists('bad')) {
  function bad($msg){
    $_SESSION['err'] = $msg;
    header("Location: /pmc2/checkout.php");
    exit;
  }
}

/* ===================== CSRF ===================== */
if (empty($_SESSION['csrf']) || strlen($_SESSION['csrf']) < 32) {
  $_SESSION['csrf'] = bin2hex(random_bytes(32));
}
function csrf_ok(): bool {
  $tok = (string)($_POST['csrf'] ?? '');
  return $tok !== '' && hash_equals($_SESSION['csrf'], $tok);
}

/* ===================== Guards ===================== */
if (empty($_SESSION['cart']) || !is_array($_SESSION['cart'])) {
  $_SESSION['err'] = "Your cart is empty.";
  header("Location: /pmc2/cart.php"); exit;
}

/* change customer (handle before any output) */
if (isset($_GET['change'])) {
  unset($_SESSION['checkout_user_id']);
  header("Location: /pmc2/checkout.php"); exit;
}

/* ===================== User selection ===================== */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['select_user'])) {
  if (!csrf_ok()) bad("Security check failed.");
  $chosen = trim((string)($_POST['user_id'] ?? ''));
  if ($chosen === '') bad("Please select a customer.");

  $st = $db->prepare("SELECT id FROM users WHERE id=? LIMIT 1");
  $st->bind_param("s", $chosen);
  $st->execute();
  $ok = (bool)$st->get_result()->fetch_assoc();
  $st->close();

  if (!$ok) bad("Selected customer not found.");

  $_SESSION['checkout_user_id'] = $chosen;
  header("Location: /pmc2/checkout.php"); exit;
}

/* First time: show selector if no user bound */
if (empty($_SESSION['checkout_user_id'])) {
  $users = [];
  if ($db instanceof mysqli && !$db->connect_errno) {
    $res = $db->query("SELECT id, name, email FROM users ORDER BY id DESC LIMIT 100");
    if ($res) $users = $res->fetch_all(MYSQLI_ASSOC);
  }
  include __DIR__ . "/header.php";
  include __DIR__ . "/navbar.php"; ?>
  <div style="max-width:700px;margin:24px auto;background:#fff;padding:20px;border-radius:12px;box-shadow:0 8px 24px rgba(0,0,0,.08)">
    <h2 style="color:#009688;margin:0 0 12px 0">Select Customer to Checkout</h2>

    <?php if(!empty($_SESSION['err'])): ?>
      <div style="background:#fef2f2;border:1px solid #fecaca;color:#991b1b;padding:10px 12px;border-radius:10px;margin:10px 0">
        <?=h($_SESSION['err']); unset($_SESSION['err']);?>
      </div>
    <?php endif; ?>

    <form method="post" style="display:grid;gap:10px">
      <input type="hidden" name="csrf" value="<?=h($_SESSION['csrf'])?>">
      <label style="font-weight:600;color:#374151">Registered Users</label>
      <select name="user_id" required style="padding:10px;border:1px solid #d1d5db;border-radius:8px">
        <option value="">— Select a customer —</option>
        <?php foreach($users as $u): ?>
          <option value="<?=h($u['id'])?>"><?=h($u['id'])?> — <?=h($u['name'] ?: 'Unnamed')?><?= $u['email'] ? ' ('.h($u['email']).')' : '' ?></option>
        <?php endforeach; ?>
      </select>

      <div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap">
        <button name="select_user" style="background:#009688;color:#fff;border:none;padding:10px 16px;border-radius:8px;cursor:pointer">
          Use this Customer
        </button>
        <a href="/pmc2/register.php?next=%2Fpmc2%2Fcheckout.php"
           style="border:1px solid #d1d5db;padding:10px 16px;border-radius:8px;text-decoration:none;color:#111">
          + Register New Customer
        </a>
      </div>
    </form>
  </div>
  <?php include __DIR__ . "/footer.php"; exit;
}

/* Fallbacks from other session keys (if needed) */
if (empty($_SESSION['checkout_user_id'])) {
  foreach (['user_id','registered_user.id','registration_user_id','reg_user_id',
            'registered_user_id','uid','id','auth_user_id','logged_user_id'] as $key) {
    if (!empty($_SESSION[$key])) { $_SESSION['checkout_user_id'] = (string)$_SESSION[$key]; break; }
  }
}

/* ===================== Dynamic users SELECT builder ===================== */
function users_select_sql(mysqli $db): array {
  static $cache=null; if ($cache!==null) return $cache;
  $cols = [];
  if ($res = $db->query("SHOW COLUMNS FROM `users`")) {
    while ($row = $res->fetch_assoc()) $cols[strtolower($row['Field'])] = true;
    $res->free();
  }
  $nameSel = "'' AS name";
  if (!empty($cols['name'])) $nameSel = "`name` AS name";
  elseif (!empty($cols['full_name'])) $nameSel = "`full_name` AS name";
  elseif (!empty($cols['user_name']))  $nameSel = "`user_name` AS name";
  elseif (!empty($cols['username']))   $nameSel = "`username` AS name";
  elseif (!empty($cols['first_name']) && !empty($cols['last_name']))
    $nameSel = "TRIM(CONCAT(`first_name`,' ',`last_name`)) AS name";
  elseif (!empty($cols['first_name']))  $nameSel = "`first_name` AS name";

  $emailSel = "'' AS email";
  foreach (['email','user_email','email_address','mail'] as $c) if (!empty($cols[$c])) { $emailSel = "`$c` AS email"; break; }

  $phoneSel = "'' AS phone";
  foreach (['contact','phone','mobile','phone_number','user_phone','tel','cell','phn'] as $c) if (!empty($cols[$c])) { $phoneSel = "`$c` AS phone"; break; }

  $addrSel = "'' AS address";
  foreach (['address','addr','user_address','address1','address_line1','shipping_address','delivery_address'] as $c)
    if (!empty($cols[$c])) { $addrSel = "`$c` AS address"; break; }

  $cache = ['select_by_id' => "SELECT `id`, $nameSel, $emailSel, $phoneSel, $addrSel FROM `users` WHERE `id`=? LIMIT 1"];
  return $cache;
}

/* ===================== Load selected user ===================== */
$selected_user = null;
$uid = (string)$_SESSION['checkout_user_id'];
if ($db instanceof mysqli && !$db->connect_errno) {
  $sql = users_select_sql($db)['select_by_id'];
  $st = $db->prepare($sql);
  $st->bind_param("s",$uid);
  $st->execute();
  $selected_user = $st->get_result()->fetch_assoc() ?: null;
}
if (!$selected_user) { unset($_SESSION['checkout_user_id']); bad("Your account could not be found. Please select or register again."); }

/* ===================== Load cart items (ACTIVE only) ===================== */
$items = []; $subtotal = 0.0;
foreach ($_SESSION['cart'] as $mid=>$qty) {
  $mid=(int)$mid; $qty=(int)$qty; if($mid<=0||$qty<=0) continue;
  $q = $db->prepare("SELECT id,name,price,quantity FROM medicines WHERE id=? AND status='ACTIVE'");
  $q->bind_param("i",$mid); $q->execute();
  if ($row = $q->get_result()->fetch_assoc()) {
    $line = (float)$row['price'] * $qty;
    $items[] = [
      'id'=>(int)$row['id'],
      'name'=>$row['name'],
      'price'=>(float)$row['price'],
      'qty'=>$qty,
      'line'=>$line,
      'stock'=>(int)$row['quantity']
    ];
    $subtotal += $line;
  }
}
if (!$items) bad("Some items in your cart are unavailable/inactive. Please review your cart.");

$vat      = round($subtotal * 0.05, 2);
$delivery = 20.00;
$grand    = round($subtotal + $vat + $delivery, 2);

/* ===================== Place order ===================== */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['place_order'])) {
  if (!csrf_ok()) bad("Security check failed.");

  // Reload user (trusted)
  $sql = users_select_sql($db)['select_by_id'];
  $st = $db->prepare($sql);
  $st->bind_param("s",$uid);
  $st->execute();
  $user = $st->get_result()->fetch_assoc();
  if (!$user) bad("Please select/register again.");

  // Delivery address (required)
  $delivery_address = trim((string)($_POST['delivery_address'] ?? ''));
  if ($delivery_address === '') bad("Please provide delivery address.");

  // If users.email/phone is blank, allow override from form
  $email_override = trim((string)($_POST['email_override'] ?? ''));
  $phone_override = trim((string)($_POST['phone_override'] ?? ''));
  $final_email = ($user['email'] ?? '') !== '' ? $user['email'] : $email_override;
  $final_phone = ($user['phone'] ?? '') !== '' ? $user['phone'] : $phone_override;
  if ($final_email === '') bad("Email is required for the order.");
  if ($final_phone === '') bad("Phone is required for the order.");

  // Payment (validate)
  $allowed_methods = ['Card','Mobile Banking','Cash on Delivery'];
  $payment_method = trim((string)($_POST['payment_method'] ?? 'Cash on Delivery'));
  if (!in_array($payment_method, $allowed_methods, true)) bad("Invalid payment method.");

  $transaction_id = trim((string)($_POST['transaction_id'] ?? ''));
  if ($payment_method !== 'Cash on Delivery') {
    if ($transaction_id !== '' && !preg_match('/^[A-Za-z0-9._-]{1,64}$/', $transaction_id)) {
      bad("Invalid transaction reference.");
    }
  } else {
    $transaction_id = '';
  }

  // Status (demo auto-PAID for non-COD)
  $status = 'PENDING';
  if (defined('PAYMENT_DEMO') && PAYMENT_DEMO === true) {
    if (in_array($payment_method, ['Card','Mobile Banking'], true)) {
      if ($transaction_id === '') $transaction_id = demo_trx($payment_method);
      $status = 'PAID';
    }
  }

  // Re-check stock + compute totals (server-trust)
  $items2 = []; $subtotal2 = 0.0;
  foreach ($_SESSION['cart'] as $mid=>$qty) {
    $mid=(int)$mid; $qty=(int)$qty; if ($mid<=0||$qty<=0) continue;
    $q = $db->prepare("SELECT id,name,price,quantity FROM medicines WHERE id=? AND status='ACTIVE'");
    $q->bind_param("i",$mid); $q->execute();
    if ($row = $q->get_result()->fetch_assoc()) {
      if ($qty > (int)$row['quantity']) bad("Insufficient stock for {$row['name']}. Available: ".(int)$row['quantity']);
      $line = (float)$row['price'] * $qty;
      $items2[] = ['id'=>(int)$row['id'],'name'=>$row['name'],'price'=>(float)$row['price'],'qty'=>$qty,'line'=>$line];
      $subtotal2 += $line;
    } else {
      bad("An item in your cart is unavailable.");
    }
  }
  if (!$items2) { header("Location: /pmc2/cart.php"); exit; }
  $vat2      = round($subtotal2 * 0.05, 2);
  $delivery2 = 20.00;
  $grand2    = round($subtotal2 + $vat2 + $delivery2, 2);

  // Save order + items + stock (atomic)
  $db->begin_transaction();
  try {
    $ins = $db->prepare("INSERT INTO orders
      (user_id, user_name, user_email, user_phone, user_address,
       payment_method, transaction_id,
       subtotal, vat, delivery_charge, grand_total,
       status)
      VALUES (?,?,?,?,?,?,?,?,?,?,?,?)");

    $uidStr    = (string)$user['id'];
    $nameStr   = (string)$user['name'];
    $emailStr  = (string)$final_email;
    $phoneStr  = (string)$final_phone;
    $addrStr   = (string)$delivery_address;
    $pmStr     = (string)$payment_method;
    $trxStr    = (string)$transaction_id;
    $subF      = (float)$subtotal2;
    $vatF      = (float)$vat2;
    $delF      = (float)$delivery2;
    $grandF    = (float)$grand2;
    $statusStr = (string)$status;

    $ins->bind_param("sssssssdddds",
      $uidStr, $nameStr, $emailStr, $phoneStr, $addrStr,
      $pmStr, $trxStr,
      $subF, $vatF, $delF, $grandF,
      $statusStr
    );
    $ins->execute();
    $order_id = $ins->insert_id;

    $oi  = $db->prepare("INSERT INTO order_items (order_id, medicine_id, name, unit_price, qty, line_total)
                         VALUES (?,?,?,?,?,?)");
    $upd = $db->prepare("UPDATE medicines
                          SET quantity = quantity - ?, 
                              sold_count = COALESCE(sold_count,0) + ?
                          WHERE id=? AND quantity >= ?");

    foreach ($items2 as $it) {
      $medId = (int)$it['id'];
      $nm    = (string)$it['name'];
      $price = (float)$it['price'];
      $qtyI  = (int)$it['qty'];
      $line  = (float)$it['line'];

      $oi->bind_param("iisdid", $order_id, $medId, $nm, $price, $qtyI, $line);
      $oi->execute();

      $upd->bind_param("iiii", $qtyI, $qtyI, $medId, $qtyI);
      $upd->execute();
      if ($upd->affected_rows !== 1) throw new RuntimeException("Insufficient stock for {$nm}");
    }

    /* === ALWAYS insert into `sales` right here (instant report update) === */
    $hasOrderIdCol = false;
    if ($chk = $db->query("SHOW COLUMNS FROM `sales` LIKE 'order_id'")) {
      $hasOrderIdCol = (bool)$chk->num_rows;
      $chk->free();
    }

    if ($hasOrderIdCol) {
      $insSale = $db->prepare(
        "INSERT INTO sales (order_id, medicine_name, quantity, price, total, sale_date)
         VALUES (?,?,?,?,?, NOW())"
      );
      foreach ($items2 as $it) {
        $name = (string)$it['name'];
        $qty  = (int)$it['qty'];
        $price= (float)$it['price'];
        $line = (float)$it['line'];
        $insSale->bind_param("isidd", $order_id, $name, $qty, $price, $line);
        $insSale->execute();
      }
    } else {
      $insSale = $db->prepare(
        "INSERT INTO sales (medicine_name, quantity, price, total, sale_date)
         VALUES (?,?,?,?, NOW())"
      );
      foreach ($items2 as $it) {
        $name = (string)$it['name'];
        $qty  = (int)$it['qty'];
        $price= (float)$it['price'];
        $line = (float)$it['line'];
        $insSale->bind_param("sidd", $name, $qty, $price, $line);
        $insSale->execute();
      }
    }
    /* === END sales insert === */

    // ... inside your success block, just before redirect:
// ... try {  (আপনার ট্রানজ্যাকশন কোডের ভিতরে)

$db->commit();
$_SESSION['cart'] = [];
unset($_SESSION['checkout_user_id']);

// ✅ Place Order সফল হলে হেডারে ইনভয়েস বাটন দেখানোর জন্য:
$_SESSION['last_order_id'] = (int)$order_id;

header("Location: /pmc2/invoice.php?order_id=".(int)$order_id);
exit;

// } catch (...) { ... }



    // 🔹 DIRECT REDIRECT TO INVOICE (requested)
    header("Location: /pmc2/invoice.php?order_id=".(int)$order_id);
    exit;

  } catch (Throwable $e) {
    $db->rollback();
    error_log("Order failed: ".$e->getMessage().($db->error ? " | DB: ".$db->error : ""));
    if (isset($ins) && $ins instanceof mysqli_stmt && $ins->error) error_log("orders: ".$ins->error);
    if (isset($oi)  && $oi  instanceof mysqli_stmt && $oi->error)  error_log("order_items: ".$oi->error);
    if (isset($upd) && $upd instanceof mysqli_stmt && $upd->error) error_log("stock_update: ".$upd->error);

    bad("Order failed. Please try again or contact support.");
  }
}

/* ===================== View ===================== */
include __DIR__ . "/header.php";
include __DIR__ . "/navbar.php";
?>
<div style="max-width:1000px;margin:24px auto;background:#fff;padding:20px;border-radius:12px;box-shadow:0 8px 24px rgba(0,0,0,.08)">
  <h2 style="color:#009688;margin-top:0">Checkout</h2>

  <?php if(!empty($_SESSION['err'])): ?>
    <div style="background:#fef2f2;border:1px solid #fecaca;color:#991b1b;padding:10px 12px;border-radius:10px;margin:10px 0">
      <?=h($_SESSION['err']); unset($_SESSION['err']);?>
    </div>
  <?php endif; ?>

  <div style="border:1px solid #eee;padding:14px;border-radius:10px;margin-bottom:14px;display:flex;gap:8px;align-items:center;flex-wrap:wrap">
    <span style="color:#065f46;background:#ecfdf5;border:1px solid #a7f3d0;padding:6px 10px;border-radius:8px">
      ✅ Logged in as: #<?= h($selected_user['id']) ?> — <?= h($selected_user['name'] ?: 'N/A') ?>
    </span>
    <a href="/pmc2/checkout.php?change=1"
       style="margin-left:auto;border:1px solid #d1d5db;background:#fff;padding:6px 10px;border-radius:8px;cursor:pointer;text-decoration:none;color:#111">
      Change Customer
    </a>
  </div>

  <div style="display:grid;grid-template-columns:1.2fr .8fr;gap:16px;align-items:start">
    <!-- Left: Customer + Delivery + Payment -->
    <div style="border:1px solid #eee;padding:14px;border-radius:10px">
      <h3 style="margin:0 0 8px 0">Customer Information</h3>
      <div style="display:grid;grid-template-columns:140px 1fr;gap:8px;margin-bottom:10px">
        <div style="color:#6b7280">User ID</div><div>#<?= h($selected_user['id']) ?></div>
        <div style="color:#6b7280">Name</div><div><?= h($selected_user['name'] ?: '—') ?></div>
        <div style="color:#6b7280">Email</div>
        <div>
          <?php if(($selected_user['email'] ?? '')===''): ?>
            <input form="placeForm" name="email_override" type="email" required placeholder="Enter email"
                   style="width:100%;padding:8px;border:1px solid #d1d5db;border-radius:8px">
          <?php else: ?>
            <?= h($selected_user['email']) ?>
          <?php endif; ?>
        </div>
        <div style="color:#6b7280">Phone</div>
        <div>
          <?php if(($selected_user['phone'] ?? '')===''): ?>
            <input form="placeForm" name="phone_override" required placeholder="Enter phone"
                   style="width:100%;padding:8px;border:1px solid #d1d5db;border-radius:8px">
          <?php else: ?>
            <?= h($selected_user['phone']) ?>
          <?php endif; ?>
        </div>
        <div style="color:#6b7280">Saved Address</div><div><?= h($selected_user['address'] ?: '—') ?></div>
      </div>

      <form method="post" id="placeForm">
        <input type="hidden" name="csrf" value="<?=h($_SESSION['csrf'])?>">

        <h4 style="margin:10px 0 6px 0">Delivery Address (required)</h4>
        <textarea name="delivery_address" required placeholder="House/Flat, Road, Area, City, Postcode"
                  style="width:100%;min-height:90px;padding:8px;border:1px solid #d1d5db;border-radius:8px"></textarea>

        <h3 style="margin:14px 0 8px 0">Payment Method</h3>
        <div style="display:grid;gap:6px;margin-bottom:8px">
          <label style="display:flex;gap:8px;align-items:center"><input type="radio" name="payment_method" value="Card" checked><span>Card</span></label>
          <label style="display:flex;gap:8px;align-items:center"><input type="radio" name="payment_method" value="Mobile Banking"><span>Mobile Banking</span></label>
          <label style="display:flex;gap:8px;align-items:center"><input type="radio" name="payment_method" value="Cash on Delivery"><span>Cash on Delivery</span></label>
        </div>

        <div id="trxBox" style="display:block">
          <label>Transaction / Reference ID (Card/Mobile হলে)</label>
          <div style="display:flex;gap:8px;align-items:center">
            <input id="trx" name="transaction_id" placeholder="<?= (defined('PAYMENT_DEMO') && PAYMENT_DEMO ? 'Leave empty in demo; auto-generated' : 'Enter gateway reference') ?>"
                   style="flex:1;padding:8px;border:1px solid #d1d5db;border-radius:8px">
            <?php if (defined('PAYMENT_DEMO') && PAYMENT_DEMO): ?>
              <button type="button"
                      onclick="document.getElementById('trx').value = 'DEMO-'+Math.random().toString(16).slice(2).toUpperCase()"
                      style="padding:8px 10px;border:1px solid #d1d5db;border-radius:8px;background:#f8fafc">Demo ID</button>
            <?php endif; ?>
          </div>
        </div>

        <button name="place_order" style="margin-top:10px;background:#009688;color:#fff;border:none;padding:10px 16px;border-radius:8px;cursor:pointer">
          Place Order
        </button>
      </form>
    </div>

    <!-- Right: Order summary -->
    <div style="border:1px solid #eee;padding:14px;border-radius:10px">
      <h3 style="margin:0 0 8px 0">Order Summary</h3>
      <table width="100%" border="1" cellspacing="0" cellpadding="6" style="border-color:#eee;font-size:14px">
        <tr style="background:#009688;color:#fff">
          <th align="left">Product</th><th>Qty</th><th>Unit</th><th>Subtotal</th>
        </tr>
        <?php foreach($items as $it): ?>
          <tr>
            <td align="left"><?= h($it['name']) ?></td>
            <td align="center"><?= (int)$it['qty'] ?></td>
            <td align="right"><?= money($it['price']) ?></td>
            <td align="right"><?= money($it['line']) ?></td>
          </tr>
        <?php endforeach; ?>
        <tr><td colspan="3" align="right">Subtotal</td><td align="right"><?= money($subtotal) ?></td></tr>
        <tr><td colspan="3" align="right">VAT (5%)</td><td align="right"><?= money($vat) ?></td></tr>
        <tr><td colspan="3" align="right">Delivery</td><td align="right"><?= money($delivery) ?></td></tr>
        <tr><th colspan="3" align="right">Grand Total</th><th align="right"><?= money($grand) ?></th></tr>
      </table>
    </div>
  </div>
</div>

<script>
  // Card/Mobile হলে trx box visible; COD হলে hidden
  (function(){
    const radios = document.querySelectorAll('input[name="payment_method"]');
    const box = document.getElementById('trxBox');
    function sync(){
      const v = document.querySelector('input[name="payment_method"]:checked')?.value || '';
      box.style.display = (v === 'Cash on Delivery') ? 'none' : 'block';
    }
    radios.forEach(r => r.addEventListener('change', sync));
    sync();
  })();
</script>

<?php include __DIR__ . "/footer.php"; ?>
