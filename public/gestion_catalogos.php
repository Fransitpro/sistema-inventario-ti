<?php
require_once '../templates/header.php';

// --- Lógica para CAMBIAR ESTADO ---
if (isset($_GET['action']) && isset($_GET['id']) && isset($_GET['type'])) {
    $id = (int)$_GET['id'];
    $type = $_GET['type'];
    $action = $_GET['action'];
    $estado = ($action == 'deactivate') ? 'Inactivo' : 'Activo';
    
    // Mapa de tipos a tablas (asegúrate que 'tipo_equipo' esté correcto)
    $table_map = [
        'sucursal' => 'sucursales', 
        'tipo_equipo' => 'tipos_equipo', // Nombre de tipo esperado por catalogo_editar.php
        'marca' => 'marcas', 
        'modelo' => 'modelos', 
        'area' => 'areas', 
        'cargo' => 'cargos',
        'tipo' => 'tipos_equipo' // Dejamos esto por si la acción de activar/desactivar usa 'tipo'
    ];

    if (array_key_exists($type, $table_map)) {
        $table_name = $table_map[$type];
        $stmt = $conexion->prepare("UPDATE {$table_name} SET estado = ? WHERE id = ?");
        $stmt->bind_param("si", $estado, $id);
        $stmt->execute();
        header("Location: gestion_catalogos.php");
        exit();
    }
}

// --- Lógica para AÑADIR NUEVOS ELEMENTOS ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $stmt = null;
    if (isset($_POST['catalogo'])) {
        $catalogo = $_POST['catalogo'];
        $nombre = $_POST['nombre'];
        switch ($catalogo) {
            case 'tipo': $stmt = $conexion->prepare("INSERT INTO tipos_equipo (nombre) VALUES (?)"); $stmt->bind_param("s", $nombre); break;
            case 'marca': $stmt = $conexion->prepare("INSERT INTO marcas (nombre) VALUES (?)"); $stmt->bind_param("s", $nombre); break;
            case 'area': $stmt = $conexion->prepare("INSERT INTO areas (nombre) VALUES (?)"); $stmt->bind_param("s", $nombre); break;
            case 'modelo': $id_marca = $_POST['id_marca']; $stmt = $conexion->prepare("INSERT INTO modelos (id_marca, nombre) VALUES (?, ?)"); $stmt->bind_param("is", $id_marca, $nombre); break;
            case 'cargo': $id_area = $_POST['id_area']; $stmt = $conexion->prepare("INSERT INTO cargos (id_area, nombre) VALUES (?, ?)"); $stmt->bind_param("is", $id_area, $nombre); break;
        }
    } elseif (isset($_POST['catalogo_sucursal'])) {
        $nombre = $_POST['nombre_sucursal'];
        $direccion = $_POST['direccion_sucursal'];
        $stmt = $conexion->prepare("INSERT INTO sucursales (nombre, direccion) VALUES (?, ?)");
        $stmt->bind_param("ss", $nombre, $direccion);
    }
    if ($stmt && $stmt->execute()) { echo "<div class='alert alert-success mt-3'>Elemento agregado correctamente.</div>"; } 
    elseif($stmt) { echo "<div class='alert alert-danger mt-3'>Error al agregar: " . $stmt->error . "</div>"; }
    if ($stmt) $stmt->close();
}

// --- Cargar datos existentes para las tablas ---
$sucursales = $conexion->query("SELECT * FROM sucursales ORDER BY nombre");
$tipos = $conexion->query("SELECT * FROM tipos_equipo ORDER BY nombre");
$marcas = $conexion->query("SELECT * FROM marcas ORDER BY nombre");
$modelos = $conexion->query("SELECT m.id, m.nombre, m.estado, ma.nombre as marca_nombre FROM modelos m JOIN marcas ma ON m.id_marca = ma.id ORDER BY ma.nombre, m.nombre");
$areas = $conexion->query("SELECT * FROM areas ORDER BY nombre");
$cargos = $conexion->query("SELECT c.id, c.nombre, c.estado, a.nombre AS area_nombre FROM cargos c JOIN areas a ON c.id_area = a.id ORDER BY a.nombre, c.nombre");
?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <h1 class="h2">Gestión de Catálogos</h1>
</div>

