<?php
require_once '../templates/header.php';

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    echo '<div class="alert alert-danger">Error: ID de equipo no válido.</div>';
    require_once '../templates/footer.php';
    exit();
}
$id_equipo = (int)$_GET['id'];

// Consulta 1: Obtener los detalles del equipo
$sql_equipo = "SELECT e.*, s.nombre AS sucursal_nombre, t.nombre AS tipo_nombre, ma.nombre as marca_nombre, mo.nombre as modelo_nombre
               FROM equipos e
               LEFT JOIN sucursales s ON e.id_sucursal = s.id
               LEFT JOIN tipos_equipo t ON e.id_tipo_equipo = t.id
               LEFT JOIN marcas ma ON e.id_marca = ma.id
               LEFT JOIN modelos mo ON e.id_modelo = mo.id
               WHERE e.id = ?";
$stmt_equipo = $conexion->prepare($sql_equipo);
$stmt_equipo->bind_param("i", $id_equipo);
$stmt_equipo->execute();
$equipo = $stmt_equipo->get_result()->fetch_assoc();

if (!$equipo) {
    echo '<div class="alert alert-warning">Equipo no encontrado.</div>';
    require_once '../templates/footer.php';
    exit();
}

// Consulta 2: Obtener el historial de reparaciones de ESE equipo
$sql_reparaciones = "SELECT * FROM reparaciones WHERE id_equipo = ? ORDER BY fecha_ingreso DESC";
$stmt_reparaciones = $conexion->prepare($sql_reparaciones);
$stmt_reparaciones->bind_param("i", $id_equipo);
$stmt_reparaciones->execute();
$historial_reparaciones = $stmt_reparaciones->get_result();
?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <h1 class="h2">Detalle de Equipo</h1>
    <a href="equipos.php" class="btn btn-secondary"><i class="bi bi-arrow-left me-2"></i> Volver al Listado</a>
</div>

<div class="card mb-4">
    <div class="card-header">
        Información General del Equipo: <?php echo htmlspecialchars($equipo['codigo_inventario'] ?? 'N/A'); ?>
    </div>
    <div class="card-body">
        <div class="row">
            <div class="col-md-6">
                <p><strong>Tipo:</strong> <?php echo htmlspecialchars($equipo['tipo_nombre'] ?? 'N/A'); ?></p>
                <p><strong>Marca / Modelo:</strong> <?php echo htmlspecialchars(($equipo['marca_nombre'] ?? 'N/A') . ' / ' . ($equipo['modelo_nombre'] ?? 'N/A')); ?></p>
                <p><strong>Número de Serie:</strong> <?php echo htmlspecialchars($equipo['numero_serie'] ?? 'N/A'); ?></p>
            </div>
            <div class="col-md-6">
                <p><strong>Fecha de Adquisición:</strong> <?php echo isset($equipo['fecha_adquisicion']) ? date('d/m/Y', strtotime($equipo['fecha_adquisicion'])) : 'N/A'; ?></p>
                <p><strong>Sucursal:</strong> <?php echo htmlspecialchars($equipo['sucursal_nombre'] ?? 'N/A'); ?></p>
                <p><strong>Estado Actual:</strong> 
                    <?php
                    $estado = htmlspecialchars($equipo['estado'] ?? 'Desconocido');
                    $clase_badge = 'bg-secondary';
                    if ($estado == 'Disponible') $clase_badge = 'bg-success';
                    if ($estado == 'Asignado') $clase_badge = 'bg-primary';
                    if ($estado == 'En Reparación') $clase_badge = 'bg-warning text-dark';
                    if ($estado == 'De Baja') $clase_badge = 'bg-danger';
                    ?>
                    <span class="badge <?php echo $clase_badge; ?>"><?php echo $estado; ?></span>
                </p>
            </div>
        </div>
        <?php if (!empty($equipo['caracteristicas'])): ?>
            <hr>
            <p><strong>Características:</strong> <?php echo nl2br(htmlspecialchars($equipo['caracteristicas'])); ?></p>
        <?php endif; ?>
        <?php if (!empty($equipo['proveedor'])): ?>
            <p><strong>Proveedor:</strong> <?php echo htmlspecialchars($equipo['proveedor']); ?></p>
        <?php endif; ?>
         <?php if (!empty($equipo['observaciones'])): ?>
            <p><strong>Observaciones:</strong> <?php echo nl2br(htmlspecialchars($equipo['observaciones'])); ?></p>
        <?php endif; ?>
    </div>
</div>

<div class="card">
    <div class="card-header">
        Historial de Reparaciones
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-sm table-bordered table-striped">
                <thead class="table-light">
                    <tr>
                        <th>Fecha Ingreso</th>
                        <th>Motivo</th>
                        <th>Fecha Salida</th>
                        <th>Solución / Observaciones</th>
                        <th>Costo</th>
                        <th>Estado</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($historial_reparaciones->num_rows > 0): ?>
                        <?php while ($rep = $historial_reparaciones->fetch_assoc()): ?>
                            <tr>
                                <td><?php echo date('d/m/Y', strtotime($rep['fecha_ingreso'])); ?></td>
                                <td><?php echo htmlspecialchars($rep['motivo'] ?? '---'); ?></td>
                                <td><?php echo $rep['fecha_salida'] ? date('d/m/Y', strtotime($rep['fecha_salida'])) : '---'; ?></td>
                                
                                <td><?php echo nl2br(htmlspecialchars($rep['observaciones_salida'] ?? '---')); ?></td>
                                
                                <td class="text-end">
                                    <?php echo htmlspecialchars($_SESSION['configuracion']['moneda_simbolo'] ?? 'S/'); ?> <?php echo number_format($rep['costo'], 2); ?>
                                </td>
                                
                                <td>
                                    <span class="badge <?php echo $rep['estado_reparacion'] == 'En Proceso' ? 'bg-warning text-dark' : 'bg-secondary'; ?>">
                                        <?php echo htmlspecialchars($rep['estado_reparacion']); ?>
                                    </span>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr><td colspan="6" class="text-center text-muted">Este equipo no tiene reparaciones registradas.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require_once '../templates/footer.php'; ?>