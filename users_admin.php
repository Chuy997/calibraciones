<?php
// /var/www/html/calibraciones/users_admin.php
declare(strict_types=1);

require_once __DIR__.'/config.php';
require_auth('admin');

function h(?string $s): string { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

$pdo = pdo();
$me  = $_SESSION['username'] ?? '';

$errors = [];
$success = null;

/** Helpers */
function is_valid_role(string $r): bool {
  return in_array($r, ['admin','operator','ingenieria'], true); // ajustado a tu esquema
}
function username_exists(PDO $pdo, string $u, ?int $ignoreId=null): bool {
  $sql = 'SELECT userID FROM users WHERE username = ?'.($ignoreId ? ' AND userID <> ?' : '');
  $st  = $pdo->prepare($sql);
  $st->execute($ignoreId ? [$u,$ignoreId] : [$u]);
  return (bool)$st->fetchColumn();
}
function count_admins(PDO $pdo): int {
  return (int)$pdo->query("SELECT COUNT(*) FROM users WHERE role='admin'")->fetchColumn();
}

/** Acciones */
if ($_SERVER['REQUEST_METHOD']==='POST') {
  try {
    if (!isset($_POST['csrf']) || !csrf_validate($_POST['csrf'])) {
      throw new RuntimeException('Sesión expirada. Vuelve a intentar.');
    }
    $action = $_POST['action'] ?? '';
    if ($action === 'create') {
      $username = trim($_POST['username'] ?? '');
      $role     = trim($_POST['role'] ?? 'operator');
      $pass1    = (string)($_POST['password'] ?? '');
      $pass2    = (string)($_POST['password2'] ?? '');

      if ($username==='' || !preg_match('/^[A-Za-z0-9._-]{3,50}$/',$username)) throw new RuntimeException('Usuario inválido (3-50, letras/números . _ -).');
      if (!is_valid_role($role)) throw new RuntimeException('Rol inválido.');
      if ($pass1==='' || strlen($pass1)<8) throw new RuntimeException('La contraseña debe tener al menos 8 caracteres.');
      if ($pass1 !== $pass2) throw new RuntimeException('Las contraseñas no coinciden.');
      if (username_exists($pdo,$username)) throw new RuntimeException('Ese usuario ya existe.');

      $hash = password_hash($pass1, PASSWORD_DEFAULT);
      $st = $pdo->prepare('INSERT INTO users (username,password,role) VALUES (?,?,?)');
      $st->execute([$username,$hash,$role]);
      $success = 'Usuario creado correctamente.';

    } elseif ($action === 'update') {
      $id   = (int)($_POST['id'] ?? 0);
      $role = trim($_POST['role'] ?? '');
      $pass = (string)($_POST['password'] ?? '');
      $user = trim($_POST['username'] ?? '');

      if ($id<=0) throw new RuntimeException('ID inválido.');
      if ($user==='' || !preg_match('/^[A-Za-z0-9._-]{3,50}$/',$user)) throw new RuntimeException('Usuario inválido.');
      if (!is_valid_role($role)) throw new RuntimeException('Rol inválido.');
      if (username_exists($pdo,$user,$id)) throw new RuntimeException('Ya existe otro usuario con ese nombre.');

      // Si se cambia rol del último admin a operator -> bloquear
      if ($role !== 'admin') {
        $current = $pdo->prepare('SELECT username, role FROM users WHERE userID=?');
        $current->execute([$id]);
        $row = $current->fetch();
        if ($row && $row['role']==='admin' && count_admins($pdo) <= 1) {
          throw new RuntimeException('No puedes quitar el último administrador.');
        }
      }

      $pdo->beginTransaction();
      $pdo->prepare('UPDATE users SET username=?, role=? WHERE userID=?')->execute([$user,$role,$id]);
      if ($pass !== '') {
        if (strlen($pass)<8) throw new RuntimeException('La nueva contraseña debe tener al menos 8 caracteres.');
        $hash = password_hash($pass, PASSWORD_DEFAULT);
        $pdo->prepare('UPDATE users SET password=? WHERE userID=?')->execute([$hash,$id]);
      }
      $pdo->commit();
      $success = 'Usuario actualizado.';

      // Si el admin cambia su propio username, actualizamos sesión
      if ($me && $user === $me) $_SESSION['username'] = $user;

    } elseif ($action === 'delete') {
      $id = (int)($_POST['id'] ?? 0);
      if ($id<=0) throw new RuntimeException('ID inválido.');

      $st = $pdo->prepare('SELECT username, role FROM users WHERE userID=?');
      $st->execute([$id]);
      $row = $st->fetch();
      if (!$row) throw new RuntimeException('Usuario no encontrado.');
      if ($row['username'] === $me) throw new RuntimeException('No puedes eliminar tu propio usuario.');
      if ($row['role']==='admin' && count_admins($pdo)<=1) throw new RuntimeException('No puedes eliminar al último administrador.');

      $pdo->prepare('DELETE FROM users WHERE userID=?')->execute([$id]);
      $success = 'Usuario eliminado.';
    } else {
      throw new RuntimeException('Acción no reconocida.');
    }
  } catch (Throwable $e) {
    $errors[] = $e->getMessage();
  }
}

/** Listado */
$users = $pdo->query('SELECT userID, username, role FROM users ORDER BY userID DESC')->fetchAll();
?>
<?php include __DIR__.'/partials/header.php'; ?>

<div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
  <h1 class="h4 m-0">Administración de usuarios</h1>
</div>

<?php if ($success): ?>
  <div class="alert alert-success"><?= h($success) ?></div>
<?php endif; ?>
<?php if ($errors): ?>
  <div class="alert alert-danger">
    <ul class="m-0 ps-3"><?php foreach ($errors as $er): ?><li><?= h($er) ?></li><?php endforeach; ?></ul>
  </div>
<?php endif; ?>

<div class="row g-3">
  <div class="col-lg-4">
    <div class="card p-3">
      <h2 class="h6 mb-3">Crear nuevo usuario</h2>
      <form method="post" class="vstack gap-2">
        <input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>">
        <input type="hidden" name="action" value="create">
        <div>
          <label class="form-label">Usuario</label>
          <input name="username" class="form-control" required maxlength="50" pattern="[A-Za-z0-9._-]{3,50}">
        </div>
        <div>
          <label class="form-label">Rol</label>
          <select name="role" class="form-select">
            <option value="operator">operator (solo lectura)</option>
            <option value="ingenieria">ingenieria</option>
            <option value="admin">admin</option>
          </select>
        </div>
        <div>
          <label class="form-label">Contraseña</label>
          <input type="password" name="password" class="form-control" minlength="8" required>
        </div>
        <div>
          <label class="form-label">Repetir contraseña</label>
          <input type="password" name="password2" class="form-control" minlength="8" required>
        </div>
        <button class="btn btn-primary mt-2"><i class="fa fa-user-plus me-1"></i> Crear</button>
      </form>
    </div>
  </div>

  <div class="col-lg-8">
    <div class="card p-3 table-density-comfort">
      <div class="d-flex justify-content-between align-items-center mb-2">
        <h2 class="h6 m-0">Usuarios existentes</h2>
        <input id="filter" class="form-control" placeholder="Buscar..." style="max-width:260px;">
      </div>
      <div class="table-wrap">
        <div class="table-scroll">
          <table class="table table-striped table-hover align-middle" id="usersTable">
            <thead>
              <tr>
                <th>ID</th>
                <th>Usuario</th>
                <th>Rol</th>
                <th style="width:260px;">Acciones</th>
              </tr>
            </thead>
            <tbody>
            <?php foreach ($users as $u): ?>
              <tr>
                <td><?= (int)$u['userID'] ?></td>
                <td>
                  <form method="post" class="d-flex gap-2 align-items-center flex-wrap">
                    <input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>">
                    <input type="hidden" name="action" value="update">
                    <input type="hidden" name="id" value="<?= (int)$u['userID'] ?>">
                    <input name="username" class="form-control form-control-sm" value="<?= h($u['username']) ?>" required maxlength="50" pattern="[A-Za-z0-9._-]{3,50}">
                </td>
                <td>
                    <select name="role" class="form-select form-select-sm">
                      <option value="operator" <?= $u['role']==='operator'?'selected':''; ?>>operator</option>
                      <option value="ingenieria" <?= $u['role']==='ingenieria'?'selected':''; ?>>ingenieria</option>
                      <option value="admin"    <?= $u['role']==='admin'?'selected':''; ?>>admin</option>
                    </select>
                </td>
                <td class="text-nowrap">
                    <input type="password" name="password" class="form-control form-control-sm" placeholder="Nueva contraseña (opcional)" minlength="8" style="max-width:200px; display:inline-block;">
                    <button class="btn btn-sm btn-primary ms-1"><i class="fa fa-save me-1"></i> Guardar</button>
                  </form>
                  <form method="post" class="d-inline" onsubmit="return confirm('¿Eliminar usuario?');">
                    <input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="id" value="<?= (int)$u['userID'] ?>">
                    <button class="btn btn-sm btn-danger"><i class="fa fa-trash me-1"></i> Eliminar</button>
                  </form>
                </td>
              </tr>
            <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>
      <small class="text-secondary">Nota: no puedes eliminarte a ti mismo ni dejar el sistema sin administradores.</small>
    </div>
  </div>
</div>

<script>
  const filter = document.getElementById('filter');
  filter?.addEventListener('input', ()=>{
    const q = filter.value.toLowerCase();
    document.querySelectorAll('#usersTable tbody tr').forEach(tr=>{
      tr.style.display = tr.textContent.toLowerCase().includes(q) ? '' : 'none';
    });
  });
</script>

<?php include __DIR__.'/partials/footer.php'; ?>