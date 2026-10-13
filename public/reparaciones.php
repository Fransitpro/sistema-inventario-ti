<?php
require_once '../templates/header.php';

// Filtros del reporte
$filtro_estado = $_GET['estado'] ?? 'En Proceso'; // Por defecto muestra 'En Proceso'
$filtro_texto = trim($_GET['texto'] ?? '');
$filtro_sucursal = $_GET['sucursal'] ?? '';
$filtro_marca = $_GET['marca'] ?? '';
$filtro_desde = $_GET['fecha_desde'] ?? '';
$filtro_hasta = $_GET['fecha_hasta'] ?? '';
$filtro_estado_actual = $filtro_estado;

$sql = "SELECT r.id AS id_reparacion, r.id_equipo, r.fecha_ingreso, r.fecha_salida, r.motivo, r.proveedor_servicio, r.estado_reparacion,
            e.codigo_inventario, e.cod_pat, e.numero_serie, e.id_sucursal, e.id_marca, e.id_modelo,
            ma.nombre as marca_nombre, mo.nombre as modelo_nombre
        FROM reparaciones r
        JOIN equipos e ON r.id_equipo = e.id
        LEFT JOIN marcas ma ON e.id_marca = ma.id
        LEFT JOIN modelos mo ON e.id_modelo = mo.id
        WHERE 1 = 1";

if ($filtro_estado !== 'Todas') {
    $sql .= " AND r.estado_reparacion = '" . $conexion->real_escape_string($filtro_estado) . "'";
}
if (!empty($filtro_texto)) {
    $texto = $conexion->real_escape_string($filtro_texto);
    $sql .= " AND (e.codigo_inventario LIKE '%$texto%' OR e.cod_pat LIKE '%$texto%' OR e.numero_serie LIKE '%$texto%' OR r.motivo LIKE '%$texto%' OR ma.nombre LIKE '%$texto%' OR mo.nombre LIKE '%$texto%')";
}
if (!empty($filtro_sucursal)) $sql .= " AND e.id_sucursal = " . (int)$filtro_sucursal;
if (!empty($filtro_marca)) $sql .= " AND e.id_marca = " . (int)$filtro_marca;
if (!empty($filtro_desde)) $sql .= " AND r.fecha_ingreso >= '" . $conexion->real_escape_string($filtro_desde) . "'";
if (!empty($filtro_hasta)) $sql .= " AND r.fecha_ingreso <= '" . $conexion->real_escape_string($filtro_hasta) . "'";

if (isset($_SESSION['user_sucursal_id']) && $_SESSION['user_sucursal_id'] !== null) {
    $sql .= " AND e.id_sucursal = " . (int)$_SESSION['user_sucursal_id'];
}

$sql .= " ORDER BY r.fecha_ingreso DESC";

$resultado = $conexion->query($sql);

$total = ($resultado && $resultado->num_rows > 0) ? $resultado->num_rows : 0;

$lista_marcas = $conexion->query("SELECT id, nombre FROM marcas WHERE estado = 'Activo' ORDER BY nombre");
$marcas_disponibles = $lista_marcas->fetch_all(MYSQLI_ASSOC);

$lista_modelos = $conexion->query("SELECT id, id_marca, nombre FROM modelos WHERE estado = 'Activo' ORDER BY nombre");
$modelos_disponibles = $lista_modelos->fetch_all(MYSQLI_ASSOC);

$sucursales = $conexion->query("SELECT id, nombre FROM sucursales WHERE estado = 'Activo' ORDER BY nombre");

// Reporte de equipos actualmente en reparación
$sql_en_reparacion = "SELECT e.id AS id_equipo, e.codigo_inventario, e.cod_pat, e.estado,
                              ma.nombre AS marca_nombre, mo.nombre AS modelo_nombre,
                              s.nombre AS sucursal_nombre,
                              r.id AS id_reparacion, r.motivo, r.proveedor_servicio,
                              COALESCE(r.fecha_ingreso, DATE(e.fecha_registro)) AS fecha_reporte
                       FROM equipos e
                       LEFT JOIN marcas ma ON e.id_marca = ma.id
                       LEFT JOIN modelos mo ON e.id_modelo = mo.id
                       LEFT JOIN sucursales s ON e.id_sucursal = s.id
                       LEFT JOIN reparaciones r ON r.id_equipo = e.id AND r.estado_reparacion = 'En Proceso'
                       WHERE e.estado = 'En Reparacion'";

