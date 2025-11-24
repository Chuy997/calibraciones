<?php
require_once 'config.php';
require_auth('admin');

$pdo = pdo();

$serial = trim($_GET['serial'] ?? '');
if (!$serial) {
    header('Location: linpu_admin.php');
    exit;
}

$stmt = $pdo->prepare("
    SELECT serial_number, slot, channel, wavelength_nm, reference_reading_dbm, dut_reading_dbm,
           deviation_db, tolerance_db, result, calibration_date, picture_path, comments
    FROM linpu_calibrations
    WHERE serial_number = ?
    ORDER BY calibration_date DESC, wavelength_nm
");
$stmt->execute([$serial]);
$records = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<?php include 'partials/header.php'; ?>
<div class="container mt-4">
    <h2 class="mb-3">Historial - Linpu F1200 (<?= htmlspecialchars($serial) ?>)</h2>

    <?php if (empty($records)): ?>
        <div class="alert alert-warning">No hay registros para este número de serie.</div>
        <a href="linpu_admin.php" class="btn btn-secondary">Volver</a>
    <?php else: ?>
        <!-- Gráfica simple de desviación -->
        <div class="card bg-dark border border-secondary mb-4">
            <div class="card-header">Desviación por Longitud de Onda</div>
            <div class="card-body">
                <?php
                $max_dev = 0.5; // dB máximo visualizado
                $data1310 = array_filter($records, fn($r) => $r['wavelength_nm'] == 1310);
                $data1550 = array_filter($records, fn($r) => $r['wavelength_nm'] == 1550);
                $latest1310 = $data1310 ? end($data1310) : null;
                $latest1550 = $data1550 ? end($data1550) : null;
                ?>
                <div class="row g-3">
                    <?php if ($latest1310): ?>
                    <div class="col-md-6 col-lg-4">
                        <div class="card h-100 bg-dark text-light border-secondary">
                            <div class="card-header p-2">1310 nm</div>
                            <div class="card-body d-flex flex-column align-items-center justify-content-center">
                                <div class="fs-4 fw-bold"><?= number_format($latest1310['deviation_db'], 3) ?> dB</div>
                                <div class="progress w-100 my-2" style="height: 8px;">
                                    <div class="progress-bar <?= $latest1310['result'] === 'aprobado' ? 'bg-success' : 'bg-danger' ?>"
                                         role="progressbar"
                                         style="width: <?= min(100, ($latest1310['deviation_db']/$max_dev)*100) ?>%;"
                                         aria-valuenow="<?= $latest1310['deviation_db'] ?>"
                                         aria-valuemin="0"
                                         aria-valuemax="<?= $max_dev ?>">
                                    </div>
                                </div>
                                <span class="badge <?= $latest1310['result'] === 'aprobado' ? 'bg-success' : 'bg-danger' ?> mt-1">
                                    <?= $latest1310['result'] === 'aprobado' ? 'Aprobado' : 'Fuera' ?>
                                </span>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>

                    <?php if ($latest1550): ?>
                    <div class="col-md-6 col-lg-4">
                        <div class="card h-100 bg-dark text-light border-secondary">
                            <div class="card-header p-2">1550 nm</div>
                            <div class="card-body d-flex flex-column align-items-center justify-content-center">
                                <div class="fs-4 fw-bold"><?= number_format($latest1550['deviation_db'], 3) ?> dB</div>
                                <div class="progress w-100 my-2" style="height: 8px;">
                                    <div class="progress-bar <?= $latest1550['result'] === 'aprobado' ? 'bg-success' : 'bg-danger' ?>"
                                         role="progressbar"
                                         style="width: <?= min(100, ($latest1550['deviation_db']/$max_dev)*100) ?>%;"
                                         aria-valuenow="<?= $latest1550['deviation_db'] ?>"
                                         aria-valuemin="0"
                                         aria-valuemax="<?= $max_dev ?>">
                                    </div>
                                </div>
                                <span class="badge <?= $latest1550['result'] === 'aprobado' ? 'bg-success' : 'bg-danger' ?> mt-1">
                                    <?= $latest1550['result'] === 'aprobado' ? 'Aprobado' : 'Fuera' ?>
                                </span>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
                <div class="text-muted small text-center mt-2">
                    Tolerancia: ±0.20 dB | Máx. visualizado: <?= $max_dev ?> dB
                </div>
            </div>
        </div>

        <!-- Tabla detallada -->
        <div class="table-responsive">
            <table class="table table-bordered table-striped table-dark">
                <thead class="table-secondary">
                    <tr>
                        <th>Fecha</th>
                        <th>Slot</th>
                        <th>Canal</th>
                        <th>λ (nm)</th>
                        <th>Ref (dBm)</th>
                        <th>Linpu (dBm)</th>
                        <th>Desviación (dB)</th>
                        <th>Resultado</th>
                        <th>Comentarios</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($records as $r): ?>
                    <tr>
                        <td><?= htmlspecialchars(date('Y-m-d H:i', strtotime($r['calibration_date']))) ?></td>
                        <td><?= (int)$r['slot'] ?></td>
                        <td><?= (int)$r['channel'] ?></td>
                        <td><?= (int)$r['wavelength_nm'] ?></td>
                        <td><?= number_format($r['reference_reading_dbm'], 3) ?></td>
                        <td><?= number_format($r['dut_reading_dbm'], 3) ?></td>
                        <td><?= number_format($r['deviation_db'], 3) ?></td>
                        <td>
                            <?php if ($r['result'] === 'aprobado'): ?>
                                <span class="badge bg-success">Aprobado</span>
                            <?php else: ?>
                                <span class="badge bg-danger">Fuera</span>
                            <?php endif; ?>
                        </td>
                        <td><?= htmlspecialchars($r['comments'] ?: '-') ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <div class="mt-3">
            <a href="linpu_add.php" class="btn btn-success"><i class="fa fa-plus me-1"></i> Nuevo registro</a>
            <a href="linpu_admin.php" class="btn btn-secondary"><i class="fa fa-arrow-left me-1"></i> Volver al listado</a>
        </div>
    <?php endif; ?>
</div>
<?php include 'partials/footer.php'; ?>
