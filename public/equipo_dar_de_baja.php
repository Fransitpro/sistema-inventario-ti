<?php
require_once '../templates/header.php';

// --- Procesar el formulario cuando se envía (POST) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id_equipo = $_POST['id_equipo'];
    $fecha_baja = $_POST['fecha_baja'];
    $motivo = $_POST['motivo'];
    $observaciones = $_POST['observaciones'];
    $acta_path = null;

    // Lógica para subir el archivo del acta (si se adjunta)
    if (isset($_FILES['acta_baja']) && $_FILES['acta_baja']['error'] === UPLOAD_ERR_OK) {
        $upload_dir = '../uploads/bajas/'; // Asegúrate de que esta carpeta exista y tenga permisos de escritura
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }
        $file_name = "acta_baja_" . $id_equipo . "_" . time() . '.' . pathinfo($_FILES['acta_baja']['name'], PATHINFO_EXTENSION);
        $acta_path = $file_name;
        if (!move_uploaded_file($_FILES['acta_baja']['tmp_name'], $upload_dir . $file_name)) {
            // Manejar error si la subida falla
            echo '<div class="alert alert-danger">Error al subir el archivo del acta.</div>';
            // Podrías decidir si continuar sin el acta o detener el proceso
            $acta_path = null; // Asegurarse de no guardar una ruta si la subida falló
        }
    }

    $conexion->begin_transaction();
    try {
        // 1. Actualizar estado del equipo
        $stmt_eq = $conexion->prepare("UPDATE equipos SET estado = 'De Baja' WHERE id = ?");
        $stmt_eq->bind_param("i", $id_equipo);
        $stmt_eq->execute();

        // 2. Insertar registro en la tabla 'bajas'
        $stmt_baja = $conexion->prepare("INSERT INTO bajas (id_equipo, fecha_baja, motivo, observaciones, acta_baja_path) VALUES (?, ?, ?, ?, ?)");
        $stmt_baja->bind_param("issss", $id_equipo, $fecha_baja, $motivo, $observaciones, $acta_path);
        $stmt_baja->execute();

        $conexion->commit();
        header("Location: equipos.php?status=baja_exitosa");
        exit();
    } catch (mysqli_sql_exception $exception) {
        $conexion->rollback();
        // Mostrar un mensaje de error más detallado (considera quitarlo en producción)
        echo '<div class="alert alert-danger">Error al dar de baja el equipo: ' . $exception->getMessage() . '</div>';
        // Puedes añadir un enlace para volver atrás o registrar el error
    }
}

// --- Mostrar el formulario (GET) ---
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    echo '<div class="alert alert-danger">Error: ID de equipo no válido.</div>';
    require_once '../templates/footer.php';
    exit();
}
$id_equipo = (int)$_GET['id'];
$equipo = $conexion->query("SELECT e.codigo_inventario FROM equipos e WHERE e.id = $id_equipo")->fetch_assoc();
if (!$equipo) {
    echo '<div class="alert alert-warning">Equipo no encontrado.</div>';
    require_once '../templates/footer.php';
    exit();
}
?>

<h1 class="h2 mb-4">Dar de Baja Equipo</h1>

<div class="card">
    <div class="card-header">
        Confirmar Baja para el Equipo: <?php echo htmlspecialchars($equipo['codigo_inventario']); ?>
    </div>
    <div class="card-body">
        <div class="alert alert-danger">
            <strong>¡Atención!</strong> Esta acción es irreversible. El estado del equipo cambiará a "De Baja" y no podrá ser asignado ni modificado.
        </div>
        
        <form action="equipo_dar_de_baja.php" method="POST" enctype="multipart/form-data">
            <input type="hidden" name="id_equipo" value="<?php echo $id_equipo; ?>">
            
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label for="fecha_baja" class="form-label">Fecha de Baja *</label>
                    <input type="date" class="form-control" id="fecha_baja" name="fecha_baja" value="<?php echo date('Y-m-d'); ?>" required>
                </div>
                <div class="col-md-6 mb-3">
                    <label for="motivo" class="form-label">Motivo de la Baja *</label>
                    <select class="form-select" id="motivo" name="motivo" required>
                        <option value="">Seleccione un motivo...</option>
                        <option value="Dañado sin reparación">Dañado sin reparación</option>
                        <option value="Obsoleto">Obsoleto</option>
                        <option value="Perdido / Robado">Perdido / Robado</option>
                        <option value="Otro">Otro</option>
                    </select>
                </div>
            </div>
            
            <div class="mb-3">
                <label for="observaciones" class="form-label">Observaciones</label>
                <textarea class="form-control" id="observaciones" name="observaciones" rows="4"></textarea>
            </div>

            <div class="mb-3">
                <label for="acta_baja" class="form-label">Adjuntar Acta de Baja (Opcional)</label>
                <input class="form-control" type="file" id="acta_baja" name="acta_baja" accept=".pdf, image/*">
                <small class="form-text text-muted">Formatos permitidos: PDF, JPG, PNG.</small>
            </div>
            
            <hr>
            
            <div class="d-flex justify-content-end gap-2">
                <a href="equipos.php" class="btn btn-secondary">Cancelar</a>
                
                <a id="btn-generar-acta" href="generar_acta_baja.php?id_equipo=<?php echo $id_equipo; ?>" target="_blank" class="btn btn-info">
                    <i class="bi bi-file-earmark-pdf me-2"></i> Generar Acta
                </a>
                
                <button type="submit" class="btn btn-danger">Confirmar Baja del Equipo</button>
            </div>
        </form>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        const motivoSelect = document.getElementById('motivo');
        const observacionesTextarea = document.getElementById('observaciones');
        const btnGenerarActa = document.getElementById('btn-generar-acta');
        
        // Asegúrate que btnGenerarActa no sea null antes de proceder
        if (btnGenerarActa) {
            const baseUrl = btnGenerarActa.href.split('?')[0]; 
            const idEquipo = <?php echo $id_equipo; ?>; 

            // Función para construir la URL actualizada
            function construirUrlActa() {
                const motivo = motivoSelect.value;
                const observaciones = observacionesTextarea.value;
                // Asegurarse de que los valores no estén vacíos antes de codificar
                const motivoEnc = motivo ? encodeURIComponent(motivo) : ''; 
                const observacionesEnc = observaciones ? encodeURIComponent(observaciones) : ''; 
                
                return `${baseUrl}?id_equipo=${idEquipo}&motivo=${motivoEnc}&observaciones=${observacionesEnc}`;
            }

            // Event listener para actualizar la URL JUSTO ANTES de seguir el enlace
            btnGenerarActa.addEventListener('click', function(event) {
                // Previene seguir el enlace inmediatamente
                event.preventDefault(); 
                // Actualiza el href con los valores actuales del formulario
                this.href = construirUrlActa(); 
                // Ahora sí, sigue el enlace actualizado (abre en nueva pestaña)
                window.open(this.href, '_blank'); 
            });

            // Opcional: Actualizar al cambiar (para que el hover muestre la URL actualizada)
            motivoSelect.addEventListener('change', function() { btnGenerarActa.href = construirUrlActa(); });
            observacionesTextarea.addEventListener('input', function() { btnGenerarActa.href = construirUrlActa(); });

            // Actualizar una vez al cargar por si hay valores iniciales
            btnGenerarActa.href = construirUrlActa(); 
        } else {
            console.error("Botón 'Generar Acta' no encontrado.");
        }
    });
</script>

<?php require_once '../templates/footer.php'; ?>