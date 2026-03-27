<?php
require_once "config.php";
if (session_status()===PHP_SESSION_NONE) session_start();
date_default_timezone_set('Asia/Dhaka');

function h($v){ return htmlspecialchars((string)$v,ENT_QUOTES,'UTF-8'); }

// CSRF for export links
if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(16));
$csrf = $_SESSION['csrf'];

// ---- Filter (POST & GET both supported) ----
$fromDate = $_POST['from_date'] ?? ($_GET['from'] ?? '');
$toDate   = $_POST['to_date']   ?? ($_GET['to']   ?? '');
$useFilter = ($fromDate !== '' && $toDate !== '');

// ---- WHERE (date-safe) ----
// NOTE: আমরা ইচ্ছাকৃতভাবে DATE(sale_date) ব্যবহার করছি,
// যাতে sale_date DATETIME হলেও pure date range-এ পড়ে।
$where = '';
if ($useFilter) {
  $f = $db->real_escape_string($fromDate);
  $t = $db->real_escape_string($toDate);
  $where = "WHERE DATE(sale_date) BETWEEN '{$f}' AND '{$t}'";
}

// ---- Debug toggle ----
$debug = isset($_GET['debug']) && $_GET['debug'] == '1';
$last_err = '';
function q($db,$sql){
  $res = $db->query($sql);
  if ($db->error) {
    global $last_err;
    $last_err = $db->error;
  }
  return $res;
}

