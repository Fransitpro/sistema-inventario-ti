<?php
require_once '../templates/header.php';

$id_sucursal_usuario = $_SESSION['user_sucursal_id'] ?? null;
?>

<h1 class="h2 mb-1"><i class="bi bi-file-earmark-bar-graph me-1"></i>Reportes</h1>
<p class="text-muted mb-4">
    Descarga los reportes en Excel o en PDF A4 apaisado, o envíalos directo a la impresora.
    Cada reporte incluye todos los registros del filtro actual.
</p>

<?php include __DIR__ . '/../templates/reportes.php'; ?>

<?php require_once '../templates/footer.php'; ?>
