<?php
// Migración: agrega el campo "Estado del Bien" (Bueno / Regular / Malo).
//
// Es un campo NUEVO e independiente de equipos.estado, que sigue siendo el
// estado operativo del equipo (Disponible, Asignado, En reparación, De baja)
// y que usa toda la lógica de asignación, reparaciones y bajas.
//
// Idempotente: se puede ejecutar varias veces sin duplicar nada.
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/../config/database.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$mensajes = [];
$columna = 'estado_bien';

// 1. Crear la columna si no existe.
//    MySQL no admite "ADD COLUMN IF NOT EXISTS" (eso es de MariaDB),
//    así que se consulta el esquema antes de alterar.
$existe = false;
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
        // NULL = todavía sin evaluar. No se rellena con 'Bueno' a ciegas
        // porque sería inventar el estado de 40 equipos.
        $conexion->query(
            "ALTER TABLE equipos ADD COLUMN `$columna` ENUM('Bueno','Regular','Malo') NULL DEFAULT NULL AFTER `estado`"
        );
        $mensajes[] = "Columna `$columna` creada (Bueno / Regular / Malo).";
    }
} catch (Throwable $e) {
    $mensajes[] = "ERROR al crear la columna: " . $e->getMessage();
}

// 2. Resumen
if ($existe) {
    $res = $conexion->query(
        "SELECT COUNT(*) AS total,
                SUM(estado_bien IS NOT NULL) AS evaluados,
                SUM(estado_bien IS NULL) AS sin_evaluar
         FROM equipos"
    );
    $fila = $res->fetch_assoc();
    $res->free();
    $mensajes[] = "Total equipos: {$fila['total']} | con estado del bien: {$fila['evaluados']} | sin evaluar: {$fila['sin_evaluar']}";
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Migración estado del bien</title>
</head>
<body style="font-family: Segoe UI, sans-serif; padding: 2rem;">
    <h2>Migración: campo Estado del Bien</h2>
    <ul>
        <?php foreach ($mensajes as $m): ?>
            <li><?php echo htmlspecialchars($m); ?></li>
        <?php endforeach; ?>
    </ul>
    <p><a href="equipos.php">Volver a Equipos</a></p>
</body>
</html>