<?php
// medicine_list.php
session_start();
require_once "config.php";

// ===== CSRF =====
if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(32));
$CSRF = $_SESSION['csrf'];

// ===== DELETE via POST =====
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_id'])) {
  if (!hash_equals($CSRF, $_POST['csrf'] ?? '')) {
    $_SESSION['err'] = "Invalid token.";
    header("Location: medicine_list.php"); exit;
  }
  $id = (int)$_POST['delete_id'];
  $stmt = $db->prepare("DELETE FROM medicines WHERE id=?");
  $stmt->bind_param("i", $id);
  if ($stmt->execute()) {
    $_SESSION['msg'] = "Medicine deleted.";
  } else {
    $_SESSION['err'] = "Delete failed.";
  }
  $stmt->close();
  header("Location: medicine_list.php"); exit;
}

/* ====== PDF EXPORT (Single / All) — must run BEFORE any output ====== */
if (isset($_GET['export']) && $_GET['export'] === 'pdf') {
  if (!hash_equals($CSRF, $_GET['csrf'] ?? '')) {
    die("Invalid token.");
  }

  require_once __DIR__ . '/lib/fpdf.php';

  $singleId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

  if (ob_get_length()) { ob_end_clean(); }

  // ---------- Styled FPDF ----------
  class PDFX extends FPDF {
    public $brand = [0,150,136]; // teal
    public $titleText = '';
    public $subtitle  = '';      // e.g. "Generated: 2025-09-09 10:22"
    public $tableHeader = [];    // for auto repeating table header
    public $tableWidths = [];

    function Header(){
      // Brand bar
      $this->SetFillColor($this->brand[0],$this->brand[1],$this->brand[2]);
      $this->Rect(0, 0, $this->w, 4, 'F');

      // Title
      $this->Ln(8);
      $this->SetFont('Arial','B',14);
      $this->SetTextColor(17,24,39);
      $this->Cell(0,8, $this->titleText ?: 'Report', 0,1,'C');

      // Subtitle (muted)
      if ($this->subtitle) {
        $this->SetFont('Arial','',9);
        $this->SetTextColor(107,114,128);
        $this->Cell(0,6, $this->subtitle, 0,1,'C');
      }

      // Divider
      $this->SetDrawColor(229,231,235);
      $this->Ln(2);
      $this->Line(10, $this->GetY(), 200, $this->GetY());
      $this->Ln(4);

      // If a table is in progress, draw header again (repeating header)
      if (!empty($this->tableHeader) && !empty($this->tableWidths)) {
        $this->DrawTableHeader();
      }
    }

    function Footer(){
      $this->SetY(-15);
      $this->SetFont('Arial','I',8);
      $this->SetTextColor(120,120,120);
      $this->Cell(0,8,'Page '.$this->PageNo().'/{nb}',0,0,'C');
    }

    // Styled table header (teal background, white text)
    function DrawTableHeader(){
      $this->SetFont('Arial','B',9);
      $this->SetFillColor($this->brand[0],$this->brand[1],$this->brand[2]);
      $this->SetTextColor(255,255,255);
      foreach ($this->tableHeader as $i=>$h) {
        $w = $this->tableWidths[$i] ?? 20;
        $this->Cell($w,8,$h,1,0,'C',true);
      }
      $this->Ln();
      $this->SetTextColor(0,0,0);
    }

    // Zebra row helper
    function ZebraRow($cells, $aligns, $fillOn){
      $this->SetFont('Arial','',9);
      if ($fillOn) $this->SetFillColor(251,251,252); else $this->SetFillColor(255,255,255);
      foreach ($cells as $i=>$txt) {
        $w = $this->tableWidths[$i] ?? 20;
        $a = $aligns[$i] ?? 'L';
        $this->Cell($w,8,$txt,1,0,$a,true);
      }
      $this->Ln();
    }
  }
  // ---------- /Styled FPDF ----------

  if ($singleId > 0) {
    // -------- Single medicine details --------
    $stmt = $db->prepare("SELECT id,name,category,company,dosage_form,strength,price,quantity FROM medicines WHERE id=? LIMIT 1");
    $stmt->bind_param("i", $singleId);
    $stmt->execute();
    $res = $stmt->get_result();
    if (!$res || !$res->num_rows) { die("Medicine not found."); }
    $row = $res->fetch_assoc();
    $stmt->close();

    $price = (float)$row['price'];
    $qty   = (int)$row['quantity'];
    $val   = $price * $qty;

    $pdf = new PDFX('P','mm','A4');
    $pdf->AliasNbPages();
    $pdf->titleText = 'Medicine Details';
    $pdf->subtitle  = 'Generated: '.date('Y-m-d H:i');
    $pdf->AddPage();

    // Header card
    $pdf->SetFont('Arial','B',11);
    $pdf->SetFillColor(230,255,250); // soft teal
    $pdf->SetDrawColor(229,231,235);
    $pdf->SetTextColor(15,118,110);
    $pdf->Cell(0,8,'Overview',1,1,'L',true);
    $pdf->SetTextColor(0,0,0);

    // Two-column layout
    $L = 45; $R = 145; $H = 8;
    $pdf->SetFont('Arial','B',10);
    $pdf->Cell($L,$H,'ID:',1,0,'L');          $pdf->SetFont('Arial','',10); $pdf->Cell($R,$H,$row['id'],1,1,'L');
    $pdf->SetFont('Arial','B',10);
    $pdf->Cell($L,$H,'Name:',1,0,'L');        $pdf->SetFont('Arial','',10); $pdf->Cell($R,$H,utf8_decode($row['name']),1,1,'L');
    $pdf->SetFont('Arial','B',10);
    $pdf->Cell($L,$H,'Category:',1,0,'L');    $pdf->SetFont('Arial','',10); $pdf->Cell($R,$H,utf8_decode($row['category']),1,1,'L');
    $pdf->SetFont('Arial','B',10);
    $pdf->Cell($L,$H,'Company:',1,0,'L');     $pdf->SetFont('Arial','',10); $pdf->Cell($R,$H,utf8_decode($row['company']),1,1,'L');
    $pdf->SetFont('Arial','B',10);
    $pdf->Cell($L,$H,'Form:',1,0,'L');        $pdf->SetFont('Arial','',10); $pdf->Cell($R,$H,utf8_decode($row['dosage_form']),1,1,'L');
    $pdf->SetFont('Arial','B',10);
    $pdf->Cell($L,$H,'Strength:',1,0,'L');    $pdf->SetFont('Arial','',10); $pdf->Cell($R,$H,utf8_decode($row['strength']),1,1,'L');

    $pdf->Ln(2);
    $pdf->SetFont('Arial','B',11);
    $pdf->SetFillColor(230,255,250);
    $pdf->SetDrawColor(229,231,235);
    $pdf->SetTextColor(15,118,110);
    $pdf->Cell(0,8,'Pricing & Stock',1,1,'L',true);
    $pdf->SetTextColor(0,0,0);

    $pdf->SetFont('Arial','B',10);
    $pdf->Cell($L,$H,'Price:',1,0,'L');       $pdf->SetFont('Arial','',10); $pdf->Cell($R,$H,'Tk '.number_format($price,2),1,1,'L');
    $pdf->SetFont('Arial','B',10);
    $pdf->Cell($L,$H,'Quantity:',1,0,'L');    $pdf->SetFont('Arial','',10); $pdf->Cell($R,$H,$qty,1,1,'L');
    $pdf->SetFont('Arial','B',10);
    $pdf->Cell($L,$H,'Value:',1,0,'L');       $pdf->SetFont('Arial','',10); $pdf->Cell($R,$H,'Tk '.number_format($val,2),1,1,'L');

    $pdf->Output('D','medicine_'.$row['id'].'_'.date('Ymd_His').'.pdf');
    exit;

  } else {
    // -------- Full list (styled) --------
    $result = $db->query("SELECT id,name,category,company,dosage_form,strength,price,quantity FROM medicines ORDER BY id DESC");

    $pdf = new PDFX('P','mm','A4');
    $pdf->AliasNbPages();
    $pdf->titleText = 'Medicine List';
    $pdf->subtitle  = 'Generated: '.date('Y-m-d H:i');
    $pdf->AddPage();

    // Table header config
    $pdf->tableHeader = ['ID','Name','Category','Company','Form','Strength','Price','Qty','Value'];
    $pdf->tableWidths = [12,38,26,28,20,22,20,12,22];

    // Draw header once (Header() will also draw when new page starts)
    $pdf->DrawTableHeader();

    $fill = false;
    $total_items = 0;
    $total_value = 0.0;

    if ($result && $result->num_rows) {
      while ($row = $result->fetch_assoc()) {
        // Prepare row cells
        $id   = (int)$row['id'];
        $name = utf8_decode($row['name']);
        $cat  = utf8_decode($row['category']);
        $comp = utf8_decode($row['company']);
        $form = utf8_decode($row['dosage_form']);
        $str  = utf8_decode($row['strength']);
        $price= (float)$row['price'];
        $qty  = (int)$row['quantity'];
        $val  = $price * $qty;

        $cells = [
          $id, $name, $cat, $comp, $form, $str,
          'Tk '.number_format($price,2),
          $qty,
          'Tk '.number_format($val,2)
        ];
        $aligns = ['C','L','L','L','L','L','R','R','R'];

        // Page break safety
        if ($pdf->GetY() > 265) { $pdf->AddPage(); }

        $pdf->ZebraRow($cells, $aligns, $fill);
        $fill = !$fill;

        $total_items += $qty;
        $total_value += $val;
      }

      // Totals row (highlight)
      if ($pdf->GetY() > 265) { $pdf->AddPage(); }
      $pdf->SetFont('Arial','B',9);
      $pdf->SetFillColor(245,255,252);
      $pdf->SetDrawColor(229,231,235);

      $spanLeft = array_sum($pdf->tableWidths) - ($pdf->tableWidths[7] + $pdf->tableWidths[8]);
      $pdf->Cell($spanLeft,8,'Totals',1,0,'R',true);
      $pdf->Cell($pdf->tableWidths[7],8,number_format($total_items),1,0,'R',true);
      $pdf->Cell($pdf->tableWidths[8],8,'Tk '.number_format($total_value,2),1,1,'R',true);

    } else {
      $pdf->SetFont('Arial','I',10);
      $pdf->Cell(array_sum($pdf->tableWidths),10,'No medicines found',1,1,'C');
    }

    $pdf->Output('D','medicine_list_'.date('Ymd_His').'.pdf');
    exit;
  }
}
/* ====== END PDF EXPORT ====== */

