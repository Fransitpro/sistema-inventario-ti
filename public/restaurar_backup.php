<?php
require_once '../config/database.php';
session_start();

// Seguridad
if (!isset($_SESSION['user_rol']) || (strtolower($_SESSION['user_rol']) !== 'administrador' && strtolower($_SESSION['user_rol']) !== 'admin')) {
    header("Location: index.php");
    exit();
}

// Aumentar límites de memoria y tiempo para archivos grandes
ini_set('memory_limit', '512M');
set_time_limit(300);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['archivo_sql'])) {
    
    $archivo = $_FILES['archivo_sql'];
    
    // Validar extensión
    $ext = pathinfo($archivo['name'], PATHINFO_EXTENSION);
    if ($ext !== 'sql') {
        header("Location: backup.php?status=error&msg=El archivo debe ser extensión .sql");
        exit();
    }

    if ($archivo['error'] !== UPLOAD_ERR_OK) {
        header("Location: backup.php?status=error&msg=Error al subir el archivo.");
        exit();
    }

    // Leer contenido
    $sql_content = file_get_contents($archivo['tmp_name']);
    
    if (empty($sql_content)) {
        header("Location: backup.php?status=error&msg=El archivo está vacío.");
        exit();
    }

    // Limpiar comentarios para evitar errores de parseo básicos
    $sql_clean = '';
    $lines = explode("\n", $sql_content);
    foreach ($lines as $line) {
        $line = trim($line);
        // Ignorar comentarios y líneas vacías
        if ($line && substr($line, 0, 2) !== '--' && substr($line, 0, 1) !== '#') {
            $sql_clean .= $line . "\n";
        }
    }
    
    // Separar consultas por punto y coma
    $queries = explode(";", $sql_clean);
    
    $conexion->begin_transaction();
    
    try {
        // Desactivar revisión de claves foráneas temporalmente
        $conexion->query("SET FOREIGN_KEY_CHECKS=0");
        
        foreach ($queries as $query) {
            $query = trim($query);
            if (!empty($query)) {
                $conexion->query($query);
            }
        }
        
        $conexion->query("SET FOREIGN_KEY_CHECKS=1");
        $conexion->commit();
        
        header("Location: backup.php?status=success_restore");
        
    } catch (Exception $e) {
        $conexion->rollback();
        // Capturar el mensaje de error de MySQL para mostrarlo
        $errorMsg = "Error SQL: " . $e->getMessage();
        header("Location: backup.php?status=error&msg=" . urlencode($errorMsg));
    }
    
} else {
    header("Location: backup.php");
}
?>