<?php
// export_invoice_fpdf.php — Render invoice as PDF using FPDF (no HTML dependency)
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . "/config.php";

// ---- helpers (ASCII only for FPDF core fonts) ----
function s($text) {
  // Convert to windows-1252 best-effort; replace unknowns with '?'
  $t = (string)$text;
  $out = @iconv('UTF-8', 'windows-1252//TRANSLIT', $t);
  if ($out === false) $out = preg_replace('/[^\x20-\x7E]/', '?', $t);
  return $out;
}
function money_ascii($n) {
  $n = (float)$n;
  return 'Tk ' . number_format($n, 2); // Use 'Tk ' to avoid Unicode issues
}

$order_id = (int)($_GET['order_id'] ?? 0);
if ($order_id <= 0) { http_response_code(404); exit("Order not found."); }

// ---- load order ----
$ord = $db->prepare("
  SELECT id, user_id, user_name, user_email, user_phone, user_address,
         payment_method, transaction_id, status,
         subtotal, vat, delivery_charge, grand_total,
         created_at
  FROM orders WHERE id=? LIMIT 1
");
$ord->bind_param("i", $order_id);
$ord->execute();
$order = $ord->get_result()->fetch_assoc();
if (!$order) { http_response_code(404); exit("Order not found."); }

// ---- load items ----
$it = $db->prepare("
  SELECT medicine_id, name, unit_price, qty, line_total
  FROM order_items WHERE order_id=? ORDER BY id ASC
");
$it->bind_param("i", $order_id);
$it->execute();
$items = $it->get_result()->fetch_all(MYSQLI_ASSOC);

// ---- FPDF ----
require_once __DIR__ . "/lib/fpdf.php";

class InvoicePDF extends FPDF {
  public $title1 = 'Pharmacy Management System for';
  public $title2 = 'Lazz Pharma';
  public $invNo  = '';
  public $dateStr= '';
  public $paid   = false;

  function Header() {
    // Title
    $this->SetFont('Arial','B',14);
    $this->Cell(0,8, s($this->title1), 0, 1, 'C');
    $this->SetFont('Arial','B',16);
    $this->Cell(0,9, s($this->title2), 0, 1, 'C');

    $this->SetFont('Arial','',10);
    $this->Cell(0,6, s("Invoice No: ".$this->invNo), 0, 1, 'C');
    $this->Cell(0,6, s("Date: ".$this->dateStr), 0, 1, 'C');
    $this->Ln(3);

    // Watermark if PAID (simple header corner note)
    if ($this->paid) {
      // FPDF-এ রোটেটেড বড় টেক্সটের জন্য ট্রিক বাদ দিলাম; হেডারে নোট দেখাই
      $this->SetTextColor(0, 128, 0);
      $this->SetFont('Arial','B',12);
      $this->Cell(0,7, s("PAID"), 0, 1, 'R');
      $this->SetTextColor(0, 0, 0);
      $this->Ln(1);
    }
  }
  function Footer(){
    $this->SetY(-15);
    $this->SetFont('Arial','I',8);
    $this->Cell(0,8, s('Page '.$this->PageNo().'/{nb}'), 0, 0, 'C');
  }
}

$pdf = new InvoicePDF();
$pdf->AliasNbPages();
$pdf->SetTitle('Invoice '.$order_id, true);
$pdf->invNo  = 'INV-'.$order_id;
$pdf->dateStr= date('Y-m-d', strtotime($order['created_at'] ?? 'now'));
$pdf->paid   = (strtoupper((string)$order['status']) === 'PAID');

$pdf->AddPage();
$pdf->SetAutoPageBreak(true, 18);

// ===== Info boxes (3 columns like your HTML) =====
$startX = 10;
$w = 190;              // total printable width
$colW = ($w - 0) / 3;  // three columns
$y = $pdf->GetY();

$pdf->SetFont('Arial','B',11);
$pdf->SetFillColor(245,245,245);
$pdf->SetDrawColor(220,220,220);

// Customer
$pdf->SetXY($startX, $y);
$pdf->Cell($colW, 7, s("Customer Information"), 1, 2, 'L', true);
$pdf->SetFont('Arial','',10);
$pdf->MultiCell($colW, 6, s(
  "Name: ".$order['user_name']."\n".
  "Email: ".($order['user_email'] ?: 'N/A')."\n".
  "Phone: ".($order['user_phone'] ?: 'N/A')."\n".
  "Address: ".($order['user_address'] ?: 'N/A')
), 1);

// Payment
$pdf->SetXY($startX + $colW, $y);
$pdf->SetFont('Arial','B',11);
$pdf->Cell($colW, 7, s("Payment Information"), 1, 2, 'L', true);
$pdf->SetFont('Arial','',10);
$pdf->MultiCell($colW, 6, s(
  "Method: ".$order['payment_method']."\n".
  "Transaction ID: ".($order['transaction_id'] ?: 'N/A')."\n".
  "Status: ".$order['status']
), 1);

// Order
$pdf->SetXY($startX + 2*$colW, $y);
$pdf->SetFont('Arial','B',11);
$pdf->Cell($colW, 7, s("Order Information"), 1, 2, 'L', true);
$pdf->SetFont('Arial','',10);
$pdf->MultiCell($colW, 6, s(
  "Order ID: ORD-".$order['id']."\n".
  "Order Date: ".date('Y-m-d', strtotime($order['created_at'] ?? 'now'))."\n".
  "Status: ".$order['status']
), 1);

$pdf->Ln(6);

// ===== Items table =====
$pdf->SetFont('Arial','B',10);
$pdf->SetFillColor(0,150,136); // brand-ish
$pdf->SetTextColor(255,255,255);
$head = ['Product','Qty','Unit Price (Tk)','Subtotal (Tk)'];
$wcols= [100, 20, 35, 35];   // total 190
for ($i=0; $i<count($head); $i++){
  $pdf->Cell($wcols[$i], 8, s($head[$i]), 1, 0, 'C', true);
}
$pdf->Ln();
$pdf->SetTextColor(0,0,0);
$pdf->SetFont('Arial','',10);

if ($items) {
  foreach ($items as $r) {
    $pdf->Cell($wcols[0], 7, s($r['name']), 1);
    $pdf->Cell($wcols[1], 7, (int)$r['qty'], 1, 0, 'C');
    $pdf->Cell($wcols[2], 7, s(money_ascii($r['unit_price'])), 1, 0, 'R');
    $pdf->Cell($wcols[3], 7, s(money_ascii($r['line_total'])), 1, 0, 'R');
    $pdf->Ln();
    if ($pdf->GetY() > 260) $pdf->AddPage();
  }
} else {
  $pdf->Cell(array_sum($wcols), 9, s('No items.'), 1, 1, 'C');
}

// Totals
$pdf->SetFont('Arial','',10);
$pdf->Cell($wcols[0]+$wcols[1]+$wcols[2], 7, s('Subtotal'), 1, 0, 'R');
$pdf->Cell($wcols[3], 7, s(money_ascii($order['subtotal'])), 1, 1, 'R');

$pdf->Cell($wcols[0]+$wcols[1]+$wcols[2], 7, s('VAT (5%)'), 1, 0, 'R');
$pdf->Cell($wcols[3], 7, s(money_ascii($order['vat'])), 1, 1, 'R');

$pdf->Cell($wcols[0]+$wcols[1]+$wcols[2], 7, s('Delivery Charge'), 1, 0, 'R');
$pdf->Cell($wcols[3], 7, s(money_ascii($order['delivery_charge'])), 1, 1, 'R');

$pdf->SetFont('Arial','B',10);
$pdf->Cell($wcols[0]+$wcols[1]+$wcols[2], 8, s('Total'), 1, 0, 'R');
$pdf->Cell($wcols[3], 8, s(money_ascii($order['grand_total'])), 1, 1, 'R');

// Thank you
$pdf->Ln(6);
$pdf->SetFont('Arial','',10);
$pdf->Cell(0, 6, s('Thank you for shopping with us!'), 0, 1, 'L');

// Footer note (developer)
$pdf->Ln(2);
$pdf->SetFont('Arial','I',9);
$pdf->SetTextColor(100,100,100);
$pdf->Cell(0, 6, s('Developed by Md Mokhlesur Rahman Momin'), 0, 1, 'C');
$pdf->SetTextColor(0,0,0);

// ---- Output ----
if (ob_get_length()) ob_end_clean();
$fname = 'invoice_'.$order_id.'_'.date('Ymd_His').'.pdf';
$pdf->Output('D', $fname);
