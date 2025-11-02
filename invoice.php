<?php
require_once __DIR__ . "/config.php";

/* Helpers */
if (!function_exists('h')) {
  function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
}
if (!function_exists('money')) {
  function money($n){ $n=(float)$n; return number_format($n,2); }
}

$order_id = (int)($_GET['order_id'] ?? 0);
if ($order_id <= 0) { http_response_code(404); exit("Order not found."); }

/* 1) Order */
$ord = $db->prepare("
  SELECT id, user_id, user_name, user_email, user_phone, user_address,
         payment_method, transaction_id, status,
         subtotal, vat, delivery_charge, grand_total,
         created_at
  FROM orders WHERE id=? LIMIT 1
");
$ord->bind_param("i", $order_id);
$ord->execute();
$order = $ord->get_result()->fetch_assoc();
if (!$order) { http_response_code(404); exit("Order not found."); }

/* 2) Items */
$it = $db->prepare("
  SELECT medicine_id, name, unit_price, qty, line_total
  FROM order_items WHERE order_id=? ORDER BY id ASC
");
$it->bind_param("i", $order_id);
$it->execute();
$items = $it->get_result()->fetch_all(MYSQLI_ASSOC);

$st = strtoupper((string)$order['status']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>Invoice #<?= h($order['id']) ?></title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<style>
  :root{
    --brand:#009688;
    --brand-dark:#00796b;
    --muted:#6b7280;
    --bg:#f5f7fb;
    --card:#ffffff;
    --bd:#e5e7eb;
    --heading:#111827;
  }
  *{box-sizing:border-box}
  body{margin:0;font-family:Arial,Helvetica,sans-serif;background:var(--bg);color:#111}

  .wrap{padding:24px}
  .inv{
    max-width:900px;margin:18px auto 32px;background:#fff;border:1px solid var(--bd);
    border-radius:16px;box-shadow:0 10px 30px rgba(2,8,20,.06);
    position:relative;overflow:hidden;
  }
  .inv-inner{padding:24px 28px;position:relative;z-index:1}

  /* ✅ Watermark - বড় ও মাঝখানে */
  .wm{
    position:absolute;
    top:50%; left:50%;
    transform:translate(-50%,-50%) rotate(-30deg);
    font-size:200px; font-weight:900; text-transform:uppercase;
    color:rgba(220,38,38,0.15); /* লালচে গ্রে, বোঝা যাবে */
    letter-spacing:16px; white-space:nowrap;
    pointer-events:none; user-select:none;
    z-index:0;
  }

  .header{margin-bottom:12px;text-align:center}
  .header .line-1{font-size:24px;font-weight:700;color:var(--heading);margin:0}
  .header .line-2{font-size:26px;font-weight:800;color:var(--brand);margin:2px 0 0}
  .meta-row{display:flex;justify-content:space-between;margin-top:10px;font-size:14px;color:#555}

  /* Actions */
  .actions{position:fixed;top:24px;right:24px;display:flex;flex-direction:column;gap:8px;z-index:9999}
  .btn{display:inline-flex;align-items:center;gap:6px;padding:8px 12px;border-radius:10px;
       border:1px solid var(--bd);background:#fff;color:#111;text-decoration:none;cursor:pointer;font-size:14px}
  .btn:hover{background:#f3f4f6}
  .btn-primary{background:var(--brand);color:#fff;border-color:var(--brand)}
  .btn-primary:hover{background:var(--brand-dark)}

  /* Info boxes */
  .grid{display:grid;grid-template-columns:repeat(3,1fr);gap:16px;margin-top:16px}
  .box{border:1px solid var(--bd);border-radius:12px;background:#fff;box-shadow:0 2px 8px rgba(0,0,0,.04)}
  .box .box-h{padding:10px 12px;font-weight:700;background:#e6fffa;border-bottom:1px solid var(--bd);color:#0f766e}
  .box .box-c{padding:10px 12px;font-size:14px}

  /* Table */
  table{width:100%;border-collapse:collapse;margin-top:18px;font-size:14px}
  thead th{background:var(--brand);color:#fff;padding:10px;text-align:center}
  tbody td{border:1px solid var(--bd);padding:10px;text-align:center}
  th.left,td.left{text-align:left} th.right,td.right{text-align:right}
  .totals-row td{font-weight:bold;background:#f8fffd}

  .footer{text-align:center;margin:24px 0 4px;font-size:13px;color:var(--muted)}

  @media print{.actions{display:none!important}.inv{border:none;box-shadow:none}}
</style>
</head>
<body>
<div class="wrap">

  <div class="inv">
    <?php if ($st === 'PAID'): ?>
      <div class="wm">PAID</div>
    <?php endif; ?>

    <!-- Actions -->
    <div class="actions">
      <a class="btn" href="home.php">← Back</a>
      <a class="btn" href="invoice_pdf.php?order_id=<?= (int)$order['id'] ?>" download target="_blank" rel="noopener">⬇️ Download PDF</a>
      <button class="btn btn-primary" type="button" onclick="window.print()">🖨 Print</button>
    </div>

    <div class="inv-inner">
      <!-- Header -->
      <div class="header">
        <div class="line-1">Pharmacy Management System</div>
        <div class="line-2">Lazz Pharma</div>
        <div class="meta-row">
          <div>Invoice No: <strong>INV-<?= h($order['id']) ?></strong></div>
          <div>Date: <?= h(date('Y-m-d', strtotime($order['created_at'] ?? 'now'))) ?></div>
        </div>
      </div>

      <!-- Info boxes -->
      <div class="grid">
        <div class="box">
          <div class="box-h">Customer Information</div>
          <div class="box-c">
            Name: <?= h($order['user_name']) ?><br>
            Email: <?= h($order['user_email'] ?: 'N/A') ?><br>
            Phone: <?= h($order['user_phone'] ?: 'N/A') ?><br>
            Address: <?= h($order['user_address'] ?: 'N/A') ?>
          </div>
        </div>
        <div class="box">
          <div class="box-h">Payment Information</div>
          <div class="box-c">
            Method: <?= h($order['payment_method']) ?><br>
            Transaction ID: <?= h($order['transaction_id'] ?: 'N/A') ?><br>
            Status: <?= h($order['status']) ?>
          </div>
        </div>
        <div class="box">
          <div class="box-h">Order Information</div>
          <div class="box-c">
            Order ID: ORD-<?= h($order['id']) ?><br>
            Order Date: <?= h(date('Y-m-d', strtotime($order['created_at'] ?? 'now'))) ?><br>
            Status: <?= h($order['status']) ?>
          </div>
        </div>
      </div>

      <!-- Items -->
      <table>
        <thead>
          <tr>
            <th class="left">Product</th>
            <th>Qty</th>
            <th class="right">Unit Price (৳)</th>
            <th class="right">Subtotal (৳)</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($items as $r): ?>
            <tr>
              <td class="left"><?= h($r['name']) ?></td>
              <td><?= (int)$r['qty'] ?></td>
              <td class="right"><?= money($r['unit_price']) ?></td>
              <td class="right"><?= money($r['line_total']) ?></td>
            </tr>
          <?php endforeach; ?>
          <tr>
            <td colspan="3" class="right"><strong>Subtotal</strong></td>
            <td class="right"><?= money($order['subtotal']) ?></td>
          </tr>
          <tr>
            <td colspan="3" class="right">VAT (5%)</td>
            <td class="right"><?= money($order['vat']) ?></td>
          </tr>
          <tr>
            <td colspan="3" class="right">Delivery Charge</td>
            <td class="right"><?= money($order['delivery_charge']) ?></td>
          </tr>
          <tr class="totals-row">
            <td colspan="3" class="right"><strong>Total</strong></td>
            <td class="right"><strong><?= money($order['grand_total']) ?></strong></td>
          </tr>
        </tbody>
      </table>

      <p style="margin:18px 0 8px">Thank you for shopping with us!</p>

      <div class="footer">
        Developed by <strong>Md Mokhlesur Rahman Momin</strong>
      </div>
    </div>
  </div>
</div>
</body>
</html>
