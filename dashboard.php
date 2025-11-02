<?php include "header.php"; ?>
<?php include "navbar.php"; ?>
<?php require_once "config.php"; ?>

<?php
// Update medicine (name, price, image)
if(isset($_POST['update_medicine'])){
    $id = (int)$_POST['id'];
    $name = $_POST['name'];
    $price = (float)$_POST['price'];
    $image = $_POST['old_image'];

    if(!empty($_FILES['image']['name']) && $_FILES['image']['error']===UPLOAD_ERR_OK){
        $dir=__DIR__."/uploads/"; if(!is_dir($dir)) mkdir($dir,0755,true);
        $allowed=['image/jpeg'=>'jpg','image/png'=>'png','image/gif'=>'gif'];
        $info=getimagesize($_FILES['image']['tmp_name']);
        if($info && isset($allowed[$info['mime']])){
            $fname=bin2hex(random_bytes(8)).'.'.$allowed[$info['mime']];
            if(move_uploaded_file($_FILES['image']['tmp_name'],$dir.$fname)) $image=$fname;
        }
    }
    $stmt=$db->prepare("UPDATE medicines SET name=?, price=?, image=? WHERE id=?");
    $stmt->bind_param("sdsi",$name,$price,$image,$id);
    $stmt->execute();
}

// Delete medicine
if(isset($_GET['delete_medicine']) && ctype_digit($_GET['delete_medicine'])){
    $id=(int)$_GET['delete_medicine'];
    $stmt=$db->prepare("DELETE FROM medicines WHERE id=?");
    $stmt->bind_param("i",$id);
    $stmt->execute();
    header("Location: dashboard.php"); exit;
}

// Fetch lists
$medicines = $db->query("SELECT id,name,price,image FROM medicines ORDER BY id DESC");
$employees = $db->query("SELECT emp_id,emp_name,emp_email,emp_phone,emp_position,emp_address FROM employee ORDER BY emp_id DESC");
$popular   = $db->query("SELECT name,image,view_count FROM medicines ORDER BY view_count DESC LIMIT 5");
?>
<!-- (styles same as আপনার ভার্সন) -->
