<?php
// Reuse global $db from config
if (!isset($db) || !($db instanceof mysqli) || $db->connect_errno) {
  require_once __DIR__ . "/config.php";
}
?>
<div class="navbar">
  <a href="home.php">Home</a>

  <div class="dropdown">
    <span class="dropdown-btn">Registration</span>
    <div class="dropdown-content">
      <a href="user.php">User Registration</a>
      <a href="emp_reg.php">Employee Registration</a>
    </div>
  </div>

  <a href="medicine_insert.php">Medicine Insert</a>
  <div class="dropdown">
    <span class="dropdown-btn">View</span>
    <div class="dropdown-content">
      <a href="user_view.php">View User</a>
      <a href="emp_view.php">View Employee</a>
      <a href="medicine_list.php">Medicine List</a>
    </div>
  </div>

  <a href="catagory.php">Medicine</a>
 
  <?php
  if ($db && !$db->connect_errno) {
    if ($res = $db->query("SELECT DISTINCT category FROM medicines ORDER BY category")) {
      while ($row = $res->fetch_assoc()) {
        $cat = $row['category'];
        if ($cat === null || $cat === '') continue;
        // exclude some categories from showing in navbar
        if (in_array(strtolower($cat), ['baby & mom care','pharma','beauty'])) continue;
        echo '<a href="catagory.php?category=' . urlencode($cat) . '">' .
             htmlspecialchars($cat, ENT_QUOTES, 'UTF-8') . '</a>';
      }
      $res->free();
    }
  }
  ?>
</div>
