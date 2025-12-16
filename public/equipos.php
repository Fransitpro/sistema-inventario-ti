<?php
require_once '../templates/header.php';

// --- LÓGICA PARA FILTROS ---
$filtro_codigo = $_GET['codigo_inventario'] ?? '';
$filtro_serie = $_GET['numero_serie'] ?? '';
$filtro_sucursal = $_GET['sucursal'] ?? '';
$filtro_tipo = $_GET['tipo_equipo'] ?? '';
$filtro_marca = $_GET['marca'] ?? '';
$filtro_estado = $_GET['estado'] ?? '';

$sucursales = $conexion->query("SELECT id, nombre FROM sucursales WHERE estado = 'Activo' ORDER BY nombre");
$tipos_equipo = $conexion->query("SELECT id, nombre FROM tipos_equipo ORDER BY nombre");
$marcas = $conexion->query("SELECT id, nombre FROM marcas ORDER BY nombre");

$sql = "SELECT e.*, s.nombre AS sucursal_nombre, t.nombre AS tipo_nombre, ma.nombre as marca_nombre, mo.nombre as modelo_nombre
        FROM equipos e
        LEFT JOIN sucursales s ON e.id_sucursal = s.id
        LEFT JOIN tipos_equipo t ON e.id_tipo_equipo = t.id
        LEFT JOIN marcas ma ON e.id_marca = ma.id
        LEFT JOIN modelos mo ON e.id_modelo = mo.id";

$where_clauses = [];
if (!empty($filtro_codigo)) $where_clauses[] = "e.codigo_inventario LIKE '%" . $conexion->real_escape_string($filtro_codigo) . "%'";
if (!empty($filtro_serie)) $where_clauses[] = "e.numero_serie LIKE '%" . $conexion->real_escape_string($filtro_serie) . "%'";
if (!empty($filtro_sucursal)) $where_clauses[] = "e.id_sucursal = " . (int)$filtro_sucursal;
if (!empty($filtro_tipo)) $where_clauses[] = "e.id_tipo_equipo = " . (int)$filtro_tipo;
if (!empty($filtro_marca)) $where_clauses[] = "e.id_marca = " . (int)$filtro_marca;
if (!empty($filtro_estado)) $where_clauses[] = "e.estado = '" . $conexion->real_escape_string($filtro_estado) . "'";

if (isset($_SESSION['user_sucursal_id']) && $_SESSION['user_sucursal_id'] !== null) {
     $where_clauses[] = "e.id_sucursal = " . (int)$_SESSION['user_sucursal_id'];
}

if (!empty($where_clauses)) {
    $sql .= " WHERE " . implode(" AND ", $where_clauses);
}

$sql .= " ORDER BY e.id DESC";
$resultado = $conexion->query($sql);
?>

<h1 class="h2 mb-4">Gestión de Equipos</h1>

