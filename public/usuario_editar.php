<?php
// 1. LÓGICA DE NEGOCIO PRIMERO (ANTES DE HTML)
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once '../config/database.php';

// Validar acceso (Solo administradores)
if (!isset($_SESSION['user_rol']) || (strtolower($_SESSION['user_rol']) !== 'administrador' && strtolower($_SESSION['user_rol']) !== 'admin')) {
    header("Location: index.php");
    exit();
}

// Validar ID
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header("Location: gestion_usuarios.php");
    exit();
}

$id_usuario = (int)$_GET['id'];
$mensaje = '';
$tipo_mensaje = '';

// 2. PROCESAR ACTUALIZACIÓN (POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Recibir datos
    $nombre = trim($_POST['nombre']);
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    $id_sucursal = !empty($_POST['id_sucursal']) ? $_POST['id_sucursal'] : null;
    $id_rol = $_POST['id_rol'];
    $activo = $_POST['activo'];

    if (empty($nombre) || empty($email) || empty($id_rol)) {
        $mensaje = "Por favor complete los campos obligatorios.";
        $tipo_mensaje = "danger";
    } else {
        // Verificar correo duplicado (excluyendo al usuario actual)
        $check = $conexion->prepare("SELECT id FROM usuarios WHERE email = ? AND id != ?");
        $check->bind_param("si", $email, $id_usuario);
        $check->execute();
        $check->store_result();

        if ($check->num_rows > 0) {
            $mensaje = "El correo electrónico ya está registrado por otro usuario.";
            $tipo_mensaje = "warning";
        } else {
            $conexion->begin_transaction();
            try {
                // A. Actualizar tabla usuarios
                // Si hay contraseña nueva, la actualizamos. Si no, la ignoramos.
                if (!empty($password)) {
                    $hash = password_hash($password, PASSWORD_DEFAULT);
                    $sql_user = "UPDATE usuarios SET nombre=?, email=?, password=?, id_sucursal=?, activo=? WHERE id=?";
                    $stmt = $conexion->prepare($sql_user);
                    $stmt->bind_param("sssiii", $nombre, $email, $hash, $id_sucursal, $activo, $id_usuario);
                } else {
                    $sql_user = "UPDATE usuarios SET nombre=?, email=?, id_sucursal=?, activo=? WHERE id=?";
                    $stmt = $conexion->prepare($sql_user);
                    $stmt->bind_param("ssiii", $nombre, $email, $id_sucursal, $activo, $id_usuario);
                }
                
                if (!$stmt->execute()) {
                    throw new Exception("Error al actualizar usuario: " . $stmt->error);
                }
                $stmt->close();

                // B. Actualizar Rol (tabla usuario_roles)
                // Primero verificamos si ya tiene rol asignado
                $check_rol = $conexion->query("SELECT * FROM usuario_roles WHERE id_usuario = $id_usuario");
                if ($check_rol->num_rows > 0) {
                    $sql_rol = "UPDATE usuario_roles SET id_rol=? WHERE id_usuario=?";
                    $stmt_rol = $conexion->prepare($sql_rol);
                    $stmt_rol->bind_param("ii", $id_rol, $id_usuario);
                } else {
                    // Si por error no tenía rol, lo insertamos
                    $sql_rol = "INSERT INTO usuario_roles (id_rol, id_usuario) VALUES (?, ?)";
                    $stmt_rol = $conexion->prepare($sql_rol);
                    $stmt_rol->bind_param("ii", $id_rol, $id_usuario);
                }
                
                if (!$stmt_rol->execute()) {
                    throw new Exception("Error al actualizar rol: " . $stmt_rol->error);
                }
                $stmt_rol->close();

                $conexion->commit();
                
                // REDIRECCIÓN CORRECTA
                header("Location: gestion_usuarios.php?msg=actualizado");
                exit();

            } catch (Exception $e) {
                $conexion->rollback();
                $mensaje = $e->getMessage();
                $tipo_mensaje = "danger";
            }
        }
        $check->close();
    }
}

// 3. OBTENER DATOS ACTUALES
// Usamos JOIN para traer el rol actual también
$sql_data = "SELECT u.*, ur.id_rol 
             FROM usuarios u 
             LEFT JOIN usuario_roles ur ON u.id = ur.id_usuario 
             WHERE u.id = ?";
