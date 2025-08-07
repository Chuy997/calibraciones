<?php
// /var/www/html/calibraciones/report.php

session_start();
/*if (!isset($_SESSION['username'])) {
    header('Location: login.php');
    exit();
}*/

require 'config.php';
$conn = getConnection('consulta');

// --- Descarga CSV de próximos vencimientos ---
if (isset($_GET['download'])) {
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="proximos_vencimientos.csv"');
    echo "ID,Description,Brand,Model,SerialNumber,DueDate,DaysLeft\n";
    $sql2 = "
        SELECT ID, Description, Brand, Model, SerialNumber,
               DueDate, DATEDIFF(DueDate, CURRENT_DATE()) AS days_left
        FROM instruments
        WHERE DueDate >= CURRENT_DATE()
        ORDER BY DueDate ASC
    ";
    $res2dl = $conn->query($sql2);
    while ($r = $res2dl->fetch_assoc()) {
        echo "\"{$r['ID']}\",\"{$r['Description']}\",\"{$r['Brand']}\",\"{$r['Model']}\",\"{$r['SerialNumber']}\",\"{$r['DueDate']}\",\"{$r['days_left']}\"\n";
    }
    exit();
}

// 1) Datos para el pie chart (Estados actuales)
$sql1 = "
    SELECT status_calculado, COUNT(*) AS cnt FROM (
        SELECT
            CASE
                WHEN CURRENT_DATE() > DueDate THEN 'Vencido'
                WHEN DueDate BETWEEN CURRENT_DATE() AND DATE_ADD(CURRENT_DATE(), INTERVAL 30 DAY) THEN 'Próxima calibración'
                ELSE 'Calibrado'
            END AS status_calculado
        FROM instruments
    ) AS sub
    GROUP BY status_calculado
";
$res1 = $conn->query($sql1);
$pie_labels = $pie_data = [];
while ($r = $res1->fetch_assoc()) {
    $pie_labels[] = $r['status_calculado'];
    $pie_data[]   = (int)$r['cnt'];
}

// 2) Próximos vencimientos con estado calculado
$sql2 = "
    SELECT
      ID, Description, Brand, Model, SerialNumber,
      CalDate, DueDate,
      CASE
        WHEN CURRENT_DATE() > DueDate THEN 'Vencido'
        WHEN DueDate BETWEEN CURRENT_DATE() AND DATE_ADD(CURRENT_DATE(), INTERVAL 30 DAY) THEN 'Próxima calibración'
        ELSE 'Calibrado'
      END AS status_calculado,
      DATEDIFF(DueDate, CURRENT_DATE()) AS days_left
    FROM instruments
    ORDER BY DueDate ASC
";
$res2 = $conn->query($sql2);

// 3) Calibraciones pendientes por mes (próximos 12 meses)
$pending_labels = [];
for ($i = 0; $i < 12; $i++) {
    $pending_labels[] = date('Y-m', strtotime("+{$i} months"));
}
$sql3 = "
    SELECT DATE_FORMAT(DueDate, '%Y-%m') AS mes, COUNT(*) AS cnt
    FROM instruments
    WHERE DueDate >= CURRENT_DATE()
      AND DueDate < DATE_ADD(CURRENT_DATE(), INTERVAL 12 MONTH)
    GROUP BY mes
    ORDER BY mes
