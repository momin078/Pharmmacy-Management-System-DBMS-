<?php
// edit_medicine.php (merged, quantity+=, no errors)
session_start();
$BASE = __DIR__;
require_once $BASE . "/config.php";

function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

$msg = ""; $errors = [];

// ---- Validate & fetch target ID ----
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) { $_SESSION['err'] = "Invalid medicine ID."; header("Location: medicine_list.php"); exit; }

// ---- Load dropdown data (for category/company like Insert page) ----
try {
  $cats = $db->query("SELECT name FROM categories ORDER BY name ASC");
  $coms = $db->query("SELECT name FROM companies ORDER BY name ASC");
} catch (Throwable $e) {
  $cats = false; $coms = false; $errors[] = "Failed to load dropdown data.";
}

// ---- Load current medicine ----
$stmt = $db->prepare("SELECT * FROM medicines WHERE id=? LIMIT 1");
$stmt->bind_param("i", $id);
$stmt->execute();
$medicine = $stmt->get_result()->fetch_assoc();
if (!$medicine) { $_SESSION['err'] = "Medicine not found."; header("Location: medicine_list.php"); exit; }

// ---- Handle update ----
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_medicine'])) {
  // Base fields (keep identical to Insert page)
  $name = trim($_POST['name'] ?? '');
  $category = trim($_POST['category'] ?? '');
  $group = trim($_POST['group'] ?? '');
  $company = trim($_POST['company'] ?? '');

  // Quick add category/company (same as Insert)
  $new_cat = trim($_POST['new_category'] ?? '');
  if ($new_cat !== '') {
    $st = $db->prepare("INSERT IGNORE INTO categories(name) VALUES(?)");
    $st->bind_param("s", $new_cat);
    $st->execute();
    $category = $new_cat;
  }
  $new_com = trim($_POST['new_company'] ?? '');
  if ($new_com !== '') {
    $st = $db->prepare("INSERT IGNORE INTO companies(name) VALUES(?)");
    $st->bind_param("s", $new_com);
    $st->execute();
    $company = $new_com;
  }

  $batch_no = trim($_POST['batch_no'] ?? '');
  // store NULL if empty
  $manufacture_date = (!empty($_POST['manufacture_date'])) ? $_POST['manufacture_date'] : null;
  $expire_date      = (!empty($_POST['expire_date']))      ? $_POST['expire_date']      : null;
  $dosage_form = $_POST['dosage_form'] ?? 'Tablet';
  $strength = trim($_POST['strength'] ?? '');
  $price = isset($_POST['price']) ? (float)$_POST['price'] : 0.0;
  $quantity = isset($_POST['quantity']) ? max(0, (int)$_POST['quantity']) : 0; // will be ADDED
  $reorder_level = isset($_POST['reorder_level']) ? max(0, (int)$_POST['reorder_level']) : 0;
  $storage_info = trim($_POST['storage_info'] ?? '');
  $prescription_required = isset($_POST['prescription_required']) ? 1 : 0;
  $barcode = trim($_POST['barcode'] ?? '');
  $details = trim($_POST['details'] ?? '');
  $status = $_POST['status'] ?? 'ACTIVE';

  // Validations
  if ($name === '') $errors[] = "Name is required.";
  if ($category === '') $errors[] = "Category is required.";
  if ($group === '') $errors[] = "Generic (group) is required.";
  if ($company === '') $errors[] = "Company is required.";
  if ($price < 0) $errors[] = "Price cannot be negative.";
  if ($quantity < 0) $errors[] = "Quantity cannot be negative.";

  // Image upload (optional replace)
  $new_image_filename = null;
  if (!empty($_FILES['image']['name']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
    $targetDir = $BASE . "/uploads/";
    if (!is_dir($targetDir)) { @mkdir($targetDir, 0777, true); }
    $tmp = $_FILES['image']['tmp_name'];
    $info = @getimagesize($tmp);
    $allowedMimes = [ 'image/jpeg'=>'jpg','image/png'=>'png','image/gif'=>'gif','image/webp'=>'webp' ];
    if ($info && isset($allowedMimes[$info['mime']])) {
      $ext = $allowedMimes[$info['mime']];
      $filename = bin2hex(random_bytes(8)) . "." . $ext;
      if (move_uploaded_file($tmp, $targetDir . $filename)) {
        $new_image_filename = $filename;
        // delete old image if exists
        if (!empty($medicine['image'])) {
          $old = $targetDir . $medicine['image'];
          if (is_file($old)) { @unlink($old); }
        }
      } else {
        $errors[] = "Image upload failed.";
      }
    } else {
      $errors[] = "Only JPG/PNG/GIF/WebP images are allowed.";
    }
  }

  if (!$errors) {
    // Build UPDATE with quantity ADD logic (quantity = quantity + ?)
    $sql = "UPDATE medicines SET
              name=?, category=?, `group`=?, company=?, batch_no=?,
              manufacture_date=?, expire_date=?, dosage_form=?, strength=?,
              price=?, quantity = quantity + ?, reorder_level=?, storage_info=?,
              prescription_required=?, barcode=?, details=?, status=?";
    if ($new_image_filename !== null) {
      $sql .= ", image=?";
    }
    $sql .= " WHERE id=?";

    $stmt = $db->prepare($sql);

    if ($new_image_filename !== null) {
      // 19 params total
      // types: s s s s s s s s s d i i s i s s s s i
      $stmt->bind_param(
        "sssssssssdiisissssi",
        $name,                 // s
        $category,             // s
        $group,                // s
        $company,              // s
        $batch_no,             // s
        $manufacture_date,     // s (NULL ok)
        $expire_date,          // s (NULL ok)
        $dosage_form,          // s
        $strength,             // s
        $price,                // d
        $quantity,             // i  (ADDED to existing)
        $reorder_level,        // i
        $storage_info,         // s
        $prescription_required,// i
        $barcode,              // s
        $details,              // s
        $status,               // s
        $new_image_filename,   // s
        $id                    // i
      );
    } else {
      // 18 params total
      // types: s s s s s s s s s d i i s i s s s i
      $stmt->bind_param(
        "sssssssssdiisisssi",
        $name,                 // s
        $category,             // s
        $group,                // s
        $company,              // s
        $batch_no,             // s
        $manufacture_date,     // s (NULL ok)
        $expire_date,          // s (NULL ok)
        $dosage_form,          // s
        $strength,             // s
        $price,                // d
        $quantity,             // i  (ADDED to existing)
        $reorder_level,        // i
        $storage_info,         // s
        $prescription_required,// i
        $barcode,              // s
        $details,              // s
        $status,               // s
        $id                    // i
      );
    }

    if ($stmt->execute()) {
      $msg = "✅ Medicine updated successfully.";
      // Re-fetch to show latest values on the same page
      $stmt2 = $db->prepare("SELECT * FROM medicines WHERE id=? LIMIT 1");
      $stmt2->bind_param("i", $id);
      $stmt2->execute();
      $medicine = $stmt2->get_result()->fetch_assoc();
      $_POST = []; // clear POST so form reflects DB
    } else {
      $errors[] = "Update failed: " . $stmt->error;
    }
  }
}

