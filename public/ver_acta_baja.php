<?php
require_once '../config/database.php';
session_start();

// 1. Validar que el usuario haya iniciado sesión
if (!isset($_SESSION['user_id'])) {
    http_response_code(403);
    die("Acceso denegado.");
}

// 2. Validar los parámetros de la URL
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    http_response_code(400);
    die("Error: ID de baja no válido.");
}

$id_baja = (int)$_GET['id'];

// 3. Obtener el nombre del archivo de la base de datos
$stmt = $conexion->prepare("SELECT acta_baja_path FROM bajas WHERE id = ?"); // OJO: id de la tabla 'bajas', no del equipo
$stmt->bind_param("i", $id_baja);
$stmt->execute();
$resultado = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$resultado || empty($resultado['acta_baja_path'])) {
    die("Error: No se encontró el documento del acta.");
}

$nombre_archivo = basename($resultado['acta_baja_path']);

// 4. Construir la ruta REAL al archivo
// La carpeta es 'uploads/bajas/'
$ruta_completa = __DIR__ . '/../uploads/bajas/' . $nombre_archivo;

// 5. Entregar el archivo
if (file_exists($ruta_completa)) {
    if (ob_get_level()) { ob_end_clean(); }

    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime_type = finfo_file($finfo, $ruta_completa);
    finfo_close($finfo);

    header('Content-Type: ' . $mime_type);
    header('Content-Length: ' . filesize($ruta_completa));
    header('Content-Disposition: inline; filename="' . $nombre_archivo . '"');
    
    readfile($ruta_completa);
    exit;
} else {
    http_response_code(404);
    die("Error: El archivo físico no fue encontrado en el servidor.");
}
?>