// ================= KPIs =================
$kpi = q($db,"SELECT COUNT(*) AS orders,
                     COALESCE(SUM(quantity),0) AS qty,
                     COALESCE(SUM(total),0)    AS revenue
              FROM sales {$where}");
$k = $kpi ? $kpi->fetch_assoc() : ['orders'=>0,'qty'=>0,'revenue'=>0];
$total_orders = (int)($k['orders'] ?? 0);
$total_qty    = (int)($k['qty'] ?? 0);
$total_rev    = (float)($k['revenue'] ?? 0.0);
$avg_order    = $total_orders>0 ? ($total_rev/$total_orders) : 0.0;

// ================= Sales by Day =================
$byday = q($db,"SELECT DATE(sale_date) AS d,
                       COUNT(*) AS orders,
                       COALESCE(SUM(quantity),0) AS qty,
                       COALESCE(SUM(total),0)    AS rev
                FROM sales
                {$where}
                GROUP BY DATE(sale_date)
                ORDER BY d DESC");
$sales_by_day = $byday ? $byday->fetch_all(MYSQLI_ASSOC) : [];

// ================= Top by Revenue =================
$toprev = q($db,"SELECT medicine_name,
                        COALESCE(SUM(quantity),0) AS qty,
                        COALESCE(SUM(total),0)    AS rev,
                        COALESCE(AVG(price),0)    AS avg_price
                 FROM sales
                 {$where}
                 GROUP BY medicine_name
                 ORDER BY rev DESC
                 LIMIT 10");
$top_rev = $toprev ? $toprev->fetch_all(MYSQLI_ASSOC) : [];

// ================= Top by Quantity =================
$topq = q($db,"SELECT medicine_name,
                      COALESCE(SUM(quantity),0) AS qty,
                      COALESCE(SUM(total),0)    AS rev,
                      COALESCE(AVG(price),0)    AS avg_price
               FROM sales
               {$where}
               GROUP BY medicine_name
               ORDER BY qty DESC
               LIMIT 10");
$top_qty = $topq ? $topq->fetch_all(MYSQLI_ASSOC) : [];

// ================= Recent Sales =================
$recent_sql = "SELECT sale_id, medicine_name, quantity, price, total, sale_date
               FROM sales
               {$where}
               ORDER BY sale_date DESC";
if (!$useFilter) $recent_sql .= " LIMIT 100";
$recent_res   = q($db,$recent_sql);
$recent_sales = $recent_res ? $recent_res->fetch_all(MYSQLI_ASSOC) : [];

// Export base
$exportBase = 'export_sales.php?csrf='.urlencode($csrf).($useFilter ? '&from='.urlencode($fromDate).'&to='.urlencode($toDate) : '');
include __DIR__ . "/header.php";
include __DIR__ . "/navbar.php";
?>
<!doctype html>
<html lang="bn">
<head>
<meta charset="utf-8">
<title>Sales Report</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<style>
.grid-4{display:grid;grid-template-columns:repeat(auto-fill,minmax(220px,1fr));gap:12px;}
.kpi-card{border:1px solid #eef2f7;border-radius:12px;padding:12px;background:#fff;cursor:pointer;user-select:none;transition:box-shadow .15s, transform .05s;}
.kpi-card:hover{box-shadow:0 6px 18px rgba(0,0,0,.06);}
.kpi-card:active{transform:scale(.99);}
.kpi-hint{font-size:12px;color:#64748b;margin-top:4px;}
.collapse-section{display:none;}
.collapse-section.show{display:block;}
@media print{ body * { visibility: hidden !important; } .collapse-section.show, .collapse-section.show * { visibility: visible !important; } .collapse-section.show { position: absolute; left:0; top:0; width:100%; } }
</style>
</head>
<body class="bg-light">
<div class="container my-4">
  <h2>💵 Sales Report</h2>
  <div class="mb-3">
   
    <a class="btn btn-secondary" href="medicine_list.php">← Back to List</a>
  </div>

  <!-- Filter -->
  <form method="post" class="d-flex gap-2 align-items-end mb-3">
    <div>
      <label class="form-label mb-1">From</label>
      <input type="date" name="from_date" value="<?= h($fromDate) ?>" class="form-control">
    </div>
    <div>
      <label class="form-label mb-1">To</label>
      <input type="date" name="to_date" value="<?= h($toDate) ?>" class="form-control">
    </div>
    <div>
      <button type="submit" class="btn btn-primary">Filter</button>
      <?php if($useFilter): ?>
        <a class="btn btn-outline-secondary" href="sales_report.php">Reset</a>
      <?php endif; ?>
    </div>
    <div class="ms-auto">
      <a class="btn btn-success" href="<?= $exportBase ?>&section=summary">📄 Export Summary PDF</a>
    </div>
  </form>

  <?php if ($debug): ?>
    <div class="alert alert-warning">
      <b>DEBUG</b><br>
      WHERE: <code><?= h($where ?: '(none)') ?></code><br>
      Total Orders: <?= (int)$total_orders ?> |
      Total Qty: <?= (int)$total_qty ?> |
      Total Rev: <?= number_format($total_rev,2) ?><br>
      Last SQL Error: <code><?= h($last_err ?: 'n/a') ?></code>
    </div>
  <?php endif; ?>

  <!-- KPIs -->
  <div class="grid-4 mb-3">
    <div class="kpi-card kpi-click" data-target="#sec-by-day">
      <div>Total Orders</div>
      <div class="fs-4 fw-bold"><?= number_format($total_orders) ?></div>
      <div class="kpi-hint">Click to view by day</div>
    </div>
    <div class="kpi-card kpi-click" data-target="#sec-top-qty">
      <div>Total Qty</div>
      <div class="fs-4 fw-bold"><?= number_format($total_qty) ?></div>
      <div class="kpi-hint">Top items by qty</div>
    </div>
    <div class="kpi-card kpi-click" data-target="#sec-top-rev">
      <div>Total Revenue</div>
      <div class="fs-4 fw-bold">৳ <?= number_format($total_rev,2) ?></div>
      <div class="kpi-hint">Top items by revenue</div>
    </div>
    <div class="kpi-card kpi-click" data-target="#sec-recent">
      <div>Avg Order Value</div>
      <div class="fs-4 fw-bold">৳ <?= number_format($avg_order,2) ?></div>
      <div class="kpi-hint">Recent sales</div>
    </div>
  </div>

  <!-- Sales by day -->
  <div id="sec-by-day" class="card mb-3 collapse-section">
    <div class="card-header d-flex justify-content-between align-items-center">
      <span>Sales by Day <?= $useFilter ? "(Filtered)" : "" ?></span>
      <a class="btn btn-sm btn-danger" href="<?= $exportBase ?>&section=byday">📄 PDF</a>
    </div>
    <div class="card-body p-0">
      <table class="table table-sm m-0">
        <tr><th>Date</th><th>Orders</th><th>Qty</th><th>Revenue (৳)</th></tr>
        <?php if($sales_by_day): foreach($sales_by_day as $r): ?>
          <tr>
            <td><?= h($r['d']) ?></td>
            <td><?= (int)$r['orders'] ?></td>
            <td><?= (int)$r['qty'] ?></td>
            <td>৳ <?= number_format($r['rev'],2) ?></td>
          </tr>
        <?php endforeach; else: ?>
          <tr><td colspan="4">No data<?= $useFilter ? ' for selected dates' : '' ?></td></tr>
        <?php endif; ?>
      </table>
    </div>
  </div>

  <!-- Top by revenue -->
  <div id="sec-top-rev" class="card mb-3 collapse-section">
    <div class="card-header d-flex justify-content-between align-items-center">
      <span>Top 10 Medicines by Revenue <?= $useFilter ? "(Filtered)" : "" ?></span>
      <a class="btn btn-sm btn-danger" href="<?= $exportBase ?>&section=toprev">📄 PDF</a>
    </div>
    <div class="card-body p-0">
      <table class="table table-sm m-0">
        <tr><th>Medicine</th><th>Qty</th><th>Avg Price</th><th>Revenue</th></tr>
        <?php if($top_rev): foreach($top_rev as $r): ?>
          <tr>
            <td><?= h($r['medicine_name']) ?></td>
            <td><?= (int)$r['qty'] ?></td>
            <td>৳ <?= number_format($r['avg_price'],2) ?></td>
            <td>৳ <?= number_format($r['rev'],2) ?></td>
          </tr>
        <?php endforeach; else: ?>
          <tr><td colspan="4">No data<?= $useFilter ? ' for selected dates' : '' ?></td></tr>
        <?php endif; ?>
      </table>
    </div>
  </div>

  <!-- Top by quantity -->
  <div id="sec-top-qty" class="card mb-3 collapse-section">
    <div class="card-header d-flex justify-content-between align-items-center">
      <span>Top 10 Medicines by Quantity <?= $useFilter ? "(Filtered)" : "" ?></span>
      <a class="btn btn-sm btn-danger" href="<?= $exportBase ?>&section=topqty">📄 PDF</a>
    </div>
    <div class="card-body p-0">
      <table class="table table-sm m-0">
        <tr><th>Medicine</th><th>Qty</th><th>Avg Price</th><th>Revenue</th></tr>
        <?php if($top_qty): foreach($top_qty as $r): ?>
          <tr>
            <td><?= h($r['medicine_name']) ?></td>
            <td><?= (int)$r['qty'] ?></td>
            <td>৳ <?= number_format($r['avg_price'],2) ?></td>
            <td>৳ <?= number_format($r['rev'],2) ?></td>
          </tr>
        <?php endforeach; else: ?>
          <tr><td colspan="4">No data<?= $useFilter ? ' for selected dates' : '' ?></td></tr>
        <?php endif; ?>
      </table>
    </div>
  </div>

  <!-- Recent sales -->
  <div id="sec-recent" class="card mb-3 collapse-section">
    <div class="card-header d-flex justify-content-between align-items-center">
      <span>Recent Sales <?= $useFilter ? "(Filtered)" : "(Latest 100)" ?></span>
      <a class="btn btn-sm btn-danger" href="<?= $exportBase ?>&section=recent">📄 PDF</a>
    </div>
    <div class="card-body p-0">
      <table class="table table-sm m-0">
        <tr><th>ID</th><th>Medicine</th><th>Qty</th><th>Price</th><th>Total</th><th>Date</th></tr>
        <?php if($recent_sales): foreach($recent_sales as $r): ?>
          <tr>
            <td><?= h($r['sale_id']) ?></td>
            <td><?= h($r['medicine_name']) ?></td>
            <td><?= (int)$r['quantity'] ?></td>
            <td>৳ <?= number_format($r['price'],2) ?></td>
            <td>৳ <?= number_format($r['total'],2) ?></td>
            <td><?= h($r['sale_date']) ?></td>
          </tr>
        <?php endforeach; else: ?>
          <tr><td colspan="6">No sales found<?= $useFilter ? ' for selected dates' : '' ?></td></tr>
        <?php endif; ?>
      </table>
    </div>
  </div>

</div>

<script>
(function(){
  const cards=document.querySelectorAll('.kpi-click');
  const sections=document.querySelectorAll('.collapse-section');
  function hideAll(){sections.forEach(s=>s.classList.remove('show'));}
  cards.forEach(card=>{
    card.addEventListener('click',()=>{
      const target=document.querySelector(card.getAttribute('data-target'));
      if(!target) return;
      const willShow=!target.classList.contains('show');
      hideAll();
      if(willShow){ target.classList.add('show'); setTimeout(()=>target.scrollIntoView({behavior:'smooth'}),50); }
    });
  });
})();
</script>
</body>
</html>
