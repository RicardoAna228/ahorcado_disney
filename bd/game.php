<?php
session_start();
if (!isset($_SESSION['id_usuario'])) {
    header('Location: ../index.php');
    exit;
}
// Si tiene sesión, redirige al juego HTML
header('Location: html/game.html');
exit;
?>
