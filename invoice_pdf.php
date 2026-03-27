<?php
// invoice_pdf.php — FPDF version that visually matches invoice.php (with centered PAID watermark)
require_once __DIR__ . "/config.php";
if (session_status() === PHP_SESSION_NONE) session_start();

$order_id = (int)($_GET['order_id'] ?? 0);
if ($order_id <= 0) { http_response_code(404); exit("Order not found."); }

// ------- Load order -------
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

// ------- Load items -------
$it = $db->prepare("
  SELECT medicine_id, name, unit_price, qty, line_total
  FROM order_items WHERE order_id=? ORDER BY id ASC
");
$it->bind_param("i", $order_id);
$it->execute();
$items = $it->get_result()->fetch_all(MYSQLI_ASSOC);

// ------- PDF -------
require_once __DIR__ . "/lib/fpdf.php";

// avoid "Some data has already been output" error
if (ob_get_length()) { ob_end_clean(); }

class InvoicePDF extends FPDF {
  // Theme to match invoice.php
  public $brandRGB = [0,150,136];    // teal
  public $mutedRGB = [107,114,128];  // slate
  public $isPaid   = false;

  protected $angle = 0;
  public $meta_left  = '';
  public $meta_right = '';

  /* Rotation helpers for watermark */
  function Rotate($angle, $x = -1, $y = -1) {
    if ($x == -1) $x = $this->x;
    if ($y == -1) $y = $this->y;
    if ($this->angle != 0) { $this->_out('Q'); }
    $this->angle = $angle;
    if ($angle != 0) {
      $angle *= M_PI / 180;
      $c = cos($angle);
      $s = sin($angle);
      $cx = $x * $this->k;
      $cy = ($this->h - $y) * $this->k;
      $this->_out(sprintf(
        'q %.5F %.5F %.5F %.5F %.2F %.2F cm 1 0 0 1 %.2F %.2F cm',
        $c, $s, -$s, $c, $cx, $cy, -$cx, -$cy
      ));
    }
  }
  function RotatedText($x, $y, $txt, $angle) {
    $this->Rotate($angle, $x, $y);
    $this->Text($x, $y, $txt);
    $this->Rotate(0);
  }
  function _endpage() {
    if ($this->angle != 0) {
      $this->angle = 0;
      $this->_out('Q');
    }
    parent::_endpage();
  }

  function Header(){
    // Top brand bar
    [$r,$g,$b] = $this->brandRGB;
    $this->SetFillColor($r,$g,$b);
    $this->Rect(0, 0, $this->w, 4, 'F');

    // Centered big diagonal watermark if PAID
    if ($this->isPaid) {
      // Make it big and obvious (like invoice.php)
      $this->SetFont('Arial','B',60);
      // simulate translucent red/gray by using a lighter color
      $this->SetTextColor(180,60,60);
      // Position tuned for A4 portrait so it goes through center
      $this->RotatedText(35, 190, 'PAID', 45);
      $this->SetTextColor(0,0,0);
    }

    $this->Ln(8);

    // Line 1: Pharmacy Management System (center)
    $this->SetFont('Arial','B',14);
    $this->SetTextColor(17,24,39);
    $this->Cell(0,8,'Pharmacy Management System',0,1,'C');

    // Line 2: Lazz Pharma (center, teal)
    $this->SetFont('Arial','B',16);
    [$r,$g,$b] = $this->brandRGB;
    $this->SetTextColor($r,$g,$b);
    $this->Cell(0,9,'Lazz Pharma',0,1,'C');

    // Meta row: left = Invoice No, right = Date
    $this->Ln(1);
    $this->SetFont('Arial','',10);
    $this->SetTextColor(85,85,85);
    $y = $this->GetY();
    $this->SetXY(10, $y);
    $this->Cell(95,6, $this->meta_left, 0, 0, 'L');
    $this->SetXY(105, $y);
    $this->Cell(95,6, $this->meta_right, 0, 1, 'R');

    // Divider
    $this->SetDrawColor(229,231,235);
    $this->Ln(3);
    $this->Line(10, $this->GetY(), 200, $this->GetY());
    $this->Ln(4);
  }

  function Footer(){
    $this->SetY(-15);
    $this->SetFont('Arial','I',8);
    $this->SetTextColor(120,120,120);
    $this->Cell(0,8,'Generated: '.date('Y-m-d H:i').'  |  Page '.$this->PageNo().'/{nb}',0,0,'C');
  }
}

$pdf = new InvoicePDF('P','mm','A4');
$pdf->AliasNbPages();

$st = strtoupper((string)$order['status']);
$pdf->isPaid = ($st === 'PAID');

// Header meta (match invoice.php)
$pdf->meta_left  = 'Invoice No: INV-'.(int)$order['id'];
$pdf->meta_right = 'Date: '.date('Y-m-d', strtotime($order['created_at'] ?? 'now'));

$pdf->AddPage();

/* ========== Three Info Boxes (Customer / Payment / Order) ========== */
$boxW = 190/3;

function box_header($pdf, $txt, $x, $y, $w){
  $pdf->SetXY($x,$y);
  $pdf->SetFont('Arial','B',10);
  $pdf->SetFillColor(230,255,250); // soft teal
  $pdf->SetDrawColor(229,231,235);
  $pdf->SetTextColor(15,118,110);
  $pdf->Cell($w,7,$txt,1,1,'L',true);
  $pdf->SetTextColor(0,0,0);
}
function box_row($pdf, $x, $w, $label, $value){
  $pdf->SetX($x);
  $pdf->SetFont('Arial','',9);
  $pdf->SetDrawColor(229,231,235);
  $pdf->Cell($w,6, $label.' '.$value, 1, 1, 'L');
}

$yStart = $pdf->GetY();

// Customer
box_header($pdf, 'Customer Information', 10, $yStart, $boxW);
box_row($pdf, 10, $boxW, 'Name:',  $order['user_name']);
box_row($pdf, 10, $boxW, 'Email:', $order['user_email'] ?: 'N/A');
box_row($pdf, 10, $boxW, 'Phone:', $order['user_phone'] ?: 'N/A');
box_row($pdf, 10, $boxW, 'Address:', $order['user_address'] ?: 'N/A');

// Payment
box_header($pdf, 'Payment Information', 10+$boxW, $yStart, $boxW);
box_row($pdf, 10+$boxW, $boxW, 'Method:', $order['payment_method']);
box_row($pdf, 10+$boxW, $boxW, 'Transaction ID:', $order['transaction_id'] ?: 'N/A');
box_row($pdf, 10+$boxW, $boxW, 'Status:', $order['status']);
box_row($pdf, 10+$boxW, $boxW, '', ''); // filler

// Order
box_header($pdf, 'Order Information', 10+2*$boxW, $yStart, $boxW);
box_row($pdf, 10+2*$boxW, $boxW, 'Order ID:', 'ORD-'.(int)$order['id']);
box_row($pdf, 10+2*$boxW, $boxW, 'Order Date:', date('Y-m-d', strtotime($order['created_at'] ?? 'now')));
box_row($pdf, 10+2*$boxW, $boxW, 'Status:', $order['status']);
box_row($pdf, 10+2*$boxW, $boxW, '', ''); // filler

$pdf->Ln(4);

/* ========== Items Table (teal header + zebra rows) ========== */
[$r,$g,$b] = $pdf->brandRGB;
$pdf->SetFillColor($r,$g,$b);
$pdf->SetTextColor(255,255,255);
$pdf->SetFont('Arial','B',10);

$w = [100, 20, 30, 30]; // Product, Qty, Unit, Subtotal  => total 180mm
$pdf->SetX(15);
$pdf->Cell($w[0],8,'Product',1,0,'L',true);
$pdf->Cell($w[1],8,'Qty',1,0,'C',true);
$pdf->Cell($w[2],8,'Unit (Tk)',1,0,'R',true);
$pdf->Cell($w[3],8,'Subtotal (Tk)',1,1,'R',true);

$pdf->SetFont('Arial','',10);
$pdf->SetTextColor(0,0,0);
$fill = false;

foreach ($items as $r) {
  $pdf->SetX(15);
  // zebra bg
  if ($fill) { $pdf->SetFillColor(251,251,252); } else { $pdf->SetFillColor(255,255,255); }
  $pdf->Cell($w[0],8,$r['name'],1,0,'L',true);
  $pdf->Cell($w[1],8,(int)$r['qty'],1,0,'C',true);
  $pdf->Cell($w[2],8,number_format((float)$r['unit_price'],2),1,0,'R',true);
  $pdf->Cell($w[3],8,number_format((float)$r['line_total'],2),1,1,'R',true);
  $fill = !$fill;
}

/* ========== Totals ========== */
$pdf->SetX(15);
$pdf->SetFont('Arial','',10);
$pdf->Cell($w[0]+$w[1]+$w[2],8,'Subtotal',1,0,'R');
$pdf->Cell($w[3],8,number_format((float)$order['subtotal'],2),1,1,'R');

$pdf->SetX(15);
$pdf->Cell($w[0]+$w[1]+$w[2],8,'VAT (5%)',1,0,'R');
$pdf->Cell($w[3],8,number_format((float)$order['vat'],2),1,1,'R');

$pdf->SetX(15);
$pdf->Cell($w[0]+$w[1]+$w[2],8,'Delivery Charge',1,0,'R');
$pdf->Cell($w[3],8,number_format((float)$order['delivery_charge'],2),1,1,'R');

$pdf->SetX(15);
$pdf->SetFont('Arial','B',11);
$pdf->Cell($w[0]+$w[1]+$w[2],9,'Total',1,0,'R');
$pdf->Cell($w[3],9,number_format((float)$order['grand_total'],2),1,1,'R');

/* ========== Footer Note ========== */
$pdf->Ln(6);
$pdf->SetFont('Arial','',10);
$pdf->SetTextColor(107,114,128);
$pdf->Cell(0,6,'Thank you for shopping with us!',0,1,'C');
$pdf->SetTextColor(0,0,0);

// Force download
$filename = 'invoice_'.(int)$order['id'].'_'.date('Ymd_His').'.pdf';
$pdf->Output('D', $filename);
exit;
