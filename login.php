<?php
session_start();
require 'config.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = $_POST['username'];
    $password = $_POST['password'];

    $conn = getConnection('admin'); // Use admin connection for login verification

    $stmt = $conn->prepare("SELECT * FROM users WHERE username = ?");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        if (password_verify($password, $row['password'])) {
            $_SESSION['username'] = $username;
            $_SESSION['role'] = $row['role']; // Assuming 'role' is a column in your users table
            
            // Redireccionar según el rol del usuario
            if ($_SESSION['role'] == 'admin') {
                header('Location: index.php');
            } elseif ($_SESSION['role'] == 'consulta') {
                header('Location: consulta.php');
            }
            exit();
        } else {
            $error = "Credenciales incorrectas.";
        }
    } else {
        $error = "Credenciales incorrectas.";
    }

    $stmt->close();
    $conn->close();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    
    <title>Login</title>
    <style>
        body {
            margin: 0;
            padding: 0;
            font-family: sans-serif;
            background: #121212;
            color: #ffffff;
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh;
        }

        .login-box {
            position: absolute;
            top: 50%;
            left: 50%;
            width: 400px;
            padding: 40px;
            transform: translate(-50%, -50%);
            background: rgba(0,0,0,.5);
            box-shadow: 0 15px 25px rgba(0, 0, 0, 0.5);
            border-radius: 10px;
            text-align: center;
        }

        .login-box h2 {
            margin: 0 0 30px;
            padding: 0;
            color: #ffffff;
            text-align: center;
        }

        .login-box .user-box {
            position: relative;
        }

        .login-box .user-box input {
            width: 100%;
            padding: 10px 0;
            font-size: 16px;
            color: #ffffff;
            margin-bottom: 30px;
            border: none;
            border-bottom: 1px solid #ffffff;
            outline: none;
            background: transparent;
        }

        .login-box .user-box label {
            position: absolute;
            top: 0;
            left: 0;
            padding: 10px 0;
            font-size: 16px;
            color: #ffffff;
            pointer-events: none;
            transition: 0.5s;
        }

        .login-box .user-box input:focus ~ label,
        .login-box .user-box input:valid ~ label {
            top: -20px;
            left: 0;
            color: #03e9f4;
            font-size: 12px;
        }

        .login-box form a {
            position: relative;
            display: inline-block;
            padding: 10px 20px;
            color: #03e9f4;
            font-size: 16px;
            text-decoration: none;
            text-transform: uppercase;
            overflow: hidden;
            margin-top: 40px;
            letter-spacing: 4px;
            transition: 0.5s;
        }

        .login-box a:hover {
            background: #03e9f4;
            color: #fff;
            border-radius: 5px;
            box-shadow: 0 0 5px #03e9f4,
                        0 0 25px #03e9f4,
                        0 0 50px #03e9f4,
                        0 0 100px #03e9f4;
        }

        .login-box a span {
            position: absolute;
            display: block;
        }

        .login-box a span:nth-child(1) {
            top: 0;
            left: 100%;
            width: 100%;
            height: 2px;
            background: linear-gradient(90deg, transparent, #03e9f4);
            animation: btn-anim1 1s linear infinite;
        }

        @keyframes btn-anim1 {
            0% {
                left: 100%;
            }
            50%, 100% {
                left: 0;
            }
        }

        .login-box a span:nth-child(2) {
            top: -100%;
            right: 0;
            width: 2px;
            height: 100%;
            background: linear-gradient(180deg, transparent, #03e9f4);
            animation: btn-anim2 1s linear infinite;
            animation-delay: 0.25s;
        }

        @keyframes btn-anim2 {
            0% {
                top: -100%;
            }
            50%, 100% {
                top: 0;
            }
        }

        .login-box a span:nth-child(3) {
            bottom: 0;
            right: 100%;
            width: 100%;
            height: 2px;
            background: linear-gradient(270deg, transparent, #03e9f4);
            animation: btn-anim3 1s linear infinite;
            animation-delay: 0.5s;
        }

        @keyframes btn-anim3 {
            0% {
                right: 100%;
            }
            50%, 100% {
                right: 0;
            }
        }

        .login-box a span:nth-child(4) {
            bottom: -100%;
            left: 0;
            width: 2px;
            height: 100%;
            background: linear-gradient(360deg, transparent, #03e9f4);
            animation: btn-anim4 1s linear infinite;
            animation-delay: 0.75s;
        }

        @keyframes btn-anim4 {
            0% {
                bottom: -100%;
            }
            50%, 100% {
                bottom: 0;
            }
        }

        p {
            color: #ff4d4d;
        }
    </style>
</head>
<body>
    <div class="login-box">
        <h2>Login</h2>
        <form method="post" action="">
            <div class="user-box">
                <input type="text" id="username" name="username" required>
                <label for="username">Username</label>
            </div>
            <div class="user-box">
                <input type="password" id="password" name="password" required>
                <label for="password">Password</label>
            </div>
            <a href="#">
                <span></span>
                <span></span>
                <span></span>
                <span></span>
                <button type="submit" style="background: none; border: none; color: inherit; font: inherit; cursor: pointer; outline: inherit;">Login</button>
            </a>
            <?php if (isset($error)): ?>
                <p><?php echo $error; ?></p>
            <?php endif; ?>
        </form>
    </div>
</body>
</html>
