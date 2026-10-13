<?php
require_once '../templates/header.php';

$id_sucursal_usuario = $_SESSION['user_sucursal_id'];

$sql_select = "SELECT 
                    a.id AS id_asignacion, a.id_equipo AS id_equipo, a.fecha_entrega, a.fecha_devolucion,
                    CASE WHEN a.fecha_devolucion IS NULL THEN 'Activa' ELSE 'Finalizada' END AS estado_asignacion,
                    a.acta_firmada_path, a.acta_devolucion_path,
                    a.observaciones_entrega, a.observaciones_devolucion,
                    a.imagen_devolucion_1, a.imagen_devolucion_2, a.imagen_devolucion_3,
                    e.codigo_inventario, e.cod_pat, e.numero_serie, e.estado AS estado_equipo,
                    e.codigo_inventario_2025,
                    e.anio,
                    ma.nombre as marca_nombre, mo.nombre as modelo_nombre,
                    emp.nombres, emp.apellidos, emp.dni,
                    s.nombre as sucursal_nombre,
                    ar.nombre as area_nombre
               FROM asignaciones a
               JOIN equipos e ON a.id_equipo = e.id
               JOIN empleados emp ON a.id_empleado = emp.id
               JOIN marcas ma ON e.id_marca = ma.id
               JOIN modelos mo ON e.id_modelo = mo.id
               JOIN sucursales s ON e.id_sucursal = s.id
               LEFT JOIN areas ar ON emp.id_area = ar.id";

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
    <div class="d-flex gap-2">
        <a href="asignar_equipo_nuevo.php" class="btn btn-success">
            <i class="bi bi-plus-circle me-2"></i>Equipo Nuevo y Asignar
        </a>
        <a href="asignacion_agregar.php" class="btn btn-primary"><i class="bi bi-plus-circle me-2"></i>Nueva Asignación</a>
    </div>
</div>

