<?php
require_once '../templates/header.php';

$filtro_texto = trim($_GET['texto'] ?? '');
$filtro_sucursal = $_GET['sucursal'] ?? '';
$filtro_tipo = $_GET['tipo_equipo'] ?? '';
$filtro_motivo = $_GET['motivo'] ?? '';
$filtro_desde = $_GET['fecha_desde'] ?? '';
$filtro_hasta = $_GET['fecha_hasta'] ?? '';

$sucursales = $conexion->query("SELECT id, nombre FROM sucursales WHERE estado = 'Activo' ORDER BY nombre");
$tipos_equipo = $conexion->query("SELECT id, nombre FROM tipos_equipo WHERE estado = 'Activo' ORDER BY nombre");
$motivos = $conexion->query("SELECT DISTINCT motivo FROM bajas WHERE motivo IS NOT NULL AND motivo <> '' ORDER BY motivo");

$sql = "SELECT t.id_equipo,
               t.codigo_inventario,
               t.numero_serie,
               t.estado_actual,
               t.id_sucursal,
               t.id_tipo_equipo,
               t.id_baja,
               t.fecha_baja,
               t.motivo,
               t.observaciones,
               t.descripcion_motivo,
               t.acta_baja_path,
               t.fecha_reporte,
               e2.cod_pat AS cod_pat,
               s.nombre AS sucursal_nombre,
               ma.nombre AS marca_nombre,
               mo.nombre AS modelo_nombre
        FROM (
            SELECT e.id AS id_equipo,
                   e.codigo_inventario,
                   e.numero_serie,
                   e.estado AS estado_actual,
                   e.id_sucursal,
                   e.id_tipo_equipo,
                   b.id AS id_baja,
                   b.fecha_baja,
                   b.motivo,
                   b.observaciones,
                   b.descripcion_motivo,
                   b.acta_baja_path,
                   COALESCE(b.fecha_baja, DATE(e.fecha_registro)) AS fecha_reporte
            FROM bajas b
            JOIN equipos e ON e.id = b.id_equipo
            UNION ALL
            SELECT e.id,
                   e.codigo_inventario,
                   e.numero_serie,
                   e.estado,
                   e.id_sucursal,
                   e.id_tipo_equipo,
                   NULL,
                   NULL,
                   NULL,
                   NULL,
                   NULL,
                   NULL,
                   DATE(e.fecha_registro)
            FROM equipos e
            LEFT JOIN bajas b2 ON b2.id_equipo = e.id
            WHERE e.estado = 'De Baja' AND b2.id IS NULL
        ) t
        LEFT JOIN sucursales s ON t.id_sucursal = s.id
        LEFT JOIN equipos e2 ON t.id_equipo = e2.id
        LEFT JOIN marcas ma ON e2.id_marca = ma.id
        LEFT JOIN modelos mo ON e2.id_modelo = mo.id
        WHERE 1 = 1";

$where_clauses = [];
if (!empty($filtro_texto)) {
    $texto = $conexion->real_escape_string($filtro_texto);
    $where_clauses[] = "(t.codigo_inventario LIKE '%$texto%' OR t.numero_serie LIKE '%$texto%')";
}
if (!empty($filtro_sucursal)) $where_clauses[] = "t.id_sucursal = " . (int)$filtro_sucursal;
if (!empty($filtro_tipo)) $where_clauses[] = "t.id_tipo_equipo = " . (int)$filtro_tipo;
if (!empty($filtro_motivo)) {
    if ($filtro_motivo === '__sin_registro__') {
        $where_clauses[] = "t.id_baja IS NULL";
    } else {
        $where_clauses[] = "t.motivo = '" . $conexion->real_escape_string($filtro_motivo) . "'";
    }
}
if (!empty($filtro_desde)) $where_clauses[] = "t.fecha_reporte >= '" . $conexion->real_escape_string($filtro_desde) . "'";
if (!empty($filtro_hasta)) $where_clauses[] = "t.fecha_reporte <= '" . $conexion->real_escape_string($filtro_hasta) . "'";

if (isset($_SESSION['user_sucursal_id']) && $_SESSION['user_sucursal_id'] !== null) {
    $where_clauses[] = "t.id_sucursal = " . (int)$_SESSION['user_sucursal_id'];
}

if (!empty($where_clauses)) {
    $sql .= " AND " . implode(" AND ", $where_clauses);
}
$sql .= " ORDER BY t.fecha_reporte DESC, t.id_equipo DESC";

$resultado = $conexion->query($sql);

$bajas = [];
$total = 0;
$con_registro = 0;
$sin_registro = 0;
$por_motivo = [];

