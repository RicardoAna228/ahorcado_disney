<?php
session_start();
header('Content-Type: application/json');

require_once 'config.php';  // Está en la misma carpeta bd/

// Solo POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Método no permitido']);
    exit;
}

// Verificar sesión
if (!isset($_SESSION['id_usuario'])) {
    http_response_code(401);
    echo json_encode(['error' => 'No hay sesión activa']);
    exit;
}

$puntaje = isset($_POST['puntaje']) ? filter_var($_POST['puntaje'], FILTER_VALIDATE_INT) : false;

if ($puntaje === false || $puntaje < 0) {
    http_response_code(400);
    echo json_encode(['error' => 'Puntaje inválido']);
    exit;
}

try {
    $conn = connect();
    $stmt = $conn->prepare('INSERT INTO puntajes (id_usuario, puntaje) VALUES (:id_usuario, :puntaje)');
    $stmt->execute([
        ':id_usuario' => (int) $_SESSION['id_usuario'],
        ':puntaje' => $puntaje,
    ]);

    $nombre_usuario = isset($_SESSION['nombre']) ? $_SESSION['nombre'] : 'Usuario';
    doLog($nombre_usuario, "Puntaje guardado: {$puntaje}");

} catch (PDOException $e) {
    http_response_code(500);
    error_log('Error al guardar puntaje: ' . $e->getMessage());
    echo json_encode(['error' => 'Error al guardar puntaje']);
} finally {
    $conn = null;
}
?>