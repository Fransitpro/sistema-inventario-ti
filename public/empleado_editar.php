<?php
require_once '../templates/header.php';

// 1. Validar el ID del empleado
$id_empleado = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$id_empleado) {
    echo '<div class="alert alert-danger">Error: ID de empleado no válido.</div>';
    require_once '../templates/footer.php';
    exit();
}

// 2. Lógica para procesar la ACTUALIZACIÓN (cuando se guarda)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Recoger datos del formulario
    $id_sucursal = $_POST['id_sucursal'] ?? null;
    $dni = trim($_POST['dni'] ?? '');
    $nombres = trim($_POST['nombres'] ?? '');
    $apellidos = trim($_POST['apellidos'] ?? '');
    $id_area = $_POST['id_area'] ?? null;
    $id_cargo = $_POST['id_cargo'] ?? null;
    $estado = $_POST['estado'] ?? 'Activo';

    // Validación
    if (empty($id_sucursal) || empty($dni) || empty($nombres) || empty($apellidos) || empty($id_area) || empty($id_cargo)) {
        $error_message = "Error: Todos los campos marcados con * son obligatorios.";
    } else {
        // Preparar y ejecutar la actualización
        $sql_update = "UPDATE empleados SET id_sucursal = ?, dni = ?, nombres = ?, apellidos = ?, id_area = ?, id_cargo = ?, estado = ? WHERE id = ?";
        $stmt_update = $conexion->prepare($sql_update);
        $stmt_update->bind_param("isssiisi", $id_sucursal, $dni, $nombres, $apellidos, $id_area, $id_cargo, $estado, $id_empleado);
        
        if ($stmt_update->execute()) {
            header("Location: empleados.php?status=empleado_editado");
            exit();
        } else {
            $error_message = "Error al actualizar el empleado: " . $stmt_update->error;
        }
        $stmt_update->close();
    }
}

// 3. Cargar datos actuales del empleado para pre-llenar el formulario
$stmt_select = $conexion->prepare("SELECT * FROM empleados WHERE id = ?");
$stmt_select->bind_param("i", $id_empleado);
$stmt_select->execute();
$empleado = $stmt_select->get_result()->fetch_assoc();
$stmt_select->close();

if (!$empleado) {
    echo '<div class="alert alert-warning">Empleado no encontrado.</div>';
    require_once '../templates/footer.php';
    exit();
}

// 4. Cargar catálogos para los dropdowns
$sucursales = $conexion->query("SELECT id, nombre FROM sucursales WHERE estado = 'Activo' ORDER BY nombre");
$areas = $conexion->query("SELECT id, nombre FROM areas WHERE estado = 'Activo' ORDER BY nombre");
// Cargar los cargos que pertenecen al área actual del empleado
$cargos_actuales = $conexion->query("SELECT id, nombre FROM cargos WHERE id_area = " . (int)$empleado['id_area'] . " AND estado = 'Activo' ORDER BY nombre");

?>

<h1 class="h2 mb-4">Editar Empleado</h1>

<?php if (isset($error_message)): ?>
    <div class="alert alert-danger"><?php echo htmlspecialchars($error_message); ?></div>
<?php endif; ?>

