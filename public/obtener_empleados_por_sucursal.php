<?php
require_once '../config/database.php';
header('Content-Type: application/json');
$response = [];

if (!isset($_GET['id_sucursal']) || !is_numeric($_GET['id_sucursal'])) {
    echo json_encode(['error' => 'ID de sucursal no válido']);
    exit;
}

$id_sucursal = (int)$_GET['id_sucursal'];

$sql = "SELECT id, dni, nombres, apellidos FROM empleados WHERE id_sucursal = ? AND estado = 'Activo' ORDER BY apellidos, nombres";
$stmt = $conexion->prepare($sql);

if ($stmt) {
    $stmt->bind_param("i", $id_sucursal);
    if ($stmt->execute()) {
        $resultado = $stmt->get_result();
        while ($fila = $resultado->fetch_assoc()) {
            $response[] = $fila; // Añadir cada empleado al array
        }
    } else { 
        $response = ['error' => 'Error al ejecutar consulta']; 
    }
    $stmt->close();
} else { 
    $response = ['error' => 'Error al preparar consulta']; 
}

echo json_encode($response);
?>