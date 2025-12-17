<?php
// 1. LÓGICA DE NEGOCIO PRIMERO
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once '../config/database.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header("Location: equipos.php");
    exit();
}

$id_equipo = (int)$_GET['id'];
$mensaje = '';
$tipo_mensaje = '';

// 2. PROCESAR ACTUALIZACIÓN
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
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
    
    if (empty($codigo) || empty($id_sucursal) || empty($id_tipo) || empty($id_marca)) {
        $mensaje = "Por favor complete los campos obligatorios.";
        $tipo_mensaje = "danger";
    } else {
        $check = $conexion->prepare("SELECT id FROM equipos WHERE codigo_inventario = ? AND id != ?");
        $check->bind_param("si", $codigo, $id_equipo);
        $check->execute();
        $check->store_result();

        if ($check->num_rows > 0) {
            $mensaje = "El código de inventario ya existe.";
            $tipo_mensaje = "warning";
        } else {
            $sql = "UPDATE equipos SET 
                    codigo_inventario = ?, 
                    numero_serie = ?, 
                    id_sucursal = ?, 
                    id_tipo_equipo = ?, 
                    id_marca = ?, 
                    id_modelo = ?, 
                    fecha_adquisicion = ?,
                    tipo_adquisicion = ?,
                    caracteristicas = ?,
                    proveedor = ?,
                    observaciones = ?
                    WHERE id = ?";
            
            $stmt = $conexion->prepare($sql);
            $stmt->bind_param("ssiiiisssssi", 
                $codigo, $serie, $id_sucursal, $id_tipo, $id_marca, $id_modelo, 
                $fecha, $tipo_adq, $caracteristicas, $proveedor, $observaciones, $id_equipo
            );

            if ($stmt->execute()) {
                header("Location: equipos.php?msg=actualizado");
                exit();
            } else {
                $mensaje = "Error al actualizar: " . $conexion->error;
                $tipo_mensaje = "danger";
            }
            $stmt->close();
        }
        $check->close();
    }
}

// 3. LEER DATOS ACTUALES
$stmt = $conexion->prepare("SELECT * FROM equipos WHERE id = ?");
$stmt->bind_param("i", $id_equipo);
$stmt->execute();
$resultado = $stmt->get_result();
$equipo = $resultado->fetch_assoc();
$stmt->close();

if (!$equipo) {
    header("Location: equipos.php?msg=no_encontrado");
    exit();
}

require_once '../templates/header.php';

// CARGAR CATÁLOGOS
$sucursales = $conexion->query("SELECT id, nombre FROM sucursales WHERE estado = 'Activo' ORDER BY nombre");
$tipos = $conexion->query("SELECT id, nombre FROM tipos_equipo WHERE estado = 'Activo' ORDER BY nombre");
$marcas = $conexion->query("SELECT id, nombre FROM marcas WHERE estado = 'Activo' ORDER BY nombre");

