<?php
require_once '../templates/header.php';

$id_sucursal_usuario = $_SESSION['user_sucursal_id'];

$sql_select = "SELECT 
                    a.id AS id_asignacion, a.fecha_entrega, a.fecha_devolucion,
                    CASE WHEN a.fecha_devolucion IS NULL THEN 'Activa' ELSE 'Finalizada' END AS estado_asignacion,
                    a.acta_firmada_path, a.acta_devolucion_path,
                    e.codigo_inventario, ma.nombre as marca_nombre, mo.nombre as modelo_nombre,
                    emp.nombres, emp.apellidos,
                    s.nombre as sucursal_nombre
               FROM asignaciones a
               JOIN equipos e ON a.id_equipo = e.id
               JOIN empleados emp ON a.id_empleado = emp.id
               JOIN marcas ma ON e.id_marca = ma.id
               JOIN modelos mo ON e.id_modelo = mo.id
               JOIN sucursales s ON e.id_sucursal = s.id";

$where_clauses = [];
$params = [];
$types = "";

if ($id_sucursal_usuario !== null) {
    $where_clauses[] = "e.id_sucursal = ?";
    $types .= "i";
    $params[] = $id_sucursal_usuario;
}

$filtro_sucursal = $_GET['sucursal'] ?? '';
$filtro_empleado = $_GET['empleado'] ?? '';
$filtro_equipo = $_GET['equipo'] ?? '';
$filtro_estado = $_GET['estado'] ?? '';
$filtro_fecha_desde = $_GET['fecha_desde'] ?? '';
$filtro_fecha_hasta = $_GET['fecha_hasta'] ?? '';

if (!empty($filtro_sucursal) && $id_sucursal_usuario === null) { $where_clauses[] = "e.id_sucursal = ?"; $types .= "i"; $params[] = $filtro_sucursal; }
if (!empty($filtro_empleado)) { $where_clauses[] = "a.id_empleado = ?"; $types .= "i"; $params[] = $filtro_empleado; }
if (!empty($filtro_equipo)) { $where_clauses[] = "a.id_equipo = ?"; $types .= "i"; $params[] = $filtro_equipo; }
if (!empty($filtro_estado)) {
    if ($filtro_estado == 'Activa') $where_clauses[] = "a.fecha_devolucion IS NULL";
    elseif ($filtro_estado == 'Finalizada') $where_clauses[] = "a.fecha_devolucion IS NOT NULL";
}
if (!empty($filtro_fecha_desde)) { $where_clauses[] = "a.fecha_entrega >= ?"; $types .= "s"; $params[] = $filtro_fecha_desde; }
if (!empty($filtro_fecha_hasta)) { $where_clauses[] = "a.fecha_entrega <= ?"; $types .= "s"; $params[] = $filtro_fecha_hasta; }

if (!empty($where_clauses)) { $sql_select .= " WHERE " . implode(" AND ", $where_clauses); }
$sql_select .= " ORDER BY a.estado_asignacion ASC, a.fecha_entrega DESC";

$stmt = $conexion->prepare($sql_select);
if (!empty($params)) { $stmt->bind_param($types, ...$params); }
$stmt->execute();
$resultado = $stmt->get_result();

$sucursales = $conexion->query("SELECT * FROM sucursales WHERE estado = 'Activo' ORDER BY nombre");
$empleados = $conexion->query("SELECT id, nombres, apellidos FROM empleados WHERE estado = 'Activo' ORDER BY apellidos");
$equipos = $conexion->query("SELECT id, codigo_inventario FROM equipos WHERE estado != 'De Baja' ORDER BY codigo_inventario");
?>

<h1 class="h2 mb-3">Historial de Asignaciones</h1>

