<?php
// export_emp.php (receipt-style for single, list for all)
ob_start(); // prevent accidental output
session_start();
require_once __DIR__ . "/config.php";
require_once __DIR__ . "/lib/fpdf.php";     // for receipt (single)
require_once __DIR__ . "/lib/pdf_theme.php"; // for list (A4)

// footer.php থেকে ব্র্যান্ড ইনফো (HTML ছাড়া)
define('FOOTER_EXPORT_INFO', true);
$brand = include __DIR__ . '/footer.php';

function safe_pdf_output($pdf, $filename, $dest='D'){
  if (ob_get_length()) ob_end_clean();
  header('Content-Type: application/pdf');
  $pdf->Output($dest, $filename);
  exit;
}

/* ডেমো বারকোড (ভিজ্যুয়াল): দরকার হলে বাস্তব Code128 ব্যবহার করুন */
function draw_barcode($pdf,$x,$y,$w,$h,$text){
  $pdf->SetFillColor(0,0,0);
  $hash = md5($text); $bits='';
  foreach (str_split($hash) as $ch) $bits .= str_pad(base_convert($ch,16,2),4,'0',STR_PAD_LEFT);
  $bars = substr($bits,0,100);
  $barW = max(0.3, $w / strlen($bars));
  $cx = $x;
  for ($i=0; $i<strlen($bars); $i++){
    if ($bars[$i]==='1') $pdf->Rect($cx,$y,$barW*0.8,$h,'F');
    $cx += $barW; if ($cx > $x+$w) break;
  }
  $pdf->SetFont('Arial','',8);
  $pdf->SetXY($x, $y+$h+1.5);
  $pdf->Cell($w, 4, $text, 0, 0, 'C');
}

/* ============ Single Employee: RECEIPT STYLE (80mm) ============ */
$empId = isset($_GET['id']) ? trim($_GET['id']) : '';
if ($empId !== '') {
  $stmt = $db->prepare("SELECT emp_id,emp_name,emp_email,emp_phone,emp_position,emp_address FROM employee WHERE emp_id=? LIMIT 1");
  $stmt->bind_param("s", $empId);
  $stmt->execute();
  $res = $stmt->get_result();
  if (!$res || !$res->num_rows) { if (ob_get_length()) ob_end_clean(); die("Employee not found"); }
  $e = $res->fetch_assoc();

  // 80mm thermal receipt size; height will be enough for details
  $w = 80; $h = 130;
  $pdf = new FPDF('P','mm',[$w,$h]);
  $pdf->SetMargins(6,5,6);
  $pdf->AddPage();

  // Shop header (Lazz Pharma from footer.php)
  $pdf->SetFont('Arial','B',14);
  $pdf->Cell(0,7, $brand['org_name'] ?? 'Lazz Pharma', 0,1,'C');
  $pdf->SetFont('Arial','',9);
  $pdf->Cell(0,5, $brand['address'] ?? '', 0,1,'C');
  $pdf->Cell(0,5, 'Tel: '.( ($brand['phone'] ?? '') ?: ($brand['mobile'] ?? '') ), 0,1,'C');

  // decorative star line
  $pdf->Cell(0,5, str_repeat('* ', 18), 0,1,'C');

  // Title
  $pdf->SetFont('Arial','B',11);
  $pdf->Cell(0,6, 'EMPLOYEE Details', 0,1,'C');

  $pdf->Cell(0,5, str_repeat('* ', 18), 0,1,'C');

  // Meta
  $pdf->SetFont('Arial','',9);
  $pdf->Cell(0,5, 'Date: '.date('Y-m-d H:i'), 0,1,'L');

  // Details block
  $pdf->Ln(1);
  $pdf->SetFont('Arial','B',10);
  $pdf->Cell(0,6,'Details',0,1,'L');

  $pdf->SetFont('Arial','',9);
  $pdf->Cell(0,5,'Employee ID: '.$e['emp_id'],0,1,'L');
  $pdf->Cell(0,5,'Name: '.$e['emp_name'],0,1,'L');
  $pdf->Cell(0,5,'Email: '.$e['emp_email'],0,1,'L');
  $pdf->Cell(0,5,'Phone: '.$e['emp_phone'],0,1,'L');
  $pdf->Cell(0,5,'Position: '.$e['emp_position'],0,1,'L');
  $pdf->MultiCell(0,5,'Address: '.$e['emp_address']);

  // Separator + Thank you
  $pdf->Cell(0,5, str_repeat('* ', 18), 0,1,'C');
  $pdf->SetFont('Arial','B',11);
  $pdf->Cell(0,6,'THANK YOU!',0,1,'C');

  // Pseudo barcode at bottom
  draw_barcode($pdf, 10, $pdf->GetY()+2, $w-20, 10, 'EMP-'.$e['emp_id']);

  safe_pdf_output($pdf, 'employee_'.$e['emp_id'].'_'.date('Ymd_His').'.pdf');
}

/* ============ All Employees: LIST (A4) ============ */
$HEAD = ['ID','Name','Email','Phone','Position','Address'];
$W    = [20, 35, 45, 28, 28, 34]; // ≈190mm total for A4 inner width

$pdf = pdf_make('Employee List', $HEAD, $W, $brand, true);
$pdf->AddPage();
$pdf->SetFont('Arial','',9);

$res = $db->query("SELECT emp_id,emp_name,emp_email,emp_phone,emp_position,emp_address FROM employee ORDER BY emp_id DESC");
if ($res && $res->num_rows) {
  while ($r = $res->fetch_assoc()) {
    $cells = [
      $r['emp_id'],
      utf8_decode($r['emp_name']),
      $r['emp_email'],
      $r['emp_phone'],
      $r['emp_position'],
      utf8_decode($r['emp_address']),
    ];
    foreach ($cells as $i=>$t) {
      $pdf->Cell($W[$i], 8, $t, 1, 0, ($i==0?'C':'L'));
    }
    $pdf->Ln();
    if ($pdf->GetY() > 260) $pdf->AddPage();
  }
} else {
  $pdf->Cell(array_sum($W),10,'No employees found',1,1,'C');
}

safe_pdf_output($pdf, 'employee_list_'.date('Ymd_His').'.pdf');
