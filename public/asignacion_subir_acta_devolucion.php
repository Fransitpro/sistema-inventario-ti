<?php
// 1. LÓGICA DE NEGOCIO PRIMERO
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once '../config/database.php';

// Validar sesión
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Validar ID
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header("Location: asignaciones.php");
    exit();
}

$id_asignacion = (int)$_GET['id'];
$mensaje = '';
$tipo_mensaje = '';

// 2. PROCESAR SUBIDA DE ARCHIVO (POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_FILES['acta']) && $_FILES['acta']['error'] === UPLOAD_ERR_OK) {
        
        $archivo = $_FILES['acta'];
        $ext = strtolower(pathinfo($archivo['name'], PATHINFO_EXTENSION));
        $permitidos = ['pdf', 'jpg', 'jpeg', 'png'];

        if (in_array($ext, $permitidos)) {
            // Nombre único para devolución
            $nombre_nuevo = "acta_devolucion_{$id_asignacion}_" . time() . ".{$ext}";
            
            // --- CORRECCIÓN DE RUTA: CARPETA ESPECÍFICA 'actas_devolucion' ---
            // Usamos __DIR__ para asegurar la ruta absoluta desde la carpeta 'public'
            $directorio_base = __DIR__ . '/../uploads/actas_devolucion/';
            
            // Crear carpeta si no existe (con permisos)
            if (!is_dir($directorio_base)) {
                if (!mkdir($directorio_base, 0777, true)) {
                    $mensaje = "Error crítico: No se pudo crear la carpeta de destino.";
                    $tipo_mensaje = "danger";
                }
            }

            if (empty($mensaje)) {
                $ruta_destino = $directorio_base . $nombre_nuevo;

                if (move_uploaded_file($archivo['tmp_name'], $ruta_destino)) {
                    // Actualizar BD
                    $stmt = $conexion->prepare("UPDATE asignaciones SET acta_devolucion_path = ? WHERE id = ?");
                    $stmt->bind_param("si", $nombre_nuevo, $id_asignacion);
                    
                    if ($stmt->execute()) {
                        header("Location: asignaciones.php?msg=acta_dev_subida");
                        exit();
                    } else {
                        $mensaje = "Error al actualizar la base de datos.";
                        $tipo_mensaje = "danger";
                    }
                } else {
                    $mensaje = "Error al mover el archivo. Verifique permisos de carpeta.";
                    $tipo_mensaje = "danger";
                }
            }
        } else {
            $mensaje = "Formato no válido. Solo PDF, JPG o PNG.";
            $tipo_mensaje = "warning";
        }
    } else {
        $mensaje = "Seleccione un archivo válido.";
        $tipo_mensaje = "danger";
    }
}

// 3. OBTENER DATOS
$sql = "SELECT a.id, e.codigo_inventario, m.nombre as marca, mdl.nombre as modelo, 
               emp.nombres, emp.apellidos
        FROM asignaciones a
        JOIN equipos e ON a.id_equipo = e.id
        JOIN marcas m ON e.id_marca = m.id
        JOIN modelos mdl ON e.id_modelo = mdl.id 
        JOIN empleados emp ON a.id_empleado = emp.id
        WHERE a.id = ?";

$stmt = $conexion->prepare($sql);
$stmt->bind_param("i", $id_asignacion);
$stmt->execute();
$asignacion = $stmt->get_result()->fetch_assoc();

if (!$asignacion) {
    header("Location: asignaciones.php");
    exit();
}

require_once '../templates/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h2">Subir Acta de Devolución</h1>
    <a href="asignaciones.php" class="btn btn-secondary">
        <i class="bi bi-arrow-left me-2"></i> Volver
    </a>
</div>

<?php if (!empty($mensaje)): ?>
    <div class="alert alert-<?php echo $tipo_mensaje; ?> alert-dismissible fade show shadow-sm" role="alert">
        <i class="bi bi-info-circle-fill me-2"></i> <?php echo $mensaje; ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<div class="row justify-content-center">
    <div class="col-md-8">
        <div class="card shadow-sm border-0">
            <div class="card-header bg-white py-3">
                <h5 class="mb-0 text-danger fw-bold"><i class="bi bi-file-earmark-arrow-down-fill me-2"></i> Adjuntar Documento de Devolución</h5>
            </div>
            <div class="card-body p-4">
                
                <div class="alert alert-light border d-flex align-items-center mb-4">
                    <i class="bi bi-person-x fs-2 text-danger me-3"></i>
                    <div>
                        <h6 class="fw-bold mb-1">Empleado: <?php echo htmlspecialchars($asignacion['nombres'] . ' ' . $asignacion['apellidos']); ?></h6>
                        <small class="text-muted">Equipo devuelto: <?php echo htmlspecialchars($asignacion['marca'] . ' ' . $asignacion['modelo'] . ' (' . $asignacion['codigo_inventario'] . ')'); ?></small>
                    </div>
                </div>

                <form action="asignacion_subir_acta_devolucion.php?id=<?php echo $id_asignacion; ?>" method="POST" enctype="multipart/form-data">
                    <div class="mb-4">
                        <label for="acta" class="form-label fw-bold">Archivo del Acta Firmada</label>
                        <input class="form-control form-control-lg" type="file" id="acta" name="acta" accept=".pdf,.jpg,.jpeg,.png" required>
                        <div class="form-text">Se guardará en: <strong>/uploads/actas_devolucion</strong></div>
                    </div>
                    <div class="d-grid">
                        <button type="submit" class="btn btn-danger btn-lg text-white">
                            <i class="bi bi-upload me-2"></i> Subir Acta de Devolución
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php require_once '../templates/footer.php'; ?>