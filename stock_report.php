<?php
require_once "config.php";
if (session_status()===PHP_SESSION_NONE) session_start();
date_default_timezone_set('Asia/Dhaka');

function h($v){ return htmlspecialchars((string)$v,ENT_QUOTES,'UTF-8'); }

// KPI calculations
$item_count   = $db->query("SELECT COUNT(*) FROM medicines")->fetch_row()[0];
$sum          = $db->query("SELECT SUM(quantity), SUM(quantity*price) FROM medicines")->fetch_row();
$total_qty    = $sum[0] ?? 0;
$total_value  = $sum[1] ?? 0.0;

// Low stock: Qty < 20
$low          = $db->query("SELECT COUNT(*), SUM(quantity) FROM medicines WHERE quantity < 20")->fetch_row();
$low_items    = $low[0] ?? 0;
$low_qty      = $low[1] ?? 0;

// Expiring / Expired
$expiring_items = $db->query("SELECT COUNT(*) FROM medicines WHERE expire_date IS NOT NULL AND expire_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(),INTERVAL 60 DAY)")->fetch_row()[0];
$expired_items  = $db->query("SELECT COUNT(*) FROM medicines WHERE expire_date IS NOT NULL AND expire_date<CURDATE()")->fetch_row()[0];

// Detailed queries
$cat_rows     = $db->query("SELECT category, SUM(quantity) as qty FROM medicines GROUP BY category ORDER BY qty DESC")->fetch_all(MYSQLI_ASSOC);
$top_value    = $db->query("SELECT name, quantity, price, (quantity*price) as value FROM medicines ORDER BY value DESC LIMIT 10")->fetch_all(MYSQLI_ASSOC);
$low_rows     = $db->query("SELECT id, name, quantity, reorder_level FROM medicines WHERE quantity < 20 ORDER BY quantity ASC LIMIT 20")->fetch_all(MYSQLI_ASSOC);
$expiring_rows= $db->query("SELECT id, name, expire_date, quantity FROM medicines WHERE expire_date IS NOT NULL AND expire_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(),INTERVAL 60 DAY)")->fetch_all(MYSQLI_ASSOC);
$expired_rows = $db->query("SELECT id, name, expire_date, quantity FROM medicines WHERE expire_date IS NOT NULL AND expire_date<CURDATE()")->fetch_all(MYSQLI_ASSOC);
$all_rows     = $db->query("SELECT id, name, category, quantity, price FROM medicines ORDER BY id DESC LIMIT 50")->fetch_all(MYSQLI_ASSOC);

