<?php
// Configuración de la base de datos
// Las variables de entorno tienen prioridad sobre los valores por defecto.
define('DB_HOST', getenv('DB_HOST') ?: 'localhost');
define('DB_USER', getenv('DB_USER') ?: 'root');
define('DB_PASS', getenv('DB_PASS') !== false ? getenv('DB_PASS') : 'admin');
define('DB_NAME', getenv('DB_NAME') ?: 'inventario_ti');

// Crear la conexión con MySQLi
try {
    $conexion = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
} catch (mysqli_sql_exception $e) {
    die('Error de Conexión: ' . $e->getMessage());
}

// Establecer el charset a UTF-8
$conexion->set_charset('utf8mb4');