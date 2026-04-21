<?php
// /var/www/html/calibraciones/mant_equipos_report.php
declare(strict_types=1);

require_once __DIR__.'/config.php';
require_auth(['admin','ingenieria']);

function h(?string $s): string { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

$pdo = pdo();

$periodLabels = ['3M' => 'Cada 3 meses', '6M' => 'Cada 6 meses', '1Y' => 'Anual'];

// --- CSV Download ---
if (isset($_GET['download']) && $_GET['download'] === 'csv') {
    $filename = 'reporte_mantenimientos_' . date('Y-m-d') . '.csv';

    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');

    $out = fopen('php://output', 'w');
    fprintf($out, chr(0xEF).chr(0xBB).chr(0xBF)); // BOM para Excel

    fputcsv($out, ['ID', 'Descripción', 'Marca', 'Modelo', 'No. Serie', 'Ubicación',
                   'Período', 'Último Mant.', 'Próximo Mant.', 'Estado', 'Días restantes', 'Comentarios']);

    $sql = "
        SELECT
            ID, Description, Brand, Model, SerialNumber, Location,
            MaintPeriod, LastMaintDate, NextMaintDate, Comments,
            CASE
                WHEN CURRENT_DATE() > NextMaintDate THEN 'Vencido'
                WHEN NextMaintDate BETWEEN CURRENT_DATE() AND DATE_ADD(CURRENT_DATE(), INTERVAL 30 DAY) THEN 'Próximo mantenimiento'
                ELSE 'Al corriente'
            END AS status_calculado,
            DATEDIFF(NextMaintDate, CURRENT_DATE()) AS days_left
        FROM mant_equipos
        ORDER BY NextMaintDate ASC
    ";

    $stm = $pdo->query($sql);
    while ($r = $stm->fetch(PDO::FETCH_ASSOC)) {
        fputcsv($out, [
            $r['ID'],
            $r['Description'],
            $r['Brand'],
            $r['Model'],
            $r['SerialNumber'],
            $r['Location'],
            $periodLabels[$r['MaintPeriod']] ?? $r['MaintPeriod'],
            $r['LastMaintDate'],
            $r['NextMaintDate'],
            $r['status_calculado'],
            $r['days_left'],
            $r['Comments'],
        ]);
    }

    fclose($out);
    exit;
}

// --- Datos para gráficas y tabla ---

// Gráfica de pastel: equipos por estado
$pie_labels = []; $pie_data = []; $pie_colors = [];
$sql1 = "
    SELECT status_calculado, COUNT(*) AS cnt FROM (
        SELECT CASE
            WHEN CURRENT_DATE() > NextMaintDate THEN 'Vencido'
            WHEN NextMaintDate BETWEEN CURRENT_DATE() AND DATE_ADD(CURRENT_DATE(), INTERVAL 30 DAY) THEN 'Próximo mantenimiento'
            ELSE 'Al corriente'
        END AS status_calculado
        FROM mant_equipos
    ) sub
    GROUP BY status_calculado
";
foreach ($pdo->query($sql1) as $r) {
    $lbl = $r['status_calculado'];
    $pie_labels[] = $lbl;
    $pie_data[]   = (int)$r['cnt'];
    if ($lbl === 'Vencido')               $pie_colors[] = '#ff6384';
    elseif ($lbl === 'Próximo mantenimiento') $pie_colors[] = '#ffcd56';
    else                                  $pie_colors[] = '#36a2eb'; // Al corriente
}

// Gráfica de barras: próximos 12 meses (vencimientos de mantenimiento)
$pending_labels = [];
for ($i = 0; $i < 12; $i++) { $pending_labels[] = date('Y-m', strtotime("+{$i} months")); }

$counts = [];
foreach ($pdo->query("
    SELECT DATE_FORMAT(NextMaintDate, '%Y-%m') AS mes, COUNT(*) AS cnt
    FROM mant_equipos
    WHERE NextMaintDate >= CURRENT_DATE()
      AND NextMaintDate < DATE_ADD(CURRENT_DATE(), INTERVAL 12 MONTH)
    GROUP BY mes ORDER BY mes
") as $r) { $counts[$r['mes']] = (int)$r['cnt']; }
$pending_data = array_map(fn($m) => $counts[$m] ?? 0, $pending_labels);

// Gráfica de dona: distribución por período
$period_labels = []; $period_data = []; $period_colors = [];
foreach ($pdo->query("
    SELECT MaintPeriod, COUNT(*) AS cnt
    FROM mant_equipos
    GROUP BY MaintPeriod
    ORDER BY MaintPeriod
") as $r) {
    $period_labels[] = $periodLabels[$r['MaintPeriod']] ?? $r['MaintPeriod'];
    $period_data[]   = (int)$r['cnt'];
    if ($r['MaintPeriod'] === '3M')      $period_colors[] = '#4bc0c0';
    elseif ($r['MaintPeriod'] === '6M')  $period_colors[] = '#9966ff';
    else                                 $period_colors[] = '#ff9f40';
}

// Tabla completa ordenada por próximo mantenimiento
$rows2 = $pdo->query("
    SELECT ID, Description, Brand, Model, SerialNumber, Location,
        MaintPeriod, LastMaintDate, NextMaintDate, Comments,
        CASE
            WHEN CURRENT_DATE() > NextMaintDate THEN 'Vencido'
            WHEN NextMaintDate BETWEEN CURRENT_DATE() AND DATE_ADD(CURRENT_DATE(), INTERVAL 30 DAY) THEN 'Próximo mantenimiento'
            ELSE 'Al corriente'
        END AS status_calculado,
        DATEDIFF(NextMaintDate, CURRENT_DATE()) AS days_left
    FROM mant_equipos
    ORDER BY NextMaintDate ASC
")->fetchAll();

// KPIs de resumen
$kpi = $pdo->query("
    SELECT
        COUNT(*) AS total,
        SUM(CASE WHEN CURRENT_DATE() > NextMaintDate THEN 1 ELSE 0 END) AS vencidos,
        SUM(CASE WHEN NextMaintDate BETWEEN CURRENT_DATE() AND DATE_ADD(CURRENT_DATE(), INTERVAL 30 DAY) THEN 1 ELSE 0 END) AS proximos,
        SUM(CASE WHEN CURRENT_DATE() <= NextMaintDate AND NOT (NextMaintDate BETWEEN CURRENT_DATE() AND DATE_ADD(CURRENT_DATE(), INTERVAL 30 DAY)) THEN 1 ELSE 0 END) AS al_corriente
    FROM mant_equipos
")->fetch();
?>
<?php include __DIR__.'/partials/header.php'; ?>

<style>
.chart-box {
    position: relative;
    height: 260px;
}
@media (min-width: 992px) {
    .chart-box { height: 280px; }
}
.kpi-card {
    background: rgba(255,255,255,0.03);
    border: 1px solid rgba(255,255,255,0.08);
    border-radius: 14px;
    padding: 1.25rem;
    text-align: center;
    transition: all 0.25s ease;
}
.kpi-card:hover {
    transform: translateY(-3px);
    border-color: rgba(255,255,255,0.15);
    box-shadow: 0 8px 24px rgba(0,0,0,0.25);
}
.kpi-number {
    font-size: 2.25rem;
    font-weight: 700;
    line-height: 1;
    margin-bottom: 0.35rem;
}
.kpi-label {
    font-size: 0.8rem;
    text-transform: uppercase;
    letter-spacing: 0.07em;
    color: #8b92a7;
}
</style>

<div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
  <h1 class="h4 m-0"><i class="fa fa-gears me-2"></i>Reporte de Mantenimientos de Maquinaria</h1>
  <div class="d-flex gap-2">
    <a href="mant_equipos_report.php?download=csv" class="btn btn-success btn-sm">
      <i class="fa fa-file-csv me-1"></i>Descargar CSV
    </a>
    <a href="mant_equipos_admin.php" class="btn btn-outline-secondary btn-sm">
      <i class="fa fa-arrow-left me-1"></i>Volver al panel
    </a>
  </div>
</div>

<!-- KPIs -->
<div class="row g-3 mb-4">
  <div class="col-6 col-md-3">
    <div class="kpi-card">
      <div class="kpi-number text-white"><?= (int)($kpi['total'] ?? 0) ?></div>
      <div class="kpi-label">Total equipos</div>
    </div>
  </div>
  <div class="col-6 col-md-3">
    <div class="kpi-card">
      <div class="kpi-number text-success"><?= (int)($kpi['al_corriente'] ?? 0) ?></div>
      <div class="kpi-label">Al corriente</div>
    </div>
  </div>
  <div class="col-6 col-md-3">
    <div class="kpi-card">
      <div class="kpi-number text-warning"><?= (int)($kpi['proximos'] ?? 0) ?></div>
      <div class="kpi-label">Próximos (&lt;30 días)</div>
    </div>
  </div>
  <div class="col-6 col-md-3">
    <div class="kpi-card">
      <div class="kpi-number text-danger"><?= (int)($kpi['vencidos'] ?? 0) ?></div>
      <div class="kpi-label">Vencidos</div>
    </div>
  </div>
</div>

<!-- Gráficas -->
<div class="row g-3 mb-4">
  <div class="col-md-4">
    <div class="card p-3">
      <h2 class="h6 mb-3">Equipos por estado</h2>
      <div class="chart-box">
        <canvas id="pieChart"></canvas>
      </div>
    </div>
  </div>
  <div class="col-md-4">
    <div class="card p-3">
      <h2 class="h6 mb-3">Mantenimientos por mes (próx. 12 meses)</h2>
      <div class="chart-box">
        <canvas id="barChart"></canvas>
      </div>
    </div>
  </div>
  <div class="col-md-4">
    <div class="card p-3">
      <h2 class="h6 mb-3">Distribución por período</h2>
      <div class="chart-box">
        <canvas id="donutChart"></canvas>
      </div>
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

    <select class="form-select dt-density" style="max-width:180px;">
      <option value="comfort">Densidad: cómoda</option>
      <option value="compact" selected>Densidad: compacta</option>
    </select>

    <select class="form-select dt-rows-per-page" style="max-width:160px;">
      <option value="10">10 por página</option>
      <option value="20">20 por página</option>
      <option value="50" selected>50 por página</option>
      <option value="100">100 por página</option>
    </select>

    <div class="dropdown">
      <button class="btn btn-outline-secondary dropdown-toggle" data-bs-toggle="dropdown">Columnas</button>
      <div class="dropdown-menu dropdown-menu-dark p-2 colvis-menu">
        <?php
        $cols = ['ID','Descripción','Marca','Modelo','Ubicación','Período','Último Mant.','Próximo','Estado','Días'];
        foreach ($cols as $i => $c): ?>
          <label class="dropdown-item d-flex align-items-center gap-2">
            <input class="form-check-input me-2" type="checkbox" data-col="<?= $i ?>" checked>
            <span><?= h($c) ?></span>
          </label>
        <?php endforeach; ?>
      </div>
    </div>

    <div class="ms-auto d-flex gap-2">
      <select class="form-select dt-filter" data-col="8" style="max-width:230px;">
        <option value="">Estado: todos</option>
        <option>Al corriente</option>
        <option>Próximo mantenimiento</option>
        <option>Vencido</option>
      </select>
    </div>
  </div>

  <div class="table-wrap">
    <div class="table-scroll">
      <table class="table table-striped table-hover align-middle">
        <thead>
          <tr>
            <th class="th-sort" data-sort="text">ID <span class="sort-ind">▲▼</span></th>
            <th class="th-sort" data-sort="text">Descripción <span class="sort-ind">▲▼</span></th>
            <th class="th-sort" data-sort="text">Marca <span class="sort-ind">▲▼</span></th>
            <th class="th-sort" data-sort="text">Modelo <span class="sort-ind">▲▼</span></th>
            <th class="th-sort" data-sort="text">Ubicación <span class="sort-ind">▲▼</span></th>
            <th class="th-sort" data-sort="text">Período <span class="sort-ind">▲▼</span></th>
            <th class="th-sort" data-sort="date">Último Mant. <span class="sort-ind">▲▼</span></th>
            <th class="th-sort" data-sort="date">Próximo <span class="sort-ind">▲▼</span></th>
            <th class="th-sort" data-sort="text">Estado <span class="sort-ind">▲▼</span></th>
            <th class="th-sort" data-sort="num">Días <span class="sort-ind">▲▼</span></th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($rows2 as $r2):
            $estado = (string)$r2['status_calculado'];
            $badge  = $estado === 'Vencido' ? 'badge-ven' : ($estado === 'Próximo mantenimiento' ? 'badge-prox' : 'badge-cal');
            $pLabel = $periodLabels[$r2['MaintPeriod']] ?? $r2['MaintPeriod'];
          ?>
          <tr>
            <td><?= h($r2['ID']) ?></td>
            <td><?= h($r2['Description']) ?></td>
            <td><?= h($r2['Brand']) ?></td>
            <td><?= h($r2['Model']) ?></td>
            <td><?= h($r2['Location']) ?></td>
            <td><?= h($pLabel) ?></td>
            <td><?= h($r2['LastMaintDate']) ?></td>
            <td><?= h($r2['NextMaintDate']) ?></td>
            <td><span class="badge badge-state <?= $badge ?>"><?= h($estado) ?></span></td>
            <td><?= h((string)$r2['days_left']) ?></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>

  <div class="d-flex justify-content-between align-items-center mt-2">
    <small class="text-secondary">Tip: usa la lupa para filtrar rápidamente.</small>
    <div class="dt-pager"></div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chartjs-plugin-datalabels@2"></script>
<script>
Chart.register(ChartDataLabels);

// Gráfica de pastel — estado
new Chart(document.getElementById('pieChart'), {
  type: 'pie',
  data: {
    labels: <?= json_encode($pie_labels) ?>,
    datasets: [{
      data: <?= json_encode($pie_data) ?>,
      backgroundColor: <?= json_encode($pie_colors) ?>
    }]
  },
  options: {
    responsive: true,
    maintainAspectRatio: false,
    plugins: {
      legend: { position: 'bottom', labels: { color: '#e0e0e0' } },
      datalabels: {
        color: '#fff',
        formatter: (v, ctx) => {
          const d = ctx.chart.data.datasets[0].data, s = d.reduce((a,b) => a+b, 0) || 1;
          return v + ' (' + ((v*100/s).toFixed(1)) + '%)';
        },
        font: { weight: 'bold', size: 13 }
      }
    }
  }
});

// Gráfica de barras — próximos 12 meses
new Chart(document.getElementById('barChart'), {
  type: 'bar',
  data: {
    labels: <?= json_encode($pending_labels) ?>,
    datasets: [{ label: 'Mantenimientos', data: <?= json_encode($pending_data) ?>, backgroundColor: '#36a2eb' }]
  },
  options: {
    responsive: true,
    maintainAspectRatio: false,
    scales: {
      x: { ticks: { color: '#e0e0e0' } },
      y: { ticks: { color: '#e0e0e0' }, beginAtZero: true }
    },
    plugins: {
      legend: { labels: { color: '#e0e0e0' } },
      datalabels: { display: false }
    }
  }
});

// Gráfica de dona — distribución por período
new Chart(document.getElementById('donutChart'), {
  type: 'doughnut',
  data: {
    labels: <?= json_encode($period_labels) ?>,
    datasets: [{
      data: <?= json_encode($period_data) ?>,
      backgroundColor: <?= json_encode($period_colors) ?>
    }]
  },
  options: {
    responsive: true,
    maintainAspectRatio: false,
    plugins: {
      legend: { position: 'bottom', labels: { color: '#e0e0e0' } },
      datalabels: {
        color: '#fff',
        formatter: (v, ctx) => {
          const d = ctx.chart.data.datasets[0].data, s = d.reduce((a,b) => a+b, 0) || 1;
          return v + ' (' + ((v*100/s).toFixed(1)) + '%)';
        },
        font: { weight: 'bold', size: 13 }
      }
    }
  }
});
</script>

<?php include __DIR__.'/partials/footer.php'; ?>
