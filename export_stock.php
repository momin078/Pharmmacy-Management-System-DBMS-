<?php
// export_stock.php
ob_start(); // prevent accidental output before PDF headers
if (session_status()===PHP_SESSION_NONE) session_start();
require_once __DIR__ . "/config.php";
require_once __DIR__ . "/lib/pdf_theme.php"; // uses FPDF internally

date_default_timezone_set('Asia/Dhaka');

// ---- CSRF (required) ----
if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(16));
$csrf = $_GET['csrf'] ?? '';
if (!hash_equals($_SESSION['csrf'], $csrf)) {
  if (ob_get_length()) ob_end_clean();
  http_response_code(400);
  die("Invalid token");
}

// ---- Brand (from footer.php; NO HTML output) ----
define('FOOTER_EXPORT_INFO', true);
$brand = include __DIR__ . '/footer.php';
$brand['PRINTED_AT'] = date('Y-m-d H:i'); // for header timestamp fallback

// ---- Helpers ----
function money($n){ return '৳ '.number_format((float)$n,2); }
function outpdf($pdf,$name){
  if (ob_get_length()) ob_end_clean();
  header('Content-Type: application/pdf');
  $pdf->Output('D',$name);
  exit;
}
function q($db,$sql){ return $db->query($sql); }

// ---- Which section? ----
$section = $_GET['section'] ?? ''; // cat | top | low | expiring | expired | items

// ---- Column widths (A4 inner ≈ 190mm) ----
$W_CAT = [120, 70];                 // Category, Qty
$W_TOP = [80, 25, 30, 55];          // Name, Qty, Price, Value
$W_LOW = [18, 86, 28, 58];          // ID, Name, Qty, Reorder
$W_EXP = [18, 86, 40, 46];          // ID, Name, Expire, Qty
$W_ALL = [18, 72, 40, 30, 30];      // ID, Name, Category, Qty, Price

