<?php
require_once '../templates/header.php';

// 1. OBTENER ID SUCURSAL DE FORMA SEGURA
$id_sucursal_usuario = $_SESSION['user_sucursal_id'] ?? null;

// 2. CONSULTAS PARA LAS TARJETAS (KPIs)
$where_sucursal = "";
if ($id_sucursal_usuario !== null) {
    $where_sucursal = "WHERE id_sucursal = $id_sucursal_usuario";
}

// A. Total de Equipos
$sql_total = "SELECT COUNT(*) as total FROM equipos $where_sucursal";
$res_total = $conexion->query($sql_total);
$total_equipos = $res_total->fetch_assoc()['total'] ?? 0;

// B. Equipos Asignados
$where_asignados = ($id_sucursal_usuario !== null) ? "AND id_sucursal = $id_sucursal_usuario" : "";
$sql_asignados = "SELECT COUNT(*) as total FROM equipos WHERE estado = 'Asignado' $where_asignados";
$res_asignados = $conexion->query($sql_asignados);
$total_asignados = $res_asignados->fetch_assoc()['total'] ?? 0;

// C. Equipos Disponibles
$where_disponibles = ($id_sucursal_usuario !== null) ? "AND id_sucursal = $id_sucursal_usuario" : "";
$sql_disponibles = "SELECT COUNT(*) as total FROM equipos WHERE estado = 'Disponible' $where_disponibles";
$res_disponibles = $conexion->query($sql_disponibles);
$total_disponibles = $res_disponibles->fetch_assoc()['total'] ?? 0;

// 3. CONSULTA PARA EL GRÁFICO 1 (Equipos por Tipo)
$sql_chart1 = "SELECT t.nombre, COUNT(e.id) as cantidad 
               FROM equipos e 
               JOIN tipos_equipo t ON e.id_tipo_equipo = t.id 
               $where_sucursal
               GROUP BY t.nombre";
$res_chart1 = $conexion->query($sql_chart1);

$tipos_labels = [];
$tipos_data = [];
while ($row = $res_chart1->fetch_assoc()) {
    $tipos_labels[] = $row['nombre'];
    $tipos_data[] = $row['cantidad'];
}

// 4. CONSULTA PARA EL GRÁFICO 2 (Equipos y Empleados por Sucursal)
// Si el usuario es admin, ve todas. Si es de sucursal, solo ve la suya.
$filtro_sucursal_sql = ($id_sucursal_usuario !== null) ? "AND s.id = $id_sucursal_usuario" : "";

$sql_chart2 = "SELECT 
                s.nombre as sucursal,
                (SELECT COUNT(*) FROM equipos e WHERE e.id_sucursal = s.id) as total_equipos,
                (SELECT COUNT(*) FROM empleados emp WHERE emp.id_sucursal = s.id AND emp.estado = 'Activo') as total_empleados
               FROM sucursales s
               WHERE s.estado = 'Activo' $filtro_sucursal_sql";

$res_chart2 = $conexion->query($sql_chart2);

$sucursal_labels = [];
$data_equipos = [];
$data_empleados = [];

while ($row = $res_chart2->fetch_assoc()) {
    $sucursal_labels[] = $row['sucursal'];
    $data_equipos[] = $row['total_equipos'];
    $data_empleados[] = $row['total_empleados'];
}

// 5. Reportes: los conteos y las tarjetas se cargan desde templates/reportes.php
// Filtro por sucursal para el JOIN de equipos
$f_suc_equipos = ($id_sucursal_usuario !== null) ? " AND e.id_sucursal = " . (int)$id_sucursal_usuario : "";

// 6. TOTALES POR TIPO DE EQUIPO (TELEFONOS, IMPRESORAS, MULTIFUNCIONES)
function normalizarTipo($nombre) {
    $texto = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', (string)$nombre);
    return preg_replace('/[^A-Z0-9]/', '', strtoupper($texto));
}

$res_tipos = $conexion->query(
    "SELECT t.id, t.nombre, COUNT(e.id) AS total
     FROM tipos_equipo t
     LEFT JOIN equipos e ON e.id_tipo_equipo = t.id $f_suc_equipos
     GROUP BY t.id, t.nombre
     ORDER BY t.nombre"
);

