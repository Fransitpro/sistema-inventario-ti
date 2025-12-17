<?php
require_once '../config/database.php';

// Validar si se recibió el ID del área
if (isset($_POST['id_area'])) {
    $id_area = (int)$_POST['id_area'];

    // Consultar cargos activos de esa área
    $stmt = $conexion->prepare("SELECT id, nombre FROM cargos WHERE id_area = ? AND estado = 'Activo' ORDER BY nombre");
    $stmt->bind_param("i", $id_area);
    $stmt->execute();
    $resultado = $stmt->get_result();

    if ($resultado->num_rows > 0) {
        echo '<option value="">Seleccione un cargo...</option>';
        while ($row = $resultado->fetch_assoc()) {
            echo '<option value="' . $row['id'] . '">' . htmlspecialchars($row['nombre']) . '</option>';
        }
    } else {
        echo '<option value="">No hay cargos registrados para esta área</option>';
    }
    $stmt->close();
} else {
    echo '<option value="">Seleccione un área primero</option>';
}
?>