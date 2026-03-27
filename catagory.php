<?php
// medicines.php — All medicines listing (search + pagination) with screenshot-style cards
$BASE = __DIR__;
require_once $BASE . '/config.php';
if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }

/* ---------- Helpers ---------- */
function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function getv($k,$d=null){ return isset($_GET[$k]) ? trim($_GET[$k]) : $d; }
function intget($k,$d=0){ return (isset($_GET[$k]) && $_GET[$k] !== '') ? (int)$_GET[$k] : $d; }
function qs(array $overrides=[]){
  $merged = array_merge($_GET,$overrides);
  foreach($merged as $k=>$v){ if($v==='' || $v===null) unset($merged[$k]); }
  return http_build_query($merged, '', '&', PHP_QUERY_RFC3986);
}
function price_fmt($n){
  $n = (float)$n; $dec = (floor($n) == $n) ? 0 : 2;
  return '৳'.number_format($n, $dec);
}

/* ---------- CSRF (robust) ---------- */
if (!function_exists('m_secure_random')) {
  function m_secure_random($len = 32) {
    if (function_exists('random_bytes')) return random_bytes($len);
    if (function_exists('openssl_random_pseudo_bytes')) return openssl_random_pseudo_bytes($len);
    $buf = ''; for ($i=0;$i<$len;$i++) $buf .= chr(mt_rand(0,255)); return $buf;
  }
}
if (!function_exists('hash_equals')) {
  function hash_equals($a,$b){
    if (!is_string($a)||!is_string($b)||strlen($a)!==strlen($b)) return false;
    $res=$a^$b; $ret=0; for($i=strlen($res)-1;$i>=0;$i--) $ret|=ord($res[$i]); return $ret===0;
  }
}
if (empty($_SESSION['csrf']) || strlen($_SESSION['csrf']) < 32) {
  $_SESSION['csrf'] = bin2hex(m_secure_random(32));
}

/* ---------- Add to Bag ---------- */
if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['add_to_bag'])) {
  $posted = (string)($_POST['csrf'] ?? '');
  if ($posted==='' || !hash_equals($_SESSION['csrf'] ?? '', $posted)) {
    $_SESSION['err'] = 'Security check failed.'; header("Location: ".$_SERVER['REQUEST_URI']); exit;
  }
  $id = (int)($_POST['id'] ?? 0);
  if ($id>0 && $db instanceof mysqli && !$db->connect_errno) {
    $q = $db->prepare("SELECT id,name,quantity FROM medicines WHERE id=? AND status='ACTIVE' LIMIT 1");
    $q->bind_param("i",$id); $q->execute(); $row = $q->get_result()->fetch_assoc();
    if ($row) {
      if (!isset($_SESSION['cart'])) $_SESSION['cart'] = [];
      $cur = (int)($_SESSION['cart'][$id]['qty'] ?? 0); $new = $cur+1;
      if ((int)$row['quantity'] >= $new) {
        $_SESSION['cart'][$id] = ['id'=>$id,'qty'=>$new];
        $_SESSION['msg'] = "Added to bag: ".$row['name'];
      } else { $_SESSION['err'] = "Insufficient stock. Available: ".(int)$row['quantity']; }
    } else { $_SESSION['err'] = "Unavailable item."; }
  }
  header("Location: ".$_SERVER['REQUEST_URI']); exit;
}

/* ---------- Inputs (only search + optional sort/limit via URL) ---------- */
$search = getv('q', getv('query',''));   // header থেকে name="q"; fallback: query
$sort   = getv('sort','popular');        // UI নেই, কিন্তু URL-এ দিলে কাজ করবে
$page   = max(1, intget('page',1));
$limit  = max(12, min(60, intget('limit',24)));
$offset = ($page-1)*$limit;

/* ---------- Build filters & order ---------- */
$orderSql = "m.view_count DESC, m.sold_count DESC, m.name ASC";
if ($sort==='price_asc')  $orderSql = "m.price ASC, m.name ASC";
if ($sort==='price_desc') $orderSql = "m.price DESC, m.name ASC";
if ($sort==='name')       $orderSql = "m.name ASC";

$where = "m.status='ACTIVE'";
$types = ""; $params = [];

if ($search!=='') {
  $where .= " AND (m.name LIKE CONCAT('%',?,'%') OR m.`group` LIKE CONCAT('%',?,'%') OR m.category LIKE CONCAT('%',?,'%'))";
  $types .= "sss"; $params[]=$search; $params[]=$search; $params[]=$search;
}

