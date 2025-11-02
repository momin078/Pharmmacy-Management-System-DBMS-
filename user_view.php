<?php
require_once "config.php";

// Update
if (isset($_POST['update_user'])) {
  $id=(int)$_POST['id']; 
  $name=$_POST['name']; 
  $email=$_POST['email']; 
  $contact=$_POST['contact']; 
  $address=$_POST['address'];

  $stmt=$db->prepare("UPDATE users SET name=?, email=?, contact=?, address=? WHERE id=?");
  $stmt->bind_param("ssssi",$name,$email,$contact,$address,$id);
  $stmt->execute();
}

// Delete
if (isset($_GET['delete'])) {
  $id=(int)$_GET['delete'];
  $stmt=$db->prepare("DELETE FROM users WHERE id=?");
  $stmt->bind_param("i",$id); 
  $stmt->execute();
}

// ---- Search only by ID ----
$q = trim($_GET['q'] ?? '');

if ($q !== '' && ctype_digit($q)) {
  $stmt = $db->prepare("SELECT id,name,email,contact,address FROM users WHERE id=?");
  $stmt->bind_param("i",$q);
  $stmt->execute();
  $result = $stmt->get_result();
} else {
  $result = $db->query("SELECT id,name,email,contact,address FROM users ORDER BY id DESC");
}

function h($s){ return htmlspecialchars((string)$s,ENT_QUOTES,'UTF-8'); }
?>

<?php include "header.php"; ?>
<?php include "navbar.php"; ?>

<style>
.table-container{width:95%;margin:30px auto;background:#fff;padding:20px;border-radius:10px;
  box-shadow:0 4px 12px rgba(0,0,0,.15)}
.header-row{display:flex;justify-content:space-between;align-items:center;margin-bottom:15px;flex-wrap:wrap}
h2{color:#009688;margin:0}
.search-form{display:flex;gap:8px;align-items:center}
.search-form input[type=number]{padding:8px 10px;border:1px solid #ccc;border-radius:6px;min-width:150px}
.search-form button{padding:8px 12px;border:none;border-radius:6px;background:#009688;color:#fff;cursor:pointer}
.search-form a.reset{padding:8px 12px;border:1px solid #ddd;border-radius:6px;text-decoration:none;color:#333;background:#fafafa}
table{width:100%;border-collapse:collapse;margin-top:10px}
th,td{padding:10px;text-align:center;border:1px solid #ddd}
th{background:#009688;color:#fff}
.update-btn{background:#009688;color:#fff;border:none;padding:6px 12px;border-radius:5px;cursor:pointer}
.delete-btn{background:#e74c3c;color:#fff;padding:6px 12px;border-radius:5px;text-decoration:none}
.export-btn{background:#2563eb;color:#fff;padding:6px 8px;border-radius:5px;text-decoration:none;display:inline-block;margin:2px 0}
.export-btn.doc{background:#0ea5e9}
.modal{display:none;position:fixed;left:0;top:0;width:100%;height:100%;background:rgba(0,0,0,.5)}
.modal-content{background:#fff;margin:8% auto;padding:20px;border-radius:10px;width:400px;position:relative}
.modal-content .close{position:absolute;top:10px;right:12px;cursor:pointer;font-size:20px}
</style>

<div class="table-container">
  <div class="header-row">
    <h2>Registered Users</h2>
    <!-- Search by ID -->
    <form class="search-form" method="get" action="">
      <input type="number" name="q" value="<?= h($q) ?>" placeholder="Search by User ID">
      <button type="submit">Search</button>
      <?php if($q!==''): ?>
        <a class="reset" href="view_users.php">Reset</a>
      <?php endif; ?>
    </form>
  </div>

  <table>
    <tr>
      <th>User ID</th>
      <th>Name</th>
    
      <th>Email</th>
      <th>Contact</th>
      <th>Address</th>
      <th>Action</th>
      <th>Export</th>
    </tr>
    <?php if ($result && $result->num_rows>0): while($row=$result->fetch_assoc()): ?>
      <tr>
        <td><?= h($row['id']) ?></td>
        <td><?= h($row['name']) ?></td>
  
        <td><?= h($row['email']) ?></td>
        <td><?= h($row['contact']) ?></td>
        <td><?= h($row['address']) ?></td>
        <td>
          <button class="update-btn"
            onclick="openModal('<?= h($row['id']) ?>','<?= h($row['name']) ?>','<?= h($row['email']) ?>','<?= h($row['contact']) ?>','<?= h($row['address']) ?>')">
            Update
          </button>
          <a class="delete-btn" href="?delete=<?= h($row['id']) ?>" onclick="return confirm('Are you sure?')">Delete</a>
        </td>
        <td>
          <a class="export-btn" href="export_user.php?format=pdf&id=<?= h($row['id']) ?>">PDF</a>
         
        </td>
      </tr>
    <?php endwhile; else: ?>
      <tr><td colspan="7">No users found</td></tr>
    <?php endif; ?>
  </table>
</div>

<!-- Modal -->
<div id="userModal" class="modal">
  <div class="modal-content">
    <span class="close" onclick="document.getElementById('userModal').style.display='none'">&times;</span>
    <h3>Update User</h3>
    <form method="post">
      <input type="hidden" name="id" id="uid">
      <label>Name:</label><input type="text" name="name" id="uname" required style="width:100%;padding:8px;margin:5px 0;">
      <label>Email:</label><input type="email" name="email" id="uemail" required style="width:100%;padding:8px;margin:5px 0;">
      <label>Contact:</label><input type="text" name="contact" id="ucontact" required style="width:100%;padding:8px;margin:5px 0;">
      <label>Address:</label><input type="text" name="address" id="uaddress" required style="width:100%;padding:8px;margin:5px 0;">
      <button type="submit" name="update_user" style="background:#009688;color:#fff;padding:10px 20px;border:none;border-radius:5px;">Save</button>
    </form>
  </div>
</div>

<script>
function openModal(id,name,email,contact,address){
  document.getElementById('uid').value = id;
  document.getElementById('uname').value = name;
  document.getElementById('uemail').value = email;
  document.getElementById('ucontact').value = contact;
  document.getElementById('uaddress').value = address;
  document.getElementById('userModal').style.display='block';
}
</script>

<?php include "footer.php"; ?>
