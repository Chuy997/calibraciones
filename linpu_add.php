<?php
require_once 'config.php';
require_auth('admin');

$pdo = pdo(); // ← Corrección clave

$error = '';
$success = '';

// Definición de equipos Linpu en uso
$linpu_units = [
    'C023000012111160007' => ['name' => 'Linpu F1200 #1', 'max_slot' => 2],
    'C023000012111160008' => ['name' => 'Linpu F1200 #2', 'max_slot' => 3],
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'] ?? '')) {
        die('CSRF token mismatch');
    }

    $serial = trim($_POST['serial_number'] ?? '');
    $slot = (int)($_POST['slot'] ?? 0);
    $channel = (int)($_POST['channel'] ?? 1);
    $comments = trim($_POST['comments'] ?? '');
    $operator = $_SESSION['username'] ?? 'unknown';

    if (!isset($linpu_units[$serial])) {
        $error = 'Número de serie no autorizado.';
    } elseif ($slot < 1 || $slot > $linpu_units[$serial]['max_slot'] || $channel < 1 || $channel > 4) {
        $error = 'Slot o canal fuera de rango para este equipo.';
    } else {
        $picture_path = null;
        if (!empty($_FILES['picture']['name'])) {
            $upload_dir = __DIR__ . '/uploads/';
            if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);

            $file = $_FILES['picture'];
            $allowed_types = ['image/jpeg', 'image/png', 'image/jpg', 'image/heic'];
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mime = finfo_file($finfo, $file['tmp_name']);
            finfo_close($finfo);

            if (!in_array($mime, $allowed_types)) {
                $error = 'Tipo de archivo no permitido (solo JPG, PNG, HEIC).';
            } else {
                $ext = match($mime) {
                    'image/heic' => 'jpg',
                    'image/jpeg', 'image/jpg' => 'jpg',
                    'image/png' => 'png',
                    default => 'jpg'
                };
                $filename = preg_replace('/[^a-zA-Z0-9]/', '_', $serial) . '_' . time() . '.' . $ext;
                $target = $upload_dir . $filename;

                if (move_uploaded_file($file['tmp_name'], $target)) {
                    $picture_path = 'uploads/' . $filename;
                } else {
                    $error = 'Error al subir la imagen.';
                }
            }
        }

        if (!$error) {
            $tolerance = 0.20;
            $inserted = 0;

            // Procesar 1310 nm
            $ref1310 = floatval($_POST['ref_1310'] ?? 0);
            $dut1310 = floatval($_POST['dut_1310'] ?? 0);
            if ($ref1310 != 0 || $dut1310 != 0) {
                $dev1310 = abs($ref1310 - $dut1310);
                $result1310 = ($dev1310 <= $tolerance) ? 'aprobado' : 'fuera de tolerancia';
                $stmt = $pdo->prepare("
                    INSERT INTO linpu_calibrations 
                    (serial_number, slot, channel, wavelength_nm, reference_reading_dbm, dut_reading_dbm, 
                     tolerance_db, result, operator, comments, picture_path)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([
                    $serial, $slot, $channel, 1310, $ref1310, $dut1310,
                    $tolerance, $result1310, $operator,
                    $comments ?: "Calibración {$linpu_units[$serial]['name']} – Slot {$slot}, Canal {$channel}, 1310 nm",
                    $picture_path
                ]);
                $inserted++;
            }

            // Procesar 1550 nm
            $ref1550 = floatval($_POST['ref_1550'] ?? 0);
            $dut1550 = floatval($_POST['dut_1550'] ?? 0);
            if ($ref1550 != 0 || $dut1550 != 0) {
                $dev1550 = abs($ref1550 - $dut1550);
                $result1550 = ($dev1550 <= $tolerance) ? 'aprobado' : 'fuera de tolerancia';
                $stmt = $pdo->prepare("
                    INSERT INTO linpu_calibrations 
                    (serial_number, slot, channel, wavelength_nm, reference_reading_dbm, dut_reading_dbm, 
                     tolerance_db, result, operator, comments, picture_path)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([
                    $serial, $slot, $channel, 1550, $ref1550, $dut1550,
                    $tolerance, $result1550, $operator,
                    $comments ?: "Calibración {$linpu_units[$serial]['name']} – Slot {$slot}, Canal {$channel}, 1550 nm",
                    $picture_path
                ]);
                $inserted++;
            }

            if ($inserted > 0) {
                $success = "Calibración registrada correctamente ({$inserted} longitud" . ($inserted > 1 ? 'es' : '') . ").";
            } else {
                $error = 'Debe ingresar al menos una medición (1310 nm o 1550 nm).';
            }
        }
    }
}

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
?>

