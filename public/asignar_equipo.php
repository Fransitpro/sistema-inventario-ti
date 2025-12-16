<?php
require_once '../config/database.php';
session_start();

// Validar que el usuario esté logueado
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // 1. Recoger datos del formulario
    $id_equipo = $_POST['id_equipo'] ?? null;
    $id_empleado = $_POST['id_empleado'] ?? null;
    $id_sucursal = $_POST['id_sucursal'] ?? null;
    $observaciones = $_POST['observaciones_entrega'] ?? '';
    $fecha_entrega = date('Y-m-d H:i:s'); // Fecha y hora actual
    // $id_usuario_entrega = $_SESSION['user_id']; // Esta columna no existe, la ignoramos

    // 2. Validación
    if (empty($id_equipo) || empty($id_empleado) || empty($id_sucursal)) {
         header("Location: asignacion_agregar.php?status=error_campos");
         exit();
    }

    // 3. Iniciar Transacción
    $conexion->begin_transaction();
    try {
        // 3a. Insertar el registro de asignación
        // CORRECCIÓN: Se eliminó 'id_usuario_entrega' de la consulta
        $sql_asig = "INSERT INTO asignaciones (id_equipo, id_empleado, fecha_entrega, observaciones_entrega, estado_asignacion)
                     VALUES (?, ?, ?, ?, 'Activa')";
        $stmt_asig = $conexion->prepare($sql_asig);
        // CORRECCIÓN: Se ajustó el bind_param (de 'iiiss' a 'iiss')
        $stmt_asig->bind_param("iiss", $id_equipo, $id_empleado, $fecha_entrega, $observaciones);
        $stmt_asig->execute();
        
        // 3b. Actualizar el estado del equipo a 'Asignado'
        $sql_eq = "UPDATE equipos SET estado = 'Asignado' WHERE id = ? AND estado = 'Disponible'";
        $stmt_eq = $conexion->prepare($sql_eq);
        $stmt_eq->bind_param("i", $id_equipo);
        $stmt_eq->execute();
        
        if ($stmt_eq->affected_rows === 0) {
            throw new Exception("El equipo no estaba disponible o ya estaba asignado.");
        }

        // 4. Confirmar Transacción
        $conexion->commit();
        header("Location: asignaciones.php?status=success_add");
        exit();

    } catch (Exception $e) {
        // 5. Revertir en caso de error
        $conexion->rollback();
        header("Location: asignacion_agregar.php?status=error_guardar&msg=" . urlencode($e->getMessage()));
        exit();
    }
} else {
    // Si no es POST, redirigir
    header("Location: asignacion_agregar.php");
    exit();
}
?>