";
$res3 = $conn->query($sql3);
$pending_counts = [];
while ($r = $res3->fetch_assoc()) {
    $pending_counts[$r['mes']] = (int)$r['cnt'];
}
$pending_data = [];
foreach ($pending_labels as $mes) {
    $pending_data[] = $pending_counts[$mes] ?? 0;
}
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Reportes de Calibraciones</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <style>
    body { background:#121212; color:#e0e0e0; }
    .container { margin-top:20px; }
    h1, h2 { color:#fff; }
    .card { background:#1e1e1e; border:none; }

    /* Tabla texto blanco */
    table { color: #ffffff; }
    table th, table td { color: #ffffff !important; border-color: #444; }
    table thead th { background-color: #333; }
    .table-striped tbody tr:nth-of-type(odd)  { background-color: #2e2e2e; }
    .table-striped tbody tr:nth-of-type(even) { background-color: #242424; }
    .table tbody tr:hover { background-color: #3a3a3a !important; }

    /* Resaltado por estado */
    .vencido td     { background-color: #441111 !important; }
    .proxima td     { background-color: #443a11 !important; }
    </style>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chartjs-plugin-datalabels@2"></script>
</head>
<body>
    <?php include 'menu2.php'; ?>
    <div class="container">
        <h1 class="mb-4">Reporte de Calibraciones</h1>
        <p class="text-muted">
            <strong>Instrumentos por Estado:</strong> distribución actual.<br>
            <strong>Calibraciones Pendientes:</strong> próximos 12 meses.
        </p>
        <div class="mb-3">
            <a href="report.php?download=1" class="btn btn-info">
                <i class="fas fa-file-csv"></i> Descargar CSV
            </a>
        </div>
        <div class="row">
            <div class="col-md-6 mb-4">
                <div class="card p-3">
                    <h2 class="h5">Instrumentos por Estado</h2>
                    <canvas id="pieChart"></canvas>
                </div>
            </div>
            <div class="col-md-6 mb-4">
                <div class="card p-3">
                    <h2 class="h5">Pendientes por Mes</h2>
                    <canvas id="barChart"></canvas>
                </div>
            </div>
        </div>
        <div class="card p-3">
            <h2 class="h5">Próximos Vencimientos</h2>
            <div class="table-responsive">
                <table class="table table-striped" id="instrumentsTable">
                    <thead class="thead-dark">
                        <tr>
                            <th>ID</th><th>Desc</th><th>Marca</th>
                            <th>Model</th><th>Serial</th>
                            <th>Cal.Date</th><th>Due.Date</th>
                            <th>Estado</th><th>Días</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php while($r2 = $res2->fetch_assoc()): 
                        // Asignar clase solo para vencido y próxima calibración
                        $cls = '';
                        if ($r2['status_calculado'] === 'Vencido') {
                            $cls = 'vencido';
                        } elseif ($r2['status_calculado'] === 'Próxima calibración') {
                            $cls = 'proxima';
                        }
                    ?>
                        <tr class="<?= $cls ?>">
                            <td><?= htmlspecialchars($r2['ID']) ?></td>
                            <td><?= htmlspecialchars($r2['Description']) ?></td>
                            <td><?= htmlspecialchars($r2['Brand']) ?></td>
                            <td><?= htmlspecialchars($r2['Model']) ?></td>
                            <td><?= htmlspecialchars($r2['SerialNumber']) ?></td>
                            <td><?= htmlspecialchars($r2['CalDate']) ?></td>
                            <td><?= htmlspecialchars($r2['DueDate']) ?></td>
                            <td><?= htmlspecialchars($r2['status_calculado']) ?></td>
                            <td><?= htmlspecialchars($r2['days_left']) ?></td>
                        </tr>
                    <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <script>
    Chart.register(ChartDataLabels);
    new Chart(document.getElementById('pieChart'), {
        type: 'pie',
        data: {
            labels: <?= json_encode($pie_labels) ?>,
            datasets: [{
                data: <?= json_encode($pie_data) ?>,
                backgroundColor: ['#28a745','#ffc107','#dc3545']
            }]
        },
        options: {
            responsive: true,
            plugins: {
                legend: { position: 'bottom', labels:{ color:'#e0e0e0' } },
                datalabels: {
                    color: '#fff',
                    formatter: (v, ctx) => {
                        const d = ctx.chart.data.datasets[0].data,
                              s = d.reduce((a,b)=>a+b,0),
                              p = (v*100/s).toFixed(1)+'%';
                        return v+' ('+p+')';
                    },
                    font:{ weight:'bold', size:16 }
                }
            }
        }
    });
    new Chart(document.getElementById('barChart'), {
        type:'bar',
        data:{ labels: <?= json_encode($pending_labels) ?>,
               datasets:[{ label:'Pendientes', data: <?= json_encode($pending_data) ?>, backgroundColor:'#17a2b8' }] },
        options:{
            responsive:true,
            scales:{ x:{ ticks:{ color:'#e0e0e0' }}, y:{ ticks:{ color:'#e0e0e0' }, beginAtZero:true } },
            plugins:{ legend:{ labels:{ color:'#e0e0e0' } } }
        }
    });
    </script>
</body>
</html>
<?php
$conn->close();
?>
