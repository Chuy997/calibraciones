<?php
// /var/www/html/calibraciones/admin.php
declare(strict_types=1);

require __DIR__.'/config.php';
require_auth('admin');

// Consulta
$sql = <<<SQL
SELECT 
    i.ID,
    i.Picture,
    i.Description,
    i.Brand,
    i.Model,
    i.SerialNumber,
    i.CalDate,
    i.DueDate,
    (
        SELECT uh.PdfPath
        FROM updatehistory uh
        WHERE uh.InstrumentID = i.ID
          AND uh.PdfPath IS NOT NULL
        ORDER BY uh.UpdatedAt DESC
        LIMIT 1
    ) AS LastPdfPath,
    i.Comments,
    CASE
        WHEN CURRENT_DATE() > i.DueDate THEN 'Vencido'
        WHEN i.DueDate BETWEEN CURRENT_DATE() AND DATE_ADD(CURRENT_DATE(), INTERVAL 30 DAY) THEN 'Próxima calibración'
        ELSE 'Calibrado'
    END AS status_calculado
FROM instruments i
ORDER BY i.ID
SQL;

$stmt = pdo()->query($sql);
$rows = $stmt->fetchAll();

function h(?string $s): string { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
?>
<?php include __DIR__.'/partials/header.php'; ?>

<div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
  <h1 class="h4 m-0">Instrumentos de medición</h1>
  <a class="btn btn-success" href="add.php"><i class="fa fa-plus me-1"></i> Nuevo</a>
</div>

<div class="card p-3 table-density-comfort dt-container">
  <div class="dt-toolbar">
    <div class="input-group" style="max-width:320px;">
      <span class="input-group-text"><i class="fa fa-magnifying-glass"></i></span>
      <input type="text" class="form-control dt-search" placeholder="Buscar…">
    </div>

    <select class="form-select dt-density" style="max-width:180px;">
      <option value="comfort">Densidad: cómoda</option>
      <option value="compact"selected>Densidad: compacta</option>
    </select>

    <select class="form-select dt-rows-per-page" style="max-width:160px;">
      <option value="10">10 por página</option>
      <option value="20">20 por página</option>
      <option value="50"selected>50 por página</option>
      <option value="100">100 por página</option>
    </select>

    <div class="dropdown">
      <button class="btn btn-outline-secondary dropdown-toggle" data-bs-toggle="dropdown">
        Columnas
      </button>
      <div class="dropdown-menu dropdown-menu-dark p-2 colvis-menu">
        <?php
          $cols = ['ID','Foto','Descripción','Marca','Modelo','Serie','Cal Date','Due Date','Estado','PDF','Acciones'];
          foreach ($cols as $i=>$c):
        ?>
          <label class="dropdown-item d-flex align-items-center gap-2">
            <input class="form-check-input me-2" type="checkbox" data-col="<?= $i ?>" checked>
            <span><?= h($c) ?></span>
          </label>
        <?php endforeach; ?>
      </div>
    </div>

    <div class="ms-auto">
      <form class="d-flex align-items-center gap-2" action="generate_report.php" method="get">
        <select name="month" class="form-select" required>
          <?php for ($m=1; $m<=12; $m++): ?>
            <option value="<?= $m ?>"><?= date('F', mktime(0,0,0,$m,1)) ?></option>
          <?php endfor; ?>
        </select>
        <select name="year" class="form-select" required>
          <?php for ($y=(int)date('Y'); $y >= (int)date('Y')-10; $y--): ?>
            <option value="<?= $y ?>"><?= $y ?></option>
          <?php endfor; ?>
        </select>
        <button class="btn btn-info" type="submit"><i class="fa fa-file-arrow-down me-1"></i>Reporte</button>
      </form>
    </div>
  </div>

  <div class="table-wrap">
    <div class="table-scroll">
      <table class="table table-striped table-hover align-middle" id="instrumentsTable">
        <thead>
          <tr>
            <th class="th-sort" data-sort="text">ID <span class="sort-ind">▲▼</span></th>
            <th>Foto</th>
            <th class="th-sort" data-sort="text">Descripción <span class="sort-ind">▲▼</span></th>
            <th class="th-sort" data-sort="text">Marca <span class="sort-ind">▲▼</span></th>
            <th class="th-sort" data-sort="text">Modelo <span class="sort-ind">▲▼</span></th>
            <th class="th-sort" data-sort="text">Serie <span class="sort-ind">▲▼</span></th>
            <th class="th-sort" data-sort="date">Cal Date <span class="sort-ind">▲▼</span></th>
            <th class="th-sort" data-sort="date">Due Date <span class="sort-ind">▲▼</span></th>
            <th class="th-sort" data-sort="text">Estado <span class="sort-ind">▲▼</span></th>
            <th>PDF</th>
            <th>Acciones</th>
          </tr>
        </thead>
        <tbody>
        <?php foreach ($rows as $r): 
          $estado = $r['status_calculado'] ?? '';
          $badge = $estado==='Vencido' ? 'badge-ven' : ($estado==='Próxima calibración' ? 'badge-prox' : 'badge-cal');
        ?>
          <tr>
            <td><?= h($r['ID']) ?></td>
            <td>
              <?php if (!empty($r['Picture'])): ?>
                <img src="<?= h($r['Picture']) ?>" class="img-thumb" alt="foto"
                     data-bs-toggle="modal" data-bs-target="#imagePreviewModal"
                     data-src="<?= h($r['Picture']) ?>">
              <?php else: ?>—<?php endif; ?>
            </td>
            <td><?= h($r['Description']) ?></td>
            <td><?= h($r['Brand']) ?></td>
            <td><?= h($r['Model']) ?></td>
            <td><?= h($r['SerialNumber']) ?></td>
            <td><?= h($r['CalDate']) ?></td>
            <td><?= h($r['DueDate']) ?></td>
            <td><span class="badge badge-state <?= $badge ?>"><?= h($estado) ?></span></td>
            <td>
              <?php if (!empty($r['LastPdfPath'])): ?>
                <a href="<?= h($r['LastPdfPath']) ?>" target="_blank" class="btn btn-sm btn-outline-light" title="Ver PDF"><i class="fa fa-file-pdf"></i></a>
              <?php else: ?>—<?php endif; ?>
            </td>
            <td>
              <div class="btn-group">
                <a class="btn btn-primary btn-sm" href="update.php?id=<?= urlencode((string)$r['ID']) ?>" title="Editar">
                  <i class="fa fa-pen-to-square"></i>
                </a>
                <a class="btn btn-info btn-sm" href="history.php?id=<?= urlencode((string)$r['ID']) ?>" title="Historial">
                  <i class="fa fa-clock-rotate-left"></i>
                </a>
                <a class="btn btn-warning btn-sm" href="move_out_of_use.php?id=<?= urlencode((string)$r['ID']) ?>" title="Mover fuera de uso">
                  <i class="fa fa-triangle-exclamation"></i>
                </a>
              </div>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>

  <div class="d-flex justify-content-between align-items-center mt-2">
    <small class="text-secondary">Mostrando tabla local (orden, búsqueda y paginación en el navegador).</small>
    <div class="dt-pager"></div>
  </div>
</div>

<!-- Modal imagen (igual que en history.php) -->
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

<?php include __DIR__.'/partials/footer.php'; ?>
