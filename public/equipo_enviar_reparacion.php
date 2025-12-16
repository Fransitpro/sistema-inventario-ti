<?php
require_once '../templates/header.php';

// ----- Lógica para procesar el formulario cuando se envía -----
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id_equipo = $_POST['id_equipo'];
    $fecha_ingreso = $_POST['fecha_ingreso'];
    $motivo = $_POST['motivo'];
    $proveedor = $_POST['proveedor'];

    // Iniciar transacción para asegurar que ambas consultas se ejecuten correctamente
    $conexion->begin_transaction();
    try {
        // 1. Actualizar el estado del equipo
        $stmt_equipo = $conexion->prepare("UPDATE equipos SET estado = 'En Reparación' WHERE id = ?");
        $stmt_equipo->bind_param("i", $id_equipo);
        $stmt_equipo->execute();

        // 2. Insertar el registro en la nueva tabla 'reparaciones'
        $stmt_reparacion = $conexion->prepare("INSERT INTO reparaciones (id_equipo, fecha_ingreso, motivo, proveedor_servicio, estado_reparacion) VALUES (?, ?, ?, ?, 'En Proceso')");
        $stmt_reparacion->bind_param("isss", $id_equipo, $fecha_ingreso, $motivo, $proveedor);
        $stmt_reparacion->execute();

        // Si todo fue bien, confirmar los cambios
        $conexion->commit();
        
        // Redirigir al listado de equipos con un mensaje de éxito
        header("Location: equipos.php?status=reparacion_iniciada");
        exit();

    } catch (mysqli_sql_exception $exception) {
        // Si algo falló, deshacer los cambios
        $conexion->rollback();
        die("Error al registrar la reparación: " . $exception->getMessage());
    }
}

// ----- Lógica para mostrar el formulario con los datos del equipo -----
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    die("Error: ID de equipo no válido.");
}
$id_equipo = (int)$_GET['id'];

$sql_equipo = "SELECT e.codigo_inventario, ma.nombre as marca_nombre, mo.nombre as modelo_nombre 
               FROM equipos e
               LEFT JOIN marcas ma ON e.id_marca = ma.id
               LEFT JOIN modelos mo ON e.id_modelo = mo.id
               WHERE e.id = ?";
$stmt = $conexion->prepare($sql_equipo);
$stmt->bind_param("i", $id_equipo);
$stmt->execute();
$equipo = $stmt->get_result()->fetch_assoc();

if (!$equipo) {
    die("Equipo no encontrado.");
}
?>

<h1 class="h2 mb-4">Enviar Equipo a Reparación</h1>

<div class="card">
    <div class="card-header">
        Registrar Inicio de Proceso de Reparación
    </div>
    <div class="card-body">
        <div class="mb-4">
            <h5>Equipo a reparar:</h5>
            <ul class="list-group">
                <li class="list-group-item"><strong>Código:</strong> <?php echo htmlspecialchars($equipo['codigo_inventario']); ?></li>
                <li class="list-group-item"><strong>Marca/Modelo:</strong> <?php echo htmlspecialchars($equipo['marca_nombre'] . ' / ' . $equipo['modelo_nombre']); ?></li>
            </ul>
        </div>
        
        <form action="equipo_enviar_reparacion.php" method="POST">
            <input type="hidden" name="id_equipo" value="<?php echo $id_equipo; ?>">
            
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label for="fecha_ingreso" class="form-label">Fecha de Envío *</label>
                    <input type="date" class="form-control" id="fecha_ingreso" name="fecha_ingreso" value="<?php echo date('Y-m-d'); ?>" required>
                </div>
                <div class="col-md-6 mb-3">
                    <label for="proveedor" class="form-label">Proveedor de Servicio / Técnico</label>
                    <input type="text" class="form-control" id="proveedor" name="proveedor">
                </div>
            </div>
            
            <div class="mb-3">
                <label for="motivo" class="form-label">Motivo de la Falla / Reparación *</label>
                <textarea class="form-control" id="motivo" name="motivo" rows="4" required></textarea>
            </div>
            
            <hr>
            
            <div class="d-flex justify-content-end gap-2">
                <a href="equipos.php" class="btn btn-secondary">Cancelar</a>
                <button type="submit" class="btn btn-primary">Guardar y Enviar a Reparación</button>
            </div>
        </form>
    </div>
</div>

<?php require_once '../templates/footer.php'; ?>