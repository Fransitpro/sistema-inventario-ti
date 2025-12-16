<?php
require_once '../config/database.php';
session_start();

// 1. Validar que el usuario esté logueado
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// 2. Verificar que sea una solicitud POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: asignaciones.php");
    exit();
}

// 3. Recoger datos del formulario
$id_asignacion = $_POST['id_asignacion'] ?? null;
$id_equipo = $_POST['id_equipo'] ?? null;
$fecha_devolucion = $_POST['fecha_devolucion'] ?? null;

// --- CAMPOS DE TU FORMULARIO (basado en image_7dd47e.png) ---
$estado_recibido = $_POST['estado_recibido'] ?? 'No especificado';
$estado_final_equipo = $_POST['estado_final_equipo'] ?? 'Disponible'; // Estado por defecto
$observaciones_adicionales = trim($_POST['observaciones_devolucion'] ?? ''); // 'observaciones_devolucion' es el name en tu archivo

// --- COMBINAR OBSERVACIONES ---
// Se crea un texto unificado para guardar en la base de datos
$observaciones_completas = "Estado al recibir: " . $estado_recibido . ".\nObservaciones: " . $observaciones_adicionales;

$nombres_imagenes = [
    'imagen_devolucion_1' => null,
    'imagen_devolucion_2' => null,
    'imagen_devolucion_3' => null
];

// 4. Validar IDs y campos obligatorios
if (empty($id_asignacion) || empty($id_equipo) || empty($fecha_devolucion) || empty($observaciones_adicionales)) {
    header("Location: asignacion_devolver.php?id={$id_asignacion}&status=error_campos");
    exit();
}

// Validar que el estado final sea uno de los permitidos
if (!in_array($estado_final_equipo, ['Disponible', 'En Reparación'])) {
    $estado_final_equipo = 'Disponible'; // Seguridad
}

// 5. Lógica de Subida de Archivos
// Usar la carpeta 'uploads/devoluciones/' (basado en tu proyecto)
$upload_dir = __DIR__ . '/../uploads/devoluciones/' . $id_asignacion . '/';
if (!is_dir($upload_dir)) {
    if (!mkdir($upload_dir, 0777, true)) {
        header("Location: asignacion_devolver.php?id={$id_asignacion}&status=error_carpeta");
        exit();
    }
}

// Procesar las 3 imágenes (basado en la estructura de tu tabla)
foreach ($nombres_imagenes as $key => $value) {
    if (isset($_FILES[$key]) && $_FILES[$key]['error'] === UPLOAD_ERR_OK) {
        $file_tmp_name = $_FILES[$key]['tmp_name'];
        $file_name = time() . '_' . basename($_FILES[$key]['name']);
        $destination = $upload_dir . $file_name;

        if (move_uploaded_file($file_tmp_name, $destination)) {
            $nombres_imagenes[$key] = $file_name; // Guardar solo el nombre del archivo
        }
    }
}

// 6. Actualizar la Base de Datos
$conexion->begin_transaction();
try {
    // 6a. Actualizar la tabla 'asignaciones'
    $sql_asig = "UPDATE asignaciones SET 
                    fecha_devolucion = ?,
                    observaciones_devolucion = ?,
                    estado_asignacion = 'Finalizada',
                    imagen_devolucion_1 = ?,
                    imagen_devolucion_2 = ?,
                    imagen_devolucion_3 = ?
                 WHERE id = ?";
    $stmt_asig = $conexion->prepare($sql_asig);
    $stmt_asig->bind_param("sssssi",
        $fecha_devolucion,
        $observaciones_completas, // Variable con el texto combinado
        $nombres_imagenes['imagen_devolucion_1'],
        $nombres_imagenes['imagen_devolucion_2'],
        $nombres_imagenes['imagen_devolucion_3'],
        $id_asignacion
    );
    $stmt_asig->execute();
    $stmt_asig->close();

    // 6b. Actualizar la tabla 'equipos' con el ESTADO FINAL SELECCIONADO
    $sql_eq = "UPDATE equipos SET estado = ? WHERE id = ?";
    $stmt_eq = $conexion->prepare($sql_eq);
    $stmt_eq->bind_param("si", $estado_final_equipo, $id_equipo);
    $stmt_eq->execute();
    $stmt_eq->close();
    
    // (Opcional) Si el estado es 'En Reparación', crear registro en la tabla 'reparaciones'
    if ($estado_final_equipo === 'En Reparación') {
        $motivo_reparacion = "Devuelto con estado '$estado_recibido'. Obs: " . $observaciones_adicionales;
        $fecha_ingreso_rep = date('Y-m-d');
        // Asumiendo que tu tabla 'reparaciones' existe
        $stmt_rep = $conexion->prepare("INSERT INTO reparaciones (id_equipo, fecha_ingreso, motivo, estado_reparacion) VALUES (?, ?, ?, 'En Proceso')");
        $stmt_rep->bind_param("iss", $id_equipo, $fecha_ingreso_rep, $motivo_reparacion);
        $stmt_rep->execute();
        $stmt_rep->close();
    }

    // 7. Confirmar Transacción
    $conexion->commit();
    header("Location: asignaciones.php?status=devolucion_exitosa");
    exit();

} catch (Exception $e) {
    // 8. Revertir en caso de error
    $conexion->rollback();
    // Eliminar archivos subidos si la BD falló
    foreach ($nombres_imagenes as $file_name) {
        if ($file_name && file_exists($upload_dir . $file_name)) {
            unlink($upload_dir . $file_name);
        }
    }
    header("Location: asignacion_devolver.php?id={$id_asignacion}&status=error_bd&msg=" . urlencode($e->getMessage()));
    exit();
}
?>