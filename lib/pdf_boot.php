<?php
// lib/pdf_boot.php
// Requires: lib/tfpdf.php  +  lib/fonts/SolaimanLipi.ttf

if (!class_exists('tFPDF')) {
  require_once __DIR__ . '/tfpdf.php';
}

class BrandPDF extends tFPDF {
  public array $brand = [];
  public string $titleText = '';

  public function __construct($orientation='P', $unit='mm', $size='A4', array $brand = []) {
    parent::__construct($orientation, $unit, $size);
    // আপনার বাংলা TTF ফন্ট ফাইল (lib/fonts/ এর ভেতর)
    $this->AddFont('SolaimanLipi', '', 'SolaimanLipi.ttf', true);
    $this->brand = $brand + [
      'name'   => 'Lazz Pharma',
      'addr'   => 'Lazz Center, 63/C, Lake Circus, Kalabagan, Dhaka.',
      'phone'  => '+8801886886041',
      'email'  => 'lazzcorporate@gmail.com',
      'logo'   => __DIR__ . '/../assets/lp-logo.png', // থাকলে দেখাবে
      'footer' => 'Pharmacy Software & Website: Md Mokhlesur Rahman Momin | +8801778772327'
    ];
  }

  function Header() {
    $this->SetFont('SolaimanLipi','', 11);

    if (!empty($this->brand['logo']) && file_exists($this->brand['logo'])) {
      $this->Image($this->brand['logo'], 10, 8, 18);
    }

    $this->SetXY(10, 8);
    $this->SetFont('SolaimanLipi','', 14);
    $this->Cell(0, 7, $this->brand['name'], 0, 1, 'C');

    $this->SetFont('SolaimanLipi','', 9);
    if (!empty($this->brand['addr']))  $this->Cell(0, 5, $this->brand['addr'], 0, 1, 'C');

    $infoLine = [];
    if (!empty($this->brand['phone'])) $infoLine[] = 'Mobile: '.$this->brand['phone'];
    if (!empty($this->brand['email'])) $infoLine[] = 'Email: '.$this->brand['email'];
    if ($infoLine) $this->Cell(0, 5, implode(' | ', $infoLine), 0, 1, 'C');

    if ($this->titleText) {
      $this->SetFont('SolaimanLipi','', 12);
      $this->Ln(1);
      $this->Cell(0, 6, $this->titleText, 0, 1, 'C');
    }

    $this->SetFont('SolaimanLipi','', 8);
    $this->Cell(0, 5, 'Generated: '.date('Y-m-d H:i'), 0, 1, 'C');

    $this->Ln(1);
    $this->SetDrawColor(0,150,136);
    $this->SetLineWidth(0.4);
    $y = $this->GetY();
    $this->Line(10, $y, 200, $y);
    $this->Ln(3);
  }

  function Footer() {
    $this->SetY(-18);
    $this->SetDrawColor(220,220,220);
    $this->Line(10, $this->GetY(), 200, $this->GetY());
    $this->Ln(2);
    $this->SetFont('SolaimanLipi','', 9);
    $this->Cell(0, 6, 'পৃষ্ঠা '.$this->PageNo().'/{nb}', 0, 1, 'C');

    if (!empty($this->brand['footer'])) {
      $this->SetFont('SolaimanLipi','', 8);
      $this->Cell(0, 4, $this->brand['footer'], 0, 0, 'C');
    }
  }
}

/** Start a branded PDF */
function pdf_start(string $title, array $brand = []): BrandPDF {
  $pdf = new BrandPDF('P','mm','A4', $brand);
  $pdf->AliasNbPages();
  $pdf->AddPage();
  $pdf->SetFont('SolaimanLipi','', 10);
  $pdf->titleText = $title;
  return $pdf;
}
