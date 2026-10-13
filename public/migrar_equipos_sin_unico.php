<?php
// Migración: elimina restricciones UNIQUE de la tabla equipos para permitir
// valores repetidos como S/S en codigo_inventario, cod_pat y numero_serie.
// Uso: abrir esta URL una vez y luego ELIMINAR este archivo.

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once '../config/database.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$columnas = ['codigo_inventario', 'cod_pat', 'numero_serie'];
$log = [];

function obtener_indices(mysqli $conexion) {
    $indices = [];
    $res = $conexion->query("SHOW INDEX FROM equipos");
    if ($res) {
        while ($i = $res->fetch_assoc()) {
            if ((int) $i['Non_unique'] === 0) {
                $indices[$i['Key_name']][] = $i['Column_name'];
            }
        }
    }
    return $indices;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $indices = obtener_indices($conexion);

    foreach ($indices as $nombre => $cols) {
        // Solo se eliminan índices UNIQUE de una sola columna (no claves primarias)
        if (count($cols) !== 1 || !in_array($cols[0], $columnas, true)) {
            $log[] = "Se conservo el indice UNIQUE '$nombre' (" . implode(', ', $cols) . ").";
            continue;
        }

        if ($conexion->query("ALTER TABLE equipos DROP INDEX `" . $conexion->real_escape_string($nombre) . "`")) {
            $log[] = "Indice UNIQUE '$nombre' eliminado en la columna " . $cols[0] . ".";
        } else {
            $log[] = "Error al eliminar '$nombre': " . $conexion->error;
        }
    }

    if (!$log) {
        $log[] = "No habia indices UNIQUE sobre " . implode(', ', $columnas) . ".";
    }
}

$indices_actuales = obtener_indices($conexion);

require_once '../templates/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h2">Restricciones de la tabla equipos</h1>
    <a href="equipos.php" class="btn btn-secondary"><i class="bi bi-arrow-left me-2"></i> Volver</a>
</div>

<div class="card shadow-sm border-0">
    <div class="card-body p-4">
        <p class="text-muted">
            Esta herramienta quita los indices <strong>UNIQUE</strong> de
            <code>codigo_inventario</code>, <code>cod_pat</code> y <code>numero_serie</code>
            para que valores como <strong>S/S</strong> puedan repetirse sin error.
        </p>

        <form method="POST" onsubmit="return confirm('¿Continuar con la modificacion?');">
            <button type="submit" class="btn btn-warning">
                <i class="bi bi-unlock me-2"></i> Quitar restricciones UNIQUE
            </button>
        </form>

        <?php if (!empty($log)): ?>
            <hr>
            <h6>Resultado</h6>
            <ul class="list-group mb-3">
                <?php foreach ($log as $linea): ?>
                    <li class="list-group-item"><?php echo htmlspecialchars($linea); ?></li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>

        <h6>Indices UNIQUE actuales en <code>equipos</code></h6>
        <?php if ($indices_actuales): ?>
            <ul class="list-group">
                <?php foreach ($indices_actuales as $nombre => $cols): ?>
                    <li class="list-group-item">
                        <code><?php echo htmlspecialchars($nombre); ?></code>
                        &rarr; <?php echo htmlspecialchars(implode(', ', $cols)); ?>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php else: ?>
            <div class="alert alert-success mb-0">
                No hay indices UNIQUE: los codigos se pueden repetir libremente.
            </div>
        <?php endif; ?>
    </div>
</div>

<?php require_once '../templates/footer.php'; ?>
