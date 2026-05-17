<?php
session_start();
if (!isset($_SESSION['id_usuario'])) {
    header('Location: ../index.php');
    exit;
}

require_once 'config.php';

// Función para escribir log (si no existe)
if (!function_exists('escribirLog')) {
    function escribirLog($usuario, $accion) {
        $log_file = 'logs.txt';
        $fecha = date('Y-m-d H:i:s');
        $registro = "[$fecha] Usuario: $usuario - Acción: $accion" . PHP_EOL;
        file_put_contents($log_file, $registro, FILE_APPEND);
    }
}

class ReportesController {
    private $conn;
    private $filtro_usuario;
    private $filtro_periodo;
    private $rows;
    private $usuarios;

    public function __construct() {
        $this->conn = conectar();
        $this->filtro_usuario = isset($_GET['usuario']) ? intval($_GET['usuario']) : 0;
        $this->filtro_periodo = isset($_GET['periodo']) ? $_GET['periodo'] : 'todo';
        $this->procesarReporte();
    }

    private function procesarReporte() {
        $this->usuarios = $this->conn->query("SELECT id_usuario, nombre FROM usuarios WHERE activo = 1 ORDER BY nombre");

        if ($this->filtro_usuario > 0) {
            $this->filtrarPorUsuario();
        } else {
            $this->filtrarTodos();
        }

        $nombre_usuario = isset($_SESSION['nombre']) ? $_SESSION['nombre'] : 'Usuario';
        if (function_exists('escribirLog')) {
            escribirLog($nombre_usuario, "Consulta de reporte: periodo={$this->filtro_periodo}, usuario=" .
                    ($this->filtro_usuario > 0 ? $this->filtro_usuario : 'todos'));
        }
    }

    private function filtrarPorUsuario() {
        switch ($this->filtro_periodo) {
            case 'semana':
                $sql = "SELECT u.nombre, p.puntaje, p.fecha_registro
                        FROM puntajes p 
                        JOIN usuarios u ON p.id_usuario = u.id_usuario
                        WHERE p.id_usuario = ?
                          AND YEARWEEK(p.fecha_registro, 1) = YEARWEEK(NOW(), 1)
                        ORDER BY p.fecha_registro DESC";
                break;
            case 'mes':
                $sql = "SELECT u.nombre, p.puntaje, p.fecha_registro
                        FROM puntajes p 
                        JOIN usuarios u ON p.id_usuario = u.id_usuario
                        WHERE p.id_usuario = ?
                          AND MONTH(p.fecha_registro) = MONTH(NOW())
                          AND YEAR(p.fecha_registro) = YEAR(NOW())
                        ORDER BY p.fecha_registro DESC";
                break;
            default:
                $sql = "SELECT u.nombre, p.puntaje, p.fecha_registro
                        FROM puntajes p 
                        JOIN usuarios u ON p.id_usuario = u.id_usuario
                        WHERE p.id_usuario = ?
                        ORDER BY p.fecha_registro DESC";
                break;
        }

        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param("i", $this->filtro_usuario);
        $stmt->execute();
        $result = $stmt->get_result();

        $this->rows = [];
        while ($r = $result->fetch_assoc()) {
            $this->rows[] = $r;
        }
        $stmt->close();
    }

    private function filtrarTodos() {
        switch ($this->filtro_periodo) {
            case 'semana':
                $sql = "SELECT u.nombre, p.puntaje, p.fecha_registro
                        FROM puntajes p 
                        JOIN usuarios u ON p.id_usuario = u.id_usuario
                        WHERE YEARWEEK(p.fecha_registro, 1) = YEARWEEK(NOW(), 1)
                        ORDER BY p.puntaje DESC
                        LIMIT 50";
                break;
            case 'mes':
                $sql = "SELECT u.nombre, p.puntaje, p.fecha_registro
                        FROM puntajes p 
                        JOIN usuarios u ON p.id_usuario = u.id_usuario
                        WHERE MONTH(p.fecha_registro) = MONTH(NOW())
                          AND YEAR(p.fecha_registro) = YEAR(NOW())
                        ORDER BY p.puntaje DESC
                        LIMIT 50";
                break;
            default:
                $sql = "SELECT u.nombre, 
                               MAX(p.puntaje) AS puntaje,
                               MAX(p.fecha_registro) AS fecha_registro
                        FROM puntajes p 
                        JOIN usuarios u ON p.id_usuario = u.id_usuario
                        GROUP BY u.id_usuario, u.nombre 
                        ORDER BY puntaje DESC
                        LIMIT 50";
                break;
        }

        $stmt = $this->conn->prepare($sql);
        $stmt->execute();
        $result = $stmt->get_result();

        $this->rows = [];
        while ($r = $result->fetch_assoc()) {
            $this->rows[] = $r;
        }
        $stmt->close();
    }

