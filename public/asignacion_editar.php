<?php
// 1. LÓGICA DE NEGOCIO (ANTES DE HTML)
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once '../config/database.php';

// Validar sesión
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// El id puede venir por GET (abrir el formulario) o por POST (guardar)
$id_asignacion = $_POST['id_asignacion'] ?? $_GET['id'] ?? null;
if (!is_numeric($id_asignacion)) {
    header("Location: asignaciones.php");
    exit();
}
$id_asignacion = (int)$id_asignacion;

// Petición AJAX (modal del listado): responde JSON en lugar de redirigir.
$es_ajax = !empty($_GET['ajax']) || ($_POST['ajax'] ?? '') === '1';

function responder_json($ok, $mensaje)
{
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['ok' => $ok, 'mensaje' => $mensaje]);
    exit();
}

// ---- Edición ÚNICA del código de inventario ----
// El modal del listado solo permite cambiar este dato: el resto de la
// información se muestra en modo lectura y no se toca.
if (!empty($_POST['editar_codigo']) && $es_ajax) {
    $nuevo_codigo = trim((string)($_POST['nuevo_codigo'] ?? ''));

    if ($nuevo_codigo === '') {
        responder_json(false, 'El código de inventario no puede quedar vacío.');
    }
    if (mb_strlen($nuevo_codigo, 'UTF-8') > 50) {
        responder_json(false, 'El código de inventario no puede superar los 50 caracteres.');
    }

    // Primero se obtiene el equipo de esta asignación: el id del equipo NO es
    // el id de la asignación, hay que consultarlo.
    $stmt_eq = $conexion->prepare("SELECT id_equipo FROM asignaciones WHERE id = ?");
    $stmt_eq->bind_param("i", $id_asignacion);
    $stmt_eq->execute();
    $fila_as = $stmt_eq->get_result()->fetch_assoc();
    $stmt_eq->close();

    if (!$fila_as) {
        responder_json(false, 'La asignación no existe.');
    }
    $id_equipo = (int)$fila_as['id_equipo'];

    // Se verifica que el equipo exista y no esté dado de baja
    $stmt_ev = $conexion->prepare("SELECT id FROM equipos
                                   WHERE id = ? AND estado <> 'De Baja'");
    $stmt_ev->bind_param("i", $id_equipo);
    $stmt_ev->execute();
    $stmt_ev->store_result();
    $equipo_existe = ($stmt_ev->num_rows > 0);
    $stmt_ev->close();

    if (!$equipo_existe) {
        responder_json(false, 'El equipo de esta asignación no existe o está dado de baja.');
    }

    // El código de inventario puede repetirse en este sistema (hay equipos con
    // "S/S"), así que no se bloquea por duplicado; solo se avisa.
    $stmt_dup = $conexion->prepare("SELECT codigo_inventario FROM equipos
                                    WHERE codigo_inventario = ? AND id <> ? LIMIT 1");
    $stmt_dup->bind_param("si", $nuevo_codigo, $id_equipo);
    $stmt_dup->execute();
    $stmt_dup->store_result();
    $ya_existe = ($stmt_dup->num_rows > 0);
    $stmt_dup->close();

    // El código ya congelado (columna "Código Inventario 2025" del listado)
    // no se vuelve a tocar: solo se rellena la primera vez.
    try {
        $stmt_frz = $conexion->prepare(
            "UPDATE equipos SET codigo_inventario_2025 = codigo_inventario
             WHERE id = ? AND (codigo_inventario_2025 IS NULL OR codigo_inventario_2025 = '')"
        );
        $stmt_frz->bind_param("i", $id_equipo);
        $stmt_frz->execute();
        $stmt_frz->close();
    } catch (Throwable $e) {
        // Si la migración aún no se aplicó, se sigue guardando el código
        // principal: es preferible a bloquear la edición.
    }

    $stmt_up = $conexion->prepare("UPDATE equipos SET codigo_inventario = ? WHERE id = ?");
    $stmt_up->bind_param("si", $nuevo_codigo, $id_equipo);
    $stmt_up->execute();
    $stmt_up->close();

    $aviso = $ya_existe ? ' (ese código ya existe en otro equipo)' : '';
    responder_json(true, 'Código de inventario actualizado a ' . $nuevo_codigo . $aviso . '.');
}

$mensaje = '';
$tipo_mensaje = '';

// 2. PROCESAR ACTUALIZACIÓN
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    $id_empleado = $_POST['id_empleado'] ?? '';
    $observaciones = trim($_POST['observaciones_entrega'] ?? '');
    $nuevo_id_equipo = $_POST['id_equipo'] ?? '';
    // Formulario exclusivo de equipo: no debe tocar empleado ni observaciones.
    $solo_equipo = !empty($_POST['solo_equipo']);
    // Opción "crear nuevo código de inventario" dentro del modal.
    $crear_equipo = !empty($_POST['crear_equipo']);

    if (!$solo_equipo && (empty($id_empleado) || !is_numeric($id_empleado))) {
        $mensaje = "Por favor seleccione un empleado.";
        $tipo_mensaje = "danger";
    } elseif (!$solo_equipo) {
        // El empleado debe existir y estar activo
        $check = $conexion->prepare("SELECT id FROM empleados WHERE id = ? AND estado = 'Activo'");
        $check->bind_param("i", $id_empleado);
        $check->execute();
        $check->store_result();
        $empleado_valido = ($check->num_rows > 0);
        $check->close();

        if (!$empleado_valido) {
            $mensaje = "El empleado seleccionado no existe o está inactivo.";
            $tipo_mensaje = "warning";
        }
    }

    // Si la validación de empleado falló, en modo AJAX se responde ya mismo.
    if (!empty($mensaje) && $es_ajax) {
        responder_json(false, $mensaje);
    }

    if (empty($mensaje)) {
            // Cargar la asignación actual para conocer el equipo previo
            $sql_actual = "SELECT id_equipo, fecha_devolucion FROM asignaciones WHERE id = ?";
            $stmt_actual = $conexion->prepare($sql_actual);
            $stmt_actual->bind_param("i", $id_asignacion);
            $stmt_actual->execute();
            $fila_actual = $stmt_actual->get_result()->fetch_assoc();
            $stmt_actual->close();

            if (!$fila_actual) {
                header("Location: asignaciones.php?status=error&msg=La asignacion no existe");
                exit();
            }

            $id_equipo_anterior = (int)$fila_actual['id_equipo'];
            $asignacion_activa = ($fila_actual['fecha_devolucion'] === null);

            // ¿Se pidió cambiar el equipo?
            $cambia_equipo = is_numeric($nuevo_id_equipo)
                && (int)$nuevo_id_equipo > 0
                && (int)$nuevo_id_equipo !== $id_equipo_anterior;

            $conexion->begin_transaction();
            try {
                // Opción "crear nuevo equipo con código de inventario": se registra
                // el equipo dentro de la MISMA transacción que luego lo asigna,
                // para no dejar equipos huérfanos si el cambio falla.
                if ($crear_equipo) {
                    if (!$asignacion_activa) {
                        throw new Exception("No se puede crear y asignar un equipo en una asignacion ya finalizada.");
                    }

                    $nuevo = $_POST['nuevo_equipo'] ?? [];
                    $n_codigo = trim((string)($nuevo['codigo_inventario'] ?? ''));
                    $n_suc    = (int)($nuevo['id_sucursal'] ?? $asignacion['id_sucursal']);
                    $n_tipo   = (int)($nuevo['id_tipo_equipo'] ?? 0);
                    $n_marca  = (int)($nuevo['id_marca'] ?? 0);
                    $n_modelo = (int)($nuevo['id_modelo'] ?? 0);
                    $n_serie  = trim((string)($nuevo['numero_serie'] ?? ''));
                    $n_codpat = mb_strtoupper(trim((string)($nuevo['cod_pat'] ?? '')), 'UTF-8');
                    $n_anio   = (int)($nuevo['anio'] ?? 0);
                    $n_caract = trim((string)($nuevo['caracteristicas'] ?? ''));

                    if ($n_codigo === '' || $n_tipo === 0 || $n_marca === 0 || $n_suc === 0) {
                        throw new Exception("Complete el código de inventario, tipo, marca y sucursal del equipo nuevo.");
                    }

                    // id_modelo es NOT NULL con clave foránea: si no se eligió,
                    // se toma el primer modelo de la marca (toda marca tiene uno).
                    if ($n_modelo === 0) {
                        $stmt_m = $conexion->prepare("SELECT id FROM modelos WHERE id_marca = ? ORDER BY nombre LIMIT 1");
                        $stmt_m->bind_param("i", $n_marca);
                        $stmt_m->execute();
                        $fila_m = $stmt_m->get_result()->fetch_assoc();
                        $stmt_m->close();
                        $n_modelo = $fila_m ? (int)$fila_m['id'] : 0;
                    }
                    if ($n_modelo === 0) {
                        throw new Exception("La marca seleccionada no tiene ningún modelo registrado.");
                    }

                    $n_fecha  = trim((string)($nuevo['fecha_adquisicion'] ?? ''));
                    $n_tipoadq = $nuevo['tipo_adquisicion'] ?? 'Propio';

                    // Variables separadas: bind_param() exige referencias.
                    $v_cod = $n_codigo; $v_ser = $n_serie; $v_cpa = $n_codpat;
                    $v_suc = $n_suc; $v_tip = $n_tipo; $v_mar = $n_marca; $v_mod = $n_modelo;
                    $v_fec = ($n_fecha !== '') ? $n_fecha : date('Y-m-d');
                    $v_taq = in_array($n_tipoadq, ['Propio', 'Alquilado', 'Leasing', 'Prestamo'], true) ? $n_tipoadq : 'Propio';
                    $v_car = $n_caract;
                    $v_pro = '';
                    $v_obs = '';
                    $v_anio = ($n_anio > 0) ? $n_anio : null;

                    $sql_ins = "INSERT INTO equipos (
                                    codigo_inventario, numero_serie, id_sucursal, id_tipo_equipo,
                                    id_marca, id_modelo, fecha_adquisicion, tipo_adquisicion,
                                    caracteristicas, proveedor, observaciones, estado,
                                    cod_pat, anio
                                 ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'Disponible', ?, ?)";
                    $stmt_ins = $conexion->prepare($sql_ins);
                    // 13 placeholders: codigo(s), serie(s), sucursal(i), tipo(i), marca(i),
                //   modelo(i), fecha(s), tipo_adq(s), caracteristicas(s),
                //   proveedor(s), observaciones(s), cod_pat(s), anio(i)
                $stmt_ins->bind_param(
                        "ssiiiissssssi",
                        $v_cod, $v_ser, $v_suc, $v_tip, $v_mar, $v_mod,
                        $v_fec, $v_taq, $v_car, $v_pro, $v_obs, $v_cpa, $v_anio
                    );
                    $stmt_ins->execute();
                    $nuevo_id_equipo = (int)$stmt_ins->insert_id;
                    $stmt_ins->close();

                    if (!$nuevo_id_equipo) {
                        throw new Exception("No se pudo crear el equipo con el código de inventario indicado.");
                    }

                    // A partir de aquí el flujo es el mismo que un cambio normal.
                    $cambia_equipo = true;
                }

                if ($cambia_equipo) {
                    // Un equipo no se puede cambiar en una asignación ya
                    // finalizada: el equipo ya fue devuelto y el acta ya existe.
                    if (!$asignacion_activa) {
                        throw new Exception("No se puede cambiar el equipo de una asignacion ya finalizada.");
                    }

                    // El nuevo equipo debe existir y no estar dado de baja
                    $stmt_ev = $conexion->prepare("SELECT id FROM equipos
                                                     WHERE id = ? AND estado NOT IN ('De Baja', 'En Reparacion')");
                    $stmt_ev->bind_param("i", $nuevo_id_equipo);
                    $stmt_ev->execute();
                    $stmt_ev->store_result();
                    if ($stmt_ev->num_rows === 0) {
                        $stmt_ev->close();
                        throw new Exception("El equipo seleccionado no existe, esta dado de baja o en reparacion.");
                    }
                    $stmt_ev->close();

                    // No debe tener otra asignacion activa
                    $stmt_ocupado = $conexion->prepare("SELECT id FROM asignaciones
                                                         WHERE id_equipo = ? AND fecha_devolucion IS NULL");
                    $stmt_ocupado->bind_param("i", $nuevo_id_equipo);
                    $stmt_ocupado->execute();
                    $stmt_ocupado->store_result();
                    if ($stmt_ocupado->num_rows > 0) {
                        $stmt_ocupado->close();
                        throw new Exception("El equipo seleccionado ya esta asignado a otro empleado.");
                    }
                    $stmt_ocupado->close();

                    // Liberar el equipo anterior SOLO si no tiene otra
                    // asignacion activa (hay equipos con varias asignaciones).
                    $stmt_libre = $conexion->prepare("SELECT COUNT(*) AS n FROM asignaciones
                                                      WHERE id_equipo = ? AND fecha_devolucion IS NULL AND id <> ?");
                    $stmt_libre->bind_param("ii", $id_equipo_anterior, $id_asignacion);
                    $stmt_libre->execute();
                    $otras_activas = (int)$stmt_libre->get_result()->fetch_assoc()['n'];
                    $stmt_libre->close();

                    if ($otras_activas === 0) {
                        $stmt_upd = $conexion->prepare("UPDATE equipos SET estado = 'Disponible' WHERE id = ?");
                        $stmt_upd->bind_param("i", $id_equipo_anterior);
                        $stmt_upd->execute();
                        $stmt_upd->close();
                    }

                    // Ocupar el nuevo equipo
                    $stmt_upd2 = $conexion->prepare("UPDATE equipos SET estado = 'Asignado' WHERE id = ?");
                    $stmt_upd2->bind_param("i", $nuevo_id_equipo);
                    $stmt_upd2->execute();
                    $stmt_upd2->close();
                }

                // Se actualiza solo lo que corresponde al formulario enviado:
                // - formulario de equipo  -> únicamente id_equipo
                // - formulario de datos  -> id_empleado y observaciones
                if ($solo_equipo) {
                    if ($cambia_equipo) {
                        $sql = "UPDATE asignaciones SET id_equipo = ? WHERE id = ?";
                        $stmt = $conexion->prepare($sql);
                        $stmt->bind_param("ii", $nuevo_id_equipo, $id_asignacion);
                    } else {
                        // No se pidió cambio de equipo: no hay nada que guardar.
                        $stmt = null;
                    }
                } elseif ($cambia_equipo) {
                    $sql = "UPDATE asignaciones
                            SET id_empleado = ?, observaciones_entrega = ?, id_equipo = ?
                            WHERE id = ?";
                    $stmt = $conexion->prepare($sql);
                    $stmt->bind_param("ssii", $id_empleado, $observaciones, $nuevo_id_equipo, $id_asignacion);
                } else {
                    $sql = "UPDATE asignaciones
                            SET id_empleado = ?, observaciones_entrega = ?
                            WHERE id = ?";
                    $stmt = $conexion->prepare($sql);
                    $stmt->bind_param("ssi", $id_empleado, $observaciones, $id_asignacion);
                }

                if ($stmt !== null && !$stmt->execute()) {
                    throw new Exception("Error al actualizar: " . $stmt->error);
                }
                if ($stmt !== null) {
                    $stmt->close();
                }

                $conexion->commit();
                if ($es_ajax) {
                    responder_json(true, $cambia_equipo
                        ? 'Se actualizó el código de inventario del equipo.'
                        : 'Asignación actualizada correctamente.');
                }
                header("Location: asignaciones.php?status=" . ($cambia_equipo ? 'success_equipo' : 'success_edit'));
                exit();
            } catch (Throwable $e) {
                $conexion->rollback();
                if ($es_ajax) {
                    responder_json(false, $e->getMessage());
                }
                $mensaje = $e->getMessage();
                $tipo_mensaje = "danger";
            }
        }
}

// 3. OBTENER DATOS ACTUALES DE LA ASIGNACIÓN
$stmt = $conexion->prepare("
    SELECT a.id, a.id_empleado, a.id_equipo, a.fecha_entrega, a.fecha_devolucion,
           a.observaciones_entrega, a.estado_asignacion,
           e.codigo_inventario, e.cod_pat, e.numero_serie, e.id_sucursal,
           ma.nombre AS marca_nombre, mo.nombre AS modelo_nombre,
           s.nombre AS sucursal_nombre
    FROM asignaciones a
    JOIN equipos e ON a.id_equipo = e.id
    LEFT JOIN marcas ma ON e.id_marca = ma.id
    LEFT JOIN modelos mo ON e.id_modelo = mo.id
    LEFT JOIN sucursales s ON e.id_sucursal = s.id
    WHERE a.id = ?");
$stmt->bind_param("i", $id_asignacion);
$stmt->execute();
$asignacion = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$asignacion) {
    header("Location: asignaciones.php?status=error&msg=La asignacion no existe");
    exit();
}

// Si un usuario de sucursal intenta editar una asignación de otra sucursal
$id_sucursal_usuario = $_SESSION['user_sucursal_id'] ?? null;
if ($id_sucursal_usuario !== null && (int)$asignacion['id_sucursal'] !== (int)$id_sucursal_usuario) {
    header("Location: asignaciones.php?status=error&msg=No tiene permiso para editar esta asignacion");
    exit();
}

// 4. EMPLEADOS DISPONIBLES PARA ASIGNAR
// No se filtran por la sucursal del equipo: en los datos existentes hay
// asignaciones cuyo equipo y cuyo empleado pertenecen a sucursales distintas,
// y filtrar dejaba el select vacío, imposible de editar.
// Se listan todos agrupados por sucursal e incluye también a los inactivos
// para que el empleado ya asignado siga apareciendo seleccionado.
// query() ya devuelve un mysqli_result: no se llama a get_result()
$empleados = $conexion->query("
    SELECT emp.id, emp.nombres, emp.apellidos, emp.dni, emp.estado,
           s.nombre AS sucursal_nombre
    FROM empleados emp
    LEFT JOIN sucursales s ON emp.id_sucursal = s.id
    ORDER BY s.nombre ASC, emp.apellidos ASC, emp.nombres ASC");

// 5. EQUIPOS ELEGIBLES PARA EL CAMBIO DE CÓDIGO INVENTARIO
// Se listan los que NO tienen ninguna asignación activa, excluyendo la de
// esta misma asignación (para que el equipo actual siga en la lista).
// Así se capturan también los que figuran como 'Asignado' pero están libres
// por inconsistencia de datos. Se excluyen los 'De Baja' y los 'En Reparacion',
// que no deben ofrecerse para asignarse.
// Un equipo solo se puede cambiar en asignaciones activas.
$stmt_eq = $conexion->prepare("
    SELECT e.id, e.codigo_inventario, e.cod_pat, e.estado,
           ma.nombre AS marca_nombre, mo.nombre AS modelo_nombre,
           s.nombre AS sucursal_nombre
    FROM equipos e
    LEFT JOIN marcas ma ON e.id_marca = ma.id
    LEFT JOIN modelos mo ON e.id_modelo = mo.id
    LEFT JOIN sucursales s ON e.id_sucursal = s.id
    WHERE (e.estado NOT IN ('De Baja', 'En Reparacion')
      AND NOT EXISTS (
            SELECT 1 FROM asignaciones a2
            WHERE a2.id_equipo = e.id AND a2.fecha_devolucion IS NULL
      ))
      OR e.id = ?
    ORDER BY s.nombre ASC, e.codigo_inventario ASC");
$stmt_eq->bind_param("i", $asignacion['id_equipo']);
$stmt_eq->execute();
$equipos = $stmt_eq->get_result();
$stmt_eq->close();

$asignacion_activa = ($asignacion['fecha_devolucion'] === null);

require_once '../templates/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h2">Editar Código de Inventario</h1>
    <a href="asignaciones.php" class="btn btn-secondary">
        <i class="bi bi-arrow-left me-2"></i> Volver
    </a>
</div>

<?php if (!empty($mensaje)): ?>
    <div class="alert alert-<?php echo $tipo_mensaje; ?> alert-dismissible fade show shadow-sm" role="alert">
        <?php echo htmlspecialchars($mensaje); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<div class="card shadow-sm border-0 mb-4" id="equipo">
    <div class="card-header bg-white py-3">
        <h5 class="mb-0 text-success fw-bold">
            <i class="bi bi-hash me-2"></i> Cambiar Código de Inventario
        </h5>
    </div>
    <div class="card-body">
        <?php if ($asignacion_activa): ?>
            <form action="asignacion_editar.php?id=<?php echo $id_asignacion; ?>" method="POST" id="formCambiarEquipo">
                <input type="hidden" name="id_asignacion" value="<?php echo $id_asignacion; ?>">
                <input type="hidden" name="solo_equipo" value="1">

                <div class="row g-3 align-items-end">
                    <div class="col-md-8">
                        <label class="form-label fw-bold">Equipo (Código de Inventario) <span class="text-danger">*</span></label>
                        <select class="form-select" name="id_equipo" id="selectCambioEquipo" required>
                            <option value="">Seleccione el nuevo equipo...</option>
                            <?php
                            if ($equipos && $equipos->num_rows > 0) {
                                $sucursal_actual = null;
                                $grupo_abierto = false;
                                while ($eq = $equipos->fetch_assoc()) {
                                    $nombre_suc = $eq['sucursal_nombre'] ?? 'Sin sucursal';
                                    if ($nombre_suc !== $sucursal_actual) {
                                        if ($grupo_abierto) {
                                            echo '</optgroup>';
                                        }
                                        $sucursal_actual = $nombre_suc;
                                        $grupo_abierto = true;
                                        echo '<optgroup label="' . htmlspecialchars($nombre_suc) . '">';
                                    }
                                    $sel = ((int)$eq['id'] === (int)$asignacion['id_equipo']) ? 'selected' : '';
                                    $desc = trim(($eq['marca_nombre'] ?? '') . ' ' . ($eq['modelo_nombre'] ?? ''));
                                    $etiqueta = htmlspecialchars($eq['codigo_inventario']);
                                    if ($desc !== '') {
                                        $etiqueta .= ' — ' . htmlspecialchars($desc);
                                    }
                                    if ((int)$eq['id'] === (int)$asignacion['id_equipo']) {
                                        $etiqueta .= ' (actual)';
                                    }
                                    echo "<option value='{$eq['id']}' {$sel}>{$etiqueta}</option>";
                                }
                                if ($grupo_abierto) {
                                    echo '</optgroup>';
                                }
                            } else {
                                echo '<option value="">No hay equipos disponibles</option>';
                            }
                            ?>
                        </select>
                        <div class="form-text">
                            Solo equipos sin asignación activa. Al guardar, el equipo anterior queda
                            <strong>Disponible</strong> y el nuevo queda <strong>Asignado</strong>.
                        </div>
                    </div>
                    <div class="col-md-4">
                        <button type="submit" class="btn btn-success w-100 py-2">
                            <i class="bi bi-arrow-left-right me-2"></i> Cambiar Equipo
                        </button>
                    </div>
                </div>
            </form>
        <?php else: ?>
            <div class="alert alert-warning py-2 mb-0 small rounded-3">
                <i class="bi bi-lock-fill me-1"></i>
                Esta asignación ya fue <strong>finalizada</strong> (el equipo fue devuelto), por lo que no se puede cambiar su código de inventario.
            </div>
        <?php endif; ?>
    </div>
</div>

<div class="card shadow-sm border-0 mb-4">
    <div class="card-header bg-white py-3">
        <h5 class="mb-0 text-secondary fw-bold">
            <i class="bi bi-info-circle me-2"></i> Datos del equipo actual (solo lectura)
        </h5>
    </div>
    <div class="card-body">
        <div class="row g-3">
            <div class="col-md-3">
                <label class="form-label text-muted small">Código Inventario</label>
                <input type="text" class="form-control fw-bold" value="<?php echo htmlspecialchars($asignacion['codigo_inventario']); ?>" disabled>
            </div>
            <div class="col-md-3">
                <label class="form-label text-muted small">Código Patrimonial</label>
                <input type="text" class="form-control" value="<?php echo htmlspecialchars($asignacion['cod_pat'] ?: '---'); ?>" disabled>
            </div>
            <div class="col-md-3">
                <label class="form-label text-muted small">Marca / Modelo</label>
                <input type="text" class="form-control" value="<?php echo htmlspecialchars(trim($asignacion['marca_nombre'] . ' ' . $asignacion['modelo_nombre'])); ?>" disabled>
            </div>
            <div class="col-md-3">
                <label class="form-label text-muted small">Sucursal</label>
                <input type="text" class="form-control" value="<?php echo htmlspecialchars($asignacion['sucursal_nombre']); ?>" disabled>
            </div>
            <div class="col-md-3">
                <label class="form-label text-muted small">Fecha de Entrega</label>
                <input type="text" class="form-control" value="<?php echo date('d/m/Y H:i', strtotime($asignacion['fecha_entrega'])); ?>" disabled>
            </div>
            <div class="col-md-3">
                <label class="form-label text-muted small">Estado de la Asignación</label>
                <input type="text" class="form-control" value="<?php echo htmlspecialchars($asignacion['estado_asignacion']); ?>" disabled>
            </div>
        </div>
    </div>
</div>

<div class="card shadow-sm border-0">
    <div class="card-header bg-white py-3">
        <h5 class="mb-0 text-primary fw-bold"><i class="bi bi-pencil-square me-2"></i> Modificar Datos</h5>
    </div>
    <div class="card-body p-4">
        <form action="asignacion_editar.php?id=<?php echo $id_asignacion; ?>" method="POST">
            <input type="hidden" name="id_asignacion" value="<?php echo $id_asignacion; ?>">

            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label fw-bold">Empleado <span class="text-danger">*</span></label>
                    <select class="form-select" name="id_empleado" required>
                        <option value="">Seleccione...</option>
                        <?php
                        if ($empleados && $empleados->num_rows > 0) {
                            $sucursal_actual = null;
                            $grupo_abierto = false;
                            while ($emp = $empleados->fetch_assoc()) {
                                $nombre_suc = $emp['sucursal_nombre'] ?? 'Sin sucursal';
                                if ($nombre_suc !== $sucursal_actual) {
                                    // Cerrar el grupo anterior antes de abrir el siguiente
                                    if ($grupo_abierto) {
                                        echo '</optgroup>';
                                    }
                                    $sucursal_actual = $nombre_suc;
                                    $grupo_abierto = true;
                                    echo '<optgroup label="' . htmlspecialchars($nombre_suc) . '">';
                                }
                                $sel = ((int)$emp['id'] === (int)$asignacion['id_empleado']) ? 'selected' : '';
                                $inactivo = $emp['estado'] === 'Inactivo' ? ' (inactivo)' : '';
                                $dni = htmlspecialchars($emp['dni']);
                                $etiqueta = htmlspecialchars($emp['apellidos'] . ', ' . $emp['nombres']) . " (DNI: {$dni}){$inactivo}";
                                echo "<option value='{$emp['id']}' {$sel}>{$etiqueta}</option>";
                            }
                            if ($grupo_abierto) {
                                echo '</optgroup>';
                            }
                        } else {
                            echo '<option value="">No hay empleados registrados</option>';
                        }
                        ?>
                    </select>
                    <div class="form-text">Se listan todos los empleados, agrupados por sucursal.</div>
                </div>

                <div class="col-12">
                    <label class="form-label fw-bold">Observaciones de la Entrega</label>
                    <textarea class="form-control" name="observaciones_entrega" rows="3" placeholder="Ej: Se entrega con cargador y maletín."><?php echo htmlspecialchars($asignacion['observaciones_entrega'] ?? ''); ?></textarea>
                </div>
            </div>

            <hr class="my-4">

            <div class="d-flex justify-content-end gap-2">
                <a href="asignaciones.php" class="btn btn-secondary">Cancelar</a>
                <button type="submit" class="btn btn-primary px-4">
                    <i class="bi bi-save me-2"></i> Guardar Cambios
                </button>
            </div>
        </form>
    </div>
</div>

<?php require_once '../templates/footer.php'; ?>