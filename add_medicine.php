<?php
session_start();
require_once "config.php";
date_default_timezone_set('Asia/Dhaka');

function h($s){ return htmlspecialchars((string)$s,ENT_QUOTES,'UTF-8'); }
if(empty($_SESSION['csrf'])) $_SESSION['csrf']=bin2hex(random_bytes(16));
$CSRF=$_SESSION['csrf'];

$errors=[];
$dosageOptions=['Tablet','Capsule','Syrup','Injection','Ointment','Other'];
$statuses=['ACTIVE','DISCONTINUED'];

// Dropdown data
$catsRes = $db->query("SELECT name FROM categories ORDER BY name ASC");
$catList = $catsRes ? array_column($catsRes->fetch_all(MYSQLI_ASSOC),'name') : [];
$comRes  = $db->query("SELECT name FROM companies ORDER BY name ASC");
$comList = $comRes ? array_column($comRes->fetch_all(MYSQLI_ASSOC),'name')  : [];

// Handle POST
if($_SERVER['REQUEST_METHOD']==='POST'){
  if(empty($_POST['csrf']) || !hash_equals($CSRF,$_POST['csrf'])) $errors[]="Invalid request token.";

  // Collect inputs
  $name=trim($_POST['name']??'');
  $category=trim($_POST['category']??'');
  $group=trim($_POST['group']??'');
  $company=trim($_POST['company']??'');
  $dosage_form=trim($_POST['dosage_form']??'');
  $strength=trim($_POST['strength']??'');
  $price=(float)($_POST['price']??0);
  $quantity=(int)($_POST['quantity']??0);
  $reorder_level=(int)($_POST['reorder_level']??0);
  $batch_no=trim($_POST['batch_no']??'');
  $manufacture_date=trim($_POST['manufacture_date']??'');
  $expire_date=trim($_POST['expire_date']??'');
  $status=trim($_POST['status']??'ACTIVE');
  $barcode=trim($_POST['barcode']??'');

  // Validation
  if($name==='') $errors[]="Name is required.";
  if($category==='') $errors[]="Category is required.";
  if($company==='') $errors[]="Company is required.";
  if($dosage_form==='' || !in_array($dosage_form,$dosageOptions,true)) $errors[]="Valid dosage form required.";
  if($price<0) $errors[]="Price cannot be negative.";
  if($quantity<0) $errors[]="Quantity cannot be negative.";
  if(!in_array($status,$statuses,true)) $errors[]="Invalid status.";

  // Image upload (optional)
  $imageName=null;
  if(isset($_FILES['image']) && $_FILES['image']['error']!==UPLOAD_ERR_NO_FILE){
    if($_FILES['image']['error']===UPLOAD_ERR_OK){
      $ext=strtolower(pathinfo($_FILES['image']['name'],PATHINFO_EXTENSION));
      if(!in_array($ext,['jpg','jpeg','png','gif','webp'])) $errors[]="Image must be jpg,jpeg,png,gif,webp";
      if($_FILES['image']['size']>2*1024*1024) $errors[]="Image must be <= 2MB";
      if(!$errors){
        $imageName='med_'.time().'_'.bin2hex(random_bytes(3)).'.'.$ext;
        if(!move_uploaded_file($_FILES['image']['tmp_name'],__DIR__."/uploads/".$imageName)){
          $errors[]="Image upload failed.";
        }
      }
    } else {
      $errors[]="Image upload error.";
    }
  }

  if(!$errors){
    $stmt=$db->prepare("INSERT INTO medicines
      (name, category, `group`, company, dosage_form, strength, price, quantity, reorder_level, batch_no, manufacture_date, expire_date, status, barcode, image, created_at)
      VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,NOW())");
    $stmt->bind_param(
      "sssssssiiissssss",
      $name,$category,$group,$company,$dosage_form,$strength,$price,$quantity,$reorder_level,$batch_no,$manufacture_date,$expire_date,$status,$barcode,$imageName
    );
    if($stmt->execute()){
      $_SESSION['msg']="Medicine added (ID: ".$stmt->insert_id.")";
      header("Location: medicine_list.php"); exit;
    } else {
      $errors[]="Save failed: ".$db->error;
    }
  }
}
?>
<!doctype html>
<html lang="bn">
<head>
  <meta charset="utf-8">
  <title>Add Medicine</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
