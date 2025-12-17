<?php
require_once '../config/database.php';

if (isset($_POST['id_marca'])) {
    $id_marca = (int)$_POST['id_marca'];

    $stmt = $conexion->prepare("SELECT id, nombre FROM modelos WHERE id_marca = ? AND estado = 'Activo' ORDER BY nombre");
    $stmt->bind_param("i", $id_marca);
    $stmt->execute();
    $resultado = $stmt->get_result();

    if ($resultado->num_rows > 0) {
        echo '<option value="">Seleccione un modelo...</option>';
        while ($row = $resultado->fetch_assoc()) {
            echo '<option value="' . $row['id'] . '">' . htmlspecialchars($row['nombre']) . '</option>';
        }
    } else {
        echo '<option value="">No hay modelos registrados para esta marca</option>';
    }
    $stmt->close();
} else {
    echo '<option value="">Seleccione una marca primero</option>';
}
?>