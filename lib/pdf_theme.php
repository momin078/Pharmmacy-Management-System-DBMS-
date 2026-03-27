<?php
require_once __DIR__ . '/fpdf.php';

class PDF_Theme extends FPDF {
  public $org_name, $org_addr, $org_phone, $org_logo, $doc_title, $printed_at;
  public $headers = [], $widths = [];
  public $render_table_header = true;

  function Header() {
    $this->SetFillColor(245,245,245);
    $this->Rect(10, 8, 190, 24, 'F');

    if (!empty($this->org_logo) && file_exists($this->org_logo)) {
      $this->Image($this->org_logo, 12, 10, 18);
    }
    $this->SetXY(32, 10);
    $this->SetFont('Arial','B',12);
    $this->Cell(0,6, $this->org_name, 0, 1, 'L');
    $this->SetFont('Arial','',9);
    $this->SetTextColor(60,60,60);
    $this->SetX(32); $this->Cell(0,5, $this->org_addr, 0, 1, 'L');
    $this->SetX(32); $this->Cell(0,5, $this->org_phone, 0, 1, 'L');

    $this->SetTextColor(0,0,0);
    $this->SetXY(-100, 10);
    $this->SetFont('Arial','B',13);
    $this->Cell(90,6, $this->doc_title, 0, 2, 'R');
    $this->SetFont('Arial','',9);
    $this->Cell(90,5, "Generated: ".$this->printed_at, 0, 2, 'R');

    $this->Ln(4);
    $this->SetDrawColor(200,200,200);
    $this->Line(10, 34, 200, 34);
    $this->Ln(2);

    if ($this->render_table_header && !empty($this->headers)) {
      $this->SetFillColor(230,230,230);
      $this->SetFont('Arial','B',9);
      foreach ($this->headers as $i=>$h) {
        $w = $this->widths[$i] ?? 20;
        $this->Cell($w, 8, $h, 1, 0, 'C', true);
      }
      $this->Ln();
    }
  }

  function Footer() {
    $this->SetDrawColor(200,200,200);
    $this->Line(10, 285, 200, 285);
    $this->SetY(-15);
    $this->SetFont('Arial','',8);
    $this->SetTextColor(100,100,100);
    $this->Cell(0,5, "Printed: ".$this->printed_at, 0, 1, 'L');
    $this->Cell(0,5, "Page ".$this->PageNo()."/{nb}", 0, 0, 'R');
  }
}

function pdf_make($title, $headers, $widths, $brand, $render_table_header = true) {
  $pdf = new PDF_Theme();
  $pdf->AliasNbPages();
  $pdf->SetAutoPageBreak(true, 20);
  $pdf->org_name   = $brand['ORG_NAME']   ?? '';
  $pdf->org_addr   = $brand['ORG_ADDR']   ?? '';
  $pdf->org_phone  = $brand['ORG_PHONE']  ?? '';
  $pdf->org_logo   = $brand['ORG_LOGO']   ?? '';
  $pdf->doc_title  = $title;
  $pdf->printed_at = $brand['PRINTED_AT'] ?? date('Y-m-d H:i');
  $pdf->headers    = $headers;
  $pdf->widths     = $widths;
  $pdf->render_table_header = $render_table_header;
  return $pdf;
}