<div class="container my-4">
  <div class="d-flex justify-content-between align-items-center mb-3">
    <h3 class="m-0">+ Add Medicine</h3>
    <div><a class="btn btn-outline-dark" href="medicine_list.php">← Back</a></div>
  </div>

  <?php if(!empty($errors)): ?>
    <div class="alert alert-danger"><ul class="m-0"><?php foreach($errors as $e) echo "<li>".h($e)."</li>";?></ul></div>
  <?php endif; ?>

  <form method="post" enctype="multipart/form-data" class="card p-3">
    <input type="hidden" name="csrf" value="<?=$CSRF?>">
    <div class="row g-3">
      <div class="col-md-6">
        <label class="form-label">Name *</label>
        <input class="form-control" name="name" required>
      </div>
      <div class="col-md-3">
        <label class="form-label">Category *</label>
        <input list="catList" class="form-control" name="category" required>
        <datalist id="catList"><?php foreach($catList as $c) echo "<option value=\"".h($c)."\">"; ?></datalist>
      </div>
      <div class="col-md-3">
        <label class="form-label">Group (Generic)</label>
        <input class="form-control" name="group">
      </div>
      <div class="col-md-4">
        <label class="form-label">Company *</label>
        <input list="comList" class="form-control" name="company" required>
        <datalist id="comList"><?php foreach($comList as $c) echo "<option value=\"".h($c)."\">"; ?></datalist>
      </div>
      <div class="col-md-4">
        <label class="form-label">Dosage Form *</label>
        <select class="form-select" name="dosage_form" required>
          <option value="">Choose...</option>
          <?php foreach($dosageOptions as $d) echo "<option>".h($d)."</option>"; ?>
        </select>
      </div>
      <div class="col-md-4">
        <label class="form-label">Strength</label>
        <input class="form-control" name="strength" placeholder="e.g., 500 mg">
      </div>

      <div class="col-md-3">
        <label class="form-label">Price *</label>
        <input type="number" step="0.01" min="0" class="form-control" name="price" required>
      </div>
      <div class="col-md-3">
        <label class="form-label">Quantity *</label>
        <input type="number" min="0" class="form-control" name="quantity" required>
      </div>
      <div class="col-md-3">
        <label class="form-label">Reorder Level</label>
        <input type="number" min="0" class="form-control" name="reorder_level" value="0">
      </div>
      <div class="col-md-3">
        <label class="form-label">Status *</label>
        <select class="form-select" name="status" required>
          <?php foreach($statuses as $s) echo "<option>".h($s)."</option>"; ?>
        </select>
      </div>

      <div class="col-md-3">
        <label class="form-label">Batch No</label>
        <input class="form-control" name="batch_no">
      </div>
      <div class="col-md-3">
        <label class="form-label">Manufacture Date</label>
        <input type="date" class="form-control" name="manufacture_date">
      </div>
      <div class="col-md-3">
        <label class="form-label">Expire Date</label>
        <input type="date" class="form-control" name="expire_date">
      </div>
      <div class="col-md-3">
        <label class="form-label">Barcode</label>
        <input class="form-control" name="barcode">
      </div>

      <div class="col-md-4">
        <label class="form-label">Image (max 2MB)</label>
        <input type="file" class="form-control" name="image" accept=".jpg,.jpeg,.png,.gif,.webp">
      </div>
    </div>

    <div class="mt-3 d-flex gap-2">
      <button class="btn btn-success">Save</button>
      <a class="btn btn-outline-secondary" href="medicine_list.php">Cancel</a>
    </div>
  </form>
</div>
</body>
</html>
