<?php
require_once '../templates/header.php';

// Lógica del filtro de estado
$filtro_estado = $_GET['estado'] ?? 'En Proceso'; // Por defecto muestra 'En Proceso'

$sql = "SELECT r.id AS id_reparacion, r.fecha_ingreso, r.fecha_salida, r.motivo, r.proveedor_servicio, r.estado_reparacion,
            e.codigo_inventario, ma.nombre as marca_nombre, mo.nombre as modelo_nombre
        FROM reparaciones r
        JOIN equipos e ON r.id_equipo = e.id
        LEFT JOIN marcas ma ON e.id_marca = ma.id
        LEFT JOIN modelos mo ON e.id_modelo = mo.id";

if ($filtro_estado !== 'Todas') {
    $sql .= " WHERE r.estado_reparacion = ?";
}
$sql .= " ORDER BY r.fecha_ingreso DESC";

$stmt = $conexion->prepare($sql);
if ($filtro_estado !== 'Todas') {
    $stmt->bind_param("s", $filtro_estado);
}
$stmt->execute();
$resultado = $stmt->get_result();
?>

<h1 class="h2 mb-4">Historial de Reparaciones</h1>

<div class="card mb-4">
    <div class="card-body">
        <form action="reparaciones.php" method="GET" class="d-flex align-items-center">
            <label for="estado" class="form-label me-2 mb-0">Filtrar por estado:</label>
            <select name="estado" id="estado" class="form-select w-auto" onchange="this.form.submit()">
                <option value="En Proceso" <?php if($filtro_estado == 'En Proceso') echo 'selected'; ?>>En Proceso</option>
                <option value="Finalizada" <?php if($filtro_estado == 'Finalizada') echo 'selected'; ?>>Finalizada</option>
                <option value="Todas" <?php if($filtro_estado == 'Todas') echo 'selected'; ?>>Todas</option>
            </select>
        </form>
    </div>
</div>

<div class="card">
    <div class="card-header">
        Listado de Reparaciones
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-striped table-hover">
                <thead class="table-dark">
                    <tr>
                        <th>Código Equipo</th>
                        <th>Marca / Modelo</th>
                        <th>Fecha Ingreso</th>
                        <th>Fecha Salida</th>
                        <th>Motivo</th>
                        <th>Estado</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($resultado && $resultado->num_rows > 0): ?>
                        <?php while ($reparacion = $resultado->fetch_assoc()): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($reparacion['codigo_inventario']); ?></td>
                                <td><?php echo htmlspecialchars($reparacion['marca_nombre'] . ' / ' . $reparacion['modelo_nombre']); ?></td>
                                <td><?php echo date('d/m/Y', strtotime($reparacion['fecha_ingreso'])); ?></td>
                                <td><?php echo $reparacion['fecha_salida'] ? date('d/m/Y', strtotime($reparacion['fecha_salida'])) : '---'; ?></td>
                                <td><?php echo htmlspecialchars($reparacion['motivo']); ?></td>
                                <td>
                                    <span class="badge <?php echo $reparacion['estado_reparacion'] == 'En Proceso' ? 'bg-warning text-dark' : 'bg-secondary'; ?>">
                                        <?php echo htmlspecialchars($reparacion['estado_reparacion']); ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if ($reparacion['estado_reparacion'] == 'En Proceso'): ?>
                                        <a href="reparacion_finalizar.php?id_reparacion=<?php echo $reparacion['id_reparacion']; ?>" class="btn btn-success btn-sm" title="Finalizar Reparación">
                                            <i class="bi bi-check-circle-fill"></i> Finalizar
                                        </a>
                                    <?php else: ?>
                                        <span class="text-muted">---</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr><td colspan="7" class="text-center">No hay reparaciones que coincidan con el filtro.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require_once '../templates/footer.php'; ?>