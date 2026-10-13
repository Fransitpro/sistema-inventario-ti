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
ini_set('max_execution_time', '300');
set_time_limit(300);

/**
 * Divide el contenido de un .sql en sentencias individuales respetando:
 * - comillas simples y dobles
 * - escapes con barra invertida
 * - comentarios de línea (--) y de bloque
 * (Un explode(';') ingenuo rompe cualquier valor que contenga ";" o "--",
 *  por ejemplo un nombre de empleado o una descripción de equipo.)
 */
function sql_split($sql)
{
    $statements = [];
    $len = strlen($sql);
    $buf = '';
    $i = 0;
    $inS = false;
    $inD = false;

    while ($i < $len) {
        $ch = $sql[$i];
        $next = ($i + 1 < $len) ? $sql[$i + 1] : '';

        if (!$inS && !$inD) {
            // Comentario de línea: --  o  #
            if (($ch === '-' && $next === '-') || $ch === '#') {
                while ($i < $len && $sql[$i] !== "\n") {
                    $i++;
                }
                continue;
            }
            // Comentario de bloque: /* ... */
            if ($ch === '/' && $next === '*') {
                $i += 2;
                while ($i < $len && !($sql[$i] === '*' && ($sql[$i + 1] ?? '') === '/')) {
                    $i++;
                }
                $i += 2;
                continue;
            }
            if ($ch === ';') {
                $stmt = trim($buf);
                if ($stmt !== '') {
                    $statements[] = $stmt;
                }
                $buf = '';
                $i++;
                continue;
            }
            // Apertura de cadena: a partir de aquí los ";" ya no cortan sentencia
            if ($ch === "'") {
                $inS = true;
            } elseif ($ch === '"') {
                $inD = true;
            }
            $buf .= $ch;
            $i++;
            continue;
        }

        // Dentro de comillas: una barra invertida escapa el carácter siguiente
        if (($inS || $inD) && $ch === '\\') {
            $buf .= $ch . $next;
            $i += 2;
            continue;
        }

        if ($ch === "'") {
            $inS = !$inS;
        } elseif ($ch === '"') {
            $inD = !$inD;
        }
        $buf .= $ch;
        $i++;
    }

    $stmt = trim($buf);
    if ($stmt !== '') {
        $statements[] = $stmt;
    }

    return $statements;
}

function redir_error($mensaje)
{
    header('Location: backup.php?status=error&msg=' . urlencode($mensaje));
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['archivo_sql'])) {

    $archivo = $_FILES['archivo_sql'];

    // Validar que la subida haya sido correcta
    if ($archivo['error'] !== UPLOAD_ERR_OK) {
        $motivos = [
            UPLOAD_ERR_INI_SIZE   => 'El archivo supera el tamaño máximo permitido por el servidor.',
            UPLOAD_ERR_FORM_SIZE  => 'El archivo supera el tamaño máximo permitido por el formulario.',
            UPLOAD_ERR_PARTIAL    => 'El archivo se subió solo parcialmente.',
            UPLOAD_ERR_NO_FILE    => 'No se seleccionó ningún archivo.',
            UPLOAD_ERR_NO_TMP_DIR => 'Falta la carpeta temporal del servidor.',
            UPLOAD_ERR_CANT_WRITE => 'No se pudo escribir el archivo en el disco.',
            UPLOAD_ERR_EXTENSION  => 'Una extensión de PHP bloqueó la subida.',
        ];
        redir_error($motivos[$archivo['error']] ?? 'Error al subir el archivo.');
    }

    // Validar extensión
    $ext = strtolower(pathinfo($archivo['name'], PATHINFO_EXTENSION));
    if ($ext !== 'sql') {
        redir_error('El archivo debe ser extensión .sql');
    }

    if (!is_uploaded_file($archivo['tmp_name'])) {
        redir_error('Archivo subido no válido.');
    }

    // Leer contenido y quitar el BOM si viene de un editor de Windows
    $sql_content = file_get_contents($archivo['tmp_name']);
    if ($sql_content === false) {
        redir_error('No se pudo leer el archivo subido.');
    }
    $sql_content = preg_replace('/^\xEF\xBB\xBF/', '', $sql_content);

    if (trim($sql_content) === '') {
        redir_error('El archivo está vacío.');
    }

    $queries = sql_split($sql_content);

    if (empty($queries)) {
        redir_error('No se encontraron sentencias SQL en el archivo.');
    }

    mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
    $conexion->set_charset('utf8mb4');

    // OJO: DROP TABLE / CREATE TABLE producen DDL, que MySQL ejecuta con
    // autocommits implícitos. Un rollback NO deshace un DROP ya confirmado,
    // por lo que una restauración fallida puede dejar la base a medias.
    // Por eso se registra en qué sentencia falló.
    $fallo = null;

    try {
        $conexion->query('SET FOREIGN_KEY_CHECKS=0');

        foreach ($queries as $idx => $query) {
            try {
                $conexion->query($query);
            } catch (mysqli_sql_exception $e) {
                $resumen = preg_replace('/\s+/', ' ', substr($query, 0, 120));
                $fallo = sprintf(
                    'Fallo en la sentencia %d de %d: %s | Sentencia: %s',
                    $idx + 1,
                    count($queries),
                    $e->getMessage(),
                    $resumen
                );
                throw $e;
            }
        }

        $conexion->query('SET FOREIGN_KEY_CHECKS=1');
    } catch (Throwable $e) {
        if ($fallo === null) {
            $fallo = $e->getMessage();
        }
        redir_error($fallo);
    }

    header('Location: backup.php?status=success_restore');
    exit;
}

header('Location: backup.php');
exit;