switch ($section) {

  /* ---------- Category breakdown ---------- */
  case 'cat': {
    $title = "Category Breakdown";
    $HEAD  = ['Category','Qty'];
    $pdf = pdf_make($title, $HEAD, $W_CAT, $brand, true);
    $pdf->AddPage(); $pdf->SetFont('Arial','',9);

    $res = q($db,"SELECT category, SUM(quantity) as qty
                  FROM medicines
                  GROUP BY category
                  ORDER BY qty DESC");
    if ($res && $res->num_rows){
      while($r=$res->fetch_assoc()){
        $pdf->Cell($W_CAT[0],8, utf8_decode((string)$r['category']), 1,0,'L');
        $pdf->Cell($W_CAT[1],8, (int)$r['qty'],                      1,0,'R');
        $pdf->Ln();
        if ($pdf->GetY()>260) $pdf->AddPage();
      }
    } else {
      $pdf->Cell(array_sum($W_CAT),10,'No data',1,1,'C');
    }
    outpdf($pdf, 'stock_category_'.date('Ymd_His').'.pdf');
  }

  /* ---------- Top 10 by stock value ---------- */
  case 'top': {
    $title = "Top 10 by Stock Value";
    $HEAD  = ['Name','Qty','Price','Value'];
    $pdf = pdf_make($title, $HEAD, $W_TOP, $brand, true);
    $pdf->AddPage(); $pdf->SetFont('Arial','',9);

    $res = q($db,"SELECT name, quantity, price, (quantity*price) as value
                  FROM medicines
                  ORDER BY value DESC
                  LIMIT 10");
    if ($res && $res->num_rows){
      while($r=$res->fetch_assoc()){
        $pdf->Cell($W_TOP[0],8, utf8_decode((string)$r['name']), 1,0,'L');
        $pdf->Cell($W_TOP[1],8, (int)$r['quantity'],            1,0,'R');
        $pdf->Cell($W_TOP[2],8, money($r['price']),             1,0,'R');
        $pdf->Cell($W_TOP[3],8, money($r['value']),             1,0,'R');
        $pdf->Ln();
        if ($pdf->GetY()>260) $pdf->AddPage();
      }
    } else {
      $pdf->Cell(array_sum($W_TOP),10,'No data',1,1,'C');
    }
    outpdf($pdf, 'stock_top_value_'.date('Ymd_His').'.pdf');
  }

  /* ---------- Low stock (Qty < 20) ---------- */
  case 'low': {
    $title = "Low Stock (Qty < 20)";
    $HEAD  = ['ID','Name','Qty','Reorder Level'];
    $pdf = pdf_make($title, $HEAD, $W_LOW, $brand, true);
    $pdf->AddPage(); $pdf->SetFont('Arial','',9);

    $res = q($db,"SELECT id, name, quantity, reorder_level
                  FROM medicines
                  WHERE quantity < 20
                  ORDER BY quantity ASC");
    $total_qty = 0; $rows = 0;
    if ($res && $res->num_rows){
      while($r=$res->fetch_assoc()){
        $pdf->Cell($W_LOW[0],8, (int)$r['id'],                    1,0,'C');
        $pdf->Cell($W_LOW[1],8, utf8_decode((string)$r['name']),  1,0,'L');
        $pdf->Cell($W_LOW[2],8, (int)$r['quantity'],              1,0,'R');
        $pdf->Cell($W_LOW[3],8, (int)$r['reorder_level'],         1,0,'R');
        $pdf->Ln();
        $total_qty += (int)$r['quantity']; $rows++;
        if ($pdf->GetY()>260) $pdf->AddPage();
      }
      // summary
      $pdf->SetFont('Arial','B',9);
      $pdf->Cell($W_LOW[0]+$W_LOW[1],8,'Totals',1,0,'R');
      $pdf->Cell($W_LOW[2],8,$total_qty,1,0,'R');
      $pdf->Cell($W_LOW[3],8,$rows.' items',1,0,'R');
    } else {
      $pdf->Cell(array_sum($W_LOW),10,'No low-stock items (Qty < 20)',1,1,'C');
    }
    outpdf($pdf, 'stock_low_'.date('Ymd_His').'.pdf');
  }

  /* ---------- Expiring within 60 days ---------- */
  case 'expiring': {
    $title = "Expiring within 60 Days";
    $HEAD  = ['ID','Name','Expire','Qty'];
    $pdf = pdf_make($title, $HEAD, $W_EXP, $brand, true);
    $pdf->AddPage(); $pdf->SetFont('Arial','',9);

    $res = q($db,"SELECT id, name, expire_date, quantity
                  FROM medicines
                  WHERE expire_date IS NOT NULL
                    AND expire_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(),INTERVAL 60 DAY)
                  ORDER BY expire_date ASC");
    if ($res && $res->num_rows){
      while($r=$res->fetch_assoc()){
        $pdf->Cell($W_EXP[0],8, (int)$r['id'],                    1,0,'C');
        $pdf->Cell($W_EXP[1],8, utf8_decode((string)$r['name']),  1,0,'L');
        $pdf->Cell($W_EXP[2],8, (string)$r['expire_date'],        1,0,'C');
        $pdf->Cell($W_EXP[3],8, (int)$r['quantity'],              1,0,'R');
        $pdf->Ln();
        if ($pdf->GetY()>260) $pdf->AddPage();
      }
    } else {
      $pdf->Cell(array_sum($W_EXP),10,'No expiring items',1,1,'C');
    }
    outpdf($pdf, 'stock_expiring_'.date('Ymd_His').'.pdf');
  }

  /* ---------- Already expired ---------- */
  case 'expired': {
    $title = "Expired Items";
    $HEAD  = ['ID','Name','Expire','Qty'];
    $pdf = pdf_make($title, $HEAD, $W_EXP, $brand, true);
    $pdf->AddPage(); $pdf->SetFont('Arial','',9);

    $res = q($db,"SELECT id, name, expire_date, quantity
                  FROM medicines
                  WHERE expire_date IS NOT NULL
                    AND expire_date < CURDATE()
                  ORDER BY expire_date ASC");
    if ($res && $res->num_rows){
      while($r=$res->fetch_assoc()){
        $pdf->Cell($W_EXP[0],8, (int)$r['id'],                    1,0,'C');
        $pdf->Cell($W_EXP[1],8, utf8_decode((string)$r['name']),  1,0,'L');
        $pdf->Cell($W_EXP[2],8, (string)$r['expire_date'],        1,0,'C');
        $pdf->Cell($W_EXP[3],8, (int)$r['quantity'],              1,0,'R');
        $pdf->Ln();
        if ($pdf->GetY()>260) $pdf->AddPage();
      }
    } else {
      $pdf->Cell(array_sum($W_EXP),10,'No expired items',1,1,'C');
    }
    outpdf($pdf, 'stock_expired_'.date('Ymd_His').'.pdf');
  }

  /* ---------- Full detailed list (latest 50) ---------- */
  case 'items': {
    $title = "Full Detailed List (Latest 50)";
    $HEAD  = ['ID','Name','Category','Qty','Price'];
    $pdf = pdf_make($title, $HEAD, $W_ALL, $brand, true);
    $pdf->AddPage(); $pdf->SetFont('Arial','',9);

    $res = q($db,"SELECT id, name, category, quantity, price
                  FROM medicines
                  ORDER BY id DESC
                  LIMIT 50");
    if ($res && $res->num_rows){
      while($r=$res->fetch_assoc()){
        $pdf->Cell($W_ALL[0],8, (int)$r['id'],                    1,0,'C');
        $pdf->Cell($W_ALL[1],8, utf8_decode((string)$r['name']),  1,0,'L');
        $pdf->Cell($W_ALL[2],8, (string)$r['category'],           1,0,'L');
        $pdf->Cell($W_ALL[3],8, (int)$r['quantity'],              1,0,'R');
        $pdf->Cell($W_ALL[4],8, money($r['price']),               1,0,'R');
        $pdf->Ln();
        if ($pdf->GetY()>260) $pdf->AddPage();
      }
    } else {
      $pdf->Cell(array_sum($W_ALL),10,'No items found',1,1,'C');
    }
    outpdf($pdf, 'stock_items_'.date('Ymd_His').'.pdf');
  }

  default: {
    if (ob_get_length()) ob_end_clean();
    http_response_code(400);
    echo "Unknown section";
    exit;
  }
}
