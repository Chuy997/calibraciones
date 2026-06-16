<?php
// /var/www/html/calibraciones/ingenieria_ajax.php
declare(strict_types=1);

require_once __DIR__.'/config.php';
require_auth(['admin','ingenieria']);

header('Content-Type: application/json; charset=utf-8');

function h(?string $s): string { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

$pdo    = pdo();
$q      = trim((string)($_GET['q']      ?? ''));
$status = trim((string)($_GET['status'] ?? ''));
$page   = max(1, (int)($_GET['page']    ?? 1));
$per    = min(100, max(10, (int)($_GET['per'] ?? 50)));
$offset = ($page - 1) * $per;

// ── Construir WHERE ───────────────────────────────────────────────────────────
$where  = [];
$params = [];

if ($q !== '') {
    // LIKE en campos clave (compatible con cualquier motor)
    $like = '%' . $q . '%';
    $where[]  = "(Description LIKE :q1 OR Brand LIKE :q2 OR Model LIKE :q3
                  OR SerialNumber LIKE :q4 OR Owner LIKE :q5
                  OR Location LIKE :q6 OR Department LIKE :q7
                  OR ID LIKE :q8 OR XY_Asset LIKE :q9
                  OR HW_Asset LIKE :q10 OR ZL_Asset LIKE :q11)";
    for ($i = 1; $i <= 11; $i++) {
        $params[":q{$i}"] = $like;
    }
}

if ($status !== '') {
    $where[]           = 'Status = :status';
    $params[':status'] = $status;
} else {
    $where[] = "Status NOT IN ('Scrap', 'Destruido')";
}

$whereSQL = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

// ── Total ─────────────────────────────────────────────────────────────────────
$countSQL  = "SELECT COUNT(*) FROM ingenieria_items {$whereSQL}";
$countStmt = $pdo->prepare($countSQL);
$countStmt->execute($params);
$total = (int)$countStmt->fetchColumn();
$pages = max(1, (int)ceil($total / $per));

// ── Filas ─────────────────────────────────────────────────────────────────────
$dataSQL = "
    SELECT ID, Description, Brand, Model, SerialNumber, Location, Department,
           Owner, Status, Picture, Document, Pedimento,
           Qty, HW_Asset, ZL_Asset, AI_Asset, XY_Asset, Years, Come_form, ReceivedDate
    FROM ingenieria_items
    {$whereSQL}
    ORDER BY ID ASC
    LIMIT :limit OFFSET :offset
";
$dataStmt = $pdo->prepare($dataSQL);
foreach ($params as $k => $v) {
    $dataStmt->bindValue($k, $v);
}
$dataStmt->bindValue(':limit',  $per,    PDO::PARAM_INT);
$dataStmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$dataStmt->execute();
$rows = $dataStmt->fetchAll();

// ── Renderizar HTML de filas ──────────────────────────────────────────────────
$role = $_SESSION['role'] ?? '';

ob_start();
foreach ($rows as $r) {
    $st    = (string)($r['Status'] ?? '');
    $badge = $st === 'Scrap' ? 'badge-ven' : 'badge-cal';
    $id    = urlencode((string)$r['ID']);
    ?>
    <tr>
      <td data-col="col-id"><?= h($r['ID']) ?></td>
      <td data-col="col-foto" class="text-center">
        <?php if (!empty($r['Picture'])): ?>
          <img src="<?= h($r['Picture']) ?>" class="img-thumb" alt="img"
               data-bs-toggle="modal" data-bs-target="#imagePreviewModal"
               data-src="<?= h($r['Picture']) ?>">
        <?php else: ?>—<?php endif; ?>
      </td>
      <td data-col="col-desc"><?= h($r['Description']) ?></td>
      <td data-col="col-marca"><?= h($r['Brand']) ?></td>
      <td data-col="col-modelo"><?= h($r['Model']) ?></td>
      <td data-col="col-serie"><?= h($r['SerialNumber']) ?></td>
      <td data-col="col-qty"><?= h((string)($r['Qty'] ?? '')) ?></td>
      <td data-col="col-hw" style="display:none;"><?= h($r['HW_Asset'] ?? '') ?></td>
      <td data-col="col-zl" style="display:none;"><?= h($r['ZL_Asset'] ?? '') ?></td>
      <td data-col="col-ai" style="display:none;"><?= h($r['AI_Asset'] ?? '') ?></td>
      <td data-col="col-xy"><?= h($r['XY_Asset'] ?? '') ?></td>
      <td data-col="col-years"><?= h($r['Years'] ?? '') ?></td>
      <td data-col="col-come"><?= h($r['Come_form'] ?? '') ?></td>
      <td data-col="col-recv"><?= h($r['ReceivedDate'] ?? '') ?></td>
      <td data-col="col-ubi"><?= h($r['Location']) ?></td>
      <td data-col="col-depto"><?= h($r['Department']) ?></td>
      <td data-col="col-resp"><?= h($r['Owner']) ?></td>
      <td data-col="col-est"><span class="badge badge-state <?= $badge ?>"><?= h($st) ?></span></td>
      <td data-col="col-doc" class="text-center">
        <?php if (!empty($r['Document'])): ?>
          <a href="<?= h($r['Document']) ?>" target="_blank" class="btn btn-sm btn-outline-light" title="Ver PDF">
            <i class="fa fa-file-pdf"></i>
          </a>
        <?php else: ?>—<?php endif; ?>
      </td>
      <td data-col="col-ped"><?= h($r['Pedimento']) ?></td>
      <td data-col="col-acc">
        <div class="btn-group">
          <a class="btn btn-primary btn-sm" href="ingenieria_update.php?id=<?= $id ?>" title="Editar">
            <i class="fa fa-pen-to-square"></i>
          </a>
          <a class="btn btn-info btn-sm" href="ingenieria_history.php?id=<?= $id ?>" title="Historial">
            <i class="fa fa-clock-rotate-left"></i>
          </a>
          <?php if ($st !== 'Scrap'): ?>
            <a class="btn btn-warning btn-sm" href="ingenieria_scrap.php?id=<?= $id ?>" title="Enviar a Scrap">
              <i class="fa fa-triangle-exclamation"></i>
            </a>
          <?php else: ?>
            <button class="btn btn-secondary btn-sm" disabled title="Ya en Scrap">
              <i class="fa fa-ban"></i>
            </button>
          <?php endif; ?>
        </div>
      </td>
    </tr>
    <?php
}
$html = ob_get_clean();

echo json_encode([
    'html'  => $html,
    'total' => $total,
    'pages' => $pages,
    'page'  => $page,
    'per'   => $per,
]);
