<?php
session_start();

// Verificar que el usuario haya iniciado sesión
if (!isset($_SESSION['id_usuario'])) {
    header('Location: index.php');
    exit;
}

// Ruta del archivo de logs
$log_file = __DIR__ . '/../logs.txt';

// Verificar si el archivo existe
if (!file_exists($log_file)) {
    die('El archivo de logs no existe aún. No hay registros disponibles.');
}

// Configurar cabeceras para forzar la descarga
header('Content-Type: text/plain');
header('Content-Disposition: attachment; filename="logs_' . date('Y-m-d_H-i-s') . '.txt"');
header('Content-Length: ' . filesize($log_file));
header('Cache-Control: no-cache, must-revalidate');
header('Pragma: public');

// Leer y enviar el archivo
readfile($log_file);
exit;
?>