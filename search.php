<?php require_once "config.php"; if (session_status()===PHP_SESSION_NONE) session_start(); ?>
<?php include "header.php"; ?>
<?php include "navbar.php"; ?>

<?php
$query = trim($_GET['query'] ?? $_GET['q'] ?? '');
$category = trim($_GET['category'] ?? '');
?>
<div style="width:90%; margin:30px auto; background:#fff; padding:20px; border-radius:10px; box-shadow:0 4px 15px rgba(0,0,0,0.2);">
  <h2 style="text-align:center; color:#009688; margin-bottom:20px;">
    Medicine Search Results for "<?= h($query) ?>"
    <?php if($category!=='') echo ' in '.h($category); ?>
  </h2>

<?php if($query): ?>
<?php
  if (ctype_digit($query)) {
    $stmt = $db->prepare("SELECT id,name,category,`group`,company,price,quantity FROM medicines WHERE id = ?");
    $stmt->bind_param("i",$query);
  } else {
    $sql = "SELECT id,name,category,`group`,company,price,quantity FROM medicines WHERE (name LIKE ? OR category LIKE ? OR `group` LIKE ? OR company LIKE ?)";
    $types = "ssss";
    $params = ["%$query%","%$query%","%$query%","%$query%"];
    if ($category !== '') {
      $sql .= " AND category = ?";
      $types .= "s";
      $params[] = $category;
    }
    $stmt = $db->prepare($sql);
    $stmt->bind_param($types, ...$params);
  }
  $stmt->execute();
  $res = $stmt->get_result();
  if($res->num_rows>0){
    echo "<table border='1' width='100%' cellpadding='10'>
      <tr>
        <th>ID</th><th>Name</th><th>Category</th><th>Group</th>
        <th>Company</th><th>Price</th><th>Quantity</th>
      </tr>";
    while($r=$res->fetch_assoc()){
      echo "<tr>
        <td>".h($r['id'])."</td>
        <td>".h($r['name'])."</td>
        <td>".h($r['category'])."</td>
        <td>".h($r['`group`'] ?? $r['group'])."</td>
        <td>".h($r['company'])."</td>
        <td>".h($r['price'])."</td>
        <td>".h($r['quantity'])."</td>
      </tr>";
    }
    echo "</table>";
  } else {
    echo "<p style='color:red; text-align:center;'>No medicines found!</p>";
  }
  $stmt->close();
?>
<?php else: ?>
  <p style="text-align:center;">Please enter a search query.</p>
<?php endif; ?>
</div>
<?php include "footer.php"; ?>
