<?php
// PHP template for IUBAT-Style Dashboard (sidebar 1 inch wide)
?><!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>IUBAT-Style Dashboard</title>
  <style>
    body {
      margin:0;
      background:#fff;
      color:#333;
      font-family: Arial, sans-serif;
    }
    .topbar {
      display:flex;
      justify-content:space-between;
      align-items:center;
      padding:10px 16px;
      background:#f5f5f5;
      border-bottom:1px solid #ddd;
    }
    .brand { display:flex; align-items:center; gap:10px; font-weight:bold; }
    .brand-logo {
      width:30px; height:30px; border-radius:6px;
      background:#2ecc71; color:#fff; display:flex; justify-content:center; align-items:center;
    }
    .tabs { display:flex; gap:12px; }
    .tabs a { text-decoration:none; color:#555; padding:6px 10px; border-radius:6px; }
    .tabs a.active { background:#e0e0e0; color:#000; }
    .search input { padding:6px 10px; border:1px solid #ccc; border-radius:6px; }
    .shell {
      display:grid;
      grid-template-columns: 1in 1fr 250px; /* Left sidebar fixed 1 inch */
      gap:16px;
      padding:16px;
    }
    .left {
      width:1in; min-width:1in; max-width:1in;
      background:#fff;
      border:1px solid #ddd;
      border-radius:8px;
      padding:8px;
    }
    .right, .card {
      background:#fff;
      border:1px solid #ddd;
      border-radius:8px;
      padding:12px;
    }
    .nav a {
      display:block;
      padding:6px;
      margin-bottom:4px;
      text-decoration:none;
      color:#333;
      border-radius:4px;
      font-size:12px; /* smaller text to fit narrow bar */
      text-align:center;
    }
    .nav a:hover { background:#f0f0f0; }
    .hero img { width:100%; height:auto; border-radius:6px; }
    .status {
      margin-top:8px;
      font-size:12px;
      color:#666;
      display:flex;
      justify-content:space-between;
    }
    summary { cursor:pointer; font-weight:bold; padding:6px 0; }
    .list a { display:block; padding:4px 6px; text-decoration:none; color:#333; font-size:14px; }
    .list a:hover { background:#f0f0f0; }
  </style>
</head>
<body>
  <header class="topbar">
    <div class="brand">
      <div class="brand-logo">U</div>
      <span>IUBAT University</span>
    </div>
    <nav class="tabs">
      <a href="#" class="active">Employee Roster</a>
      <a href="#">Routine Management</a>
      <a href="#">Students Birthday</a>
    </nav>
    <div class="search">
      <input placeholder="Quick search" />
    </div>
  </header>

  <div class="shell">
    <aside class="left">
      <nav class="nav">
        <a href="#">A</a>
        <a href="#">C</a>
        <a href="#">R</a>
        <a href="#">M</a>
      </nav>
    </aside>

    <main>
      <section class="card hero">
        <img src="https://images.unsplash.com/photo-1529101091764-c3526daf38fe?q=80&w=1600&auto=format&fit=crop" alt="Campus lake" />
        <div class="status">
          <span><?php echo "Server IP: ".$_SERVER['SERVER_ADDR']; ?></span>
          <span>Not secure</span>
        </div>
      </section>
    </main>

    <aside class="right">
      <details open>
        <summary>User Login</summary>
        <div class="list">
          <a href="#">Founder</a>
          <a href="#">Vice Chancellor</a>
          <a href="#">Dean</a>
        </div>
      </details>
    </aside>
  </div>
</body>
</html>
