<?php
include 'auth.php';
include 'config/db.php';
include 'config/functions.php';
mysqli_set_charset($conn, 'utf8');

$userId   = (int)$_SESSION['user_id'];
$page_title = 'รายงานภาพรวม';

$requestedYear = isset($_GET['year']) ? (int)$_GET['year'] : 0;
if ($requestedYear > 2400) {
    $selectedBE = $requestedYear;
    $selectedAD = $requestedYear - 543;
} elseif ($requestedYear > 1900) {
    $selectedAD = $requestedYear;
    $selectedBE = $requestedYear + 543;
} else {
    $selectedAD = (int)date('Y');
    $selectedBE = $selectedAD + 543;
}

$thaiMonths = [1=>'ม.ค.','ก.พ.','มี.ค.','เม.ย.','พ.ค.','มิ.ย.','ก.ค.','ส.ค.','ก.ย.','ต.ค.','พ.ย.','ธ.ค.'];
$thaiMonthsFull = [1=>'มกราคม','กุมภาพันธ์','มีนาคม','เมษายน','พฤษภาคม','มิถุนายน','กรกฎาคม','สิงหาคม','กันยายน','ตุลาคม','พฤศจิกายน','ธันวาคม'];

// Year options
$yearOptions = [];
$rs = mysqli_query($conn, "SELECT DISTINCT YEAR(entry_date) AS y FROM entries WHERE user_id={$userId} ORDER BY y DESC");
while ($r = mysqli_fetch_assoc($rs)) {
    $yearOptions[] = ['ad'=>(int)$r['y'], 'be'=>(int)$r['y']+543];
}
if (empty($yearOptions)) $yearOptions[] = ['ad'=>$selectedAD,'be'=>$selectedBE];

