<?php
require_once '../templates/header.php';

// Solo los administradores pueden acceder
if ($_SESSION['user_rol'] !== 'Administrador') {
    echo "<div class='alert alert-danger'>Acceso denegado.</div>";
    require_once '../templates/footer.php';
    exit();
}
$usuarios = $conexion->query("SELECT u.id, u.nombre, u.email, r.nombre_rol, s.nombre as sucursal_nombre FROM usuarios u LEFT JOIN usuario_roles ur ON u.id = ur.id_usuario LEFT JOIN roles r ON ur.id_rol = r.id LEFT JOIN sucursales s ON u.id_sucursal = s.id ORDER BY u.nombre");
?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <h1 class="h2">Gestión de Usuarios y Roles</h1>
    <a href="usuario_agregar.php" class="btn btn-primary"><i class="bi bi-plus-circle me-2"></i>Registrar Nuevo Usuario</a>
</div>

<div class="card">
    <div class="card-header">Usuarios del Sistema</div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-striped table-hover">
                <thead>
                    <tr><th>Nombre</th><th>Email</th><th>Rol</th><th>Sucursal</th><th>Acciones</th></tr>
                </thead>
                <tbody>
                    <?php while ($usuario = $usuarios->fetch_assoc()): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($usuario['nombre']); ?></td>
                            <td><?php echo htmlspecialchars($usuario['email']); ?></td>
                            <td><span class="badge bg-info text-dark"><?php echo htmlspecialchars($usuario['nombre_rol'] ?? 'Sin rol'); ?></span></td>
                            <td><?php echo htmlspecialchars($usuario['sucursal_nombre'] ?? 'Todas'); ?></td>
                            <td>
                                <a href="usuario_editar.php?id=<?php echo $usuario['id']; ?>" class="btn btn-warning btn-sm" title="Editar"><i class="bi bi-pencil"></i></a>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require_once '../templates/footer.php'; ?>