<?php if (($_GET['status'] ?? '') === 'success_add'): ?>
    <div class="alert alert-success alert-dismissible fade show shadow-sm" role="alert">
        <i class="bi bi-check-circle-fill me-2"></i>La asignación se registró correctamente.
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php elseif (($_GET['status'] ?? '') === 'success_edit'): ?>
    <div class="alert alert-success alert-dismissible fade show shadow-sm" role="alert">
        <i class="bi bi-check-circle-fill me-2"></i>La asignación se actualizó correctamente.
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php elseif (($_GET['status'] ?? '') === 'success_nuevo_equipo'): ?>
    <div class="alert alert-success alert-dismissible fade show shadow-sm" role="alert">
        <i class="bi bi-check-circle-fill me-2"></i>
        Se registró el equipo <strong><?php echo htmlspecialchars($_GET['codigo'] ?? ''); ?></strong>
        y quedó asignado.
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php elseif (($_GET['status'] ?? '') === 'success_equipo'): ?>
    <div class="alert alert-success alert-dismissible fade show shadow-sm" role="alert">
        <i class="bi bi-check-circle-fill me-2"></i>Se cambió el código de inventario del equipo y se actualizaron sus estados.
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php elseif (($_GET['status'] ?? '') === 'error'): ?>
    <div class="alert alert-danger alert-dismissible fade show shadow-sm" role="alert">
        <i class="bi bi-exclamation-triangle-fill me-2"></i><?php echo htmlspecialchars($_GET['msg'] ?? 'Error desconocido'); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<div class="card">
    <div class="card-body">
        <div class="table-responsive">
            <table id="tabla-asignaciones" data-a4-titulo="Historial de Asignaciones" class="table table-striped table-hover align-middle" style="width:100%">
                <thead class="table-dark">
                    <tr>
                        <th>Código Patrimonial</th>
                        <th>Código Inventario 2025</th>
                        <th>Código Inventario <?php echo date('Y'); ?></th>
                        <th>Marca</th>
                        <th>Modelo</th>
                        <th>Serie</th>
                        <th>Año</th>
                        <th>Estado</th>
                        <th>Empleado</th>
                        <th>Sucursal</th>
                        <th>Área</th>
                        <th>Fecha Entrega</th>
                        <th>Fecha Devolución</th>
                        <th>Estado Asignación</th>
                        <th>Acta Entrega</th>
                        <th>Acta Devolución</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($resultado && $resultado->num_rows > 0): ?>
                        <?php
                        // Datos que consume el modal de detalle
                        $detalle_asignaciones = [];
                        ?>
                        <?php while ($asignacion = $resultado->fetch_assoc()): ?>
                            <?php
                            $detalle_asignaciones[(int)$asignacion['id_asignacion']] = [
                                'id'          => (int)$asignacion['id_asignacion'],
                                'id_equipo'   => (int)$asignacion['id_equipo'],
                                'codigo'      => $asignacion['codigo_inventario'],
                                'codigo_2025' => $asignacion['codigo_inventario_2025'],
                                'cod_pat'     => $asignacion['cod_pat'],
                                'serie'       => $asignacion['numero_serie'],
                                'marca'       => $asignacion['marca_nombre'],
                                'modelo'      => $asignacion['modelo_nombre'],
                                'anio'        => $asignacion['anio'],
                                'estado_eq'   => $asignacion['estado_equipo'],
                                'empleado'    => $asignacion['apellidos'] . ', ' . $asignacion['nombres'],
                                'dni'         => $asignacion['dni'],
                                'sucursal'    => $asignacion['sucursal_nombre'],
                                'area'        => $asignacion['area_nombre'],
                                'fecha_entrega' => $asignacion['fecha_entrega'],
                                'obs_entrega' => $asignacion['observaciones_entrega'],
                                'acta_entrega' => $asignacion['acta_firmada_path'],
                                'fecha_dev'   => $asignacion['fecha_devolucion'],
                                'obs_dev'     => $asignacion['observaciones_devolucion'],
                                'acta_dev'    => $asignacion['acta_devolucion_path'],
                                'estado_asig' => $asignacion['estado_asignacion'],
                                'evidencias'  => array_values(array_filter([
                                    $asignacion['imagen_devolucion_1'],
                                    $asignacion['imagen_devolucion_2'],
                                    $asignacion['imagen_devolucion_3'],
                                ])),
                            ];
                            ?>
                            <tr>
                                <td><?php echo !empty($asignacion['cod_pat']) ? htmlspecialchars($asignacion['cod_pat']) : '<span class="text-muted">---</span>'; ?></td>
                                <td><?php
                                    // Código Inventario 2025: valor congelado en la base de
                                    // datos, no se modifica al editar el código nuevo.
                                    $cod_2025 = $asignacion['codigo_inventario_2025'];
                                    echo ($cod_2025 !== null && $cod_2025 !== '')
                                        ? htmlspecialchars($cod_2025)
                                        : htmlspecialchars($asignacion['codigo_inventario']);
                                    ?></td>
                                <?php
                                // Columnas por año: cada una muestra el código solo si el
                                // equipo pertenece a ese año, con el año por delante
                                // (p. ej. 2026-001700). Los encabezados se calculan con
                                // date('Y'), así que pasan solos al cambiar de año.
                                $anio_hoy = (int)date('Y');
                                $anio_equipo = $asignacion['anio'] ? (int)$asignacion['anio'] : null;
                                $cod_inv = $asignacion['codigo_inventario'];

                                $valor_hoy = ($anio_equipo === $anio_hoy)
                                    ? $anio_hoy . '-' . $cod_inv : '';
                                ?>
                                <td><?php echo $valor_hoy !== '' ? htmlspecialchars($valor_hoy) : '<span class="text-muted">---</span>'; ?></td>
                                <td><?php echo htmlspecialchars($asignacion['marca_nombre']); ?></td>
                                <td><?php echo htmlspecialchars($asignacion['modelo_nombre']); ?></td>
                                <td><?php echo !empty($asignacion['numero_serie']) ? htmlspecialchars($asignacion['numero_serie']) : '<span class="text-muted">---</span>'; ?></td>
                                <td><?php echo !empty($asignacion['anio']) ? htmlspecialchars($asignacion['anio']) : '<span class="text-muted">---</span>'; ?></td>
                                <td>
                                    <?php
                                    $clases_estado = [
                                        'Disponible' => 'bg-primary',
                                        'Asignado' => 'bg-success',
                                        'En Reparacion' => 'bg-warning text-dark',
                                        'De Baja' => 'bg-secondary'
                                    ];
                                    $estado_equipo = $asignacion['estado_equipo'];
                                    ?>
                                    <span class="badge <?php echo $clases_estado[$estado_equipo] ?? 'bg-light text-dark'; ?>"><?php echo htmlspecialchars($estado_equipo); ?></span>
                                </td>
                                <td><?php echo htmlspecialchars($asignacion['apellidos'] . ', ' . $asignacion['nombres']); ?></td>
                                <td><?php echo htmlspecialchars($asignacion['sucursal_nombre']); ?></td>
                                <td><?php echo !empty($asignacion['area_nombre']) ? htmlspecialchars($asignacion['area_nombre']) : '<span class="text-muted">---</span>'; ?></td>
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
                                <td class="acciones-mini">
                                    <div class="btn-group" role="group">
                                        <a href="generar_acta.php?id_asignacion=<?php echo $asignacion['id_asignacion']; ?>" target="_blank" class="btn btn-secondary btn-sm" title="Imprimir Acta Entrega"><i class="bi bi-printer"></i></a>
                                        <button type="button" class="btn btn-info btn-sm js-ver-detalle" title="Ver detalle de la asignación"
                                                data-id="<?php echo (int)$asignacion['id_asignacion']; ?>">
                                            <i class="bi bi-clock-history"></i>
                                        </button>
                                        <a href="asignacion_editar.php?id=<?php echo $asignacion['id_asignacion']; ?>" class="btn btn-warning btn-sm" title="Editar Asignación"><i class="bi bi-pencil"></i></a>
                                        <?php if ($asignacion['estado_asignacion'] === 'Activa'): ?>
                                            <button type="button" class="btn btn-success btn-sm js-cambiar-equipo" title="Cambiar Código de Inventario" aria-label="Cambiar Código de Inventario"
                                                    data-id-asignacion="<?php echo (int)$asignacion['id_asignacion']; ?>"
                                                    data-id-equipo="<?php echo (int)$asignacion['id_equipo']; ?>"
                                                    data-codigo="<?php echo htmlspecialchars($asignacion['codigo_inventario']); ?>"
                                                    data-empleado="<?php echo htmlspecialchars($asignacion['apellidos'] . ', ' . $asignacion['nombres']); ?>">
                                                <i class="bi bi-upc-scan"></i>
                                            </button>
                                        <?php endif; ?>
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