$tipos_disponibles = [];
while ($fila_tipo = $res_tipos->fetch_assoc()) {
    $tipos_disponibles[] = $fila_tipo;
}

// Una tipo entra en la tarjeta si su nombre coincide con alguno de los
// patrones. Se acepta el nombre exacto, el que empieza por el patron y
// tambien el que lo lleva en medio: "Impresora Multifuncional HP" tiene que
// caer en Multifunciones, no solo "Multifuncional".
function coincideTipo($nombre, $patron) {
    if ($nombre === $patron) { return true; }
    return strpos($nombre, $patron) !== false;
}

// Agrupa varios tipos del catálogo bajo un mismo indicador.
// Por ejemplo, todas las "SILLA ..." o "MESA ..." cuentan como un solo grupo.
function agruparTipos($tipos, $patrones) {
    $coincidentes = [];
    foreach ($tipos as $tipo) {
        $nombre = normalizarTipo($tipo['nombre']);
        foreach ($patrones as $patron) {
            if (coincideTipo($nombre, $patron)) {
                $coincidentes[] = $tipo;
                break;
            }
        }
    }
    return $coincidentes;
}

$totales_tipos = [
    [
        'etiqueta' => 'Teléfonos',
        'patrones' => ['TELEFONO', 'TELEFONOS'],
        'icono' => 'bi-telephone', 'color' => 'success', 'texto' => 'text-white'
    ],
    [
        'etiqueta' => 'Impresoras',
        'patrones' => ['IMPRESORA', 'IMPRESORAS', 'IMPRESOR'],
        'icono' => 'bi-printer', 'color' => 'info', 'texto' => 'text-white'
    ],
    [
        'etiqueta' => 'Multifunciones',
        // Un multifuncion suele venir con el tipo de impresora delante
        // ("Impresora Multifuncional HP"), asi que se busca la palabra en
        // cualquier parte del nombre, no solo al principio.
        'patrones' => ['MULTIFUNCIONAL', 'MULTIFUNCION', 'IMPRESORAMULTIFUNCIONAL', 'ALLINONE', 'MULTIFUNCIONHP'],
        'icono' => 'bi-files', 'color' => 'warning', 'texto' => 'text-dark',
        // Se resuelve antes que Impresoras: si no, "Impresora Multifuncional"
        // se contaria tambien como impresora.
        'prioridad' => 1
    ],
    [
        'etiqueta' => 'Mesas',
        'patrones' => ['MESA', 'MESAS'],
        'icono' => 'bi-table', 'color' => 'secondary', 'texto' => 'text-white'
    ],
    [
        'etiqueta' => 'Sillas',
        'patrones' => ['SILLA', 'SILLAS'],
        'icono' => 'bi-chair', 'color' => 'danger', 'texto' => 'text-white'
    ],
    [
        'etiqueta' => 'Escritorios',
        'patrones' => ['ESCRITORIO', 'ESCRITORIOS'],
        'icono' => 'bi-display', 'color' => 'dark', 'texto' => 'text-white'
    ]
];

foreach ($totales_tipos as $pos => $indicador) {
    // Las tarjetas se dibujan en el orden de la lista, pero los tipos se
    // reparten segun la prioridad: cada tipo entra solo en la primera tarjeta
    // que lo reclame. Asi "Impresora Multifuncional" suma en Multifunciones y
    // no vuelve a sumar en Impresoras.
    $totales_tipos[$pos]['total'] = 0;
    $totales_tipos[$pos]['existe'] = false;
    $totales_tipos[$pos]['ids'] = [];
    $totales_tipos[$pos]['nombres'] = [];
}

$reparto = $totales_tipos;
usort($reparto, function ($a, $b) {
    $pa = $a['prioridad'] ?? 99;
    $pb = $b['prioridad'] ?? 99;
    return $pa === $pb ? 0 : ($pa < $pb ? -1 : 1);
});

