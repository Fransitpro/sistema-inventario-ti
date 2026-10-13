<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}
require_once '../config/database.php';

$mensaje = '';
$tipo_mensaje = '';

$id_equipo = (int) ($_GET['id_equipo'] ?? $_POST['id_equipo'] ?? 0);
if ($id_equipo <= 0) {
    header("Location: bajas.php");
    exit();
}

$upload_dir = __DIR__ . '/../uploads/bajas/';

$motivos_permitidos = [
    'Desfase tecnológico',
    'Dañado sin reparación',
    'Obsoleto',
    'Perdido / Robado',
    'Otro',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $fecha_baja = trim($_POST['fecha_baja'] ?? '');
    $motivo = trim($_POST['motivo'] ?? '');
    $observaciones = trim($_POST['observaciones'] ?? '');
    $marcar_de_baja = isset($_POST['marcar_de_baja']);
    $eliminar_acta = isset($_POST['eliminar_acta']);
    $acta_anterior = trim($_POST['acta_anterior'] ?? '');

    if ($fecha_baja === '' || $motivo === '') {
        $mensaje = "Complete la fecha de baja y el motivo.";
        $tipo_mensaje = "danger";
    } elseif (!in_array($motivo, $motivos_permitidos, true)) {
        $mensaje = "Seleccione un motivo válido de la lista.";
        $tipo_mensaje = "danger";
    } else {
        $nuevo_acta = null;
        if (isset($_FILES['acta_baja']) && $_FILES['acta_baja']['error'] === UPLOAD_ERR_OK) {
            if (!is_dir($upload_dir) && !mkdir($upload_dir, 0777, true)) {
                $mensaje = "No se pudo crear la carpeta de actas.";
                $tipo_mensaje = "danger";
            } else {
                $extension = strtolower(pathinfo($_FILES['acta_baja']['name'], PATHINFO_EXTENSION));
                if (!in_array($extension, ['pdf', 'jpg', 'jpeg', 'png'], true)) {
                    $mensaje = "El acta debe ser un archivo PDF, JPG o PNG.";
                    $tipo_mensaje = "danger";
                } else {
                    $acta_nuevo = "acta_baja_{$id_equipo}_" . time() . ".{$extension}";
                    if (move_uploaded_file($_FILES['acta_baja']['tmp_name'], $upload_dir . $acta_nuevo)) {
                        $nuevo_acta = $acta_nuevo;
                    } else {
                        $mensaje = "Error al subir el archivo del acta.";
                        $tipo_mensaje = "danger";
                    }
                }
            }
        }

        if (empty($mensaje)) {
            $conexion->begin_transaction();
            try {
                $stmt_actual = $conexion->prepare("SELECT id, acta_baja_path FROM bajas WHERE id_equipo = ? ORDER BY fecha_baja DESC, id DESC LIMIT 1");
                $stmt_actual->bind_param("i", $id_equipo);
                $stmt_actual->execute();
                $baja_actual = $stmt_actual->get_result()->fetch_assoc();
                $stmt_actual->close();

                $acta = $baja_actual['acta_baja_path'] ?? $nuevo_acta;

                if ($eliminar_acta && $nuevo_acta === null) {
                    $acta = null;
                } elseif ($nuevo_acta !== null) {
                    $acta = $nuevo_acta;
                }

                if ($baja_actual) {
                    $stmt = $conexion->prepare("UPDATE bajas SET fecha_baja = ?, motivo = ?, observaciones = ?, acta_baja_path = ? WHERE id = ?");
                    $stmt->bind_param("ssssi", $fecha_baja, $motivo, $observaciones, $acta, $baja_actual['id']);
                    $stmt->execute();
                    $stmt->close();
                } else {
                    $stmt = $conexion->prepare("INSERT INTO bajas (id_equipo, fecha_baja, motivo, observaciones, acta_baja_path) VALUES (?, ?, ?, ?, ?)");
                    $stmt->bind_param("issss", $id_equipo, $fecha_baja, $motivo, $observaciones, $acta);
                    $stmt->execute();
                    $stmt->close();
                }

                if ($marcar_de_baja) {
                    $stmt_eq = $conexion->prepare("UPDATE equipos SET estado = 'De Baja' WHERE id = ?");
                    $stmt_eq->bind_param("i", $id_equipo);
                    $stmt_eq->execute();
                    $stmt_eq->close();
                }

                $conexion->commit();

                if ($acta_anterior && $acta_anterior !== $acta) {
                    $ruta_anterior = $upload_dir . basename($acta_anterior);
                    if (file_exists($ruta_anterior)) {
                        unlink($ruta_anterior);
                    }
                }

                header("Location: bajas.php?msg=guardado");
                exit();
            } catch (Throwable $e) {
                $conexion->rollback();
                if ($nuevo_acta && file_exists($upload_dir . $nuevo_acta)) {
                    unlink($upload_dir . $nuevo_acta);
                }
                $mensaje = "Error al guardar la baja: " . $e->getMessage();
                $tipo_mensaje = "danger";
            }
        }
    }
}

