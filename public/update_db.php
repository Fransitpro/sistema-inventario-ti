<?php require_once '../config/database.php'; $conexion->query('ALTER TABLE equipos ADD anio_inventario VARCHAR(4) NULL AFTER color'); echo 'Done equipos; '; ?>
