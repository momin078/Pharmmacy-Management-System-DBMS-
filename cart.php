<?php
// cart.php (merged & hardened)
require_once __DIR__ . "/config.php";
if (session_status() === PHP_SESSION_NONE) session_start();

/* ---------- Helpers ---------- */
function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function money($n){
  $n = (float)$n; $dec = (floor($n)===$n)?0:2;
  return '৳ '.number_format($n, $dec);
}

/* ---------- CSRF (robust) ---------- */
if (!function_exists('cart_secure_random')) {
  function cart_secure_random($len=32){
    if (function_exists('random_bytes')) return random_bytes($len);
    if (function_exists('openssl_random_pseudo_bytes')) return openssl_random_pseudo_bytes($len);
    $buf=''; for($i=0;$i<$len;$i++) $buf.=chr(mt_rand(0,255)); return $buf;
  }
}
if (!function_exists('hash_equals')) {
  function hash_equals($a,$b){
    if (!is_string($a)||!is_string($b)||strlen($a)!==strlen($b)) return false;
    $res=$a^$b; $ret=0; for($i=strlen($res)-1;$i>=0;$i--) $ret|=ord($res[$i]); return $ret===0;
  }
}
if (empty($_SESSION['csrf']) || strlen($_SESSION['csrf'])<32) {
  $_SESSION['csrf'] = bin2hex(cart_secure_random(32));
}

/* ---------- Ensure cart structure ---------- */
if (!isset($_SESSION['cart']) || !is_array($_SESSION['cart'])) $_SESSION['cart'] = [];

/* ---------- POST: add to cart ---------- */
if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['add_to_cart'])) {
  $token = (string)($_POST['csrf'] ?? '');
  if ($token==='' || !hash_equals($_SESSION['csrf'], $token)) {
    $_SESSION['err'] = "Security check failed.";
    header("Location: cart.php"); exit;
  }
  $id = max(0, (int)($_POST['id'] ?? 0));
  $qty = max(1, (int)($_POST['quantity'] ?? 1));

  if ($id>0 && $db instanceof mysqli && !$db->connect_errno) {
    $q = $db->prepare("SELECT id,name,quantity FROM medicines WHERE id=? AND status='ACTIVE' LIMIT 1");
    $q->bind_param("i",$id); $q->execute(); $row = $q->get_result()->fetch_assoc();
    if ($row) {
      $current = (int)($_SESSION['cart'][$id] ?? 0);
      $desired = $current + $qty;
      $cap = (int)$row['quantity'];
      if ($desired > $cap) { $desired = $cap; $_SESSION['err'] = "Only $cap in stock for ".$row['name']."."; }
      $_SESSION['cart'][$id] = $desired;
      if (empty($_SESSION['err'])) $_SESSION['msg'] = "Added to cart: ".$row['name'];
    } else {
      $_SESSION['err'] = "Item unavailable.";
    }
  }
  header("Location: cart.php"); exit;
}

/* ---------- POST: update qty ---------- */
if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['update_qty'])) {
  $token = (string)($_POST['csrf'] ?? '');
  if ($token==='' || !hash_equals($_SESSION['csrf'], $token)) {
    $_SESSION['err'] = "Security check failed.";
    header("Location: cart.php"); exit;
  }
  $id = max(0, (int)($_POST['id'] ?? 0));
  $qty = max(0, (int)($_POST['quantity'] ?? 0)); // 0 হলে remove

  if ($id>0) {
    if ($qty===0) {
      unset($_SESSION['cart'][$id]);
      $_SESSION['msg'] = "Item removed.";
    } else if ($db instanceof mysqli && !$db->connect_errno) {
      $q = $db->prepare("SELECT name,quantity FROM medicines WHERE id=? AND status='ACTIVE' LIMIT 1");
      $q->bind_param("i",$id); $q->execute(); $row = $q->get_result()->fetch_assoc();
      if ($row) {
        $cap = (int)$row['quantity'];
        if ($qty > $cap) { $qty = $cap; $_SESSION['err'] = "Only $cap in stock for ".$row['name']."."; }
        $_SESSION['cart'][$id] = $qty;
        if (empty($_SESSION['err'])) $_SESSION['msg'] = "Quantity updated.";
      } else {
        unset($_SESSION['cart'][$id]);
        $_SESSION['err'] = "Item unavailable.";
      }
    }
  }
  header("Location: cart.php"); exit;
}

/* ---------- POST: remove item ---------- */
if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['remove_item'])) {
  $token = (string)($_POST['csrf'] ?? '');
  if ($token==='' || !hash_equals($_SESSION['csrf'], $token)) {
    $_SESSION['err'] = "Security check failed.";
    header("Location: cart.php"); exit;
  }
  $id = max(0, (int)($_POST['id'] ?? 0));
  unset($_SESSION['cart'][$id]);
  $_SESSION['msg'] = "Item removed.";
  header("Location: cart.php"); exit;
}

/* ---------- Removed legacy GET remove (CSRF risk) ---------- */

