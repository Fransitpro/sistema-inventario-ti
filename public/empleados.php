<?php
require_once '../templates/header.php';

$id_sucursal_usuario = $_SESSION['user_sucursal_id'];

$sql_select = "SELECT 
                    emp.id, emp.dni, emp.nombres, emp.apellidos, emp.estado,
                    c.nombre AS cargo_nombre,
                    a.nombre AS area_nombre,
                    s.nombre AS sucursal_nombre
               FROM empleados emp
               LEFT JOIN sucursales s ON emp.id_sucursal = s.id
               LEFT JOIN areas a ON emp.id_area = a.id
               LEFT JOIN cargos c ON emp.id_cargo = c.id";

$where_clauses = [];
$params = [];
$types = "";

if ($id_sucursal_usuario !== null) {
    $where_clauses[] = "emp.id_sucursal = ?"; $types .= "i"; $params[] = $id_sucursal_usuario;
}

$filtro_sucursal = $_GET['sucursal'] ?? '';
$filtro_area = $_GET['area'] ?? '';
$filtro_cargo = $_GET['cargo'] ?? '';
$filtro_estado = $_GET['estado'] ?? '';
$filtro_texto = $_GET['texto'] ?? '';

if (!empty($filtro_sucursal) && $id_sucursal_usuario === null) { $where_clauses[] = "emp.id_sucursal = ?"; $types .= "i"; $params[] = $filtro_sucursal; }
if (!empty($filtro_area)) { $where_clauses[] = "emp.id_area = ?"; $types .= "i"; $params[] = $filtro_area; }
if (!empty($filtro_cargo)) { $where_clauses[] = "emp.id_cargo = ?"; $types .= "i"; $params[] = $filtro_cargo; }
if (!empty($filtro_estado)) { $where_clauses[] = "emp.estado = ?"; $types .= "s"; $params[] = $filtro_estado; }
if (!empty($filtro_texto)) {
    $where_clauses[] = "(emp.dni LIKE ? OR emp.nombres LIKE ? OR emp.apellidos LIKE ?)";
    $types .= "sss";
    $like_texto = "%" . $filtro_texto . "%";
    array_push($params, $like_texto, $like_texto, $like_texto);
}

if (!empty($where_clauses)) { $sql_select .= " WHERE " . implode(" AND ", $where_clauses); }
$sql_select .= " ORDER BY emp.apellidos, emp.nombres";

$stmt = $conexion->prepare($sql_select);
if (!empty($params)) { $stmt->bind_param($types, ...$params); }
$stmt->execute();
$resultado = $stmt->get_result();

$sucursales = $conexion->query("SELECT * FROM sucursales WHERE estado = 'Activo' ORDER BY nombre");
$areas = $conexion->query("SELECT * FROM areas WHERE estado = 'Activo' ORDER BY nombre");
$cargos = $conexion->query("SELECT * FROM cargos WHERE estado = 'Activo' ORDER BY nombre");
?>

<h1 class="h2 mb-3">Gestión de Empleados</h1>

<div class="card mb-4">
    <div class="card-header"><i class="bi bi-funnel-fill"></i> Filtros y Reportes</div>
    <div class="card-body">
        <form action="empleados.php" method="GET">
            <div class="row g-3">
                <div class="col-md-4"><label class="form-label">Buscar por DNI o Nombre</label><input type="text" class="form-control form-control-sm" name="texto" value="<?php echo htmlspecialchars($filtro_texto); ?>"></div>
                <?php if ($id_sucursal_usuario === null): ?>
                <div class="col-md-4"><label class="form-label">Sucursal</label><select class="form-select form-select-sm" name="sucursal"><option value="">Todas</option><?php if ($sucursales) { mysqli_data_seek($sucursales, 0); while($s = $sucursales->fetch_assoc()) { echo "<option value='{$s['id']}' ".($filtro_sucursal == $s['id'] ? 'selected' : '').">".htmlspecialchars($s['nombre'])."</option>"; }} ?></select></div>
                <?php endif; ?>
                <div class="col-md-4"><label class="form-label">Área</label><select class="form-select form-select-sm" name="area"><option value="">Todas</option><?php if ($areas) { mysqli_data_seek($areas, 0); while($a = $areas->fetch_assoc()) { echo "<option value='{$a['id']}' ".($filtro_area == $a['id'] ? 'selected' : '').">".htmlspecialchars($a['nombre'])."</option>"; }} ?></select></div>
                <div class="col-md-4"><label class="form-label">Cargo</label><select class="form-select form-select-sm" name="cargo"><option value="">Todas</option><?php if ($cargos) { mysqli_data_seek($cargos, 0); while($c = $cargos->fetch_assoc()) { echo "<option value='{$c['id']}' ".($filtro_cargo == $c['id'] ? 'selected' : '').">".htmlspecialchars($c['nombre'])."</option>"; }} ?></select></div>
                <div class="col-md-4"><label class="form-label">Estado</label><select class="form-select form-select-sm" name="estado"><option value="">Todos</option><option value="Activo" <?php if($filtro_estado == 'Activo') echo 'selected'; ?>>Activo</option><option value="Inactivo" <?php if($filtro_estado == 'Inactivo') echo 'selected'; ?>>Inactivo</option></select></div>
                <div class="col-md-4 d-flex align-items-end"><button type="submit" class="btn btn-primary btn-sm me-2">Filtrar</button><a href="empleados.php" class="btn btn-secondary btn-sm">Limpiar</a></div>
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
    <h2 class="h4">Listado de Empleados</h2>
    <a href="empleado_agregar.php" class="btn btn-primary"><i class="bi bi-plus-circle me-2"></i>Registrar Nuevo Empleado</a>
</div>

<div class="card">
    <div class="card-body">
        <div class="table-responsive">
            <table id="tabla-empleados" class="table table-striped table-hover" style="width:100%">
                <thead class="table-dark">
                    <tr>
                        <?php if ($id_sucursal_usuario === null) echo '<th>Sucursal</th>'; ?>
                        <th>DNI</th><th>Apellidos y Nombres</th><th>Cargo</th><th>Área</th><th>Estado</th><th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($resultado->num_rows > 0) : ?>
                        <?php while ($empleado = $resultado->fetch_assoc()) : ?>
                            <tr>
                                <?php if ($id_sucursal_usuario === null) echo '<td>' . htmlspecialchars($empleado['sucursal_nombre'] ?? 'N/A') . '</td>'; ?>
                                <td><?php echo htmlspecialchars($empleado['dni']); ?></td>
                                <td><?php echo htmlspecialchars($empleado['apellidos'] . ', ' . $empleado['nombres']); ?></td>
                                <td><?php echo htmlspecialchars($empleado['cargo_nombre'] ?? 'N/A'); ?></td>
                                <td><?php echo htmlspecialchars($empleado['area_nombre'] ?? 'N/A'); ?></td>
                                <td><span class="badge <?php echo $empleado['estado'] === 'Activo' ? 'bg-success' : 'bg-danger'; ?>"><?php echo htmlspecialchars($empleado['estado']); ?></span></td>
                                <td><a href="empleado_editar.php?id=<?php echo $empleado['id']; ?>" class="btn btn-warning btn-sm" title="Editar"><i class="bi bi-pencil"></i></a></td>
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
    if ($.fn.DataTable.isDataTable('#tabla-empleados')) { $('#tabla-empleados').DataTable().destroy(); }
    
    // Detectar si la tabla está vacía
    var tablaVacia = $('#tabla-empleados tbody tr').length === 0;

    // Inicializar DataTables
    var table = $('#tabla-empleados').DataTable({
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