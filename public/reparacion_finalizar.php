<?php
require_once '../templates/header.php';

// --- Lógica para procesar el formulario cuando se envía (POST) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id_reparacion = $_POST['id_reparacion'];
    $id_equipo = $_POST['id_equipo'];
    $fecha_salida = $_POST['fecha_salida'];
    // Leer el costo, si está vacío o no es un número, guardarlo como 0.00
    $costo = !empty($_POST['costo']) && is_numeric($_POST['costo']) ? (float)$_POST['costo'] : 0.00;
    $observaciones_salida = trim($_POST['observaciones_salida'] ?? '');

    $conexion->begin_transaction();
    try {
        // 1. Actualizar el registro en la tabla 'reparaciones'
        $stmt_rep = $conexion->prepare("UPDATE reparaciones SET fecha_salida = ?, costo = ?, observaciones_salida = ?, estado_reparacion = 'Finalizada' WHERE id = ?");
        $stmt_rep->bind_param("sdsi", $fecha_salida, $costo, $observaciones_salida, $id_reparacion);
        $stmt_rep->execute();

        // 2. Actualizar el estado del equipo en la tabla 'equipos' a 'Disponible'
        $stmt_eq = $conexion->prepare("UPDATE equipos SET estado = 'Disponible' WHERE id = ?");
        $stmt_eq->bind_param("i", $id_equipo);
        $stmt_eq->execute();

        $conexion->commit();
        header("Location: reparaciones.php?status=reparacion_finalizada");
        exit();
    } catch (mysqli_sql_exception $exception) {
        $conexion->rollback();
        // Mostrar un mensaje de error más claro
        $error_message = "Error al finalizar la reparación: " . $exception->getMessage();
    }
}

// --- Lógica para mostrar el formulario con los datos (GET) ---
if (!isset($_GET['id_reparacion']) || !is_numeric($_GET['id_reparacion'])) {
    echo '<div class="alert alert-danger">Error: ID de reparación no válido.</div>';
    require_once '../templates/footer.php';
    exit();
}
$id_reparacion = (int)$_GET['id_reparacion'];

$sql_data = "SELECT r.*, e.codigo_inventario 
             FROM reparaciones r 
             JOIN equipos e ON r.id_equipo = e.id
             WHERE r.id = ?";
$stmt = $conexion->prepare($sql_data);
$stmt->bind_param("i", $id_reparacion);
$stmt->execute();
$reparacion = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$reparacion || $reparacion['estado_reparacion'] !== 'En Proceso') {
    echo '<div class="alert alert-warning">Reparación no encontrada o ya ha sido finalizada.</div>';
    require_once '../templates/footer.php';
    exit();
}
?>

<h1 class="h2 mb-4">Finalizar Proceso de Reparación</h1>

<?php if (isset($error_message)): ?>
    <div class="alert alert-danger"><?php echo htmlspecialchars($error_message); ?></div>
<?php endif; ?>

<div class="card">
    <div class="card-header">
        Registrar Finalización para el Equipo: <?php echo htmlspecialchars($reparacion['codigo_inventario']); ?>
    </div>
    <div class="card-body">
        <div class="mb-4 p-3 bg-light border rounded">
            <h5>Detalles del Ingreso:</h5>
            <p class="mb-1"><strong>Fecha de Ingreso:</strong> <?php echo date('d/m/Y', strtotime($reparacion['fecha_ingreso'])); ?></p>
            <p class="mb-0"><strong>Motivo:</strong> <?php echo htmlspecialchars($reparacion['motivo']); ?></p>
        </div>

        <form action="reparacion_finalizar.php?id_reparacion=<?php echo $id_reparacion; ?>" method="POST">
            <input type="hidden" name="id_reparacion" value="<?php echo $id_reparacion; ?>">
            <input type="hidden" name="id_equipo" value="<?php echo $reparacion['id_equipo']; ?>">
            
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label for="fecha_salida" class="form-label">Fecha de Salida / Retorno *</label>
                    <input type="date" class="form-control" id="fecha_salida" name="fecha_salida" value="<?php echo date('Y-m-d'); ?>" required>
                </div>
                
                <div class="col-md-6 mb-3">
                    <label for="costo" class="form-label">Costo Final de la Reparación</label>
                    <div class="input-group">
                        <span class="input-group-text">
                            <?php echo htmlspecialchars($_SESSION['configuracion']['moneda_simbolo'] ?? 'S/'); ?>
                        </span>
                        <input type="number" step="0.01" class="form-control" id="costo" name="costo" placeholder="0.00" value="<?php echo htmlspecialchars($reparacion['costo'] > 0 ? $reparacion['costo'] : ''); ?>">
                    </div>
                </div>
            </div>
            
            <div class="mb-3">
                <label for="observaciones_salida" class="form-label">Observaciones de la Solución / Trabajo Realizado *</label>
                <textarea class="form-control" id="observaciones_salida" name="observaciones_salida" rows="4" required></textarea>
            </div>
            
            <hr>
            
            <div class="d-flex justify-content-end gap-2">
                <a href="reparaciones.php" class="btn btn-secondary">Cancelar</a>
                <button type="submit" class="btn btn-success">Guardar y Finalizar Reparación</button>
            </div>
        </form>
    </div>
</div>

<?php require_once '../templates/footer.php'; ?>