<div class="card">
    <div class="card-header">
        Datos del Empleado
    </div>
    <div class="card-body">
        <form action="empleado_editar.php?id=<?php echo $id_empleado; ?>" method="POST">
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label for="id_sucursal" class="form-label">Sucursal *</label>
                    <select class="form-select" id="id_sucursal" name="id_sucursal" required>
                        <option value="">Seleccione sucursal...</option>
                        <?php if ($sucursales): while ($s = $sucursales->fetch_assoc()): ?>
                            <option value="<?php echo $s['id']; ?>" <?php if ($s['id'] == $empleado['id_sucursal']) echo 'selected'; ?>>
                                <?php echo htmlspecialchars($s['nombre']); ?>
                            </option>
                        <?php endwhile; endif; ?>
                    </select>
                </div>
            </div>

            <div class="row">
                <div class="col-md-4 mb-3">
                    <label for="dni" class="form-label">DNI *</label>
                    <input type="text" class="form-control" id="dni" name="dni" maxlength="8" required pattern="[0-9]{8}" value="<?php echo htmlspecialchars($empleado['dni']); ?>">
                </div>
                <div class="col-md-4 mb-3">
                    <label for="nombres" class="form-label">Nombres *</label>
                    <input type="text" class="form-control" id="nombres" name="nombres" required value="<?php echo htmlspecialchars($empleado['nombres']); ?>">
                </div>
                 <div class="col-md-4 mb-3">
                    <label for="apellidos" class="form-label">Apellidos *</label>
                    <input type="text" class="form-control" id="apellidos" name="apellidos" required value="<?php echo htmlspecialchars($empleado['apellidos']); ?>">
                </div>
            </div>

            <div class="row">
                 <div class="col-md-6 mb-3">
                    <label for="id_area" class="form-label">Área *</label>
                    <select class="form-select" id="id_area" name="id_area" required>
                        <option value="">Seleccione área...</option>
                         <?php if ($areas): while ($a = $areas->fetch_assoc()): ?>
                            <option value="<?php echo $a['id']; ?>" <?php if ($a['id'] == $empleado['id_area']) echo 'selected'; ?>>
                                <?php echo htmlspecialchars($a['nombre']); ?>
                            </option>
                        <?php endwhile; endif; ?>
                    </select>
                </div>
                <div class="col-md-6 mb-3">
                    <label for="id_cargo" class="form-label">Cargo *</label>
                    <select class="form-select" id="id_cargo" name="id_cargo" required>
                        <option value="">Seleccione un área...</option>
                        <?php if ($cargos_actuales): while ($c = $cargos_actuales->fetch_assoc()): ?>
                            <option value="<?php echo $c['id']; ?>" <?php if ($c['id'] == $empleado['id_cargo']) echo 'selected'; ?>>
                                <?php echo htmlspecialchars($c['nombre']); ?>
                            </option>
                        <?php endwhile; endif; ?>
                    </select>
                </div>
            </div>

             <div class="mb-3">
                <label for="estado" class="form-label">Estado *</label>
                <select class="form-select" id="estado" name="estado" required>
                    <option value="Activo" <?php if ($empleado['estado'] == 'Activo') echo 'selected'; ?>>Activo</option>
                    <option value="Inactivo" <?php if ($empleado['estado'] == 'Inactivo') echo 'selected'; ?>>Inactivo</option>
                </select>
            </div>

            <hr>

            <div class="d-flex justify-content-end gap-2">
                <a href="empleados.php" class="btn btn-secondary">Cancelar</a>
                <button type="submit" class="btn btn-primary">Guardar Cambios</button>
            </div>
        </form>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const areaSelect = document.getElementById('id_area');
    const cargoSelect = document.getElementById('id_cargo');

    areaSelect.addEventListener('change', function() {
        const areaId = this.value; 
        cargoSelect.disabled = true; 
        cargoSelect.innerHTML = '<option value="">Cargando...</option>'; 

        if (areaId) {
            fetch('obtener_cargos.php?id_area=' + areaId)
                .then(response => {
                    if (!response.ok) {
                        throw new Error('Network response was not ok');
                    }
                    return response.json(); 
                })
                .then(cargos => {
                    cargoSelect.innerHTML = '<option value="">Seleccione cargo...</option>'; 
                    if (cargos && !cargos.error && cargos.length > 0) {
                        cargos.forEach(cargo => {
                            const option = document.createElement('option');
                            option.value = cargo.id;
                            option.textContent = cargo.nombre;
                            cargoSelect.appendChild(option);
                        });
                        cargoSelect.disabled = false; 
                    } else if (cargos && cargos.length === 0) {
                        cargoSelect.innerHTML = '<option value="">No hay cargos para esta área</option>';
                    } else {
                        throw new Error(cargos.error || 'Respuesta inválida');
                    }
                })
                .catch(error => {
                    console.error('Error fetching cargos:', error);
                    cargoSelect.innerHTML = '<option value="">Error al cargar cargos</option>';
                });
        } else {
            cargoSelect.innerHTML = '<option value="">Seleccione un área primero...</option>';
            cargoSelect.disabled = true;
        }
    });
});
</script>

<?php require_once '../templates/footer.php'; ?>