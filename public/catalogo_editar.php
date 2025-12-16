<?php
require_once '../templates/header.php';

// CORRECCIÓN EN LA LÍNEA 5:
// Se reemplazó la función obsoleta 'FILTER_SANITIZE_STRING'
// con 'htmlspecialchars()' para limpiar la entrada de la URL.
$tipo_catalogo = isset($_GET['type']) ? htmlspecialchars($_GET['type'], ENT_QUOTES, 'UTF-8') : null;
$id_elemento = isset($_GET['id']) && is_numeric($_GET['id']) ? (int)$_GET['id'] : null;

// Mapeo de tipos a nombres de tabla (basado en tu archivo gestion_catalogos.php)
$tablas_catalogo = [
    'sucursal' => 'sucursales',
    'area' => 'areas',
    'cargo' => 'cargos',
    'tipo_equipo' => 'tipos_equipo',
    'marca' => 'marcas',
    'modelo' => 'modelos',
];

// Validar que el tipo sea válido y exista en nuestro mapeo
if (!$tipo_catalogo || !$id_elemento || !array_key_exists($tipo_catalogo, $tablas_catalogo)) {
    echo '<div class="alert alert-danger">Error: Tipo de catálogo o ID no válido.</div>';
    require_once '../templates/footer.php';
    exit();
}

$nombre_tabla = $tablas_catalogo[$tipo_catalogo];
$nombre_campo = 'nombre'; // Asumimos que la columna principal se llama 'nombre'

// --- Procesar el formulario POST ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nuevo_nombre = $_POST['nombre'] ?? '';
    $nuevo_estado = $_POST['estado'] ?? 'Activo';
    
    // Variables adicionales para 'modelo' o 'cargo'
    $id_marca_modelo = isset($_POST['id_marca']) ? (int)$_POST['id_marca'] : null; 
    $id_area_cargo = isset($_POST['id_area']) ? (int)$_POST['id_area'] : null; 

    if (!empty($nuevo_nombre)) {
        
        // Construir la consulta de actualización
        $sql_update = "";
        $types = "";
        $params = [];

        if ($tipo_catalogo === 'modelo' && $id_marca_modelo) {
            $sql_update = "UPDATE `$nombre_tabla` SET `nombre` = ?, `estado` = ?, `id_marca` = ? WHERE `id` = ?";
            $types = "ssii";
            $params = [$nuevo_nombre, $nuevo_estado, $id_marca_modelo, $id_elemento];
        } elseif ($tipo_catalogo === 'cargo' && $id_area_cargo) {
            $sql_update = "UPDATE `$nombre_tabla` SET `nombre` = ?, `estado` = ?, `id_area` = ? WHERE `id` = ?";
            $types = "ssii";
            $params = [$nuevo_nombre, $nuevo_estado, $id_area_cargo, $id_elemento];
        } elseif ($tipo_catalogo !== 'modelo' && $tipo_catalogo !== 'cargo') {
             $sql_update = "UPDATE `$nombre_tabla` SET `nombre` = ?, `estado` = ? WHERE `id` = ?";
             $types = "ssi";
             $params = [$nuevo_nombre, $nuevo_estado, $id_elemento];
        } else {
            $error_msg = "Error: Faltan datos para actualizar (marca o área).";
        }

        if (empty($error_msg) && !empty($sql_update)) {
            $stmt_update = $conexion->prepare($sql_update);
            if ($stmt_update) {
                $stmt_update->bind_param($types, ...$params);
                if ($stmt_update->execute()) {
                    header("Location: gestion_catalogos.php?status=editado&tipo=" . urlencode($tipo_catalogo));
                    exit();
                } else {
                    $error_msg = "Error al guardar los cambios: " . $stmt_update->error;
                }
                $stmt_update->close();
            } else {
                 $error_msg = "Error al preparar la consulta: " . $conexion->error;
            }
        }
    } else {
        $error_msg = "El nombre no puede estar vacío.";
    }
}

