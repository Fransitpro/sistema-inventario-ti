<?php
require_once '../config/database.php';
header('Content-Type: application/json');
$response = [];

if (!isset($_GET['id_sucursal']) || !is_numeric($_GET['id_sucursal'])) {
    echo json_encode(['error' => 'ID de sucursal no válido']);
    exit;
}

$id_sucursal = (int)$_GET['id_sucursal'];

$sql = "SELECT e.id, e.dni, e.nombres, e.apellidos, e.id_sucursal,
               a.nombre AS area_nombre, c.nombre AS cargo_nombre
        FROM empleados e
        LEFT JOIN areas a ON e.id_area = a.id
        LEFT JOIN cargos c ON e.id_cargo = c.id
        WHERE e.id_sucursal = ? AND e.estado = 'Activo'
        ORDER BY c.nombre, e.apellidos, e.nombres";
$stmt = $conexion->prepare($sql);

if ($stmt) {
    $stmt->bind_param("i", $id_sucursal);
    if ($stmt->execute()) {
        $resultado = $stmt->get_result();
        while ($fila = $resultado->fetch_assoc()) {
            $fila['area_nombre'] = $fila['area_nombre'] ?: 'Sin área';
            $fila['cargo_nombre'] = $fila['cargo_nombre'] ?: 'Sin cargo';
            $fila['es_jefe_ancash'] = 0;
            $response[] = $fila; // Añadir cada empleado al array
        }
    } else { 
        $response = ['error' => 'Error al ejecutar consulta']; 
    }
    $stmt->close();
} else { 
    $response = ['error' => 'Error al preparar consulta']; 
}

// Jefe Médico de ANCASH: siempre disponible como opción, para cualquier sucursal elegida
$sql_jefe = "SELECT e.id, e.dni, e.nombres, e.apellidos, e.id_sucursal,
                    a.nombre AS area_nombre, c.nombre AS cargo_nombre
             FROM empleados e
             INNER JOIN sucursales s ON s.id = e.id_sucursal
             LEFT JOIN areas a ON e.id_area = a.id
             LEFT JOIN cargos c ON e.id_cargo = c.id
             WHERE s.nombre LIKE '%ANCASH%' AND c.nombre = 'Jefe Médico' AND e.estado = 'Activo'
             ORDER BY e.id LIMIT 1";
$jefe_ancash = $conexion->query($sql_jefe);

if ($jefe_ancash && $fila_jefe = $jefe_ancash->fetch_assoc()) {
    $fila_jefe['area_nombre'] = $fila_jefe['area_nombre'] ?: 'Sin área';
    $fila_jefe['cargo_nombre'] = $fila_jefe['cargo_nombre'] ?: 'Sin cargo';
    $fila_jefe['es_jefe_ancash'] = 1;

    $posicion = null;
    foreach ($response as $i => $emp) {
        if ((int)$emp['id'] === (int)$fila_jefe['id']) { $posicion = $i; break; }
    }

    if ($posicion === null) {
        array_unshift($response, $fila_jefe); // No está en la sucursal elegida: se agrega al inicio
    } else {
        $response[$posicion]['es_jefe_ancash'] = 1;
    }
}

echo json_encode($response);
?>