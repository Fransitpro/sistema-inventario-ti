<?php
// Solo iniciar sesión si no está activa
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}
require_once __DIR__ . '/../config/database.php';

if (!isset($_SESSION['configuracion'])) {
    $_SESSION['configuracion'] = [];
    $sql_config = "SELECT clave, valor FROM configuracion";
    $resultado_config = $conexion->query($sql_config);
    if ($resultado_config) {
        while ($fila = $resultado_config->fetch_assoc()) {
            $_SESSION['configuracion'][$fila['clave']] = $fila['valor'];
        }
    }
}

$current_page = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sistema de Inventario TI</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/buttons/2.4.1/css/buttons.bootstrap5.min.css">
    <link rel="stylesheet" href="css/style.css?ver=<?php echo time(); ?>">
</head>
<body>

<div id="sidebar-overlay" class="sidebar-overlay"></div>

<header class="mobile-header d-lg-none bg-primary text-white p-3 d-flex align-items-center shadow-sm sticky-top">
    <button class="btn text-white me-3 p-0 border-0" type="button" id="menu-toggle">
        <i class="bi bi-list fs-1"></i>
    </button>
    <span class="fs-4 fw-bold">Inventario TI</span>
</header>

<div class="sidebar d-flex flex-column flex-shrink-0 p-3 text-white" id="sidebar">
    <a href="index.php" class="d-flex align-items-center mb-4 mb-md-0 me-md-auto text-white text-decoration-none">
        <i class="bi bi-layers-fill me-3" style="font-size: 2rem;"></i>
        <span class="fs-4 fw-bold">Inventario TI</span>
    </a>
    <hr>
    
    <ul class="nav nav-pills flex-column mb-auto">
        <li class="nav-item">
            <a href="index.php" class="nav-link <?php if($current_page == 'index.php') echo 'active'; ?>">
                <i class="bi bi-speedometer2"></i> Dashboard
            </a>
        </li>
        <li>
            <a href="equipos.php" class="nav-link <?php if(in_array($current_page, ['equipos.php', 'equipo_agregar.php', 'equipo_editar.php', 'equipo_detalle.php'])) echo 'active'; ?>">
                <i class="bi bi-laptop"></i> Equipos
            </a>
        </li>
        <li>
            <a href="empleados.php" class="nav-link <?php if(in_array($current_page, ['empleados.php', 'empleado_agregar.php', 'empleado_editar.php'])) echo 'active'; ?>">
                <i class="bi bi-people"></i> Empleados
            </a>
        </li>
        <li>
            <a href="asignaciones.php" class="nav-link <?php if(str_starts_with($current_page, 'asignacion')) echo 'active'; ?>">
                <i class="bi bi-card-checklist"></i> Asignaciones
            </a>
        </li>
        <li>
            <a href="reparaciones.php" class="nav-link <?php if(str_starts_with($current_page, 'reparacion')) echo 'active'; ?>">
                <i class="bi bi-tools"></i> Reparaciones
            </a>
        </li>
        <li>
            <a href="bajas.php" class="nav-link <?php if(str_starts_with($current_page, 'baja') || $current_page == 'equipo_dar_de_baja.php') echo 'active'; ?>">
                <i class="bi bi-trash"></i> Bajas
            </a>
        </li>
        <li>
            <a href="gestion_catalogos.php" class="nav-link <?php if($current_page == 'gestion_catalogos.php') echo 'active'; ?>">
                <i class="bi bi-tags"></i> Catálogos
            </a>
        </li>
        
        <?php 
        $rol = isset($_SESSION['user_rol']) ? strtolower($_SESSION['user_rol']) : '';
        if ($rol === 'administrador' || $rol === 'admin'): 
        ?>
        <hr class="my-2 border-white opacity-25">
        <div class="small text-uppercase text-white-50 mb-2 px-3">Administración</div>
        
        <li>
            <a href="gestion_usuarios.php" class="nav-link <?php if(str_starts_with($current_page, 'usuario')) echo 'active'; ?>">
                <i class="bi bi-shield-lock"></i> Usuarios
            </a>
        </li>
        <li>
            <a href="configuracion.php" class="nav-link <?php if($current_page == 'configuracion.php') echo 'active'; ?>">
                <i class="bi bi-gear"></i> Configuración
            </a>
        </li>
        <li>
            <a href="backup.php" class="nav-link <?php if($current_page == 'backup.php') echo 'active'; ?>">
                <i class="bi bi-database-down"></i> Copia de Seguridad
            </a>
        </li>
        <li>
            <a href="reset_system.php" class="nav-link text-danger <?php if($current_page == 'reset_system.php') echo 'active bg-danger text-white'; ?>" style="font-weight: 600;">
                <i class="bi bi-exclamation-triangle"></i> Restablecer
            </a>
        </li>
        <?php endif; ?>
    </ul>
    
    <hr>
    
    <div class="dropdown">
        <a href="#" class="d-flex align-items-center text-white text-decoration-none dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false">
            <div class="rounded-circle bg-white text-primary d-flex justify-content-center align-items-center me-2" style="width: 40px; height: 40px;">
                <i class="bi bi-person-fill fs-5"></i>
            </div>
            <div>
                <strong class="d-block lh-1"><?php echo htmlspecialchars($_SESSION['user_nombre'] ?? 'Usuario'); ?></strong>
                <small class="text-white-50" style="font-size: 0.8rem;"><?php echo htmlspecialchars($_SESSION['user_rol'] ?? 'Rol'); ?></small>
            </div>
        </a>
        <ul class="dropdown-menu dropdown-menu-dark text-small shadow border-0">
            <li><a class="dropdown-item" href="cambiar_password.php"><i class="bi bi-key me-2"></i> Cambiar Contraseña</a></li>
            <li><hr class="dropdown-divider"></li>
            <li><a class="dropdown-item" href="logout.php"><i class="bi bi-box-arrow-right me-2"></i> Cerrar Sesión</a></li>
        </ul>
    </div>
</div>

<main class="main-content">