// PDF export base
$exportBase = 'export_stock.php?csrf='.urlencode($_SESSION['csrf']);
include __DIR__ . "/header.php";
include __DIR__ . "/navbar.php";
?>
<!doctype html>
<html lang="bn">
<head>
<meta charset="utf-8"><title>Stock Report</title>
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
  <h2>📊 Medicine Stock Report</h2>
  <div class="mb-3">
   
    <a class="btn btn-secondary" href="medicine_list.php">← Back to List</a>
  </div>

  <div class="grid-4 mb-3">
    <div class="kpi-card kpi-click" data-target="#sec-items"><div>Items</div><div class="fs-4 fw-bold"><?=number_format($item_count)?></div><div class="kpi-hint">Click to view details</div></div>
    <div class="kpi-card kpi-click" data-target="#sec-cat"><div>Total Qty</div><div class="fs-4 fw-bold"><?=number_format($total_qty)?></div><div class="kpi-hint">Category breakdown</div></div>
    <div class="kpi-card kpi-click" data-target="#sec-top-value"><div>Total Value</div><div class="fs-4 fw-bold">৳ <?=number_format($total_value,2)?></div><div class="kpi-hint">Top by stock value</div></div>
    <div class="kpi-card kpi-click" data-target="#sec-low"><div>Low Stock</div><div><b><?=$low_items?></b> items / <b><?=$low_qty?></b> qty</div><div class="kpi-hint">Qty &lt; 20</div></div>
  </div>

  <div class="grid-4 mb-4">
    <div class="kpi-card kpi-click" data-target="#sec-expiring"><div>Expiring ≤60d</div><div class="fs-4 fw-bold"><?=$expiring_items?></div><div class="kpi-hint">Expiring soon</div></div>
    <div class="kpi-card kpi-click" data-target="#sec-expired"><div>Expired</div><div class="fs-4 fw-bold"><?=$expired_items?></div><div class="kpi-hint">Expired items</div></div>
  </div>

  <!-- Category -->
  <div id="sec-cat" class="card mb-3 collapse-section">
    <div class="card-header d-flex justify-content-between align-items-center">
      <span>Category Breakdown</span>
      <a class="btn btn-sm btn-danger" href="<?= $exportBase ?>&section=cat">📄 PDF</a>
    </div>
    <div class="card-body p-0">
      <table class="table table-sm m-0"><tr><th>Category</th><th>Qty</th></tr>
        <?php foreach($cat_rows as $r): ?><tr><td><?=h($r['category'])?></td><td><?=$r['qty']?></td></tr><?php endforeach; ?>
      </table>
    </div>
  </div>

  <!-- Top value -->
  <div id="sec-top-value" class="card mb-3 collapse-section">
    <div class="card-header d-flex justify-content-between align-items-center">
      <span>Top 10 by Stock Value</span>
      <a class="btn btn-sm btn-danger" href="<?= $exportBase ?>&section=top">📄 PDF</a>
    </div>
    <div class="card-body p-0">
      <table class="table table-sm m-0"><tr><th>Name</th><th>Qty</th><th>Price</th><th>Value</th></tr>
      <?php foreach($top_value as $r): ?>
        <tr><td><?=h($r['name'])?></td><td><?=$r['quantity']?></td><td>৳<?=number_format($r['price'],2)?></td><td>৳<?=number_format($r['value'],2)?></td></tr>
      <?php endforeach; ?>
      </table>
    </div>
  </div>

  <!-- Low stock -->
  <div id="sec-low" class="card mb-3 collapse-section">
    <div class="card-header d-flex justify-content-between align-items-center">
      <span>Low Stock (Qty &lt; 20)</span>
      <a class="btn btn-sm btn-danger" href="<?= $exportBase ?>&section=low">📄 PDF</a>
    </div>
    <div class="card-body p-0">
      <table class="table table-sm m-0"><tr><th>ID</th><th>Name</th><th>Qty</th><th>Reorder Level</th></tr>
      <?php foreach($low_rows as $r): ?>
        <tr><td><?=$r['id']?></td><td><?=h($r['name'])?></td><td><?=$r['quantity']?></td><td><?=$r['reorder_level']?></td></tr>
      <?php endforeach; ?>
      </table>
    </div>
  </div>

  <!-- Expiring -->
  <div id="sec-expiring" class="card mb-3 collapse-section">
    <div class="card-header d-flex justify-content-between align-items-center">
      <span>Expiring ≤60 Days</span>
      <a class="btn btn-sm btn-danger" href="<?= $exportBase ?>&section=expiring">📄 PDF</a>
    </div>
    <div class="card-body p-0">
      <table class="table table-sm m-0"><tr><th>ID</th><th>Name</th><th>Expire</th><th>Qty</th></tr>
      <?php foreach($expiring_rows as $r): ?>
        <tr><td><?=$r['id']?></td><td><?=h($r['name'])?></td><td><?=$r['expire_date']?></td><td><?=$r['quantity']?></td></tr>
      <?php endforeach; ?>
      </table>
    </div>
  </div>

  <!-- Expired -->
  <div id="sec-expired" class="card mb-3 collapse-section">
    <div class="card-header d-flex justify-content-between align-items-center">
      <span>Expired</span>
      <a class="btn btn-sm btn-danger" href="<?= $exportBase ?>&section=expired">📄 PDF</a>
    </div>
    <div class="card-body p-0">
      <table class="table table-sm m-0"><tr><th>ID</th><th>Name</th><th>Expire</th><th>Qty</th></tr>
      <?php foreach($expired_rows as $r): ?>
        <tr><td><?=$r['id']?></td><td><?=h($r['name'])?></td><td><?=$r['expire_date']?></td><td><?=$r['quantity']?></td></tr>
      <?php endforeach; ?>
      </table>
    </div>
  </div>

  <!-- Full detailed list -->
  <div id="sec-items" class="card mb-3 collapse-section">
    <div class="card-header d-flex justify-content-between align-items-center">
      <span>Full Detailed List (Latest 50)</span>
      <a class="btn btn-sm btn-danger" href="<?= $exportBase ?>&section=items">📄 PDF</a>
    </div>
    <div class="card-body p-0">
      <table class="table table-sm m-0"><tr><th>ID</th><th>Name</th><th>Category</th><th>Qty</th><th>Price</th></tr>
      <?php foreach($all_rows as $r): ?>
        <tr><td><?=$r['id']?></td><td><?=h($r['name'])?></td><td><?=h($r['category'])?></td><td><?=$r['quantity']?></td><td>৳<?=number_format($r['price'],2)?></td></tr>
      <?php endforeach; ?>
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