<div class="card mb-4">
    <div class="card-header"><i class="bi bi-funnel-fill me-2"></i> Filtros y Reportes</div>
    <div class="card-body">
        <form action="equipos.php" method="GET" class="mb-4">
            <div class="row g-3">
                <div class="col-md-3"><label class="form-label">Código de Inventario</label><input type="text" class="form-control form-control-sm" name="codigo_inventario" value="<?php echo htmlspecialchars($filtro_codigo); ?>"></div>
                <div class="col-md-3"><label class="form-label">Número de Serie</label><input type="text" class="form-control form-control-sm" name="numero_serie" value="<?php echo htmlspecialchars($filtro_serie); ?>"></div>
                <?php if (!isset($_SESSION['user_sucursal_id']) || $_SESSION['user_sucursal_id'] === null): ?>
                <div class="col-md-3"><label class="form-label">Sucursal</label><select class="form-select form-select-sm" name="sucursal"><option value="">Todas</option><?php if ($sucursales) { mysqli_data_seek($sucursales, 0); while($s = $sucursales->fetch_assoc()) { echo "<option value='{$s['id']}' ".($filtro_sucursal == $s['id'] ? 'selected' : '').">".htmlspecialchars($s['nombre'])."</option>"; }} ?></select></div>
                <?php endif; ?>
                <div class="col-md-3"><label class="form-label">Tipo de Equipo</label><select class="form-select form-select-sm" name="tipo_equipo"><option value="">Todos</option><?php if ($tipos_equipo) { mysqli_data_seek($tipos_equipo, 0); while($t = $tipos_equipo->fetch_assoc()) { echo "<option value='{$t['id']}' ".($filtro_tipo == $t['id'] ? 'selected' : '').">".htmlspecialchars($t['nombre'])."</option>"; }} ?></select></div>
                <div class="col-md-3"><label class="form-label">Marca</label><select class="form-select form-select-sm" name="marca"><option value="">Todas</option><?php if ($marcas) { mysqli_data_seek($marcas, 0); while($m = $marcas->fetch_assoc()) { echo "<option value='{$m['id']}' ".($filtro_marca == $m['id'] ? 'selected' : '').">".htmlspecialchars($m['nombre'])."</option>"; }} ?></select></div>
                <div class="col-md-3"><label class="form-label">Estado</label><select class="form-select form-select-sm" name="estado"><option value="">Todos</option><option value="Disponible" <?php if($filtro_estado == 'Disponible') echo 'selected'; ?>>Disponible</option><option value="Asignado" <?php if($filtro_estado == 'Asignado') echo 'selected'; ?>>Asignado</option><option value="En Reparación" <?php if($filtro_estado == 'En Reparación') echo 'selected'; ?>>En Reparación</option><option value="De Baja" <?php if($filtro_estado == 'De Baja') echo 'selected'; ?>>De Baja</option></select></div>
                <div class="col-md-6 d-flex align-items-end"><button type="submit" class="btn btn-primary btn-sm me-2">Filtrar</button><a href="equipos.php" class="btn btn-secondary btn-sm">Limpiar</a></div>
            </div>
        </form>
        <hr>
        <div class="d-flex gap-2">
            <button type="button" id="export-excel" class="btn btn-success"><i class="bi bi-file-earmark-excel"></i> Excel</button>
            <button type="button" id="export-pdf" class="btn btn-danger"><i class="bi bi-file-earmark-pdf"></i> PDF</button>
            <button type="button" id="export-print" class="btn btn-info"><i class="bi bi-printer"></i> Imprimir</button>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span>Inventario Actual</span>
        <a href="equipo_agregar.php" class="btn btn-primary"><i class="bi bi-plus-circle me-2"></i> Registrar Nuevo Equipo</a>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table id="tabla-equipos" class="table table-striped table-hover align-middle">
                <thead class="table-dark">
                    <tr><th>Sucursal</th><th>Código</th><th>Tipo</th><th>Marca / Modelo</th><th>N/S</th><th>Fecha Adquisición</th><th>Estado</th><th>Acciones</th></tr>
                </thead>
                <tbody>
                    <?php if ($resultado && $resultado->num_rows > 0): ?>
                        <?php while ($equipo = $resultado->fetch_assoc()): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($equipo['sucursal_nombre'] ?? 'N/A'); ?></td>
                                <td><?php echo htmlspecialchars($equipo['codigo_inventario'] ?? 'N/A'); ?></td>
                                <td><?php echo htmlspecialchars($equipo['tipo_nombre'] ?? 'N/A'); ?></td>
                                <td><?php echo htmlspecialchars(($equipo['marca_nombre'] ?? '') . ' / ' . ($equipo['modelo_nombre'] ?? '')); ?></td>
                                <td><?php echo htmlspecialchars($equipo['numero_serie'] ?? 'N/A'); ?></td>
                                <td><?php echo isset($equipo['fecha_adquisicion']) ? date('d/m/Y', strtotime($equipo['fecha_adquisicion'])) : 'N/A'; ?></td>
                                <td>
                                    <span class="badge <?php echo ($equipo['estado'] == 'Disponible' ? 'bg-success' : ($equipo['estado'] == 'Asignado' ? 'bg-primary' : ($equipo['estado'] == 'En Reparación' ? 'bg-warning text-dark' : 'bg-danger'))); ?>">
                                        <?php echo htmlspecialchars($equipo['estado']); ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="btn-group" role="group">
                                        <a href="equipo_detalle.php?id=<?php echo $equipo['id']; ?>" class="btn btn-primary btn-sm" title="Ver Detalle"><i class="bi bi-eye-fill"></i></a>
                                        <a href="equipo_editar.php?id=<?php echo $equipo['id']; ?>" class="btn btn-warning btn-sm" title="Editar Equipo"><i class="bi bi-pencil-fill"></i></a>
                                        <?php if (($equipo['estado'] ?? '') === 'Disponible'): ?>
                                            <a href="equipo_enviar_reparacion.php?id=<?php echo $equipo['id']; ?>" class="btn btn-info btn-sm" title="Enviar a Reparación"><i class="bi bi-wrench"></i></a>
                                        <?php endif; ?>
                                        <?php if (($equipo['estado'] ?? '') !== 'Asignado' && ($equipo['estado'] ?? '') !== 'De Baja'): ?>
                                             <a href="equipo_dar_de_baja.php?id=<?php echo $equipo['id']; ?>" class="btn btn-danger btn-sm" title="Dar de Baja"><i class="bi bi-trash-fill"></i></a>
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
    if ($.fn.DataTable.isDataTable('#tabla-equipos')) { $('#tabla-equipos').DataTable().destroy(); }
    
    var tablaVacia = $('#tabla-equipos tbody tr').length === 0;

    var table = $('#tabla-equipos').DataTable({
        "language": { "url": "//cdn.datatables.net/plug-ins/1.13.6/i18n/es-ES.json" },
        "dom": 'rt<"d-flex justify-content-between"ip>',
        "buttons": [ 
            { extend: 'excelHtml5', className: 'buttons-excel', exportOptions: { columns: ':visible:not(:last-child)' } },
            { extend: 'pdfHtml5', className: 'buttons-pdf', exportOptions: { columns: ':visible:not(:last-child)' } },
            { extend: 'print', className: 'buttons-print', exportOptions: { columns: ':visible:not(:last-child)' } }
        ]
    });

    if (tablaVacia) {
        $('#export-excel, #export-pdf, #export-print').hide();
    }

    // VINCULACIÓN DE EVENTOS
    $('#export-excel').on('click', function() { table.button('.buttons-excel').trigger(); });
    $('#export-pdf').on('click', function() { table.button('.buttons-pdf').trigger(); });
    $('#export-print').on('click', function() { table.button('.buttons-print').trigger(); });
});
</script>