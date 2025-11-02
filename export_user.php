<?php
ob_start(); // prevent accidental output
session_start();
require_once __DIR__ . "/config.php";
require_once __DIR__ . "/lib/fpdf.php";

/* footer.php থেকে ব্র্যান্ড ইনফো */
define('FOOTER_EXPORT_INFO', true);
$brand = include __DIR__ . '/footer.php';

function safe_output_pdf($pdf,$name,$dest='D'){ if(ob_get_length()) ob_end_clean(); header('Content-Type: application/pdf'); $pdf->Output($dest,$name); exit; }
function draw_barcode($pdf,$x,$y,$w,$h,$text){
  $pdf->SetFillColor(0,0,0); $hash=md5($text); $bits='';
  foreach(str_split($hash) as $ch){ $bits.=str_pad(base_convert($ch,16,2),4,'0',STR_PAD_LEFT); }
  $bars=substr($bits,0,100); $barW=max(0.3,$w/strlen($bars)); $cx=$x;
  for($i=0;$i<strlen($bars);$i++){ if($bars[$i]==='1') $pdf->Rect($cx,$y,$barW*0.8,$h,'F'); $cx+=$barW; if($cx>$x+$w) break; }
  $pdf->SetFont('Arial','',8); $pdf->SetXY($x,$y+$h+1.5); $pdf->Cell($w,4,$text,0,0,'C');
}

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($id > 0) {
  // Single user -> receipt style
  $stmt=$db->prepare("SELECT id,name,email,contact,address FROM users WHERE id=? LIMIT 1");
  $stmt->bind_param("i",$id); $stmt->execute(); $res=$stmt->get_result();
  if(!$res || !$res->num_rows){ if(ob_get_length()) ob_end_clean(); die("User not found"); }
  $row=$res->fetch_assoc();

  $w=80; $h=120; $pdf=new FPDF('P','mm',[$w,$h]); $pdf->SetMargins(6,5,6); $pdf->AddPage();

  $pdf->SetFont('Arial','B',14);
  $pdf->Cell(0,7,$brand['org_name'] ?? 'Lazz Pharma',0,1,'C');
  $pdf->SetFont('Arial','',9);
  $pdf->Cell(0,5,$brand['address'] ?? '',0,1,'C');
  $pdf->Cell(0,5,'Tel: '.(($brand['phone'] ?? '') ?: ($brand['mobile'] ?? '')),0,1,'C');

  $pdf->Cell(0,5,str_repeat('* ',18),0,1,'C');
  $pdf->SetFont('Arial','B',11); $pdf->Cell(0,6,'USER Details',0,1,'C');
  $pdf->Cell(0,5,str_repeat('* ',18),0,1,'C');

  $pdf->SetFont('Arial','B',10); $pdf->Cell(0,6,'Details',0,1,'L');
  $pdf->SetFont('Arial','',9);
  $pdf->Cell(0,5,'ID: '.$row['id'],0,1,'L');
  $pdf->Cell(0,5,'Name: '.$row['name'],0,1,'L');
  $pdf->Cell(0,5,'Email: '.$row['email'],0,1,'L');
  $pdf->Cell(0,5,'Contact: '.$row['contact'],0,1,'L');
  $pdf->MultiCell(0,5,'Address: '.$row['address']);

  $pdf->Cell(0,5,str_repeat('* ',18),0,1,'C');
  $pdf->SetFont('Arial','B',11); $pdf->Cell(0,6,'THANK YOU!',0,1,'C');

  draw_barcode($pdf,10,$pdf->GetY()+2,$w-20,10,'USER-'.$row['id']);
  safe_output_pdf($pdf, 'user_'.$row['id'].'_'.date('Ymd_His').'.pdf');
}

/* All users list (classic) */
require_once __DIR__ . "/lib/pdf_theme.php";
$HEADERS=['ID','Name','Email','Contact','Address']; $WIDTHS=[15,35,50,30,55];

$pdf = pdf_make('User List', $HEADERS, $WIDTHS, $brand, true);
$pdf->AddPage(); $pdf->SetFont('Arial','',9);

$res = $db->query("SELECT id,name,email,contact,address FROM users ORDER BY id DESC");
if($res && $res->num_rows){
  while($r=$res->fetch_assoc()){
    $cells = [$r['id'], utf8_decode($r['name']), $r['email'], $r['contact'], utf8_decode($r['address'])];
    foreach($cells as $i=>$t){ $pdf->Cell($WIDTHS[$i], 7, $t, 1, 0, ($i==0?'C':'L')); }
    $pdf->Ln(); if($pdf->GetY()>260) $pdf->AddPage();
  }
}else{ $pdf->Cell(array_sum($WIDTHS),10,'No users found',1,1,'C'); }

safe_output_pdf($pdf, 'user_list_'.date('Ymd_His').'.pdf');
