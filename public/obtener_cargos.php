<?php
// Incluir conexión a la base de datos
require_once '../config/database.php';

// Configurar cabecera para JSON
header('Content-Type: application/json');
$response = []; // Array para la respuesta

// Validar que se recibió el id_area y es un número
if (!isset($_GET['id_area']) || !is_numeric($_GET['id_area'])) {
    echo json_encode(['error' => 'ID de área no válido']);
    exit;
}

$id_area = (int)$_GET['id_area'];

// Preparar la consulta para obtener los cargos activos de esa área
// Asegúrate de que tus columnas se llamen 'id', 'nombre'
$sql = "SELECT id, nombre FROM cargos WHERE id_area = ? AND estado = 'Activo' ORDER BY nombre";
$stmt = $conexion->prepare($sql);

if ($stmt) {
    $stmt->bind_param("i", $id_area);
    if ($stmt->execute()) {
        $resultado = $stmt->get_result();
        while ($fila = $resultado->fetch_assoc()) {
            $response[] = $fila; // Añadir cada cargo al array
        }
    } else {
        $response = ['error' => 'Error al ejecutar consulta: ' . $stmt->error];
    }
    $stmt->close();
} else {
    $response = ['error' => 'Error al preparar consulta: ' . $conexion->error];
}

// Devolver el array de respuesta codificado como JSON
echo json_encode($response);
?>