<div class="row">
    <div class="col-lg-4 col-md-6 mb-4">
        <div class="card h-100"><div class="card-header">Sucursales</div><div class="card-body d-flex flex-column"><form method="POST" class="mb-3"><input type="hidden" name="catalogo_sucursal" value="1"><div class="mb-2"><label class="form-label">Nombre <span class="text-danger">*</span></label><input type="text" name="nombre_sucursal" class="form-control" required></div><div class="mb-2"><label class="form-label">Dirección</label><textarea name="direccion_sucursal" class="form-control" rows="1"></textarea></div><button class="btn btn-primary btn-sm" type="submit"><i class="bi bi-plus"></i> Agregar</button></form><hr><div class="table-responsive flex-grow-1"><table class="table table-sm table-hover"><tbody><?php while ($item = $sucursales->fetch_assoc()): ?><tr><td><strong><?php echo htmlspecialchars($item['nombre']); ?></strong><br><small class="text-muted"><?php echo htmlspecialchars($item['direccion']); ?></small><span class="badge float-end <?php echo $item['estado'] == 'Activo' ? 'bg-success' : 'bg-danger'; ?>"><?php echo $item['estado']; ?></span></td><td class="text-end align-middle"><div class="btn-group"><a href="catalogo_editar.php?id=<?php echo $item['id']; ?>&type=sucursal" class="btn btn-warning btn-sm" title="Editar"><i class="bi bi-pencil"></i></a><?php if ($item['estado'] == 'Activo'): ?><a href="?action=deactivate&id=<?php echo $item['id']; ?>&type=sucursal" class="btn btn-danger btn-sm" title="Desactivar"><i class="bi bi-trash"></i></a><?php else: ?><a href="?action=activate&id=<?php echo $item['id']; ?>&type=sucursal" class="btn btn-success btn-sm" title="Activar"><i class="bi bi-check-circle"></i></a><?php endif; ?></div></td></tr><?php endwhile; ?></tbody></table></div></div></div>
    </div>
    
    <div class="col-lg-4 col-md-6 mb-4">
        <div class="card h-100"><div class="card-header">Áreas</div><div class="card-body d-flex flex-column"><form method="POST" class="mb-3"><input type="hidden" name="catalogo" value="area"><div class="input-group"><input type="text" name="nombre" class="form-control" placeholder="Nueva área... *" required><button class="btn btn-primary" type="submit"><i class="bi bi-plus"></i></button></div></form><hr><div class="table-responsive flex-grow-1"><table class="table table-sm table-hover"><tbody><?php $areas->data_seek(0); while ($item = $areas->fetch_assoc()): ?><tr><td><?php echo htmlspecialchars($item['nombre']); ?><span class="badge float-end <?php echo $item['estado'] == 'Activo' ? 'bg-success' : 'bg-danger'; ?>"><?php echo $item['estado']; ?></span></td><td class="text-end align-middle"><div class="btn-group"><a href="catalogo_editar.php?id=<?php echo $item['id']; ?>&type=area" class="btn btn-warning btn-sm"><i class="bi bi-pencil"></i></a><?php if ($item['estado'] == 'Activo'): ?><a href="?action=deactivate&id=<?php echo $item['id']; ?>&type=area" class="btn btn-danger btn-sm"><i class="bi bi-trash"></i></a><?php else: ?><a href="?action=activate&id=<?php echo $item['id']; ?>&type=area" class="btn btn-success btn-sm"><i class="bi bi-check-circle"></i></a><?php endif; ?></div></td></tr><?php endwhile; ?></tbody></table></div></div></div>
    </div>

    <div class="col-lg-4 col-md-6 mb-4">
        <div class="card h-100"><div class="card-header">Cargos (por Área)</div><div class="card-body d-flex flex-column"><form method="POST" class="mb-3"><input type="hidden" name="catalogo" value="cargo"><div class="mb-2"><select name="id_area" class="form-select" required><option value="">Selecciona un área *</option><?php $areas->data_seek(0); while($area = $areas->fetch_assoc()): ?><option value="<?php echo $area['id']; ?>"><?php echo htmlspecialchars($area['nombre']); ?></option><?php endwhile; ?></select></div><div class="input-group"><input type="text" name="nombre" class="form-control" placeholder="Nuevo cargo... *" required><button class="btn btn-primary" type="submit"><i class="bi bi-plus"></i></button></div></form><hr><div class="table-responsive flex-grow-1"><table class="table table-sm table-hover"><tbody><?php while ($item = $cargos->fetch_assoc()): ?><tr><td><strong><?php echo htmlspecialchars($item['area_nombre']); ?></strong> - <?php echo htmlspecialchars($item['nombre']); ?><span class="badge float-end <?php echo $item['estado'] == 'Activo' ? 'bg-success' : 'bg-danger'; ?>"><?php echo $item['estado']; ?></span></td><td class="text-end align-middle"><div class="btn-group"><a href="catalogo_editar.php?id=<?php echo $item['id']; ?>&type=cargo" class="btn btn-warning btn-sm"><i class="bi bi-pencil"></i></a><?php if ($item['estado'] == 'Activo'): ?><a href="?action=deactivate&id=<?php echo $item['id']; ?>&type=cargo" class="btn btn-danger btn-sm"><i class="bi bi-trash"></i></a><?php else: ?><a href="?action=activate&id=<?php echo $item['id']; ?>&type=cargo" class="btn btn-success btn-sm"><i class="bi bi-check-circle"></i></a><?php endif; ?></div></td></tr><?php endwhile; ?></tbody></table></div></div></div>
    </div>

    <div class="col-lg-4 col-md-6 mb-4">
        <div class="card h-100"><div class="card-header">Tipos de Equipo</div><div class="card-body d-flex flex-column"><form method="POST" class="mb-3"><input type="hidden" name="catalogo" value="tipo"><div class="input-group"><input type="text" name="nombre" class="form-control" placeholder="Nuevo tipo... *" required><button class="btn btn-primary" type="submit"><i class="bi bi-plus"></i></button></div></form><hr><div class="table-responsive flex-grow-1"><table class="table table-sm table-hover"><tbody><?php while($item = $tipos->fetch_assoc()): ?><tr><td><?php echo htmlspecialchars($item['nombre']); ?><span class="badge float-end <?php echo $item['estado'] == 'Activo' ? 'bg-success' : 'bg-danger'; ?>"><?php echo $item['estado']; ?></span></td><td class="text-end align-middle"><div class="btn-group">
            
            <a href="catalogo_editar.php?id=<?php echo $item['id']; ?>&type=tipo_equipo" class="btn btn-warning btn-sm" title="Editar"><i class="bi bi-pencil"></i></a>
            
            <?php if ($item['estado'] == 'Activo'): ?><a href="?action=deactivate&id=<?php echo $item['id']; ?>&type=tipo" class="btn btn-danger btn-sm" title="Desactivar"><i class="bi bi-trash"></i></a><?php else: ?><a href="?action=activate&id=<?php echo $item['id']; ?>&type=tipo" class="btn btn-success btn-sm" title="Activar"><i class="bi bi-check-circle"></i></a><?php endif; ?></div></td></tr><?php endwhile; ?></tbody></table></div></div></div>
    </div>

    <div class="col-lg-4 col-md-6 mb-4">
        <div class="card h-100"><div class="card-header">Marcas</div><div class="card-body d-flex flex-column"><form method="POST" class="mb-3"><input type="hidden" name="catalogo" value="marca"><div class="input-group"><input type="text" name="nombre" class="form-control" placeholder="Nueva marca... *" required><button class="btn btn-primary" type="submit"><i class="bi bi-plus"></i></button></div></form><hr><div class="table-responsive flex-grow-1"><table class="table table-sm table-hover"><tbody><?php $marcas->data_seek(0); while($item = $marcas->fetch_assoc()): ?><tr><td><?php echo htmlspecialchars($item['nombre']); ?><span class="badge float-end <?php echo $item['estado'] == 'Activo' ? 'bg-success' : 'bg-danger'; ?>"><?php echo $item['estado']; ?></span></td><td class="text-end align-middle"><div class="btn-group"><a href="catalogo_editar.php?id=<?php echo $item['id']; ?>&type=marca" class="btn btn-warning btn-sm"><i class="bi bi-pencil"></i></a><?php if ($item['estado'] == 'Activo'): ?><a href="?action=deactivate&id=<?php echo $item['id']; ?>&type=marca" class="btn btn-danger btn-sm"><i class="bi bi-trash"></i></a><?php else: ?><a href="?action=activate&id=<?php echo $item['id']; ?>&type=marca" class="btn btn-success btn-sm"><i class="bi bi-check-circle"></i></a><?php endif; ?></div></td></tr><?php endwhile; ?></tbody></table></div></div></div>
    </div>

    <div class="col-lg-4 col-md-6 mb-4">
        <div class="card h-100"><div class="card-header">Modelos</div><div class="card-body d-flex flex-column"><form method="POST" class="mb-3"><input type="hidden" name="catalogo" value="modelo"><div class="mb-2"><select name="id_marca" class="form-select" required><option value="">Selecciona una marca *</option><?php $marcas->data_seek(0); while($marca = $marcas->fetch_assoc()): ?><option value="<?php echo $marca['id']; ?>"><?php echo htmlspecialchars($marca['nombre']); ?></option><?php endwhile; ?></select></div><div class="input-group"><input type="text" name="nombre" class="form-control" placeholder="Nuevo modelo... *" required><button class="btn btn-primary" type="submit"><i class="bi bi-plus"></i></button></div></form><hr><div class="table-responsive flex-grow-1"><table class="table table-sm table-hover"><tbody><?php while($item = $modelos->fetch_assoc()): ?><tr><td><strong><?php echo htmlspecialchars($item['marca_nombre']); ?></strong> - <?php echo htmlspecialchars($item['nombre']); ?><span class="badge float-end <?php echo $item['estado'] == 'Activo' ? 'bg-success' : 'bg-danger'; ?>"><?php echo $item['estado']; ?></span></td><td class="text-end align-middle"><div class="btn-group"><a href="catalogo_editar.php?id=<?php echo $item['id']; ?>&type=modelo" class="btn btn-warning btn-sm"><i class="bi bi-pencil"></i></a><?php if ($item['estado'] == 'Activo'): ?><a href="?action=deactivate&id=<?php echo $item['id']; ?>&type=modelo" class="btn btn-danger btn-sm"><i class="bi bi-trash"></i></a><?php else: ?><a href="?action=activate&id=<?php echo $item['id']; ?>&type=modelo" class="btn btn-success btn-sm"><i class="bi bi-check-circle"></i></a><?php endif; ?></div></td></tr><?php endwhile; ?></tbody></table></div></div></div>
    </div>
</div>

<?php require_once '../templates/footer.php'; ?>