// ===== List for UI =====
$result = $db->query("SELECT id,name,category,company,dosage_form,strength,price,quantity FROM medicines ORDER BY id DESC");

// helper
function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
?>

<?php include "header.php"; ?>
<?php include "navbar.php"; ?>

<!doctype html>
<html lang="bn">
<head>
<meta charset="utf-8"><title>Medicine List</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<style>@media print{.no-print{display:none!important;}}</style>
</head>
<body class="bg-light">
<div class="container my-4">
  <h2>💊 Medicine List</h2>

  <div class="no-print mb-3 d-flex gap-2 flex-wrap">
    <a class="btn btn-success" href="medicine_insert.php">+ Add Medicine</a>
    <button class="btn btn-info text-white" onclick="window.print()">🖨 Print</button>
    <a class="btn btn-secondary" href="stock_report.php">📊 Stock Report</a>
    <a class="btn btn-secondary" href="sales_report.php">📊 Sales Report</a>
    <a class="btn btn-danger" href="medicine_list.php?export=pdf&csrf=<?= h($CSRF) ?>">📄 Export All PDF</a>
  </div>

  <?php if (!empty($_SESSION['msg'])): ?>
    <div class="alert alert-success"><?= h($_SESSION['msg']); unset($_SESSION['msg']); ?></div>
  <?php endif; ?>
  <?php if (!empty($_SESSION['err'])): ?>
    <div class="alert alert-danger"><?= h($_SESSION['err']); unset($_SESSION['err']); ?></div>
  <?php endif; ?>

  <table class="table table-bordered bg-white">
    <thead class="table-light">
      <tr>
        <th>ID</th><th>Name</th><th>Category</th><th>Company</th>
        <th>Form</th><th>Strength</th><th>Price</th><th>Qty</th>
        <th class="no-print">Action</th>
      </tr>
    </thead>
    <tbody>
      <?php if ($result && $result->num_rows): while ($row = $result->fetch_assoc()): ?>
      <tr>
        <td><?= (int)$row['id'] ?></td>
        <td><?= h($row['name']) ?></td>
        <td><?= h($row['category']) ?></td>
        <td><?= h($row['company']) ?></td>
        <td><?= h($row['dosage_form']) ?></td>
        <td><?= h($row['strength']) ?></td>
        <td>Tk <?= number_format((float)$row['price'], 2) ?></td>
        <td><?= (int)$row['quantity'] ?></td>
        <td class="no-print">
          <a class="btn btn-sm btn-primary" href="edit_medicine.php?id=<?= (int)$row['id'] ?>">Update</a>

          <form method="post" style="display:inline" onsubmit="return confirm('Delete this medicine?');">
            <input type="hidden" name="csrf" value="<?= h($CSRF) ?>">
            <input type="hidden" name="delete_id" value="<?= (int)$row['id'] ?>">
            <button type="submit" class="btn btn-sm btn-danger">Delete</button>
          </form>

          <a class="btn btn-sm btn-outline-danger"
             href="medicine_list.php?export=pdf&id=<?= (int)$row['id'] ?>&csrf=<?= h($CSRF) ?>">
             📄 PDF
          </a>
        </td>
      </tr>
      <?php endwhile; else: ?>
      <tr><td colspan="9" class="text-center text-muted">No medicines found</td></tr>
      <?php endif; ?>
    </tbody>
  </table>
</div>
</body>
</html>

<?php include "footer.php"; ?>
