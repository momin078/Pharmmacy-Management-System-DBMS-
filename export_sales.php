<?php
// export_sales.php
ob_start();
if (session_status()===PHP_SESSION_NONE) session_start();
require_once __DIR__ . "/config.php";
require_once __DIR__ . "/lib/pdf_theme.php"; // ThemedPDF + pdf_make

date_default_timezone_set('Asia/Dhaka');

// CSRF
if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(16));
$csrf = $_GET['csrf'] ?? '';
if (!hash_equals($_SESSION['csrf'], $csrf)) { if(ob_get_length()) ob_end_clean(); die("Invalid token"); }

// brand for header
define('FOOTER_EXPORT_INFO', true);
$brand = include __DIR__ . '/footer.php';
$brand['PRINTED_AT'] = date('Y-m-d H:i');

// filter
$fromDate = $_GET['from'] ?? '';
$toDate   = $_GET['to']   ?? '';
$useFilter = ($fromDate && $toDate);
$where = '';
if ($useFilter) {
  $fromDT = $db->real_escape_string($fromDate.' 00:00:00');
  $toDT   = $db->real_escape_string($toDate.' 23:59:59');
  $where = "WHERE sale_date BETWEEN '{$fromDT}' AND '{$toDT}'";
}

function outpdf($pdf,$name){ if(ob_get_length()) ob_end_clean(); header('Content-Type: application/pdf'); $pdf->Output('D',$name); exit; }
function money($n){ return '৳ '.number_format((float)$n,2); }
function q($db,$sql){ return $db->query($sql); }

$section = $_GET['section'] ?? ''; // summary | byday | toprev | topqty | recent