$stmt = $conexion->prepare($sql_data);
$stmt->bind_param("i", $id_usuario);
$stmt->execute();
$usuario = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$usuario) {
    header("Location: gestion_usuarios.php?msg=no_encontrado");
    exit();
}

// 4. INCLUIR HEADER (AHORA SÍ)
require_once '../templates/header.php';

// Cargar listas
$sucursales = $conexion->query("SELECT id, nombre FROM sucursales WHERE estado = 'Activo' ORDER BY nombre");
$roles = $conexion->query("SELECT id, nombre_rol FROM roles ORDER BY nombre_rol");
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h2">Editar Usuario</h1>
    <a href="gestion_usuarios.php" class="btn btn-secondary">
        <i class="bi bi-arrow-left me-2"></i> Volver
    </a>
</div>

<?php if (!empty($mensaje)): ?>
    <div class="alert alert-<?php echo $tipo_mensaje; ?> alert-dismissible fade show shadow-sm" role="alert">
        <?php echo $mensaje; ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<div class="card shadow-sm border-0">
    <div class="card-header bg-white py-3">
        <h5 class="mb-0 text-primary fw-bold"><i class="bi bi-person-gear me-2"></i> Modificar Datos</h5>
    </div>
    <div class="card-body p-4">
        <form action="usuario_editar.php?id=<?php echo $id_usuario; ?>" method="POST" autocomplete="off">
            <div class="row g-3">
                
                <div class="col-md-6">
                    <label class="form-label fw-bold">Nombre Completo <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" name="nombre" value="<?php echo htmlspecialchars($usuario['nombre']); ?>" required>
                </div>

                <div class="col-md-6">
                    <label class="form-label fw-bold">Correo Electrónico <span class="text-danger">*</span></label>
                    <input type="email" class="form-control" name="email" value="<?php echo htmlspecialchars($usuario['email']); ?>" required>
                </div>

                <div class="col-md-6">
                    <label class="form-label fw-bold">Contraseña</label>
                    <input type="password" class="form-control" name="password" placeholder="Dejar en blanco para mantener la actual" autocomplete="new-password">
                    <div class="form-text">Solo rellene si desea cambiar la contraseña.</div>
                </div>

                <div class="col-md-6">
                    <label class="form-label fw-bold">Sucursal Asignada</label>
                    <select class="form-select" name="id_sucursal">
                        <option value="">-- Sin Sucursal (Global) --</option>
                        <?php 
                        if ($sucursales) {
                            mysqli_data_seek($sucursales, 0);
                            while ($row = $sucursales->fetch_assoc()) {
                                $selected = ($usuario['id_sucursal'] == $row['id']) ? 'selected' : '';
                                echo "<option value='{$row['id']}' $selected>{$row['nombre']}</option>";
                            }
                        }
                        ?>
                    </select>
                </div>

                <div class="col-md-6">
                    <label class="form-label fw-bold">Rol de Usuario <span class="text-danger">*</span></label>
                    <select class="form-select" name="id_rol" required>
                        <option value="">Seleccione un rol...</option>
                        <?php 
                        if ($roles) {
                            mysqli_data_seek($roles, 0);
                            while ($row = $roles->fetch_assoc()) {
                                $selected = ($usuario['id_rol'] == $row['id']) ? 'selected' : '';
                                echo "<option value='{$row['id']}' $selected>{$row['nombre_rol']}</option>";
                            }
                        }
                        ?>
                    </select>
                </div>

                <div class="col-md-6">
                    <label class="form-label fw-bold">Estado</label>
                    <select class="form-select" name="activo">
                        <option value="1" <?php echo ($usuario['activo'] == 1) ? 'selected' : ''; ?>>Activo</option>
                        <option value="0" <?php echo ($usuario['activo'] == 0) ? 'selected' : ''; ?>>Inactivo (Bloqueado)</option>
                    </select>
                </div>

            </div>

            <hr class="my-4">

            <div class="d-flex justify-content-end gap-2">
                <a href="gestion_usuarios.php" class="btn btn-secondary">Cancelar</a>
                <button type="submit" class="btn btn-primary px-4">
                    <i class="bi bi-save me-2"></i> Guardar Cambios
                </button>
            </div>
        </form>
    </div>
</div>

<?php require_once '../templates/footer.php'; ?>