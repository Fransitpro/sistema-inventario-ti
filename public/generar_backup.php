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

// Configuración DB
$host = "localhost"; 
$user = "root";      
$pass = "";          
$name = "inventario_ti"; 

$mysqli = new mysqli($host, $user, $pass, $name);
if ($mysqli->connect_error) {
    die("Error de conexión");
}
$mysqli->select_db($name);
$mysqli->query("SET NAMES 'utf8'");

$fecha = date('Y-m-d_H-i-s');
$filename = "backup_inventario_{$fecha}.sql";

// Headers para descarga
header('Content-Type: application/octet-stream');
header("Content-Transfer-Encoding: Binary");
header("Content-disposition: attachment; filename=\"".$filename."\"");

// Cabecera del SQL
echo "-- RESPALDO SISTEMA INVENTARIO TI\n";
echo "-- Fecha: " . date('d/m/Y H:i:s') . "\n";
echo "-- Generado por: " . ($_SESSION['user_nombre'] ?? 'Admin') . "\n\n";
echo "SET FOREIGN_KEY_CHECKS=0;\n\n";

// Obtener tablas
$tables = array();
$result = $mysqli->query('SHOW TABLES');
while($row = $result->fetch_row()) {
    $tables[] = $row[0];
}

// Recorrer tablas
foreach($tables as $table) {
    $result = $mysqli->query('SELECT * FROM '.$table);
    $num_fields = $result->field_count;

    echo "-- Estructura de tabla `$table`\n";
    echo "DROP TABLE IF EXISTS `$table`;\n";
    $row2 = $mysqli->query('SHOW CREATE TABLE '.$table)->fetch_row();
    echo $row2[1].";\n\n";

    echo "-- Datos de tabla `$table`\n";
    for ($i = 0; $i < $num_fields; $i++) {
        while($row = $result->fetch_row()) {
            echo "INSERT INTO `$table` VALUES(";
            for($j=0; $j < $num_fields; $j++) {
                
                // CORRECCIÓN CRÍTICA: Manejo de NULL
                if (!isset($row[$j]) || is_null($row[$j])) {
                    echo "NULL";
                } else {
                    // Solo aplicamos addslashes si hay un valor string
                    $row[$j] = addslashes($row[$j]);
                    $row[$j] = str_replace("\n","\\n",$row[$j]);
                    echo '"' . $row[$j] . '"';
                }
                
                if ($j < ($num_fields-1)) { echo ','; }
            }
            echo ");\n";
        }
    }
    echo "\n\n";
}

echo "SET FOREIGN_KEY_CHECKS=1;\n";
exit;
?>