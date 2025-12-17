<?php
session_start();
require_once '../config/database.php';

// Validar sesión
if (!isset($_SESSION['user_id'])) {
    die("Acceso denegado.");
}

// Validar parámetros
if (!isset($_GET['id_asignacion']) || !isset($_GET['tipo'])) {
    die("Parámetros incompletos.");
}

$id_asignacion = (int)$_GET['id_asignacion'];
$tipo = $_GET['tipo']; // 'entrega' o 'devolucion'

// Determinar columna y CARPETA según el tipo
if ($tipo === 'entrega') {
    $columna = 'acta_firmada_path';
    $subcarpeta = 'actas'; // Carpeta para entregas
} elseif ($tipo === 'devolucion') {
    $columna = 'acta_devolucion_path';
    $subcarpeta = 'actas_devolucion'; // Carpeta para devoluciones
} else {
    die("Tipo de acta no válido.");
}

// Obtener nombre del archivo de la BD
$stmt = $conexion->prepare("SELECT $columna FROM asignaciones WHERE id = ?");
$stmt->bind_param("i", $id_asignacion);
$stmt->execute();
$resultado = $stmt->get_result();
$asignacion = $resultado->fetch_assoc();

if (!$asignacion || empty($asignacion[$columna])) {
    die("El acta no ha sido registrada en la base de datos.");
}

$nombre_archivo = $asignacion[$columna];

// RUTA COMPLETA AL ARCHIVO
// Usamos __DIR__ para salir de 'public' y entrar a 'uploads/SUBCARPETA'
$ruta_archivo = __DIR__ . '/../uploads/' . $subcarpeta . '/' . $nombre_archivo;

// Verificar existencia física
if (file_exists($ruta_archivo)) {
    // Forzar descarga o visualización
    $ext = strtolower(pathinfo($ruta_archivo, PATHINFO_EXTENSION));
    
    // Tipos MIME
    $mime_types = [
        'pdf' => 'application/pdf',
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'png' => 'image/png'
    ];
    
    $content_type = $mime_types[$ext] ?? 'application/octet-stream';

    header('Content-Description: File Transfer');
    header('Content-Type: ' . $content_type);
    header('Content-Disposition: inline; filename="'.basename($ruta_archivo).'"');
    header('Expires: 0');
    header('Cache-Control: must-revalidate');
    header('Pragma: public');
    header('Content-Length: ' . filesize($ruta_archivo));
    
    // Limpiar búfer de salida para no corromper el archivo
    ob_clean();
    flush();
    
    readfile($ruta_archivo);
    exit;
} else {
    // Depuración visual
    echo "<div style='font-family: sans-serif; padding: 20px; border: 1px solid #d33; background: #fff0f0; color: #d33;'>";
    echo "<h3>⚠️ Error: Archivo físico no encontrado</h3>";
    echo "<p>El sistema tiene registrado el archivo: <strong>" . htmlspecialchars($nombre_archivo) . "</strong></p>";
    echo "<p>Pero no se encuentra en la ruta: <strong>" . htmlspecialchars($ruta_archivo) . "</strong></p>";
    echo "<p><em>Posible causa: El archivo se subió antes de corregir la ruta de carpetas. Por favor, intente subir el acta nuevamente.</em></p>";
    echo "</div>";
}
?>