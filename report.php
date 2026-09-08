<?php
// /var/www/html/calibraciones/report.php
declare(strict_types=1);

require_once __DIR__.'/config.php';
require_auth('admin');

function h(?string $s): string { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

$pdo = pdo();

// --- CSV de Todos los Instrumentos (Optimizado) ---
if (isset($_GET['download']) && $_GET['download'] === 'csv') {
  // Nombre del archivo con fecha
  $filename = 'inventario_instrumentos_' . date('Y-m-d') . '.csv';
  
  header('Content-Type: text/csv; charset=UTF-8');
  header('Content-Disposition: attachment; filename="' . $filename . '"');
  
  // Abrir salida
  $out = fopen('php://output', 'w');
  
  // BOM para Excel
  fprintf($out, chr(0xEF).chr(0xBB).chr(0xBF));
  
  // Encabezados
  fputcsv($out, ['ID', 'Descripción', 'Marca', 'Modelo', 'No. Serie', 'Certificado', 'Fecha Cal.', 'Vencimiento', 'Estado', 'Comentarios']);
  
  // Query optimizada (todos los instrumentos)
  $sql = "
    SELECT 
      ID, Description, Brand, Model, SerialNumber, CertificateNo,
      CalDate, DueDate,
      CASE
          WHEN CURRENT_DATE() > DueDate THEN 'Vencido'
          WHEN DueDate BETWEEN CURRENT_DATE() AND DATE_ADD(CURRENT_DATE(), INTERVAL 30 DAY) THEN 'Próxima calibración'
          ELSE 'Calibrado'
      END AS status_calculado,
      Comments
    FROM instruments
    ORDER BY ID ASC
  ";
  
  // Streaming directo
  $stm = $pdo->query($sql);
  while ($r = $stm->fetch(PDO::FETCH_ASSOC)) {
    fputcsv($out, [
      $r['ID'],
      $r['Description'],
      $r['Brand'],
      $r['Model'],
      $r['SerialNumber'],
      $r['CertificateNo'],
      $r['CalDate'],
      $r['DueDate'],
      $r['status_calculado'],
      $r['Comments']
    ]);
  }
  
  fclose($out);
  exit;
}

// --- Datos para gráficas y tabla ---
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
  if ($lbl === 'Vencido')                       $pie_colors[] = '#ff6384'; // Rojo
  elseif ($lbl === 'Próxima calibración')       $pie_colors[] = '#ffcd56'; // Amarillo
  elseif ($lbl === 'En proceso de calibracion') $pie_colors[] = '#a855f7'; // Morado
  else                                          $pie_colors[] = '#36a2eb'; // Azul (Calibrado)
}