include $BASE . "/header.php";
include $BASE . "/navbar.php";
?>
<style>
.container{max-width:980px;margin:24px auto;background:#fff;padding:20px;border-radius:12px;box-shadow:0 4px 16px rgba(0,0,0,.08)}
h2{margin:0 0 12px;color:#0b7d6d;display:flex;gap:8px;align-items:center}
.form-grid{display:grid;grid-template-columns:1fr 1fr;gap:14px}
.full{grid-column:1/-1}
label{display:block;font-weight:600;margin-bottom:6px;color:#334155}
input[type=text],input[type=number],input[type=date],select,textarea{width:100%;padding:10px;border:1px solid #e5e7eb;border-radius:8px;box-sizing:border-box}
textarea{min-height:100px;resize:vertical}
small.help{color:#64748b}
.btn{background:#009688;color:#fff;border:none;padding:10px 16px;border-radius:8px;cursor:pointer}
.btn:hover{background:#00796b}
.btn-ghost{background:#fff;border:1px solid #e5e7eb;color:#0f172a}
.btn-ghost:hover{background:#f8fafc}
.note{padding:10px;border-radius:8px;margin-bottom:12px}
.note.ok{background:#e6ffed;border:1px solid #b2f5ba;color:#14532d}
.note.err{background:#fff1f2;border:1px solid #fecdd3;color:#7f1d1d}
.badge{background:#f1f5f9;color:#334155;padding:2px 6px;border-radius:6px;font-size:12px}
.image-preview{display:flex;gap:12px;align-items:flex-start}
.image-preview img{max-height:80px;border-radius:8px;border:1px solid #e5e7eb}
.inline{display:flex;gap:8px;align-items:center}
</style>

<div class="container">
  <h2>Edit Medicine <span class="badge">ID #<?= h($medicine['id']) ?></span></h2>

  <?php if($msg): ?><div class="note ok"><?= h($msg) ?></div><?php endif; ?>
  <?php if($errors): ?><div class="note err"><?php foreach($errors as $e) echo "• ".h($e)."<br>"; ?></div><?php endif; ?>

  <form method="POST" enctype="multipart/form-data">
    <div class="form-grid">
      <div><label>Medicine Name *</label><input type="text" name="name" required value="<?= h($_POST['name'] ?? $medicine['name']) ?>"></div>
      <div><label>Generic (Group) *</label><input type="text" name="group" required placeholder="e.g., PARACETAMOL" value="<?= h($_POST['group'] ?? $medicine['group']) ?>"></div>

      <div>
        <label>Category *</label>
        <select name="category" required>
          <option value="">-- Select Category --</option>
          <?php
          $currentCat = $_POST['category'] ?? $medicine['category'];
          if ($cats) { while($c = $cats->fetch_assoc()): ?>
            <option value="<?= h($c['name']) ?>" <?= ($currentCat === $c['name']) ? 'selected' : '' ?>><?= h($c['name']) ?></option>
          <?php endwhile; } ?>
        </select>
        <small class="help">Quick add if not found:</small>
        <input type="text" name="new_category" placeholder="New category" value="<?= h($_POST['new_category'] ?? '') ?>">
      </div>

      <div>
        <label>Company (Supplier) *</label>
        <select name="company" required>
          <option value="">-- Select Company --</option>
          <?php
          $currentCom = $_POST['company'] ?? $medicine['company'];
          if ($coms) { while($c = $coms->fetch_assoc()): ?>
            <option value="<?= h($c['name']) ?>" <?= ($currentCom === $c['name']) ? 'selected' : '' ?>><?= h($c['name']) ?></option>
          <?php endwhile; } ?>
        </select>
        <small class="help">Quick add if not found:</small>
        <input type="text" name="new_company" placeholder="New company" value="<?= h($_POST['new_company'] ?? '') ?>">
      </div>

      <div>
        <label>Dosage Form *</label>
        <select name="dosage_form" required>
          <?php
            $forms = ['Tablet','Capsule','Syrup','Injection','Ointment','Other'];
            $cur = $_POST['dosage_form'] ?? $medicine['dosage_form'] ?? 'Tablet';
            foreach($forms as $f){
              $sel = ($cur === $f) ? 'selected' : '';
              echo "<option value='".h($f)."' $sel>".h($f)."</option>";
            }
          ?>
        </select>
      </div>

      <div><label>Strength</label><input type="text" name="strength" placeholder="e.g., 500mg" value="<?= h($_POST['strength'] ?? $medicine['strength']) ?>"></div>
      <div><label>Batch No.</label><input type="text" name="batch_no" value="<?= h($_POST['batch_no'] ?? $medicine['batch_no']) ?>"></div>
      <div><label>Manufacture Date</label><input type="date" name="manufacture_date" value="<?= h($_POST['manufacture_date'] ?? ($medicine['manufacture_date'] ?? '')) ?>"></div>
      <div><label>Expire Date</label><input type="date" name="expire_date" value="<?= h($_POST['expire_date'] ?? ($medicine['expire_date'] ?? '')) ?>"></div>
      <div><label>Price (৳) *</label><input type="number" step="0.01" min="0" name="price" required value="<?= h($_POST['price'] ?? $medicine['price']) ?>"></div>
      <div><label>Quantity (Add) *</label><input type="number" min="0" name="quantity" required value="<?= h($_POST['quantity'] ?? 0) ?>"><small class="help">এই সংখ্যাটা আগের quantity-এর সাথে যোগ হবে</small></div>
      <div><label>Reorder Level</label><input type="number" min="0" name="reorder_level" value="<?= h($_POST['reorder_level'] ?? ($medicine['reorder_level'] ?? 0)) ?>"></div>
      <div><label>Barcode</label><input type="text" name="barcode" value="<?= h($_POST['barcode'] ?? ($medicine['barcode'] ?? '')) ?>"></div>
      <div><label>Storage Info</label><input type="text" name="storage_info" placeholder="e.g., Keep below 25°C" value="<?= h($_POST['storage_info'] ?? ($medicine['storage_info'] ?? '')) ?>"></div>

      <div class="inline full">
        <label><input type="checkbox" name="prescription_required" <?= (isset($_POST['prescription_required']) ? true : ( !isset($_POST['prescription_required']) && !empty($medicine['prescription_required']) )) ? 'checked' : '' ?>> Prescription Required</label>
      </div>

      <div class="full"><label>Details / Description</label><textarea name="details" placeholder="Short description..."><?= h($_POST['details'] ?? ($medicine['details'] ?? '')) ?></textarea></div>

      <div class="full">
        <label>Status</label>
        <select name="status">
          <?php
            $stCur = $_POST['status'] ?? ($medicine['status'] ?? 'ACTIVE');
            foreach (['ACTIVE','INACTIVE'] as $st) {
              $sel = ($stCur === $st) ? 'selected' : '';
              echo "<option value='".h($st)."' $sel>".h($st)."</option>";
            }
          ?>
        </select>
      </div>

      <div class="full">
        <label>Image</label>
        <div class="image-preview">
          <input type="file" name="image" accept="image/*">
          <?php if (!empty($medicine['image'])): ?>
            <div>
              <small class="help">Current:</small><br>
              <img src="uploads/<?= h($medicine['image']) ?>" alt="Current Image">
            </div>
          <?php endif; ?>
        </div>
        <small class="help">JPG/PNG/GIF/WebP. Uploading a new image will replace the current one.</small>
      </div>

      <div class="full" style="display:flex;gap:10px;flex-wrap:wrap;margin-top:6px">
        <button class="btn" name="update_medicine" type="submit">Save Changes</button>
        <a class="btn-ghost" href="medicine_list.php">Cancel</a>
      </div>
    </div>
  </form>
</div>

<?php include $BASE . "/footer.php"; ?>