/* ---------- Fetch items in one query (ACTIVE only) ---------- */
$items = [];
if (!empty($_SESSION['cart']) && $db instanceof mysqli && !$db->connect_errno) {
  $ids = array_keys($_SESSION['cart']);
  $ids = array_values(array_filter(array_map('intval',$ids), fn($x)=>$x>0));
  if ($ids) {
    $ph = implode(',', array_fill(0, count($ids), '?'));
    $types = str_repeat('i', count($ids));
    $sql = "SELECT id, name, price, quantity FROM medicines WHERE status='ACTIVE' AND id IN ($ph)";
    $stmt = $db->prepare($sql);
    $stmt->bind_param($types, ...$ids);
    $stmt->execute();
    $res = $stmt->get_result();
    while($row = $res->fetch_assoc()) { $items[(int)$row['id']] = $row; }
    $stmt->close();
  }
}

/* ---------- Totals ---------- */
$grand = 0.0;
?>
<?php include __DIR__ . "/header.php"; ?>
<?php include __DIR__ . "/navbar.php"; ?>

<div style="width:90%;max-width:1200px;margin:30px auto;background:#fff;padding:20px;border-radius:14px;
            box-shadow:0 10px 30px rgba(0,0,0,0.08)">

  <h2 style="color:#0f7a3a;margin:0 0 10px">Your Shopping Cart</h2>

  <?php if(!empty($_SESSION['msg'])): ?>
    <div style="background:#ecfdf5;border:1px solid #a7f3d0;color:#065f46;padding:10px 12px;border-radius:10px;margin:10px 0">
      <?=h($_SESSION['msg']); unset($_SESSION['msg']);?>
    </div>
  <?php endif; ?>
  <?php if(!empty($_SESSION['err'])): ?>
    <div style="background:#fef2f2;border:1px solid #fecaca;color:#991b1b;padding:10px 12px;border-radius:10px;margin:10px 0">
      <?=h($_SESSION['err']); unset($_SESSION['err']);?>
    </div>
  <?php endif; ?>

  <?php if (!empty($_SESSION['cart']) && !empty($items)): ?>
    <div style="overflow:auto">
      <table width="100%" cellspacing="0" cellpadding="10"
             style="border-collapse:separate;border-spacing:0 8px">
        <thead>
          <tr style="background:#009688;color:#fff">
            <th align="left" style="border-radius:8px 0 0 8px">Medicine</th>
            <th align="center">Quantity</th>
            <th align="right">Price</th>
            <th align="right">Total</th>
            <th align="center" style="border-radius:0 8px 8px 0">Action</th>
          </tr>
        </thead>
        <tbody>
        <?php foreach ($_SESSION['cart'] as $id => $qty):
          $id=(int)$id; $qty=(int)$qty;
          if (!isset($items[$id])) { unset($_SESSION['cart'][$id]); continue; }
          $row = $items[$id];
          $price = (float)$row['price'];
          $stock = (int)$row['quantity'];
          if ($qty>$stock) { $qty=$stock; $_SESSION['cart'][$id]=$qty; }
          $line = $price * $qty; $grand += $line;
        ?>
          <tr style="background:#f8fafc;border:1px solid #eef2f7">
            <td style="border-left:1px solid #eef2f7;border-radius:8px 0 0 8px">
              <?=h($row['name'])?>
              <?php if($stock<=0): ?>
                <div style="color:#b91c1c;font-size:12px">Out of stock</div>
              <?php elseif($qty>=$stock): ?>
                <div style="color:#92400e;font-size:12px">Max available: <?=$stock?></div>
              <?php endif; ?>
            </td>
            <td align="center">
              <form method="post" style="display:inline-flex;gap:6px;align-items:center">
                <input type="hidden" name="csrf" value="<?=h($_SESSION['csrf'])?>">
                <input type="hidden" name="id" value="<?=$id?>">
                <input type="number" name="quantity" min="0" max="<?=$stock?>"
                       value="<?=$qty?>" style="width:80px;padding:6px;border:1px solid #d1d5db;border-radius:8px">
                <button name="update_qty" class="btn"
                        style="background:#009688;color:#fff;padding:8px 12px;border:0;border-radius:8px;cursor:pointer">
                  Update
                </button>
              </form>
            </td>
            <td align="right"><?=money($price)?></td>
            <td align="right" style="font-weight:700"><?=money($line)?></td>
            <td align="center" style="border-right:1px solid #eef2f7;border-radius:0 8px 8px 0">
              <form method="post" onsubmit="return confirm('Remove this item?')" style="display:inline">
                <input type="hidden" name="csrf" value="<?=h($_SESSION['csrf'])?>">
                <input type="hidden" name="id" value="<?=$id?>">
                <button name="remove_item" style="background:#e11d48;color:#fff;border:0;padding:8px 12px;border-radius:8px;cursor:pointer">
                  Remove
                </button>
              </form>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>

    <p style="text-align:right;font-weight:800;font-size:18px;margin:12px 0">
      Grand Total: <?=money($grand)?>
    </p>

    <div style="text-align:right">
      <a href="checkout.php" style="background:#0f7a3a;color:#fff;padding:12px 18px;border-radius:10px;
         text-decoration:none;display:inline-block;font-weight:700">
        Proceed to Checkout
      </a>
    </div>

  <?php else: ?>
    <p style="margin:10px 0">Your cart is empty.</p>
  <?php endif; ?>
</div>

<?php include __DIR__ . "/footer.php"; ?>