$rows2 = $pdo->query("
  SELECT ID, Description, Brand, Model, SerialNumber, CalDate, DueDate,
    Status AS status_bd,
    CASE
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

// KPIs de resumen
// Los instrumentos 'en proceso de calibracion' NO se cuentan en vencidos/proximos/calibrados
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
<?php include __DIR__.'/partials/header.php'; ?>

<style>
/* Hacer las gráficas un poco más pequeñas */
.chart-box{
  position: relative;
  height: 260px;   /* antes: implícito (más alto). Ahora ligeramente menor */
}
@media (min-width: 992px){
  .chart-box{ height: 280px; } /* en pantallas grandes, un pelín más alto */
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
  <h1 class="h4 m-0">Reportes de calibraciones</h1>
  <div class="d-flex gap-2">
    <a href="report.php?download=csv" class="btn btn-success btn-sm">
      <i class="fa fa-file-csv me-1"></i> Descargar CSV
    </a>
    <a href="instruments_inventory_print.php?no_img=1" target="_blank" class="btn btn-outline-danger btn-sm">
      <i class="fa fa-file-pdf me-1"></i> PDF (Ligero)
    </a>
    <a href="instruments_inventory_print.php" target="_blank" class="btn btn-danger btn-sm">
      <i class="fa fa-file-pdf me-1"></i> PDF (Completo)
    </a>
  </div>
</div>

<!-- KPIs -->
<div class="row g-3 mb-4">
  <div class="col-6 col-md-3">
    <div class="kpi-card">
      <div class="kpi-number text-white"><?= (int)($kpi['total'] ?? 0) ?></div>
      <div class="kpi-label">Total instrumentos</div>
    </div>
  </div>
  <div class="col-6 col-md-3">
    <div class="kpi-card">
      <div class="kpi-number text-success"><?= (int)($kpi['calibrados'] ?? 0) ?></div>
      <div class="kpi-label">Calibrados</div>
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
  <div class="col-6 col-md-3">
    <div class="kpi-card">
      <div class="kpi-number" style="color:#a855f7"><?= (int)($kpi['en_proceso'] ?? 0) ?></div>
      <div class="kpi-label">En proceso de cal.</div>
    </div>
  </div>
</div>

<div class="row g-3 mb-3">
  <div class="col-md-6">
    <div class="card p-3">
      <h2 class="h6 mb-3">Instrumentos por estado</h2>
      <div class="chart-box">
        <canvas id="pieChart"></canvas>
      </div>
    </div>
  </div>
  <div class="col-md-6">
    <div class="card p-3">
      <h2 class="h6 mb-3">Pendientes por mes</h2>
      <div class="chart-box">
        <canvas id="barChart"></canvas>
      </div>
    </div>
  </div>
</div>

<div class="card p-3 table-density-comfort dt-container">
  <div class="dt-toolbar">
    <div class="input-group" style="max-width:320px;">
      <span class="input-group-text"><i class="fa fa-magnifying-glass"></i></span>
      <input type="text" class="form-control dt-search" placeholder="Buscar…">
    </div>

    <select class="form-select dt-density" style="max-width:180px;">
      <option value="comfort">Densidad: cómoda</option>
      <option value="compact"selected>Densidad: compacta</option>
    </select>

    <select class="form-select dt-rows-per-page" style="max-width:160px;">
      <option value="10">10 por página</option>
      <option value="20">20 por página</option>
      <option value="50"selected>50 por página</option>
      <option value="100">100 por página</option>
    </select>

    <div class="dropdown">
      <button class="btn btn-outline-secondary dropdown-toggle" data-bs-toggle="dropdown">Columnas</button>
      <div class="dropdown-menu dropdown-menu-dark p-2 colvis-menu">
        <?php
        $cols = ['ID','Desc','Marca','Modelo','Serial','Cal.Date','Due.Date','Estado','Días'];
        foreach ($cols as $i=>$c): ?>
          <label class="dropdown-item d-flex align-items-center gap-2">
            <input class="form-check-input me-2" type="checkbox" data-col="<?= $i ?>" checked>
            <span><?= h($c) ?></span>
          </label>
        <?php endforeach; ?>
      </div>
    </div>

    <div class="ms-auto d-flex gap-2">
      <select class="form-select dt-filter" data-col="7" style="max-width:260px;">
        <option value="">Estado BD: todos</option>
        <option>calibrado</option>
        <option>en proceso de calibracion</option>
        <option>fuera de calibracion</option>
      </select>
    </div>
  </div>

  <div class="table-wrap">
    <div class="table-scroll">
      <table class="table table-striped table-hover align-middle">
        <thead>
          <tr>
            <th class="th-sort" data-sort="text">ID <span class="sort-ind">▲▼</span></th>
            <th class="th-sort" data-sort="text">Desc <span class="sort-ind">▲▼</span></th>
            <th class="th-sort" data-sort="text">Marca <span class="sort-ind">▲▼</span></th>
            <th class="th-sort" data-sort="text">Modelo <span class="sort-ind">▲▼</span></th>
            <th class="th-sort" data-sort="text">Serial <span class="sort-ind">▲▼</span></th>
            <th class="th-sort" data-sort="date">Cal.Date <span class="sort-ind">▲▼</span></th>
            <th class="th-sort" data-sort="date">Due.Date <span class="sort-ind">▲▼</span></th>
            <th class="th-sort" data-sort="text">Estado <span class="sort-ind">▲▼</span></th>
            <th class="th-sort" data-sort="num">Días <span class="sort-ind">▲▼</span></th>
            <th>Cambiar Estado</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($rows2 as $r2):
            $statusBd2   = (string)($r2['status_bd'] ?? '');
            $statusCal2  = (string)($r2['status_calculado'] ?? '');
            // Lógica híbrida: solo 'en proceso' usa el status de la BD;
            // los demás se calculan automáticamente por fecha
            if ($statusBd2 === 'en proceso de calibracion') {
              $displayLabel2 = 'En proceso de cal.';
              $displayVal2   = 'en proceso de calibracion';
              $displayCls2   = 'badge-proc';
              $displayIcon2  = 'fa-rotate';
            } else {
              // Estado automático por fechas
              $autoMap2 = [
                'Vencido'             => ['cls'=>'badge-ven',  'icon'=>'fa-circle-xmark'],
                'Próxima calibración' => ['cls'=>'badge-prox', 'icon'=>'fa-clock'],
                'Calibrado'           => ['cls'=>'badge-cal',  'icon'=>'fa-circle-check'],
              ];
              $ai2 = $autoMap2[$statusCal2] ?? ['cls'=>'badge-cal', 'icon'=>'fa-circle-question'];
              $displayLabel2 = $statusCal2;
              $displayVal2   = $statusCal2;
              $displayCls2   = $ai2['cls'];
              $displayIcon2  = $ai2['icon'];
            }
          ?>
          <tr>
            <td><?= h($r2['ID']) ?></td>
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
            <td><?= h((string)$r2['days_left']) ?></td>
            <td>
              <div class="dropdown">
                <button class="btn btn-sm btn-outline-secondary dropdown-toggle status-btn"
                        type="button" data-bs-toggle="dropdown" aria-expanded="false">
                  <i class="fa fa-sliders"></i>
                </button>
                <ul class="dropdown-menu dropdown-menu-dark shadow status-menu"
                    data-id="<?= h((string)$r2['ID']) ?>"
                    data-csrf="<?= h(csrf_token()) ?>">
                  <li><h6 class="dropdown-header">Cambiar a:</h6></li>
                  <li>
                    <button class="dropdown-item d-flex align-items-center gap-2 status-option"
                            data-val="calibrado">
                      <i class="fa fa-circle-check text-success"></i>Calibrado
                    </button>
                  </li>
                  <li>
                    <button class="dropdown-item d-flex align-items-center gap-2 status-option"
                            data-val="en proceso de calibracion">
                      <i class="fa fa-rotate text-warning"></i>En proceso de calibración
                    </button>
                  </li>
                  <li>
                    <button class="dropdown-item d-flex align-items-center gap-2 status-option"
                            data-val="fuera de calibracion">
                      <i class="fa fa-circle-xmark text-danger"></i>Fuera de calibración
                    </button>
                  </li>
                </ul>
              </div>
            </td>
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
    maintainAspectRatio: false, // permite respetar la altura de .chart-box (más pequeño)
    plugins:{
      legend:{ position:'bottom', labels:{ color:'#e0e0e0' } },
      datalabels:{
        color:'#fff',
        formatter:(v,ctx)=>{
          const d=ctx.chart.data.datasets[0].data, s=d.reduce((a,b)=>a+b,0)||1;
          return v+' ('+((v*100/s).toFixed(1))+'%)';
        },
        font:{ weight:'bold', size:14 }
      }
    }
  }
});