// CORRECCIÓN: Cargar solo los modelos de la marca actual para la vista inicial
$id_marca_actual = $equipo['id_marca'];
$stmt_modelos = $conexion->prepare("SELECT id, nombre FROM modelos WHERE id_marca = ? AND estado = 'Activo' ORDER BY nombre");
$stmt_modelos->bind_param("i", $id_marca_actual);
$stmt_modelos->execute();
$modelos = $stmt_modelos->get_result();
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h2">Editar Equipo</h1>
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
        <h5 class="mb-0 text-primary fw-bold"><i class="bi bi-pencil-square me-2"></i> Modificar Información</h5>
    </div>
    <div class="card-body p-4">
        <form action="equipo_editar.php?id=<?php echo $id_equipo; ?>" method="POST">
            <div class="row g-3">
                
                <div class="col-md-6">
                    <label class="form-label fw-bold">Sucursal <span class="text-danger">*</span></label>
                    <select class="form-select" name="id_sucursal" required>
                        <option value="">Seleccione...</option>
                        <?php 
                        if ($sucursales) {
                            mysqli_data_seek($sucursales, 0);
                            while ($row = $sucursales->fetch_assoc()) {
                                $sel = ($equipo['id_sucursal'] == $row['id']) ? 'selected' : '';
                                echo "<option value='{$row['id']}' $sel>{$row['nombre']}</option>";
                            }
                        }
                        ?>
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-bold">Código Inventario <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" name="codigo_inventario" value="<?php echo htmlspecialchars($equipo['codigo_inventario']); ?>" required>
                </div>

                <div class="col-md-6">
                    <label class="form-label fw-bold">Número de Serie</label>
                    <input type="text" class="form-control" name="numero_serie" value="<?php echo htmlspecialchars($equipo['numero_serie']); ?>">
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-bold">Estado del Equipo <span class="text-danger">*</span></label>
                    <select class="form-select bg-light" disabled>
                        <option><?php echo htmlspecialchars($equipo['estado']); ?> (No se puede cambiar aquí)</option>
                    </select>
                </div>

                <div class="col-md-4">
                    <label class="form-label fw-bold">Tipo <span class="text-danger">*</span></label>
                    <select class="form-select" name="id_tipo_equipo" required>
                        <option value="">Seleccione...</option>
                        <?php 
                        if ($tipos) {
                            mysqli_data_seek($tipos, 0);
                            while ($row = $tipos->fetch_assoc()) {
                                $sel = ($equipo['id_tipo_equipo'] == $row['id']) ? 'selected' : '';
                                echo "<option value='{$row['id']}' $sel>{$row['nombre']}</option>";
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
                            mysqli_data_seek($marcas, 0);
                            while ($row = $marcas->fetch_assoc()) {
                                $sel = ($equipo['id_marca'] == $row['id']) ? 'selected' : '';
                                echo "<option value='{$row['id']}' $sel>{$row['nombre']}</option>";
                            }
                        }
                        ?>
                    </select>
                </div>

                <div class="col-md-4">
                    <label class="form-label fw-bold">Modelo</label>
                    <select class="form-select" name="id_modelo" id="id_modelo">
                        <option value="">Seleccione...</option>
                        <?php 
                        if ($modelos && $modelos->num_rows > 0) {
                            while ($row = $modelos->fetch_assoc()) {
                                $sel = ($equipo['id_modelo'] == $row['id']) ? 'selected' : '';
                                echo "<option value='{$row['id']}' $sel>{$row['nombre']}</option>";
                            }
                        } else {
                            echo "<option value=''>Sin modelos para esta marca</option>";
                        }
                        ?>
                    </select>
                </div>

                <div class="col-md-6">
                    <label class="form-label fw-bold">Tipo de Adquisición</label>
                    <select class="form-select" name="tipo_adquisicion">
                        <option value="Propio" <?php echo ($equipo['tipo_adquisicion'] == 'Propio') ? 'selected' : ''; ?>>Propio</option>
                        <option value="Alquilado" <?php echo ($equipo['tipo_adquisicion'] == 'Alquilado') ? 'selected' : ''; ?>>Alquilado</option>
                        <option value="Leasing" <?php echo ($equipo['tipo_adquisicion'] == 'Leasing') ? 'selected' : ''; ?>>Leasing</option>
                        <option value="Prestamo" <?php echo ($equipo['tipo_adquisicion'] == 'Prestamo') ? 'selected' : ''; ?>>Préstamo</option>
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-bold">Características</label>
                    <input type="text" class="form-control" name="caracteristicas" value="<?php echo htmlspecialchars($equipo['caracteristicas'] ?? ''); ?>">
                </div>

                <div class="col-md-6">
                    <label class="form-label fw-bold">Fecha de Adquisición</label>
                    <input type="date" class="form-control" name="fecha_adquisicion" value="<?php echo $equipo['fecha_adquisicion']; ?>">
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-bold">Proveedor</label>
                    <input type="text" class="form-control" name="proveedor" value="<?php echo htmlspecialchars($equipo['proveedor'] ?? ''); ?>">
                </div>

                <div class="col-12">
                    <label class="form-label fw-bold">Observaciones</label>
                    <textarea class="form-control" name="observaciones" rows="3"><?php echo htmlspecialchars($equipo['observaciones'] ?? ''); ?></textarea>
                </div>

            </div>

            <hr class="my-4">

            <div class="d-flex justify-content-end gap-2">
                <a href="equipos.php" class="btn btn-secondary">Cancelar</a>
                <button type="submit" class="btn btn-primary px-4">Guardar Cambios</button>
            </div>
        </form>
    </div>
</div>

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