<?php
require_once '../templates/header.php';

// Seguridad: Solo administradores pueden acceder
if (!isset($_SESSION['user_rol']) || $_SESSION['user_rol'] !== 'Administrador') {
    echo '<div class="alert alert-danger">Acceso denegado.</div>';
    require_once '../templates/footer.php';
    exit();
}

// Procesar el formulario al guardar
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nuevo_simbolo = trim($_POST['moneda_simbolo'] ?? 'S/');
    
    if (!empty($nuevo_simbolo)) {
        // 1. Actualizar en la Base de Datos
        $stmt = $conexion->prepare("UPDATE configuracion SET valor = ? WHERE clave = 'moneda_simbolo'");
        $stmt->bind_param("s", $nuevo_simbolo);
        
        if ($stmt->execute()) {
            // 2. Actualizar la sesión inmediatamente para ver el cambio sin reloguearse
            $_SESSION['configuracion']['moneda_simbolo'] = $nuevo_simbolo;
            
            $mensaje = '<div class="alert alert-success">Configuración guardada correctamente.</div>';
        } else {
            $mensaje = '<div class="alert alert-danger">Error al guardar: ' . $stmt->error . '</div>';
        }
        $stmt->close();
    } else {
        $mensaje = '<div class="alert alert-warning">El símbolo de moneda no puede estar vacío.</div>';
    }
}

// Obtener el valor actual (desde la sesión que ya carga el header, o BD por seguridad)
$moneda_actual = $_SESSION['configuracion']['moneda_simbolo'] ?? 'S/';
?>

<h1 class="h2 mb-4">Configuración del Sistema</h1>

<?php if (isset($mensaje)) echo $mensaje; ?>

<div class="card" style="max-width: 600px;">
    <div class="card-header">
        <i class="bi bi-currency-exchange me-2"></i> Ajustes Generales
    </div>
    <div class="card-body">
        <form action="configuracion.php" method="POST">
            <div class="mb-3">
                <label for="moneda_simbolo" class="form-label">Símbolo de Moneda</label>
                <div class="input-group">
                    <input type="text" class="form-control" id="moneda_simbolo" name="moneda_simbolo" 
                           value="<?php echo htmlspecialchars($moneda_actual); ?>" required>
                    <span class="input-group-text">Ejemplo: S/, $, €, US$</span>
                </div>
                <div class="form-text">Este símbolo se mostrará en todos los reportes y costos del sistema.</div>
            </div>

            <hr>
            <button type="submit" class="btn btn-primary">
                <i class="bi bi-save me-2"></i> Guardar Configuración
            </button>
        </form>
    </div>
</div>

<?php require_once '../templates/footer.php'; ?>