<?php
session_start();
$BASE = __DIR__;
require_once $BASE . "/config.php";

$msg = ""; $errors = [];
function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

try {
  $cats = $db->query("SELECT name FROM categories ORDER BY name ASC");
  $coms = $db->query("SELECT name FROM companies ORDER BY name ASC");
} catch (Throwable $e) { $cats = false; $coms = false; $errors[] = "Failed to load dropdown data."; }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $name = trim($_POST['name'] ?? '');
  $category = trim($_POST['category'] ?? '');
  $group = trim($_POST['group'] ?? '');
  $company = trim($_POST['company'] ?? '');

  $new_cat = trim($_POST['new_category'] ?? '');
  if ($new_cat !== '') { $stmt = $db->prepare("INSERT IGNORE INTO categories(name) VALUES(?)"); $stmt->bind_param("s", $new_cat); $stmt->execute(); $category = $new_cat; }

  $new_com = trim($_POST['new_company'] ?? '');
  if ($new_com !== '') { $stmt = $db->prepare("INSERT IGNORE INTO companies(name) VALUES(?)"); $stmt->bind_param("s", $new_com); $stmt->execute(); $company = $new_com; }

  $batch_no = trim($_POST['batch_no'] ?? '');
  $manufacture_date = $_POST['manufacture_date'] ?: null;
  $expire_date = $_POST['expire_date'] ?: null;
  $dosage_form = $_POST['dosage_form'] ?? 'Tablet';
  $strength = trim($_POST['strength'] ?? '');
  $price = isset($_POST['price']) ? (float)$_POST['price'] : 0.0;
  $quantity = isset($_POST['quantity']) ? max(0, (int)$_POST['quantity']) : 0;
  $reorder_level = isset($_POST['reorder_level']) ? max(0, (int)$_POST['reorder_level']) : 0;
  $storage_info = trim($_POST['storage_info'] ?? '');
  $prescription_required = isset($_POST['prescription_required']) ? 1 : 0;
  $barcode = trim($_POST['barcode'] ?? '');
  $details = trim($_POST['details'] ?? '');
  $status = $_POST['status'] ?? 'ACTIVE';
  $created_by = $_SESSION['emp_id'] ?? ($_SESSION['user_id'] ?? 'admin');

  if ($name === '') $errors[] = "Name is required.";
  if ($category === '') $errors[] = "Category is required.";
  if ($group === '') $errors[] = "Generic (group) is required.";
  if ($company === '') $errors[] = "Company is required.";
  if ($price < 0) $errors[] = "Price cannot be negative.";
  if ($quantity < 0) $errors[] = "Quantity cannot be negative.";

  $image = null;
  if (!empty($_FILES['image']['name']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
    $targetDir = $BASE . "/uploads/"; if (!is_dir($targetDir)) { @mkdir($targetDir, 0777, true); }
    $tmp = $_FILES['image']['tmp_name'];
    $info = @getimagesize($tmp);
    $allowedMimes = [ 'image/jpeg'=>'jpg','image/png'=>'png','image/gif'=>'gif','image/webp'=>'webp' ];
    if ($info && isset($allowedMimes[$info['mime']])) {
      $ext = $allowedMimes[$info['mime']];
      $filename = bin2hex(random_bytes(8)) . "." . $ext;
      if (move_uploaded_file($tmp, $targetDir . $filename)) { $image = $filename; } else { $errors[] = "Image upload failed."; }
    } else { $errors[] = "Only JPG/PNG/GIF/WebP images are allowed."; }
  }

  if (!$errors) {
    $sql = "INSERT INTO medicines
      (name, category, `group`, company, batch_no, manufacture_date, expire_date, dosage_form, strength, price, quantity, reorder_level, storage_info, prescription_required, barcode, details, image, status, created_by)
      VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)
      ON DUPLICATE KEY UPDATE
        quantity = quantity + VALUES(quantity),
        price = VALUES(price),
        reorder_level = GREATEST(reorder_level, VALUES(reorder_level)),
        batch_no = VALUES(batch_no),
        manufacture_date = VALUES(manufacture_date),
        expire_date = VALUES(expire_date),
        dosage_form = VALUES(dosage_form),
        strength = VALUES(strength),
        storage_info = VALUES(storage_info),
        prescription_required = VALUES(prescription_required),
        barcode = VALUES(barcode),
        details = VALUES(details),
        status = VALUES(status),
        created_by = VALUES(created_by),
        image = COALESCE(VALUES(image), image)";
    $stmt = $db->prepare($sql);
    $stmt->bind_param("sssssssssdiisisssss",
      $name, $category, $group, $company, $batch_no, $manufacture_date, $expire_date,
      $dosage_form, $strength, $price, $quantity, $reorder_level, $storage_info,
      $prescription_required, $barcode, $details, $image, $status, $created_by
    );
    if ($stmt->execute()) {
      $msg = ($db->affected_rows > 1) ? "✅ Existing medicine found — quantity increased." : "✅ Medicine inserted successfully.";
      $_POST = [];
    } else {
      $errors[] = "DB Error: " . $stmt->error;
    }
  }
}
include $BASE . "/header.php";
include $BASE . "/navbar.php";
?>
<style>
.container{max-width:980px;margin:24px auto;background:#fff;padding:20px;border-radius:12px;box-shadow:0 4px 16px rgba(0,0,0,.08)}
h2{margin:0 0 12px;color:#0b7d6d}
.form-grid{display:grid;grid-template-columns:1fr 1fr;gap:14px}
.full{grid-column:1/-1}
label{display:block;font-weight:600;margin-bottom:6px;color:#334155}
input[type=text],input[type=number],input[type=date],select,textarea{width:100%;padding:10px;border:1px solid #e5e7eb;border-radius:8px;box-sizing:border-box}
textarea{min-height:100px;resize:vertical}
small.help{color:#64748b}
.btn{background:#009688;color:#fff;border:none;padding:10px 16px;border-radius:8px;cursor:pointer}
.btn:hover{background:#00796b}
.note{padding:10px;border-radius:8px;margin-bottom:12px}
.note.ok{background:#e6ffed;border:1px solid #b2f5ba;color:#14532d}
.note.err{background:#fff1f2;border:1px solid #fecdd3;color:#7f1d1d}
.badge{background:#f1f5f9;color:#334155;padding:2px 6px;border-radius:6px;font-size:12px}
.form-grid .inline{display:flex;gap:8px;align-items:center}
</style>

<div class="container">
  <h2>Insert Medicine <span class="badge">UPSERT</span></h2>
  <?php if($msg): ?><div class="note ok"><?= h($msg) ?></div><?php endif; ?>
  <?php if($errors): ?><div class="note err"><?php foreach($errors as $e) echo "• ".h($e)."<br>"; ?></div><?php endif; ?>

  <form method="POST" enctype="multipart/form-data">
    <div class="form-grid">
      <div><label>Medicine Name *</label><input type="text" name="name" required value="<?= h($_POST['name'] ?? '') ?>"></div>
      <div><label>Generic (Group) *</label><input type="text" name="group" required placeholder="e.g., PARACETAMOL" value="<?= h($_POST['group'] ?? '') ?>"></div>

      <div>
        <label>Category *</label>
        <select name="category" required>
          <option value="">-- Select Category --</option>
          <?php if ($cats) while($c=$cats->fetch_assoc()): ?>
            <option value="<?= h($c['name']) ?>" <?= (($_POST['category'] ?? '')===$c['name']?'selected':'') ?>><?= h($c['name']) ?></option>
          <?php endwhile; ?>
        </select>
        <small class="help">Quick add if not found:</small>
        <input type="text" name="new_category" placeholder="New category" value="<?= h($_POST['new_category'] ?? '') ?>">
      </div>

      <div>
        <label>Company (Supplier) *</label>
        <select name="company" required>
          <option value="">-- Select Company --</option>
          <?php if ($coms) while($c=$coms->fetch_assoc()): ?>
            <option value="<?= h($c['name']) ?>" <?= (($_POST['company'] ?? '')===$c['name']?'selected':'') ?>><?= h($c['name']) ?></option>
          <?php endwhile; ?>
        </select>
        <small class="help">Quick add if not found:</small>
        <input type="text" name="new_company" placeholder="New company" value="<?= h($_POST['new_company'] ?? '') ?>">
      </div>

      <div>
        <label>Dosage Form *</label>
        <select name="dosage_form" required>
          <?php $forms=['Tablet','Capsule','Syrup','Injection','Ointment','Other']; $cur=$_POST['dosage_form'] ?? 'Tablet';
          foreach($forms as $f){ $sel=($cur===$f)?'selected':''; echo "<option value='".h($f)."' $sel>".h($f)."</option>"; } ?>
        </select>
      </div>
      <div><label>Strength</label><input type="text" name="strength" placeholder="e.g., 500mg" value="<?= h($_POST['strength'] ?? '') ?>"></div>
      <div><label>Batch No.</label><input type="text" name="batch_no" value="<?= h($_POST['batch_no'] ?? '') ?>"></div>
      <div><label>Manufacture Date</label><input type="date" name="manufacture_date" value="<?= h($_POST['manufacture_date'] ?? '') ?>"></div>
      <div><label>Expire Date</label><input type="date" name="expire_date" value="<?= h($_POST['expire_date'] ?? '') ?>"></div>
      <div><label>Price (৳) *</label><input type="number" step="0.01" min="0" name="price" required value="<?= h($_POST['price'] ?? '') ?>"></div>
      <div><label>Quantity *</label><input type="number" min="0" name="quantity" required value="<?= h($_POST['quantity'] ?? 0) ?>"></div>
      <div><label>Reorder Level</label><input type="number" min="0" name="reorder_level" value="<?= h($_POST['reorder_level'] ?? 0) ?>"></div>
      <div><label>Barcode</label><input type="text" name="barcode" value="<?= h($_POST['barcode'] ?? '') ?>"></div>
      <div><label>Storage Info</label><input type="text" name="storage_info" placeholder="e.g., Keep below 25°C" value="<?= h($_POST['storage_info'] ?? '') ?>"></div>
      <div class="inline"><label><input type="checkbox" name="prescription_required" <?= isset($_POST['prescription_required'])?'checked':'' ?>> Prescription Required</label></div>
      <div class="full"><label>Details / Description</label><textarea name="details" placeholder="Short description..."><?= h($_POST['details'] ?? '') ?></textarea></div>
      <div class="full"><label>Image</label><input type="file" name="image" accept="image/*"><small class="help">JPG/PNG/GIF/WebP</small></div>
      <div class="full"><button type="submit" class="btn">Add Medicine</button></div>
    </div>
  </form>
</div>
<?php include $BASE . "/footer.php"; ?>
