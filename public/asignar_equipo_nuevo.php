<?php
// 1. INICIO DE SESIÓN SEGURO
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once '../config/database.php';

// Validar acceso
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$mensaje = '';
$tipo_mensaje = '';
$es_admin_general = ($_SESSION['user_sucursal_id'] === null);
$sucursal_fija = $_SESSION['user_sucursal_id'] ?? null;

// Mantener lo que el usuario ya escribió si el guardado falla
$form = [
    'id_sucursal'  => '',
    'codigo_inventario' => '',
    'cod_pat'      => '',
    'numero_serie' => '',
    'id_tipo_equipo' => '',
    'id_marca'     => '',
    'id_modelo'    => '',
    'ip'           => '',
    'dimension'    => '',
    'color'        => '',
    'anio'         => '',
    'caracteristicas' => '',
    'tipo_adquisicion' => 'Propio',
    'fecha_adquisicion' => date('Y-m-d'),
    'proveedor'    => '',
    'observaciones' => '',
    'id_empleado'  => '',
    'observaciones_entrega' => '',
];

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    foreach ($form as $campo => $_) {
        if (array_key_exists($campo, $_POST)) {
            $form[$campo] = is_string($_POST[$campo]) ? trim($_POST[$campo]) : $_POST[$campo];
        }
    }
    $form['cod_pat'] = mb_strtoupper($form['cod_pat'], 'UTF-8');

    $errores = [];
    if ($form['codigo_inventario'] === '') {
        $errores[] = 'El código de inventario es obligatorio.';
    }
    if ($form['id_sucursal'] === '' || !is_numeric($form['id_sucursal'])) {
        $errores[] = 'Debe seleccionar una sucursal.';
    }
    if ($form['id_tipo_equipo'] === '' || !is_numeric($form['id_tipo_equipo'])) {
        $errores[] = 'Debe seleccionar el tipo de equipo.';
    }
    if ($form['id_marca'] === '' || !is_numeric($form['id_marca'])) {
        $errores[] = 'Debe seleccionar una marca.';
    }
    if ($form['id_empleado'] === '' || !is_numeric($form['id_empleado'])) {
        $errores[] = 'Debe seleccionar el empleado que recibirá el equipo.';
    }

    // El empleado debe existir y estar activo
    if (empty($errores)) {
        $stmt_chk = $conexion->prepare("SELECT id FROM empleados WHERE id = ? AND estado = 'Activo'");
        $stmt_chk->bind_param("i", $form['id_empleado']);
        $stmt_chk->execute();
        $stmt_chk->store_result();
        if ($stmt_chk->num_rows === 0) {
            $errores[] = 'El empleado seleccionado no existe o está inactivo.';
        }
        $stmt_chk->close();
    }

    // Un usuario de sucursal no puede registrar equipos en otra
    if (empty($errores) && $sucursal_fija !== null && (int)$form['id_sucursal'] !== (int)$sucursal_fija) {
        $errores[] = 'No puede registrar equipos en una sucursal distinta a la suya.';
    }

    if (!empty($errores)) {
        $mensaje = implode('<br>', array_map('htmlspecialchars', $errores));
        $tipo_mensaje = 'danger';
    } else {
        // 2. Registrar el equipo y asignarlo en la MISMA transacción:
        //    si algo falla, no queda ni el equipo suelto ni la asignación colgada.
        $conexion->begin_transaction();
        try {
            $anio = ($form['anio'] !== '' && is_numeric($form['anio'])) ? (int)$form['anio'] : null;

            // bind_param() exige variables por referencia: no se pueden pasar
            // expresiones ternarias ni conversiones en línea.
            $v_codigo  = $form['codigo_inventario'];
            $v_serie   = $form['numero_serie'];
            $v_ip      = $form['ip'];
            $v_suc     = (int)$form['id_sucursal'];
            $v_tipo    = (int)$form['id_tipo_equipo'];
            $v_marca   = (int)$form['id_marca'];
            $v_modelo  = 0;
            if ($form['id_modelo'] !== '' && is_numeric($form['id_modelo']) && (int)$form['id_modelo'] > 0) {
                $v_modelo = (int)$form['id_modelo'];
            } else {
                // id_modelo tiene clave foránea a modelos y es NOT NULL, así que
                // no se puede dejar en 0. Toda marca activa tiene al menos un
                // modelo, por lo que se toma el primero como valor por defecto.
                $stmt_mod = $conexion->prepare("SELECT id FROM modelos WHERE id_marca = ? ORDER BY nombre LIMIT 1");
                $stmt_mod->bind_param("i", $v_marca);
                $stmt_mod->execute();
                $fila_mod = $stmt_mod->get_result()->fetch_assoc();
                $stmt_mod->close();
                $v_modelo = $fila_mod ? (int)$fila_mod['id'] : 0;
            }
            if ($v_modelo === 0) {
                throw new Exception("La marca seleccionada no tiene ningún modelo registrado.");
            }
            $v_fecha   = $form['fecha_adquisicion'];
            $v_tipoadq = $form['tipo_adquisicion'];
            $v_caract  = $form['caracteristicas'];
            $v_proveed = $form['proveedor'];
            $v_obs     = $form['observaciones'];
            $v_codpat  = $form['cod_pat'];
            $v_dim     = $form['dimension'];
            $v_color   = $form['color'];

            $sql_eq = "INSERT INTO equipos (
                        codigo_inventario, numero_serie, ip, id_sucursal, id_tipo_equipo,
                        id_marca, id_modelo, fecha_adquisicion, tipo_adquisicion,
                        caracteristicas, proveedor, observaciones, estado,
                        cod_pat, dimension, color, anio
                     ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'Disponible', ?, ?, ?, ?)";
            $stmt_eq = $conexion->prepare($sql_eq);
            // 3 textos (codigo, serie, ip) + 4 enteros (sucursal, tipo, marca, modelo)
// + 8 textos (fecha, tipo_adquisicion, caracteristicas, proveedor,
//   observaciones, cod_pat, dimension, color) + 1 entero (anio) = 16
$stmt_eq->bind_param(
                "sssiiiissssssssi",
                $v_codigo,
                $v_serie,
                $v_ip,
                $v_suc,
                $v_tipo,
                $v_marca,
                $v_modelo,
                $v_fecha,
                $v_tipoadq,
                $v_caract,
                $v_proveed,
                $v_obs,
                $v_codpat,
                $v_dim,
                $v_color,
                $anio
            );
            $stmt_eq->execute();
            $id_equipo_nuevo = $stmt_eq->insert_id;
            $stmt_eq->close();

            if (!$id_equipo_nuevo) {
                throw new Exception("No se pudo registrar el equipo.");
            }

            // 3. Crear la asignación activa
            $fecha_entrega = date('Y-m-d H:i:s');
            $sql_as = "INSERT INTO asignaciones
                       (id_equipo, id_empleado, fecha_entrega, observaciones_entrega, estado_asignacion)
                       VALUES (?, ?, ?, ?, 'Activa')";
            $stmt_as = $conexion->prepare($sql_as);
            $stmt_as->bind_param(
                "iiss",
                $id_equipo_nuevo,
                $form['id_empleado'],
                $fecha_entrega,
                $form['observaciones_entrega']
            );
            $stmt_as->execute();
            $stmt_as->close();

            // 4. El equipo pasa a Asignado
            $stmt_up = $conexion->prepare("UPDATE equipos SET estado = 'Asignado' WHERE id = ?");
            $stmt_up->bind_param("i", $id_equipo_nuevo);
            $stmt_up->execute();
            $stmt_up->close();

            $conexion->commit();
            header('Location: asignaciones.php?status=success_nuevo_equipo&codigo=' . urlencode($form['codigo_inventario']));
            exit();
        } catch (Throwable $e) {
            $conexion->rollback();
            $mensaje = htmlspecialchars($e->getMessage());
            $tipo_mensaje = 'danger';
        }
    }
}

