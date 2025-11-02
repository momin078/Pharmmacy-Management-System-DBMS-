<?php
// config.php — env-aware, safe defaults, single mysqli $db

date_default_timezone_set('Asia/Dhaka');

// ENV: 'dev' | 'prod'
if (!defined('APP_ENV')) {
  define('APP_ENV', getenv('APP_ENV') ?: 'dev');
}
// Demo payment: prod-এ OFF, dev-এ ON
if (!defined('PAYMENT_DEMO')) {
  define('PAYMENT_DEMO', APP_ENV !== 'prod');
}

// Error handling
if (APP_ENV === 'prod') {
  ini_set('display_errors', '0');
  ini_set('log_errors', '1');
} else {
  ini_set('display_errors', '1');
  ini_set('log_errors', '1');
}

// Session cookie hardening (only if no session yet & no output)
if (session_status() === PHP_SESSION_NONE && !headers_sent()) {
  ini_set('session.use_only_cookies', '1');
  ini_set('session.cookie_httponly', '1');
  ini_set('session.cookie_samesite', 'Lax');
  $secure = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
  if ($secure) ini_set('session.cookie_secure', '1');
  session_set_cookie_params([
    'lifetime' => 0,
    'path' => '/',
    'domain' => '',
    'secure' => $secure,
    'httponly' => true,
    'samesite' => 'Lax',
  ]);
}

// DB creds
if (!defined('DB_HOST')) define('DB_HOST', getenv('DB_HOST') ?: 'localhost');
if (!defined('DB_USER')) define('DB_USER', getenv('DB_USER') ?: 'root');
if (!defined('DB_PASS')) define('DB_PASS', getenv('DB_PASS') ?: '');
if (!defined('DB_NAME')) define('DB_NAME', getenv('DB_NAME') ?: 'pharmacy');
if (!defined('DB_PORT')) define('DB_PORT', (int)(getenv('DB_PORT') ?: 3306));

// mysqli reporting
if (APP_ENV === 'dev') {
  mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
} else {
  mysqli_report(MYSQLI_REPORT_OFF);
}

// Connect (single global $db)
try {
  $db = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME, DB_PORT);
  $db->set_charset('utf8mb4');
  $db->query("SET time_zone = '+06:00'");
  $db->query("SET sql_mode = 'STRICT_TRANS_TABLES,NO_ENGINE_SUBSTITUTION'");
} catch (Throwable $e) {
  if (APP_ENV !== 'prod') error_log('DB connect error: '.$e->getMessage());
  http_response_code(500);
  die('Database connection failed.');
}

// Helpers
if (!function_exists('h')) {
  function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
}
if (!function_exists('money')) {
  function money($n){ $n=(float)$n; $d=(floor($n)===$n)?0:2; return '৳ '.number_format($n,$d); }
}
if (!function_exists('intget')) {
  function intget($arr, $key, $default=0){
    if (!isset($arr[$key]) || $arr[$key]==='') return (int)$default;
    return (int)$arr[$key];
  }
}
if (!function_exists('demo_trx')) {
  function demo_trx(string $method): string {
    $prefix = ($method === 'Card') ? 'CARD' : 'MBK';
    try { $rand = bin2hex(random_bytes(4)); } catch (Throwable $e) { $rand = dechex(mt_rand()); }
    return $prefix.'-'.strtoupper($rand);
  }
}
// NOTE: session_start() এখানে নেই—প্রতিটি পেজে config include করার পরেই session_start() কল করো।
