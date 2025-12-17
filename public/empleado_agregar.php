<?php
// 1. LÓGICA DE NEGOCIO PRIMERO
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once '../config/database.php';

// Validar sesión
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$mensaje = '';
$tipo_mensaje = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Recibir datos
    $id_sucursal = $_POST['id_sucursal'];
    $dni = trim($_POST['dni']);
    $nombres = trim($_POST['nombres']);
    $apellidos = trim($_POST['apellidos']);
    $id_area = $_POST['id_area'];
    $id_cargo = $_POST['id_cargo'];
    $estado = 'Activo'; // Por defecto

    if (empty($dni) || empty($nombres) || empty($apellidos) || empty($id_sucursal) || empty($id_area) || empty($id_cargo)) {
        $mensaje = "Por favor complete todos los campos obligatorios (*).";
        $tipo_mensaje = "danger";
    } else {
        // Verificar duplicado
        $check = $conexion->prepare("SELECT id FROM empleados WHERE dni = ?");
        $check->bind_param("s", $dni);
        $check->execute();
        $check->store_result();

        if ($check->num_rows > 0) {
            $mensaje = "El DNI ya está registrado.";
            $tipo_mensaje = "warning";
        } else {
            // Insertar
            $sql = "INSERT INTO empleados (id_sucursal, dni, nombres, apellidos, id_area, id_cargo, estado) VALUES (?, ?, ?, ?, ?, ?, ?)";
            $stmt = $conexion->prepare($sql);
            $stmt->bind_param("isssiis", $id_sucursal, $dni, $nombres, $apellidos, $id_area, $id_cargo, $estado);

            if ($stmt->execute()) {
                header("Location: empleados.php?msg=guardado");
                exit();
            } else {
                $mensaje = "Error al guardar: " . $conexion->error;
                $tipo_mensaje = "danger";
            }
            $stmt->close();
        }
        $check->close();
    }
}

require_once '../templates/header.php';

// Cargar listas iniciales
$sucursales = $conexion->query("SELECT id, nombre FROM sucursales WHERE estado = 'Activo' ORDER BY nombre");
$areas = $conexion->query("SELECT id, nombre FROM areas WHERE estado = 'Activo' ORDER BY nombre");
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h2">Registrar Nuevo Empleado</h1>
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
        <h5 class="mb-0 text-primary fw-bold"><i class="bi bi-person-plus-fill me-2"></i> Datos del Empleado</h5>
    </div>
    <div class="card-body p-4">
        <form action="empleado_agregar.php" method="POST">
            <div class="row g-3">
                
                <div class="col-md-12">
                    <label class="form-label fw-bold">Sucursal <span class="text-danger">*</span></label>
                    <select class="form-select" name="id_sucursal" required>
                        <option value="">Seleccione sucursal...</option>
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
                    <label class="form-label fw-bold">DNI <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" name="dni" required placeholder="Número de documento">
                </div>

                <div class="col-md-4">
                    <label class="form-label fw-bold">Nombres <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" name="nombres" required>
                </div>

                <div class="col-md-4">
                    <label class="form-label fw-bold">Apellidos <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" name="apellidos" required>
                </div>

                <div class="col-md-6">
                    <label class="form-label fw-bold">Área <span class="text-danger">*</span></label>
                    <select class="form-select" name="id_area" id="id_area" required>
                        <option value="">Seleccione área...</option>
                        <?php 
                        if ($areas) {
                            while ($row = $areas->fetch_assoc()) {
                                echo "<option value='{$row['id']}'>{$row['nombre']}</option>";
                            }
                        }
                        ?>
                    </select>
                </div>

                <div class="col-md-6">
                    <label class="form-label fw-bold">Cargo <span class="text-danger">*</span></label>
                    <select class="form-select" name="id_cargo" id="id_cargo" required>
                        <option value="">Seleccione un área primero</option>
                    </select>
                </div>

                <div class="col-md-12">
                    <label class="form-label fw-bold">Estado</label>
                    <select class="form-select" name="estado" disabled>
                        <option value="Activo" selected>Activo (Por defecto)</option>
                    </select>
                </div>

            </div>

            <hr class="my-4">

            <div class="d-flex justify-content-end gap-2">
                <a href="empleados.php" class="btn btn-secondary">Cancelar</a>
                <button type="submit" class="btn btn-primary px-4">Registrar Empleado</button>
            </div>
        </form>
    </div>
</div>

<?php require_once '../templates/footer.php'; ?>

<script>
$(document).ready(function() {
    $('#id_area').on('change', function() {
        var idArea = $(this).val();
        var cargoSelect = $('#id_cargo');

        // Resetear select
        cargoSelect.html('<option value="">Cargando cargos...</option>');

        if (idArea) {
            $.ajax({
                url: 'obtener_cargos.php',
                type: 'POST',
                data: { id_area: idArea },
                success: function(response) {
                    cargoSelect.html(response);
                },
                error: function() {
                    cargoSelect.html('<option value="">Error al cargar cargos</option>');
                }
            });
        } else {
            cargoSelect.html('<option value="">Seleccione un área primero</option>');
        }
    });
});
</script>