<div class="card mb-4">
    <div class="card-header"><i class="bi bi-funnel-fill"></i> Filtros y Reportes</div>
    <div class="card-body">
        <form action="asignaciones.php" method="GET">
            <div class="row g-3">
                <?php if ($id_sucursal_usuario === null): ?>
                <div class="col-md-4"><label class="form-label">Sucursal</label><select class="form-select form-select-sm" name="sucursal"><option value="">Todas</option><?php if ($sucursales) { mysqli_data_seek($sucursales, 0); while($s = $sucursales->fetch_assoc()) { echo "<option value='{$s['id']}' ".($filtro_sucursal == $s['id'] ? 'selected' : '').">".htmlspecialchars($s['nombre'])."</option>"; }} ?></select></div>
                <?php endif; ?>
                <div class="col-md-4"><label class="form-label">Empleado</label><select class="form-select form-select-sm" name="empleado"><option value="">Todos</option><?php if ($empleados) { mysqli_data_seek($empleados, 0); while($e = $empleados->fetch_assoc()) { echo "<option value='{$e['id']}' ".($filtro_empleado == $e['id'] ? 'selected' : '').">".htmlspecialchars($e['apellidos'].', '.$e['nombres'])."</option>"; }} ?></select></div>
                <div class="col-md-4"><label class="form-label">Equipo (Código)</label><select class="form-select form-select-sm" name="equipo"><option value="">Todos</option><?php if ($equipos) { while($eq = $equipos->fetch_assoc()) { echo "<option value='{$eq['id']}' ".($filtro_equipo == $eq['id'] ? 'selected' : '').">".htmlspecialchars($eq['codigo_inventario'])."</option>"; }} ?></select></div>
                <div class="col-md-3"><label class="form-label">Estado Asignación</label><select class="form-select form-select-sm" name="estado"><option value="">Todos</option><option value="Activa" <?php if($filtro_estado == 'Activa') echo 'selected'; ?>>Activa</option><option value="Finalizada" <?php if($filtro_estado == 'Finalizada') echo 'selected'; ?>>Finalizada</option></select></div>
                <div class="col-md-3"><label class="form-label">Fecha Entrega (Desde)</label><input type="date" class="form-control form-control-sm" name="fecha_desde" value="<?php echo htmlspecialchars($filtro_fecha_desde); ?>"></div>
                <div class="col-md-3"><label class="form-label">Fecha Entrega (Hasta)</label><input type="date" class="form-control form-control-sm" name="fecha_hasta" value="<?php echo htmlspecialchars($filtro_fecha_hasta); ?>"></div>
                <div class="col-md-3 d-flex align-items-end"><button type="submit" class="btn btn-primary btn-sm me-2">Filtrar</button><a href="asignaciones.php" class="btn btn-secondary btn-sm">Limpiar</a></div>
            </div>
        </form>
        <hr>
        <div class="d-flex gap-2">
            <button id="export-excel" class="btn btn-success"><i class="bi bi-file-earmark-excel"></i> Excel</button>
            <button id="export-pdf" class="btn btn-danger"><i class="bi bi-file-earmark-pdf"></i> PDF</button>
            <button id="export-print" class="btn btn-info"><i class="bi bi-printer"></i> Imprimir</button>
        </div>
    </div>
</div>

<div class="d-flex justify-content-between align-items-center mb-3">
    <h2 class="h4">Listado de Asignaciones</h2>
    <a href="asignacion_agregar.php" class="btn btn-primary"><i class="bi bi-plus-circle me-2"></i>Nueva Asignación</a>
</div>