// 5. DATOS PARA LOS SELECTORES
$sucursales = $conexion->query("SELECT id, nombre FROM sucursales WHERE estado = 'Activo' ORDER BY nombre");
$tipos = $conexion->query("SELECT id, nombre FROM tipos_equipo WHERE estado = 'Activo' ORDER BY nombre");
$marcas = $conexion->query("SELECT id, nombre FROM marcas WHERE estado = 'Activo' ORDER BY nombre");

// Todos los empleados agrupados por sucursal: en los datos existentes hay
// asignaciones con equipo y empleado de sucursales distintas, así que filtrar
// por sucursal dejaría sin opciones casos válidos.
$empleados = $conexion->query("
    SELECT emp.id, emp.nombres, emp.apellidos, emp.dni, s.nombre AS sucursal_nombre
    FROM empleados emp
    LEFT JOIN sucursales s ON emp.id_sucursal = s.id
    WHERE emp.estado = 'Activo'
    ORDER BY s.nombre ASC, emp.apellidos ASC, emp.nombres ASC");

require_once '../templates/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h2">Registrar Equipo Nuevo y Asignarlo</h1>
    <a href="asignaciones.php" class="btn btn-secondary">
        <i class="bi bi-arrow-left me-2"></i> Volver
    </a>
</div>

<?php if (!empty($mensaje)): ?>
    <div class="alert alert-<?php echo $tipo_mensaje; ?> alert-dismissible fade show shadow-sm" role="alert">
        <?php echo $mensaje; ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<form action="asignar_equipo_nuevo.php" method="POST" autocomplete="off">
    <div class="card shadow-sm border-0 mb-4">
        <div class="card-header bg-white py-3">
            <h5 class="mb-0 text-primary fw-bold"><i class="bi bi-person-check me-2"></i> 1. Empleado que recibirá el equipo</h5>
        </div>
        <div class="card-body p-4">
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label fw-bold">Empleado <span class="text-danger">*</span></label>
                    <select class="form-select" name="id_empleado" required>
                        <option value="">Seleccione...</option>
                        <?php
                        if ($empleados) {
                            $suc_actual = null;
                            $abierto = false;
                            while ($emp = $empleados->fetch_assoc()) {
                                $nombre_suc = $emp['sucursal_nombre'] ?? 'Sin sucursal';
                                if ($nombre_suc !== $suc_actual) {
                                    if ($abierto) {
                                        echo '</optgroup>';
                                    }
                                    $suc_actual = $nombre_suc;
                                    $abierto = true;
                                    echo '<optgroup label="' . htmlspecialchars($nombre_suc) . '">';
                                }
                                $sel = ((string)$emp['id'] === (string)$form['id_empleado']) ? 'selected' : '';
                                $etiqueta = htmlspecialchars($emp['apellidos'] . ', ' . $emp['nombres'])
                                    . ' (DNI: ' . htmlspecialchars($emp['dni']) . ')';
                                echo "<option value='{$emp['id']}' {$sel}>{$etiqueta}</option>";
                            }
                            if ($abierto) {
                                echo '</optgroup>';
                            }
                        }
                        ?>
                    </select>
                    <div class="form-text">Empleados activos, agrupados por sucursal.</div>
                </div>

                <div class="col-md-6">
                    <label class="form-label fw-bold">Observaciones de la Entrega</label>
                    <textarea class="form-control" name="observaciones_entrega" rows="3" placeholder="Ej: Se entrega con cargador y maletín."><?php echo htmlspecialchars($form['observaciones_entrega']); ?></textarea>
                </div>
            </div>
        </div>
    </div>

    <div class="card shadow-sm border-0">
        <div class="card-header bg-white py-3">
            <h5 class="mb-0 text-primary fw-bold"><i class="bi bi-pc-display me-2"></i> 2. Datos del equipo nuevo</h5>
        </div>
        <div class="card-body p-4">
            <div class="row g-3">

                <div class="col-md-4">
                    <label class="form-label fw-bold">Sucursal <span class="text-danger">*</span></label>
                    <select class="form-select" name="id_sucursal" required <?php echo !$es_admin_general ? 'disabled' : ''; ?>>
                        <option value="">Seleccione...</option>
                        <?php
                        if ($sucursales) {
                            mysqli_data_seek($sucursales, 0);
                            while ($row = $sucursales->fetch_assoc()) {
                                $sel = ((string)$row['id'] === (string)$form['id_sucursal']) ? 'selected' : '';
                                echo "<option value='{$row['id']}' {$sel}>" . htmlspecialchars($row['nombre']) . "</option>";
                            }
                        }
                        ?>
                    </select>
                    <?php if (!$es_admin_general): ?>
                        <input type="hidden" name="id_sucursal" value="<?php echo (int)$sucursal_fija; ?>">
                    <?php endif; ?>
                </div>

                <div class="col-md-4">
                    <label class="form-label fw-bold">Código Inventario <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" name="codigo_inventario" required
                           placeholder="Ej: 001701" value="<?php echo htmlspecialchars($form['codigo_inventario']); ?>">
                    <div class="form-text">Use <strong>S/S</strong> si el equipo no tiene código.</div>
                </div>

                <div class="col-md-4">
                    <label class="form-label fw-bold">Código Patrimonial</label>
                    <input type="text" class="form-control" name="cod_pat" maxlength="10" style="text-transform: uppercase;"
                           placeholder="Ej: CP-001 o S/S" value="<?php echo htmlspecialchars($form['cod_pat']); ?>">
                </div>

                <div class="col-md-6">
                    <label class="form-label fw-bold">Número de Serie</label>
                    <input type="text" class="form-control" name="numero_serie" placeholder="O S/S"
                           value="<?php echo htmlspecialchars($form['numero_serie']); ?>">
                </div>

                <div class="col-md-4">
                    <label class="form-label fw-bold">Tipo de Equipo <span class="text-danger">*</span></label>
                    <select class="form-select" name="id_tipo_equipo" required>
                        <option value="">Seleccione...</option>
                        <?php
                        if ($tipos) {
                            while ($r = $tipos->fetch_assoc()) {
                                $sel = ((string)$r['id'] === (string)$form['id_tipo_equipo']) ? 'selected' : '';
                                echo "<option value='{$r['id']}' {$sel}>" . htmlspecialchars($r['nombre']) . "</option>";
                            }
                        }
                        ?>
                    </select>
                </div>

                <div class="col-md-4">
                    <label class="form-label fw-bold">Marca <span class="text-danger">*</span></label>
                    <select class="form-select" name="id_marca" id="id_marca" required>
                        <option value="">Seleccione...</option>
                        <?php
                        if ($marcas) {
                            while ($r = $marcas->fetch_assoc()) {
                                $sel = ((string)$r['id'] === (string)$form['id_marca']) ? 'selected' : '';
                                echo "<option value='{$r['id']}' {$sel}>" . htmlspecialchars($r['nombre']) . "</option>";
                            }
                        }
                        ?>
                    </select>
                </div>

                <div class="col-md-4">
                    <label class="form-label fw-bold">Modelo</label>
                    <select class="form-select" name="id_modelo" id="id_modelo" required>
                        <option value="">Seleccione el modelo</option>
                    </select>
                </div>

                <div class="col-md-4">
                    <label class="form-label fw-bold">Tipo de Adquisición</label>
                    <select class="form-select" name="tipo_adquisicion">
                        <?php foreach (['Propio', 'Alquilado', 'Leasing', 'Prestamo'] as $ta): ?>
                            <option value="<?php echo $ta; ?>" <?php echo ($form['tipo_adquisicion'] === $ta) ? 'selected' : ''; ?>>
                                <?php echo ($ta === 'Prestamo') ? 'Préstamo' : $ta; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-md-4">
                    <label class="form-label fw-bold">Año</label>
                    <input type="number" class="form-control" name="anio" min="1980" max="2100"
                           placeholder="Ej: 2026" value="<?php echo htmlspecialchars($form['anio']); ?>">
                </div>

                <div class="col-md-4">
                    <label class="form-label fw-bold">Dirección IP</label>
                    <input type="text" class="form-control" name="ip" maxlength="45" placeholder="Ej: 192.168.1.10"
                           value="<?php echo htmlspecialchars($form['ip']); ?>">
                </div>

                <div class="col-md-4">
                    <label class="form-label fw-bold">Dimensión</label>
                    <input type="text" class="form-control" name="dimension" maxlength="50" placeholder="Ej: 35x25x10 cm"
                           value="<?php echo htmlspecialchars($form['dimension']); ?>">
                </div>

                <div class="col-md-4">
                    <label class="form-label fw-bold">Color</label>
                    <input type="text" class="form-control" name="color" maxlength="50" placeholder="Ej: Negro"
                           value="<?php echo htmlspecialchars($form['color']); ?>">
                </div>

                <div class="col-md-4">
                    <label class="form-label fw-bold">Características</label>
                    <input type="text" class="form-control" name="caracteristicas" placeholder="Ej: Core i5, 16GB RAM..."
                           value="<?php echo htmlspecialchars($form['caracteristicas']); ?>">
                </div>

                <div class="col-md-6">
                    <label class="form-label fw-bold">Fecha de Adquisición</label>
                    <input type="date" class="form-control" name="fecha_adquisicion"
                           value="<?php echo htmlspecialchars($form['fecha_adquisicion']); ?>">
                </div>

                <div class="col-md-6">
                    <label class="form-label fw-bold">Proveedor</label>
                    <input type="text" class="form-control" name="proveedor" placeholder="Nombre del proveedor"
                           value="<?php echo htmlspecialchars($form['proveedor']); ?>">
                </div>

                <div class="col-12">
                    <label class="form-label fw-bold">Observaciones del equipo</label>
                    <textarea class="form-control" name="observaciones" rows="2"><?php echo htmlspecialchars($form['observaciones']); ?></textarea>
                </div>
            </div>

            <hr class="my-4">

            <div class="alert alert-info py-2 small rounded-3">
                <i class="bi bi-info-circle-fill me-1"></i>
                El equipo se registrará y quedará <strong>Asignado</strong> de inmediato, con su asignación activa.
                Si algo falla, no se guarda ni el equipo ni la asignación.
            </div>

            <div class="d-flex justify-content-end gap-2">
                <a href="asignaciones.php" class="btn btn-secondary">Cancelar</a>
                <button type="submit" class="btn btn-primary px-4">
                    <i class="bi bi-plus-circle me-2"></i> Registrar y Asignar
                </button>
            </div>
        </div>
    </div>
</form>

<?php require_once '../templates/footer.php'; ?>

<script>
$(document).ready(function() {
    $('#id_marca').on('change', function() {
        var idMarca = $(this).val();
        $('#id_modelo').html('<option value="">Cargando modelos...</option>');
        if (idMarca) {
            $.ajax({
                url: 'obtener_modelos.php',
                type: 'POST',
                data: { id_marca: idMarca },
                success: function(response) {
                    $('#id_modelo').html(response);
                    // Si la marca solo tiene un modelo, se selecciona directo.
                    var opts = $('#id_modelo option');
                    if (opts.length === 2) { $('#id_modelo').val(opts[1].val); }
                },
                error: function() { $('#id_modelo').html('<option value="">Error al cargar modelos</option>'); }
            });
        } else {
            $('#id_modelo').html('<option value="">Seleccione una marca primero</option>');
        }
    });
});
</script>