<?php
session_start();
if (!isset($_SESSION['username']) || $_SESSION['role'] != 'admin') {
    header('Location: login.php');
    exit();
}

require 'config.php';
$conn = getConnection('admin');

// Verificar si el ID se ha proporcionado mediante GET (al cargar el formulario) o POST (al enviar el formulario)
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['id']) && isset($_POST['ReasonForRemoval'])) {
        $id = $_POST['id'];
        $reason = $_POST['ReasonForRemoval'];

        // Llamar al procedimiento almacenado
        $sql = "CALL MoveInstrumentOutOfUse(?, ?)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ss", $id, $reason);

        if ($stmt->execute()) {
            header('Location: admin.php');
            exit();
        } else {
            echo "Error al mover el instrumento fuera de uso: " . $stmt->error;
        }
    } else {
        die("ID o razón no proporcionados.");
    }
} elseif (isset($_GET['id'])) {
    $id = $_GET['id'];
} else {
    die("ID no proporcionado.");
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Mover Instrumento Fuera de Uso</title>
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
        <h1 class="my-4">Mover Instrumento Fuera de Uso</h1>
        <form method="POST" action="move_out_of_use.php">
            <input type="hidden" name="id" value="<?php echo htmlspecialchars($id); ?>">
            <div class="form-group">
                <label for="ReasonForRemoval">Razón</label>
                <select class="form-control" id="ReasonForRemoval" name="ReasonForRemoval" required>
                    <option value="Obsoleto">Obsoleto</option>
                    <option value="Fuera de Calibración">Fuera de Calibración</option>
                    <option value="No Funciona">No Funciona</option>
                </select>
            </div>
            <button type="submit" class="btn btn-primary">Mover Fuera de Uso</button>
        </form>
    </div>
</body>
</html>
<?php
$conn->close();
?>