// --- Obtener los datos actuales del elemento (GET) ---
$sql_select = "SELECT * FROM `$nombre_tabla` WHERE `id` = ?";
$stmt_select = $conexion->prepare($sql_select);
$stmt_select->bind_param("i", $id_elemento);
$stmt_select->execute();
$elemento = $stmt_select->get_result()->fetch_assoc();
$stmt_select->close();

if (!$elemento) {
    echo '<div class="alert alert-warning">Elemento no encontrado.</div>';
    require_once '../templates/footer.php';
    exit();
}

// Convertir el tipo a un título más legible
$titulo_elemento = ucfirst(str_replace('_', ' ', $tipo_catalogo));

// Cargar catálogos necesarios para los <select> (si aplica)
$marcas = null;
$areas = null;
if ($tipo_catalogo === 'modelo') {
    $marcas = $conexion->query("SELECT id, nombre FROM marcas WHERE estado = 'Activo' ORDER BY nombre");
}
if ($tipo_catalogo === 'cargo') {
    $areas = $conexion->query("SELECT id, nombre FROM areas WHERE estado = 'Activo' ORDER BY nombre");
}

?>

<h1 class="h2 mb-4">Editar Elemento de Catálogo</h1>

<?php if (isset($error_msg)): ?>
    <div class="alert alert-danger"><?php echo htmlspecialchars($error_msg); ?></div>
<?php endif; ?>

<div class="card">
    <div class="card-header">
        Editando: <?php echo htmlspecialchars($elemento[$nombre_campo]); ?> (<?php echo $titulo_elemento; ?>)
    </div>
    <div class="card-body">
        <form action="catalogo_editar.php?id=<?php echo $id_elemento; ?>&type=<?php echo urlencode($tipo_catalogo); ?>" method="POST">
            
            <div class="mb-3">
                <label for="nombre" class="form-label">Nombre *</label>
                <input type="text" class="form-control" id="nombre" name="nombre" value="<?php echo htmlspecialchars($elemento[$nombre_campo]); ?>" required>
            </div>

            <?php if ($tipo_catalogo === 'cargo' && $areas): ?>
                <div class="mb-3">
                    <label for="id_area" class="form-label">Área *</label>
                    <select class="form-select" id="id_area" name="id_area" required>
                        <option value="">Seleccione un área...</option>
                        <?php while ($a = $areas->fetch_assoc()): ?>
                            <option value="<?php echo $a['id']; ?>" <?php if ($elemento['id_area'] == $a['id']) echo 'selected'; ?>>
                                <?php echo htmlspecialchars($a['nombre']); ?>
                            </option>
                        <?php endwhile; ?>
                    </select>
                </div>
            <?php endif; ?>

            <?php if ($tipo_catalogo === 'modelo' && $marcas): ?>
                <div class="mb-3">
                    <label for="id_marca" class="form-label">Marca *</label>
                    <select class="form-select" id="id_marca" name="id_marca" required>
                        <option value="">Seleccione una marca...</option>
                        <?php while ($m = $marcas->fetch_assoc()): ?>
                            <option value="<?php echo $m['id']; ?>" <?php if ($elemento['id_marca'] == $m['id']) echo 'selected'; ?>>
                                <?php echo htmlspecialchars($m['nombre']); ?>
                            </option>
                        <?php endwhile; ?>
                    </select>
                </div>
            <?php endif; ?>

            <?php if (isset($elemento['estado'])): ?>
            <div class="mb-3">
                <label for="estado" class="form-label">Estado *</label>
                <select class="form-select" id="estado" name="estado" required>
                    <option value="Activo" <?php echo ($elemento['estado'] === 'Activo') ? 'selected' : ''; ?>>Activo</option>
                    <option value="Inactivo" <?php echo ($elemento['estado'] === 'Inactivo') ? 'selected' : ''; ?>>Inactivo</con>
                </select>
            </div>
            <?php endif; ?>

            <hr>

            <div class="d-flex justify-content-end gap-2">
                <a href="gestion_catalogos.php" class="btn btn-secondary">Cancelar</a>
                <button type="submit" class="btn btn-primary">Guardar Cambios</button>
            </div>
        </form>
    </div>
</div>

<?php require_once '../templates/footer.php'; ?>