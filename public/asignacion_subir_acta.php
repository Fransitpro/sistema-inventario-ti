<?php
// 1. LÓGICA DE NEGOCIO PRIMERO
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once '../config/database.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header("Location: asignaciones.php");
    exit();
}

$id_asignacion = (int)$_GET['id'];
$mensaje = '';
$tipo_mensaje = '';

// 2. PROCESAR SUBIDA
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_FILES['acta']) && $_FILES['acta']['error'] === UPLOAD_ERR_OK) {
        
        $archivo = $_FILES['acta'];
        $ext = strtolower(pathinfo($archivo['name'], PATHINFO_EXTENSION));
        $permitidos = ['pdf', 'jpg', 'jpeg', 'png'];

        if (in_array($ext, $permitidos)) {
            // Nombre único
            $nombre_nuevo = "acta_{$id_asignacion}_" . time() . ".{$ext}";
            
            // RUTA ABSOLUTA (Para evitar problemas de carpetas relativas)
            // __DIR__ es la carpeta actual (public), subimos un nivel y entramos a uploads/actas
            $directorio_base = __DIR__ . '/../uploads/actas/';
            
            // Crear carpeta si no existe
            if (!is_dir($directorio_base)) {
                mkdir($directorio_base, 0777, true);
            }

            $ruta_destino = $directorio_base . $nombre_nuevo;

            if (move_uploaded_file($archivo['tmp_name'], $ruta_destino)) {
                // Actualizar BD (Guardamos solo el nombre del archivo, no toda la ruta)
                $stmt = $conexion->prepare("UPDATE asignaciones SET acta_firmada_path = ? WHERE id = ?");
                $stmt->bind_param("si", $nombre_nuevo, $id_asignacion);
                
                if ($stmt->execute()) {
                    header("Location: asignaciones.php?msg=acta_subida");
                    exit();
                } else {
                    $mensaje = "Error al actualizar la base de datos.";
                    $tipo_mensaje = "danger";
                }
            } else {
                $mensaje = "Error: No se pudo mover el archivo al servidor. Verifique permisos.";
                $tipo_mensaje = "danger";
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

// 3. OBTENER DATOS (Con alias corregido 'mdl')
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
    <h1 class="h2">Subir Acta de Entrega</h1>
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
                <h5 class="mb-0 text-primary fw-bold"><i class="bi bi-cloud-upload-fill me-2"></i> Adjuntar Documento</h5>
            </div>
            <div class="card-body p-4">
                
                <div class="alert alert-light border d-flex align-items-center mb-4">
                    <i class="bi bi-person-check fs-2 text-success me-3"></i>
                    <div>
                        <h6 class="fw-bold mb-1">Empleado: <?php echo htmlspecialchars($asignacion['nombres'] . ' ' . $asignacion['apellidos']); ?></h6>
                        <small class="text-muted">Equipo: <?php echo htmlspecialchars($asignacion['marca'] . ' ' . $asignacion['modelo'] . ' (' . $asignacion['codigo_inventario'] . ')'); ?></small>
                    </div>
                </div>

                <form action="asignacion_subir_acta.php?id=<?php echo $id_asignacion; ?>" method="POST" enctype="multipart/form-data">
                    <div class="mb-4">
                        <label for="acta" class="form-label fw-bold">Archivo del Acta Firmada</label>
                        <input class="form-control form-control-lg" type="file" id="acta" name="acta" accept=".pdf,.jpg,.jpeg,.png" required>
                        <div class="form-text">Formatos permitidos: PDF, JPG, PNG. Se guardará en la carpeta /uploads/actas.</div>
                    </div>
                    <div class="d-grid">
                        <button type="submit" class="btn btn-primary btn-lg">Subir Acta</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php require_once '../templates/footer.php'; ?>