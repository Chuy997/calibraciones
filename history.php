<?php
// /var/www/html/calibraciones/history.php
declare(strict_types=1);

require_once __DIR__.'/config.php';
require_auth(); // cualquier usuario logueado (admin o consulta)

function h(?string $s): string { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

$id = $_GET['id'] ?? '';
if ($id === '' || !preg_match('/^[A-Za-z0-9_-]+$/', $id)) {
  http_response_code(400);
  exit('ID no proporcionado o inválido.');
}

$sql = "
  SELECT 
    uh.InstrumentID,
    uh.Description,
    uh.Brand,
    uh.Model,
    uh.SerialNumber,
    uh.CertificateNo,
    uh.CalDate,
    uh.DueDate,
    uh.Status,
    uh.Comments,
    uh.PdfPath,
    uh.Picture,
    uh.UpdatedAt
  FROM updatehistory uh
  WHERE uh.InstrumentID = :id
  ORDER BY uh.UpdatedAt DESC
";
$stmt = pdo()->prepare($sql);
$stmt->execute([':id' => $id]);
$rows = $stmt->fetchAll();
?>
<?php include __DIR__.'/partials/header.php'; ?>

<div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
  <div>
    <h1 class="h4 m-0">Historial de instrumento</h1>
    <div class="text-secondary small mt-1">ID: <span class="text-light fw-semibold"><?= h($id) ?></span></div>
  </div>
  <a href="admin.php" class="btn btn-outline-secondary btn-sm"><i class="fa fa-arrow-left me-1"></i> Volver</a>
</div>

<?php if ($rows): ?>
<div class="card p-3 table-density-comfort dt-container">
  <div class="dt-toolbar">
    <div class="input-group" style="max-width:320px;">
      <span class="input-group-text"><i class="fa fa-magnifying-glass"></i></span>
      <input type="text" class="form-control dt-search" placeholder="Buscar en historial…">
    </div>

    <select class="form-select dt-density" style="max-width:180px;">
      <option value="comfort" selected>Densidad: cómoda</option>
      <option value="compact">Densidad: compacta</option>
    </select>

    <select class="form-select dt-rows-per-page" style="max-width:160px;">
      <option value="10" selected>10 por página</option>
      <option value="20">20 por página</option>
      <option value="50">50 por página</option>
      <option value="100">100 por página</option>
    </select>

    <div class="dropdown">
      <button class="btn btn-outline-secondary dropdown-toggle" data-bs-toggle="dropdown">Columnas</button>
      <div class="dropdown-menu dropdown-menu-dark p-2 colvis-menu">
        <?php
          $cols = ['Fecha','Certificado','Descripción','Marca','Modelo','Serial','Cal Date','Due Date','Estado','Comentarios','PDF','Imagen'];
          foreach ($cols as $i=>$c): ?>
          <label class="dropdown-item d-flex align-items-center gap-2">
            <input class="form-check-input me-2" type="checkbox" data-col="<?= $i ?>" checked>
            <span><?= h($c) ?></span>
          </label>
        <?php endforeach; ?>
      </div>
    </div>

    <div class="ms-auto d-flex gap-2">
      <!-- Filtro por Estado -->
      <select class="form-select dt-filter" data-col="8" style="max-width:220px;">
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
            <th class="th-sort" data-sort="date">Fecha <span class="sort-ind">▲▼</span></th>
            <th class="th-sort" data-sort="text">Certificado <span class="sort-ind">▲▼</span></th>
            <th class="th-sort" data-sort="text">Descripción <span class="sort-ind">▲▼</span></th>
            <th class="th-sort" data-sort="text">Marca <span class="sort-ind">▲▼</span></th>
            <th class="th-sort" data-sort="text">Modelo <span class="sort-ind">▲▼</span></th>
            <th class="th-sort" data-sort="text">Serial <span class="sort-ind">▲▼</span></th>
            <th class="th-sort" data-sort="date">Cal Date <span class="sort-ind">▲▼</span></th>
            <th class="th-sort" data-sort="date">Due Date <span class="sort-ind">▲▼</span></th>
            <th class="th-sort" data-sort="text">Estado <span class="sort-ind">▲▼</span></th>
            <th>Comentarios</th>
            <th>PDF</th>
            <th>Imagen</th>
          </tr>
        </thead>
        <tbody>
        <?php foreach ($rows as $r):
          $estado = (string)$r['Status'];
          $badge  = $estado==='Vencido' ? 'badge-ven' : ($estado==='Próxima calibración' ? 'badge-prox' : 'badge-cal');
        ?>
          <tr>
            <td><?= h($r['UpdatedAt']) ?></td>
            <td><?= h($r['CertificateNo']) ?></td>
            <td><?= h($r['Description']) ?></td>
            <td><?= h($r['Brand']) ?></td>
            <td><?= h($r['Model']) ?></td>
            <td><?= h($r['SerialNumber']) ?></td>
            <td><?= h($r['CalDate']) ?></td>
            <td><?= h($r['DueDate']) ?></td>
            <td><span class="badge badge-state <?= $badge ?>"><?= h($estado) ?></span></td>
            <td><?= h($r['Comments']) ?></td>
            <td class="text-center">
              <?php if (!empty($r['PdfPath'])): ?>
                <a href="<?= h($r['PdfPath']) ?>" target="_blank" class="btn btn-sm btn-outline-light" title="Ver PDF">
                  <i class="fa fa-file-pdf"></i>
                </a>
              <?php else: ?>—<?php endif; ?>
            </td>
            <td class="text-center">
              <?php if (!empty($r['Picture'])): ?>
                <img src="<?= h($r['Picture']) ?>" class="img-thumb" alt="img"
                     data-bs-toggle="modal" data-bs-target="#imagePreviewModal"
                     data-src="<?= h($r['Picture']) ?>">
              <?php else: ?>—<?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>

  <div class="d-flex justify-content-between align-items-center mt-2">
    <small class="text-secondary">Búsqueda, orden y paginación en el navegador.</small>
    <div class="dt-pager"></div>
  </div>
</div>

<!-- Modal imagen -->
<div class="modal fade" id="imagePreviewModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-lg">
    <div class="modal-content bg-dark">
      <div class="modal-header border-0">
        <h5 class="modal-title">Vista previa</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Cerrar"></button>
      </div>
      <div class="modal-body d-flex justify-content-center">
        <img id="previewImage" src="" alt="Imagen" class="img-fluid rounded" style="max-height:75vh;">
      </div>
    </div>
  </div>
</div>

<script>
const imgModal = document.getElementById('imagePreviewModal');
if (imgModal) {
  imgModal.addEventListener('show.bs.modal', (ev) => {
    const img = ev.relatedTarget;
    const src = img?.getAttribute('data-src') || img?.getAttribute('src');
    document.getElementById('previewImage').setAttribute('src', src || '');
  });
  imgModal.addEventListener('hidden.bs.modal', () => {
    document.getElementById('previewImage').setAttribute('src', '');
  });
}
</script>

<?php else: ?>
  <div class="card p-4">
    <div class="text-secondary">No hay historial de actualizaciones para este instrumento.</div>
  </div>
<?php endif; ?>

<?php include __DIR__.'/partials/footer.php'; ?>
