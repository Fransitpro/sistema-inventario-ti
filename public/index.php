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
?>

<h1 class="h2 mb-4">Dashboard</h1>

<div class="row g-4 mb-4">
    <div class="col-md-4">
        <div class="card text-white bg-primary h-100 shadow-sm border-0">
            <div class="card-body d-flex justify-content-between align-items-center p-4">
                <div>
                    <h6 class="card-title mb-1">Total de Equipos</h6>
                    <h2 class="display-6 fw-bold mb-0"><?php echo $total_equipos; ?></h2>
                </div>
                <i class="bi bi-server fs-1 opacity-50"></i>
            </div>
        </div>
    </div>
    
    <div class="col-md-4">
        <div class="card text-dark bg-warning h-100 shadow-sm border-0">
            <div class="card-body d-flex justify-content-between align-items-center p-4">
                <div>
                    <h6 class="card-title mb-1">Equipos Asignados</h6>
                    <h2 class="display-6 fw-bold mb-0"><?php echo $total_asignados; ?></h2>
                </div>
                <i class="bi bi-person-check fs-1 opacity-50"></i>
            </div>
        </div>
    </div>

    <div class="col-md-4">
        <div class="card text-white bg-success h-100 shadow-sm border-0">
            <div class="card-body d-flex justify-content-between align-items-center p-4">
                <div>
                    <h6 class="card-title mb-1">Equipos Disponibles</h6>
                    <h2 class="display-6 fw-bold mb-0"><?php echo $total_disponibles; ?></h2>
                </div>
                <i class="bi bi-box-seam fs-1 opacity-50"></i>
            </div>
        </div>
    </div>
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
                        '#3b82f6', '#ef4444', '#f59e0b', '#10b981', '#8b5cf6', '#6366f1'
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