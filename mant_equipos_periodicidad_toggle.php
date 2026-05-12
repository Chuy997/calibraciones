<?php
// /var/www/html/calibraciones/mant_equipos_periodicidad_toggle.php
declare(strict_types=1);

require_once __DIR__ . '/config.php';
require_auth(['admin','ingenieria']);

header('Content-Type: application/json; charset=utf-8');

// Solo POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'Método no permitido.']);
    exit;
}

// Leer JSON del body
$body = json_decode(file_get_contents('php://input'), true);

// CSRF
$csrf = $body['csrf'] ?? '';
if (!csrf_validate($csrf)) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'Token CSRF inválido.']);
    exit;
}

$id      = trim((string)($body['id']      ?? ''));
$cycle   = trim((string)($body['cycle']   ?? ''));
$enabled = isset($body['enabled']) ? (bool)$body['enabled'] : false;

// Validar ID
if ($id === '' || !preg_match('/^[A-Za-z0-9._-]+$/', $id)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'ID de equipo inválido.']);
    exit;
}

// Validar ciclo — solo columnas permitidas
$allowedCycles = [
    'monthly'   => 'CycleMonthly',
    'quarterly' => 'CycleQuarterly',
    'yearly'    => 'CycleYearly',
];

if (!array_key_exists($cycle, $allowedCycles)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Ciclo no válido.']);
    exit;
}

$column = $allowedCycles[$cycle];
$value  = $enabled ? 1 : 0;

try {
    $pdo  = pdo();

    // Verificar que el equipo existe
    $chk = $pdo->prepare('SELECT COUNT(*) FROM mant_equipos WHERE ID = ?');
    $chk->execute([$id]);
    if ((int)$chk->fetchColumn() === 0) {
        http_response_code(404);
        echo json_encode(['ok' => false, 'error' => 'Equipo no encontrado.']);
        exit;
    }

    // UPDATE seguro (columna ya validada contra lista blanca)
    $upd = $pdo->prepare("UPDATE mant_equipos SET {$column} = ? WHERE ID = ?");
    $upd->execute([$value, $id]);

    echo json_encode(['ok' => true, 'id' => $id, 'cycle' => $cycle, 'enabled' => $enabled]);

} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'Error interno del servidor.']);
}
