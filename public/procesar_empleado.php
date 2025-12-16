<?php
require_once '../config/database.php'; // Incluir conexión
session_start(); // Iniciar sesión para validaciones o mensajes

// Verificar si es una solicitud POST y la acción es 'agregar'
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_GET['accion']) && $_GET['accion'] === 'agregar') {

    // 1. Recoger datos del formulario
    $id_sucursal = $_POST['id_sucursal'] ?? null;
    $dni = trim($_POST['dni'] ?? '');
    $nombres = trim($_POST['nombres'] ?? '');
    $apellidos = trim($_POST['apellidos'] ?? '');
    $id_area = $_POST['id_area'] ?? null;
    $id_cargo = $_POST['id_cargo'] ?? null;
    $estado = $_POST['estado'] ?? 'Activo';

    // 2. Validación básica
    if (empty($id_sucursal) || empty($dni) || empty($nombres) || empty($apellidos) || empty($id_area) || empty($id_cargo)) {
        header("Location: empleado_agregar.php?status=error_campos");
        exit();
    }
    // (Aquí puedes añadir más validaciones, como DNI único)

    // 3. Preparar la consulta SQL para insertar
    $sql = "INSERT INTO empleados (id_sucursal, dni, nombres, apellidos, id_area, id_cargo, estado) 
            VALUES (?, ?, ?, ?, ?, ?, ?)";
    
    $stmt = $conexion->prepare($sql);

    if ($stmt) {
        $stmt->bind_param("isssiis", $id_sucursal, $dni, $nombres, $apellidos, $id_area, $id_cargo, $estado);
        
        // 4. Ejecutar la consulta
        if ($stmt->execute()) {
            // Éxito: Redirigir a la lista de empleados
            header("Location: empleados.php?status=empleado_agregado");
            exit();
        } else {
            // Error al ejecutar
            $error_msg = urlencode($stmt->error);
            header("Location: empleado_agregar.php?status=error_guardar&msg=" . $error_msg);
            exit();
        }
        $stmt->close();
    } else {
        // Error al preparar la consulta
        $error_msg = urlencode($conexion->error);
        header("Location: empleado_agregar.php?status=error_sql&msg=" . $error_msg);
        exit();
    }

} else {
    // Si no es POST o la acción no es 'agregar', redirigir
    header("Location: empleados.php?status=error_accion");
    exit();
}
?>