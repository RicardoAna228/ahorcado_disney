<?php
session_start();
header('Content-Type: application/json');

require_once 'Config.php';  // Está en la misma carpeta bd/

// Solo POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['error' => 'Método no permitido']);
    exit;
}

// Verificar sesión
if (!isset($_SESSION['id_usuario'])) {
    echo json_encode(['error' => 'No hay sesión activa']);
    exit;
}

$puntaje = isset($_POST['puntaje']) ? intval($_POST['puntaje']) : 0;

if ($puntaje <= 0) {
    echo json_encode(['error' => 'Puntaje inválido']);
    exit;
}

$conn = conectar();
$stmt = $conn->prepare("INSERT INTO puntajes (id_usuario, puntaje) VALUES (?, ?)");
$stmt->bind_param('ii', $_SESSION['id_usuario'], $puntaje);

if ($stmt->execute()) {
    echo json_encode(['ok' => true, 'mensaje' => 'Puntaje guardado']);
} else {
    echo json_encode(['error' => 'Error al guardar']);
}

$conn->close();
?>