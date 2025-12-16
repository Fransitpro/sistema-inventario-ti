<?php
require_once '../config/database.php';

header('Content-Type: application/json');
$action = $_GET['action'] ?? '';

// Maneja la petición para obtener los modelos de una marca
if ($action == 'getModelos' && isset($_GET['id_marca'])) {
    $id_marca = (int)$_GET['id_marca'];
    // Asegurarse de que solo trae modelos ACTIVOS
    $stmt = $conexion->prepare("SELECT id, nombre FROM modelos WHERE id_marca = ? AND estado = 'Activo' ORDER BY nombre");
    $stmt->bind_param("i", $id_marca);
    $stmt->execute();
    $resultado = $stmt->get_result();
    $modelos = $resultado->fetch_all(MYSQLI_ASSOC);
    echo json_encode($modelos);
    $stmt->close();
} 
// Maneja la petición para obtener los cargos de un área
else if ($action == 'getCargos' && isset($_GET['id_area'])) {
    $id_area = (int)$_GET['id_area'];
    // Asegurarse de que solo trae cargos ACTIVOS
    $stmt = $conexion->prepare("SELECT id, nombre FROM cargos WHERE id_area = ? AND estado = 'Activo' ORDER BY nombre");
    $stmt->bind_param("i", $id_area);
    $stmt->execute();
    $resultado = $stmt->get_result();
    $cargos = $resultado->fetch_all(MYSQLI_ASSOC);
    echo json_encode($cargos);
    $stmt->close();
}
// Obtener Empleados por Sucursal
else if ($action == 'getEmpleadosPorSucursal' && isset($_GET['id_sucursal'])) {
    $id_sucursal = (int)$_GET['id_sucursal'];
    $stmt = $conexion->prepare("SELECT id, nombres, apellidos FROM empleados WHERE estado = 'Activo' AND id_sucursal = ? ORDER BY apellidos");
    $stmt->bind_param("i", $id_sucursal);
    $stmt->execute();
    $resultado = $stmt->get_result();
    $empleados = $resultado->fetch_all(MYSQLI_ASSOC);
    echo json_encode($empleados);
    $stmt->close();
}
// Obtener Equipos por Sucursal
else if ($action == 'getEquiposPorSucursal' && isset($_GET['id_sucursal'])) {
    $id_sucursal = (int)$_GET['id_sucursal'];
    $stmt = $conexion->prepare("SELECT e.id, e.codigo_inventario, ma.nombre as marca_nombre, mo.nombre as modelo_nombre 
                                FROM equipos e
                                JOIN marcas ma ON e.id_marca = ma.id
                                JOIN modelos mo ON e.id_modelo = mo.id
                                WHERE e.estado = 'Disponible' AND e.id_sucursal = ? 
                                ORDER BY e.codigo_inventario");
    $stmt->bind_param("i", $id_sucursal);
    $stmt->execute();
    $resultado = $stmt->get_result();
    $equipos = $resultado->fetch_all(MYSQLI_ASSOC);
    echo json_encode($equipos);
    $stmt->close();
}

$conexion->close();