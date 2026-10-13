<?php
// Desactivar reporte de errores en pantalla para no corromper el archivo SQL
error_reporting(0);

require_once '../config/database.php';
session_start();

// Seguridad
if (!isset($_SESSION['user_rol']) || (strtolower($_SESSION['user_rol']) !== 'administrador' && strtolower($_SESSION['user_rol']) !== 'admin')) {
    header("Location: index.php");
    exit();
}

// $conexion ya viene abierta y con utf8mb4 desde config/database.php
mysqli_report(MYSQLI_REPORT_OFF);

// La conexión va primero: si falla, no se envían cabeceras de descarga.
if ($conexion->connect_errno) {
    header('Content-Type: text/plain; charset=utf-8');
    die('Error de conexión: ' . $conexion->connect_error);
}

mysqli_set_charset($conexion, 'utf8mb4');

$name = DB_NAME;
$fecha = date('Y-m-d_H-i-s');
$filename = "backup_inventario_{$fecha}.sql";

// Descarga por streaming: se van vaciando bloques para no agotar la memoria.
if (function_exists('ob_get_level')) {
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
}
header('Content-Type: application/sql; charset=utf-8');
header('Content-Transfer-Encoding: Binary');
header("Content-Disposition: attachment; filename=\"{$filename}\"");

/**
 * Escapa un valor para insertarlo como literal de cadena.
 * mysqli_real_escape_string ya cubre \ ' " \n \r \0 y \x1a,
 * por lo que ya no se debe aplicar str_replace/addslashes encima.
 */
function sql_literal($mysqli, $value)
{
    if ($value === null) {
        return 'NULL';
    }
    return "'" . mysqli_real_escape_string($mysqli, $value) . "'";
}

// Cabecera del SQL
echo "-- RESPALDO SISTEMA INVENTARIO TI\n";
echo "-- Base de datos: {$name}\n";
echo "-- Fecha: " . date('d/m/Y H:i:s') . "\n";
echo "-- Generado por: " . ($_SESSION['user_nombre'] ?? 'Admin') . "\n";
echo "-- Servidor: " . $conexion->server_info . "\n\n";
echo "SET NAMES utf8mb4;\n";
echo "SET FOREIGN_KEY_CHECKS=0;\n\n";

// Obtener tablas
$tables = [];
$result = $conexion->query('SHOW TABLES');
if ($result === false) {
    exit("\n-- ERROR: no se pudieron listar las tablas: " . $conexion->error . "\n");
}
while ($row = $result->fetch_row()) {
    $tables[] = $row[0];
}

foreach ($tables as $table) {
    $tabla_escapada = '`' . str_replace('`', '``', $table) . '`';

    $res_estructura = $conexion->query('SHOW CREATE TABLE ' . $tabla_escapada);
    if ($res_estructura === false) {
        echo "-- ERROR: no se pudo leer la estructura de {$table}: " . $conexion->error . "\n\n";
        continue;
    }
    $create = $res_estructura->fetch_row();

    echo "-- Estructura de tabla {$tabla_escapada}\n";
    echo "DROP TABLE IF EXISTS {$tabla_escapada};\n";
    echo $create[1] . ";\n\n";

    $result = $conexion->query('SELECT * FROM ' . $tabla_escapada);
    if ($result === false) {
        echo "-- ERROR: no se pudieron leer los datos de {$table}: " . $conexion->error . "\n\n";
        continue;
    }
    $num_fields = $result->field_count;

    echo "-- Datos de tabla {$tabla_escapada}\n";
    while ($row = $result->fetch_row()) {
        $valores = [];
        for ($j = 0; $j < $num_fields; $j++) {
            $valores[] = sql_literal($conexion, $row[$j]);
        }
        echo "INSERT INTO {$tabla_escapada} VALUES(" . implode(',', $valores) . ");\n";
    }
    $result->free();

    echo "\n\n";
}

echo "SET FOREIGN_KEY_CHECKS=1;\n";
exit;