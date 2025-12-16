<?php
require_once '../config/database.php';
header('Content-Type: application/json');
$response = [];

if (!isset($_GET['id_sucursal']) || !is_numeric($_GET['id_sucursal'])) {
    echo json_encode(['error' => 'ID de sucursal no válido']);
    exit;
}

$id_sucursal = (int)$_GET['id_sucursal'];

// Busca equipos que estén 'Disponibles' en esa sucursal
$sql = "SELECT eq.id, eq.codigo_inventario, ma.nombre as marca_nombre, mo.nombre as modelo_nombre 
        FROM equipos eq
        LEFT JOIN marcas ma ON eq.id_marca = ma.id
        LEFT JOIN modelos mo ON eq.id_modelo = mo.id
        WHERE eq.id_sucursal = ? AND eq.estado = 'Disponible' 
        ORDER BY eq.codigo_inventario";
$stmt = $conexion->prepare($sql);

if ($stmt) {
    $stmt->bind_param("i", $id_sucursal);
    if ($stmt->execute()) {
        $resultado = $stmt->get_result();
        while ($fila = $resultado->fetch_assoc()) {
            $response[] = $fila; // Añadir cada equipo al array
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