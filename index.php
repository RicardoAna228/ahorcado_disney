<?php
session_start();
require_once 'bd/config.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $nombre = isset($_POST['nombre']) ? trim($_POST['nombre']) : '';

    // VALIDACIONES
    if ($nombre === '') {
        $error = 'Por favor ingresa tu nombre.';
    } elseif (strlen($nombre) > 50) {
        $error = 'Máximo 50 caracteres.';
    } else {

        try {
            $conn = connect();

            $stmt = $conn->prepare("
                SELECT id_usuario, nombre 
                FROM usuarios 
                WHERE nombre = :nombre AND activo = 1
            ");

            $stmt->bindParam(':nombre', $nombre, PDO::PARAM_STR);
            $stmt->execute();

            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($user) {

                $_SESSION['id_usuario'] = $user['id_usuario'];
                $_SESSION['nombre'] = $user['nombre'];

                doLog($user['nombre'], 'Inicio de sesión');

                header('Location: html/game.html');
                exit;

            } else {
                $error = 'Usuario no encontrado.';
            }

        } catch (PDOException $e) {
            $error = 'Error en la base de datos: ' . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>MiniJuegos - Login</title>
    <link rel="stylesheet" href="css/index.css">
</head>
<body>

<header>
    <h1>Mini juegos</h1>
</header>

<div class="login-card">
    <h2>¡Bienvenida!</h2>
    <p>Ingresa tu nombre para jugar</p>

    <form method="POST" id="loginForm">
        <input type="text"
               name="nombre"
               placeholder="Tu nombre..."
               maxlength="50"
               required
               value="<?php echo isset($_POST['nombre']) ? htmlspecialchars($_POST['nombre']) : ''; ?>">

        <div style="margin-top: 20px;">
            <button type="submit">Jugar</button>
        </div>
    </form>

    <?php if ($error): ?>
        <p style="color:red;"><?php echo htmlspecialchars($error); ?></p>
    <?php endif; ?>

    <div class="usuarios-hint">
        Usuarios disponibles:<br><br>
        <span>Camilo</span>
        <span>Lina</span>
        <span>Mario</span>
        <span>Nicol</span>
    </div>
</div>

<script>
document.getElementById('loginForm').addEventListener('submit', function(e) {
    const input = document.querySelector('input[name="nombre"]');

    if (input.value.trim() === '') {
        e.preventDefault();
        alert('Ingresa un nombre válido');
        input.focus();
    }
});
</script>

</body>
</html>