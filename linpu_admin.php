<?php
require_once 'config.php';
require_auth('admin');

$pdo = pdo();

// Filtros
$serial_filter = trim($_GET['serial'] ?? '');
$slot_filter = isset($_GET['slot']) ? (int)$_GET['slot'] : null;
$result_filter = trim($_GET['result'] ?? '');

// Construir condiciones
$where = "1=1";
$params = [];

if ($serial_filter !== '') {
    $where .= " AND lc.serial_number LIKE ?";
    $params[] = "%{$serial_filter}%";
}
if ($slot_filter !== null && $slot_filter >= 1 && $slot_filter <= 3) {
    $where .= " AND lc.slot = ?";
    $params[] = $slot_filter;
}

// Si se filtra por resultado, aplicamos a ambas longitudes (al menos una debe coincidir)
if (in_array($result_filter, ['aprobado', 'fuera de tolerancia'])) {
    $where .= " AND (lc1310.result = ? OR lc1550.result = ?)";
    $params[] = $result_filter;
    $params[] = $result_filter;
}

$query = "
    SELECT 
        lc.serial_number,
        lc.slot,
        lc.channel,
        lc1310.deviation_db AS dev_1310,
        lc1310.result AS res_1310,
        lc1310.calibration_date AS date_1310,
        lc1550.deviation_db AS dev_1550,
        lc1550.result AS res_1550,
        lc1550.calibration_date AS date_1550,
        -- Fecha más reciente para ordenar
        GREATEST(
            COALESCE(lc1310.calibration_date, '1970-01-01'),
            COALESCE(lc1550.calibration_date, '1970-01-01')
        ) AS last_calibration
    FROM (SELECT DISTINCT serial_number, slot, channel FROM linpu_calibrations) lc
    LEFT JOIN linpu_calibrations lc1310 
        ON lc.serial_number = lc1310.serial_number 
        AND lc.slot = lc1310.slot 
        AND lc.channel = lc1310.channel 
        AND lc1310.wavelength_nm = 1310
    LEFT JOIN linpu_calibrations lc1550 
        ON lc.serial_number = lc1550.serial_number 
        AND lc.slot = lc1550.slot 
        AND lc.channel = lc1550.channel 
        AND lc1550.wavelength_nm = 1550
    WHERE {$where}
    ORDER BY last_calibration DESC, lc.serial_number, lc.slot, lc.channel
";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$records = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<?php include 'partials/header.php'; ?>
<div class="container mt-4">
    <h2>Equipos Linpu F1200 - Gestión de Calibraciones</h2>

    <div class="card mb-4">
        <div class="card-body">
            <form method="GET" class="row g-3">
                <div class="col-md-4">
                    <label class="form-label">Número de serie</label>
                    <input type="text" name="serial" class="form-control" value="<?= htmlspecialchars($serial_filter) ?>">
                </div>
                <div class="col-md-2">
                    <label class="form-label">Slot</label>
                    <select name="slot" class="form-select">
                        <option value="">Todos</option>
                        <option value="1" <?= ($slot_filter === 1) ? 'selected' : '' ?>>1</option>
                        <option value="2" <?= ($slot_filter === 2) ? 'selected' : '' ?>>2</option>
                        <option value="3" <?= ($slot_filter === 3) ? 'selected' : '' ?>>3</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Resultado (cualquier longitud)</label>
                    <select name="result" class="form-select">
                        <option value="">Todos</option>
                        <option value="aprobado" <?= ($result_filter === 'aprobado') ? 'selected' : '' ?>>Aprobado</option>
                        <option value="fuera de tolerancia" <?= ($result_filter === 'fuera de tolerancia') ? 'selected' : '' ?>>Fuera de tolerancia</option>
                    </select>
                </div>
                <div class="col-md-3 d-flex align-items-end">
                    <button type="submit" class="btn btn-primary me-2">Filtrar</button>
                    <a href="linpu_admin.php" class="btn btn-outline-secondary">Limpiar</a>
                </div>
            </form>
        </div>
    </div>

    <?php if (empty($records)): ?>
        <div class="alert alert-info">No se encontraron registros.</div>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table table-striped table-bordered">
                <thead>
                    <tr>
                        <th>Serial</th>
                        <th>Slot</th>
                        <th>Canal</th>
                        <th>1310 nm<br><small>Desv. / Resultado</small></th>
                        <th>1550 nm<br><small>Desv. / Resultado</small></th>
                        <th>Última calibración</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($records as $r): ?>
                        <tr>
                            <td><?= htmlspecialchars($r['serial_number']) ?></td>
                            <td><?= (int)$r['slot'] ?></td>
                            <td><?= (int)$r['channel'] ?></td>
                            <td>
                                <?php if ($r['dev_1310'] !== null): ?>
                                    <?= number_format($r['dev_1310'], 3) ?> dB<br>
                                    <?php if ($r['res_1310'] === 'aprobado'): ?>
                                        <span class="badge bg-success">Aprobado</span>
                                    <?php else: ?>
                                        <span class="badge bg-danger">Fuera</span>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <span class="text-muted">—</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($r['dev_1550'] !== null): ?>
                                    <?= number_format($r['dev_1550'], 3) ?> dB<br>
                                    <?php if ($r['res_1550'] === 'aprobado'): ?>
                                        <span class="badge bg-success">Aprobado</span>
                                    <?php else: ?>
                                        <span class="badge bg-danger">Fuera</span>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <span class="text-muted">—</span>
                                <?php endif; ?>
                            </td>
                            <td><?= htmlspecialchars(date('Y-m-d', strtotime($r['last_calibration']))) ?></td>
                            <td>
                                <a href="linpu_history.php?serial=<?= urlencode($r['serial_number']) ?>" class="btn btn-sm btn-outline-info">Historial</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>

    <div class="mt-3">
        <a href="linpu_add.php" class="btn btn-success">Nuevo registro</a>
        <a href="index.php" class="btn btn-secondary">Volver al inicio</a>
    </div>
</div>
<?php include 'partials/footer.php'; ?>