<?php
session_start();
require_once '../config/database.php';

// 1. VERIFICACIÓN DE SEGURIDAD (ROL)
if (!isset($_SESSION['user_rol']) || (strtolower($_SESSION['user_rol']) !== 'administrador' && strtolower($_SESSION['user_rol']) !== 'admin')) {
    header("Location: index.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $password_confirm = $_POST['password_confirm'];
    $user_id = $_SESSION['user_id'];

    // 2. VERIFICAR CONTRASEÑA DEL ADMINISTRADOR
    // Obtenemos la contraseña actual de la BD para compararla
    $stmt = $conexion->prepare("SELECT password FROM usuarios WHERE id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();

    if ($user && password_verify($password_confirm, $user['password'])) {
        // CONTRASEÑA CORRECTA: PROCEDER AL BORRADO
        
        // Tablas a vaciar (Orden importa si no desactivamos FK, pero lo haremos)
        // MANTENEMOS: usuarios, roles, usuario_roles, configuracion
        $tablas_a_vaciar = [
            'reparaciones',
            'bajas',
            'asignaciones',
            'equipos',
            'empleados',
            'modelos',
            'marcas',
            'tipos_equipo',
            'cargos',
            'areas',
            'sucursales'
        ];

        $conexion->begin_transaction();

        try {
            // Desactivar revisión de claves foráneas para permitir TRUNCATE sin errores
            $conexion->query("SET FOREIGN_KEY_CHECKS = 0");

            foreach ($tablas_a_vaciar as $tabla) {
                // TRUNCATE es más rápido y reinicia los IDs (AUTO_INCREMENT) a 1
                $conexion->query("TRUNCATE TABLE `$tabla`");
            }

            // Reactivar claves foráneas
            $conexion->query("SET FOREIGN_KEY_CHECKS = 1");

            $conexion->commit();
            
            // Registro de auditoría (opcional, si tuvieras tabla de logs)
            // ...

            header("Location: reset_system.php?status=success");
            exit();

        } catch (Exception $e) {
            $conexion->rollback();
            header("Location: reset_system.php?status=error&msg=" . urlencode($e->getMessage()));
            exit();
        }

    } else {
        // CONTRASEÑA INCORRECTA
        header("Location: reset_system.php?status=wrong_password");
        exit();
    }
} else {
    header("Location: reset_system.php");
    exit();
}
?>