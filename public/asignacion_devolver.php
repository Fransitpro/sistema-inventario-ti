<?php
require_once '../templates/header.php';

// 1. Validar el ID de la asignación
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    echo '<div class="alert alert-danger">Error: ID de asignación no válido.</div>';
    require_once '../templates/footer.php';
    exit();
}
$id_asignacion = (int)$_GET['id'];

// 2. Consultar los datos de la asignación para mostrarlos
$sql_data = "SELECT
                a.id, a.id_equipo,
                emp.nombres, emp.apellidos,
                eq.codigo_inventario, ma.nombre as marca_nombre, mo.nombre as modelo_nombre
             FROM asignaciones a
             JOIN empleados emp ON a.id_empleado = emp.id
             JOIN equipos eq ON a.id_equipo = eq.id
             LEFT JOIN marcas ma ON eq.id_marca = ma.id
             LEFT JOIN modelos mo ON eq.id_modelo = mo.id
             WHERE a.id = ? AND a.estado_asignacion = 'Activa'";
$stmt = $conexion->prepare($sql_data);
$stmt->bind_param("i", $id_asignacion);
$stmt->execute();
$asignacion = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$asignacion) {
    echo '<div class="alert alert-warning">Asignación no encontrada o ya finalizada.</div>';
    require_once '../templates/footer.php';
    exit();
}
?>

<h1 class="h2 mb-4">Registrar Devolución de Equipo</h1>

<div class="card">
    <div class="card-header">
        Confirmar Devolución
    </div>
    <div class="card-body">
        <dl class="row">
            <dt class="col-sm-3">Empleado:</dt>
            <dd class="col-sm-9"><?php echo htmlspecialchars($asignacion['apellidos'] . ', ' . $asignacion['nombres']); ?></dd>
            <dt class="col-sm-3">Equipo:</dt>
            <dd class="col-sm-9"><?php echo htmlspecialchars($asignacion['codigo_inventario'] . ' (' . $asignacion['marca_nombre'] . ' ' . $asignacion['modelo_nombre'] . ')'); ?></dd>
        </dl>
        <hr>

        <form action="procesar_devolucion.php" method="POST" enctype="multipart/form-data">
            <input type="hidden" name="id_asignacion" value="<?php echo $id_asignacion; ?>">
            <input type="hidden" name="id_equipo" value="<?php echo $asignacion['id_equipo']; ?>">

            <div class="mb-3">
                <label for="fecha_devolucion" class="form-label">Fecha de Devolución *</label>
                <input type="datetime-local" class="form-control" id="fecha_devolucion" name="fecha_devolucion" value="<?php echo date('Y-m-d\TH:i'); ?>" required>
            </div>
            
            <div class="mb-3">
                <label for="estado_recibido" class="form-label">Estado en que se recibe el equipo *</label>
                <select class="form-select" id="estado_recibido" name="estado_recibido" required>
                    <option value="Bueno" selected>Bueno</option>
                    <option value="Regular (con detalles)">Regular (con detalles)</option>
                    <option value="Dañado">Dañado</option>
                </select>
            </div>

            <div class="mb-3">
                <label for="observaciones_devolucion" class="form-label">Observaciones Adicionales *</label>
                <textarea class="form-control" id="observaciones_devolucion" name="observaciones_devolucion" rows="3" placeholder="Ej: el equipo no enciende, presenta rayones en la tapa, etc." required></textarea>
            </div>

            <div class="mb-3">
                <label for="estado_final_equipo" class="form-label">Estado final del equipo en inventario *</label>
                <select class="form-select" id="estado_final_equipo" name="estado_final_equipo" required>
                    <option value="Disponible" selected>Disponible (para reasignar)</option>
                    <option value="En Reparación">En Reparación (Enviar a módulo de reparaciones)</option>
                </select>
            </div>

            <div class="row">
                <div class="col-md-4 mb-3">
                    <label for="imagen_devolucion_1" class="form-label">Evidencia Fotográfica 1 (Opcional)</label>
                    <input class="form-control" type="file" id="imagen_devolucion_1" name="imagen_devolucion_1" accept="image/*">
                </div>
                <div class="col-md-4 mb-3">
                    <label for="imagen_devolucion_2" class="form-label">Evidencia Fotográfica 2 (Opcional)</label>
                    <input class="form-control" type="file" id="imagen_devolucion_2" name="imagen_devolucion_2" accept="image/*">
                </div>
                <div class="col-md-4 mb-3">
                    <label for="imagen_devolucion_3" class="form-label">Evidencia Fotográfica 3 (Opcional)</label>
                    <input class="form-control" type="file" id="imagen_devolucion_3" name="imagen_devolucion_3" accept="image/*">
                </div>
            </div>

            <hr>
            <div class="d-flex justify-content-end gap-2">
                <a href="asignaciones.php" class="btn btn-secondary">Cancelar</a>
                <button type="submit" class="btn btn-success">Confirmar Devolución</button>
            </div>
        </form>
    </div>
</div>

<?php require_once '../templates/footer.php'; ?>