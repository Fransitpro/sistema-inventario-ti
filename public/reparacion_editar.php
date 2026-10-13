<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}
require_once '../config/database.php';

$id_reparacion = (int) ($_GET['id_reparacion'] ?? $_POST['id_reparacion'] ?? 0);
if ($id_reparacion <= 0) {
    header("Location: reparaciones.php");
    exit();
}

$estados_permitidos = ['En Proceso', 'Finalizada'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $estado_origen = trim($_POST['estado_origen'] ?? '');
    $estado_filtro = trim($_POST['estado_filtro'] ?? 'En Proceso');
    $fecha_ingreso = trim($_POST['fecha_ingreso'] ?? '');
    $fecha_salida = trim($_POST['fecha_salida'] ?? '');
    $motivo = trim($_POST['motivo'] ?? '');
    $proveedor = trim($_POST['proveedor_servicio'] ?? '');
    $costo_texto = trim($_POST['costo'] ?? '');
    $observaciones = trim($_POST['observaciones_salida'] ?? '');
    $estado = trim($_POST['estado_reparacion'] ?? '');

    if ($estado === 'Finalizada' && $fecha_salida === '') {
        $fecha_salida = date('Y-m-d');
    }

    $errores = [];
    if ($fecha_ingreso === '') {
        $errores[] = "Complete la fecha de ingreso.";
    }
    if ($motivo === '') {
        $errores[] = "Complete el motivo de la reparación.";
    }
    if (!in_array($estado, $estados_permitidos, true)) {
        $errores[] = "Seleccione un estado válido.";
    }
    if ($fecha_salida !== '' && $fecha_ingreso !== '' && $fecha_salida < $fecha_ingreso) {
        $errores[] = "La fecha de salida no puede ser anterior a la fecha de ingreso.";
    }
    if ($costo_texto !== '' && (!is_numeric($costo_texto) || (float) $costo_texto < 0)) {
        $errores[] = "El costo debe ser un número mayor o igual a 0.";
    }
    $costo = $costo_texto !== '' ? (float) $costo_texto : 0.00;

    if (empty($errores)) {
        $conexion->begin_transaction();
        try {
            $stmt = $conexion->prepare("SELECT id_equipo FROM reparaciones WHERE id = ?");
            $stmt->bind_param("i", $id_reparacion);
            $stmt->execute();
            $reparacion = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            if (!$reparacion) {
                throw new RuntimeException("La reparación no existe.");
            }
            $id_equipo = (int) $reparacion['id_equipo'];

            $salida = $fecha_salida !== '' ? $fecha_salida : null;

            $stmt = $conexion->prepare("UPDATE reparaciones
                                        SET fecha_ingreso = ?, fecha_salida = ?, motivo = ?, proveedor_servicio = ?,
                                            costo = ?, observaciones_salida = ?, estado_reparacion = ?
                                        WHERE id = ?");
            $stmt->bind_param("ssssdssi", $fecha_ingreso, $salida, $motivo, $proveedor, $costo, $observaciones, $estado, $id_reparacion);
            $stmt->execute();
            $stmt->close();

            // Sincroniza el estado del equipo, salvo que ya esté dado de baja.
            if ($estado !== $estado_origen) {
                $stmt_estado = $conexion->prepare("UPDATE equipos SET estado = ? WHERE id = ? AND estado <> 'De Baja'");
                $nuevo_estado_equipo = ($estado === 'Finalizada') ? 'Disponible' : 'En Reparacion';
                $stmt_estado->bind_param("si", $nuevo_estado_equipo, $id_equipo);
                $stmt_estado->execute();
                $stmt_estado->close();
            }

            $conexion->commit();

            $destino = "reparaciones.php?estado=" . urlencode($estado_filtro) . "&msg=guardada";
            header("Location: $destino");
            exit();
        } catch (Throwable $e) {
            $conexion->rollback();
            $errores[] = "Error al guardar la reparación: " . $e->getMessage();
        }
    }
}

$sql = "SELECT r.*, e.codigo_inventario, e.cod_pat, e.estado AS estado_equipo,
                ma.nombre AS marca_nombre, mo.nombre AS modelo_nombre
        FROM reparaciones r
        JOIN equipos e ON r.id_equipo = e.id
        LEFT JOIN marcas ma ON e.id_marca = ma.id
        LEFT JOIN modelos mo ON e.id_modelo = mo.id
        WHERE r.id = ?";
$stmt = $conexion->prepare($sql);
$stmt->bind_param("i", $id_reparacion);
$stmt->execute();
$reparacion = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$reparacion) {
    header("Location: reparaciones.php");
    exit();
}

require_once '../templates/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h2">Editar Reparación</h1>
    <a href="reparaciones.php?estado=<?php echo urlencode($_POST['estado_filtro'] ?? $_GET['estado'] ?? 'En Proceso'); ?>" class="btn btn-secondary">
        <i class="bi bi-arrow-left me-2"></i> Volver al reporte
    </a>
</div>

