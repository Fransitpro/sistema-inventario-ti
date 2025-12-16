<?php
// Configuración de la base de datos
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'inventario_ti');

// Crear la conexión con MySQLi
$conexion = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

// Verificar la conexión
if ($conexion->connect_error) {
    die("Error de Conexión: " . $conexion->connect_error);
}

// Establecer el charset a UTF-8
$conexion->set_charset("utf8mb4");