$usados = [];
foreach ($reparto as $reparto_indicador) {
    $pos = array_search($reparto_indicador, $totales_tipos, true);
    $grupo = agruparTipos($tipos_disponibles, $reparto_indicador['patrones']);

    foreach ($grupo as $tipo) {
        // Un tipo ya reclamado por una tarjeta mas especifica no se cuenta dos veces.
        if (isset($usados[$tipo['id']])) { continue; }
        $usados[$tipo['id']] = true;

        $totales_tipos[$pos]['total'] += (int)$tipo['total'];
        $totales_tipos[$pos]['nombres'][] = $tipo['nombre'];
        $totales_tipos[$pos]['ids'][] = (int)$tipo['id'];
    }

    $totales_tipos[$pos]['existe'] = count($totales_tipos[$pos]['ids']) > 0;
}
?>

<h1 class="h2 mb-4">Dashboard</h1>

<div class="row g-4 mb-4">
    <div class="col-md-4">
        <a href="equipos.php" class="text-decoration-none">
            <div class="card text-white bg-primary h-100 shadow-sm border-0">
                <div class="card-body d-flex justify-content-between align-items-center p-4">
                    <div>
                        <h6 class="card-title mb-1">Total de Equipos</h6>
                        <h2 class="display-6 fw-bold mb-0"><?php echo $total_equipos; ?></h2>
                    </div>
                    <i class="bi bi-server fs-1 opacity-50"></i>
                </div>
            </div>
        </a>
    </div>

    <div class="col-md-4">
        <a href="equipos.php?estado=Asignado" class="text-decoration-none">
            <div class="card text-dark bg-warning h-100 shadow-sm border-0">
                <div class="card-body d-flex justify-content-between align-items-center p-4">
                    <div>
                        <h6 class="card-title mb-1">Equipos Asignados</h6>
                        <h2 class="display-6 fw-bold mb-0"><?php echo $total_asignados; ?></h2>
                    </div>
                    <i class="bi bi-person-check fs-1 opacity-50"></i>
                </div>
            </div>
        </a>
    </div>

    <div class="col-md-4">
        <a href="equipos.php?estado=Disponible" class="text-decoration-none">
            <div class="card text-white bg-success h-100 shadow-sm border-0">
                <div class="card-body d-flex justify-content-between align-items-center p-4">
                    <div>
                        <h6 class="card-title mb-1">Equipos Disponibles</h6>
                        <h2 class="display-6 fw-bold mb-0"><?php echo $total_disponibles; ?></h2>
                    </div>
                    <i class="bi bi-box-seam fs-1 opacity-50"></i>
                </div>
            </div>
        </a>
    </div>
</div>

<div class="d-flex flex-wrap align-items-center gap-2 mb-3">
    <h2 class="h5 m-0"><i class="bi bi-phone-vibrate me-1"></i>Totales por tipo de equipo</h2>
</div>

<div class="row g-4 mb-4">
    <?php foreach ($totales_tipos as $indicador): ?>
        <?php $tipos_sel = implode(',', $indicador['ids']); ?>
        <div class="col-md-4">
            <?php if ($indicador['existe']): ?>
                <a href="equipos.php?tipo_equipo=<?php echo $tipos_sel; ?>" class="text-decoration-none">
            <?php endif; ?>
                <div class="card <?php echo $indicador['texto']; ?> bg-<?php echo $indicador['color']; ?> h-100 shadow-sm border-0">
                    <div class="card-body d-flex justify-content-between align-items-center p-4">
                        <div>
                            <h6 class="card-title mb-1">Total de <?php echo $indicador['etiqueta']; ?></h6>
                            <h2 class="display-6 fw-bold mb-0"><?php echo (int)$indicador['total']; ?></h2>
                        </div>
                        <i class="bi <?php echo $indicador['icono']; ?> fs-1 opacity-50"></i>
                    </div>
                </div>
            <?php if ($indicador['existe']): ?>
                </a>
            <?php endif; ?>
            <p class="small text-muted mt-2 mb-0">
                <?php if ($indicador['existe']): ?>
                    <i class="bi bi-arrow-right"></i> Ver equipos de tipo “<?php echo htmlspecialchars(implode('”, “', $indicador['nombres'])); ?>”
                <?php else: ?>
                    <i class="bi bi-exclamation-triangle text-warning"></i>
                    Aún no existe el tipo “<?php echo $indicador['etiqueta']; ?>” en
                    <a href="gestion_catalogos.php" class="text-decoration-none">Catálogos</a>
                <?php endif; ?>
            </p>
        </div>
    <?php endforeach; ?>