/* ---------- Count ---------- */
$total = 0;
if ($db instanceof mysqli && !$db->connect_errno) {
  $sqlCount = "SELECT COUNT(*) c FROM medicines m WHERE $where";
  $stmt = $db->prepare($sqlCount);
  if ($types!=="") $stmt->bind_param($types, ...$params);
  $stmt->execute(); $total = (int)($stmt->get_result()->fetch_assoc()['c'] ?? 0); $stmt->close();
}
$totalPages = max(1, (int)ceil($total/$limit));
$page = min($page, $totalPages); $offset = ($page-1)*$limit;

/* ---------- Data ---------- */
$rows = [];
if ($db instanceof mysqli && !$db->connect_errno) {
  $sql = "SELECT m.id, m.name, m.`group`, m.price, m.quantity, m.image, m.category
          FROM medicines m
          WHERE $where
          ORDER BY $orderSql
          LIMIT ?, ?";
  $types2 = $types . "ii"; $params2 = $params; $params2[] = $offset; $params2[] = $limit;
  $stmt = $db->prepare($sql); $stmt->bind_param($types2, ...$params2);
  $stmt->execute(); $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC); $stmt->close();
}

/* ---------- Render ---------- */
require_once $BASE . '/header.php';
require_once $BASE . '/navbar.php';
?>
<style>
.wrap{max-width:1280px;margin:16px auto;padding:0 16px}
/* Alerts */
.alert{padding:10px 12px;border-radius:10px;margin:10px 0}
.alert-success{background:#ecfdf5;color:#065f46;border:1px solid #a7f3d0}
.alert-danger{background:#fef2f2;color:#991b1b;border:1px solid #fecaca}
/* Product cards — screenshot style */
.prod-grid{display:grid;grid-template-columns:repeat(5,minmax(0,1fr));gap:24px}
@media (max-width:1200px){.prod-grid{grid-template-columns:repeat(4,1fr)}}
@media (max-width:992px){ .prod-grid{grid-template-columns:repeat(3,1fr)}}
@media (max-width:768px){ .prod-grid{grid-template-columns:repeat(2,1fr)}}
@media (max-width:480px){ .prod-grid{grid-template-columns:repeat(1,1fr)}}
.prod-card{background:#fff;border:0;border-radius:18px;overflow:hidden;box-shadow:0 10px 30px rgba(0,0,0,.08)}
.prod-media{position:relative;padding:20px 20px 0 20px;height:220px;display:flex;align-items:flex-end;justify-content:center}
.prod-media img{max-width:100%;max-height:190px;object-fit:contain;display:block;filter:drop-shadow(0 6px 16px rgba(0,0,0,.12))}
.brand-badge{position:absolute;left:16px;top:16px;background:#fff;border-radius:10px;border:1px solid #e6e6e6;width:36px;height:36px;display:grid;place-items:center;font-weight:700;color:#0f7a3a}
.prod-body{padding:12px 18px 18px 18px}
.p-title{font-weight:800;font-size:18px;margin:0 0 4px 0;letter-spacing:.2px}
.p-generic{font-style:italic;color:#6b7280;font-size:14px;margin:0 0 10px 0;text-transform:uppercase;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.p-price{color:#0f7a3a;font-weight:900;font-size:20px;margin:0 0 12px 0}
.add-btn{width:100%;display:flex;align-items:center;justify-content:center;gap:10px;border:2px solid #0f7a3a;color:#0f7a3a;background:#fff;border-radius:14px;padding:12px 14px;font-weight:700;font-size:18px;cursor:pointer;transition:.15s}
.add-btn:hover{background:#0f7a3a;color:#fff}
.add-btn:disabled{opacity:.6;cursor:not-allowed;background:#f7f7f7;color:#7c7c7c;border-color:#cfcfcf}
.oos{opacity:.85;filter:grayscale(.15)}
.oos-note{color:#b91c1c;font-size:12px;margin-top:6px;text-align:center}
/* Pagination */
.pager{display:flex;gap:6px;justify-content:flex-end;flex-wrap:wrap;margin-top:18px}
.pager a,.pager span{border:1px solid #d1d5db;border-radius:8px;padding:6px 10px;color:#111;text-decoration:none}
.pager a.active{background:#111;color:#fff;border-color:#111}
.pager span.disabled{opacity:.5}
</style>

<div class="wrap">
  <div style="display:flex;justify-content:space-between;align-items:center;gap:8px">
    <h2 style="margin:0">All Medicines</h2>
    <div style="min-height:28px">
      <?php if(!empty($_SESSION['msg'])): ?><div class="alert alert-success"><?=h($_SESSION['msg']); unset($_SESSION['msg']);?></div><?php endif; ?>
      <?php if(!empty($_SESSION['err'])): ?><div class="alert alert-danger"><?=h($_SESSION['err']); unset($_SESSION['err']);?></div><?php endif; ?>
    </div>
  </div>

  <?php if($search!==''): ?>
    <div style="margin:8px 0; font-size:13px; color:#374151;">
      Results for: <strong><?=h($search)?></strong>
    </div>
  <?php endif; ?>

  <?php if(!$rows): ?>
    <div class="alert alert-danger">No medicines found.</div>
  <?php else: ?>
    <div class="prod-grid">
      <?php foreach($rows as $p):
        $imgFile = !empty($p['image']) ? basename((string)$p['image']) : '';
        $img = $imgFile ? 'uploads/'.rawurlencode($imgFile)
                        : 'https://via.placeholder.com/600x400?text=No+Image';
        $oos = ((int)$p['quantity']<=0);
        $title = trim($p['name'] ?? '');
        $generic = trim($p['group'] ?? '');
      ?>
      <div class="prod-card <?=$oos?'oos':''?>">
        <div class="prod-media">
          <span class="brand-badge">LP</span>
          <img src="<?=$img?>" alt="<?=h($title)?>">
        </div>
        <div class="prod-body">
          <div class="p-title"><?=h($title)?></div>
          <div class="p-generic"><?=h($generic)?></div>
          <div class="p-price"><?=price_fmt($p['price'])?></div>

          <form method="post">
            <input type="hidden" name="id" value="<?=$p['id']?>">
            <input type="hidden" name="csrf" value="<?=h($_SESSION['csrf'])?>">
            <button type="submit" class="add-btn" name="add_to_bag" <?=$oos?'disabled aria-disabled="true"':''?>>
              <svg width="22" height="22" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
                <path d="M7 18a2 2 0 1 0 0 4 2 2 0 0 0 0-4Zm10 0a2 2 0 1 0 .001 4.001A2 2 0 0 0 17 18ZM6.2 6l.27 1.5h11.65a1 1 0 0 1 .98 1.2l-1.1 5.5a2 2 0 0 1-1.97 1.6H8.22a2 2 0 0 1-1.97-1.6L5 5H3a1 1 0 1 1 0-2h3a1 1 0 0 1 .98.8L7.3 6H6.2Z"/>
              </svg>
              Add to Bag
            </button>
            <?php if($oos): ?><div class="oos-note">Out of stock</div><?php endif; ?>
          </form>
        </div>
      </div>
      <?php endforeach; ?>
    </div>

    <?php if($totalPages>1): ?>
      <div class="pager">
        <?php
          $prevDisabled = $page<=1;  $nextDisabled = $page>=$totalPages;
          if($prevDisabled) echo '<span class="disabled">« Prev</span>';
          else echo '<a href="?'.h(qs(['page'=>max(1,$page-1)])).'">« Prev</a>';

          $window=2; $start=max(1,$page-$window); $end=min($totalPages,$page+$window);
          if($start>1){ echo '<a href="?'.h(qs(['page'=>1])).'">1</a>'; if($start>2) echo '<span class="disabled">…</span>'; }
          for($p=$start;$p<=$end;$p++){
            $active = $p==$page ? ' class="active"' : '';
            echo '<a'.$active.' href="?'.h(qs(['page'=>$p])).'">'.$p.'</a>';
          }
          if($end<$totalPages){
            if($end<$totalPages-1) echo '<span class="disabled">…</span>';
            echo '<a href="?'.h(qs(['page'=>$totalPages])).'">'.$totalPages.'</a>';
          }

          if($nextDisabled) echo '<span class="disabled">Next »</span>';
          else echo '<a href="?'.h(qs(['page'=>min($totalPages,$page+1)])).'">Next »</a>';
        ?>
      </div>
    <?php endif; ?>
  <?php endif; ?>

  <div style="margin-top:14px;display:flex;gap:8px">
    <a href="home.php" class="add-btn" style="border-color:#111;color:#111">← Back to Home</a>
  </div>
</div>

<?php require_once $BASE . '/footer.php'; ?>
