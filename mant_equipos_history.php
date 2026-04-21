<?php
// /var/www/html/calibraciones/mant_equipos_history.php
declare(strict_types=1);

require_once __DIR__ . '/config.php';
require_auth(['admin','ingenieria']);

function h(?string $s): string { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

$id = $_GET['id'] ?? null;
if (!$id || !preg_match('/^[A-Za-z0-9._-]+$/', $id)) {
    http_response_code(400);
    exit('ID inválido.');
}

try {
    $pdo  = pdo();
    $stmt = $pdo->prepare('SELECT * FROM mant_equipos WHERE ID = ?');
    $stmt->execute([$id]);
    $equipo = $stmt->fetch();
    if (!$equipo) {
        http_response_code(404);
        exit('Equipo no encontrado.');
    }

    $histStmt = $pdo->prepare("
        SELECT *
        FROM mant_equipos_history
        WHERE EquipoID = ?
        ORDER BY UpdatedAt DESC
    ");
    $histStmt->execute([$id]);
    $history = $histStmt->fetchAll();
} catch (Throwable $e) {
    http_response_code(500);
    exit('Error al cargar historial: ' . $e->getMessage());
}

$periodLabels = ['3M' => 'Cada 3 meses', '6M' => 'Cada 6 meses', '1Y' => 'Anual'];
?>
<?php include __DIR__ . '/partials/header.php'; ?>

<div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
  <div>
    <h1 class="h4 m-0"><i class="fa fa-clock-rotate-left me-2"></i>Historial de Mantenimientos</h1>
    <small class="text-secondary">Equipo: <strong><?= h($id) ?></strong> — <?= h($equipo['Description'] ?? '') ?></small>
  </div>
  <a href="mant_equipos_admin.php" class="btn btn-outline-secondary">
    <i class="fa fa-arrow-left me-1"></i>Volver
  </a>
</div>

<!-- Info actual del equipo -->
<div class="card mb-4 p-3">
  <div class="row g-3">
    <?php if (!empty($equipo['Picture'])): ?>
    <div class="col-md-3 text-center">
      <img src="<?= h($equipo['Picture']) ?>" alt="<?= h($equipo['Description']) ?>"
           class="img-fluid rounded" style="max-height:200px;object-fit:cover;">
    </div>
    <div class="col-md-9">
    <?php else: ?>
    <div class="col-12">
    <?php endif; ?>
      <div class="row g-2">
        <div class="col-sm-4">
          <small class="text-muted d-block">Marca</small>
          <strong><?= h($equipo['Brand'] ?? '—') ?></strong>
        </div>
        <div class="col-sm-4">
          <small class="text-muted d-block">Modelo</small>
          <strong><?= h($equipo['Model'] ?? '—') ?></strong>
        </div>
        <div class="col-sm-4">
          <small class="text-muted d-block">Serie</small>
          <strong><?= h($equipo['SerialNumber'] ?? '—') ?></strong>
        </div>
        <div class="col-sm-4">
          <small class="text-muted d-block">Último mantenimiento</small>
          <strong><?= h($equipo['LastMaintDate'] ?? '—') ?></strong>
        </div>
        <div class="col-sm-4">
          <small class="text-muted d-block">Próximo mantenimiento</small>
          <strong><?= h($equipo['NextMaintDate'] ?? '—') ?></strong>
        </div>
        <div class="col-sm-4">
          <small class="text-muted d-block">Período</small>
          <strong><?= h($periodLabels[$equipo['MaintPeriod'] ?? ''] ?? ($equipo['MaintPeriod'] ?? '—')) ?></strong>
        </div>
        <div class="col-sm-4">
          <small class="text-muted d-block">Estado actual</small>
          <?php
            $st = $equipo['Status'] ?? '';
            $stClass = $st === 'Vencido' ? 'danger' : ($st === 'Próximo mantenimiento' ? 'warning' : 'success');
          ?>
          <span class="badge bg-<?= $stClass ?>"><?= h($st) ?></span>
        </div>
        <?php if (!empty($equipo['Location'])): ?>
        <div class="col-sm-8">
          <small class="text-muted d-block">Ubicación</small>
          <strong><?= h($equipo['Location']) ?></strong>
        </div>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>

<!-- Historial de mantenimientos -->
<div class="card p-3">
  <h5 class="mb-3"><i class="fa fa-list-ul me-2"></i>Registro de mantenimientos realizados</h5>

  <?php if (empty($history)): ?>
    <div class="alert alert-info">
      <i class="fa fa-info-circle me-2"></i>No hay registros de mantenimiento todavía.
    </div>
  <?php else: ?>
    <div class="timeline">
      <?php foreach ($history as $idx => $h_row): ?>
        <?php
          $action = $h_row['Action'] ?? 'update';
          $iconAction = $action === 'create' ? 'fa-plus-circle' : 'fa-wrench';
          $colorAction = $action === 'create' ? 'success' : 'primary';
          $labelAction = $action === 'create' ? 'Alta del equipo' : 'Mantenimiento registrado';
          $pLabel = $periodLabels[$h_row['MaintPeriod'] ?? ''] ?? ($h_row['MaintPeriod'] ?? '');
        ?>
        <div class="timeline-item">
          <div class="timeline-icon bg-<?= $colorAction ?>">
            <i class="fa <?= $iconAction ?> text-white"></i>
          </div>
          <div class="timeline-body">
            <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-2">
              <div>
                <span class="badge bg-<?= $colorAction ?> me-2"><?= h($labelAction) ?></span>
                <small class="text-muted"><?= h((string)($h_row['UpdatedAt'] ?? '')) ?></small>
              </div>
              <?php if (!empty($h_row['PdfPath'])): ?>
                <?php $pdfUrl = str_starts_with($h_row['PdfPath'], '/') ? $h_row['PdfPath'] : '/'.$h_row['PdfPath']; ?>
                <a href="<?= h($pdfUrl) ?>" target="_blank" class="btn btn-sm btn-outline-danger">
                  <i class="fa fa-file-pdf me-1"></i>Ver Documento
                </a>
              <?php endif; ?>
            </div>

            <div class="row g-2 text-sm">
              <div class="col-sm-6 col-md-3">
                <span class="text-muted d-block">Último mant.</span>
                <strong><?= h($h_row['LastMaintDate'] ?? '—') ?></strong>
              </div>
              <div class="col-sm-6 col-md-3">
                <span class="text-muted d-block">Próximo mant.</span>
                <strong><?= h($h_row['NextMaintDate'] ?? '—') ?></strong>
              </div>
              <div class="col-sm-6 col-md-3">
                <span class="text-muted d-block">Período</span>
                <strong><?= h($pLabel) ?></strong>
              </div>
              <div class="col-sm-6 col-md-3">
                <span class="text-muted d-block">Estado</span>
                <strong><?= h($h_row['Status'] ?? '—') ?></strong>
              </div>
              <?php if (!empty($h_row['Location'])): ?>
              <div class="col-sm-6 col-md-3">
                <span class="text-muted d-block">Ubicación</span>
                <strong><?= h($h_row['Location']) ?></strong>
              </div>
              <?php endif; ?>
              <?php if (!empty($h_row['Comments'])): ?>
              <div class="col-12">
                <span class="text-muted d-block">Comentarios</span>
                <p class="mb-0"><?= h($h_row['Comments']) ?></p>
              </div>
              <?php endif; ?>
            </div>

            <?php if (!empty($h_row['Picture'])): ?>
              <?php $picUrl = str_starts_with($h_row['Picture'], '/') ? $h_row['Picture'] : '/'.$h_row['Picture']; ?>
              <div class="mt-2">
                <img src="<?= h($picUrl) ?>" alt="Foto del equipo"
                     class="img-thumbnail" style="max-height:120px;cursor:pointer;"
                     data-bs-toggle="modal" data-bs-target="#histImgModal"
                     data-src="<?= h($picUrl) ?>">
              </div>
            <?php endif; ?>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</div>

<!-- Modal imagen -->
<div class="modal fade" id="histImgModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-lg">
    <div class="modal-content bg-dark">
      <div class="modal-header border-0">
        <h5 class="modal-title">Vista previa</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body d-flex justify-content-center">
        <img id="histPreviewImg" src="" alt="Imagen" class="img-fluid rounded" style="max-height:75vh;">
      </div>
    </div>
  </div>
</div>

<script>
const histModal = document.getElementById('histImgModal');
if (histModal) {
  histModal.addEventListener('show.bs.modal', (ev) => {
    const el = ev.relatedTarget;
    document.getElementById('histPreviewImg').src = el?.getAttribute('data-src') || '';
  });
  histModal.addEventListener('hidden.bs.modal', () => {
    document.getElementById('histPreviewImg').src = '';
  });
}
</script>

<style>
/* ===== TIMELINE ===== */
.timeline { position: relative; padding-left: 2.5rem; }
.timeline::before {
  content: '';
  position: absolute;
  left: 1rem;
  top: 0;
  bottom: 0;
  width: 2px;
  background: rgba(255,255,255,0.1);
}
.timeline-item {
  position: relative;
  margin-bottom: 1.5rem;
  padding-bottom: 1.5rem;
  border-bottom: 1px solid rgba(255,255,255,0.05);
}
.timeline-item:last-child { border-bottom: none; padding-bottom: 0; }

.timeline-icon {
  position: absolute;
  left: -2rem;
  top: 0;
  width: 32px;
  height: 32px;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 0.8rem;
  box-shadow: 0 0 0 4px rgba(0,0,0,0.3);
}
.timeline-body {
  background: rgba(255,255,255,0.03);
  border: 1px solid rgba(255,255,255,0.07);
  border-radius: 12px;
  padding: 1rem 1.25rem;
}
.timeline-body:hover {
  border-color: rgba(255,255,255,0.12);
}
.text-sm { font-size: 0.9rem; }

@media (max-width: 767px) {
  .timeline { padding-left: 2rem; }
  .timeline-icon { left: -1.75rem; width: 28px; height: 28px; font-size: 0.7rem; }
}
</style>

<?php include __DIR__ . '/partials/footer.php'; ?>