</div>

<div class="row">
    <div class="col-md-6 mb-4">
        <div class="card shadow-sm h-100">
            <div class="card-header bg-white py-3 border-0">
                <h6 class="m-0 fw-bold text-primary">Equipos Disponibles por Tipo</h6>
            </div>
            <div class="card-body d-flex justify-content-center align-items-center">
                <?php if (count($tipos_data) > 0): ?>
                    <div style="width: 80%; height: 300px;">
                        <canvas id="chartTipos"></canvas>
                    </div>
                <?php else: ?>
                    <div class="text-center py-5 text-muted">
                        <i class="bi bi-pie-chart fs-1 opacity-25"></i>
                        <p class="mt-2">No hay datos suficientes</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <div class="col-md-6 mb-4">
        <div class="card shadow-sm h-100">
            <div class="card-header bg-white py-3 border-0">
                <h6 class="m-0 fw-bold text-primary">Equipos y Empleados por Sucursal</h6>
            </div>
            <div class="card-body d-flex justify-content-center align-items-center">
                <?php if (count($sucursal_labels) > 0): ?>
                    <div style="width: 100%; height: 300px;">
                        <canvas id="chartSucursales"></canvas>
                    </div>
                <?php else: ?>
                    <div class="text-center py-5 text-muted">
                        <i class="bi bi-bar-chart-line fs-1 opacity-25"></i>
                        <p class="mt-2">No hay datos de sucursales</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php $mostrar_titulo_reportes = false; include __DIR__ . '/../templates/reportes.php'; ?>

<?php require_once '../templates/footer.php'; ?>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<script>
document.addEventListener("DOMContentLoaded", function() {
    
    // --- GRÁFICO 1: DONA (TIPOS) ---
    <?php if (count($tipos_data) > 0): ?>
    const ctx1 = document.getElementById('chartTipos');
    if (ctx1) {
        new Chart(ctx1, {
            type: 'doughnut',
            data: {
                labels: <?php echo json_encode($tipos_labels); ?>,
                datasets: [{
                    label: 'Cantidad',
                    data: <?php echo json_encode($tipos_data); ?>,
                    backgroundColor: [
                        '#3b82f6', '#ef4444', '#f59e0b', '#10b981', '#8b5cf6', '#6366f1',
                        '#ec4899', '#14b8a6', '#f97316', '#64748b', '#a855f7', '#0ea5e9'
                    ],
                    borderWidth: 2,
                    hoverOffset: 4
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { position: 'bottom', labels: { usePointStyle: true, padding: 20 } }
                }
            }
        });
    }
    <?php endif; ?>

    // --- GRÁFICO 2: BARRAS (SUCURSALES) ---
    <?php if (count($sucursal_labels) > 0): ?>
    const ctx2 = document.getElementById('chartSucursales');
    if (ctx2) {
        new Chart(ctx2, {
            type: 'bar',
            data: {
                labels: <?php echo json_encode($sucursal_labels); ?>,
                datasets: [
                    {
                        label: 'Equipos',
                        data: <?php echo json_encode($data_equipos); ?>,
                        backgroundColor: '#3b82f6', // Azul
                        borderRadius: 4
                    },
                    {
                        label: 'Empleados',
                        data: <?php echo json_encode($data_empleados); ?>,
                        backgroundColor: '#f59e0b', // Amarillo/Naranja
                        borderRadius: 4
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { position: 'top', align: 'end', labels: { usePointStyle: true } }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        grid: { borderDash: [2, 4] }
                    },
                    x: {
                        grid: { display: false }
                    }
                }
            }
        });
    }
    <?php endif; ?>

});
</script>