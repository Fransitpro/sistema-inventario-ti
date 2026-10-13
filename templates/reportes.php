<?php
// Bloque de Reportes: conteos y tarjetas con exportacion A4.
// Se incluye desde index.php (dashboard) y desde public/reportes.php.
// Espera disponibles: $conexion e $id_sucursal_usuario (los define cada pagina).

// Filtro por sucursal: variantes con AND (para dentro de un ON) y con WHERE (para consultas simples)
$f_suc_equipos = ($id_sucursal_usuario !== null) ? " AND e.id_sucursal = " . (int)$id_sucursal_usuario : "";
$w_suc_equipos = ($id_sucursal_usuario !== null) ? "WHERE e.id_sucursal = " . (int)$id_sucursal_usuario : "";
$w_suc_empleados = ($id_sucursal_usuario !== null) ? "WHERE emp.id_sucursal = " . (int)$id_sucursal_usuario : "";

function contarRegistros($conexion, $sql) {
    $res = $conexion->query($sql);
    return $res ? (int)($res->fetch_assoc()['total'] ?? 0) : 0;
}

$total_reportes = [];
$total_reportes['equipos'] = contarRegistros($conexion, "SELECT COUNT(*) AS total FROM equipos e $w_suc_equipos");
$total_reportes['empleados'] = contarRegistros($conexion, "SELECT COUNT(*) AS total FROM empleados emp $w_suc_empleados");
$total_reportes['asignaciones'] = contarRegistros($conexion, "SELECT COUNT(*) AS total FROM asignaciones a JOIN equipos e ON a.id_equipo = e.id $w_suc_equipos");
$total_reportes['bajas'] = contarRegistros(
    $conexion,
    "SELECT COUNT(*) AS total FROM (
        SELECT e.id FROM bajas b JOIN equipos e ON b.id_equipo = e.id $f_suc_equipos
        UNION ALL
        SELECT e.id FROM equipos e LEFT JOIN bajas b2 ON b2.id_equipo = e.id
        WHERE e.estado = 'De Baja' AND b2.id IS NULL $f_suc_equipos
     ) AS t"
);
$total_reportes['historial'] = contarRegistros($conexion, "SELECT COUNT(*) AS total FROM reparaciones r JOIN equipos e ON r.id_equipo = e.id $w_suc_equipos");
$total_reportes['reparacion'] = contarRegistros($conexion, "SELECT COUNT(*) AS total FROM equipos e WHERE e.estado = 'En Reparacion' $f_suc_equipos");
$total_reportes['inventario_empleado'] = contarRegistros($conexion, "SELECT COUNT(*) AS total FROM asignaciones a JOIN equipos e ON a.id_equipo = e.id WHERE a.estado_asignacion = 'Activa' $f_suc_equipos");

// Enlaces de los reportes: las paginas finales ejecutan la exportacion A4 al detectar ?a4=
$reportes = [
    [
        'titulo' => 'Gestión de Equipos',
        'descripcion' => 'Inventario completo: estado, tipo de equipo, marca y modelo.',
        'icono' => 'bi-server', 'color' => 'primary',
        'pagina' => 'equipos.php',
        'total' => $total_reportes['equipos'], 'unidad' => 'equipos'
    ],
    [
        'titulo' => 'Gestión de Empleados',
        'descripcion' => 'Personal registrado con área, cargo, sucursal y estado.',
        'icono' => 'bi-people', 'color' => 'info',
        'pagina' => 'empleados.php',
        'total' => $total_reportes['empleados'], 'unidad' => 'empleados'
    ],
    [
        'titulo' => 'Historial de Asignaciones',
        'descripcion' => 'Entregas y devoluciones de equipos a empleados.',
        'icono' => 'bi-arrow-left-right', 'color' => 'warning',
        'pagina' => 'asignaciones.php',
        'total' => $total_reportes['asignaciones'], 'unidad' => 'asignaciones'
    ],
    [
        'titulo' => 'Equipos Dados de Baja',
        'descripcion' => 'Bajas registradas y equipos marcados como De Baja.',
        'icono' => 'bi-archive', 'color' => 'secondary',
        'pagina' => 'bajas.php',
        'total' => $total_reportes['bajas'], 'unidad' => 'equipos'
    ],
    [
        'titulo' => 'Historial de Reparaciones',
        'descripcion' => 'Historial completo de reparaciones, de todos los estados.',
        'icono' => 'bi-wrench-adjustable', 'color' => 'danger',
        'pagina' => 'reparaciones.php?estado=Todas&reporte=historial',
        'total' => $total_reportes['historial'], 'unidad' => 'registros'
    ],
    [
        'titulo' => 'Equipos en Reparación',
        'descripcion' => 'Equipos que actualmente están en proceso de reparación.',
        'icono' => 'bi-tools', 'color' => 'dark',
        'pagina' => 'reparaciones.php?reporte=reparacion',
        'total' => $total_reportes['reparacion'], 'unidad' => 'equipos'
    ],
    [
        'titulo' => 'Cargo Personal por Asignación de Bienes en Uso',
        'descripcion' => 'Equipos asignados a cada empleado.',
        'icono' => 'bi-person-vcard', 'color' => 'success',
        'pagina' => 'inventario_empleado.php',
        'total' => $total_reportes['inventario_empleado'], 'unidad' => 'asignaciones activas'
    ]
];