<?php if (!empty($errores)): ?>
    <div class="alert alert-danger alert-dismissible fade show shadow-sm" role="alert">
        <ul class="mb-0">
            <?php foreach ($errores as $error): ?>
                <li><?php echo htmlspecialchars($error); ?></li>
            <?php endforeach; ?>
        </ul>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<div class="card shadow-sm border-0">
    <div class="card-header bg-white py-3">
        <h5 class="mb-0 text-primary fw-bold"><i class="bi bi-pencil-square me-2"></i> Datos de la Reparación</h5>
    </div>
    <div class="card-body p-4">

        <div class="row g-3 mb-4">
            <div class="col-md-3">
                <label class="form-label">Código Patrimonial</label>
                <input type="text" class="form-control" value="<?php echo htmlspecialchars($reparacion['cod_pat'] ?: 'N/A'); ?>" disabled>
            </div>
            <div class="col-md-3">
                <label class="form-label">Código Inventario</label>
                <input type="text" class="form-control" value="<?php echo htmlspecialchars($reparacion['codigo_inventario']); ?>" disabled>
            </div>
            <div class="col-md-3">
                <label class="form-label">Marca / Modelo</label>
                <input type="text" class="form-control" value="<?php echo htmlspecialchars(($reparacion['marca_nombre'] ?: 'N/A') . ' / ' . ($reparacion['modelo_nombre'] ?: 'N/A')); ?>" disabled>
            </div>
            <div class="col-md-3">
                <label class="form-label">Estado del equipo</label>
                <input type="text" class="form-control" value="<?php echo htmlspecialchars($reparacion['estado_equipo'] === 'En Reparacion' ? 'En Reparación' : $reparacion['estado_equipo']); ?>" disabled>
            </div>
        </div>

        <form action="reparacion_editar.php?id_reparacion=<?php echo $id_reparacion; ?>" method="POST" autocomplete="off">
            <input type="hidden" name="id_reparacion" value="<?php echo $id_reparacion; ?>">
            <input type="hidden" name="estado_origen" value="<?php echo htmlspecialchars($reparacion['estado_reparacion']); ?>">
            <input type="hidden" name="estado_filtro" value="<?php echo htmlspecialchars($_POST['estado_filtro'] ?? $_GET['estado'] ?? 'En Proceso'); ?>">

            <div class="row g-3">
                <div class="col-md-4">
                    <label class="form-label fw-bold" for="fecha_ingreso">Fecha de Ingreso <span class="text-danger">*</span></label>
                    <input type="date" class="form-control" id="fecha_ingreso" name="fecha_ingreso" required
                           value="<?php echo htmlspecialchars($_POST['fecha_ingreso'] ?? $reparacion['fecha_ingreso']); ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label fw-bold" for="fecha_salida">Fecha de Salida</label>
                    <input type="date" class="form-control" id="fecha_salida" name="fecha_salida"
                           value="<?php echo htmlspecialchars($_POST['fecha_salida'] ?? ($reparacion['fecha_salida'] ?? '')); ?>">
                    <div class="form-text">Si marca el estado como <strong>Finalizada</strong> y la deja vacía, se usa la fecha de hoy.</div>
                </div>
                <div class="col-md-4">
                    <label class="form-label fw-bold" for="estado_reparacion">Estado de la Reparación <span class="text-danger">*</span></label>
                    <select class="form-select" id="estado_reparacion" name="estado_reparacion" required>
                        <?php foreach ($estados_permitidos as $estado_opcion): ?>
                            <option value="<?php echo $estado_opcion; ?>" <?php echo ($_POST['estado_reparacion'] ?? $reparacion['estado_reparacion']) === $estado_opcion ? 'selected' : ''; ?>>
                                <?php echo $estado_opcion; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-12">
                    <label class="form-label fw-bold" for="motivo">Motivo de la reparación <span class="text-danger">*</span></label>
                    <textarea class="form-control" id="motivo" name="motivo" rows="3" required><?php echo htmlspecialchars($_POST['motivo'] ?? $reparacion['motivo']); ?></textarea>
                </div>

                <div class="col-md-8">
                    <label class="form-label fw-bold" for="proveedor_servicio">Proveedor de Servicio</label>
                    <input type="text" class="form-control" id="proveedor_servicio" name="proveedor_servicio"
                           value="<?php echo htmlspecialchars($_POST['proveedor_servicio'] ?? ($reparacion['proveedor_servicio'] ?? '')); ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label fw-bold" for="costo">Costo</label>
                    <div class="input-group">
                        <span class="input-group-text"><?php echo htmlspecialchars($_SESSION['configuracion']['moneda_simbolo'] ?? 'S/'); ?></span>
                        <input type="number" step="0.01" min="0" class="form-control" id="costo" name="costo"
                               value="<?php echo htmlspecialchars($_POST['costo'] ?? ($reparacion['costo'] > 0 ? $reparacion['costo'] : '')); ?>">
                    </div>
                </div>

                <div class="col-12">
                    <label class="form-label fw-bold" for="observaciones_salida">Observaciones de la Solución / Trabajo Realizado</label>
                    <textarea class="form-control" id="observaciones_salida" name="observaciones_salida" rows="3"><?php echo htmlspecialchars($_POST['observaciones_salida'] ?? ($reparacion['observaciones_salida'] ?? '')); ?></textarea>
                </div>
            </div>

            <hr class="my-4">

            <div class="d-flex justify-content-end gap-2">
                <a href="reparaciones.php?estado=<?php echo urlencode($_POST['estado_filtro'] ?? $_GET['estado'] ?? 'En Proceso'); ?>" class="btn btn-secondary">Cancelar</a>
                <button type="submit" class="btn btn-primary px-4">
                    <i class="bi bi-save me-2"></i>Guardar Cambios
                </button>
            </div>
        </form>
    </div>
</div>

<?php require_once '../templates/footer.php'; ?>
