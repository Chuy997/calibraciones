<?php
session_start();
if (!isset($_SESSION['username']) || $_SESSION['role'] != 'admin') {
    header('Location: login.php');
    exit();
}

require 'config.php';
$conn = getConnection('admin');

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['id'])) {
    $id = $_POST['id'];

    // Llamar al procedimiento almacenado
    $sql = "CALL ReturnInstrumentToActive(?)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $id);

    if ($stmt->execute()) {
        header('Location: out_of_use.php');
        exit();
    } else {
        echo "Error al regresar el instrumento a uso: " . $stmt->error;
    }
} else {
    die("ID no proporcionado.");
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Regresar Instrumento a Uso</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <style>
        body {
            background-color: #121212;
            color: #e0e0e0;
        }

        .navbar, .card, .modal-content {
            background-color: #1e1e1e;
            color: #e0e0e0;
        }

        .form-control {
            background-color: #2c2c2c;
            color: #e0e0e0;
            border: 1px solid #444444;
        }

        .form-control::placeholder {
            color: #e0e0e0;
        }

        .btn-primary {
            background-color: #007bff;
            border-color: #007bff;
            color: #ffffff;
        }

        .btn-primary:hover {
            background-color: #0056b3;
            border-color: #004085;
        }
        
        .container {
            margin-top: 20px;
        }
    </style>
</head>
<body>
    <?php include 'menu.php'; ?>
    <div class="container">
        <h1 class="my-4">Regresar Instrumento a Uso</h1>
        <form method="POST" action="return_to_active.php">
            <input type="hidden" name="id" value="<?php echo htmlspecialchars($_GET['id']); ?>">
            <button type="submit" class="btn btn-primary">Regresar a Uso</button>
        </form>
    </div>
</body>
</html>
<?php
$conn->close();
?>
