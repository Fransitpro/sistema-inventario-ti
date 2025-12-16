<?php
// Incluimos la cabecera
require_once '../templates/header.php';

// 1. OBTENER Y VALIDAR EL ID DE LA ASIGNACIÓN
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    echo '<div class="alert alert-danger" role="alert">Error: ID de asignación no válido.</div>';
    require_once '../templates/footer.php';
    exit();
}
$id_asignacion = (int)$_GET['id'];

// 2. CONSULTAR LA BASE DE DATOS PARA OBTENER LOS DETALLES
$sql = "SELECT 
            a.fecha_devolucion, a.observaciones_devolucion,
            a.imagen_devolucion_1, a.imagen_devolucion_2, a.imagen_devolucion_3,
            emp.nombres AS emp_nombres, emp.apellidos AS emp_apellidos,
            eq.codigo_inventario,
            ma.nombre AS marca_nombre,
            mo.nombre AS modelo_nombre
        FROM asignaciones a
        JOIN empleados emp ON a.id_empleado = emp.id
        JOIN equipos eq ON a.id_equipo = eq.id
        LEFT JOIN modelos mo ON eq.id_modelo = mo.id
        LEFT JOIN marcas ma ON mo.id_marca = ma.id
        WHERE a.id = ?";

$stmt = $conexion->prepare($sql);
$stmt->bind_param("i", $id_asignacion);
$stmt->execute();
$resultado = $stmt->get_result();

if ($resultado->num_rows === 0) {
    echo '<div class="alert alert-warning" role="alert">No se encontró la asignación solicitada.</div>';
    require_once '../templates/footer.php';
    exit();
}
$data = $resultado->fetch_assoc();
?>

<style>
    .evidence-thumbnail {
        width: 100%;
        height: 100px; /* Altura fija para todas las miniaturas */
        object-fit: cover; /* Recorta la imagen para que llene el espacio sin deformarse */
    }
</style>

<div class="d-flex justify-content-between align-items-center mb-3">
    <h1 class="h2">Detalle de Devolución</h1>
    <div class="d-flex gap-2">
        <a href="generar_acta_devolucion.php?id_asignacion=<?php echo $id_asignacion; ?>" target="_blank" class="btn btn-danger">
            <i class="bi bi-file-earmark-pdf me-2"></i> Imprimir Acta
        </a>
        <a href="asignaciones.php" class="btn btn-secondary">
            <i class="bi bi-arrow-left me-2"></i> Volver al Historial
        </a>
    </div>
</div>

<div class="row">
    <div class="col-lg-8">
        <div class="card">
            <div class="card-header">
                Información de la Devolución
            </div>
            <div class="card-body">
                <dl class="row">
                    <dt class="col-sm-3">Empleado:</dt>
                    <dd class="col-sm-9"><?php echo htmlspecialchars($data['emp_apellidos'] . ', ' . $data['emp_nombres']); ?></dd>

                    <dt class="col-sm-3">Equipo:</dt>
                    <dd class="col-sm-9"><?php echo htmlspecialchars($data['codigo_inventario'] . ' - ' . $data['marca_nombre'] . ' ' . $data['modelo_nombre']); ?></dd>

                    <dt class="col-sm-3">Fecha de Devolución:</dt>
                    <dd class="col-sm-9"><?php echo date("d/m/Y H:i", strtotime($data['fecha_devolucion'])); ?></dd>

                    <hr class="my-3">

                    <dt class="col-sm-3">Observaciones registradas:</dt>
                    <dd class="col-sm-9">
                        <p class="fst-italic">"<?php echo !empty($data['observaciones_devolucion']) ? nl2br(htmlspecialchars($data['observaciones_devolucion'])) : 'No hay observaciones.'; ?>"</p>
                    </dd>
                </dl>
            </div>
        </div>
    </div>

    <div class="col-lg-4">
        <div class="card">
            <div class="card-header">
                Evidencia Fotográfica
            </div>
            <div class="card-body">
                <?php
                // Se crea un array con las imágenes que no están vacías
                $imagenes = array_filter([
                    $data['imagen_devolucion_1'],
                    $data['imagen_devolucion_2'],
                    $data['imagen_devolucion_3']
                ]);

                if (!empty($imagenes)) : ?>
                    <div class="row g-2">
                        <?php foreach ($imagenes as $imagen) : ?>
                            <div class="col-4"> 
                                <?php
                                // =============================================================
                                // RUTA CORREGIDA: Apunta al nuevo script 'ver_evidencia.php'
                                // =============================================================
                                $ruta_script = "ver_evidencia.php?id_asig=" . $id_asignacion . "&img=" . urlencode(htmlspecialchars($imagen));
                                ?>
                                <a href="<?php echo $ruta_script; ?>" target="_blank">
                                    <img src="<?php echo $ruta_script; ?>" class="img-thumbnail evidence-thumbnail" alt="Evidencia de devolución">
                                </a>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else : ?>
                    <p class="text-muted">No se encontraron evidencias fotográficas para esta devolución.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php
// Incluimos el pie de página
require_once '../templates/footer.php';
?>