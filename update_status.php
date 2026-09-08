<?php
// /var/www/html/calibraciones/update_status.php
declare(strict_types=1);

require_once __DIR__ . '/config.php';
require_auth('admin');

header('Content-Type: application/json');

// Solo POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'Método no permitido.']);
    exit;
}

// CSRF
if (!isset($_POST['csrf']) || !csrf_validate($_POST['csrf'])) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'Token CSRF inválido.']);
    exit;
}

// Validar ID
$id = trim($_POST['id'] ?? '');
if ($id === '' || !preg_match('/^[A-Za-z0-9._-]+$/', $id)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'ID inválido.']);
    exit;
}

// Valores válidos del ENUM en la tabla instruments
$allowed = ['fuera de calibracion', 'calibrado', 'en proceso de calibracion'];
$status = trim($_POST['status'] ?? '');

if (!in_array($status, $allowed, true)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Estado no válido.']);
    exit;
}

try {
    $pdo = pdo();

    // Verificar que el instrumento existe
    $chk = $pdo->prepare('SELECT ID FROM instruments WHERE ID = ?');
    $chk->execute([$id]);
    if (!$chk->fetch()) {
        http_response_code(404);
        echo json_encode(['ok' => false, 'error' => 'Instrumento no encontrado.']);
        exit;
    }

    // Actualizar solo el Status
    $upd = $pdo->prepare('UPDATE instruments SET Status = ? WHERE ID = ?');
    $upd->execute([$status, $id]);

    echo json_encode(['ok' => true, 'id' => $id, 'status' => $status]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'Error interno: ' . $e->getMessage()]);
}