<div class="card">
    <div class="card-body">
        <div class="table-responsive">
            <table id="tabla-asignaciones" class="table table-striped table-hover align-middle" style="width:100%">
                <thead class="table-dark">
                    <tr><th>Empleado</th><th>Equipo</th><th>Fecha Entrega</th><th>Fecha Devolución</th><th>Estado</th><th>Acta Entrega</th><th>Acta Devolución</th><th>Acciones</th></tr>
                </thead>
                <tbody>
                    <?php if ($resultado && $resultado->num_rows > 0): ?>
                        <?php while ($asignacion = $resultado->fetch_assoc()): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($asignacion['apellidos'] . ', ' . $asignacion['nombres']); ?></td>
                                <td><?php echo htmlspecialchars($asignacion['codigo_inventario'] . ' (' . $asignacion['marca_nombre'] . ')'); ?></td>
                                <td><?php echo date('d/m/Y', strtotime($asignacion['fecha_entrega'])); ?></td>
                                <td><?php echo $asignacion['fecha_devolucion'] ? date('d/m/Y', strtotime($asignacion['fecha_devolucion'])) : '<span class="text-muted">---</span>';?></td>
                                <td><span class="badge <?php echo $asignacion['estado_asignacion'] === 'Activa' ? 'bg-success' : 'bg-secondary'; ?>"><?php echo htmlspecialchars($asignacion['estado_asignacion']); ?></span></td>
                                <td>
                                    <?php if ($asignacion['acta_firmada_path']): ?>
                                        <a href="descargar_acta.php?id_asignacion=<?php echo $asignacion['id_asignacion']; ?>&tipo=entrega" target="_blank" class="btn btn-info btn-sm" title="Ver Acta Entrega"><i class="bi bi-file-earmark-pdf-fill"></i></a>
                                    <?php else: ?>
                                        <a href="asignacion_subir_acta.php?id=<?php echo $asignacion['id_asignacion']; ?>" class="btn btn-outline-primary btn-sm" title="Subir Acta Entrega"><i class="bi bi-upload"></i></a>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($asignacion['estado_asignacion'] === 'Finalizada'): ?>
                                        <?php if ($asignacion['acta_devolucion_path']): ?>
                                            <a href="descargar_acta.php?id_asignacion=<?php echo $asignacion['id_asignacion']; ?>&tipo=devolucion" target="_blank" class="btn btn-info btn-sm" title="Ver Acta Devolución"><i class="bi bi-file-earmark-pdf-fill"></i></a>
                                        <?php else: ?>
                                            <a href="asignacion_subir_acta_devolucion.php?id=<?php echo $asignacion['id_asignacion']; ?>" class="btn btn-outline-danger btn-sm" title="Subir Acta Devolución"><i class="bi bi-upload"></i></a>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <span class="text-muted">---</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="btn-group" role="group">
                                        <a href="generar_acta.php?id_asignacion=<?php echo $asignacion['id_asignacion']; ?>" target="_blank" class="btn btn-secondary btn-sm" title="Imprimir Acta Entrega"><i class="bi bi-printer"></i></a>
                                        <?php if ($asignacion['estado_asignacion'] === 'Activa'): ?>
                                            <a href="asignacion_devolver.php?id=<?php echo $asignacion['id_asignacion']; ?>" class="btn btn-danger btn-sm" title="Registrar Devolución"><i class="bi bi-arrow-return-left"></i></a>
                                        <?php else: ?>
                                            <a href="asignacion_detalle_devolucion.php?id=<?php echo $asignacion['id_asignacion']; ?>" class="btn btn-primary btn-sm" title="Ver Detalle Devolución"><i class="bi bi-eye"></i></a>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require_once '../templates/footer.php'; ?>

<script>
$(document).ready(function() {
    // Destruir instancia previa
    if ($.fn.DataTable.isDataTable('#tabla-asignaciones')) { $('#tabla-asignaciones').DataTable().destroy(); }
    
    // Detectar si la tabla está vacía
    var tablaVacia = $('#tabla-asignaciones tbody tr').length === 0;

    // Inicializar DataTables
    var table = $('#tabla-asignaciones').DataTable({
        "language": { "url": "//cdn.datatables.net/plug-ins/1.13.6/i18n/es-ES.json" },
        "dom": 'rt<"d-flex justify-content-between"ip>',
        "buttons": [ 
            { extend: 'excelHtml5', className: 'buttons-excel', exportOptions: { columns: ':visible:not(:last-child)' } },
            { extend: 'pdfHtml5', className: 'buttons-pdf', exportOptions: { columns: ':visible:not(:last-child)' } },
            { extend: 'print', className: 'buttons-print', exportOptions: { columns: ':visible:not(:last-child)' } }
        ]
    });

    // Ocultar botones si no hay datos
    if (tablaVacia) {
        $('#export-excel, #export-pdf, #export-print').hide();
    }

    // Vincular botones externos
    $('#export-excel').on('click', function() { table.button('.buttons-excel').trigger(); });
    $('#export-pdf').on('click', function() { table.button('.buttons-pdf').trigger(); });
    $('#export-print').on('click', function() { table.button('.buttons-print').trigger(); });
});
</script>