<?php include 'partials/header.php'; ?>
<div class="container mt-4">
    <h2>Registrar Calibración - Linpu F1200</h2>

    <?php if ($error): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>
    <?php if ($success): ?>
        <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
    <?php endif; ?>

    <form method="POST" enctype="multipart/form-data">
        <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">

        <div class="mb-3">
            <label class="form-label">Equipo Linpu F1200</label>
            <select name="serial_number" class="form-control" required onchange="updateSlotOptions(this.value)">
                <option value="">Selecciona un equipo</option>
                <?php foreach ($linpu_units as $sn => $info): ?>
                    <option value="<?= htmlspecialchars($sn) ?>"><?= htmlspecialchars("{$info['name']} ({$sn})") ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="row">
            <div class="col-md-6 mb-3">
                <label class="form-label">Slot</label>
                <select name="slot" class="form-control" required id="slot-select">
                    <option value="">Seleccione un equipo primero</option>
                </select>
            </div>
            <div class="col-md-6 mb-3">
                <label class="form-label">Canal (1-4)</label>
                <select name="channel" class="form-control" required>
                    <option value="1">1</option>
                    <option value="2">2</option>
                    <option value="3">3</option>
                    <option value="4">4</option>
                </select>
            </div>
        </div>

        <!-- Mediciones -->
        <div class="card mb-4">
            <div class="card-header">Mediciones (requeridas)</div>
            <div class="card-body">
                <p class="text-secondary small">Complete al menos una longitud de onda. Se recomienda ambas.</p>

                <h6>1310 nm</h6>
                <div class="row mb-3">
                    <div class="col-md-6">
                        <label class="form-label">Lectura referencia (dBm)</label>
                        <input type="number" step="0.001" name="ref_1310" class="form-control">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Lectura Linpu (dBm)</label>
                        <input type="number" step="0.001" name="dut_1310" class="form-control">
                    </div>
                </div>

                <h6>1550 nm</h6>
                <div class="row">
                    <div class="col-md-6">
                        <label class="form-label">Lectura referencia (dBm)</label>
                        <input type="number" step="0.001" name="ref_1550" class="form-control">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Lectura Linpu (dBm)</label>
                        <input type="number" step="0.001" name="dut_1550" class="form-control">
                    </div>
                </div>
            </div>
        </div>

        <div class="mb-3">
            <label class="form-label">Foto del setup (opcional, se aplica a ambas longitudes)</label>
            <input type="file" name="picture" class="form-control" accept="image/*">
        </div>

        <div class="mb-3">
            <label class="form-label">Comentarios (opcional)</label>
            <textarea name="comments" class="form-control" rows="2"></textarea>
        </div>

        <button type="submit" class="btn btn-primary">Guardar Calibración</button>
        <a href="linpu_admin.php" class="btn btn-outline-secondary">← Regresar a registros</a>
    </form>
</div>

<script>
function updateSlotOptions(serial) {
    const slotSelect = document.getElementById('slot-select');
    slotSelect.innerHTML = '<option value="">Seleccione slot</option>';
    if (!serial) return;

    const slots = {
        'C023000012111160007': [1, 2],
        'C023000012111160008': [1, 2, 3]
    };

    const available = slots[serial] || [];
    available.forEach(slot => {
        const opt = document.createElement('option');
        opt.value = slot;
        opt.textContent = slot;
        slotSelect.appendChild(opt);
    });
}
</script>

<?php include 'partials/footer.php'; ?>