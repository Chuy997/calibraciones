<?php
require __DIR__.'/config.php';
secure_session_start();

$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validar CSRF
    if (!isset($_POST['csrf']) || !csrf_validate($_POST['csrf'])) {
        $error = 'Sesión expirada, intenta de nuevo.';
    } else {
        $username = trim($_POST['username'] ?? '');
        $password = (string)($_POST['password'] ?? '');

        if ($username === '' || $password === '') {
            $error = 'Usuario y contraseña son obligatorios.';
        } else {
            $stmt = pdo()->prepare('SELECT id, username, password, role FROM users WHERE username = ?');
            $stmt->execute([$username]);
            $user = $stmt->fetch();

            if ($user && password_verify($password, $user['password'])) {
                // Sesión
                $_SESSION['username'] = $user['username'];
                $_SESSION['role']     = $user['role'];
                session_regenerate_id(true);

                // Redirección por rol
                if ($user['role'] === 'admin') {
                    header('Location: index.php'); // dashboard
                } else {
                    header('Location: index.php'); // o a consulta.php si luego reactivamos esa vista
                }
                exit();
            } else {
                $error = 'Credenciales incorrectas.';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login — Calibraciones</title>
    <style>
        body {
            margin: 0; padding: 0; font-family: sans-serif;
            background: #121212; color: #ffffff;
            display: flex; justify-content: center; align-items: center; height: 100vh;
        }
        .login-box {
            width: 400px; padding: 32px; background: rgba(0,0,0,.5);
            box-shadow: 0 15px 25px rgba(0,0,0,.5); border-radius: 10px; text-align: center;
        }
        .login-box h2 { margin: 0 0 24px; }
        .form-row { text-align: left; margin-bottom: 24px; }
        .form-row label { display: block; margin-bottom: 6px; color: #bbb; font-size: 14px; }
        .form-row input {
            width: 100%; padding: 10px 12px; font-size: 16px; color: #fff;
            border: 1px solid #333; border-radius: 6px; background: #1a1a1a; outline: none;
        }
        .btn {
            width: 100%; padding: 12px 16px; border: none; border-radius: 6px; cursor: pointer;
            background: #03e9f4; color: #000; font-weight: 600; text-transform: uppercase; letter-spacing: 1px;
        }
        .btn:hover { filter: brightness(1.05); }
        .error { margin-top: 16px; color: #ff6b6b; }
    </style>
</head>
<body>
    <div class="login-box">
        <h2>Iniciar sesión</h2>
        <form method="post" action="">
            <input type="hidden" name="csrf" value="<?php echo htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8'); ?>">
            <div class="form-row">
                <label for="username">Usuario</label>
                <input type="text" id="username" name="username" autocomplete="username" required>
            </div>
            <div class="form-row">
                <label for="password">Contraseña</label>
                <input type="password" id="password" name="password" autocomplete="current-password" required>
            </div>
            <button type="submit" class="btn">Entrar</button>
            <?php if ($error): ?>
                <div class="error"><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></div>
            <?php endif; ?>
        </form>
    </div>
</body>
</html>
