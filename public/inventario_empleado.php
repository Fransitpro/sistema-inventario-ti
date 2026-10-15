<?php
require_once '../templates/header.php';

$id_sucursal_usuario = $_SESSION['user_sucursal_id'];

// Filtros
$filtro_sucursal = $_GET['sucursal'] ?? '';
$filtro_area = $_GET['area'] ?? '';
$filtro_empleado = $_GET['empleado'] ?? '';
$filtro_estado = $_GET['estado'] ?? 'Activa';
$filtro_texto = trim($_GET['texto'] ?? '');
$filtro_tipo = $_GET['tipo'] ?? '';
$filtro_marca = $_GET['marca'] ?? '';
$filtro_estado_bien = $_GET['estado_bien'] ?? '';
$filtro_fecha_desde = $_GET['fecha_desde'] ?? '';
$filtro_fecha_hasta = $_GET['fecha_hasta'] ?? '';

$sql = "SELECT
            a.id AS id_asignacion,
            a.fecha_entrega,
            a.fecha_devolucion,
            a.estado_asignacion,
            emp.dni, emp.nombres, emp.apellidos, emp.estado AS estado_empleado,
            cg.nombre AS cargo_nombre,
            ar.nombre AS area_nombre,
            e.codigo_inventario, e.cod_pat, e.numero_serie, e.estado_bien,
            te.nombre AS tipo_nombre,
            ma.nombre AS marca_nombre,
            mo.nombre AS modelo_nombre,
            s.nombre AS sucursal_nombre
        FROM asignaciones a
        JOIN equipos e ON a.id_equipo = e.id
        JOIN empleados emp ON a.id_empleado = emp.id
        LEFT JOIN cargos cg ON emp.id_cargo = cg.id
        LEFT JOIN areas ar ON emp.id_area = ar.id
        LEFT JOIN tipos_equipo te ON e.id_tipo_equipo = te.id
        LEFT JOIN marcas ma ON e.id_marca = ma.id
        LEFT JOIN modelos mo ON e.id_modelo = mo.id
        JOIN sucursales s ON e.id_sucursal = s.id
        WHERE 1 = 1";

$params = [];
$types = "";

if ($id_sucursal_usuario !== null) {
    $sql .= " AND e.id_sucursal = ?";
    $types .= "i";
    $params[] = $id_sucursal_usuario;
}

if ($filtro_sucursal !== '' && $id_sucursal_usuario === null) {
    $sql .= " AND e.id_sucursal = ?";
    $types .= "i";
    $params[] = (int)$filtro_sucursal;
}

if ($filtro_area !== '') {
    $sql .= " AND emp.id_area = ?";
    $types .= "i";
    $params[] = (int)$filtro_area;
}

if ($filtro_empleado !== '') {
    $sql .= " AND a.id_empleado = ?";
    $types .= "i";
    $params[] = (int)$filtro_empleado;
}

if ($filtro_estado === 'Activa') {
    $sql .= " AND a.estado_asignacion = 'Activa'";
} elseif ($filtro_estado === 'Finalizada') {
    $sql .= " AND a.estado_asignacion = 'Finalizada'";
}

if ($filtro_texto !== '') {
    $sql .= " AND (emp.nombres LIKE ? OR emp.apellidos LIKE ? OR emp.dni LIKE ? OR e.codigo_inventario LIKE ? OR e.numero_serie LIKE ?)";
    $like = "%" . $filtro_texto . "%";
    $types .= "sssss";
    array_push($params, $like, $like, $like, $like, $like);
}

if ($filtro_tipo !== '') {
    $sql .= " AND e.id_tipo_equipo = ?";
    $types .= "i";
    $params[] = (int)$filtro_tipo;
}

if ($filtro_marca !== '') {
    $sql .= " AND e.id_marca = ?";
    $types .= "i";
    $params[] = (int)$filtro_marca;
}

if ($filtro_estado_bien !== '') {
    $sql .= " AND e.estado_bien = ?";
    $types .= "s";
    $params[] = $filtro_estado_bien;
}

if ($filtro_fecha_desde !== '') {
    $sql .= " AND DATE(a.fecha_entrega) >= ?";
    $types .= "s";
    $params[] = $filtro_fecha_desde;
}

if ($filtro_fecha_hasta !== '') {
    $sql .= " AND DATE(a.fecha_entrega) <= ?";
    $types .= "s";
    $params[] = $filtro_fecha_hasta;
}

$sql .= " ORDER BY emp.apellidos, emp.nombres, e.codigo_inventario";

$stmt = $conexion->prepare($sql);
if ($types !== "") { $stmt->bind_param($types, ...$params); }
$stmt->execute();
$resultado = $stmt->get_result();

