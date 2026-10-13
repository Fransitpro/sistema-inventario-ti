<?php
require_once '../templates/header.php';

$es_admin_general = ($_SESSION['user_sucursal_id'] === null);
$id_sucursal_usuario = (int)$_SESSION['user_sucursal_id'];

// Cargar sucursales SOLO si es admin general
$sucursales = null;
if ($es_admin_general) {
    $sucursales = $conexion->query("SELECT id, nombre FROM sucursales WHERE estado = 'Activo' ORDER BY nombre");
}
?>

<h1 class="h2 mb-4">Asignar Equipo a Empleado</h1>

<div class="card">
    <div class="card-body">
        <form action="asignar_equipo.php" method="POST">
            
            <div class="mb-3">
                <label for="selectSucursal" class="form-label">Seleccionar Sucursal *</label>
                <select class="form-select" id="selectSucursal" name="id_sucursal" <?php echo !$es_admin_general ? 'disabled' : 'required'; ?>>
                    <?php if ($es_admin_general): ?>
                        <option value="">Seleccione...</option>
                        <?php while($s = $sucursales->fetch_assoc()): ?>
                            <option value="<?php echo $s['id']; ?>"><?php echo htmlspecialchars($s['nombre']); ?></option>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <?php $nombre_sucursal = $conexion->query("SELECT nombre FROM sucursales WHERE id = $id_sucursal_usuario")->fetch_assoc()['nombre']; ?>
                        <option value="<?php echo $id_sucursal_usuario; ?>" selected><?php echo htmlspecialchars($nombre_sucursal); ?></option>
                    <?php endif; ?>
                </select>
                <?php if (!$es_admin_general): ?>
                    <input type="hidden" name="id_sucursal" value="<?php echo $id_sucursal_usuario; ?>" />
                <?php endif; ?>
            </div>

            <div class="mb-3">
                <label for="selectEmpleado" class="form-label">Seleccionar Empleado *</label>
                <select class="form-select" id="selectEmpleado" name="id_empleado" required <?php echo $es_admin_general ? 'disabled' : ''; ?>>
                    <option value="">Cargando...</option>
                </select>
                <div class="form-text">Al elegir la sucursal se preselecciona al Jefe Médico de ANCASH (★), disponible en cualquier sucursal.</div>
            </div>

            <div class="mb-3">
                <label for="selectEquipo" class="form-label">Seleccionar Equipo *</label>
                <select class="form-select" id="selectEquipo" name="id_equipo" required <?php echo $es_admin_general ? 'disabled' : ''; ?>>
                    <option value="">Cargando...</option>
                </select>
            </div>

            <div class="mb-3">
                <label for="observaciones_entrega" class="form-label">Observaciones de la Entrega</label>
                <textarea class="form-control" id="observaciones_entrega" name="observaciones_entrega" rows="3" placeholder="Ej: Se entrega con cargador y maletín."></textarea>
            </div>

            <hr>
            <a href="asignaciones.php" class="btn btn-secondary">Cancelar</a>
            <button type="submit" class="btn btn-primary">Asignar Equipo</button>
        </form>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const selectSucursal = document.getElementById('selectSucursal');
    const selectEmpleado = document.getElementById('selectEmpleado');
    const selectEquipo = document.getElementById('selectEquipo');
    
    const esAdminGeneral = <?php echo $es_admin_general ? 'true' : 'false'; ?>;
    const CARGO_JEFE_MEDICO = 'Jefe Médico';

    function esJefeMedico(option) {
        return (option.dataset.cargo || '').trim().toLowerCase() === CARGO_JEFE_MEDICO.trim().toLowerCase();
    }

    function seleccionarJefeMedico() {
        const opciones = Array.from(selectEmpleado.options);
        // Prioridad: jefe médico de ANCASH; si no está, cualquier jefe médico de la sucursal
        const jefe = opciones.find(o => o.dataset.esJefeAncash === '1') || opciones.find(esJefeMedico);
        if (jefe) {
            selectEmpleado.value = jefe.value;
        }
    }

    function cargarDropdowns(sucursalId) {
        if (!sucursalId) {
            selectEmpleado.innerHTML = '<option value="">Seleccione una sucursal</option>';
            selectEquipo.innerHTML = '<option value="">Seleccione una sucursal</option>';
            selectEmpleado.disabled = true;
            selectEquipo.disabled = true;
            return;
        }

        selectEmpleado.innerHTML = '<option value="">Cargando...</option>';
        selectEquipo.innerHTML = '<option value="">Cargando...</option>';
        selectEmpleado.disabled = true;
        selectEquipo.disabled = true;

        // 1. Fetch Empleados
        const fetchEmpleados = fetch(`obtener_empleados_por_sucursal.php?id_sucursal=${sucursalId}`)
            .then(response => response.json())
            .then(empleados => {
                selectEmpleado.innerHTML = '<option value="">Seleccionar Empleado *</option>';
                if (empleados && !empleados.error && empleados.length > 0) {
                    let grupoActual = '';
                    empleados.forEach(emp => {
                        const cargo = emp.cargo_nombre || 'Sin cargo';
                        if (cargo !== grupoActual) {
                            grupoActual = cargo;
                            const grupo = document.createElement('optgroup');
                            grupo.label = cargo;
                            grupo.dataset.cargo = cargo;
                            selectEmpleado.appendChild(grupo);
                        }
                        const area = emp.area_nombre ? ` — ${emp.area_nombre}` : '';
                        const marca = emp.es_jefe_ancash ? ' ★ Jefe Médico ANCASH' : '';
                        const option = new Option(`${emp.apellidos}, ${emp.nombres}${area} (DNI: ${emp.dni})${marca}`, emp.id);
                        option.dataset.cargo = cargo;
                        option.dataset.esJefeAncash = emp.es_jefe_ancash ? '1' : '0';
                        selectEmpleado.lastElementChild.appendChild(option);
                    });
                    selectEmpleado.disabled = false;
                    seleccionarJefeMedico();
                } else {
                    selectEmpleado.innerHTML = '<option value="">No hay empleados activos en esta sucursal</option>';
                }
            });

        // 2. Fetch Equipos
        const fetchEquipos = fetch(`obtener_equipos_por_sucursal.php?id_sucursal=${sucursalId}`)
            .then(response => response.json())
            .then(equipos => {
                selectEquipo.innerHTML = '<option value="">Seleccionar Equipo *</option>';
                if (equipos && !equipos.error && equipos.length > 0) {
                    equipos.forEach(eq => {
                        const option = new Option(`${eq.codigo_inventario} (${eq.marca_nombre} ${eq.modelo_nombre})`, eq.id);
                        selectEquipo.add(option);
                    });
                    selectEquipo.disabled = false;
                } else {
                    selectEquipo.innerHTML = '<option value="">No hay equipos disponibles en esta sucursal</option>';
                }
            });

        // Esperar a que ambas promesas se resuelvan
        Promise.all([fetchEmpleados, fetchEquipos]).catch(error => {
            console.error('Error al cargar datos:', error);
            selectEmpleado.innerHTML = '<option value="">Error al cargar</option>';
            selectEquipo.innerHTML = '<option value="">Error al cargar</option>';
        });
    }

    // Si es admin general, esperar a que cambie la sucursal
    if (esAdminGeneral) {
        selectSucursal.addEventListener('change', function() {
            cargarDropdowns(this.value);
        });
    } else {
        // Si es admin de sucursal, cargar los datos inmediatamente
        cargarDropdowns(selectSucursal.value);
    }
});
</script>

<?php require_once '../templates/footer.php'; ?>