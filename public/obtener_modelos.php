<?php
// Incluir conexión a la base de datos
require_once '../config/database.php';

// Configurar cabecera para JSON
header('Content-Type: application/json');
$response = []; // Array para la respuesta

// Validar que se recibió el id_marca y es un número
if (!isset($_GET['id_marca']) || !is_numeric($_GET['id_marca'])) {
    echo json_encode(['error' => 'ID de marca no válido']);
    exit;
}

$id_marca = (int)$_GET['id_marca'];

// Preparar la consulta para obtener los modelos activos de esa marca
// (Asegúrate de que tus columnas se llamen 'id', 'nombre', 'id_marca' y 'estado')
$sql = "SELECT id, nombre FROM modelos WHERE id_marca = ? AND estado = 'Activo' ORDER BY nombre";
$stmt = $conexion->prepare($sql);

if ($stmt) {
    $stmt->bind_param("i", $id_marca);
    if ($stmt->execute()) {
        $resultado = $stmt->get_result();
        while ($fila = $resultado->fetch_assoc()) {
            $response[] = $fila; // Añadir cada modelo al array
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