// En el dashboard el titulo ya lo pone la pagina; en reportes.php se muestra
$mostrar_titulo_reportes = !isset($mostrar_titulo_reportes) ? true : $mostrar_titulo_reportes;
?>

<?php if ($mostrar_titulo_reportes): ?>
<div class="d-flex flex-wrap align-items-center gap-2 mb-3">
    <h2 class="h5 m-0"><i class="bi bi-file-earmark-bar-graph me-1"></i>Reportes</h2>
    <span class="badge bg-light text-dark border">Se generan en A4 apaisado con los datos de hoy <?php echo date('d/m/Y'); ?></span>
</div>
<?php endif; ?>

<div class="row row-cols-1 row-cols-md-2 row-cols-xl-3 g-3 mb-4">
    <?php foreach ($reportes as $reporte): ?>
        <?php $separador = (strpos($reporte['pagina'], '?') !== false) ? '&amp;' : '?'; ?>
        <div class="col">
            <div class="card h-100 shadow-sm border-0">
                <div class="card-body d-flex flex-column">
                    <div class="d-flex justify-content-between align-items-start mb-2">
                        <h6 class="m-0 fw-bold text-<?php echo $reporte['color']; ?>"><?php echo $reporte['titulo']; ?></h6>
                        <i class="bi <?php echo $reporte['icono']; ?> fs-4 text-<?php echo $reporte['color']; ?> opacity-50"></i>
                    </div>

                    <p class="text-muted small mb-3"><?php echo $reporte['descripcion']; ?></p>

                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <span class="badge rounded-pill bg-light text-dark border">
                            <?php echo (int)$reporte['total']; ?> <?php echo $reporte['unidad']; ?>
                        </span>
                        <a href="<?php echo $reporte['pagina']; ?>" class="small text-decoration-none">
                            Ver página <i class="bi bi-arrow-right"></i>
                        </a>
                    </div>

                    <div class="btn-group btn-group-sm w-100 mt-auto" role="group" aria-label="Exportar <?php echo $reporte['titulo']; ?>">
                        <a class="btn btn-outline-success" href="<?php echo $reporte['pagina'] . $separador . 'a4=excel'; ?>" title="Descargar en Excel">
                            <i class="bi bi-file-earmark-excel"></i> Excel
                        </a>
                        <a class="btn btn-outline-danger" href="<?php echo $reporte['pagina'] . $separador . 'a4=pdf'; ?>" title="Descargar en PDF A4">
                            <i class="bi bi-file-earmark-pdf"></i> PDF
                        </a>
                        <a class="btn btn-outline-secondary" href="<?php echo $reporte['pagina'] . $separador . 'a4=imprimir'; ?>" title="Imprimir en A4">
                            <i class="bi bi-printer"></i> Imprimir
                        </a>
                    </div>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
</div>