    public function getRows() { return $this->rows; }
    public function getUsuarios() { return $this->usuarios; }
    public function getFiltroUsuario() { return $this->filtro_usuario; }
    public function getFiltroPeriodo() { return $this->filtro_periodo; }
    public function getTotalPuntaje() {
        $total = 0;
        foreach ($this->rows as $row) {
            $total += intval($row['puntaje']);
        }
        return $total;
    }

    public function close() {
        if ($this->conn) {
            $this->conn->close();
        }
    }
}

$reporte = new ReportesController();
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Reportes – Mini juegos NV</title>
    <link rel="stylesheet" href="../css/reportes.css">
</head>
<body>
<header>
    <h1>Reportes de Puntajes</h1>
    <a href="../html/juego.html">← Volver al juego</a>
</header>

<div class="container">
    <h2>Estadísticas de partidas</h2>

    <form class="filtros" method="GET">
        <label>
            Usuario
            <select name="usuario">
                <option value="0">Todos los usuarios</option>
                <?php
                if ($reporte->getUsuarios() && $reporte->getUsuarios()->num_rows > 0):
                    mysqli_data_seek($reporte->getUsuarios(), 0);
                    while ($u = $reporte->getUsuarios()->fetch_assoc()):
                        ?>
                        <option value="<?= $u['id_usuario'] ?>" <?= $reporte->getFiltroUsuario() == $u['id_usuario'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($u['nombre']) ?>
                        </option>
                    <?php endwhile; endif; ?>
            </select>
        </label>
        <input type="hidden" name="periodo" value="<?= htmlspecialchars($reporte->getFiltroPeriodo()) ?>">
        <button type="submit">Filtrar</button>
    </form>

    <div class="periodo-tabs">
        <?php
        $periodos = ['semana' => '🗓 Esta semana', 'mes' => 'Este mes', 'todo' => 'Todo el tiempo'];
        foreach ($periodos as $key => $label):
            $url = '?usuario=' . $reporte->getFiltroUsuario() . '&periodo=' . $key;
            ?>
            <a href="<?= $url ?>" class="<?= $reporte->getFiltroPeriodo() === $key ? 'activo' : '' ?>">
                <?= $label ?>
            </a>
        <?php endforeach; ?>
    </div>

    <?php if (empty($reporte->getRows())): ?>
        <div class="empty"><span></span>No hay registros para este filtro.<br>¡Juega algunas partidas para ver estadísticas!</div>
    <?php else: ?>
        <table>
            <thead>
            <tr><th>#</th><th>Usuario</th><th>Puntaje</th><th>Fecha</th></tr>
            </thead>
            <tbody>
            <?php foreach ($reporte->getRows() as $i => $row): ?>
                <tr>
                    <td><?= $i + 1 ?></td>
                    <td><strong><?= htmlspecialchars($row['nombre']) ?></strong></td>
                    <td><span class="puntaje-badge"><?= number_format($row['puntaje']) ?></span></td>
                    <td><?= date('d/m/Y H:i', strtotime($row['fecha_registro'])) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
            <div class="total-puntaje">Total de registros: <?= count($reporte->getRows()) ?> | Sumatoria de puntajes: <?= number_format($reporte->getTotalPuntaje()) ?></div>
        <?php endif; ?>
</div>

<?php $reporte->close(); ?>
</body>
</html>