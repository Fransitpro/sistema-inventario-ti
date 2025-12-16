<?php
require_once '../config/database.php';
session_start();

// 1. Validar que el usuario haya iniciado sesión
if (!isset($_SESSION['user_id'])) {
    http_response_code(403); // Prohibido
    die("Acceso denegado.");
}

// 2. Validar los parámetros de la URL
if (!isset($_GET['id_asig']) || !is_numeric($_GET['id_asig']) || !isset($_GET['img'])) {
    http_response_code(400); // Solicitud incorrecta
    die("Error: Faltan parámetros.");
}

$id_asignacion = (int)$_GET['id_asig'];
$nombre_imagen = basename($_GET['img']); // 'basename()' es una medida de seguridad

// 3. Validar que la imagen solicitada realmente pertenezca a esa asignación
// (Esto evita que alguien cambie los números en la URL y vea fotos de otras devoluciones)
$stmt_check = $conexion->prepare("SELECT 1 FROM asignaciones WHERE id = ? AND 
    (imagen_devolucion_1 = ? OR imagen_devolucion_2 = ? OR imagen_devolucion_3 = ?)");
$stmt_check->bind_param("isss", $id_asignacion, $nombre_imagen, $nombre_imagen, $nombre_imagen);
$stmt_check->execute();
$resultado_check = $stmt_check->get_result();

if ($resultado_check->num_rows === 0) {
    http_response_code(404); // No encontrado
    die("Error: No se encontró la imagen o no pertenece a esta asignación.");
}
$stmt_check->close();

// 4. Construir la ruta REAL y SEGURA al archivo en el servidor
// __DIR__ es C:\laragon\www\inventario_ti\public
// La ruta será: C:\laragon\www\inventario_ti\uploads\devoluciones\[ID]\[IMAGEN]
$ruta_completa = __DIR__ . '/../uploads/devoluciones/' . $id_asignacion . '/' . $nombre_imagen;

// 5. Verificar que el archivo exista y entregarlo al navegador
if (file_exists($ruta_completa)) {
    // Limpiar cualquier salida de buffer anterior
    if (ob_get_level()) { ob_end_clean(); }

    // Determinar el tipo de contenido (ej. 'image/jpeg', 'image/png')
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime_type = finfo_file($finfo, $ruta_completa);
    finfo_close($finfo);

    // Enviar cabeceras
    header('Content-Type: ' . $mime_type);
    header('Content-Length: ' . filesize($ruta_completa));
    // 'inline' muestra la imagen en el navegador
    header('Content-Disposition: inline; filename="' . $nombre_imagen . '"'); 
    
    // Leer y enviar el archivo
    readfile($ruta_completa);
    exit;
} else {
    http_response_code(404);
    die("Error: El archivo físico no fue encontrado en el servidor.");
}
?>