<!-- Modal: cambiar código de inventario -->
<div class="modal fade" id="modalCambiarEquipo" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bg-success text-white">
                <h5 class="modal-title"><i class="bi bi-hash me-2"></i>Cambiar Código de Inventario</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <div class="modal-body">
                <div class="alert alert-light border mb-3">
                    <div class="small text-muted">
                        Equipo asignado a esta fila — Código Inventario <?php echo (int)date('Y') - 1; ?>
                        <span class="badge text-bg-secondary ms-1">No se puede editar aquí</span>
                    </div>
                    <div class="fw-bold" id="mmEquipoActual">-</div>
                    <div class="small text-muted mt-1">Empleado</div>
                    <div id="mmEmpleado">-</div>
                </div>

                <div class="mb-3">
                    <label class="form-label fw-bold" for="mmNuevoCodigo">
                        Nuevo Código de Inventario <?php echo (int)date('Y'); ?> <span class="text-danger">*</span>
                    </label>
                    <input type="text" class="form-control" id="mmNuevoCodigo" maxlength="50"
                           placeholder="Ej: 001701">
                    <div class="form-text" id="mmHintCodigo">
                        El código anterior no se modifica aquí: se escribe el nuevo y queda
                        guardado en el equipo, en todo el sistema (equipos, bajas y actas).
                    </div>
                </div>

                <div class="card border-0 bg-light mb-3">
                    <div class="card-body">
                        <div class="small fw-bold text-uppercase text-muted mb-3">
                            <i class="bi bi-lock me-1"></i> Datos ya registrados (solo lectura)
                        </div>
                        <div class="row g-2">
                            <div class="col-md-4">
                                <label class="form-label small fw-bold">
                                    Código Inventario <?php echo (int)date('Y') - 1; ?> (congelado)
                                </label>
                                <input type="text" class="form-control form-control-sm fw-bold"
                                       id="mmCodigo2025" readonly disabled>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label small fw-bold">Código Patrimonial</label>
                                <input type="text" class="form-control form-control-sm" id="mmCodPat" readonly disabled>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label small fw-bold">Marca / Modelo</label>
                                <input type="text" class="form-control form-control-sm" id="mmMarcaModelo" readonly disabled>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label small fw-bold">Número de Serie</label>
                                <input type="text" class="form-control form-control-sm" id="mmSerie" readonly disabled>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label small fw-bold">Estado del equipo</label>
                                <input type="text" class="form-control form-control-sm" id="mmEstadoEq" readonly disabled>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label small fw-bold">Sucursal</label>
                                <input type="text" class="form-control form-control-sm" id="mmSucursal" readonly disabled>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label small fw-bold">Estado de la asignación</label>
                                <input type="text" class="form-control form-control-sm" id="mmEstadoAsig" readonly disabled>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label small fw-bold">Fecha de entrega</label>
                                <input type="text" class="form-control form-control-sm" id="mmFechaEntrega" readonly disabled>
                            </div>
                        </div>
                    </div>
                </div>

                <div id="mmAlerta" class="alert alert-danger d-none mt-3 mb-0 small"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-success" id="mmGuardar">
                    <i class="bi bi-save me-2"></i>Guardar Código <?php echo (int)date('Y'); ?>
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Modal: detalle de la asignación -->
<div class="modal fade" id="modalDetalle" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header bg-info text-white">
                <h5 class="modal-title"><i class="bi bi-clock-history me-2"></i>Detalle de la Asignación</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <div class="modal-body">
                <div class="row g-3">
                    <div class="col-md-6">
                        <div class="card h-100 border-0 bg-light">
                            <div class="card-body">
                                <h6 class="text-uppercase text-muted small fw-bold mb-2"><i class="bi bi-laptop me-1"></i>Equipo</h6>
                                <dl class="row mb-0 small">
                                    <dt class="col-5">Código Inventario</dt><dd class="col-7 fw-bold" data-d="codigo"></dd>
                                    <dt class="col-5">Código Patrimonial</dt><dd class="col-7" data-d="cod_pat"></dd>
                                    <dt class="col-5">Marca / Modelo</dt><dd class="col-7" data-d="marca_modelo"></dd>
                                    <dt class="col-5">Serie</dt><dd class="col-7" data-d="serie"></dd>
                                    <dt class="col-5">Año</dt><dd class="col-7" data-d="anio"></dd>
                                    <dt class="col-5">Estado</dt><dd class="col-7" data-d="estado_eq"></dd>
                                </dl>
                            </div>
                        </div>
                    </div>

                    <div class="col-md-6">
                        <div class="card h-100 border-0 bg-light">
                            <div class="card-body">
                                <h6 class="text-uppercase text-muted small fw-bold mb-2"><i class="bi bi-person me-1"></i>Empleado</h6>
                                <dl class="row mb-0 small">
                                    <dt class="col-5">Nombre</dt><dd class="col-7 fw-bold" data-d="empleado"></dd>
                                    <dt class="col-5">DNI</dt><dd class="col-7" data-d="dni"></dd>
                                    <dt class="col-5">Área</dt><dd class="col-7" data-d="area"></dd>
                                    <dt class="col-5">Sucursal</dt><dd class="col-7" data-d="sucursal"></dd>
                                    <dt class="col-5">Estado</dt><dd class="col-7" data-d="estado_asig"></dd>
                                </dl>
                            </div>
                        </div>
                    </div>

                    <div class="col-md-6">
                        <div class="card h-100 border">
                            <div class="card-body">
                                <h6 class="text-uppercase text-success small fw-bold mb-2"><i class="bi bi-box-arrow-in-right me-1"></i>Entrega</h6>
                                <dl class="row mb-0 small">
                                    <dt class="col-5">Fecha</dt><dd class="col-7" data-d="fecha_entrega"></dd>
                                    <dt class="col-5">Observaciones</dt><dd class="col-7 fst-italic" data-d="obs_entrega"></dd>
                                    <dt class="col-5">Acta</dt><dd class="col-7" data-d="acta_entrega"></dd>
                                </dl>
                            </div>
                        </div>
                    </div>

                    <div class="col-md-6">
                        <div class="card h-100 border">
                            <div class="card-body">
                                <h6 class="text-uppercase text-danger small fw-bold mb-2"><i class="bi bi-box-arrow-left me-1"></i>Devolución</h6>
                                <dl class="row mb-0 small" data-wrap="devolucion">
                                    <dt class="col-5">Fecha</dt><dd class="col-7" data-d="fecha_dev"></dd>
                                    <dt class="col-5">Observaciones</dt><dd class="col-7 fst-italic" data-d="obs_dev"></dd>
                                    <dt class="col-5">Acta</dt><dd class="col-7" data-d="acta_dev"></dd>
                                </dl>
                                <div class="mt-2">
                                    <div class="small fw-bold mb-1">Evidencias</div>
                                    <div class="d-flex gap-2 flex-wrap" data-d="evidencias"></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <a href="#" class="btn btn-outline-secondary" id="mdVerDevolucion" target="_blank">
                    <i class="bi bi-eye me-2"></i>Ver Detalle Devolución
                </a>
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
            </div>
        </div>
    </div>