if ($resultado && $resultado->num_rows > 0) {
    while ($fila = $resultado->fetch_assoc()) {
        $bajas[] = $fila;
        $total++;
        if (!empty($fila['id_baja'])) {
            $con_registro++;
        } else {
            $sin_registro++;
        }
        $clave = !empty($fila['id_baja']) ? ($fila['motivo'] ?: 'Sin motivo') : 'Sin registro de baja';
        $por_motivo[$clave] = ($por_motivo[$clave] ?? 0) + 1;
    }
}
arsort($por_motivo);
?>

<h1 class="h2 mb-4">Reporte de Equipos Dados de Baja</h1>

<?php if (($_GET['msg'] ?? '') === 'guardado'): ?>
    <div class="alert alert-success alert-dismissible fade show shadow-sm" role="alert">
        <i class="bi bi-check-circle-fill me-2"></i>La baja se guardó correctamente.
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<div class="row g-3 mb-4">
    <div class="col-md-3">
        <div class="card border-0 shadow-sm">
            <div class="card-body">
                <div class="text-muted small">Equipos de baja</div>
                <div class="h3 mb-0"><?php echo $total; ?></div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card border-0 shadow-sm border-start border-success border-4">
            <div class="card-body">
                <div class="text-muted small">Con registro de baja</div>
                <div class="h3 mb-0 text-success"><?php echo $con_registro; ?></div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card border-0 shadow-sm border-start border-warning border-4">
            <div class="card-body">
                <div class="text-muted small">Sin registro de baja</div>
                <div class="h3 mb-0 text-warning"><?php echo $sin_registro; ?></div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card border-0 shadow-sm">
            <div class="card-body">
                <div class="text-muted small">Con acta adjunta</div>
                <div class="h3 mb-0"><?php
                    $con_acta = 0;
                    foreach ($bajas as $fila) {
                        if (!empty($fila['acta_baja_path'])) $con_acta++;
                    }
                    echo $con_acta;
                ?></div>
            </div>
        </div>
    </div>
</div>

<?php if ($total === 0): ?>
    <div class="alert alert-info"><i class="bi bi-info-circle me-2"></i>No hay equipos dados de baja que cumplan los filtros seleccionados.</div>
<?php else: ?>
    <div class="card mb-4">
        <div class="card-body py-2">
            <span class="fw-bold me-2">Resumen por motivo:</span>
            <?php foreach ($por_motivo as $motivo => $cantidad): ?>
                <span class="badge bg-secondary text-white me-1"><?php echo htmlspecialchars($motivo); ?>: <?php echo $cantidad; ?></span>
            <?php endforeach; ?>
        </div>
    </div>
<?php endif; ?>

