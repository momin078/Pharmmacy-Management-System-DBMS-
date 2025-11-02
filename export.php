<?php
session_start();
require_once __DIR__ . "/config.php"; // DB connection

// CSRF check
if (empty($_SESSION['csrf']) || ($_GET['csrf'] ?? '') !== $_SESSION['csrf']) {
  die("Invalid token.");
}

// include FPDF
require_once __DIR__ . '/fpdf/fpdf.php';  // আপনার FPDF যেখানে রেখেছেন সেই path দিন

// ডাটা আনা
$sql = "SELECT id,name,category,company,dosage_form,strength,price,quantity 
        FROM medicines ORDER BY id DESC";
$result = $db->query($sql);

// নতুন PDF অবজেক্ট
$pdf = new FPDF();
$pdf->AddPage();
$pdf->SetFont('Arial','B',14);
$pdf->Cell(0,10, '💊 Medicine List', 0,1,'C');
$pdf->SetFont('Arial','',10);
$pdf->Cell(0,8,'Generated: '.date('Y-m-d H:i'),0,1,'C');
$pdf->Ln(5);

// টেবিল হেডার
$pdf->SetFont('Arial','B',9);
$pdf->SetFillColor(230,230,230);
$headers = ['ID','Name','Category','Company','Form','Strength','Price','Qty','Value'];
$widths  = [10,30,25,30,20,20,20,15,20];
for($i=0;$i<count($headers);$i++){
    $pdf->Cell($widths[$i],8,$headers[$i],1,0,'C',true);
}
$pdf->Ln();

// টেবিল ডাটা
$pdf->SetFont('Arial','',9);
$total_items = 0;
$total_value = 0.0;

if ($result && $result->num_rows){
  while($row = $result->fetch_assoc()){
    $id = (int)$row['id'];
    $name = $row['name'];
    $cat = $row['category'];
    $comp = $row['company'];
    $form = $row['dosage_form'];
    $strength = $row['strength'];
    $price = (float)$row['price'];
    $qty = (int)$row['quantity'];
    $value = $price * $qty;

    $pdf->Cell($widths[0],8,$id,1);
    $pdf->Cell($widths[1],8,utf8_decode($name),1);
    $pdf->Cell($widths[2],8,utf8_decode($cat),1);
    $pdf->Cell($widths[3],8,utf8_decode($comp),1);
    $pdf->Cell($widths[4],8,$form,1);
    $pdf->Cell($widths[5],8,$strength,1);
    $pdf->Cell($widths[6],8,'৳ '.number_format($price,2),1,0,'R');
    $pdf->Cell($widths[7],8,$qty,1,0,'R');
    $pdf->Cell($widths[8],8,'৳ '.number_format($value,2),1,0,'R');
    $pdf->Ln();

    $total_items += $qty;
    $total_value += $value;
  }
} else {
  $pdf->Cell(array_sum($widths),10,'No medicines found',1,1,'C');
}

// টোটাল রো
$pdf->SetFont('Arial','B',9);
$pdf->Cell(array_sum($widths)-($widths[7]+$widths[8]),8,'Totals',1,0,'R');
$pdf->Cell($widths[7],8,$total_items,1,0,'R');
$pdf->Cell($widths[8],8,'৳ '.number_format($total_value,2),1,0,'R');

$pdf->Output('D','medicine_list_'.date('Ymd_His').'.pdf');
