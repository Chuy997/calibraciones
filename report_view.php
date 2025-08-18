<?php
// /var/www/html/calibraciones/report_view.php
declare(strict_types=1);

require_once __DIR__.'/config.php';
require_auth(); // consulta o admin

function h(?string $s): string { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
$pdo = pdo();

// Distribución por estado
$pie_labels=[]; $pie_data=[];
foreach ($pdo->query("
  SELECT status_calculado, COUNT(*) AS cnt FROM (
    SELECT CASE
      WHEN CURRENT_DATE() > DueDate THEN 'Vencido'
      WHEN DueDate BETWEEN CURRENT_DATE() AND DATE_ADD(CURRENT_DATE(), INTERVAL 30 DAY) THEN 'Próxima calibración'
      ELSE 'Calibrado'
    END AS status_calculado
    FROM instruments
  ) sub
  GROUP BY status_calculado
") as $r){
  $pie_labels[]=$r['status_calculado'];
  $pie_data[]=(int)$r['cnt'];
}

// Próximos vencimientos
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

// Pendientes por mes (12 meses)
$pending_labels=[]; for ($i=0;$i<12;$i++) $pending_labels[] = date('Y-m', strtotime("+{$i} months"));
$counts=[]; foreach ($pdo->query("
  SELECT DATE_FORMAT(DueDate, '%Y-%m') AS mes, COUNT(*) AS cnt
  FROM instruments
  WHERE DueDate >= CURRENT_DATE() AND DueDate < DATE_ADD(CURRENT_DATE(), INTERVAL 12 MONTH)
  GROUP BY mes ORDER BY mes
") as $r){ $counts[$r['mes']] = (int)$r['cnt']; }
$pending_data = array_map(fn($m)=>$counts[$m]??0, $pending_labels);
?>
<?php include __DIR__.'/partials/header.php'; ?>

<style>
/* Igual que report.php: gráficas ligeramente más pequeñas */
.chart-box{
  position: relative;
  height: 260px;
}
@media (min-width: 992px){
  .chart-box{ height: 280px; }
}
</style>

<div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
  <h1 class="h4 m-0">Reportes (solo lectura)</h1>
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
    <select class="form-select dt-rows-per-page" style="max-width:160px;">
      <option value="10">10 por página</option>
      <option value="20">20 por página</option>
      <option value="50"selected>50 por página</option>
    </select>
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
    <small class="text-secondary">Solo lectura.</small>
    <div class="dt-pager"></div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chartjs-plugin-datalabels@2"></script>
<script>
Chart.register(ChartDataLabels);

// Igual que en report.php: mantener proporciones y alturas controladas por .chart-box
new Chart(document.getElementById('pieChart'), {
  type: 'pie',
  data: {
    labels: <?= json_encode($pie_labels) ?>,
    datasets: [{ data: <?= json_encode($pie_data) ?> }]
  },
  options: {
    responsive: true,
    maintainAspectRatio: false,
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
    maintainAspectRatio:false,
    scales:{
      x:{ ticks:{ color:'#e0e0e0' } },
      y:{ ticks:{ color:'#e0e0e0' }, beginAtZero:true }
    },
    plugins:{ legend:{ labels:{ color:'#e0e0e0' } } }
  }
});
</script>

<script>
// Mini datatable (solo lectura)
(function(){
  const container = document.querySelector('.dt-container');
  if (!container) return;
  const table   = container.querySelector('table');
  const tbody   = table.tBodies[0];
  const search  = container.querySelector('.dt-search');
  const rowsSel = container.querySelector('.dt-rows-per-page');
  const pagerEl = container.querySelector('.dt-pager');
  const filter  = container.querySelector('.dt-filter');

  let sortCol = 0, sortDir = 1;
  table.querySelectorAll('th.th-sort').forEach((th,idx)=>{
    th.addEventListener('click', ()=>{
      const type = th.dataset.sort || 'text';
      sortCol = idx; sortDir *= -1;
      const rows = Array.from(tbody.rows);
      rows.sort((a,b)=>{
        const A = a.cells[sortCol].innerText.trim();
        const B = b.cells[sortCol].innerText.trim();
        if (type==='num') return (parseFloat(A)||0 - (parseFloat(B)||0))*sortDir;
        if (type==='date') return (new Date(A) - new Date(B))*sortDir;
        return A.localeCompare(B, undefined, {numeric:true}) * sortDir;
      });
      rows.forEach(r=>tbody.appendChild(r));
      paginate(); // mantener paginación coherente
    });
  });

  function applyFilters(){
    const q = (search?.value || '').toLowerCase();
    const state = (filter?.value || '').toLowerCase();
    Array.from(tbody.rows).forEach(tr=>{
      const textOk = tr.innerText.toLowerCase().includes(q);
      const stateOk = !state || tr.cells[7].innerText.toLowerCase().includes(state);
      tr.style.display = (textOk && stateOk) ? '' : 'none';
    });
    paginate();
  }
  search?.addEventListener('input', applyFilters);
  filter?.addEventListener('change', applyFilters);

  let page = 1;
  function paginate(){
    const per  = parseInt(rowsSel.value,10);
    const visi = Array.from(tbody.rows).filter(r=>r.style.display!=='none');
    const pages= Math.max(1, Math.ceil(visi.length/per));
    page = Math.min(page, pages);
    Array.from(tbody.rows).forEach(tr=> tr.classList.add('d-none'));
    visi.forEach((tr,i)=> tr.classList.toggle('d-none', !(i>=(page-1)*per && i<page*per)));
    pagerEl.innerHTML = '';
    for (let p=1;p<=pages;p++){
      const btn = document.createElement('button');
      btn.className = 'btn btn-sm '+(p===page?'btn-primary':'btn-outline-secondary');
      btn.textContent = p;
      btn.addEventListener('click', ()=>{ page=p; paginate(); });
      pagerEl.appendChild(btn);
    }
  }
  rowsSel?.addEventListener('change', ()=>{ page=1; paginate(); });
  applyFilters();
})();
</script>

<?php include __DIR__.'/partials/footer.php'; ?>
