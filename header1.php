<?php
require_once __DIR__ . "/config.php";
if (session_status() === PHP_SESSION_NONE) { session_start(); }

// cart count
$cart_count = 0;
if (!empty($_SESSION['cart']) && is_array($_SESSION['cart'])) {
  foreach ($_SESSION['cart'] as $qty) { $cart_count += (int)$qty; }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0" />
<title>Online Pharmacy Management System</title>
<style>
:root{--brand:#009688;--brand-dark:#00796b}
*{box-sizing:border-box}
body{margin:0;font-family:Arial,Helvetica,sans-serif;background:#f8f9fa}
/* Header */
.header{background:#fff;border-bottom:2px solid #ddd;display:flex;align-items:center;gap:16px;padding:0 24px;height:0.6in;}
.header .left{display:flex;align-items:center}
.logo{height:50px;display:block}
.middle{flex:1;display:flex;flex-direction:column;align-items:center;gap:6px}
.middle h3{margin:0;color:var(--brand);text-align:center}
/* Search bar */
.search-bar{width:260px}
.search-row{display:flex;width:100%}
.search-row input[type="text"]{flex:1;padding:8px;border:1px solid #ccc;border-right:none;outline:none;border-radius:4px 0 0 4px}
.search-row button{padding:8px 14px;border:none;background:var(--brand);color:#fff;border-radius:0 4px 4px 0;cursor:pointer}
.search-row button:hover{background:var(--brand-dark)}
.right{display:flex;align-items:center;gap:12px}
.cart-link{display:flex;align-items:center;gap:8px;text-decoration:none;color:#222;padding:6px 10px;border:1px solid #e3e3e3;border-radius:20px;background:#fafafa}
.cart-link:hover{background:#f1f1f1}
.cart-icon{width:26px;height:26px;object-fit:contain;display:block}
.cart-count{background:var(--brand);color:#fff;border-radius:12px;min-width:22px;text-align:center;padding:2px 6px;font-size:12px}
/* Navbar (dropdown) */
.navbar{background:var(--brand);height:48px;display:flex;align-items:center;gap:6px;padding:0 24px}
.navbar a,.dropdown-btn{color:#fff;padding:12px 16px;text-decoration:none;font-weight:bold;cursor:pointer;border-radius:4px}
.navbar a:hover,.dropdown:hover .dropdown-btn{background:var(--brand-dark)}
.dropdown{position:relative;display:inline-block}
.dropdown-content{display:none;position:absolute;background:#fff;min-width:220px;box-shadow:0 4px 8px rgba(0,0,0,.15);z-index:10;border-radius:6px;overflow:hidden}
.dropdown-content a{color:#222;padding:10px 14px;text-decoration:none;display:block}
.dropdown-content a:hover{background:#f6f6f6}
.dropdown:hover .dropdown-content{display:block}

/* invoice buttons */
.invoice-links .btn-invoice{
  text-decoration:none;border:1px solid #e3e3e3;border-radius:20px;
  padding:6px 10px;background:#ffffff;color:#222;display:inline-flex;align-items:center;gap:6px
}
.invoice-links .btn-invoice:hover{background:#f1f1f1}
</style>
</head>
<body>
<div class="header">
  <div class="left">
    <a href="home.php" title="Home">
      <img class="logo" src="https://via.placeholder.com/150x50?text=LOGO" alt="Logo">
    </a>
  </div>

  <div class="middle">
    <h3>Online Pharmacy Management System</h3>
  </div>

  <div class="right">
    <!-- Search bar -->
    <div class="search-bar">
      <form action="catagory.php" method="GET" class="search-row">
        <input type="text" name="q" placeholder="Search medicines…" required />
        <button type="submit">Search</button>
      </form>
    </div>

    <!-- Cart -->
    <a class="cart-link" href="cart.php" title="View Cart">
      <img class="cart-icon" src="cart.png" alt="Cart" onerror="this.src='https://img.icons8.com/ios-filled/50/shopping-cart.png'">
      <span>Cart</span>
      <span class="cart-count"><?php echo (int)$cart_count; ?></span>
    </a>

    <!-- ✅ Show invoice shortcuts after a successful order -->
    <?php if (!empty($_SESSION['last_order_id'])): ?>
      <div class="invoice-links" style="display:flex;gap:8px;align-items:center">
        <a class="btn-invoice"
           href="invoice.php?order_id=<?= (int)$_SESSION['last_order_id'] ?>"
           title="View Invoice">📄 View Invoice</a>

        <a class="btn-invoice"
           href="invoice_pdf.php?order_id=<?= (int)$_SESSION['last_order_id'] ?>"
           title="Download Invoice PDF">⬇️ Download PDF</a>
      </div>
    <?php endif; ?>
  </div>
</div>
