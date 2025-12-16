<?php
require_once '../config/database.php';
session_start();

// 1. Validar que el usuario haya iniciado sesión
if (!isset($_SESSION['user_id'])) {
    die("Acceso denegado. Por favor, inicie sesión.");
}

// 2. Validar los parámetros de la URL
if (!isset($_GET['id_asignacion']) || !is_numeric($_GET['id_asignacion']) || !isset($_GET['tipo'])) {
    die("Error: Faltan parámetros para descargar el archivo.");
}
$id_asignacion = (int)$_GET['id_asignacion'];
$tipo_acta = $_GET['tipo']; // Ej: 'entrega' o 'devolucion'

// 3. Determinar la columna y la subcarpeta correctas
if ($tipo_acta === 'entrega') {
    $columna_db = 'acta_firmada_path';
    $subcarpeta = 'actas';
} elseif ($tipo_acta === 'devolucion') {
    $columna_db = 'acta_devolucion_path';
    $subcarpeta = 'actas_devolucion';
} else {
    die("Tipo de acta no válido.");
}

// 4. Obtener el nombre del archivo de la base de datos
$stmt = $conexion->prepare("SELECT `$columna_db` FROM asignaciones WHERE id = ?");
$stmt->bind_param("i", $id_asignacion);
$stmt->execute();
$resultado = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$resultado || empty($resultado[$columna_db])) {
    die("Error: No se encontró el registro del acta en la base de datos.");
}
$nombre_archivo = $resultado[$columna_db];

// 5. Construir la ruta real y segura en el servidor
// __DIR__ es C:\laragon\www\inventario_ti\public
// ../uploads/ es C:\laragon\www\inventario_ti\uploads\
$ruta_completa_servidor = __DIR__ . '/../uploads/' . $subcarpeta . '/' . basename($nombre_archivo);

// 6. Verificar que el archivo existe y entregarlo al navegador
if (file_exists($ruta_completa_servidor)) {
    // Limpiar cualquier salida de buffer anterior
    if (ob_get_level()) {
        ob_end_clean();
    }
    
    // Enviar cabeceras
    header('Content-Type: application/pdf'); // Asumimos que es PDF
    header('Content-Disposition: inline; filename="' . basename($nombre_archivo) . '"'); // 'inline' para abrir en navegador, 'attachment' para forzar descarga
    header('Content-Length: ' . filesize($ruta_completa_servidor));
    header('Accept-Ranges: bytes');
    
    // Leer y enviar el archivo
    readfile($ruta_completa_servidor);
    exit;
} else {
    die("Error: El archivo físico no fue encontrado en el servidor.");
}
?>