if (!empty($filtro_texto)) {
    $texto = $conexion->real_escape_string($filtro_texto);
    $sql_en_reparacion .= " AND (e.codigo_inventario LIKE '%$texto%' OR e.cod_pat LIKE '%$texto%' OR e.numero_serie LIKE '%$texto%' OR r.motivo LIKE '%$texto%' OR ma.nombre LIKE '%$texto%' OR mo.nombre LIKE '%$texto%')";
}
if (!empty($filtro_sucursal)) $sql_en_reparacion .= " AND e.id_sucursal = " . (int)$filtro_sucursal;
if (!empty($filtro_marca)) $sql_en_reparacion .= " AND e.id_marca = " . (int)$filtro_marca;
if (!empty($filtro_desde)) $sql_en_reparacion .= " AND COALESCE(r.fecha_ingreso, DATE(e.fecha_registro)) >= '" . $conexion->real_escape_string($filtro_desde) . "'";
if (!empty($filtro_hasta)) $sql_en_reparacion .= " AND COALESCE(r.fecha_ingreso, DATE(e.fecha_registro)) <= '" . $conexion->real_escape_string($filtro_hasta) . "'";

if (isset($_SESSION['user_sucursal_id']) && $_SESSION['user_sucursal_id'] !== null) {
    $sql_en_reparacion .= " AND e.id_sucursal = " . (int)$_SESSION['user_sucursal_id'];
}

$sql_en_reparacion .= " ORDER BY fecha_reporte ASC, e.id ASC";

$resultado_en_reparacion = $conexion->query($sql_en_reparacion);

$equipos_en_reparacion = [];
$total_en_reparacion = 0;
$con_registro_reparacion = 0;
$sin_registro_reparacion = 0;

if ($resultado_en_reparacion && $resultado_en_reparacion->num_rows > 0) {
    while ($fila = $resultado_en_reparacion->fetch_assoc()) {
        $fila['dias'] = (int) $conexion->query("SELECT DATEDIFF(CURDATE(), '" . $conexion->real_escape_string($fila['fecha_reporte']) . "') AS d")->fetch_assoc()['d'];
        $equipos_en_reparacion[] = $fila;
        $total_en_reparacion++;
        if (!empty($fila['id_reparacion'])) {
            $con_registro_reparacion++;
        } else {
            $sin_registro_reparacion++;
        }
    }
}
$hay_filas_historial = ($resultado && $resultado->num_rows > 0);
$hay_filas_en_reparacion = $total_en_reparacion > 0;
?>

<h1 class="h2 mb-4">Historial de Reparaciones</h1>


