<?php
session_start();
require_once '../config/database.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id_empleado = $_POST['id_empleado'];
    $id_equipo = $_POST['id_equipo'];
    $observaciones_entrega = $_POST['observaciones_entrega'];
    $fecha_entrega = date("Y-m-d H:i:s");

    $conexion->begin_transaction();

    try {
        // 1. Insertar el nuevo registro en la tabla de asignaciones
        $sql_insert = "INSERT INTO asignaciones (id_equipo, id_empleado, fecha_entrega, observaciones_entrega, estado_asignacion) VALUES (?, ?, ?, ?, 'Activa')";
        $stmt_insert = $conexion->prepare($sql_insert);
        $stmt_insert->bind_param("iiss", $id_equipo, $id_empleado, $fecha_entrega, $observaciones_entrega);
        $stmt_insert->execute();
        
        // Obtener el ID de la nueva asignación
        $id_nueva_asignacion = $stmt_insert->insert_id;

        // 2. Actualizar el estado del equipo a 'Asignado'
        $sql_update = "UPDATE equipos SET estado = 'Asignado' WHERE id = ?";
        $stmt_update = $conexion->prepare($sql_update);
        $stmt_update->bind_param("i", $id_equipo);
        $stmt_update->execute();

        $conexion->commit();

        // --- CAMBIO: Redirigir de vuelta al listado con el ID de la nueva asignación ---
        header("Location: ../public/asignaciones.php?status=success_add&new_id=" . $id_nueva_asignacion);
        exit();

    } catch (mysqli_sql_exception $exception) {
        $conexion->rollback();
        header("Location: ../public/asignaciones.php?status=error");
        exit();
    }
}