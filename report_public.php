<?php
// /var/www/html/calibraciones/report_public.php
// Vista pública de solo lectura del reporte de calibraciones.
// NO requiere autenticación. NO permite ninguna acción de edición.
declare(strict_types=1);

// Solo necesitamos pdo() y la zona horaria; NO require_auth
require_once __DIR__ . '/config.php';

function h(?string $s): string { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

$pdo = pdo();

// --- Datos para gráficas y tabla (lógica idéntica a report.php) ---
$pie_labels = []; $pie_data = []; $pie_colors = [];

// La gráfica usa: 'en proceso' si el Status está en BD como tal;
// de lo contrario calcula el estado desde las fechas
$sql1 = "
  SELECT status_calculado, COUNT(*) AS cnt FROM (
    SELECT CASE
      WHEN Status = 'en proceso de calibracion' THEN 'En proceso de calibracion'
      WHEN CURRENT_DATE() > DueDate THEN 'Vencido'
      WHEN DueDate BETWEEN CURRENT_DATE() AND DATE_ADD(CURRENT_DATE(), INTERVAL 30 DAY) THEN 'Próxima calibración'
      ELSE 'Calibrado'
    END AS status_calculado
    FROM instruments
  ) sub
  GROUP BY status_calculado
";
foreach ($pdo->query($sql1) as $r) {
  $lbl = $r['status_calculado'];
  $pie_labels[] = $lbl;
  $pie_data[]   = (int)$r['cnt'];
  if ($lbl === 'Vencido')                       $pie_colors[] = '#ff6384';
  elseif ($lbl === 'Próxima calibración')       $pie_colors[] = '#ffcd56';
  elseif ($lbl === 'En proceso de calibracion') $pie_colors[] = '#a855f7';
  else                                          $pie_colors[] = '#36a2eb';
}

$rows2 = $pdo->query("
  SELECT ID, Description, Brand, Model, SerialNumber, CalDate, DueDate,
    Status AS status_bd,
    CASE
      WHEN Status = 'en proceso de calibracion' THEN 'en proceso de calibracion'
      WHEN CURRENT_DATE() > DueDate THEN 'Vencido'
      WHEN DueDate BETWEEN CURRENT_DATE() AND DATE_ADD(CURRENT_DATE(), INTERVAL 30 DAY) THEN 'Próxima calibración'
      ELSE 'Calibrado'
    END AS status_calculado,
    DATEDIFF(DueDate, CURRENT_DATE()) AS days_left
  FROM instruments
  ORDER BY DueDate ASC
")->fetchAll();

$pending_labels = [];
for ($i=0; $i<12; $i++) { $pending_labels[] = date('Y-m', strtotime("+{$i} months")); }

$counts = [];
foreach ($pdo->query("
  SELECT DATE_FORMAT(DueDate, '%Y-%m') AS mes, COUNT(*) AS cnt
  FROM instruments
  WHERE DueDate >= CURRENT_DATE()
    AND DueDate < DATE_ADD(CURRENT_DATE(), INTERVAL 12 MONTH)
  GROUP BY mes ORDER BY mes
") as $r) { $counts[$r['mes']] = (int)$r['cnt']; }
$pending_data = array_map(fn($m)=>$counts[$m]??0, $pending_labels);

// KPIs — los 'en proceso' se cuentan aparte
$kpi = $pdo->query("
    SELECT
        COUNT(*) AS total,
        SUM(CASE WHEN Status != 'en proceso de calibracion' AND CURRENT_DATE() > DueDate THEN 1 ELSE 0 END) AS vencidos,
        SUM(CASE WHEN Status != 'en proceso de calibracion' AND DueDate BETWEEN CURRENT_DATE() AND DATE_ADD(CURRENT_DATE(), INTERVAL 30 DAY) THEN 1 ELSE 0 END) AS proximos,
        SUM(CASE WHEN Status != 'en proceso de calibracion' AND CURRENT_DATE() <= DueDate AND NOT (DueDate BETWEEN CURRENT_DATE() AND DATE_ADD(CURRENT_DATE(), INTERVAL 30 DAY)) THEN 1 ELSE 0 END) AS calibrados,
        SUM(CASE WHEN Status = 'en proceso de calibracion' THEN 1 ELSE 0 END) AS en_proceso
    FROM instruments
")->fetch();
?>
<!DOCTYPE html>
<html lang="es" data-bs-theme="dark">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Reporte de Calibraciones — Vista Pública</title>
  <link rel="icon" type="image/png" href="/calibraciones/favicon.png?v=3">

  <!-- Bootstrap 5 -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <!-- Font Awesome -->
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" rel="stylesheet">
  <!-- Chart.js -->
  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/chartjs-plugin-datalabels@2"></script>

  <style>
    /* ===== BASE ===== */
    body { background: #0f1117; color: #e0e0e0; font-family: 'Segoe UI', system-ui, sans-serif; }

    /* ===== BANNER DE SOLO LECTURA ===== */
    .readonly-banner {
      background: linear-gradient(135deg, rgba(102,126,234,.15) 0%, rgba(118,75,162,.15) 100%);
      border-bottom: 1px solid rgba(102,126,234,.25);
      padding: .6rem 1.5rem;
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 1rem;
    }
    .readonly-banner .brand {
      font-size: 1.1rem;
      font-weight: 700;
      background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
      -webkit-background-clip: text;
      -webkit-text-fill-color: transparent;
      background-clip: text;
    }
    .readonly-badge {
      font-size: .75rem;
      padding: .3rem .8rem;
      border-radius: 20px;
      background: rgba(255,255,255,.06);
      border: 1px solid rgba(255,255,255,.12);
      color: #adb5bd;
      letter-spacing: .04em;
    }
    .last-updated {
      font-size: .75rem;
      color: #6c757d;
    }

    /* ===== KPI CARDS ===== */
    .kpi-card {
      background: rgba(255,255,255,.03);
      border: 1px solid rgba(255,255,255,.08);
      border-radius: 14px;
      padding: 1.25rem;
      text-align: center;
      transition: all .25s ease;
    }
    .kpi-card:hover {
      transform: translateY(-3px);
      border-color: rgba(255,255,255,.15);
      box-shadow: 0 8px 24px rgba(0,0,0,.25);
    }
    .kpi-number {
      font-size: 2.25rem;
      font-weight: 700;
      line-height: 1;
      margin-bottom: .35rem;
    }
    .kpi-label {
      font-size: .8rem;
      text-transform: uppercase;
      letter-spacing: .07em;
      color: #8b92a7;
    }

    /* ===== CHART BOX ===== */
    .chart-box { position: relative; height: 260px; }
    @media (min-width: 992px) { .chart-box { height: 280px; } }

    /* ===== TABLE ===== */
    .table-scroll { overflow-x: auto; -webkit-overflow-scrolling: touch; }
    .table { --bs-table-bg: transparent; --bs-table-striped-bg: rgba(255,255,255,.03); }
    .table thead th { background: rgba(0,0,0,.3); font-size: .8rem; text-transform: uppercase; letter-spacing: .05em; color: #8b92a7; border-bottom: 1px solid rgba(255,255,255,.08); white-space: nowrap; }
    .table tbody td { vertical-align: middle; border-color: rgba(255,255,255,.05); font-size: .9rem; }

    /* ===== BADGE ESTADOS ===== */
    .badge-state { font-size: .8rem; padding: .35rem .7rem; border-radius: 20px; font-weight: 600; white-space: nowrap; }
    .badge-cal  { background: linear-gradient(135deg,#28a745,#20c997); color:#fff; }
    .badge-ven  { background: linear-gradient(135deg,#dc3545,#bd2130); color:#fff; }
    .badge-prox { background: linear-gradient(135deg,#ffc107,#ff9800); color:#000; }
    .badge-proc { background: linear-gradient(135deg,#6f42c1,#a855f7); color:#fff; }

    /* ===== SEARCH ===== */
    .search-box { max-width: 320px; }
    .form-control { background: #1a1d23; border-color: #3d4249; color: #e0e0e0; }
    .form-control:focus { background: #1f2229; border-color: #667eea; color: #fff; box-shadow: 0 0 0 .2rem rgba(102,126,234,.25); }
    .form-control::placeholder { color: #6c757d; }

    /* ===== FOOTER ===== */
    .public-footer { border-top: 1px solid rgba(255,255,255,.06); color: #6c757d; font-size: .8rem; }

    /* ===== ANIMACIONES ===== */
    @keyframes fadeInUp {
      from { opacity:0; transform:translateY(16px); }
      to   { opacity:1; transform:translateY(0); }
    }
    .animate-in { animation: fadeInUp .5s ease both; }
  </style>
</head>
<body>

<!-- Banner de solo lectura (sin login, sin menú de navegación) -->
<div class="readonly-banner">
  <div class="d-flex align-items-center gap-3">
    <img src="imagenes/calibracion.png" alt="Logo" style="width:28px;height:28px;filter:drop-shadow(0 2px 8px rgba(102,126,234,.4))">
    <span class="brand">Argmand Instruments</span>
    <span class="readonly-badge"><i class="fa fa-eye me-1"></i>Solo lectura</span>
  </div>
  <div class="last-updated text-end">
    <i class="fa fa-clock me-1"></i>Actualizado: <?= date('d/m/Y H:i') ?>
  </div>
</div>

<main class="container-fluid py-4">

  <!-- Título -->
  <div class="d-flex align-items-center gap-3 mb-4 animate-in">
    <i class="fa fa-chart-column fa-2x" style="color:#667eea;"></i>
    <div>
      <h1 class="h4 m-0 fw-bold">Reporte de Calibraciones</h1>
      <small class="text-muted">Vista pública — sin edición</small>
    </div>
  </div>

  <!-- KPIs -->
  <div class="row g-3 mb-4 animate-in" style="animation-delay:.05s">
    <div class="col-6 col-md col-lg">
      <div class="kpi-card">
        <div class="kpi-number text-white"><?= (int)($kpi['total'] ?? 0) ?></div>
        <div class="kpi-label">Total instrumentos</div>
      </div>
    </div>
    <div class="col-6 col-md col-lg">
      <div class="kpi-card">
        <div class="kpi-number text-success"><?= (int)($kpi['calibrados'] ?? 0) ?></div>
        <div class="kpi-label">Calibrados</div>
      </div>
    </div>
    <div class="col-6 col-md col-lg">
      <div class="kpi-card">
        <div class="kpi-number text-warning"><?= (int)($kpi['proximos'] ?? 0) ?></div>
        <div class="kpi-label">Próximos (&lt;30 días)</div>
      </div>
    </div>
    <div class="col-6 col-md col-lg">
      <div class="kpi-card">
        <div class="kpi-number text-danger"><?= (int)($kpi['vencidos'] ?? 0) ?></div>
        <div class="kpi-label">Vencidos</div>
      </div>
    </div>
    <div class="col-6 col-md col-lg">
      <div class="kpi-card">
        <div class="kpi-number" style="color:#a855f7"><?= (int)($kpi['en_proceso'] ?? 0) ?></div>
        <div class="kpi-label">En proceso de cal.</div>
      </div>
    </div>
  </div>

  <!-- Gráficas -->
  <div class="row g-3 mb-4 animate-in" style="animation-delay:.1s">
    <div class="col-md-6">
      <div class="card p-3 h-100" style="background:rgba(255,255,255,.03);border:1px solid rgba(255,255,255,.08);border-radius:14px;">
        <h2 class="h6 mb-3"><i class="fa fa-chart-pie me-2 text-muted"></i>Instrumentos por estado</h2>
        <div class="chart-box">
          <canvas id="pieChart"></canvas>
        </div>
      </div>
    </div>
    <div class="col-md-6">
      <div class="card p-3 h-100" style="background:rgba(255,255,255,.03);border:1px solid rgba(255,255,255,.08);border-radius:14px;">
        <h2 class="h6 mb-3"><i class="fa fa-calendar me-2 text-muted"></i>Pendientes por mes (próximos 12 meses)</h2>
        <div class="chart-box">
          <canvas id="barChart"></canvas>
        </div>
      </div>
    </div>
  </div>

  <!-- Tabla -->
  <div class="card p-3 animate-in" style="animation-delay:.15s;background:rgba(255,255,255,.02);border:1px solid rgba(255,255,255,.07);border-radius:14px;">
    <!-- Toolbar: solo búsqueda y filtro (sin edición) -->
    <div class="d-flex flex-wrap gap-2 align-items-center mb-3">
      <div class="input-group search-box">
        <span class="input-group-text" style="background:#1a1d23;border-color:#3d4249;"><i class="fa fa-magnifying-glass text-muted"></i></span>
        <input type="text" id="searchInput" class="form-control" placeholder="Buscar instrumento…">
      </div>
      <select id="statusFilter" class="form-select form-select-sm" style="max-width:200px;background:#1a1d23;border-color:#3d4249;color:#e0e0e0;">
        <option value="">Estado: todos</option>
        <option value="Calibrado">Calibrado</option>
        <option value="En proceso">En proceso</option>
        <option value="Próxima calibración">Próxima calibración</option>
        <option value="Vencido">Vencido</option>
      </select>
      <small class="text-muted ms-auto d-none d-sm-inline">
        <i class="fa fa-lock me-1"></i>Vista de solo lectura
      </small>
    </div>

    <div class="table-scroll">
      <table class="table table-striped table-hover align-middle" id="mainTable">
        <thead>
          <tr>
            <th>ID</th>
            <th>Descripción</th>
            <th>Marca</th>
            <th>Modelo</th>
            <th>Serie</th>
            <th>Cal. Date</th>
            <th>Due Date</th>
            <th>Estado</th>
            <th>Días restantes</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($rows2 as $r2):
            $statusBd2  = (string)($r2['status_bd'] ?? '');
            $statusCal2 = (string)($r2['status_calculado'] ?? '');

            // Lógica híbrida: solo 'en proceso' es manual; el resto se calcula por fechas
            if ($statusBd2 === 'en proceso de calibracion') {
              $displayLabel2 = 'En proceso de cal.';
              $displayCls2   = 'badge-proc';
              $displayIcon2  = 'fa-rotate';
            } else {
              $autoMap2 = [
                'Vencido'             => ['cls'=>'badge-ven',  'icon'=>'fa-circle-xmark'],
                'Próxima calibración' => ['cls'=>'badge-prox', 'icon'=>'fa-clock'],
                'Calibrado'           => ['cls'=>'badge-cal',  'icon'=>'fa-circle-check'],
              ];
              $ai2 = $autoMap2[$statusCal2] ?? ['cls'=>'badge-cal', 'icon'=>'fa-circle-question'];
              $displayLabel2 = $statusCal2;
              $displayCls2   = $ai2['cls'];
              $displayIcon2  = $ai2['icon'];
            }

            $daysLeft = (int)($r2['days_left'] ?? 0);
            $daysDisplay = $daysLeft < 0
              ? '<span class="text-danger fw-semibold">' . $daysLeft . '</span>'
              : ($daysLeft <= 30
                  ? '<span class="text-warning">' . $daysLeft . '</span>'
                  : $daysLeft);
          ?>
          <tr>
            <td><code class="text-info"><?= h($r2['ID']) ?></code></td>
            <td><?= h($r2['Description']) ?></td>
            <td><?= h($r2['Brand']) ?></td>
            <td><?= h($r2['Model']) ?></td>
            <td><?= h($r2['SerialNumber']) ?></td>
            <td><?= h($r2['CalDate']) ?></td>
            <td><?= h($r2['DueDate']) ?></td>
            <td>
              <span class="badge badge-state <?= $displayCls2 ?>">
                <i class="fa <?= $displayIcon2 ?> me-1"></i><?= h($displayLabel2) ?>
              </span>
            </td>
            <td><?= $daysDisplay ?></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>

    <div class="d-flex justify-content-between align-items-center mt-2 flex-wrap gap-2">
      <small class="text-muted">
        <i class="fa fa-info-circle me-1"></i>
        Datos en tiempo real desde la base de datos.
      </small>
      <small id="rowCount" class="text-muted"></small>
    </div>
  </div>

</main>

<!-- Footer -->
<footer class="public-footer text-center py-3 mt-4">
  <i class="fa fa-shield-halved me-1"></i>
  Argmand Instruments — Reporte de calibraciones &nbsp;|&nbsp;
  <i class="fa fa-eye me-1"></i>Vista pública sin edición
</footer>

<!-- Bootstrap JS (solo para uso futuro; no hay forms ni modals aquí) -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

<script>
Chart.register(ChartDataLabels);

// ===== GRÁFICA PIE =====
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
      legend: { position: 'bottom', labels: { color: '#e0e0e0', padding: 16 } },
      datalabels: {
        color: '#fff',
        formatter: (v, ctx) => {
          const d = ctx.chart.data.datasets[0].data;
          const s = d.reduce((a, b) => a + b, 0) || 1;
          return v + ' (' + ((v * 100 / s).toFixed(1)) + '%)';
        },
        font: { weight: 'bold', size: 13 }
      }
    }
  }
});

// ===== GRÁFICA BAR =====
new Chart(document.getElementById('barChart'), {
  type: 'bar',
  data: {
    labels: <?= json_encode($pending_labels) ?>,
    datasets: [{
      label: 'Pendientes de calibración',
      data: <?= json_encode($pending_data) ?>,
      backgroundColor: 'rgba(102,126,234,.7)',
      borderColor: '#667eea',
      borderWidth: 1,
      borderRadius: 6,
    }]
  },
  options: {
    responsive: true,
    maintainAspectRatio: false,
    scales: {
      x: { ticks: { color: '#e0e0e0' }, grid: { color: 'rgba(255,255,255,.05)' } },
      y: { ticks: { color: '#e0e0e0' }, grid: { color: 'rgba(255,255,255,.05)' }, beginAtZero: true }
    },
    plugins: {
      legend: { labels: { color: '#e0e0e0' } },
      datalabels: { display: false }
    }
  }
});

// ===== BÚSQUEDA EN TABLA =====
const searchInput   = document.getElementById('searchInput');
const statusFilter  = document.getElementById('statusFilter');
const tbody         = document.querySelector('#mainTable tbody');
const rows          = tbody ? Array.from(tbody.querySelectorAll('tr')) : [];
const rowCountEl    = document.getElementById('rowCount');

function filterTable() {
  const term   = searchInput.value.toLowerCase().trim();
  const status = statusFilter.value.toLowerCase().trim();
  let visible  = 0;

  rows.forEach(row => {
    const text       = row.textContent.toLowerCase();
    const badgeText  = (row.querySelector('.badge-state')?.textContent ?? '').toLowerCase();
    const matchTerm  = !term   || text.includes(term);
    const matchStat  = !status || badgeText.includes(status.toLowerCase());

    if (matchTerm && matchStat) {
      row.style.display = '';
      visible++;
    } else {
      row.style.display = 'none';
    }
  });

  if (rowCountEl) {
    rowCountEl.textContent = visible + ' de ' + rows.length + ' registros';
  }
}

searchInput?.addEventListener('input', filterTable);
statusFilter?.addEventListener('change', filterTable);

// Inicializar contador
filterTable();
</script>
</body>
</html>
