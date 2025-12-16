<?php
require_once '../templates/header.php';

$sql = "SELECT b.*, e.codigo_inventario, ma.nombre as marca, mo.nombre as modelo
        FROM bajas b
        JOIN equipos e ON b.id_equipo = e.id
        LEFT JOIN marcas ma ON e.id_marca = ma.id
        LEFT JOIN modelos mo ON e.id_modelo = mo.id
        ORDER BY b.fecha_baja DESC";
$resultado = $conexion->query($sql);
?>

<h1 class="h2 mb-4">Historial de Equipos Dados de Baja</h1>

<div class="card">
    <div class="card-header">
        Listado de Bajas
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-striped table-hover">
                <thead class="table-dark">
                    <tr>
                        <th>Código Equipo</th>
                        <th>Marca / Modelo</th>
                        <th>Fecha de Baja</th>
                        <th>Motivo</th>
                        <th>Acta</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($resultado && $resultado->num_rows > 0): ?>
                        <?php while ($baja = $resultado->fetch_assoc()): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($baja['codigo_inventario']); ?></td>
                                <td><?php echo htmlspecialchars($baja['marca'] . ' / ' . $baja['modelo']); ?></td>
                                <td><?php echo date('d/m/Y', strtotime($baja['fecha_baja'])); ?></td>
                                <td><?php echo htmlspecialchars($baja['motivo']); ?></td>
                                <td>
                                    <?php if ($baja['acta_baja_path']): ?>
                                        <a href="ver_acta_baja.php?id=<?php echo $baja['id']; ?>" target="_blank" class="btn btn-info btn-sm" title="Ver Acta">
                                        <i class="bi bi-file-earmark-pdf-fill"></i>
                                        </a>
                                    <?php else: ?>
                                        <span class="text-muted">N/A</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr><td colspan="5" class="text-center">No hay equipos dados de baja.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require_once '../templates/footer.php'; ?>