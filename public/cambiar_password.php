<?php
require_once '../templates/header.php';

$id_usuario_logueado = $_SESSION['user_id'];

// Lógica para procesar el cambio de contraseña
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $password_actual = $_POST['password_actual'];
    $password_nueva = $_POST['password_nueva'];
    $password_confirmar = $_POST['password_confirmar'];

    // 1. Obtener el hash de la contraseña actual del usuario desde la BD
    $stmt = $conexion->prepare("SELECT password FROM usuarios WHERE id = ?");
    $stmt->bind_param("i", $id_usuario_logueado);
    $stmt->execute();
    $resultado = $stmt->get_result();
    $usuario = $resultado->fetch_assoc();
    $hash_actual = $usuario['password'];
    $stmt->close();

    // 2. Verificar si la contraseña actual es correcta
    if (password_verify($password_actual, $hash_actual)) {
        // 3. Verificar si las nuevas contraseñas coinciden y no están vacías
        if (!empty($password_nueva) && $password_nueva === $password_confirmar) {
            // 4. Hashear la nueva contraseña
            $nuevo_hash = password_hash($password_nueva, PASSWORD_DEFAULT);

            // 5. Actualizar la contraseña en la base de datos
            $stmt_update = $conexion->prepare("UPDATE usuarios SET password = ? WHERE id = ?");
            $stmt_update->bind_param("si", $nuevo_hash, $id_usuario_logueado);
            
            if ($stmt_update->execute()) {
                $success_message = "¡Contraseña actualizada con éxito!";
            } else {
                $error_message = "Error al actualizar la contraseña en la base de datos.";
            }
            $stmt_update->close();
        } else {
            $error_message = "La nueva contraseña no coincide o está vacía. Por favor, inténtelo de nuevo.";
        }
    } else {
        $error_message = "La contraseña actual es incorrecta.";
    }
}
?>

<h1 class="h2 mb-4">Restablecer Contraseña</h1>

<?php if (isset($error_message)): ?>
    <div class="alert alert-danger"><?php echo $error_message; ?></div>
<?php endif; ?>

<?php if (isset($success_message)): ?>
    <div class="alert alert-success"><?php echo $success_message; ?></div>
<?php endif; ?>

<form method="POST">
    <div class="row">
        <div class="col-md-8 col-lg-6">
            <div class="mb-3">
                <label for="password_actual" class="form-label">Contraseña Actual <span class="text-danger">*</span></label>
                <input type="password" class="form-control" name="password_actual" id="password_actual" required>
            </div>
            <div class="mb-3">
                <label for="password_nueva" class="form-label">Nueva Contraseña <span class="text-danger">*</span></label>
                <input type="password" class="form-control" name="password_nueva" id="password_nueva" required>
            </div>
            <div class="mb-3">
                <label for="password_confirmar" class="form-label">Confirmar Nueva Contraseña <span class="text-danger">*</span></label>
                <input type="password" class="form-control" name="password_confirmar" id="password_confirmar" required>
            </div>
            
            <hr class="my-4">
            <button type="submit" class="btn btn-primary">Cambiar Contraseña</button>
        </div>
    </div>
</form>

<?php require_once '../templates/footer.php'; ?>