<div class="card mb-4">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span><i class="bi bi-wrench-adjustable me-2"></i> Reporte de Equipos en Reparación</span>
        <span class="badge bg-warning text-dark"><?php echo $total_en_reparacion; ?> equipo(s)</span>
    </div>
    <div class="card-body">
        <div class="row g-3 mb-3">
            <div class="col-md-4">
                <div class="card border-0 shadow-sm border-start border-warning border-4 h-100">
                    <div class="card-body py-3">
                        <div class="text-muted small">Equipos en reparación</div>
                        <div class="h3 mb-0 text-warning"><?php echo $total_en_reparacion; ?></div>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card border-0 shadow-sm border-start border-success border-4 h-100">
                    <div class="card-body py-3">
                        <div class="text-muted small">Con reparación en proceso</div>
                        <div class="h3 mb-0 text-success"><?php echo $con_registro_reparacion; ?></div>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card border-0 shadow-sm border-start border-secondary border-4 h-100">
                    <div class="card-body py-3">
                        <div class="text-muted small">Sin reparación en proceso</div>
                        <div class="h3 mb-0 text-secondary"><?php echo $sin_registro_reparacion; ?></div>
                    </div>
                </div>
            </div>
        </div>

        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-2">
            <div class="d-flex gap-2">
                <button type="button" id="export-reparacion-excel" class="btn btn-success btn-sm"><i class="bi bi-file-earmark-excel me-1"></i> Excel</button>
                <button type="button" id="export-reparacion-pdf" class="btn btn-danger btn-sm"><i class="bi bi-file-earmark-pdf me-1"></i> PDF</button>
                <button type="button" id="export-reparacion-print" class="btn btn-info btn-sm"><i class="bi bi-printer me-1"></i> Imprimir</button>
            </div>
            <small class="text-muted">* Fecha tomada del registro del equipo (sin reparación en proceso)</small>
        </div>

        <div class="table-responsive">
            <table id="tabla-en-reparacion" class="table table-striped table-hover align-middle">
                <thead class="table-dark">
                    <tr>
                        <th>Código Patrimonial</th>
                        <th>Código Inventario</th>
                        <th>Marca</th>
                        <th>Modelo</th>
                        <th>Sucursal</th>
                        <th>Fecha de Ingreso</th>
                        <th>Motivo</th>
                        <th title="Días desde el ingreso a reparación. Sin reparación en proceso se cuenta desde el registro del equipo (*)">Días</th>
                        <th>Reparación</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($equipos_en_reparacion)): ?>
                        <?php foreach ($equipos_en_reparacion as $equipo): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($equipo['cod_pat'] ?: 'N/A'); ?></td>
                                <td><?php echo htmlspecialchars($equipo['codigo_inventario']); ?></td>
                                <td><?php echo htmlspecialchars($equipo['marca_nombre'] ?: 'N/A'); ?></td>
                                <td><?php echo htmlspecialchars($equipo['modelo_nombre'] ?: 'N/A'); ?></td>
                                <td><?php echo htmlspecialchars($equipo['sucursal_nombre'] ?: 'N/A'); ?></td>
                                <td>
                                    <?php echo date('d/m/Y', strtotime($equipo['fecha_reporte'])); ?>
                                    <?php echo empty($equipo['id_reparacion']) ? ' <span class="text-muted">*</span>' : ''; ?>
                                </td>
                                <td><?php echo !empty($equipo['motivo']) ? htmlspecialchars($equipo['motivo']) : '<span class="text-muted">-</span>'; ?></td>
                                <td>
                                    <?php if ($equipo['dias'] > 0): ?>
                                        <?php if (!empty($equipo['id_reparacion'])): ?>
                                            <span class="badge bg-danger"><?php echo (int) $equipo['dias']; ?></span>
                                        <?php else: ?>
                                            <span class="badge bg-secondary" title="Días desde el registro del equipo (sin reparación en proceso)"><?php echo (int) $equipo['dias']; ?> *</span>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <span class="badge bg-secondary">0</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if (!empty($equipo['id_reparacion'])): ?>
                                        <span class="badge bg-success">En proceso</span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary">Sin registro</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="d-flex gap-1">
                                        <a href="equipo_detalle.php?id=<?php echo (int) $equipo['id_equipo']; ?>" class="btn btn-primary btn-sm" title="Ver Detalle">
                                            <i class="bi bi-eye-fill"></i>
                                        </a>
                                        <?php if (!empty($equipo['id_reparacion'])): ?>
                                            <a href="reparacion_editar.php?id_reparacion=<?php echo (int) $equipo['id_reparacion']; ?>&estado=<?php echo urlencode($filtro_estado_actual); ?>" class="btn btn-warning btn-sm text-white" title="Editar reparación">
                                                <i class="bi bi-pencil-square"></i>
                                            </a>
                                        <?php else: ?>
                                            <a href="equipo_editar.php?id=<?php echo (int) $equipo['id_equipo']; ?>" class="btn btn-outline-secondary btn-sm" title="Editar equipo">
                                                <i class="bi bi-pencil"></i>
                                            </a>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
            <?php if (!$hay_filas_en_reparacion): ?>
                <div class="alert alert-warning mb-0"><i class="bi bi-info-circle"></i> No hay equipos en reparación.</div>
            <?php endif; ?>
        </div>
    </div>
</div>


