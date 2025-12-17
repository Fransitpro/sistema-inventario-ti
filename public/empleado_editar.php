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

// Validar ID
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header("Location: empleados.php");
    exit();
}

$id_empleado = (int)$_GET['id'];
$mensaje = '';
$tipo_mensaje = '';

// 2. PROCESAR ACTUALIZACIÓN
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id_sucursal = $_POST['id_sucursal'];
    $dni = trim($_POST['dni']);
    $nombres = trim($_POST['nombres']);
    $apellidos = trim($_POST['apellidos']);
    $id_area = $_POST['id_area'];
    $id_cargo = $_POST['id_cargo']; // Este vendrá actualizado del select dinámico
    $estado = $_POST['estado'];

    if (empty($dni) || empty($nombres) || empty($apellidos) || empty($id_sucursal)) {
        $mensaje = "Por favor complete los campos obligatorios.";
        $tipo_mensaje = "danger";
    } else {
        $check = $conexion->prepare("SELECT id FROM empleados WHERE dni = ? AND id != ?");
        $check->bind_param("si", $dni, $id_empleado);
        $check->execute();
        $check->store_result();

        if ($check->num_rows > 0) {
            $mensaje = "El DNI ya está registrado por otro empleado.";
            $tipo_mensaje = "warning";
        } else {
            $sql = "UPDATE empleados SET 
                    id_sucursal = ?, 
                    dni = ?, 
                    nombres = ?, 
                    apellidos = ?, 
                    id_area = ?, 
                    id_cargo = ?, 
                    estado = ? 
                    WHERE id = ?";
            
            $stmt = $conexion->prepare($sql);
            $stmt->bind_param("isssiisi", $id_sucursal, $dni, $nombres, $apellidos, $id_area, $id_cargo, $estado, $id_empleado);

            if ($stmt->execute()) {
                header("Location: empleados.php?msg=actualizado");
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

// 3. OBTENER DATOS ACTUALES
$stmt = $conexion->prepare("SELECT * FROM empleados WHERE id = ?");
$stmt->bind_param("i", $id_empleado);
$stmt->execute();
$empleado = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$empleado) {
    header("Location: empleados.php?msg=no_encontrado");
    exit();
}

require_once '../templates/header.php';

// CARGAR LISTAS PARA SELECTS
$sucursales = $conexion->query("SELECT id, nombre FROM sucursales WHERE estado = 'Activo' ORDER BY nombre");
$areas = $conexion->query("SELECT id, nombre FROM areas WHERE estado = 'Activo' ORDER BY nombre");

// PRECARGA INTELIGENTE DE CARGOS:
// Cargamos SOLO los cargos que pertenecen al área actual del empleado
$id_area_actual = $empleado['id_area'];
$stmt_cargos = $conexion->prepare("SELECT id, nombre FROM cargos WHERE id_area = ? AND estado = 'Activo' ORDER BY nombre");
$stmt_cargos->bind_param("i", $id_area_actual);
$stmt_cargos->execute();
$cargos = $stmt_cargos->get_result();
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h2">Editar Empleado</h1>
    <a href="empleados.php" class="btn btn-secondary">
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
        <h5 class="mb-0 text-primary fw-bold"><i class="bi bi-person-lines-fill me-2"></i> Modificar Datos</h5>
    </div>
    <div class="card-body p-4">
        <form action="empleado_editar.php?id=<?php echo $id_empleado; ?>" method="POST">
            <div class="row g-3">
                
                <div class="col-md-6">
                    <label class="form-label fw-bold">Sucursal <span class="text-danger">*</span></label>
                    <select class="form-select" name="id_sucursal" required>
                        <option value="">Seleccione...</option>
                        <?php 
                        if ($sucursales) {
                            mysqli_data_seek($sucursales, 0);
                            while ($row = $sucursales->fetch_assoc()) {
                                $sel = ($empleado['id_sucursal'] == $row['id']) ? 'selected' : '';
                                echo "<option value='{$row['id']}' $sel>{$row['nombre']}</option>";
                            }
                        }
                        ?>
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-bold">DNI <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" name="dni" value="<?php echo htmlspecialchars($empleado['dni']); ?>" required>
                </div>

                <div class="col-md-6">
                    <label class="form-label fw-bold">Nombres <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" name="nombres" value="<?php echo htmlspecialchars($empleado['nombres']); ?>" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-bold">Apellidos <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" name="apellidos" value="<?php echo htmlspecialchars($empleado['apellidos']); ?>" required>
                </div>

                <div class="col-md-4">
                    <label class="form-label fw-bold">Área</label>
                    <select class="form-select" name="id_area" id="id_area" required>
                        <option value="">Seleccione...</option>
                        <?php 
                        if ($areas) {
                            mysqli_data_seek($areas, 0);
                            while ($row = $areas->fetch_assoc()) {
                                $sel = ($empleado['id_area'] == $row['id']) ? 'selected' : '';
                                echo "<option value='{$row['id']}' $sel>{$row['nombre']}</option>";
                            }
                        }
                        ?>
                    </select>
                </div>

                <div class="col-md-4">
                    <label class="form-label fw-bold">Cargo</label>
                    <select class="form-select" name="id_cargo" id="id_cargo" required>
                        <option value="">Seleccione...</option>
                        <?php 
                        // Cargamos los cargos que coinciden con el área actual (Precarga PHP)
                        if ($cargos && $cargos->num_rows > 0) {
                            while ($row = $cargos->fetch_assoc()) {
                                $sel = ($empleado['id_cargo'] == $row['id']) ? 'selected' : '';
                                echo "<option value='{$row['id']}' $sel>{$row['nombre']}</option>";
                            }
                        } else {
                            echo "<option value=''>Sin cargos asignados</option>";
                        }
                        ?>
                    </select>
                </div>

                <div class="col-md-4">
                    <label class="form-label fw-bold">Estado</label>
                    <select class="form-select" name="estado">
                        <option value="Activo" <?php echo ($empleado['estado'] == 'Activo') ? 'selected' : ''; ?>>Activo</option>
                        <option value="Inactivo" <?php echo ($empleado['estado'] == 'Inactivo') ? 'selected' : ''; ?>>Inactivo</option>
                    </select>
                </div>

            </div>

            <hr class="my-4">

            <div class="d-flex justify-content-end gap-2">
                <a href="empleados.php" class="btn btn-secondary">Cancelar</a>
                <button type="submit" class="btn btn-primary px-4">Guardar Cambios</button>
            </div>
        </form>
    </div>
</div>

<?php require_once '../templates/footer.php'; ?>

<script>
$(document).ready(function() {
    
    // Detectar cambio en el select de ÁREA
    $('#id_area').on('change', function() {
        var idArea = $(this).val();
        
        // Mensaje de carga visual
        $('#id_cargo').html('<option value="">Cargando cargos...</option>');

        if (idArea) {
            console.log("Enviando petición para área ID: " + idArea); // Debug
            
            $.ajax({
                url: 'obtener_cargos.php',
                type: 'POST',
                data: { id_area: idArea },
                success: function(response) {
                    console.log("Respuesta recibida"); // Debug
                    $('#id_cargo').html(response);
                },
                error: function() {
                    console.error("Error en AJAX"); // Debug
                    $('#id_cargo').html('<option value="">Error al cargar datos</option>');
                }
            });
        } else {
            $('#id_cargo').html('<option value="">Seleccione un área primero</option>');
        }
    });
});
</script>