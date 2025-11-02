<?php
require_once "config.php";

/* Update (PRG) */
if (isset($_POST['update_employee'])) {
  $emp_id       = $_POST['emp_id'] ?? '';
  $emp_name     = trim($_POST['emp_name'] ?? '');
  $emp_email    = trim($_POST['emp_email'] ?? '');
  $emp_phone    = trim($_POST['emp_phone'] ?? '');
  $emp_position = trim($_POST['emp_position'] ?? '');
  $emp_address  = trim($_POST['emp_address'] ?? '');

  if ($emp_id !== '') {
    $stmt = $db->prepare("UPDATE employee SET emp_name=?, emp_email=?, emp_phone=?, emp_position=?, emp_address=? WHERE emp_id=?");
    $stmt->bind_param("ssssss", $emp_name,$emp_email,$emp_phone,$emp_position,$emp_address,$emp_id);
    $stmt->execute();
  }
  // Post-Redirect-Get
  $back = 'emp_view.php';
  if (!empty($_GET['q'])) { $back .= '?q=' . urlencode($_GET['q']); }
  header("Location: ".$back);
  exit;
}

/* Delete */
if (isset($_GET['delete'])) {
  $del = $_GET['delete'];
  if ($del !== '') {
    $stmt = $db->prepare("DELETE FROM employee WHERE emp_id=?");
    $stmt->bind_param("s", $del);
    $stmt->execute();
  }
}

/* Search by Employee ID only */
$q = trim($_GET['q'] ?? '');
if ($q !== '') {
  $stmt = $db->prepare("SELECT emp_id, emp_name, emp_email, emp_phone, emp_position, emp_address FROM employee WHERE emp_id=?");
  $stmt->bind_param("s", $q);
  $stmt->execute();
  $result = $stmt->get_result();
} else {
  $result = $db->query("SELECT emp_id, emp_name, emp_email, emp_phone, emp_position, emp_address FROM employee ORDER BY emp_id DESC");
}

function h($s){ return htmlspecialchars((string)$s,ENT_QUOTES,'UTF-8'); }
?>

<?php include "header.php"; ?>
<?php include "navbar.php"; ?>