// Datos para los filtros
$sucursales = $conexion->query("SELECT id, nombre FROM sucursales WHERE estado = 'Activo' ORDER BY nombre");
$areas = $conexion->query("SELECT id, nombre FROM areas ORDER BY nombre");
$tipos = $conexion->query("SELECT id, nombre FROM tipos_equipo ORDER BY nombre");
$marcas = $conexion->query("SELECT id, nombre FROM marcas ORDER BY nombre");

// Selector de empleado: todos los empleados (tambien los inactivos) con cuantos equipos tienen activos
$empleados = $conexion->query(
    "SELECT emp.id, emp.nombres, emp.apellidos, emp.estado,
            (SELECT COUNT(*) FROM asignaciones a2
              WHERE a2.id_empleado = emp.id AND a2.estado_asignacion = 'Activa') AS equipos_activos
     FROM empleados emp
     ORDER BY emp.apellidos, emp.nombres"
);

// Resumen: agrupar el inventario por empleado
$resumen = [];
$total_equipos = 0;
if ($resultado && $resultado->num_rows > 0) {
    while ($fila = $resultado->fetch_assoc()) {
        $clave = $fila['apellidos'] . ', ' . $fila['nombres'];
        if (!isset($resumen[$clave])) {
            $resumen[$clave] = [
                'empleado' => $clave,
                'dni' => $fila['dni'],
                'sucursal' => $fila['sucursal_nombre'],
                'area' => $fila['area_nombre'] ?? 'N/A',
                'equipos' => 0
            ];
        }
        $resumen[$clave]['equipos']++;
        $total_equipos++;
    }
    $resultado->data_seek(0);
}

$hay_filas = $total_equipos > 0;
?>

<style>
    /* Reporte centrado: encabezados y celdas alineados al centro */
    #tabla-inventario-empleado th,
    #tabla-inventario-empleado td { text-align: center; }

    /* La tabla tiene 13 columnas y no cabe en la pantalla. Con letra mas
       pequena baja de 1287 px a ~1150 px, asi que se desplazan un par de
       columnas en vez de seis. La barra horizontal la pone barra-horizontal.js
       en todas las tablas del sistema. */
    .tabla-inventario-desplazable table { font-size: 0.8rem; }

    /* Encabezado fijo: al bajar se sigue viendo cuál es cada columna. Queda
       justo debajo de la barra horizontal, que va pegada arriba. */
    .tabla-inventario-desplazable thead th {
        position: sticky;
        top: 22px;
        z-index: 2;
    }
</style>

<h1 class="h2 mb-1 text-center">CARGO PERSONAL POR ASIGNACIÓN DE BIENES EN USO</h1>
<div class="row g-0 fw-semibold mb-1">
    <div class="col-6 text-start">DISTRITO FISCAL</div>
    <div class="col-6 text-center">LOCAL</div>
</div>
<p class="fw-semibold mb-1 text-start">NOMBRES Y APELLIDOS</p>
<p class="fw-semibold mb-1 text-start">DNI</p>
<p class="fw-semibold mb-1 text-start">CARGO</p>
<p class="fw-semibold mb-1 text-start">DEPENDENCIA</p>
<p class="text-muted mb-4">Equipos asignados a cada empleado, con su estado actual.</p>

