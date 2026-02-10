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
$sql1 = "
  SELECT status_calculado, COUNT(*) AS cnt FROM (
    SELECT CASE
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
  // Colores modernos y suaves (Pastel/Chart.js style)
  if ($lbl === 'Vencido') $pie_colors[] = '#ff6384'; // Rojo suave
  elseif ($lbl === 'Próxima calibración') $pie_colors[] = '#ffcd56'; // Amarillo suave
  else $pie_colors[] = '#36a2eb'; // Azul suave (Calibrado)
}

$rows2 = $pdo->query("
  SELECT ID, Description, Brand, Model, SerialNumber, CalDate, DueDate,
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
</style>

<div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
  <h1 class="h4 m-0">Reportes de calibraciones</h1>
  <div class="d-flex gap-2">
    <a href="report.php?download=csv" class="btn btn-success btn-sm">
      <i class="fa fa-file-csv me-1"></i> Descargar CSV
    </a>
    <a href="instruments_inventory_print.php" target="_blank" class="btn btn-danger btn-sm">
      <i class="fa fa-file-pdf me-1"></i> PDF / Imprimir
    </a>
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
      <select class="form-select dt-filter" data-col="7" style="max-width:220px;">
        <option value="">Estado: todos</option>
        <option>Calibrado</option>
        <option>Próxima calibración</option>
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
            <th class="th-sort" data-sort="text">Desc <span class="sort-ind">▲▼</span></th>
            <th class="th-sort" data-sort="text">Marca <span class="sort-ind">▲▼</span></th>
            <th class="th-sort" data-sort="text">Modelo <span class="sort-ind">▲▼</span></th>
            <th class="th-sort" data-sort="text">Serial <span class="sort-ind">▲▼</span></th>
            <th class="th-sort" data-sort="date">Cal.Date <span class="sort-ind">▲▼</span></th>
            <th class="th-sort" data-sort="date">Due.Date <span class="sort-ind">▲▼</span></th>
            <th class="th-sort" data-sort="text">Estado <span class="sort-ind">▲▼</span></th>
            <th class="th-sort" data-sort="num">Días <span class="sort-ind">▲▼</span></th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($rows2 as $r2):
            $estado = (string)$r2['status_calculado'];
            $badge = $estado==='Vencido' ? 'badge-ven' : ($estado==='Próxima calibración' ? 'badge-prox' : 'badge-cal');
          ?>
          <tr>
            <td><?= h($r2['ID']) ?></td>
            <td><?= h($r2['Description']) ?></td>
            <td><?= h($r2['Brand']) ?></td>
            <td><?= h($r2['Model']) ?></td>
            <td><?= h($r2['SerialNumber']) ?></td>
            <td><?= h($r2['CalDate']) ?></td>
            <td><?= h($r2['DueDate']) ?></td>
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

<?php include __DIR__.'/partials/footer.php'; ?>
