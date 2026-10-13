<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'mensaje' => 'Sesión no iniciada.']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'mensaje' => 'Método no permitido.']);
    exit();
}

require_once '../config/database.php';

$id_equipo = (int) ($_POST['id_equipo'] ?? 0);
$id_marca = (int) ($_POST['id_marca'] ?? 0);
$id_modelo = (int) ($_POST['id_modelo'] ?? 0);

if ($id_equipo <= 0 || $id_marca <= 0 || $id_modelo <= 0) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'mensaje' => 'Seleccione marca y modelo.']);
    exit();
}

$stmt = $conexion->prepare("SELECT m.nombre AS marca_nombre, mo.nombre AS modelo_nombre
                            FROM marcas m
                            JOIN modelos mo ON mo.id_marca = m.id
                            WHERE m.id = ? AND mo.id = ?");
$stmt->bind_param("ii", $id_marca, $id_modelo);
$stmt->execute();
$resultado = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$resultado) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'mensaje' => 'El modelo no corresponde a la marca seleccionada.']);
    exit();
}

$stmt = $conexion->prepare("UPDATE equipos SET id_marca = ?, id_modelo = ? WHERE id = ?");
$stmt->bind_param("iii", $id_marca, $id_modelo, $id_equipo);

try {
    $stmt->execute();
    $stmt->close();

    echo json_encode([
        'ok' => true,
        'marca' => $resultado['marca_nombre'],
        'modelo' => $resultado['modelo_nombre'],
        'mensaje' => 'Marca y modelo actualizados.',
    ], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'mensaje' => 'Error al actualizar: ' . $e->getMessage()], JSON_UNESCAPED_UNICODE);
}