$sql_equipo = "SELECT e.id, e.codigo_inventario, e.cod_pat, e.numero_serie, e.estado,
                      ma.nombre AS marca_nombre, mo.nombre AS modelo_nombre, s.nombre AS sucursal_nombre
               FROM equipos e
               LEFT JOIN marcas ma ON e.id_marca = ma.id
               LEFT JOIN modelos mo ON e.id_modelo = mo.id
               LEFT JOIN sucursales s ON e.id_sucursal = s.id
               WHERE e.id = ?";
$stmt = $conexion->prepare($sql_equipo);
$stmt->bind_param("i", $id_equipo);
$stmt->execute();
$equipo = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$equipo) {
    header("Location: bajas.php");
    exit();
}

$stmt_baja = $conexion->prepare("SELECT * FROM bajas WHERE id_equipo = ? ORDER BY fecha_baja DESC, id DESC LIMIT 1");
$stmt_baja->bind_param("i", $id_equipo);
$stmt_baja->execute();
$baja = $stmt_baja->get_result()->fetch_assoc();
$stmt_baja->close();

$motivo_actual = $baja['motivo'] ?? '';
$motivo_fuera_de_lista = $motivo_actual !== '' && !in_array($motivo_actual, $motivos_permitidos, true);
$motivos_disponibles = $motivos_permitidos;

require_once '../templates/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h2"><?php echo $baja ? 'Editar Baja' : 'Registrar Baja'; ?></h1>
    <a href="bajas.php" class="btn btn-secondary"><i class="bi bi-arrow-left me-2"></i> Volver al reporte</a>
</div>