<div class="card mb-4">
    <div class="card-body">
        <form method="GET" class="row g-3">
            <div class="col-md-4">
                <label class="form-label">Buscar empleado o equipo</label>
                <input type="text" class="form-control form-control-sm" name="texto" value="<?php echo htmlspecialchars($filtro_texto); ?>" placeholder="Nombre, DNI, código o serie">
            </div>
            <?php if ($id_sucursal_usuario === null): ?>
            <div class="col-md-3">
                <label class="form-label">Sucursal</label>
                <select class="form-select form-select-sm" name="sucursal">
                    <option value="">Todas</option>
                    <?php if ($sucursales) { while ($s = $sucursales->fetch_assoc()) { ?>
                        <option value="<?php echo (int)$s['id']; ?>" <?php echo ($filtro_sucursal == $s['id'] ? 'selected' : ''); ?>><?php echo htmlspecialchars($s['nombre']); ?></option>
                    <?php } } ?>
                </select>
            </div>
            <?php endif; ?>
            <div class="col-md-3">
                <label class="form-label">Área</label>
                <select class="form-select form-select-sm" name="area">
                    <option value="">Todas</option>
                    <?php if ($areas) { while ($a = $areas->fetch_assoc()) { ?>
                        <option value="<?php echo (int)$a['id']; ?>" <?php echo ($filtro_area == $a['id'] ? 'selected' : ''); ?>><?php echo htmlspecialchars($a['nombre']); ?></option>
                    <?php } } ?>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label">Empleado</label>
                <input type="search" id="buscar-empleado" class="form-control form-control-sm mb-1" placeholder="Escribir para buscar..." autocomplete="off">
                <select class="form-select form-select-sm" name="empleado" id="select-empleado">
                    <option value="">Todos los empleados</option>
                    <?php if ($empleados) { while ($e = $empleados->fetch_assoc()) { ?>
                        <option value="<?php echo (int)$e['id']; ?>" <?php echo ($filtro_empleado == $e['id'] ? 'selected' : ''); ?>>
                            <?php
                            echo htmlspecialchars($e['apellidos'] . ', ' . $e['nombres']);
                            echo ' — ' . (int)$e['equipos_activos'] . ' equipo' . ((int)$e['equipos_activos'] === 1 ? '' : 's');
                            if ($e['estado'] !== 'Activo') { echo ' (inactivo)'; }
                            ?>
                        </option>
                    <?php } } ?>
                </select>
                <div class="form-text">Muestra el inventario completo de la persona elegida.</div>
            </div>
            <div class="col-md-3">
                <label class="form-label">Estado de asignación</label>
                <select class="form-select form-select-sm" name="estado">
                    <option value="Activa" <?php echo ($filtro_estado === 'Activa' ? 'selected' : ''); ?>>Activas</option>
                    <option value="Finalizada" <?php echo ($filtro_estado === 'Finalizada' ? 'selected' : ''); ?>>Finalizadas</option>
                    <option value="" <?php echo ($filtro_estado === '' ? 'selected' : ''); ?>>Todas</option>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label">Tipo de equipo</label>
                <select class="form-select form-select-sm" name="tipo">
                    <option value="">Todos</option>
                    <?php if ($tipos) { while ($t = $tipos->fetch_assoc()) { ?>
                        <option value="<?php echo (int)$t['id']; ?>" <?php echo ($filtro_tipo == $t['id'] ? 'selected' : ''); ?>><?php echo htmlspecialchars($t['nombre']); ?></option>
                    <?php } } ?>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label">Marca</label>
                <select class="form-select form-select-sm" name="marca">
                    <option value="">Todas</option>
                    <?php if ($marcas) { while ($m = $marcas->fetch_assoc()) { ?>
                        <option value="<?php echo (int)$m['id']; ?>" <?php echo ($filtro_marca == $m['id'] ? 'selected' : ''); ?>><?php echo htmlspecialchars($m['nombre']); ?></option>
                    <?php } } ?>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label">Estado del bien</label>
                <select class="form-select form-select-sm" name="estado_bien">
                    <option value="">Todos</option>
                    <option value="Bueno" <?php echo ($filtro_estado_bien === 'Bueno' ? 'selected' : ''); ?>>Bueno</option>
                    <option value="Regular" <?php echo ($filtro_estado_bien === 'Regular' ? 'selected' : ''); ?>>Regular</option>
                    <option value="Malo" <?php echo ($filtro_estado_bien === 'Malo' ? 'selected' : ''); ?>>Malo</option>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label">Entregado desde</label>
                <input type="date" class="form-control input-size-30 form-control-sm" size="30" name="fecha_desde" value="<?php echo htmlspecialchars($filtro_fecha_desde); ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label">Entregado hasta</label>
                <input type="date" class="form-control input-size-30 form-control-sm" size="30" name="fecha_hasta" value="<?php echo htmlspecialchars($filtro_fecha_hasta); ?>">
            </div>
            <div class="col-md-3 d-flex align-items-end">
                <button type="submit" class="btn btn-primary btn-sm me-2">Filtrar</button>
                <a href="inventario_empleado.php" class="btn btn-secondary btn-sm">Limpiar</a>
            </div>
        </form>
        <hr>
        <div class="d-flex flex-wrap gap-2 align-items-center">
            <button id="export-excel" class="btn btn-success"><i class="bi bi-file-earmark-excel"></i> Excel</button>
            <button id="export-pdf" class="btn btn-danger"><i class="bi bi-file-earmark-pdf"></i> PDF</button>
            <button id="export-print" class="btn btn-info"><i class="bi bi-printer"></i> Imprimir</button>
            <span class="badge bg-light text-dark border ms-auto">
                <?php echo count($resumen); ?> empleados · <?php echo $total_equipos; ?> equipos
            </span>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-body">
        <div class="table-responsive tabla-inventario-desplazable">
            <table id="tabla-inventario-empleado" data-a4-titulo="CARGO PERSONAL POR ASIGNACIÓN DE BIENES EN USO" data-a4-fila="left:DISTRITO FISCAL|center:LOCAL" data-a4-subtitulo="NOMBRES Y APELLIDOS|DNI|CARGO|DEPENDENCIA" data-a4-centrar="1" class="table table-striped table-hover text-center" style="width:100%">
                <thead class="table-dark">
                    <tr>
                        <?php if ($id_sucursal_usuario === null): ?><th>Sucursal</th><?php endif; ?>
                        <th>DNI</th>
                        <th>Empleado</th>
                        <th>Área</th>
                        <th>Código Patrimonial</th>
                        <th>Equipo</th>
                        <th>Tipo</th>
                        <th>Marca</th>
                        <th>Modelo</th>
                        <th>N° Serie</th>
                        <th>Estado del Bien</th>
                        <th>Fecha Entrega</th>
                        <th>Estado Asignación</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($hay_filas): ?>
                        <?php while ($fila = $resultado->fetch_assoc()): ?>
                            <?php $nombre_empleado = $fila['apellidos'] . ', ' . $fila['nombres']; ?>
                            <tr>
                                <?php if ($id_sucursal_usuario === null): ?>
                                    <td><?php echo htmlspecialchars($fila['sucursal_nombre']); ?></td>
                                <?php endif; ?>
                                <td><?php echo htmlspecialchars($fila['dni']); ?></td>
                                <td class="fw-semibold"><?php echo htmlspecialchars($nombre_empleado); ?></td>
                                <td><?php echo htmlspecialchars($fila['area_nombre'] ?? 'N/A'); ?></td>
                                <td><?php echo htmlspecialchars($fila['cod_pat'] ?: '—'); ?></td>
                                <td><?php echo htmlspecialchars($fila['codigo_inventario']); ?></td>
                                <td><?php echo htmlspecialchars($fila['tipo_nombre'] ?? 'N/A'); ?></td>
                                <td><?php echo htmlspecialchars($fila['marca_nombre'] ?? 'N/A'); ?></td>
                                <td><?php echo htmlspecialchars($fila['modelo_nombre'] ?? 'N/A'); ?></td>
                                <td><?php echo htmlspecialchars($fila['numero_serie']); ?></td>
                                <td>
                                    <?php if ($fila['estado_bien']): ?>
                                        <span class="badge <?php
                                            echo $fila['estado_bien'] === 'Bueno' ? 'bg-success'
                                                : ($fila['estado_bien'] === 'Regular' ? 'bg-warning text-dark' : 'bg-danger');
                                        ?>"><?php echo htmlspecialchars($fila['estado_bien']); ?></span>
                                    <?php else: ?>
                                        <span class="text-muted">Sin evaluar</span>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo $fila['fecha_entrega'] ? date('d/m/Y', strtotime($fila['fecha_entrega'])) : '—'; ?></td>
                                <td>
                                    <span class="badge <?php echo $fila['estado_asignacion'] === 'Activa' ? 'bg-success' : 'bg-secondary'; ?>">
                                        <?php echo htmlspecialchars($fila['estado_asignacion']); ?>
                                    </span>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <?php if (!$hay_filas): ?>
            <div class="alert alert-warning mb-0">
                <i class="bi bi-info-circle"></i>
                <?php if ($filtro_empleado !== '' && $filtro_estado === 'Activa'): ?>
                    El empleado seleccionado no tiene equipos con asignación activa.
                    Cambia <strong>Estado de asignación</strong> a <strong>Todas</strong> para ver todo su historial.
                <?php else: ?>
                    No hay equipos asignados que coincidan con el filtro.
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php require_once '../templates/footer.php'; ?>

<script>
$(document).ready(function () {
    // Filtro por empleado: escribir acorta la lista de opciones
    var buscador = document.getElementById('buscar-empleado');
    var selector = document.getElementById('select-empleado');

    if (buscador && selector) {
        buscador.addEventListener('input', function () {
            var texto = this.value.trim().toLowerCase();
            Array.prototype.forEach.call(selector.options, function (opcion) {
                if (opcion.value === '') { return; }
                opcion.hidden = texto !== '' && opcion.textContent.toLowerCase().indexOf(texto) === -1;
            });
        });
    }

    // Cada tabla se inicializa una sola vez
    if (!$.fn.DataTable.isDataTable('#tabla-inventario-empleado')) {
        $('#tabla-inventario-empleado').DataTable({
            "language": { "url": "//cdn.datatables.net/plug-ins/1.13.6/i18n/es-ES.json" },
            "dom": 'rt<"d-flex justify-content-between"ip>',
            "paging": false,
            "order": []
        });
    }

    <?php if (!$hay_filas): ?>
        $('#export-excel, #export-pdf, #export-print').hide();
    <?php endif; ?>

});
</script>