<style>
.wrap{width:95%;margin:30px auto;background:#fff;padding:20px;border-radius:10px;box-shadow:0 4px 12px rgba(0,0,0,.15)}
.header-row{display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap}
h2{margin:0;color:#009688}
.search-form{display:flex;gap:8px;align-items:center}
.search-form input{padding:8px 10px;border:1px solid #ccc;border-radius:6px;min-width:180px}
.search-form button{padding:8px 12px;border:none;border-radius:6px;background:#009688;color:#fff;cursor:pointer}
.search-form a{padding:8px 12px;border:1px solid #ddd;border-radius:6px;text-decoration:none;color:#333;background:#fafafa}

.print-row{margin:10px 0}
.print-btn{padding:8px 14px;border:none;border-radius:6px;background:#374151;color:#fff;cursor:pointer}
.print-btn:hover{background:#111827}

table{width:100%;border-collapse:collapse;margin-top:12px; page-break-inside:auto}
th,td{border:1px solid #eaeaea;padding:10px;text-align:center}
th{background:#009688;color:#fff}
tr{page-break-inside:avoid; page-break-after:auto}

.btn{display:inline-block;padding:6px 12px;border-radius:5px;color:#fff;text-decoration:none}
.btn-edit{background:#007bff} .btn-del{background:#e74c3c}

/* Modal: scrollable + safe sizing */
.modal{display:none;position:fixed;left:0;top:0;width:100%;height:100%;background:rgba(0,0,0,.5);overflow:auto}
.modal .box{background:#fff;margin:5% auto;padding:20px;border-radius:10px;width:min(420px,92vw);max-height:90vh;overflow:auto;position:relative}
.modal .close{position:absolute;right:12px;top:10px;font-size:20px;cursor:pointer}
.modal input, .modal select, .modal textarea{width:100%;padding:8px;margin:6px 0;border:1px solid #ddd;border-radius:6px}
.modal button{background:#009688;color:#fff;border:none;padding:10px 16px;border-radius:6px;cursor:pointer}

/* Print: only the table prints */
@media print{
  body *{ visibility:hidden !important; }
  .wrap, .wrap *{ visibility:visible !important; }
  .wrap{ position:static; margin:0; box-shadow:none; width:100%; }

  .header-row .search-form,
  .print-row,
  .btn-edit, .btn-del,
  .modal, .modal *{ display:none !important; }

  .header, .navbar, .footer{ display:none !important; }

  h2{ margin:0 0 8px 0; }
}
</style>

<div class="wrap">
  <div class="header-row">
    <h2>Employee List</h2>
    <form class="search-form" method="get">
      <input type="text" name="q" value="<?= h($q) ?>" placeholder="Search by Employee ID">
      <button type="submit">Search</button>
      <?php if($q!==''): ?><a href="emp_view.php">Reset</a><?php endif; ?>
    </form>
  </div>

  <div class="print-row">
    <button type="button" class="print-btn" onclick="window.print()">🖨 Print Employee List</button>
  </div>

  <table>
    <tr>
      <th>ID</th><th>Name</th><th>Email</th><th>Phone</th><th>Position</th><th>Address</th><th>Action</th><th>Export</th>
    </tr>
    <?php if($result && $result->num_rows): while($row=$result->fetch_assoc()): ?>
      <tr>
        <td><?= h($row['emp_id']) ?></td>
        <td><?= h($row['emp_name']) ?></td>
        <td><?= h($row['emp_email']) ?></td>
        <td><?= h($row['emp_phone']) ?></td>
        <td><?= h($row['emp_position']) ?></td>
        <td><?= h($row['emp_address']) ?></td>
        <td>
          <button
            class="btn btn-edit"
            data-id="<?= h($row['emp_id']) ?>"
            data-name="<?= h($row['emp_name']) ?>"
            data-email="<?= h($row['emp_email']) ?>"
            data-phone="<?= h($row['emp_phone']) ?>"
            data-pos="<?= h($row['emp_position']) ?>"
            data-addr="<?= h($row['emp_address']) ?>"
            onclick="openEmp(this)">Update</button>
          <a class="btn btn-del" href="?delete=<?= h($row['emp_id']) ?>" onclick="return confirm('Delete this employee?')">Delete</a>
        </td>
        <td>
          <a class="btn" style="background:#2563eb;color:#fff;padding:6px 10px;border-radius:5px;text-decoration:none"
             href="export_emp.php?id=<?= urlencode($row['emp_id']) ?>">PDF</a>
        </td>
      </tr>
    <?php endwhile; else: ?>
      <tr><td colspan="8">No employees found</td></tr>
    <?php endif; ?>
  </table>
</div>

<!-- Modal -->
<div id="empModal" class="modal">
  <div class="box">
    <span class="close" onclick="empModal.style.display='none'">&times;</span>
    <h3>Update Employee</h3>
    <form method="post">
      <input type="hidden" name="emp_id" id="eid">
      <label>Name</label><input type="text" name="emp_name" id="ename" required>
      <label>Email</label><input type="email" name="emp_email" id="eemail" required>
      <label>Phone</label><input type="text" name="emp_phone" id="ephone" required>
      <label>Position</label>
      <select name="emp_position" id="epos" required>
        <option value="Manager">Manager</option>
        <option value="Pharmacist">Pharmacist</option>
        <option value="Salesman">Salesman</option>
        <option value="Delivery">Delivery</option>
      </select>
      <label>Address</label><textarea name="emp_address" id="eaddr" required></textarea>
      <button type="submit" name="update_employee">Save</button>
    </form>
  </div>
</div>

<script>
const empModal = document.getElementById('empModal');
function openEmp(btn){
  const d = btn.dataset;
  document.getElementById('eid').value   = d.id || '';
  document.getElementById('ename').value = d.name || '';
  document.getElementById('eemail').value= d.email || '';
  document.getElementById('ephone').value= d.phone || '';
  document.getElementById('epos').value  = d.pos || '';
  document.getElementById('eaddr').value = d.addr || '';
  empModal.style.display='block';
  setTimeout(()=>{ document.getElementById('ename').focus(); }, 0);
}
window.addEventListener('click',(e)=>{ if(e.target===empModal) empModal.style.display='none'; });
</script>

<?php include "footer.php"; ?>
