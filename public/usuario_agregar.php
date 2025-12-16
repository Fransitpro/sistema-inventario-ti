<?php
// 1. LÓGICA DE NEGOCIO PRIMERO (ANTES DE CUALQUIER HTML)
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once '../config/database.php';

// Validar acceso (Solo administradores)
if (!isset($_SESSION['user_rol']) || (strtolower($_SESSION['user_rol']) !== 'administrador' && strtolower($_SESSION['user_rol']) !== 'admin')) {
    header("Location: index.php");
    exit();
}

$mensaje = '';
$tipo_mensaje = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Recibir datos
    $nombre = trim($_POST['nombre']);
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    $id_sucursal = !empty($_POST['id_sucursal']) ? $_POST['id_sucursal'] : null;
    $id_rol = $_POST['id_rol'];
    $activo = 1; // Por defecto activo al crear

    if (empty($nombre) || empty($email) || empty($password) || empty($id_rol)) {
        $mensaje = "Por favor complete los campos obligatorios.";
        $tipo_mensaje = "danger";
    } else {
        // Verificar si el correo ya existe
        $check = $conexion->prepare("SELECT id FROM usuarios WHERE email = ?");
        $check->bind_param("s", $email);
        $check->execute();
        $check->store_result();

        if ($check->num_rows > 0) {
            $mensaje = "El correo electrónico ya está registrado.";
            $tipo_mensaje = "warning";
        } else {
            // INICIAR TRANSACCIÓN (Para guardar usuario y rol juntos)
            $conexion->begin_transaction();

            try {
                // 1. Insertar Usuario
                $hash_password = password_hash($password, PASSWORD_DEFAULT);
                
                // Nota: id_sucursal puede ser NULL si es un admin global
                $sql_user = "INSERT INTO usuarios (nombre, email, password, id_sucursal, activo, fecha_creacion) VALUES (?, ?, ?, ?, ?, NOW())";
                $stmt = $conexion->prepare($sql_user);
                $stmt->bind_param("sssii", $nombre, $email, $hash_password, $id_sucursal, $activo);
                
                if (!$stmt->execute()) {
                    throw new Exception("Error al crear usuario: " . $stmt->error);
                }
                
                $nuevo_usuario_id = $conexion->insert_id;
                $stmt->close();

                // 2. Asignar Rol en tabla 'usuario_roles'
                $sql_rol = "INSERT INTO usuario_roles (id_usuario, id_rol) VALUES (?, ?)";
                $stmt_rol = $conexion->prepare($sql_rol);
                $stmt_rol->bind_param("ii", $nuevo_usuario_id, $id_rol);
                
                if (!$stmt_rol->execute()) {
                    throw new Exception("Error al asignar rol: " . $stmt_rol->error);
                }
                $stmt_rol->close();

                // CONFIRMAR TRANSACCIÓN
                $conexion->commit();
                
                // REDIRECCIÓN CORRECTA
                header("Location: gestion_usuarios.php?msg=creado");
                exit();

            } catch (Exception $e) {
                // CANCELAR SI HUBO ERROR
                $conexion->rollback();
                $mensaje = $e->getMessage();
                $tipo_mensaje = "danger";
            }
        }
        $check->close();
    }
}

// 2. INCLUIR HEADER (AHORA SÍ ES SEGURO MOSTRAR HTML)
require_once '../templates/header.php';

// Cargar listas para selectores
$sucursales = $conexion->query("SELECT id, nombre FROM sucursales WHERE estado = 'Activo' ORDER BY nombre");
$roles = $conexion->query("SELECT id, nombre_rol FROM roles ORDER BY nombre_rol");
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h2">Crear Nuevo Usuario</h1>
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
        <h5 class="mb-0 text-primary fw-bold"><i class="bi bi-person-plus-fill me-2"></i> Datos del Usuario</h5>
    </div>
    <div class="card-body p-4">
        <form action="usuario_agregar.php" method="POST" autocomplete="off">
            <div class="row g-3">
                
                <div class="col-md-6">
                    <label class="form-label fw-bold">Nombre Completo <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" name="nombre" required placeholder="Nombre Apellido">
                </div>

                <div class="col-md-6">
                    <label class="form-label fw-bold">Correo Electrónico <span class="text-danger">*</span></label>
                    <input type="email" class="form-control" name="email" required placeholder="usuario@empresa.com" autocomplete="new-password">
                </div>

                <div class="col-md-6">
                    <label class="form-label fw-bold">Contraseña <span class="text-danger">*</span></label>
                    <input type="password" class="form-control" name="password" required placeholder="••••••••" autocomplete="new-password">
                </div>

                <div class="col-md-6">
                    <label class="form-label fw-bold">Sucursal Asignada</label>
                    <select class="form-select" name="id_sucursal">
                        <option value="">-- Sin Sucursal (Global) --</option>
                        <?php 
                        if ($sucursales) {
                            while ($row = $sucursales->fetch_assoc()) {
                                echo "<option value='{$row['id']}'>{$row['nombre']}</option>";
                            }
                        }
                        ?>
                    </select>
                    <div class="form-text text-muted">Dejar vacío si es un administrador global.</div>
                </div>

                <div class="col-md-6">
                    <label class="form-label fw-bold">Rol de Usuario <span class="text-danger">*</span></label>
                    <select class="form-select" name="id_rol" required>
                        <option value="">Seleccione un rol...</option>
                        <?php 
                        if ($roles) {
                            while ($row = $roles->fetch_assoc()) {
                                echo "<option value='{$row['id']}'>{$row['nombre_rol']}</option>";
                            }
                        }
                        ?>
                    </select>
                </div>

            </div>

            <hr class="my-4">

            <div class="d-flex justify-content-end gap-2">
                <a href="gestion_usuarios.php" class="btn btn-secondary">Cancelar</a>
                <button type="submit" class="btn btn-primary px-4">
                    <i class="bi bi-save me-2"></i> Guardar Usuario
                </button>
            </div>
        </form>
    </div>
</div>

<?php require_once '../templates/footer.php'; ?>