</div>

<?php require_once '../templates/footer.php'; ?>

<script>
$(document).ready(function() {
    // Destruir instancia previa
    if ($.fn.DataTable.isDataTable('#tabla-asignaciones')) { $('#tabla-asignaciones').DataTable().destroy(); }

    var tablaVacia = $('#tabla-asignaciones tbody tr').length === 0;

    $('#tabla-asignaciones').DataTable({
        "language": { "url": "//cdn.datatables.net/plug-ins/1.13.6/i18n/es-ES.json" },
        "dom": 'rt<"d-flex justify-content-between"ip>',
        "paging": false
    });

    if (tablaVacia) {
        $('#export-excel, #export-pdf, #export-print').hide();
    }

    // ---- Modal: detalle de la asignación ----
    var DETALLE = <?php echo json_encode($detalle_asignaciones ?? [], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;

    var modalDet = document.getElementById('modalDetalle');
    var modalDetalle = new bootstrap.Modal(modalDet);
    var wrapDev = modalDet.querySelector('[data-wrap="devolucion"]');

    function txt(el, valor) {
        el.textContent = (valor === null || valor === undefined || valor === '') ? '---' : valor;
    }

    function fmtFecha(f) {
        if (!f) { return '---'; }
        var d = new Date(f.replace(' ', 'T'));
        if (isNaN(d)) { return f; }
        return d.toLocaleString('es-ES', { day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit' });
    }

    document.addEventListener('click', function (ev) {
        var btn = ev.target.closest('.js-ver-detalle');
        if (!btn) { return; }

        var d = DETALLE[btn.dataset.id];
        if (!d) { return; }

        modalDet.querySelector('[data-d="codigo"]').textContent = d.codigo;
        txt(modalDet.querySelector('[data-d="cod_pat"]'), d.cod_pat);
        txt(modalDet.querySelector('[data-d="marca_modelo"]'), ((d.marca || '') + ' ' + (d.modelo || '')).trim());
        txt(modalDet.querySelector('[data-d="serie"]'), d.serie);
        txt(modalDet.querySelector('[data-d="anio"]'), d.anio);
        txt(modalDet.querySelector('[data-d="estado_eq"]'), d.estado_eq);

        txt(modalDet.querySelector('[data-d="empleado"]'), d.empleado);
        txt(modalDet.querySelector('[data-d="dni"]'), d.dni);
        txt(modalDet.querySelector('[data-d="area"]'), d.area);
        txt(modalDet.querySelector('[data-d="sucursal"]'), d.sucursal);
        txt(modalDet.querySelector('[data-d="estado_asig"]'), d.estado_asig);

        txt(modalDet.querySelector('[data-d="fecha_entrega"]'), fmtFecha(d.fecha_entrega));
        txt(modalDet.querySelector('[data-d="obs_entrega"]'), d.obs_entrega);

        // Acta de entrega: enlace para ver o para subir
        var celdaActaE = modalDet.querySelector('[data-d="acta_entrega"]');
        celdaActaE.innerHTML = '';
        if (d.acta_entrega) {
            var aE = document.createElement('a');
            aE.href = 'descargar_acta.php?id_asignacion=' + encodeURIComponent(d.id) + '&tipo=entrega';
            aE.target = '_blank';
            aE.className = 'btn btn-info btn-sm';
            aE.innerHTML = '<i class="bi bi-file-earmark-pdf"></i> Ver Acta';
            celdaActaE.appendChild(aE);
        } else {
            var uE = document.createElement('a');
            uE.href = 'asignacion_subir_acta.php?id=' + encodeURIComponent(d.id);
            uE.className = 'btn btn-outline-info btn-sm';
            uE.innerHTML = '<i class="bi bi-upload"></i> Subir Acta';
            celdaActaE.appendChild(uE);
        }

        // Devolución: si no existe, se oculta el bloque
        var hayDevolucion = !!d.fecha_dev;
        wrapDev.style.display = hayDevolucion ? '' : 'none';

        var celdaActaD = modalDet.querySelector('[data-d="acta_dev"]');
        celdaActaD.innerHTML = '';
        if (hayDevolucion) {
            txt(modalDet.querySelector('[data-d="fecha_dev"]'), fmtFecha(d.fecha_dev));
            txt(modalDet.querySelector('[data-d="obs_dev"]'), d.obs_dev);
            if (d.acta_dev) {
                var aD = document.createElement('a');
                aD.href = 'descargar_acta.php?id_asignacion=' + encodeURIComponent(d.id) + '&tipo=devolucion';
                aD.target = '_blank';
                aD.className = 'btn btn-info btn-sm';
                aD.innerHTML = '<i class="bi bi-file-earmark-pdf"></i> Ver Acta';
                celdaActaD.appendChild(aD);
            } else {
                celdaActaD.textContent = 'Sin acta';
            }
        }

        // Evidencias fotográficas
        var contEv = modalDet.querySelector('[data-d="evidencias"]');
        contEv.innerHTML = '';
        (d.evidencias || []).forEach(function (img) {
            var url = 'ver_evidencia.php?id_asig=' + encodeURIComponent(d.id) + '&img=' + encodeURIComponent(img);
            var a = document.createElement('a');
            a.href = url;
            a.target = '_blank';
            var im = document.createElement('img');
            im.src = url;
            im.className = 'img-thumbnail';
            im.style.width = '90px';
            im.style.height = '70px';
            im.style.objectFit = 'cover';
            im.alt = 'Evidencia de devolución';
            a.appendChild(im);
            contEv.appendChild(a);
        });
        if ((d.evidencias || []).length === 0) {
            contEv.innerHTML = '<span class="text-muted small">Sin evidencias</span>';
        }

        // Botón de detalle de devolución, solo si la asignación está finalizada
        var btnDev = document.getElementById('mdVerDevolucion');
        btnDev.style.display = hayDevolucion ? '' : 'none';
        if (hayDevolucion) {
            btnDev.href = 'asignacion_detalle_devolucion.php?id=' + encodeURIComponent(d.id);
        }

        modalDetalle.show();
    });

    // Apertura del modal: usa Bootstrap si está disponible y, si no lo está,
// cae a una apertura manual. Así el botón nunca se queda sin hacer nada.
function abrirModal(el) {
    if (window.bootstrap && window.bootstrap.Modal) {
        try {
            new window.bootstrap.Modal(el).show();
            return;
        } catch (e) { /* se intenta la apertura manual */ }
    }
    el.classList.add('show');
    el.style.display = 'block';
    el.removeAttribute('aria-hidden');
    document.body.classList.add('modal-open');
}

function cerrarModal(el) {
    if (window.bootstrap && window.bootstrap.Modal) {
        try {
            window.bootstrap.Modal.getOrCreateInstance(el).hide();
            return;
        } catch (e) { /* se intenta el cierre manual */ }
    }
    el.classList.remove('show');
    el.style.display = 'none';
    document.body.classList.remove('modal-open');
}

var modalEl = document.getElementById('modalCambiarEquipo');
    var alerta = document.getElementById('mmAlerta');
    var btnGuardar = document.getElementById('mmGuardar');
    var inputCodigo = document.getElementById('mmNuevoCodigo');
    var hintCodigo = document.getElementById('mmHintCodigo');
    var idAsignacionActual = null;
    var codigoOriginal = '';

    function poner(id, valor) {
        var el = document.getElementById(id);
        if (!el) { return; }
        var v = (valor === null || valor === undefined || valor === '') ? '---' : valor;
        // Los datos ya registrados se muestran como campos de formulario:
        // se rellena 'value'; si fuera un <div> se usaría textContent.
        if ('value' in el) {
            el.value = v;
        } else {
            el.textContent = v;
        }
    }

    function fechaLegible(f) {
        if (!f) { return '---'; }
        var d = new Date(String(f).replace(' ', 'T'));
        if (isNaN(d)) { return f; }
        return d.toLocaleString('es-ES', {
            day: '2-digit', month: '2-digit', year: 'numeric',
            hour: '2-digit', minute: '2-digit'
        });
    }

    document.addEventListener('click', function (ev) {
        var btn = ev.target.closest('.js-cambiar-equipo');
        if (!btn) { return; }

        idAsignacionActual = btn.dataset.idAsignacion;
        var d = DETALLE[btn.dataset.idAsignacion];
        if (!d) { return; }

        document.getElementById('mmEquipoActual').textContent = d.codigo;
        document.getElementById('mmEmpleado').textContent = d.empleado;

        // Datos ya registrados: solo lectura, no se pueden modificar aquí
        // El código de inventario 2025 viene congelado de la base de datos
        poner('mmCodigo2025', (d.codigo_2025 !== null && d.codigo_2025 !== '') ? d.codigo_2025 : d.codigo);
        poner('mmCodPat', d.cod_pat);
        poner('mmMarcaModelo', ((d.marca || '') + ' ' + (d.modelo || '')).trim());
        poner('mmSerie', d.serie);
        poner('mmEstadoEq', d.estado_eq);
        poner('mmSucursal', d.sucursal);
        poner('mmEstadoAsig', d.estado_asig);
        poner('mmFechaEntrega', fechaLegible(d.fecha_entrega));

        codigoOriginal = d.codigo;
        inputCodigo.value = d.codigo;

        // Referencia: así se ve en la columna "Código Inventario <año>"
        var anioRef = (typeof d.anio !== 'undefined' && d.anio) ? d.anio : new Date().getFullYear();
        hintCodigo.textContent = 'En el listado aparece como: ' + anioRef + '-' + d.codigo;

        alerta.className = 'alert alert-danger d-none mt-3 mb-0 small';
        btnGuardar.disabled = false;

        abrirModal(modalEl);
    });

    modalEl.addEventListener('hidden.bs.modal', function () {
        idAsignacionActual = null;
        inputCodigo.value = '';
        alerta.className = 'alert alert-danger d-none mt-3 mb-0 small';
    });

    btnGuardar.addEventListener('click', function () {
        var codigo = inputCodigo.value.trim();

        if (!codigo) {
            alerta.textContent = 'El código de inventario no puede quedar vacío.';
            alerta.className = 'alert alert-danger mt-3 mb-0 small';
            return;
        }
        if (codigo === codigoOriginal) {
            alerta.textContent = 'El código es el mismo que ya está registrado.';
            alerta.className = 'alert alert-warning mt-3 mb-0 small';
            return;
        }

        var cuerpo = new URLSearchParams();
        cuerpo.append('id_asignacion', idAsignacionActual);
        cuerpo.append('editar_codigo', '1');
        cuerpo.append('nuevo_codigo', codigo);
        cuerpo.append('ajax', '1');

        btnGuardar.disabled = true;

        fetch('asignacion_editar.php?id=' + encodeURIComponent(idAsignacionActual) + '&ajax=1', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
            body: cuerpo.toString()
        })
        .then(function (r) { return r.json(); })
        .then(function (data) {
            if (data && data.ok) {
                cerrarModal(modalEl);
                window.location.reload();
            } else {
                alerta.textContent = (data && data.mensaje) ? data.mensaje : 'No se pudo completar la operación.';
                alerta.className = 'alert alert-danger mt-3 mb-0 small';
                btnGuardar.disabled = false;
            }
        })
        .catch(function () {
            alerta.textContent = 'Error de conexión con el servidor.';
            alerta.className = 'alert alert-danger mt-3 mb-0 small';
            btnGuardar.disabled = false;
        });
    });
});
</script>