new Chart(document.getElementById('barChart'), {
  type:'bar',
  data:{
    labels: <?= json_encode($pending_labels) ?>,
    datasets:[{ label:'Pendientes', data: <?= json_encode($pending_data) ?> }]
  },
  options:{
    responsive:true,
    maintainAspectRatio:false, // altura controlada por .chart-box
    scales:{
      x:{ ticks:{ color:'#e0e0e0' } },
      y:{ ticks:{ color:'#e0e0e0' }, beginAtZero:true }
    },
    plugins:{ legend:{ labels:{ color:'#e0e0e0' } } }
  }
});
</script>

<style>
/* Badge de En proceso */
.badge-proc {
  background: linear-gradient(135deg, #6f42c1 0%, #a855f7 100%);
  color: #fff;
}
/* Animación fadeIn para toasts */
@keyframes fadeInUp {
  from { opacity:0; transform:translateY(10px); }
  to   { opacity:1; transform:translateY(0); }
}
</style>

<script>
// ===== CAMBIO DE STATUS VÍA AJAX EN REPORT =====
const REPORT_STATUS_LABELS = {
  'calibrado':                 { label: 'calibrado',               cls: 'badge-cal',  icon: 'fa-circle-check' },
  'fuera de calibracion':      { label: 'fuera de calibracion',    cls: 'badge-ven',  icon: 'fa-circle-xmark' },
  'en proceso de calibracion': { label: 'en proceso de calibracion', cls: 'badge-proc', icon: 'fa-rotate' },
};

document.addEventListener('click', async (e) => {
  const btn = e.target.closest('.status-option');
  if (!btn) return;

  const menu   = btn.closest('.status-menu');
  const row    = btn.closest('tr');
  const id     = menu?.dataset.id;
  const csrf   = menu?.dataset.csrf;
  const status = btn.dataset.val;

  if (!id || !status) return;

  btn.disabled = true;
  btn.innerHTML = '<i class="fa fa-spinner fa-spin me-2"></i>Guardando…';

  try {
    const body = new URLSearchParams({ id, status, csrf });
    const res  = await fetch('update_status.php', { method: 'POST', body });
    const data = await res.json();

    if (data.ok) {
      const info = REPORT_STATUS_LABELS[status] ?? { label: status, cls: 'badge-cal', icon: 'fa-circle-question' };
      const badgeEl = row?.querySelector('.badge-state');
      if (badgeEl) {
        badgeEl.classList.remove('badge-cal', 'badge-ven', 'badge-proc', 'badge-prox');
        badgeEl.classList.add(info.cls);
        badgeEl.innerHTML = `<i class="fa ${info.icon} me-1"></i>${info.label}`;
      }
      showReportToast('Estado actualizado: ' + info.label, 'success');
    } else {
      showReportToast('Error: ' + (data.error ?? 'Inténtalo de nuevo.'), 'danger');
    }
  } catch (err) {
    showReportToast('Error de red.', 'danger');
  } finally {
    btn.disabled = false;
    const valInfo = REPORT_STATUS_LABELS[status] ?? { label: status, icon: 'fa-circle-question' };
    btn.innerHTML = `<i class="fa ${valInfo.icon}"></i>${valInfo.label}`;
    const ddEl = menu?.closest('.dropdown');
    if (ddEl) bootstrap.Dropdown.getInstance(ddEl.querySelector('[data-bs-toggle="dropdown"]'))?.hide();
  }
});

function showReportToast(msg, type) {
  let container = document.getElementById('toastContainerReport');
  if (!container) {
    container = document.createElement('div');
    container.id = 'toastContainerReport';
    container.style.cssText = 'position:fixed;bottom:1.5rem;right:1.5rem;z-index:9999;display:flex;flex-direction:column;gap:.5rem;';
    document.body.appendChild(container);
  }
  const toast = document.createElement('div');
  toast.className = `alert alert-${type} shadow d-flex align-items-center gap-2 py-2 px-3 mb-0`;
  toast.style.cssText = 'min-width:240px;border-radius:10px;font-size:.9rem;animation:fadeInUp .3s ease;';
  toast.innerHTML = `<i class="fa fa-${type==='success'?'circle-check':'circle-xmark'}"></i>${msg}`;
  container.appendChild(toast);
  setTimeout(() => toast.remove(), 3500);
}
</script>

<?php include __DIR__.'/partials/footer.php'; ?>