// Summary totals
$summary = ['income'=>0,'expense'=>0,'saving'=>0];
$rs = mysqli_query($conn,"
    SELECT c.type, SUM(e.amount) AS t
    FROM entries e JOIN categories c ON e.category_id=c.id
    WHERE YEAR(e.entry_date)={$selectedAD} AND e.user_id={$userId} AND c.user_id={$userId}
    GROUP BY c.type
");
while($r=mysqli_fetch_assoc($rs)) if(isset($summary[$r['type']])) $summary[$r['type']]=(float)$r['t'];
$balance = $summary['income'] - $summary['expense'] - $summary['saving'];
$savingRate = $summary['income'] > 0 ? ($summary['saving']/$summary['income']*100) : 0;

// Monthly breakdown
$monthly = [];
for($m=1;$m<=12;$m++) $monthly[$m]=['income'=>0,'expense'=>0,'saving'=>0,'net'=>0];
$rs = mysqli_query($conn,"
    SELECT MONTH(e.entry_date) AS m, c.type, SUM(e.amount) AS t
    FROM entries e JOIN categories c ON e.category_id=c.id
    WHERE YEAR(e.entry_date)={$selectedAD} AND e.user_id={$userId} AND c.user_id={$userId}
    GROUP BY MONTH(e.entry_date), c.type
");
while($r=mysqli_fetch_assoc($rs)){
    $m=(int)$r['m'];
    if(isset($monthly[$m][$r['type']])) $monthly[$m][$r['type']]=(float)$r['t'];
}
for($m=1;$m<=12;$m++) $monthly[$m]['net']=$monthly[$m]['income']-$monthly[$m]['expense']-$monthly[$m]['saving'];

// Top expense categories
$topExpenses = [];
$rs = mysqli_query($conn,"
    SELECT c.name, SUM(e.amount) AS t
    FROM entries e JOIN categories c ON e.category_id=c.id
    WHERE YEAR(e.entry_date)={$selectedAD} AND e.user_id={$userId} AND c.user_id={$userId} AND c.type='expense'
    GROUP BY c.id, c.name ORDER BY t DESC LIMIT 8
");
while($r=mysqli_fetch_assoc($rs)) $topExpenses[]=$r;

// Monthly income source
$incomeSources = [];
$rs = mysqli_query($conn,"
    SELECT c.name, SUM(e.amount) AS t
    FROM entries e JOIN categories c ON e.category_id=c.id
    WHERE YEAR(e.entry_date)={$selectedAD} AND e.user_id={$userId} AND c.user_id={$userId} AND c.type='income'
    GROUP BY c.id, c.name ORDER BY t DESC
");
while($r=mysqli_fetch_assoc($rs)) $incomeSources[]=$r;

// Chart data
$chartLabels   = array_values($thaiMonths);
$chartIncome   = array_map(fn($m)=>$m['income'],  array_values($monthly));
$chartExpense  = array_map(fn($m)=>$m['expense'], array_values($monthly));
$chartSaving   = array_map(fn($m)=>$m['saving'],  array_values($monthly));
$chartNet      = array_map(fn($m)=>$m['net'],      array_values($monthly));

// Months with data
$activeMonths = array_filter($monthly, fn($m)=>$m['income']>0||$m['expense']>0);

include 'partials/header.php';
?>
<style>
.report-hero{background:linear-gradient(135deg,#0f172a 0%,#1e3a5f 100%);color:#fff;border-radius:20px;padding:2rem 2rem 1.5rem;margin-bottom:1.5rem}
.report-hero h1{font-size:1.6rem;font-weight:700;margin-bottom:.25rem}
.report-hero .sub{color:rgba(255,255,255,.6);font-size:.95rem}
.report-hero .year-sel select{background:rgba(255,255,255,.18);border:1px solid rgba(255,255,255,.3);color:#fff;border-radius:12px;padding:.45rem .9rem;font-size:.95rem;font-weight:700;cursor:pointer}
.report-hero .year-sel select option{background:#1e3a5f;color:#fff}
.kpi-grid{display:grid;grid-template-columns:repeat(4,1fr);gap:1rem;margin-bottom:1.5rem}
@media(max-width:768px){.kpi-grid{grid-template-columns:repeat(2,1fr)}}
.kpi-card{background:#fff;border:1px solid rgba(15,23,42,.07);border-radius:16px;padding:1.1rem 1.25rem}
.kpi-label{font-size:.8rem;font-weight:600;color:#64748b;letter-spacing:.04em;text-transform:uppercase;margin-bottom:.4rem}
.kpi-value{font-size:1.55rem;font-weight:800;line-height:1.2}
.kpi-sub{font-size:.82rem;color:#94a3b8;margin-top:.25rem}
.kpi-card.is-income .kpi-value{color:#15803d}
.kpi-card.is-expense .kpi-value{color:#dc2626}
.kpi-card.is-saving .kpi-value{color:#7c3aed}
.kpi-card.is-net .kpi-value{color:#1d4ed8}
.report-grid{display:grid;grid-template-columns:1.6fr 1fr;gap:1.25rem;margin-bottom:1.25rem}
@media(max-width:900px){.report-grid{grid-template-columns:1fr}}
.r-card{background:#fff;border:1px solid rgba(15,23,42,.07);border-radius:16px;padding:1.25rem 1.4rem}
.r-card-title{font-size:.8rem;font-weight:700;color:#64748b;letter-spacing:.06em;text-transform:uppercase;margin-bottom:1rem}
.chart-box{position:relative;width:100%}
.bar-item{display:flex;align-items:center;gap:.6rem;margin-bottom:.6rem}
.bar-label{font-size:.85rem;color:#475569;flex:0 0 160px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.bar-track{flex:1;height:10px;background:#f1f5f9;border-radius:99px;overflow:hidden}
.bar-fill{height:100%;border-radius:99px;transition:width .4s}
.bar-amount{font-size:.83rem;color:#0f172a;font-weight:700;flex:0 0 90px;text-align:right}
.month-table{width:100%;border-collapse:collapse;font-size:.88rem}
.month-table th{text-align:left;padding:.5rem .6rem;color:#64748b;font-weight:600;border-bottom:1px solid #f1f5f9;font-size:.78rem;text-transform:uppercase;letter-spacing:.04em}
.month-table td{padding:.55rem .6rem;border-bottom:1px solid #f8fafc;color:#0f172a}
.month-table tr:last-child td{border-bottom:none;font-weight:700;color:#0f172a;background:#f8fafc}
.txt-green{color:#15803d}.txt-red{color:#dc2626}.txt-purple{color:#7c3aed}.txt-blue{color:#1d4ed8}
.txt-right{text-align:right}
.saving-bar{background:#f5f3ff;border-radius:12px;padding:1rem;margin-top:.75rem}
.saving-bar-label{font-size:.8rem;color:#7c3aed;font-weight:600;margin-bottom:.4rem}
.saving-bar-track{height:12px;background:#ede9fe;border-radius:99px;overflow:hidden}
.saving-bar-fill{height:100%;background:linear-gradient(90deg,#7c3aed,#a78bfa);border-radius:99px}
.saving-bar-pct{font-size:1.2rem;font-weight:800;color:#7c3aed;margin-top:.35rem}
.export-btn{display:inline-flex;align-items:center;gap:.4rem;background:#0f172a;color:#fff;border:none;border-radius:10px;padding:.55rem 1.1rem;font-size:.9rem;font-weight:600;cursor:pointer;text-decoration:none}
.export-btn:hover{background:#1e293b;color:#fff}
.legend-row{display:flex;gap:1rem;flex-wrap:wrap;margin-bottom:.75rem}
.legend-dot{width:10px;height:10px;border-radius:3px;display:inline-block;margin-right:4px}
.legend-item{font-size:.8rem;color:#64748b;display:flex;align-items:center}
</style>

<div class="report-hero">
    <div class="d-flex justify-content-between align-items-start flex-wrap gap-3">
        <div>
            <h1>รายงานการเงิน ปี พ.ศ. <?php echo h($selectedBE); ?></h1>
            <div class="sub">สรุปรายรับ รายจ่าย และเงินออมทั้งปี</div>
        </div>
        <div class="d-flex align-items-center gap-2 flex-wrap">
            <form method="get" class="year-sel">
                <select name="year" onchange="this.form.submit()">
                    <?php foreach($yearOptions as $y): ?>
                        <option value="<?php echo $y['be']; ?>" <?php echo $y['be']==$selectedBE?'selected':''; ?>>
                            พ.ศ. <?php echo $y['be']; ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </form>
            <a href="export_excel.php?year=<?php echo $selectedBE; ?>" class="export-btn">
                <i class="bi bi-file-earmark-excel"></i> Export Excel
            </a>
        </div>
    </div>
</div>

<div class="kpi-grid">
    <div class="kpi-card is-income">
        <div class="kpi-label">รายรับรวม</div>
        <div class="kpi-value">฿<?php echo number_format($summary['income'],0); ?></div>
        <div class="kpi-sub"><?php echo count($incomeSources); ?> แหล่งรายรับ</div>
    </div>
    <div class="kpi-card is-expense">
        <div class="kpi-label">รายจ่ายรวม</div>
        <div class="kpi-value">฿<?php echo number_format($summary['expense'],0); ?></div>
        <div class="kpi-sub">เฉลี่ยเดือนละ ฿<?php echo number_format($summary['expense']/max(1,count($activeMonths)),0); ?></div>
    </div>
    <div class="kpi-card is-saving">
        <div class="kpi-label">เงินออมรวม</div>
        <div class="kpi-value">฿<?php echo number_format($summary['saving'],0); ?></div>
        <div class="kpi-sub">อัตราออม <?php echo number_format($savingRate,1); ?>%</div>
    </div>
    <div class="kpi-card is-net">
        <div class="kpi-label">คงเหลือสุทธิ</div>
        <div class="kpi-value <?php echo $balance<0?'txt-red':''; ?>">฿<?php echo number_format($balance,0); ?></div>
        <div class="kpi-sub"><?php echo $balance>=0?'บวก ✓':'ติดลบ ⚠'; ?></div>
    </div>
</div>

<div class="report-grid">
    <div class="r-card">
        <div class="r-card-title">รายรับ / รายจ่าย / เงินออม รายเดือน</div>
        <div class="legend-row">
            <span class="legend-item"><span class="legend-dot" style="background:#15803d"></span>รายรับ</span>
            <span class="legend-item"><span class="legend-dot" style="background:#dc2626"></span>รายจ่าย</span>
            <span class="legend-item"><span class="legend-dot" style="background:#7c3aed"></span>เงินออม</span>
        </div>
        <div class="chart-box" style="height:260px">
            <canvas id="mainChart" role="img" aria-label="กราฟรายรับรายจ่ายรายเดือน"></canvas>
        </div>
    </div>
    <div class="r-card">
        <div class="r-card-title">กระแสสุทธิรายเดือน</div>
        <div class="chart-box" style="height:110px;margin-bottom:1rem">
            <canvas id="netChart" role="img" aria-label="กราฟกระแสสุทธิรายเดือน"></canvas>
        </div>
        <div class="saving-bar">
            <div class="saving-bar-label">อัตราการออม</div>
            <div class="saving-bar-track">
                <div class="saving-bar-fill" style="width:<?php echo min(100,round($savingRate)); ?>%"></div>
            </div>
            <div class="saving-bar-pct"><?php echo number_format($savingRate,1); ?>%</div>
        </div>
        <div class="mt-3">
            <div class="r-card-title">สัดส่วนรายจ่าย</div>
            <div class="chart-box" style="height:160px">
                <canvas id="pieChart" role="img" aria-label="สัดส่วนรายจ่ายตามหมวด"></canvas>
            </div>
        </div>
    </div>
</div>

<div class="report-grid">
    <div class="r-card">
        <div class="r-card-title">สรุปรายเดือน</div>
        <table class="month-table">
            <thead>
                <tr>
                    <th>เดือน</th>
                    <th class="txt-right">รายรับ</th>
                    <th class="txt-right">รายจ่าย</th>
                    <th class="txt-right">ออม</th>
                    <th class="txt-right">สุทธิ</th>
                </tr>
            </thead>
            <tbody>
            <?php
            $totI=$totE=$totS=$totN=0;
            for($m=1;$m<=12;$m++):
                $d=$monthly[$m];
                $hasData=($d['income']>0||$d['expense']>0||$d['saving']>0);
                $totI+=$d['income'];$totE+=$d['expense'];$totS+=$d['saving'];$totN+=$d['net'];
            ?>
                <tr <?php echo !$hasData?'style="opacity:.35"':''; ?>>
                    <td><?php echo h($thaiMonths[$m]); ?></td>
                    <td class="txt-right txt-green"><?php echo $d['income']>0?'฿'.number_format($d['income'],0):'-'; ?></td>
                    <td class="txt-right txt-red"><?php echo $d['expense']>0?'฿'.number_format($d['expense'],0):'-'; ?></td>
                    <td class="txt-right txt-purple"><?php echo $d['saving']>0?'฿'.number_format($d['saving'],0):'-'; ?></td>
                    <td class="txt-right <?php echo $d['net']>=0?'txt-blue':'txt-red'; ?>"><?php echo $hasData?'฿'.number_format($d['net'],0):'-'; ?></td>
                </tr>
            <?php endfor; ?>
                <tr>
                    <td>รวม</td>
                    <td class="txt-right txt-green">฿<?php echo number_format($totI,0); ?></td>
                    <td class="txt-right txt-red">฿<?php echo number_format($totE,0); ?></td>
                    <td class="txt-right txt-purple">฿<?php echo number_format($totS,0); ?></td>
                    <td class="txt-right <?php echo $totN>=0?'txt-blue':'txt-red'; ?>">฿<?php echo number_format($totN,0); ?></td>
                </tr>
            </tbody>
        </table>
    </div>
    <div class="r-card">
        <div class="r-card-title">รายจ่ายสูงสุดตามหมวด</div>
        <?php
        $maxExp = !empty($topExpenses)?(float)$topExpenses[0]['t']:1;
        $colors = ['#dc2626','#ef4444','#f87171','#fca5a5','#f97316','#fb923c','#fdba74','#fed7aa'];
        foreach($topExpenses as $i=>$cat):
            $pct = $maxExp>0?round((float)$cat['t']/$maxExp*100):0;
        ?>
        <div class="bar-item">
            <div class="bar-label" title="<?php echo h($cat['name']); ?>"><?php echo h($cat['name']); ?></div>
            <div class="bar-track"><div class="bar-fill" style="width:<?php echo $pct; ?>%;background:<?php echo $colors[$i%8]; ?>"></div></div>
            <div class="bar-amount">฿<?php echo number_format((float)$cat['t'],0); ?></div>
        </div>
        <?php endforeach; ?>

        <div style="margin-top:1.25rem" class="r-card-title">แหล่งรายรับ</div>
        <?php
        $maxInc = !empty($incomeSources)?(float)$incomeSources[0]['t']:1;
        foreach($incomeSources as $src):
            $pct2 = $maxInc>0?round((float)$src['t']/$maxInc*100):0;
        ?>
        <div class="bar-item">
            <div class="bar-label" title="<?php echo h($src['name']); ?>"><?php echo h($src['name']); ?></div>
            <div class="bar-track"><div class="bar-fill" style="width:<?php echo $pct2; ?>%;background:#15803d"></div></div>
            <div class="bar-amount txt-green">฿<?php echo number_format((float)$src['t'],0); ?></div>
        </div>
        <?php endforeach; ?>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
const labels = <?php echo json_encode($chartLabels,JSON_UNESCAPED_UNICODE); ?>;
const income  = <?php echo json_encode($chartIncome); ?>;
const expense = <?php echo json_encode($chartExpense); ?>;
const saving  = <?php echo json_encode($chartSaving); ?>;
const net     = <?php echo json_encode($chartNet); ?>;

const fmtBaht = v => '฿'+Number(v).toLocaleString();
const gridColor = 'rgba(100,116,139,.1)';

new Chart(document.getElementById('mainChart'),{
    type:'bar',
    data:{labels,datasets:[
        {label:'รายรับ',data:income,backgroundColor:'rgba(21,128,61,.8)',borderRadius:5,maxBarThickness:22},
        {label:'รายจ่าย',data:expense,backgroundColor:'rgba(220,38,38,.8)',borderRadius:5,maxBarThickness:22},
        {label:'เงินออม',data:saving,backgroundColor:'rgba(124,58,237,.8)',borderRadius:5,maxBarThickness:22}
    ]},
    options:{responsive:true,maintainAspectRatio:false,
        plugins:{legend:{display:false},tooltip:{callbacks:{label:c=>c.dataset.label+': '+fmtBaht(c.raw)}}},
        scales:{x:{ticks:{autoSkip:false},grid:{display:false}},y:{ticks:{callback:v=>fmtBaht(v)},grid:{color:gridColor}}}
    }
});

new Chart(document.getElementById('netChart'),{
    type:'bar',
    data:{labels,datasets:[{label:'สุทธิ',data:net,backgroundColor:net.map(v=>v>=0?'rgba(29,78,216,.75)':'rgba(220,38,38,.75)'),borderRadius:4,maxBarThickness:18}]},
    options:{responsive:true,maintainAspectRatio:false,
        plugins:{legend:{display:false},tooltip:{callbacks:{label:c=>fmtBaht(c.raw)}}},
        scales:{x:{ticks:{font:{size:10},autoSkip:false},grid:{display:false}},y:{ticks:{callback:v=>fmtBaht(v),font:{size:10}},grid:{color:gridColor}}}
    }
});

const expLabels = <?php echo json_encode(array_column($topExpenses,'name'),JSON_UNESCAPED_UNICODE); ?>;
const expData   = <?php echo json_encode(array_map(fn($c)=>(float)$c['t'],$topExpenses)); ?>;
const pieColors = ['#dc2626','#ef4444','#f87171','#fca5a5','#f97316','#fb923c','#fdba74','#fed7aa'];

new Chart(document.getElementById('pieChart'),{
    type:'doughnut',
    data:{labels:expLabels,datasets:[{data:expData,backgroundColor:pieColors,borderWidth:2,borderColor:'#fff'}]},
    options:{responsive:true,maintainAspectRatio:false,
        plugins:{legend:{display:false},tooltip:{callbacks:{label:c=>{
            const t=c.dataset.data.reduce((a,b)=>a+b,0);
            return c.label+': '+fmtBaht(c.raw)+' ('+(c.raw/t*100).toFixed(1)+'%)';
        }}}}
    }
});
</script>

<?php include 'partials/footer.php'; ?>