<?php if (!empty($mensaje)): ?>
    <div class="alert alert-<?php echo $tipo_mensaje; ?> alert-dismissible fade show shadow-sm" role="alert">
        <?php echo htmlspecialchars($mensaje); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<div class="card shadow-sm border-0">
    <div class="card-header bg-white py-3">
        <h5 class="mb-0 text-primary fw-bold"><i class="bi bi-pencil-square me-2"></i> Datos de la Baja</h5>
    </div>
    <div class="card-body p-4">

        <div class="row g-3 mb-4">
            <div class="col-md-3">
                <label class="form-label">Código Inventario</label>
                <input type="text" class="form-control" value="<?php echo htmlspecialchars($equipo['codigo_inventario']); ?>" disabled>
            </div>
            <div class="col-md-3">
                <label class="form-label">Código Patrimonial</label>
                <input type="text" class="form-control" value="<?php echo htmlspecialchars($equipo['cod_pat'] ?? 'N/A'); ?>" disabled>
            </div>
            <div class="col-md-3">
                <label class="form-label">Marca / Modelo</label>
                <input type="text" class="form-control" value="<?php echo htmlspecialchars(($equipo['marca_nombre'] ?? 'N/A') . ' / ' . ($equipo['modelo_nombre'] ?? 'N/A')); ?>" disabled>
            </div>
            <div class="col-md-3">
                <label class="form-label">Estado actual</label>
                <input type="text" class="form-control" value="<?php echo htmlspecialchars($equipo['estado'] === 'En Reparacion' ? 'En Reparación' : $equipo['estado']); ?>" disabled>
            </div>
        </div>

        <form action="baja_editar.php?id_equipo=<?php echo $id_equipo; ?>" method="POST" enctype="multipart/form-data" autocomplete="off">
            <input type="hidden" name="id_equipo" value="<?php echo $id_equipo; ?>">
            <input type="hidden" name="acta_anterior" value="<?php echo htmlspecialchars($baja['acta_baja_path'] ?? ''); ?>">

            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label fw-bold" for="fecha_baja">Fecha de Baja <span class="text-danger">*</span></label>
                    <input type="date" class="form-control input-size-30" size="30" id="fecha_baja" name="fecha_baja" required
                           value="<?php echo htmlspecialchars($baja['fecha_baja'] ?? date('Y-m-d')); ?>">
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-bold" for="motivo">Motivo <span class="text-danger">*</span></label>
                    <select class="form-select" id="motivo" name="motivo" required>
                        <option value="">Seleccione un motivo...</option>
                        <?php foreach ($motivos_disponibles as $motivo_opcion): ?>
                            <option value="<?php echo htmlspecialchars($motivo_opcion); ?>" <?php echo $motivo_opcion === $motivo_actual ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($motivo_opcion); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <?php if ($motivo_fuera_de_lista): ?>
                        <div class="form-text text-warning">
                            <i class="bi bi-exclamation-triangle-fill me-1"></i>
                            Motivo registrado actualmente: <strong><?php echo htmlspecialchars($motivo_actual); ?></strong>.
                            Seleccione el motivo correcto de la lista para actualizarlo.
                        </div>
                    <?php endif; ?>
                </div>

                <div class="col-12">
                    <label class="form-label fw-bold" for="observaciones">Observaciones</label>
                    <textarea class="form-control" id="observaciones" name="observaciones" rows="3"><?php echo htmlspecialchars($baja['observaciones'] ?? $baja['descripcion_motivo'] ?? ''); ?></textarea>
                </div>

                <div class="col-md-6">
                    <label class="form-label fw-bold" for="acta_baja">Acta de Baja (PDF, JPG o PNG)</label>
                    <input type="file" class="form-control" id="acta_baja" name="acta_baja" accept=".pdf, image/*">
                    <?php if (!empty($baja['acta_baja_path'])): ?>
                        <div class="form-text">
                            Acta actual: <a href="ver_acta_baja.php?id=<?php echo $baja['id']; ?>" target="_blank"><?php echo htmlspecialchars($baja['acta_baja_path']); ?></a>
                        </div>
                        <div class="form-check mt-1">
                            <input class="form-check-input" type="checkbox" name="eliminar_acta" id="eliminar_acta">
                            <label class="form-check-label" for="eliminar_acta">Eliminar el acta actual</label>
                        </div>
                    <?php else: ?>
                        <div class="form-text">Sin acta adjunta.</div>
                    <?php endif; ?>
                </div>

                <div class="col-md-6 d-flex align-items-end">
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="marcar_de_baja" id="marcar_de_baja"
                               <?php echo ($equipo['estado'] === 'De Baja') ? 'checked' : ''; ?>>
                        <label class="form-check-label" for="marcar_de_baja">
                            Marcar el equipo con estado <strong>De Baja</strong>
                        </label>
                    </div>
                </div>
            </div>

            <hr class="my-4">

            <div class="d-flex justify-content-end gap-2">
                <a href="bajas.php" class="btn btn-secondary">Cancelar</a>
                <button type="submit" class="btn btn-primary px-4">
                    <i class="bi bi-save me-2"></i><?php echo $baja ? 'Guardar Cambios' : 'Registrar Baja'; ?>
                </button>
            </div>
        </form>
    </div>
</div>

<?php require_once '../templates/footer.php'; ?>
