<?php
require_once '../templates/header.php';

$id_asignacion = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$id_asignacion) {
    header("Location: asignaciones.php");
    exit();
}

// Lógica para procesar la subida del archivo
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['acta_firmada'])) {
    $file = $_FILES['acta_firmada'];

    // Validaciones del archivo
    if ($file['error'] === UPLOAD_ERR_OK) {
        $file_tmp_path = $file['tmp_name'];
        $file_name = basename($file['name']);
        $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));

        if ($file_ext === 'pdf') {
            // Crear un nombre de archivo único para evitar sobreescrituras
            $new_file_name = 'acta_' . $id_asignacion . '_' . time() . '.pdf';
            $dest_path = '../uploads/actas/' . $new_file_name;

            if (move_uploaded_file($file_tmp_path, $dest_path)) {
                // Actualizar la base de datos con la ruta del archivo
                $stmt = $conexion->prepare("UPDATE asignaciones SET acta_firmada_path = ? WHERE id = ?");
                $stmt->bind_param("si", $new_file_name, $id_asignacion);
                if ($stmt->execute()) {
                    header("Location: asignaciones.php?status=upload_success");
                    exit();
                } else {
                    $error_message = "Error al actualizar la base de datos.";
                }
            } else {
                $error_message = "Error al mover el archivo subido.";
            }
        } else {
            $error_message = "Formato de archivo no válido. Solo se permiten archivos PDF.";
        }
    } else {
        $error_message = "Error al subir el archivo. Código: " . $file['error'];
    }
}
?>

<h1 class="h2 mb-4">Subir Acta de Entrega Firmada</h1>

<?php if (isset($error_message)): ?>
    <div class="alert alert-danger"><?php echo $error_message; ?></div>
<?php endif; ?>

<div class="card">
    <div class="card-body">
        <form action="asignacion_subir_acta.php?id=<?php echo $id_asignacion; ?>" method="POST" enctype="multipart/form-data">
            <div class="mb-3">
                <label for="acta_firmada" class="form-label">Seleccionar Archivo PDF <span class="text-danger">*</span></label>
                <input class="form-control" type="file" id="acta_firmada" name="acta_firmada" accept=".pdf" required>
                <div class="form-text">El archivo debe estar en formato PDF y no debe exceder los 5MB.</div>
            </div>
            
            <hr class="my-4">
            <a href="asignaciones.php" class="btn btn-secondary">Cancelar</a>
            <button type="submit" class="btn btn-primary"><i class="bi bi-upload me-2"></i>Subir Archivo</button>
        </form>
    </div>
</div>

<?php require_once '../templates/footer.php'; ?>