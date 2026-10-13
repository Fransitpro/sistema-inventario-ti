<?php
// Migración: congela el código de inventario original (columna "Código Inventario 2025").
//
// Hasta ahora, las columnas "Código Inventario 2025" y "Código Inventario 2026"
// del listado leían el mismo campo equipos.codigo_inventario, por lo que al
// editar el código cambiaban las dos. Con esta migración el valor original
// queda guardado aparte y ya no se modifica.
//
// Es idempotente: se puede ejecutar varias veces sin duplicar nada.
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/../config/database.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$mensajes = [];

$columna = 'codigo_inventario_2025';

// 1. Crear la columna si no existe.
//    MySQL no admite "ADD COLUMN IF NOT EXISTS" (eso es de MariaDB), así que
//    se consulta primero el esquema y solo se altera si hace falta.
try {
    $chk = $conexion->prepare(
        "SELECT COUNT(*) AS n FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'equipos' AND COLUMN_NAME = ?"
    );
    $chk->bind_param("s", $columna);
    $chk->execute();
    $existe = ((int)$chk->get_result()->fetch_assoc()['n'] > 0);
    $chk->close();

    if ($existe) {
        $mensajes[] = "La columna `$columna` ya existía.";
    } else {
        $conexion->query("ALTER TABLE equipos ADD COLUMN `$columna` VARCHAR(50) NULL AFTER `codigo_inventario`");
        $mensajes[] = "Columna `$columna` creada.";
    }
} catch (Throwable $e) {
    $mensajes[] = "ERROR al crear la columna: " . $e->getMessage();
    $columnaOk = false;
}
$columnaOk = !empty($columnaOk) || empty(array_filter($mensajes, function ($m) {
    return strpos($m, 'ERROR') === 0;
}));

// 2. Rellenar con el código actual solo donde esté vacío
//    (así se congela el valor vigente, no se pisa uno ya congelado)
try {
    $stmt = $conexion->prepare(
        "UPDATE equipos SET `$columna` = codigo_inventario
         WHERE (`$columna` IS NULL OR `$columna` = '')
           AND codigo_inventario IS NOT NULL AND codigo_inventario <> ''"
    );
    $stmt->execute();
    $mensajes[] = "Equipos con código congelado: " . $stmt->affected_rows;
    $stmt->close();
} catch (Throwable $e) {
    $mensajes[] = "Error al rellenar la columna: " . $e->getMessage();
}

// 3. Resumen
$res = $conexion->query(
    "SELECT COUNT(*) AS total,
            SUM(codigo_inventario_2025 IS NOT NULL AND codigo_inventario_2025 <> '') AS congelados
     FROM equipos"
);
$fila = $res->fetch_assoc();
$res->free();

$mensajes[] = "Total equipos: {$fila['total']} | congelados: {$fila['congelados']}";
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Migración código de inventario 2025</title>
</head>
<body style="font-family: Segoe UI, sans-serif; padding: 2rem;">
    <h2>Migración: congelar Código Inventario 2025</h2>
    <ul>
        <?php foreach ($mensajes as $m): ?>
            <li><?php echo htmlspecialchars($m); ?></li>
        <?php endforeach; ?>
    </ul>
    <p><a href="asignaciones.php">Volver al listado de asignaciones</a></p>
</body>
</html>