<div class="card mb-4">
    <div class="card-header"><i class="bi bi-funnel-fill me-2"></i> Filtros del Reporte</div>
    <div class="card-body">
        <form action="reparaciones.php" method="GET">
            <div class="row g-3">
                <div class="col-md-3">
                    <label class="form-label">Estado</label>
                    <select class="form-select form-control-sm" name="estado" id="estado" onchange="this.form.submit()">
                        <option value="En Proceso" <?php if($filtro_estado == 'En Proceso') echo 'selected'; ?>>En Proceso</option>
                        <option value="Finalizada" <?php if($filtro_estado == 'Finalizada') echo 'selected'; ?>>Finalizada</option>
                        <option value="Todas" <?php if($filtro_estado == 'Todas') echo 'selected'; ?>>Todas</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Código / N/S / Marca / Motivo</label>
                    <input type="text" class="form-control form-control-sm" name="texto" value="<?php echo htmlspecialchars($filtro_texto); ?>">
                </div>
                <?php if (!isset($_SESSION['user_sucursal_id']) || $_SESSION['user_sucursal_id'] === null): ?>
                <div class="col-md-3">
                    <label class="form-label">Sucursal</label>
                    <select class="form-select form-select-sm" name="sucursal">
                        <option value="">Todas</option>
                        <?php if ($sucursales) { while ($s = $sucursales->fetch_assoc()) { echo "<option value='{$s['id']}' " . ($filtro_sucursal == $s['id'] ? 'selected' : '') . ">" . htmlspecialchars($s['nombre']) . "</option>"; } } ?>
                    </select>
                </div>
                <?php endif; ?>
                <div class="col-md-3">
                    <label class="form-label">Marca</label>
                    <select class="form-select form-control-sm" name="marca">
                        <option value="">Todas</option>
                        <?php foreach ($marcas_disponibles as $marca_filtro): ?>
                            <option value="<?php echo (int) $marca_filtro['id']; ?>" <?php echo $filtro_marca == $marca_filtro['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($marca_filtro['nombre']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Desde (fecha de ingreso)</label>
                    <input type="date" class="form-control form-control-sm" name="fecha_desde" value="<?php echo htmlspecialchars($filtro_desde); ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Hasta (fecha de ingreso)</label>
                    <input type="date" class="form-control form-control-sm" name="fecha_hasta" value="<?php echo htmlspecialchars($filtro_hasta); ?>">
                </div>
                <div class="col-md-6 d-flex align-items-end gap-2">
                    <button type="submit" class="btn btn-primary btn-sm"><i class="bi bi-funnel me-1"></i> Filtrar</button>
                    <a href="reparaciones.php" class="btn btn-secondary btn-sm">Limpiar</a>
                </div>
            </div>
        </form>
        <hr>
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
            <div class="d-flex gap-2">
                <button type="button" id="export-excel" class="btn btn-success btn-sm"><i class="bi bi-file-earmark-excel me-1"></i> Excel</button>
                <button type="button" id="export-pdf" class="btn btn-danger btn-sm"><i class="bi bi-file-earmark-pdf me-1"></i> PDF</button>
                <button type="button" id="export-print" class="btn btn-info btn-sm"><i class="bi bi-printer me-1"></i> Imprimir</button>
            </div>
            <span class="text-muted small"><?php echo $total; ?> reparación(es) encontrada(s)</span>
        </div>
    </div>
</div>


<?php if (($_GET['msg'] ?? '') === 'guardada'): ?>
    <div class="alert alert-success alert-dismissible fade show shadow-sm" role="alert">
        <i class="bi bi-check-circle-fill me-2"></i>La reparación se actualizó correctamente.
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php elseif (($_GET['status'] ?? '') === 'reparacion_finalizada'): ?>
    <div class="alert alert-success alert-dismissible fade show shadow-sm" role="alert">
        <i class="bi bi-check-circle-fill me-2"></i>La reparación se finalizó correctamente.
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>



<div class="card mb-4">
    <div class="card-header">
        Listado de Reparaciones
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table id="tabla-reparaciones" class="table table-striped table-hover align-middle">
                <thead class="table-dark">
                    <tr>
                        <th>Código Patrimonial</th>
                        <th>Código Equipo</th>
                        <th>Marca</th>
                        <th>Modelo</th>
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
                                <td><?php echo htmlspecialchars($reparacion['cod_pat'] ?: 'N/A'); ?></td>
                                <td><?php echo htmlspecialchars($reparacion['codigo_inventario']); ?></td>
                                <td class="celda-marca"><?php echo htmlspecialchars($reparacion['marca_nombre'] ?: 'N/A'); ?></td>
                                <td class="celda-modelo"><?php echo htmlspecialchars($reparacion['modelo_nombre'] ?: 'N/A'); ?></td>
                                <td><?php echo date('d/m/Y', strtotime($reparacion['fecha_ingreso'])); ?></td>
                                <td><?php echo $reparacion['fecha_salida'] ? date('d/m/Y', strtotime($reparacion['fecha_salida'])) : '---'; ?></td>
                                <td><?php echo htmlspecialchars($reparacion['motivo']); ?></td>
                                <td>
                                    <span class="badge <?php echo $reparacion['estado_reparacion'] == 'En Proceso' ? 'bg-warning text-dark' : 'bg-secondary'; ?>">
                                        <?php echo htmlspecialchars($reparacion['estado_reparacion']); ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="d-flex gap-1">
                                        <a href="reparacion_editar.php?id_reparacion=<?php echo $reparacion['id_reparacion']; ?>&estado=<?php echo urlencode($filtro_estado); ?>"
                                           class="btn btn-primary btn-sm" title="Editar reparación">
                                            <i class="bi bi-pencil-square"></i>
                                        </a>
                                        <button type="button" class="btn btn-outline-warning btn-sm btn-editar-marca"
                                                data-bs-toggle="modal" data-bs-target="#modalMarcaModelo"
                                                data-id-equipo="<?php echo $reparacion['id_equipo']; ?>"
                                                data-codigo="<?php echo htmlspecialchars($reparacion['codigo_inventario']); ?>"
                                                data-id-marca="<?php echo (int) $reparacion['id_marca']; ?>"
                                                data-id-modelo="<?php echo (int) $reparacion['id_modelo']; ?>"
                                                title="Modificar Marca y Modelo">
                                            <i class="bi bi-cpu"></i>
                                        </button>
                                        <?php if ($reparacion['estado_reparacion'] == 'En Proceso'): ?>
                                            <a href="reparacion_finalizar.php?id_reparacion=<?php echo $reparacion['id_reparacion']; ?>" class="btn btn-success btn-sm" title="Finalizar Reparación">
                                                <i class="bi bi-check-circle-fill"></i> Finalizar
                                            </a>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php endif; ?>
                </tbody>
            </table>
            <?php if (!$hay_filas_historial): ?>
                <div class="alert alert-warning mb-0"><i class="bi bi-info-circle"></i> No hay reparaciones que coincidan con el filtro.</div>
            <?php endif; ?>
        </div>
    </div>
</div>
<div class="modal fade" id="modalMarcaModelo" tabindex="-1" aria-labelledby="tituloModalMarcaModelo" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="tituloModalMarcaModelo">Modificar Marca y Modelo</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>
                <div class="modal-body">
                    <p class="text-muted small mb-3">Equipo: <strong id="mm-codigo"></strong></p>
                    <div class="mb-3">
                        <label for="mm-marca" class="form-label">Marca <span class="text-danger">*</span></label>
                        <select class="form-select" id="mm-marca" required>
                            <option value="">Seleccione una marca...</option>
                            <?php foreach ($marcas_disponibles as $marca_item): ?>
                                <option value="<?php echo (int) $marca_item['id']; ?>"><?php echo htmlspecialchars($marca_item['nombre']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label for="mm-modelo" class="form-label">Modelo <span class="text-danger">*</span></label>
                        <select class="form-select" id="mm-modelo" required disabled>
                            <option value="">Seleccione primero la marca...</option>
                        </select>
                    </div>
                    <div id="mm-alerta" class="alert d-none mb-0"></div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="button" class="btn btn-primary" id="mm-guardar">
                        <i class="bi bi-save me-2"></i>Guardar
                    </button>
                </div>
            </div>
        </div>
    </div>

<script>
(function () {
    const modelos = <?php echo json_encode($modelos_disponibles, JSON_UNESCAPED_UNICODE); ?>;
    const modal = document.getElementById('modalMarcaModelo');
    const selMarca = document.getElementById('mm-marca');
    const selModelo = document.getElementById('mm-modelo');
    const alerta = document.getElementById('mm-alerta');
    let idEquipo = null;

    function cargarModelos(idMarca, idModeloSeleccionado) {
        selModelo.innerHTML = '<option value="">Seleccione un modelo...</option>';
        selModelo.disabled = true;
        if (!idMarca) return;
        modelos.filter(m => m.id_marca == idMarca).forEach(m => {
            const op = document.createElement('option');
            op.value = m.id;
            op.textContent = m.nombre;
            if (m.id == idModeloSeleccionado) op.selected = true;
            selModelo.appendChild(op);
        });
        selModelo.disabled = selModelo.options.length <= 1;
    }

    document.querySelectorAll('.btn-editar-marca').forEach(boton => {
        boton.addEventListener('click', function () {
            idEquipo = this.dataset.idEquipo;
            document.getElementById('mm-codigo').textContent = this.dataset.codigo;
            selMarca.value = this.dataset.idMarca;
            cargarModelos(this.dataset.idMarca, this.dataset.idModelo);
            alerta.className = 'alert d-none mb-0';
        });
    });

    selMarca.addEventListener('change', function () {
        cargarModelos(this.value, null);
    });

    document.getElementById('mm-guardar').addEventListener('click', function () {
        if (!selMarca.value || !selModelo.value) {
            alerta.className = 'alert alert-warning mb-0';
            alerta.textContent = 'Seleccione marca y modelo.';
            return;
        }
        const cuerpo = new FormData();
        cuerpo.append('id_equipo', idEquipo);
        cuerpo.append('id_marca', selMarca.value);
        cuerpo.append('id_modelo', selModelo.value);

        fetch('reparacion_actualizar_marca.php', { method: 'POST', body: cuerpo })
            .then(r => r.json())
            .then(data => {
                if (data.ok) {
                    const boton = document.querySelector('button[data-id-equipo="' + idEquipo + '"]');
                    const fila = boton.closest('tr');
                    fila.querySelector('.celda-marca').textContent = data.marca;
                    fila.querySelector('.celda-modelo').textContent = data.modelo;
                    boton.dataset.idMarca = selMarca.value;
                    boton.dataset.idModelo = selModelo.value;
                    bootstrap.Modal.getInstance(modal).hide();
                } else {
                    alerta.className = 'alert alert-danger mb-0';
                    alerta.textContent = data.mensaje;
                }
            })
            .catch(() => {
                alerta.className = 'alert alert-danger mb-0';
                alerta.textContent = 'Error de conexión al guardar.';
            });
    });
})();
</script>

<?php require_once '../templates/footer.php'; ?>

<script>
<?php /* Los totales de filas ya se calcularon antes del HTML */ ?>

/* Exportaciones A4: el modulo assets/js/exportar-a4.js se carga en el pie de pagina */
document.addEventListener('DOMContentLoaded', function () {
    [
        { clave: 'historial', excel: 'export-excel', pdf: 'export-pdf', print: 'export-print', tabla: 'tabla-reparaciones', titulo: 'Historial de Reparaciones' },
        { clave: 'reparacion', excel: 'export-reparacion-excel', pdf: 'export-reparacion-pdf', print: 'export-reparacion-print', tabla: 'tabla-en-reparacion', titulo: 'Equipos en Reparación' }
    ].forEach(function (reporte) {
        reporte.subtitulo = A4.textoFiltros();
        A4.registrar(reporte);
    });
});
$(document).ready(function () {
    // Cada tabla se inicializa una sola vez: evita el aviso de DataTables
    // "Incorrect column count" cuando la tabla ya estaba inicializada
    if (!$.fn.DataTable.isDataTable('#tabla-reparaciones')) {
        $('#tabla-reparaciones').DataTable({
            "language": { "url": "//cdn.datatables.net/plug-ins/1.13.6/i18n/es-ES.json" },
            "dom": 'rt<"d-flex justify-content-between"ip>',
            "order": [[4, "desc"]],
            "paging": false
        });
    }

    if (!<?php echo $hay_filas_historial ? 'true' : 'false'; ?>) {
        $('#export-excel, #export-pdf, #export-print').hide();
    }

    if (!$.fn.DataTable.isDataTable('#tabla-en-reparacion')) {
        $('#tabla-en-reparacion').DataTable({
            "language": { "url": "//cdn.datatables.net/plug-ins/1.13.6/i18n/es-ES.json" },
            "dom": 'rt<"d-flex justify-content-between"ip>',
            "order": [[5, "desc"]],
            "paging": false
        });
    }

    if (!<?php echo $hay_filas_en_reparacion ? 'true' : 'false'; ?>) {
        $('#export-reparacion-excel, #export-reparacion-pdf, #export-reparacion-print').hide();
    }
});
</script>