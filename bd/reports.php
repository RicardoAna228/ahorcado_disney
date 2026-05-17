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
    private $rows = [];
    private $usuarios = [];

    public function __construct() {
        $this->conn = connect();
        $this->filtro_usuario = isset($_GET['usuario']) ? intval($_GET['usuario']) : 0;
        $this->filtro_periodo = isset($_GET['periodo']) ? $_GET['periodo'] : 'todo';

        if (!in_array($this->filtro_periodo, ['semana', 'mes', 'todo'], true)) {
            $this->filtro_periodo = 'todo';
        }

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
        doLog($nombre_usuario, "Consulta de reporte: periodo={$this->filtro_periodo}, usuario=" .
            ($this->filtro_usuario > 0 ? $this->filtro_usuario : 'todos'));
    }

    private function getFiltroFechaSql($alias = 'p') {
        switch ($this->filtro_periodo) {
            case 'semana':
                return " AND {$alias}.fecha_registro >= DATE_SUB(CURDATE(), INTERVAL WEEKDAY(CURDATE()) DAY)
                         AND {$alias}.fecha_registro < DATE_ADD(DATE_SUB(CURDATE(), INTERVAL WEEKDAY(CURDATE()) DAY), INTERVAL 7 DAY)";
            case 'mes':
                return " AND {$alias}.fecha_registro >= DATE_FORMAT(CURDATE(), '%Y-%m-01')
                         AND {$alias}.fecha_registro < DATE_ADD(DATE_FORMAT(CURDATE(), '%Y-%m-01'), INTERVAL 1 MONTH)";
            default:
                return '';
        }
    }

    private function filtrarPorUsuario() {
        $sql = "SELECT u.nombre, p.puntaje, p.fecha_registro
                FROM puntajes p
                JOIN usuarios u ON p.id_usuario = u.id_usuario
                WHERE p.id_usuario = :usuario" . $this->getFiltroFechaSql('p') . "
                ORDER BY p.fecha_registro DESC";

        $stmt = $this->conn->prepare($sql);
        $stmt->execute([':usuario' => $this->filtro_usuario]);

        $this->rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    private function filtrarTodos() {
        $filtroFechaP = $this->getFiltroFechaSql('p');
        $filtroFechaP2 = $this->getFiltroFechaSql('p2');

        $sql = "SELECT u.nombre, p.puntaje, p.fecha_registro
                FROM puntajes p
                JOIN usuarios u ON p.id_usuario = u.id_usuario
                WHERE p.id_puntaje = (
                    SELECT p2.id_puntaje
                    FROM puntajes p2
                    WHERE p2.id_usuario = p.id_usuario" . $filtroFechaP2 . "
                    ORDER BY p2.puntaje DESC, p2.fecha_registro DESC, p2.id_puntaje DESC
                    LIMIT 1
                )" . $filtroFechaP . "
                ORDER BY p.puntaje DESC, p.fecha_registro DESC
                LIMIT 50";

        $stmt = $this->conn->prepare($sql);
        $stmt->execute();
        $this->rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
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
        $this->conn = null;
    }
}

$reporte = new ReportesController();
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Disney - Reportes</title>
    <link rel="stylesheet" href="../css/reports.css">
</head>
<body>
<header>
    <h1>Reportes de Puntajes</h1>
    <nav>
        <div class="nav-links">
            <a href="../html/game.html">← Volver al juego</a>
        </div>
    </nav>
</header>

<main class="container">
    <h2>Estadísticas de partidas</h2>

    <form class="filtros" method="GET">
        <label>
            Usuario
            <select name="usuario">
                <option value="0">Todos los usuarios</option>
                <?php foreach ($reporte->getUsuarios() as $u): ?>
                    <option value="<?= htmlspecialchars($u['id_usuario']) ?>" <?= $reporte->getFiltroUsuario() == $u['id_usuario'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($u['nombre']) ?>
                    </option>
                <?php endforeach; ?>
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