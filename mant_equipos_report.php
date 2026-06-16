<?php
// /var/www/html/calibraciones/mant_equipos_report.php
declare(strict_types=1);

require_once __DIR__.'/config.php';
require_auth(['admin','ingenieria']);

function h(?string $s): string { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

$pdo = pdo();

// Períodos activos filtrados por GET (default: todos)
$filterPeriods = $_GET['periodos'] ?? ['1M','3M','1Y'];
if (!is_array($filterPeriods)) $filterPeriods = [$filterPeriods];
$validPeriods  = ['1M','3M','1Y'];
$filterPeriods = array_values(array_intersect($filterPeriods, $validPeriods));
if (empty($filterPeriods)) $filterPeriods = $validPeriods;

$cycleLabel = ['1M'=>'Mensual','3M'=>'Trimestral','1Y'=>'Anual'];
$cycleColor = ['1M'=>'#a5b4fc','3M'=>'#fdba74','1Y'=>'#6ee7b7'];
$cycleBg    = ['1M'=>'rgba(99,102,241,0.18)','3M'=>'rgba(251,146,60,0.18)','1Y'=>'rgba(32,201,151,0.18)'];

// Helper: calcula estado a partir de una fecha próxima
function calcStatus(?string $next): string {
    if (!$next) return 'Sin fecha';
    $today = date('Y-m-d');
    $limit = date('Y-m-d', strtotime('+30 days'));
    if ($next < $today)  return 'Vencido';
    if ($next <= $limit) return 'Próximo';
    return 'Al corriente';
}

// Cargar todos los equipos activos con sus 3 ciclos
$allRows = $pdo->query("
    SELECT ID, Description, Brand, Model, SerialNumber, Location, Comments,
           CycleMonthly, CycleQuarterly, CycleYearly,
           LastMaintDate_1M, NextMaintDate_1M,
           LastMaintDate_3M, NextMaintDate_3M,
           LastMaintDate   AS LastMaintDate_1Y,
           NextMaintDate   AS NextMaintDate_1Y
    FROM mant_equipos
    WHERE Status != 'Scrap'
    ORDER BY Description ASC
")->fetchAll();

// Expandir en filas por ciclo activo
$cycleRows = [];
foreach ($allRows as $r) {
    $map = [
        '1M' => ['enabled'=>(bool)$r['CycleMonthly'],   'last'=>$r['LastMaintDate_1M'], 'next'=>$r['NextMaintDate_1M']],
        '3M' => ['enabled'=>(bool)$r['CycleQuarterly'],  'last'=>$r['LastMaintDate_3M'], 'next'=>$r['NextMaintDate_3M']],
        '1Y' => ['enabled'=>(bool)$r['CycleYearly'],     'last'=>$r['LastMaintDate_1Y'], 'next'=>$r['NextMaintDate_1Y']],
    ];
    foreach ($map as $cycle => $d) {
        if (!$d['enabled']) continue;
        $status = calcStatus($d['next'] ?: null);
        $daysLeft = $d['next'] ? (int)((strtotime($d['next']) - strtotime('today')) / 86400) : null;
        $cycleRows[] = [
            'ID'          => $r['ID'],
            'Description' => $r['Description'],
            'Brand'       => $r['Brand'],
            'Model'       => $r['Model'],
            'SerialNumber'=> $r['SerialNumber'],
            'Location'    => $r['Location'],
            'Comments'    => $r['Comments'],
            'cycle'       => $cycle,
            'last'        => $d['last'],
            'next'        => $d['next'],
            'status'      => $status,
            'days_left'   => $daysLeft,
        ];
    }
}

// CSV download
if (isset($_GET['download']) && $_GET['download'] === 'csv') {
    $filename = 'reporte_mantenimientos_' . date('Y-m-d') . '.csv';
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    $out = fopen('php://output', 'w');
    fprintf($out, chr(0xEF).chr(0xBB).chr(0xBF));
    fputcsv($out, ['ID','Descripción','Marca','Modelo','No. Serie','Ubicación','Ciclo','Último Mant.','Próximo Mant.','Estado','Días restantes']);
    foreach ($cycleRows as $cr) {
        if (!in_array($cr['cycle'], $filterPeriods)) continue;
        fputcsv($out, [
            $cr['ID'], $cr['Description'], $cr['Brand'], $cr['Model'], $cr['SerialNumber'],
            $cr['Location'], $cycleLabel[$cr['cycle']] ?? $cr['cycle'],
            $cr['last'], $cr['next'], $cr['status'], $cr['days_left'] ?? '',
        ]);
    }
    fclose($out);
    exit;
}

// Filtrar para vista
$visibleRows = array_filter($cycleRows, fn($r) => in_array($r['cycle'], $filterPeriods));

// Alertas: próximos y vencidos (todos los ciclos, dentro de 30 días)
$alertRows = array_filter($cycleRows, fn($r) => in_array($r['status'], ['Vencido','Próximo']));
usort($alertRows, fn($a,$b) => strcmp((string)$a['next'], (string)$b['next']));

// KPIs por período filtrado
$kpiTotal = count($visibleRows);
$kpiVen   = count(array_filter($visibleRows, fn($r) => $r['status'] === 'Vencido'));
$kpiProx  = count(array_filter($visibleRows, fn($r) => $r['status'] === 'Próximo'));
$kpiCal   = count(array_filter($visibleRows, fn($r) => $r['status'] === 'Al corriente'));

// Gráfica pastel estado
$pie_labels = ['Al corriente','Próximo','Vencido','Sin fecha'];
$pie_data   = [0,0,0,0];
$pie_colors = ['#36a2eb','#ffcd56','#ff6384','#8b92a7'];
foreach ($visibleRows as $r) {
    if     ($r['status'] === 'Al corriente') $pie_data[0]++;
    elseif ($r['status'] === 'Próximo')      $pie_data[1]++;
    elseif ($r['status'] === 'Vencido')      $pie_data[2]++;
    else                                     $pie_data[3]++;
}

// Gráfica barras: vencimientos próximos 12 meses
$pending_labels = [];
for ($i=0;$i<12;$i++) $pending_labels[] = date('Y-m', strtotime("+{$i} months"));
$barCounts = [];
foreach ($validPeriods as $cyc) $barCounts[$cyc] = array_fill_keys($pending_labels, 0);
foreach ($cycleRows as $cr) {
    if (!$cr['next']) continue;
    $mes = substr($cr['next'],0,7);
    if (isset($barCounts[$cr['cycle']][$mes])) $barCounts[$cr['cycle']][$mes]++;
}

// Gráfica dona: por ciclo
$dona_labels = []; $dona_data = []; $dona_colors = [];
foreach ($validPeriods as $cyc) {
    $cnt = count(array_filter($cycleRows, fn($r)=>$r['cycle']===$cyc));
    if ($cnt) { $dona_labels[]=$cycleLabel[$cyc]; $dona_data[]=$cnt; $dona_colors[]=$cycleColor[$cyc]; }
}
?>
<?php include __DIR__.'/partials/header.php'; ?>

<style>
.kpi-card{background:rgba(255,255,255,0.03);border:1px solid rgba(255,255,255,0.08);border-radius:14px;padding:1.25rem;text-align:center;transition:all .25s ease;}
.kpi-card:hover{transform:translateY(-3px);border-color:rgba(255,255,255,0.15);box-shadow:0 8px 24px rgba(0,0,0,.25);}
.kpi-number{font-size:2.25rem;font-weight:700;line-height:1;margin-bottom:.35rem;}
.kpi-label{font-size:.8rem;text-transform:uppercase;letter-spacing:.07em;color:#8b92a7;}
.chart-box{position:relative;height:260px;}
@media(min-width:992px){.chart-box{height:280px;}}
.cycle-chip{display:inline-flex;align-items:center;gap:5px;padding:3px 10px;border-radius:20px;font-size:.75rem;font-weight:700;}
.chip-1M{background:rgba(99,102,241,.2);color:#a5b4fc;}
.chip-3M{background:rgba(251,146,60,.2);color:#fdba74;}
.chip-1Y{background:rgba(32,201,151,.2);color:#6ee7b7;}
.alert-row-ven{background:rgba(220,53,69,.07)!important;}
.alert-row-prox{background:rgba(255,193,7,.05)!important;}
.badge-ven{background:linear-gradient(135deg,#dc3545,#bd2130);color:#fff;}
.badge-prox{background:linear-gradient(135deg,#ffc107,#ff9800);color:#000;}
.badge-cal{background:linear-gradient(135deg,#28a745,#20c997);color:#fff;}
.badge-state{padding:.35em .75em;border-radius:20px;font-size:.78rem;font-weight:600;}
.filter-bar{display:flex;gap:.75rem;align-items:center;flex-wrap:wrap;background:rgba(0,0,0,.2);border-radius:12px;padding:.9rem 1rem;margin-bottom:1rem;border:1px solid rgba(255,255,255,.06);}
.period-toggle{display:flex;gap:.5rem;flex-wrap:wrap;}
.period-toggle input[type=checkbox]{display:none;}
.period-toggle label{padding:.35rem .9rem;border-radius:20px;font-size:.8rem;font-weight:600;cursor:pointer;transition:all .2s;border:2px solid transparent;user-select:none;}
.pt-1M{border-color:rgba(99,102,241,.4);color:#a5b4fc;}
.pt-1M.active{background:rgba(99,102,241,.25);border-color:#6366f1;box-shadow:0 0 0 3px rgba(99,102,241,.15);}
.pt-3M{border-color:rgba(251,146,60,.4);color:#fdba74;}
.pt-3M.active{background:rgba(251,146,60,.25);border-color:#fb923c;box-shadow:0 0 0 3px rgba(251,146,60,.15);}
.pt-1Y{border-color:rgba(32,201,151,.4);color:#6ee7b7;}
.pt-1Y.active{background:rgba(32,201,151,.25);border-color:#20c997;box-shadow:0 0 0 3px rgba(32,201,151,.15);}
</style>

<!-- Header -->
<div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
  <h1 class="h4 m-0"><i class="fa fa-gears me-2"></i>Reporte de Mantenimientos de Maquinaria</h1>
  <div class="d-flex gap-2 flex-wrap">
    <a href="mant_equipos_report.php?download=csv&<?= http_build_query(['periodos'=>$filterPeriods]) ?>" class="btn btn-success btn-sm">
      <i class="fa fa-file-csv me-1"></i>Descargar CSV
    </a>
    <a href="mant_equipos_admin.php" class="btn btn-outline-secondary btn-sm">
      <i class="fa fa-arrow-left me-1"></i>Volver al panel
    </a>
  </div>
</div>

<!-- Alertas de próximos/vencidos (todos los ciclos) -->
<?php if (!empty($alertRows)): ?>
<div class="alert alert-warning border-warning mb-3 p-3" style="border-left:4px solid #ffc107;">
  <div class="d-flex align-items-center gap-2 mb-2">
    <i class="fa fa-bell text-warning fa-lg"></i>
    <strong>Avisos de mantenimiento — <?= count($alertRows) ?> ciclo(s) requieren atención</strong>
  </div>
  <div class="table-responsive">
    <table class="table table-sm table-borderless mb-0" style="font-size:.88rem;">
      <thead><tr>
        <th class="text-secondary">Equipo</th>
        <th class="text-secondary">Ciclo</th>
        <th class="text-secondary">Próximo</th>
        <th class="text-secondary">Estado</th>
        <th class="text-secondary">Días</th>
      </tr></thead>
      <tbody>
      <?php foreach ($alertRows as $ar):
        $isVen = $ar['status']==='Vencido';
        $col   = $isVen ? '#ff6b7a' : '#ffd666';
      ?>
      <tr style="border-bottom:1px solid rgba(255,255,255,.06);">
        <td style="color:#fff;font-weight:600;"><?= h($ar['ID']) ?> <span class="text-secondary fw-normal">— <?= h($ar['Description']) ?></span></td>
        <td><span class="cycle-chip chip-<?= h($ar['cycle']) ?>"><?= h($cycleLabel[$ar['cycle']] ?? $ar['cycle']) ?></span></td>
        <td style="font-family:monospace;color:<?= $col ?>;"><?= h($ar['next'] ?: '—') ?></td>
        <td><span class="badge-state <?= $isVen ? 'badge-ven' : 'badge-prox' ?>"><?= h($ar['status']) ?></span></td>
        <td style="color:<?= $col ?>;font-weight:700;">
          <?php if ($ar['days_left'] !== null): ?>
            <?= $ar['days_left'] < 0 ? abs($ar['days_left']).' días de retraso' : $ar['days_left'].' días' ?>
          <?php else: ?>—<?php endif; ?>
        </td>
      </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<?php endif; ?>

<!-- Filtro de períodos -->
<form method="GET" action="mant_equipos_report.php" id="filterForm" class="filter-bar">
  <strong class="text-secondary" style="font-size:.82rem;white-space:nowrap;"><i class="fa fa-filter me-1"></i>Mostrar ciclos:</strong>
  <div class="period-toggle">
    <?php foreach (['1M'=>'Mensual','3M'=>'Trimestral','1Y'=>'Anual'] as $cyc=>$lbl): ?>
    <input type="checkbox" id="pt_<?= $cyc ?>" name="periodos[]" value="<?= $cyc ?>"
           <?= in_array($cyc,$filterPeriods)?'checked':'' ?> onchange="document.getElementById('filterForm').submit()">
    <label for="pt_<?= $cyc ?>" class="pt-<?= $cyc ?> <?= in_array($cyc,$filterPeriods)?'active':'' ?>">
      <i class="fa <?= $cyc==='1M'?'fa-calendar-day':($cyc==='3M'?'fa-rotate':'fa-calendar-check') ?> me-1"></i><?= $lbl ?>
    </label>
    <?php endforeach; ?>
  </div>
  <span class="text-secondary ms-auto" style="font-size:.82rem;"><?= count($visibleRows) ?> ciclo(s) activo(s) mostrado(s)</span>
</form>

<!-- KPIs -->
<div class="row g-3 mb-4">
  <?php
  $kpis=[['Total ciclos',$kpiTotal,'text-white'],['Al corriente',$kpiCal,'text-success'],['Próximos (≤30d)',$kpiProx,'text-warning'],['Vencidos',$kpiVen,'text-danger']];
  foreach($kpis as [$lbl,$val,$cls]):?>
  <div class="col-6 col-md-3">
    <div class="kpi-card">
      <div class="kpi-number <?= $cls ?>"><?= $val ?></div>
      <div class="kpi-label"><?= h($lbl) ?></div>
    </div>
  </div>
  <?php endforeach; ?>
</div>

<!-- Gráficas -->
<div class="row g-3 mb-4">
  <div class="col-md-4">
    <div class="card p-3"><h2 class="h6 mb-3">Ciclos por estado</h2>
      <div class="chart-box"><canvas id="pieChart"></canvas></div>
    </div>
  </div>
  <div class="col-md-4">
    <div class="card p-3"><h2 class="h6 mb-3">Vencimientos próximos 12 meses</h2>
      <div class="chart-box"><canvas id="barChart"></canvas></div>
    </div>
  </div>
  <div class="col-md-4">
    <div class="card p-3"><h2 class="h6 mb-3">Distribución por ciclo</h2>
      <div class="chart-box"><canvas id="donutChart"></canvas></div>
    </div>
  </div>
</div>

<!-- Tabla detallada -->
<div class="card p-3 table-density-comfort dt-container">
  <div class="dt-toolbar">
    <div class="input-group" style="max-width:320px;">
      <span class="input-group-text"><i class="fa fa-magnifying-glass"></i></span>
      <input type="text" class="form-control dt-search" placeholder="Buscar…">
    </div>
    <select class="form-select dt-rows-per-page" style="max-width:160px;">
      <option value="10">10 por página</option>
      <option value="20">20 por página</option>
      <option value="50" selected>50 por página</option>
      <option value="100">100 por página</option>
    </select>
    <div class="ms-auto d-flex gap-2 flex-wrap">
      <select class="form-select dt-filter" data-col="7" style="max-width:200px;">
        <option value="">Estado: todos</option>
        <option>Al corriente</option>
        <option>Próximo</option>
        <option>Vencido</option>
        <option>Sin fecha</option>
      </select>
    </div>
  </div>

  <div class="table-wrap"><div class="table-scroll">
    <table class="table table-striped table-hover align-middle">
      <thead>
        <tr>
          <th class="th-sort" data-sort="text">ID <span class="sort-ind">▲▼</span></th>
          <th class="th-sort" data-sort="text">Descripción <span class="sort-ind">▲▼</span></th>
          <th class="th-sort" data-sort="text">Marca/Modelo <span class="sort-ind">▲▼</span></th>
          <th class="th-sort" data-sort="text">Ubicación <span class="sort-ind">▲▼</span></th>
          <th class="th-sort" data-sort="text">Ciclo <span class="sort-ind">▲▼</span></th>
          <th class="th-sort" data-sort="date">Último Mant. <span class="sort-ind">▲▼</span></th>
          <th class="th-sort" data-sort="date">Próximo <span class="sort-ind">▲▼</span></th>
          <th class="th-sort" data-sort="text">Estado <span class="sort-ind">▲▼</span></th>
          <th class="th-sort" data-sort="num">Días <span class="sort-ind">▲▼</span></th>
        </tr>
      </thead>
      <tbody>
      <?php foreach ($visibleRows as $cr):
        $badge  = $cr['status']==='Vencido' ? 'badge-ven' : ($cr['status']==='Próximo' ? 'badge-prox' : ($cr['status']==='Al corriente' ? 'badge-cal' : ''));
        $rowCls = $cr['status']==='Vencido' ? 'alert-row-ven' : ($cr['status']==='Próximo' ? 'alert-row-prox' : '');
      ?>
      <tr class="<?= $rowCls ?>">
        <td><code style="font-size:.8rem;"><?= h($cr['ID']) ?></code></td>
        <td><?= h($cr['Description']) ?></td>
        <td class="text-secondary" style="font-size:.85rem;"><?= h(trim($cr['Brand'].' '.$cr['Model'])) ?></td>
        <td><?= h($cr['Location'] ?: '—') ?></td>
        <td><span class="cycle-chip chip-<?= h($cr['cycle']) ?>"><?= h($cycleLabel[$cr['cycle']] ?? $cr['cycle']) ?></span></td>
        <td style="font-family:monospace;font-size:.88rem;"><?= h($cr['last'] ?: '—') ?></td>
        <td style="font-family:monospace;font-size:.88rem;"><?= h($cr['next'] ?: '—') ?></td>
        <td><?= $badge ? '<span class="badge-state '.$badge.'">'.h($cr['status']).'</span>' : h($cr['status']) ?></td>
        <td><?= $cr['days_left'] !== null ? h((string)$cr['days_left']) : '—' ?></td>
      </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div></div>

  <div class="d-flex justify-content-between align-items-center mt-2">
    <small class="text-secondary">Usa la lupa para filtrar rápidamente.</small>
    <div class="dt-pager"></div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chartjs-plugin-datalabels@2"></script>
<script>
Chart.register(ChartDataLabels);

// Pastel — estado
new Chart(document.getElementById('pieChart'),{
  type:'pie',
  data:{
    labels:<?= json_encode(array_values(array_filter($pie_labels,fn($k)=>$pie_data[$k]>0,ARRAY_FILTER_USE_KEY))) ?>,
    datasets:[{data:<?= json_encode(array_values(array_filter($pie_data,fn($v)=>$v>0))) ?>,
              backgroundColor:<?= json_encode(array_values(array_filter($pie_colors,fn($k)=>$pie_data[$k]>0,ARRAY_FILTER_USE_KEY))) ?>}]
  },
  options:{responsive:true,maintainAspectRatio:false,plugins:{
    legend:{position:'bottom',labels:{color:'#e0e0e0'}},
    datalabels:{color:'#fff',formatter:(v,ctx)=>{const d=ctx.chart.data.datasets[0].data,s=d.reduce((a,b)=>a+b,0)||1;return v+'('+((v*100/s).toFixed(0))+'%)'},font:{weight:'bold',size:12}}
  }}
});

// Barras — próximos 12 meses por ciclo
const barLabels = <?= json_encode($pending_labels) ?>;
const barDatasets = [
<?php foreach ($validPeriods as $cyc): if (!in_array($cyc,$filterPeriods)) continue; ?>
  {label:'<?= $cycleLabel[$cyc] ?>',data:<?= json_encode(array_values($barCounts[$cyc])) ?>,backgroundColor:'<?= $cycleColor[$cyc] ?>'},
<?php endforeach; ?>
];
new Chart(document.getElementById('barChart'),{
  type:'bar',
  data:{labels:barLabels,datasets:barDatasets},
  options:{responsive:true,maintainAspectRatio:false,
    scales:{x:{stacked:true,ticks:{color:'#e0e0e0'}},y:{stacked:true,ticks:{color:'#e0e0e0'},beginAtZero:true}},
    plugins:{legend:{labels:{color:'#e0e0e0'}},datalabels:{display:false}}
  }
});

// Dona — distribución por ciclo
new Chart(document.getElementById('donutChart'),{
  type:'doughnut',
  data:{labels:<?= json_encode($dona_labels) ?>,datasets:[{data:<?= json_encode($dona_data) ?>,backgroundColor:<?= json_encode($dona_colors) ?>}]},
  options:{responsive:true,maintainAspectRatio:false,plugins:{
    legend:{position:'bottom',labels:{color:'#e0e0e0'}},
    datalabels:{color:'#fff',formatter:(v,ctx)=>{const d=ctx.chart.data.datasets[0].data,s=d.reduce((a,b)=>a+b,0)||1;return v+'('+((v*100/s).toFixed(0))+'%)'},font:{weight:'bold',size:12}}
  }}
});

// Toggle visual de labels al cambiar checkbox
document.querySelectorAll('.period-toggle input[type=checkbox]').forEach(cb=>{
  const lbl=document.querySelector('label[for="'+cb.id+'"]');
  cb.addEventListener('change',()=>lbl.classList.toggle('active',cb.checked));
  lbl.classList.toggle('active',cb.checked);
});
</script>

<?php include __DIR__.'/partials/footer.php'; ?>
