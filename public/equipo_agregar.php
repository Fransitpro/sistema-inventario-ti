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

// Elimina los indices UNIQUE de codigo_inventario, cod_pat y numero_serie para que
// valores como S/S puedan repetirse sin restricciones en la base de datos.
function quitar_indices_unicos_equipos($conexion) {
    try {
        $res = $conexion->query("SHOW INDEX FROM equipos");
    } catch (Throwable $e) {
        return false;
    }
    if (!$res) {
        return false;
    }

    $permitidas = ['codigo_inventario', 'cod_pat', 'numero_serie'];
    $indices = [];
    while ($i = $res->fetch_assoc()) {
        if ((int) $i['Non_unique'] === 0) {
            $indices[$i['Key_name']][] = $i['Column_name'];
        }
    }
    $res->free();

    $eliminados = false;
    foreach ($indices as $nombre => $cols) {
        if (count($cols) === 1 && in_array($cols[0], $permitidas, true)) {
            try {
                if ($conexion->query("ALTER TABLE equipos DROP INDEX `" . $conexion->real_escape_string($nombre) . "`")) {
                    $eliminados = true;
                    // Se conserva un indice no unico para no perder rendimiento en las busquedas
                    try {
                        $conexion->query("ALTER TABLE equipos ADD INDEX `idx_equipos_" . $cols[0] . "` (`" . $cols[0] . "`)");
                    } catch (Throwable $e) {
                        // el indice no unico es opcional
                    }
                }
            } catch (Throwable $e) {
                // sin permisos para alterar la tabla
            }
        }
    }

    return $eliminados;
}

function registrar_equipo($conexion, $datos) {
    $sql = "INSERT INTO equipos (
                codigo_inventario, numero_serie, ip, id_sucursal, id_tipo_equipo, id_marca, id_modelo,
                fecha_adquisicion, tipo_adquisicion, caracteristicas, proveedor, observaciones, estado,
                cod_pat, dimension, color, anio_inventario
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

    try {
        $stmt = $conexion->prepare($sql);
        $stmt->bind_param("sssiiiissssssssss", ...$datos);
        $ok = $stmt->execute();
        $errno = $stmt->errno;
        $stmt->close();
    } catch (Throwable $e) {
        $ok = false;
        $errno = (int) $e->getCode();
    }

    return [$ok, $errno];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Recibir TODOS los datos del formulario
    $id_sucursal = $_POST['id_sucursal'];
    $codigo = trim($_POST['codigo_inventario']);
    $serie = trim($_POST['numero_serie']);
    $id_tipo = $_POST['id_tipo_equipo'];
    $id_marca = $_POST['id_marca'];
    $id_modelo = $_POST['id_modelo'];
    $tipo_adq = $_POST['tipo_adquisicion'];
    $caracteristicas = trim($_POST['caracteristicas']);
    $fecha = $_POST['fecha_adquisicion'];
    $proveedor = trim($_POST['proveedor']);
    $observaciones = trim($_POST['observaciones']);
    $estado = 'Disponible'; // Estado inicial por defecto
    $cod_pat = mb_strtoupper(trim($_POST['cod_pat']), 'UTF-8');
    $dimension = trim($_POST['dimension']);
    $color = trim($_POST['color']);
    $ip = trim($_POST['ip']);
    $anio_inventario = trim($_POST['anio_inventario'] ?? '');

    // Validación básica (sin control de duplicados: el código de inventario puede repetirse, ej. S/S)
    if (empty($codigo) || empty($id_sucursal) || empty($id_tipo) || empty($id_marca)) {
        $mensaje = "Por favor complete los campos obligatorios marcados con (*).";
        $tipo_mensaje = "danger";
    } else {
        $datos = [$codigo, $serie, $ip, $id_sucursal, $id_tipo, $id_marca, $id_modelo,
                  $fecha, $tipo_adq, $caracteristicas, $proveedor, $observaciones, $estado,
                  $cod_pat, $dimension, $color, $anio_inventario];

        list($guardado, $errno) = registrar_equipo($conexion, $datos);

        // Si la base de datos aun tiene un indice UNIQUE, se elimina y se reintenta
        if (!$guardado && $errno === 1062 && quitar_indices_unicos_equipos($conexion)) {
            list($guardado) = registrar_equipo($conexion, $datos);
        }

        if ($guardado) {
            header("Location: equipos.php?msg=guardado");
            exit();
        }

        $mensaje = "Error al guardar: " . $conexion->error;
        $tipo_mensaje = "danger";
    }
}


// 2. INCLUIR HEADER
require_once '../templates/header.php';

// Consultas para llenar los selectores (Modelos se carga vía AJAX ahora)
$sucursales = $conexion->query("SELECT id, nombre FROM sucursales WHERE estado = 'Activo' ORDER BY nombre");
$tipos = $conexion->query("SELECT id, nombre FROM tipos_equipo WHERE estado = 'Activo' ORDER BY nombre");
$marcas = $conexion->query("SELECT id, nombre FROM marcas WHERE estado = 'Activo' ORDER BY nombre");
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h2">Registrar Nuevo Equipo</h1>
    <a href="equipos.php" class="btn btn-secondary">
        <i class="bi bi-arrow-left me-2"></i> Volver
    </a>
</div>