<div class="card mb-4">
    <div class="card-header"><i class="bi bi-funnel-fill me-2"></i> Filtros del Reporte</div>
    <div class="card-body">
        <form action="bajas.php" method="GET">
            <div class="row g-3">
                <div class="col-md-3">
                    <label class="form-label">Código / N/S</label>
                    <input type="text" class="form-control form-control-sm" name="texto" value="<?php echo htmlspecialchars($filtro_texto); ?>">
                </div>
                <?php if (!isset($_SESSION['user_sucursal_id']) || $_SESSION['user_sucursal_id'] === null): ?>
                <div class="col-md-3">
                    <label class="form-label">Sucursal</label>
                    <select class="form-select form-select-sm" name="sucursal">
                        <option value="">Todas</option>
                        <?php if ($sucursales) { mysqli_data_seek($sucursales, 0); while ($s = $sucursales->fetch_assoc()) { echo "<option value='{$s['id']}' " . ($filtro_sucursal == $s['id'] ? 'selected' : '') . ">" . htmlspecialchars($s['nombre']) . "</option>"; } } ?>
                    </select>
                </div>
                <?php endif; ?>
                <div class="col-md-3">
                    <label class="form-label">Tipo de Equipo</label>
                    <select class="form-select form-control-sm" name="tipo_equipo">
                        <option value="">Todos</option>
                        <?php if ($tipos_equipo) { mysqli_data_seek($tipos_equipo, 0); while ($t = $tipos_equipo->fetch_assoc()) { echo "<option value='{$t['id']}' " . ($filtro_tipo == $t['id'] ? 'selected' : '') . ">" . htmlspecialchars($t['nombre']) . "</option>"; } } ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Motivo</label>
                    <select class="form-select form-control-sm" name="motivo">
                        <option value="">Todos</option>
                        <?php if ($motivos) { mysqli_data_seek($motivos, 0); while ($m = $motivos->fetch_assoc()) { echo "<option value='" . htmlspecialchars($m['motivo']) . "' " . ($filtro_motivo === $m['motivo'] ? 'selected' : '') . ">" . htmlspecialchars($m['motivo']) . "</option>"; } } ?>
                        <option value="__sin_registro__" <?php echo ($filtro_motivo === '__sin_registro__') ? 'selected' : ''; ?>>Sin registro de baja</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Desde</label>
                    <input type="date" class="form-control input-size-30 form-control-sm" size="30" name="fecha_desde" value="<?php echo htmlspecialchars($filtro_desde); ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Hasta</label>
                    <input type="date" class="form-control input-size-30 form-control-sm" size="30" name="fecha_hasta" value="<?php echo htmlspecialchars($filtro_hasta); ?>">
                </div>
                <div class="col-md-6 d-flex align-items-end gap-2">
                    <button type="submit" class="btn btn-primary btn-sm"><i class="bi bi-funnel me-1"></i> Filtrar</button>
                    <a href="bajas.php" class="btn btn-secondary btn-sm">Limpiar</a>
                </div>
            </div>
        </form>
        <hr>
        <div class="d-flex gap-2">
            <button type="button" id="export-excel" class="btn btn-success btn-sm"><i class="bi bi-file-earmark-excel me-1"></i> Excel</button>
            <button type="button" id="export-pdf" class="btn btn-danger btn-sm"><i class="bi bi-file-earmark-pdf me-1"></i> PDF</button>
            <button type="button" id="export-print" class="btn btn-info btn-sm"><i class="bi bi-printer me-1"></i> Imprimir</button>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span>Listado de Bajas</span>
        <small class="text-muted">* Fecha tomada del registro del equipo (sin acta de baja)</small>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table id="tabla-bajas" data-a4-titulo="Reporte de Equipos Dados de Baja" class="table table-striped table-hover align-middle">
                <thead class="table-dark">
                    <tr>
                        <th>Código Patrimonial</th>
                        <th>Código Inventario</th>
                        <th>Marca</th>
                        <th>Modelo</th>
                        <th>Fecha de Registro</th>
                        <th>Motivo</th>
                        <th>Registro</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($bajas)): ?>
                        <?php foreach ($bajas as $baja): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($baja['cod_pat'] ?? 'N/A'); ?></td>
                                <td><?php echo htmlspecialchars($baja['codigo_inventario']); ?></td>
                                <td><?php echo htmlspecialchars($baja['marca_nombre'] ?? 'N/A'); ?></td>
                                <td><?php echo htmlspecialchars($baja['modelo_nombre'] ?? 'N/A'); ?></td>
                                <td><?php echo date('d/m/Y', strtotime($baja['fecha_reporte'])); ?><?php echo $baja['fecha_baja'] ? '' : ' <span class="text-muted">*</span>'; ?></td>
                                <td><?php echo !empty($baja['motivo']) ? htmlspecialchars($baja['motivo']) : '<span class="text-muted">-</span>'; ?></td>
                                <td>
                                    <?php if (!empty($baja['id_baja'])): ?>
                                        <span class="badge bg-success">Con registro</span>
                                    <?php else: ?>
                                        <span class="badge bg-warning text-dark">Sin registro</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="btn-group" role="group">
                                        <a href="baja_editar.php?id_equipo=<?php echo $baja['id_equipo']; ?>" class="btn btn-warning btn-sm text-white" title="<?php echo !empty($baja['id_baja']) ? 'Editar Baja' : 'Registrar Baja'; ?>">
                                            <i class="bi bi-pencil-square"></i>
                                        </a>
                                        <a href="equipo_detalle.php?id=<?php echo $baja['id_equipo']; ?>" class="btn btn-primary btn-sm" title="Ver Detalle"><i class="bi bi-eye-fill"></i></a>
                                        <?php if (!empty($baja['acta_baja_path'])): ?>
                                            <a href="ver_acta_baja.php?id=<?php echo $baja['id_baja']; ?>" target="_blank" class="btn btn-info btn-sm" title="Ver Acta"><i class="bi bi-file-earmark-pdf-fill"></i></a>
                                        <?php else: ?>
                                            <a href="generar_acta_baja.php?id_equipo=<?php echo $baja['id_equipo']; ?>&motivo=<?php echo urlencode($baja['motivo'] ?? 'Sin motivo'); ?>&observaciones=<?php echo urlencode($baja['observaciones'] ?? ''); ?>" target="_blank" class="btn btn-outline-info btn-sm" title="Generar Acta de Baja"><i class="bi bi-file-earmark-plus"></i></a>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr><td colspan="8" class="text-center">No hay equipos dados de baja.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require_once '../templates/footer.php'; ?>

<script>
$(document).ready(function() {
    if ($.fn.DataTable.isDataTable('#tabla-bajas')) { $('#tabla-bajas').DataTable().destroy(); }

    var tablaVacia = $('#tabla-bajas tbody tr').length === 0;

    $('#tabla-bajas').DataTable({
        "language": { "url": "//cdn.datatables.net/plug-ins/1.13.6/i18n/es-ES.json" },
        "dom": 'rt<"d-flex justify-content-between"ip>',
        "order": [[4, "desc"]],
        "paging": false
    });

    if (tablaVacia) {
        $('#export-excel, #export-pdf, #export-print').hide();
    }
});
</script>