switch ($section) {

  // Summary sheet
  case 'summary': {
    $HEAD = ['Metric','Value']; $W=[100,90];
    $title = 'Sales Summary'.($useFilter? " ({$fromDate} to {$toDate})":'');
    $pdf = pdf_make($title, $HEAD, $W, $brand, true);
    $pdf->AddPage(); $pdf->SetFont('Arial','',10);

    $row = q($db,"SELECT COUNT(*) AS orders, COALESCE(SUM(quantity),0) AS qty, COALESCE(SUM(total),0) AS rev FROM sales {$where}")->fetch_assoc();
    $orders=(int)$row['orders']; $qty=(int)$row['qty']; $rev=(float)$row['rev']; $aov = $orders? $rev/$orders:0;

    $data = [
      ['Total Orders', number_format($orders)],
      ['Total Quantity', number_format($qty)],
      ['Total Revenue', money($rev)],
      ['Avg Order Value', money($aov)],
      ['Period', $useFilter? ($fromDate.' to '.$toDate) : 'All time'],
      ['Generated', date('Y-m-d H:i')],
    ];
    foreach($data as $d){
      $pdf->Cell($W[0],9,$d[0],1,0,'L');
      $pdf->Cell($W[1],9,$d[1],1,0,'R');
      $pdf->Ln();
    }
    outpdf($pdf,'sales_summary_'.date('Ymd_His').'.pdf');
  }

  // Sales by day
  case 'byday': {
    $HEAD=['Date','Orders','Qty','Revenue']; $W=[45,35,35,75];
    $title = 'Sales by Day'.($useFilter? " ({$fromDate} to {$toDate})":'');
    $pdf = pdf_make($title,$HEAD,$W,$brand,true);
    $pdf->AddPage(); $pdf->SetFont('Arial','',9);

    $res = q($db,"SELECT DATE(sale_date) AS d, COUNT(*) AS orders, SUM(quantity) AS qty, SUM(total) AS rev
                  FROM sales {$where}
                  GROUP BY DATE(sale_date)
                  ORDER BY d DESC");
    if($res && $res->num_rows){
      while($r=$res->fetch_assoc()){
        $pdf->Cell($W[0],8,$r['d'],1,0,'C');
        $pdf->Cell($W[1],8,(int)$r['orders'],1,0,'R');
        $pdf->Cell($W[2],8,(int)$r['qty'],1,0,'R');
        $pdf->Cell($W[3],8,money($r['rev']),1,0,'R');
        $pdf->Ln(); if($pdf->GetY()>260) $pdf->AddPage();
      }
    } else {
      $pdf->Cell(array_sum($W),10,'No data',1,1,'C');
    }
    outpdf($pdf,'sales_by_day_'.date('Ymd_His').'.pdf');
  }

  // Top by revenue
  case 'toprev': {
    $HEAD=['Medicine','Qty','Avg Price','Revenue']; $W=[85,25,30,50];
    $title = 'Top 10 by Revenue'.($useFilter? " ({$fromDate} to {$toDate})":'');
    $pdf = pdf_make($title,$HEAD,$W,$brand,true);
    $pdf->AddPage(); $pdf->SetFont('Arial','',9);

    $res = q($db,"SELECT medicine_name, SUM(quantity) AS qty, SUM(total) AS rev, AVG(price) AS avg_price
                  FROM sales {$where}
                  GROUP BY medicine_name
                  ORDER BY rev DESC
                  LIMIT 10");
    if($res && $res->num_rows){
      while($r=$res->fetch_assoc()){
        $pdf->Cell($W[0],8,utf8_decode($r['medicine_name']),1,0,'L');
        $pdf->Cell($W[1],8,(int)$r['qty'],1,0,'R');
        $pdf->Cell($W[2],8,money($r['avg_price']),1,0,'R');
        $pdf->Cell($W[3],8,money($r['rev']),1,0,'R');
        $pdf->Ln(); if($pdf->GetY()>260) $pdf->AddPage();
      }
    } else {
      $pdf->Cell(array_sum($W),10,'No data',1,1,'C');
    }
    outpdf($pdf,'sales_top_revenue_'.date('Ymd_His').'.pdf');
  }

  // Top by quantity
  case 'topqty': {
    $HEAD=['Medicine','Qty','Avg Price','Revenue']; $W=[85,25,30,50];
    $title = 'Top 10 by Quantity'.($useFilter? " ({$fromDate} to {$toDate})":'');
    $pdf = pdf_make($title,$HEAD,$W,$brand,true);
    $pdf->AddPage(); $pdf->SetFont('Arial','',9);

    $res = q($db,"SELECT medicine_name, SUM(quantity) AS qty, SUM(total) AS rev, AVG(price) AS avg_price
                  FROM sales {$where}
                  GROUP BY medicine_name
                  ORDER BY qty DESC
                  LIMIT 10");
    if($res && $res->num_rows){
      while($r=$res->fetch_assoc()){
        $pdf->Cell($W[0],8,utf8_decode($r['medicine_name']),1,0,'L');
        $pdf->Cell($W[1],8,(int)$r['qty'],1,0,'R');
        $pdf->Cell($W[2],8,money($r['avg_price']),1,0,'R');
        $pdf->Cell($W[3],8,money($r['rev']),1,0,'R');
        $pdf->Ln(); if($pdf->GetY()>260) $pdf->AddPage();
      }
    } else {
      $pdf->Cell(array_sum($W),10,'No data',1,1,'C');
    }
    outpdf($pdf,'sales_top_quantity_'.date('Ymd_His').'.pdf');
  }

  // Recent sales
  case 'recent': {
    $HEAD=['ID','Medicine','Qty','Price','Total','Date']; $W=[18,70,20,28,28,36];
    $title = 'Recent Sales'.($useFilter? " ({$fromDate} to {$toDate})":'');
    $pdf = pdf_make($title,$HEAD,$W,$brand,true);
    $pdf->AddPage(); $pdf->SetFont('Arial','',9);

    $sql = "SELECT sale_id, medicine_name, quantity, price, total, sale_date
            FROM sales {$where}
            ORDER BY sale_date DESC";
    if(!$useFilter) $sql .= " LIMIT 100";
    $res = q($db,$sql);
    if($res && $res->num_rows){
      while($r=$res->fetch_assoc()){
        $pdf->Cell($W[0],8,$r['sale_id'],1,0,'C');
        $pdf->Cell($W[1],8,utf8_decode($r['medicine_name']),1,0,'L');
        $pdf->Cell($W[2],8,(int)$r['quantity'],1,0,'R');
        $pdf->Cell($W[3],8,money($r['price']),1,0,'R');
        $pdf->Cell($W[4],8,money($r['total']),1,0,'R');
        $pdf->Cell($W[5],8,$r['sale_date'],1,0,'C');
        $pdf->Ln(); if($pdf->GetY()>260) $pdf->AddPage();
      }
    } else {
      $pdf->Cell(array_sum($W),10,'No sales found',1,1,'C');
    }
    outpdf($pdf,'sales_recent_'.date('Ymd_His').'.pdf');
  }

  default:
    if(ob_get_length()) ob_end_clean();
    http_response_code(400);
    echo "Unknown section";
    exit;
}
