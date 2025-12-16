<?php
// 1. INICIO DE SESIÓN Y LÓGICA (ANTES DE CUALQUIER HTML)
session_start();
require_once '../config/database.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email']);
    $password = $_POST['password'];

    if (empty($email) || empty($password)) {
        $error = "Por favor, complete todos los campos.";
    } else {
        // Consultar usuario
        $stmt = $conexion->prepare("SELECT id, nombre, password, id_sucursal FROM usuarios WHERE email = ? AND activo = 1");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $resultado = $stmt->get_result();

        if ($user = $resultado->fetch_assoc()) {
            if (password_verify($password, $user['password'])) {
                // Obtener nombre del rol
                $stmt_rol = $conexion->prepare("SELECT r.nombre_rol FROM roles r JOIN usuario_roles ur ON r.id = ur.id_rol WHERE ur.id_usuario = ?");
                $stmt_rol->bind_param("i", $user['id']);
                $stmt_rol->execute();
                $res_rol = $stmt_rol->get_result();
                $rol = ($row_rol = $res_rol->fetch_assoc()) ? $row_rol['nombre_rol'] : 'Usuario';

                // Guardar en sesión
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['user_nombre'] = $user['nombre'];
                $_SESSION['user_email'] = $email;
                $_SESSION['user_rol'] = $rol;
                $_SESSION['user_sucursal_id'] = $user['id_sucursal'];
                
                // Cargar config global
                $sql_config = "SELECT clave, valor FROM configuracion";
                $res_config = $conexion->query($sql_config);
                if($res_config) {
                    $_SESSION['configuracion'] = [];
                    while($row = $res_config->fetch_assoc()) {
                        $_SESSION['configuracion'][$row['clave']] = $row['valor'];
                    }
                }

                header("Location: index.php");
                exit();
            } else {
                $error = "La contraseña es incorrecta.";
            }
        } else {
            $error = "El usuario no existe o está inactivo.";
        }
        $stmt->close();
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Iniciar Sesión | Inventario TI</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="css/style.css?v=<?php echo time(); ?>">
</head>
<body class="login-page">

    <div class="login-layout">
        
        <div class="login-left">
            <i class="bi bi-laptop-fill illustration-icon"></i>
            
            <h1 class="login-welcome-title">Bienvenido</h1>
            <p class="login-welcome-text">
                Gestión inteligente de inventario TI. <br>
                Controla tus activos de forma rápida y segura.
            </p>
        </div>

        <div class="login-right">
            <div class="login-form-container">
                <h2 class="login-title">Iniciar Sesión</h2>

                <?php if (!empty($error)): ?>
                    <div class="alert alert-danger d-flex align-items-center mb-4 text-small" role="alert">
                        <i class="bi bi-exclamation-circle-fill me-2"></i>
                        <div><?php echo htmlspecialchars($error); ?></div>
                    </div>
                <?php endif; ?>

                <form action="login.php" method="POST">
                    
                    <div class="input-group-custom">
                        <input type="email" class="form-control-custom" id="email" name="email" placeholder="admin@correo.com" required autofocus>
                    </div>

                    <div class="input-group-custom">
                        <input type="password" class="form-control-custom" id="password" name="password" placeholder="Contraseña" required>
                    </div>

                    <button type="submit" class="btn-login-blue">
                        INGRESAR
                    </button>

                </form>
            </div>
        </div>

    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>