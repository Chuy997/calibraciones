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
            $stmt = pdo()->prepare('SELECT userID AS id, username, password, role FROM users WHERE username = ?');
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
<html lang="es" data-bs-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login — Calibraciones</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
            background: linear-gradient(135deg, #0f0c29 0%, #302b63 50%, #24243e 100%);
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 1rem;
            position: relative;
            overflow: hidden;
        }
        
        body::before {
            content: '';
            position: absolute;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background: radial-gradient(circle, rgba(102, 126, 234, 0.1) 0%, transparent 70%);
            animation: pulse 15s ease-in-out infinite;
        }
        
        @keyframes pulse {
            0%, 100% { transform: scale(1); opacity: 0.3; }
            50% { transform: scale(1.1); opacity: 0.5; }
        }
        
        .login-container {
            position: relative;
            z-index: 1;
            animation: fadeInUp 0.8s ease;
        }
        
        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .login-box {
            width: 100%;
            max-width: 420px;
            padding: 2.5rem;
            background: rgba(255, 255, 255, 0.05);
            backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 24px;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.4), 0 0 60px rgba(102, 126, 234, 0.2);
        }
        
        .logo-container {
            text-align: center;
            margin-bottom: 2rem;
        }
        
        .logo-icon {
            width: 64px;
            height: 64px;
            margin: 0 auto 1rem;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: 16px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2rem;
            box-shadow: 0 8px 24px rgba(102, 126, 234, 0.3);
            animation: float 3s ease-in-out infinite;
        }
        
        @keyframes float {
            0%, 100% { transform: translateY(0); }
            50% { transform: translateY(-10px); }
        }
        
        .login-box h2 {
            margin: 0 0 0.5rem;
            font-size: 1.75rem;
            font-weight: 600;
            text-align: center;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }
        
        .login-subtitle {
            text-align: center;
            color: #9ca3af;
            font-size: 0.9rem;
            margin-bottom: 2rem;
        }
        
        .form-row {
            margin-bottom: 1.5rem;
            position: relative;
        }
        
        .form-row label {
            display: block;
            margin-bottom: 0.5rem;
            color: #d1d5db;
            font-size: 0.875rem;
            font-weight: 500;
        }
        
        .input-wrapper {
            position: relative;
        }
        
        .input-icon {
            position: absolute;
            left: 1rem;
            top: 50%;
            transform: translateY(-50%);
            color: #6b7280;
            transition: color 0.3s ease;
        }
        
        .form-row input {
            width: 100%;
            padding: 0.875rem 1rem 0.875rem 3rem;
            font-size: 1rem;
            color: #fff;
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 12px;
            background: rgba(255, 255, 255, 0.05);
            outline: none;
            transition: all 0.3s ease;
        }
        
        .form-row input:focus {
            border-color: rgba(102, 126, 234, 0.5);
            background: rgba(255, 255, 255, 0.08);
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }
        
        .form-row input:focus + .input-icon {
            color: #667eea;
        }
        
        .btn {
            width: 100%;
            padding: 1rem;
            border: none;
            border-radius: 12px;
            cursor: pointer;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: #fff;
            font-weight: 600;
            font-size: 1rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            transition: all 0.3s ease;
            box-shadow: 0 4px 15px rgba(102, 126, 234, 0.4);
            position: relative;
            overflow: hidden;
        }
        
        .btn::before {
            content: '';
            position: absolute;
            top: 50%;
            left: 50%;
            width: 0;
            height: 0;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.2);
            transform: translate(-50%, -50%);
            transition: width 0.6s, height 0.6s;
        }
        
        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(102, 126, 234, 0.5);
        }
        
        .btn:hover::before {
            width: 300px;
            height: 300px;
        }
        
        .btn:active {
            transform: translateY(0);
        }
        
        .error {
            margin-top: 1.5rem;
            padding: 1rem;
            background: rgba(239, 68, 68, 0.1);
            border: 1px solid rgba(239, 68, 68, 0.3);
            border-radius: 12px;
            color: #fca5a5;
            font-size: 0.875rem;
            text-align: center;
            animation: shake 0.5s;
        }
        
        @keyframes shake {
            0%, 100% { transform: translateX(0); }
            25% { transform: translateX(-10px); }
            75% { transform: translateX(10px); }
        }
        
        .footer-text {
            margin-top: 2rem;
            text-align: center;
            color: #6b7280;
            font-size: 0.75rem;
        }
        
        @media (max-width: 480px) {
            .login-box {
                padding: 2rem 1.5rem;
            }
            
            .login-box h2 {
                font-size: 1.5rem;
            }
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-box">
            <div class="logo-container">
                <div class="logo-icon">
                    <i class="fa fa-flask"></i>
                </div>
                <h2>Test Instruments</h2>
                <p class="login-subtitle">Sistema de gestión de activos</p>
            </div>
            
            <form method="post" action="">
                <input type="hidden" name="csrf" value="<?php echo htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8'); ?>">
                
                <div class="form-row">
                    <label for="username">
                        <i class="fa fa-user me-1"></i> Usuario
                    </label>
                    <div class="input-wrapper">
                        <input type="text" id="username" name="username" autocomplete="username" required autofocus>
                        <i class="fa fa-user input-icon"></i>
                    </div>
                </div>
                
                <div class="form-row">
                    <label for="password">
                        <i class="fa fa-lock me-1"></i> Contraseña
                    </label>
                    <div class="input-wrapper">
                        <input type="password" id="password" name="password" autocomplete="current-password" required>
                        <i class="fa fa-lock input-icon"></i>
                    </div>
                </div>
                
                <button type="submit" class="btn">
                    <i class="fa fa-right-to-bracket me-2"></i> Iniciar Sesión
                </button>
                
                <?php if ($error): ?>
                    <div class="error">
                        <i class="fa fa-circle-exclamation me-2"></i>
                        <?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?>
                    </div>
                <?php endif; ?>
            </form>
            
            <div class="footer-text">
                <i class="fa fa-shield-halved me-1"></i>
                Acceso seguro protegido
            </div>
        </div>
    </div>
</body>
</html>