<?php if (!empty($mensaje)): ?>
    <div class="alert alert-<?php echo $tipo_mensaje; ?> alert-dismissible fade show shadow-sm" role="alert">
        <?php echo $mensaje; ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<div class="card shadow-sm border-0">
    <div class="card-header bg-white py-3">
        <h5 class="mb-0 text-primary fw-bold"><i class="bi bi-pc-display me-2"></i> Información del Equipo</h5>
    </div>
    <div class="card-body p-4">
        <form action="equipo_agregar.php" method="POST" autocomplete="off">
            <div class="row g-3">
                
                <div class="col-md-4">
                    <label class="form-label fw-bold">Sucursal <span class="text-danger">*</span></label>
                    <select class="form-select" name="id_sucursal" required>
                        <option value="">Seleccione...</option>
                        <?php 
                        $sucursal_fija = $_SESSION['user_sucursal_id'] ?? null;
                        if ($sucursales) {
                            mysqli_data_seek($sucursales, 0);
                            while ($row = $sucursales->fetch_assoc()) {
                                $selected = ($sucursal_fija == $row['id']) ? 'selected' : '';
                                echo "<option value='{$row['id']}' $selected>{$row['nombre']}</option>";
                            }
                        }
                        ?>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label fw-bold">Código Inventario <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" name="codigo_inventario" required placeholder="Ej: INV-001 o S/S">
                    <div class="form-text">Use <strong>S/S</strong> si el equipo no tiene código.</div>
                </div>
                <div class="col-md-4">
                    <label class="form-label fw-bold">Año de Inventario</label>
                    <input type="text" class="form-control" name="anio_inventario" maxlength="4" placeholder="Ej: 2026">
                </div>
                <div class="col-md-4">
                <label class="form-label fw-bold">Código Patrimonial</label>
                <input type="text" class="form-control" name="cod_pat" maxlength="10" style="text-transform: uppercase;" placeholder="Ej: CP-001 o S/S">
                <div class="form-text">Admite <strong>S/S</strong> si no aplica.</div>
                </div>

                <div class="col-12">
                    <label class="form-label fw-bold">Número de Serie</label>
                    <input type="text" class="form-control" name="numero_serie" placeholder="Ingrese el número de serie o S/S">
                    <div class="form-text">Admite <strong>S/S</strong> si el equipo no tiene número de serie.</div>
                </div>

                <div class="col-md-4">
                    <label class="form-label fw-bold">Tipo de Equipo <span class="text-danger">*</span></label>
                    <select class="form-select" name="id_tipo_equipo" required>
                        <option value="">Seleccione...</option>
                        <?php if($tipos) { mysqli_data_seek($tipos,0); while($r=$tipos->fetch_assoc()){echo "<option value='{$r['id']}'>{$r['nombre']}</option>";}} ?>
                    </select>
                </div>
                
                <div class="col-md-4">
                    <label class="form-label fw-bold">Marca <span class="text-danger">*</span></label>
                    <select class="form-select" name="id_marca" id="id_marca" required>
                        <option value="">Seleccione...</option>
                        <?php if($marcas) { mysqli_data_seek($marcas,0); while($r=$marcas->fetch_assoc()){echo "<option value='{$r['id']}'>{$r['nombre']}</option>";}} ?>
                    </select>
                </div>

                <div class="col-md-4">
                    <label class="form-label fw-bold">Modelo</label>
                    <select class="form-select" name="id_modelo" id="id_modelo">
                        <option value="">Seleccione una marca primero</option>
                    </select>
                </div>

                <div class="col-md-6">
                    <label class="form-label fw-bold">Tipo de Adquisición</label>
                    <select class="form-select" name="tipo_adquisicion">
                        <option value="Propio">Propio</option>
                        <option value="Alquilado">Alquilado</option>
                        <option value="Leasing">Leasing</option>
                        <option value="Prestamo">Préstamo</option>
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-bold">Características</label>
                    <input type="text" class="form-control" name="caracteristicas" placeholder="Ej: Core i5, 16GB RAM...">
                </div>     

                <div class="col-md-4">
                    <label class="form-label fw-bold">Dirección IP</label>
                    <input type="text" class="form-control" name="ip" maxlength="45" placeholder="Ej: 192.168.1.10">
                </div>

                <div class="col-md-4">
                    <label class="form-label fw-bold">Dimensión</label>
                    <input type="text" class="form-control" name="dimension" maxlength="50" placeholder="Ej: 35x25x10 cm">
                </div>
                
                <div class="col-md-4">
                    <label class="form-label fw-bold">Color</label>
                    <input type="text" class="form-control" name="color" maxlength="50" placeholder="Ej: Negro">
                </div>
                
                <div class="col-md-6">
                    <label class="form-label fw-bold">Fecha de Adquisición</label>
                    <input type="date" class="form-control" name="fecha_adquisicion" value="<?php echo date('Y-m-d'); ?>">
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-bold">Proveedor</label>
                    <input type="text" class="form-control" name="proveedor" placeholder="Nombre del proveedor">
                </div>

                <div class="col-12">
                    <label class="form-label fw-bold">Observaciones</label>
                    <textarea class="form-control" name="observaciones" rows="3"></textarea>
                </div>

            </div>

            <hr class="my-4">

            <div class="d-flex justify-content-end gap-2">
                <a href="equipos.php" class="btn btn-secondary">Cancelar</a>
                <button type="submit" class="btn btn-primary px-4">Registrar Equipo</button>
            </div>
        </form>
    </div>
</div>

<?php require_once '../templates/footer.php'; ?>

<script>
$(document).ready(function() {
    $('#id_marca').on('change', function() {
        var idMarca = $(this).val();
        
        // Limpiar y mostrar "Cargando..."
        $('#id_modelo').html('<option value="">Cargando modelos...</option>');

        if (idMarca) {
            $.ajax({
                url: 'obtener_modelos.php',
                type: 'POST',
                data: { id_marca: idMarca },
                success: function(response) {
                    $('#id_modelo').html(response);
                },
                error: function() {
                    $('#id_modelo').html('<option value="">Error al cargar modelos</option>');
                }
            });
        } else {
            $('#id_modelo').html('<option value="">Seleccione una marca primero</option>');
        }
    });
});
</script>