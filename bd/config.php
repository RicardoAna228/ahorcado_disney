<?php
// CONFIGURACIÓN DE BASE DE DATOS
define('DB_HOST', 'localhost');
define('DB_NAME', 'minijuegos');
define('DB_USER', 'root');
define('DB_PASS', ''); 

function connect() {
    try {
        $conn = new PDO(
            "mysql:host=127.0.0.1;port=3307;dbname=" . DB_NAME . ";charset=utf8mb4",
            DB_USER,
            DB_PASS
        );

        $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        return $conn;

    } catch (PDOException $e) {
        die("Error de conexión: " . $e->getMessage());
    }
}

// LOGS
define('LOG_FILE', __DIR__ . '/../logs.txt');

function doLog($user, $event) {
    $fecha = date('Y-m-d H:i:s');
    $linea = "[$fecha] Usuario: $user - Acción: $event" . PHP_EOL;

    file_put_contents(LOG_FILE, $linea, FILE_APPEND | LOCK_EX);
}
?>