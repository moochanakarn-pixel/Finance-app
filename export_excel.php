<?php
/**
 * export_excel.php
 * Export annual finance report to Excel (.xlsx)
 * Requires: PhpSpreadsheet  (install via Composer: composer require phpoffice/phpspreadsheet)
 *
 * Fallback: if PhpSpreadsheet is not available, outputs CSV automatically.
 */
error_reporting(0);
ini_set('display_errors', 0);
include 'auth.php';
include 'config/db.php';
include 'config/functions.php';

$userId = (int)$_SESSION['user_id'];

$requestedYear = isset($_GET['year']) ? (int)$_GET['year'] : 0;
if ($requestedYear >= 2400) {
    $selectedBE = $requestedYear;
    $selectedAD = $requestedYear - 543;
} elseif ($requestedYear > 1900) {
    $selectedAD = $requestedYear;
    $selectedBE = $requestedYear + 543;
} else {
    $selectedAD = (int)date('Y');
    $selectedBE = $selectedAD + 543;
}

$thaiMonths = [1=>'ม.ค.',2=>'ก.พ.',3=>'มี.ค.',4=>'เม.ย.',5=>'พ.ค.',6=>'มิ.ย.',
               7=>'ก.ค.',8=>'ส.ค.',9=>'ก.ย.',10=>'ต.ค.',11=>'พ.ย.',12=>'ธ.ค.'];

// ── Fetch data ──────────────────────────────────────────────────────────────

