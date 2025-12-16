<?php
require_once '../templates/header.php';

// Cargar sucursales y áreas para los dropdowns iniciales
$sucursales = $conexion->query("SELECT id, nombre FROM sucursales WHERE estado = 'Activo' ORDER BY nombre");
$areas = $conexion->query("SELECT id, nombre FROM areas WHERE estado = 'Activo' ORDER BY nombre");

?>

<h1 class="h2 mb-4">Registrar Nuevo Empleado</h1>

<div class="card">
    <div class="card-header">
        Datos del Empleado
    </div>
    <div class="card-body">
        <form action="procesar_empleado.php?accion=agregar" method="POST">
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label for="id_sucursal" class="form-label">Sucursal *</label>
                    <select class="form-select" id="id_sucursal" name="id_sucursal" required>
                        <option value="">Seleccione sucursal...</option>
                        <?php if ($sucursales): while ($s = $sucursales->fetch_assoc()): ?>
                            <option value="<?php echo $s['id']; ?>"><?php echo htmlspecialchars($s['nombre']); ?></option>
                        <?php endwhile; endif; ?>
                    </select>
                </div>
            </div>

            <div class="row">
                <div class="col-md-4 mb-3">
                    <label for="dni" class="form-label">DNI *</label>
                    <input type="text" class="form-control" id="dni" name="dni" maxlength="8" required pattern="[0-9]{8}">
                </div>
                <div class="col-md-4 mb-3">
                    <label for="nombres" class="form-label">Nombres *</label>
                    <input type="text" class="form-control" id="nombres" name="nombres" required>
                </div>
                 <div class="col-md-4 mb-3">
                    <label for="apellidos" class="form-label">Apellidos *</label>
                    <input type="text" class="form-control" id="apellidos" name="apellidos" required>
                </div>
            </div>

            <div class="row">
                 <div class="col-md-6 mb-3">
                    <label for="id_area" class="form-label">Área *</label>
                    <select class="form-select" id="id_area" name="id_area" required>
                        <option value="">Seleccione área...</option>
                         <?php if ($areas): while ($a = $areas->fetch_assoc()): ?>
                            <option value="<?php echo $a['id']; ?>"><?php echo htmlspecialchars($a['nombre']); ?></option>
                        <?php endwhile; endif; ?>
                    </select>
                </div>
                <div class="col-md-6 mb-3">
                    <label for="id_cargo" class="form-label">Cargo *</label>
                    <select class="form-select" id="id_cargo" name="id_cargo" required disabled>
                        <option value="">Seleccione un área primero...</option>
                    </select>
                </div>
            </div>

             <div class="mb-3">
                <label for="estado" class="form-label">Estado *</label>
                <select class="form-select" id="estado" name="estado" required>
                    <option value="Activo" selected>Activo</option>
                    <option value="Inactivo">Inactivo</option>
                </select>
            </div>

            <hr>

            <div class="d-flex justify-content-end gap-2">
                <a href="empleados.php" class="btn btn-secondary">Cancelar</a>
                <button type="submit" class="btn btn-primary">Registrar Empleado</button>
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
            // Llama al archivo PHP que creamos en el Paso 1
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