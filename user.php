<?php
require_once __DIR__ . "/config.php";
if (session_status()===PHP_SESSION_NONE) session_start();
if (!function_exists('h')) { function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); } }

// CSRF
if (empty($_SESSION['csrf']) || strlen($_SESSION['csrf']) < 32) {
  $_SESSION['csrf'] = bin2hex(random_bytes(32));
}

$success = ""; 
$error = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
  $tok = (string)($_POST['csrf'] ?? '');
  if ($tok === '' || !hash_equals($_SESSION['csrf'], $tok)) {
    $error = "Security check failed. Please refresh and try again.";
  } else {
    $id = trim((string)($_POST['id'] ?? ''));
    $name = trim((string)($_POST['name'] ?? ''));
    $email = trim((string)($_POST['email'] ?? ''));
    $contact = trim((string)($_POST['contact'] ?? ''));
    $address = trim((string)($_POST['address'] ?? ''));

    if (!preg_match('/^[A-Za-z0-9_\-]{3,32}$/', $id)) {
      $error = "User ID must be 3-32 chars (letters, numbers, _ or -).";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
      $error = "Invalid email address.";
    } elseif (strlen($name) < 2) {
      $error = "Name must be at least 2 characters.";
    } else {
      $chk = $db->prepare("SELECT id FROM users WHERE id=? LIMIT 1");
      $chk->bind_param("s", $id);
      $chk->execute();
      $exists = $chk->get_result()->num_rows > 0;
      $chk->close();

      if ($exists) {
        $error = "❌ This User ID already exists. Please choose another.";
      } else {
        $ins = $db->prepare("INSERT INTO users (id,name,email,contact,address) VALUES (?,?,?,?,?)");
        $ins->bind_param("sssss", $id, $name, $email, $contact, $address);
        if ($ins->execute()) {
          $success = "✅ Registration successful! You can now proceed to checkout.";
          $_SESSION['user_id'] = (string)$id;
        } else {
          $error = "Database error: " . $ins->error;
        }
        $ins->close();
      }
    }
  }
}

include __DIR__ . "/header.php";
include __DIR__ . "/navbar.php";
?>
<style>
body{margin:0;font-family:Arial,sans-serif;background:#f8f9fa}
.form-container{display:flex;justify-content:center;align-items:center;min-height:70vh;padding:20px}
.form-box{background:#fff;padding:30px;border-radius:8px;width:420px;box-shadow:0 4px 15px rgba(0,0,0,.2)}
.form-box h2{text-align:center;color:#009688;margin-bottom:20px}
.form-box input,.form-box textarea,.form-box button{width:100%;padding:10px;margin:8px 0;border-radius:6px;border:1px solid #ccc;box-sizing:border-box}
.form-box button{background:#009688;color:#fff;border:none;cursor:pointer;font-size:16px}
.form-box button:hover{background:#00796b}
.msg{margin-bottom:12px;padding:10px;border-radius:6px}
.msg.err{background:#fef2f2;border:1px solid #fecaca;color:#991b1b}
.msg.ok{background:#ecfdf5;border:1px solid #a7f3d0;color:#065f46}
</style>

<div class="form-container">
  <div class="form-box">
    <h2>User Registration</h2>
    <?php if ($error): ?><div class="msg err"><?= h($error) ?></div><?php endif; ?>
    <?php if ($success): ?><div class="msg ok"><?= h($success) ?></div><?php endif; ?>
    <form method="POST" action="">
      <input type="hidden" name="csrf" value="<?= h($_SESSION['csrf']) ?>">

      <label>User ID:</label>
      <input type="text" name="id" required placeholder="Enter unique User ID (e.g., user123)">

      <label>Name:</label>
      <input type="text" name="name" required placeholder="Full name">

      <label>Email:</label>
      <input type="email" name="email" required placeholder="name@example.com">

      <label>Contact:</label>
      <input type="text" name="contact" required placeholder="e.g., 01XXXXXXXXX">

      <label>Address:</label>
      <textarea name="address" required placeholder="House/Flat, Road, Area, City, Postcode"></textarea>

      <button type="submit">Register</button>
    </form>
  </div>
</div>
<?php include __DIR__ . "/footer.php"; ?>