// Categories + monthly amounts
$categories = ['income'=>[], 'saving'=>[], 'expense'=>[]];
$rs = mysqli_query($conn,"
    SELECT id, name, type FROM categories
    WHERE is_active=1 AND user_id={$userId}
    ORDER BY FIELD(type,'income','saving','expense'), sort_order ASC, id ASC
");
while($r=mysqli_fetch_assoc($rs)) $categories[$r['type']][] = $r;

$amountMap = [];
$rs = mysqli_query($conn,"
    SELECT e.category_id, MONTH(e.entry_date) AS m, SUM(e.amount) AS t
    FROM entries e JOIN categories c ON e.category_id=c.id
    WHERE YEAR(e.entry_date)={$selectedAD} AND e.user_id={$userId} AND c.user_id={$userId}
    GROUP BY e.category_id, MONTH(e.entry_date)
");
while($r=mysqli_fetch_assoc($rs)) $amountMap[(int)$r['category_id']][(int)$r['m']]=(float)$r['t'];

// Monthly summary
$monthly = [];
for($m=1;$m<=12;$m++) $monthly[$m]=['income'=>0,'expense'=>0,'saving'=>0];
$rs = mysqli_query($conn,"
    SELECT MONTH(e.entry_date) AS m, c.type, SUM(e.amount) AS t
    FROM entries e JOIN categories c ON e.category_id=c.id
    WHERE YEAR(e.entry_date)={$selectedAD} AND e.user_id={$userId} AND c.user_id={$userId}
    GROUP BY MONTH(e.entry_date), c.type
");
while($r=mysqli_fetch_assoc($rs)) if(isset($monthly[(int)$r['m']][$r['type']])) $monthly[(int)$r['m']][$r['type']]=(float)$r['t'];

// Detailed entries
$entries = [];
$rs = mysqli_query($conn,"
    SELECT e.entry_date, c.type, c.name AS category, e.amount, e.note
    FROM entries e JOIN categories c ON e.category_id=c.id
    WHERE YEAR(e.entry_date)={$selectedAD} AND e.user_id={$userId} AND c.user_id={$userId}
    ORDER BY e.entry_date ASC, e.id ASC
");
while($r=mysqli_fetch_assoc($rs)) $entries[]=$r;

// ── Try PhpSpreadsheet; fall back to CSV ────────────────────────────────────
$spreadsheetAvailable = false;
$autoloadPaths = [
    __DIR__.'/vendor/autoload.php',
    dirname(__DIR__).'/vendor/autoload.php',
];
foreach($autoloadPaths as $p) {
    if (file_exists($p)) { require $p; $spreadsheetAvailable=true; break; }
}

if (!$spreadsheetAvailable || !class_exists('\PhpOffice\PhpSpreadsheet\Spreadsheet')) {
    // ── CSV fallback ────────────────────────────────────────────────────────
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="finance_'.$selectedBE.'.csv"');
    echo "\xEF\xBB\xBF"; // UTF-8 BOM for Excel
    $out = fopen('php://output','w');
    fputcsv($out,['วันที่','ประเภท','หมวดหมู่','จำนวนเงิน','หมายเหตุ'], ',', '"', '\\');
    $typeLabels=['income'=>'รายรับ','expense'=>'รายจ่าย','saving'=>'เงินออม'];
    foreach($entries as $e){
        $dateParts = explode('-',$e['entry_date']);
        $thaiDate  = sprintf('%02d/%02d/%04d',(int)$dateParts[2],(int)$dateParts[1],(int)$dateParts[0]+543);
        $safeNote = preg_replace('/^([=+\-@\t])/', "'\$1", (string)$e['note']);
        $safeCat  = preg_replace('/^([=+\-@\t])/', "'\$1", (string)$e['category']);
        fputcsv($out,[$thaiDate, $typeLabels[$e['type']]??$e['type'], $safeCat, number_format((float)$e['amount'],2,'.',''), $safeNote], ',', '"', '\\');
    }
    fclose($out);
    exit;
}

// ── PhpSpreadsheet ──────────────────────────────────────────────────────────
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\{Alignment, Border, Fill, Color, NumberFormat};

$wb = new Spreadsheet();
$wb->getProperties()->setTitle("รายงานการเงิน พ.ศ. {$selectedBE}");

// ─── Helper styles ──────────────────────────────────────────────────────────
function applyStyle($ws, $range, array $s){
    $ws->getStyle($range)->applyFromArray($s);
}
function headerStyle(){ return [
    'font'=>['bold'=>true,'color'=>['rgb'=>'FFFFFF'],'size'=>11],
    'fill'=>['fillType'=>Fill::FILL_SOLID,'startColor'=>['rgb'=>'1E3A5F']],
    'alignment'=>['horizontal'=>Alignment::HORIZONTAL_CENTER,'vertical'=>Alignment::VERTICAL_CENTER],
]; }
function subHeaderStyle(){ return [
    'font'=>['bold'=>true,'color'=>['rgb'=>'FFFFFF'],'size'=>10],
    'fill'=>['fillType'=>Fill::FILL_SOLID,'startColor'=>['rgb'=>'15803D']],
    'alignment'=>['horizontal'=>Alignment::HORIZONTAL_CENTER],
]; }
function expSubHeader(){ return [
    'font'=>['bold'=>true,'color'=>['rgb'=>'FFFFFF'],'size'=>10],
    'fill'=>['fillType'=>Fill::FILL_SOLID,'startColor'=>['rgb'=>'DC2626']],
    'alignment'=>['horizontal'=>Alignment::HORIZONTAL_CENTER],
]; }
function savSubHeader(){ return [
    'font'=>['bold'=>true,'color'=>['rgb'=>'FFFFFF'],'size'=>10],
    'fill'=>['fillType'=>Fill::FILL_SOLID,'startColor'=>['rgb'=>'7C3AED']],
    'alignment'=>['horizontal'=>Alignment::HORIZONTAL_CENTER],
]; }
function totalRowStyle(){ return [
    'font'=>['bold'=>true,'size'=>10],
    'fill'=>['fillType'=>Fill::FILL_SOLID,'startColor'=>['rgb'=>'F1F5F9']],
]; }
function thinBorder(){ return [
    'borders'=>['allBorders'=>['borderStyle'=>Border::BORDER_THIN,'color'=>['rgb'=>'E2E8F0']]],
]; }
function numFmt(){ return '#,##0.00'; }
$typeColors=['income'=>'DCFCE7','saving'=>'EDE9FE','expense'=>'FEE2E2'];
$typeLabels=['income'=>'รายรับ','saving'=>'เงินออม','expense'=>'รายจ่าย'];

// ════════════════════════════════════════════════════════════════════
//  Sheet 1 – Annual Summary (matrix: categories × months)
// ════════════════════════════════════════════════════════════════════
$ws1 = $wb->getActiveSheet()->setTitle('ภาพรวมทั้งปี');
$ws1->getDefaultRowDimension()->setRowHeight(18);

// Title
$ws1->mergeCells('A1:N1');
$ws1->setCellValue('A1', "รายงานการเงิน ปี พ.ศ. {$selectedBE}");
applyStyle($ws1,'A1',['font'=>['bold'=>true,'size'=>14,'color'=>['rgb'=>'0F172A']],'alignment'=>['horizontal'=>Alignment::HORIZONTAL_CENTER]]);
$ws1->getRowDimension(1)->setRowHeight(30);

// Column headers row
$ws1->setCellValue('A2','หมวดหมู่');
for($m=1;$m<=12;$m++) $ws1->setCellValue(chr(65+$m).'2',$thaiMonths[$m]);
$ws1->setCellValue('N2','รวมทั้งปี');
applyStyle($ws1,'A2:N2',headerStyle());
$ws1->getRowDimension(2)->setRowHeight(22);
$ws1->getColumnDimension('A')->setWidth(32);
for($c=1;$c<=13;$c++) $ws1->getColumnDimension(chr(65+$c))->setWidth(12);

$row = 3;
foreach(['income','saving','expense'] as $type){
    // Group header
    $ws1->mergeCells("A{$row}:N{$row}");
    $ws1->setCellValue("A{$row}",$typeLabels[$type]);
    $style = $type==='income'?subHeaderStyle():($type==='saving'?savSubHeader():expSubHeader());
    applyStyle($ws1,"A{$row}:N{$row}",$style);
    $ws1->getRowDimension($row)->setRowHeight(20);
    $row++;

    $groupTotals = array_fill(1,12,0);
    $groupYear   = 0;
    foreach($categories[$type] as $cat){
        $cid  = (int)$cat['id'];
        $ws1->setCellValue("A{$row}",$cat['name']);
        $yearTotal=0;
        for($m=1;$m<=12;$m++){
            $v = $amountMap[$cid][$m] ?? 0;
            if($v>0){
                $ws1->setCellValue(chr(65+$m).$row,$v);
                $ws1->getStyle(chr(65+$m).$row)->getNumberFormat()->setFormatCode(numFmt());
            }
            $groupTotals[$m]+=$v; $yearTotal+=$v;
        }
        if($yearTotal>0){
            $ws1->setCellValue("N{$row}",$yearTotal);
            $ws1->getStyle("N{$row}")->getNumberFormat()->setFormatCode(numFmt());
            $ws1->getStyle("N{$row}")->getFont()->setBold(true);
        }
        // Row bg
        $ws1->getStyle("A{$row}:N{$row}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB($typeColors[$type]);
        applyStyle($ws1,"A{$row}:N{$row}",thinBorder());
        $row++;
    }

    // Sub-total row
    $ws1->setCellValue("A{$row}","รวม{$typeLabels[$type]}");
    $subTotalYear=0;
    for($m=1;$m<=12;$m++){
        if($groupTotals[$m]>0){
            $ws1->setCellValue(chr(65+$m).$row,$groupTotals[$m]);
            $ws1->getStyle(chr(65+$m).$row)->getNumberFormat()->setFormatCode(numFmt());
        }
        $subTotalYear+=$groupTotals[$m];
    }
    $ws1->setCellValue("N{$row}",$subTotalYear);
    $ws1->getStyle("N{$row}")->getNumberFormat()->setFormatCode(numFmt());
    applyStyle($ws1,"A{$row}:N{$row}",totalRowStyle());
    applyStyle($ws1,"A{$row}:N{$row}",thinBorder());
    $ws1->getRowDimension($row)->setRowHeight(20);
    $row+=2;
}

// Net row
$ws1->mergeCells("A{$row}:N{$row}");
$ws1->setCellValue("A{$row}","─────────────────────────────────────");
$row++;
$ws1->setCellValue("A{$row}","คงเหลือสุทธิ (รายรับ − รายจ่าย − เงินออม)");
$ws1->getStyle("A{$row}")->getFont()->setBold(true)->setSize(11);
$netYear=0;
for($m=1;$m<=12;$m++){
    $n=$monthly[$m]['income']-$monthly[$m]['expense']-$monthly[$m]['saving'];
    $ws1->setCellValue(chr(65+$m).$row,$n);
    $ws1->getStyle(chr(65+$m).$row)->getNumberFormat()->setFormatCode(numFmt());
    if($n<0) $ws1->getStyle(chr(65+$m).$row)->getFont()->getColor()->setRGB('DC2626');
    else $ws1->getStyle(chr(65+$m).$row)->getFont()->getColor()->setRGB('15803D');
    $netYear+=$n;
}
$ws1->setCellValue("N{$row}",$netYear);
$ws1->getStyle("N{$row}")->getNumberFormat()->setFormatCode(numFmt());
$ws1->getStyle("N{$row}")->getFont()->setBold(true)->getColor()->setRGB($netYear>=0?'15803D':'DC2626');
applyStyle($ws1,"A{$row}:N{$row}",thinBorder());
$ws1->getRowDimension($row)->setRowHeight(22);

// Freeze panes
$ws1->freezePane('B3');

// ════════════════════════════════════════════════════════════════════
//  Sheet 2 – Detailed entries
// ════════════════════════════════════════════════════════════════════
$ws2 = $wb->createSheet()->setTitle('รายการทั้งหมด');
$ws2->setSelectedCells('A1');
$ws2->getDefaultRowDimension()->setRowHeight(17);

$headers2=['วันที่','ประเภท','หมวดหมู่','จำนวนเงิน','หมายเหตุ'];
foreach($headers2 as $ci=>$h){
    $ws2->setCellValue(chr(65+$ci).'1',$h);
}
applyStyle($ws2,'A1:E1',headerStyle());
$ws2->getRowDimension(1)->setRowHeight(22);
$ws2->getColumnDimension('A')->setWidth(14);
$ws2->getColumnDimension('B')->setWidth(12);
$ws2->getColumnDimension('C')->setWidth(32);
$ws2->getColumnDimension('D')->setWidth(14);
$ws2->getColumnDimension('E')->setWidth(50);

$r2=2;
foreach($entries as $e){
    $dp=explode('-',$e['entry_date']);
    $thaiDate=sprintf('%02d/%02d/%04d',(int)$dp[2],(int)$dp[1],(int)$dp[0]+543);
    $ws2->setCellValue("A{$r2}",$thaiDate);
    $ws2->setCellValue("B{$r2}",$typeLabels[$e['type']]??$e['type']);
    $ws2->setCellValue("C{$r2}",$e['category']);
    $ws2->setCellValue("D{$r2}",(float)$e['amount']);
    $ws2->getStyle("D{$r2}")->getNumberFormat()->setFormatCode(numFmt());
    $ws2->setCellValue("E{$r2}",str_replace(["\r\n","\r","\n"],' ',$e['note']));
    // Row color by type
    $ws2->getStyle("A{$r2}:E{$r2}")->getFill()->setFillType(Fill::FILL_SOLID)
        ->getStartColor()->setRGB($typeColors[$e['type']]??'FFFFFF');
    applyStyle($ws2,"A{$r2}:E{$r2}",thinBorder());
    $r2++;
}
// Auto-filter
$ws2->setAutoFilter("A1:E".($r2-1));
$ws2->freezePane('A2');

// ════════════════════════════════════════════════════════════════════
//  Sheet 3 – Monthly summary
// ════════════════════════════════════════════════════════════════════
$ws3 = $wb->createSheet()->setTitle('สรุปรายเดือน');
$ws3->getDefaultRowDimension()->setRowHeight(18);

$h3=['เดือน','รายรับ','รายจ่าย','เงินออม','สุทธิ','อัตราออม%'];
foreach($h3 as $ci=>$h) $ws3->setCellValue(chr(65+$ci).'1',$h);
applyStyle($ws3,'A1:F1',headerStyle());
$ws3->getRowDimension(1)->setRowHeight(22);
foreach(['A'=>16,'B'=>14,'C'=>14,'D'=>14,'E'=>14,'F'=>14] as $col=>$w) $ws3->getColumnDimension($col)->setWidth($w);

$r3=2; $tI=$tE=$tS=0;
for($m=1;$m<=12;$m++){
    $d=$monthly[$m]; $net=$d['income']-$d['expense']-$d['saving'];
    $rate=$d['income']>0?round($d['saving']/$d['income']*100,1):0;
    $ws3->setCellValue("A{$r3}",$thaiMonths[$m]);
    $ws3->setCellValue("B{$r3}",$d['income']); $ws3->getStyle("B{$r3}")->getNumberFormat()->setFormatCode(numFmt());
    $ws3->setCellValue("C{$r3}",$d['expense']); $ws3->getStyle("C{$r3}")->getNumberFormat()->setFormatCode(numFmt());
    $ws3->setCellValue("D{$r3}",$d['saving']); $ws3->getStyle("D{$r3}")->getNumberFormat()->setFormatCode(numFmt());
    $ws3->setCellValue("E{$r3}",$net); $ws3->getStyle("E{$r3}")->getNumberFormat()->setFormatCode(numFmt());
    if($net<0) $ws3->getStyle("E{$r3}")->getFont()->getColor()->setRGB('DC2626');
    $ws3->setCellValue("F{$r3}",$rate); $ws3->getStyle("F{$r3}")->getNumberFormat()->setFormatCode('0.0"%"');
    applyStyle($ws3,"A{$r3}:F{$r3}",thinBorder());
    $tI+=$d['income'];$tE+=$d['expense'];$tS+=$d['saving'];
    $r3++;
}
// Total row
$tNet=$tI-$tE-$tS; $tRate=$tI>0?round($tS/$tI*100,1):0;
$ws3->setCellValue("A{$r3}",'รวมทั้งปี');
foreach(['B'=>$tI,'C'=>$tE,'D'=>$tS,'E'=>$tNet,'F'=>$tRate] as $col=>$v){
    $ws3->setCellValue("{$col}{$r3}",$v);
    $ws3->getStyle("{$col}{$r3}")->getNumberFormat()->setFormatCode($col==='F'?'0.0"%"':numFmt());
}
applyStyle($ws3,"A{$r3}:F{$r3}",totalRowStyle());
applyStyle($ws3,"A{$r3}:F{$r3}",thinBorder());
$ws3->getRowDimension($r3)->setRowHeight(20);

// Go back to Sheet 1
$wb->setActiveSheetIndex(0);

// ── Output ──────────────────────────────────────────────────────────────────
$filename = "finance_{$selectedBE}_".date('Ymd').".xlsx";
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header("Content-Disposition: attachment; filename=\"{$filename}\"");
header('Cache-Control: max-age=0');
(new Xlsx($wb))->save('php://output');
exit;
