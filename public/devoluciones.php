<?php
require_once '../templates/header.php';

// ... (lógica PHP sin cambios) ...

?>

<h1 class="h2 mb-3">Devolución de Equipos</h1>

<div class="card">
    <div class="card-header">
        Equipos Actualmente Asignados
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover align-middle">
                <thead>
                    <tr>
                        <th>Empleado</th>
                        <th>Código Inventario</th>
                        <th>Equipo</th>
                        <th style="width: 30%;">Acción de Devolución</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($resultado && $resultado->num_rows > 0) : ?>
                        <?php while ($asignacion = $resultado->fetch_assoc()) : ?>
                            <tr>
                                <td><?php echo htmlspecialchars($asignacion['apellidos'] . ', ' . $asignacion['nombres']); ?></td>
                                <td><?php echo htmlspecialchars($asignacion['codigo_inventario']); ?></td>
                                <td><?php echo htmlspecialchars($asignacion['marca_nombre'] . ' ' . $asignacion['modelo_nombre']); ?></td>
                                <td>
                                    <form action="../includes/procesar_devolucion.php" method="POST" class="d-flex gap-2" onsubmit="return confirm('¿Confirmas la devolución de este equipo?');">
                                        <input type="hidden" name="id_asignacion" value="<?php echo $asignacion['id_asignacion']; ?>">
                                        <select class="form-select form-select-sm" name="estado_equipo_devolucion" required>
                                            <option value="Disponible">Devolver (Disponible)</option>
                                            <option value="En Reparacion">Devolver (a Reparación)</option>
                                        </select>
                                        <button type="submit" class="btn btn-danger btn-sm text-nowrap"><i class="bi bi-arrow-return-left"></i> Registrar</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else : ?>
                        <tr><td colspan="4" class="text-center p-4">No hay equipos asignados en esta sucursal.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require_once '../templates/footer.php'; ?>