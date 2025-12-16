<?php
require_once '../templates/header.php';

// Seguridad: Solo administradores
if (!isset($_SESSION['user_rol']) || (strtolower($_SESSION['user_rol']) !== 'administrador' && strtolower($_SESSION['user_rol']) !== 'admin')) {
    echo '<div class="alert alert-danger m-4">Acceso denegado. Permisos insuficientes.</div>';
    require_once '../templates/footer.php';
    exit();
}
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h2 text-dark fw-bold"><i class="bi bi-database-fill-gear me-2"></i> Copia de Seguridad y Restauración</h1>
</div>

<?php
if (isset($_GET['status'])) {
    if ($_GET['status'] === 'success_backup') {
        echo '<script>
                document.addEventListener("DOMContentLoaded", function() {
                    Swal.fire({
                        icon: "success",
                        title: "¡Respaldo Generado!",
                        text: "La copia de seguridad se ha descargado correctamente.",
                        confirmButtonColor: "#2563eb"
                    });
                });
              </script>';
    } elseif ($_GET['status'] === 'success_restore') {
        echo '<script>
                document.addEventListener("DOMContentLoaded", function() {
                    Swal.fire({
                        icon: "success",
                        title: "¡Sistema Restaurado!",
                        text: "La base de datos ha sido actualizada con éxito.",
                        confirmButtonColor: "#2563eb"
                    });
                });
              </script>';
    } elseif ($_GET['status'] === 'error') {
        $msg = htmlspecialchars($_GET['msg'] ?? 'Error desconocido');
        echo '<script>
                document.addEventListener("DOMContentLoaded", function() {
                    Swal.fire({
                        icon: "error",
                        title: "Error",
                        text: "'.$msg.'",
                        confirmButtonColor: "#d33"
                    });
                });
              </script>';
    }
}
?>

<div class="row g-4">
    <div class="col-md-6">
        <div class="card h-100 shadow-sm border-0">
            <div class="card-header bg-primary text-white fw-bold py-3" style="border-radius: 12px 12px 0 0;">
                <i class="bi bi-download me-2"></i> Exportar Base de Datos
            </div>
            <div class="card-body text-center p-5">
                <div class="mb-4">
                    <i class="bi bi-database-down text-primary" style="font-size: 5rem; opacity: 0.8;"></i>
                </div>
                <h4 class="card-title fw-bold text-dark">Generar Respaldo</h4>
                <p class="card-text text-muted mb-4 px-4">
                    Crea y descarga un archivo <strong>.sql</strong> con toda la información actual del sistema para resguardar tus datos.
                </p>
                <a href="generar_backup.php" class="btn btn-primary btn-lg w-100 py-3 rounded-pill shadow-sm">
                    <i class="bi bi-cloud-download me-2"></i> Descargar Copia
                </a>
            </div>
        </div>
    </div>

    <div class="col-md-6">
        <div class="card h-100 shadow-sm border-0">
            <div class="card-header bg-warning text-dark fw-bold py-3" style="border-radius: 12px 12px 0 0;">
                <i class="bi bi-upload me-2"></i> Restaurar Sistema
            </div>
            <div class="card-body text-center p-5">
                <div class="mb-4">
                    <i class="bi bi-database-up text-warning" style="font-size: 5rem; opacity: 0.8;"></i>
                </div>
                <h4 class="card-title fw-bold text-dark">Cargar Respaldo</h4>
                <p class="card-text text-muted mb-3 px-4">
                    Selecciona un archivo <strong>.sql</strong> para restaurar el sistema a un punto anterior.
                </p>
                
                <div class="alert alert-danger py-2 mb-4 small rounded-3">
                    <i class="bi bi-exclamation-triangle-fill me-1"></i> <strong>Advertencia:</strong> Esta acción borrará los datos actuales.
                </div>

                <form id="formRestaurar" action="restaurar_backup.php" method="POST" enctype="multipart/form-data">
                    <div class="mb-3 text-start">
                        <label class="form-label fw-bold small text-muted">Seleccionar archivo SQL:</label>
                        <input type="file" class="form-control" name="archivo_sql" id="archivo_sql" accept=".sql" required>
                    </div>
                    <button type="button" id="btnRestaurar" class="btn btn-warning w-100 py-3 fw-bold rounded-pill shadow-sm text-dark">
                        <i class="bi bi-arrow-counterclockwise me-2"></i> Restaurar Base de Datos
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<?php require_once '../templates/footer.php'; ?>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<script>
document.addEventListener('DOMContentLoaded', function() {
    
    // Lógica para el botón de Restaurar
    const btnRestaurar = document.getElementById('btnRestaurar');
    const formRestaurar = document.getElementById('formRestaurar');
    const archivoInput = document.getElementById('archivo_sql');

    btnRestaurar.addEventListener('click', function() {
        // 1. Validar que haya un archivo seleccionado
        if (!archivoInput.value) {
            Swal.fire({
                icon: 'warning',
                title: 'Falta el archivo',
                text: 'Por favor, selecciona un archivo .sql antes de continuar.',
                confirmButtonColor: '#f59e0b'
            });
            return;
        }

        // 2. Alerta Profesional de Confirmación
        Swal.fire({
            title: '¿Estás completamente seguro?',
            text: "Esta acción ELIMINARÁ todos los datos actuales del sistema y los reemplazará por los del archivo seleccionado. ¡No podrás deshacer esto!",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33', // Rojo para peligro
            cancelButtonColor: '#3085d6', // Azul para cancelar
            confirmButtonText: 'Sí, restaurar sistema',
            cancelButtonText: 'Cancelar',
            background: '#fff',
            customClass: {
                popup: 'rounded-4'
            }
        }).then((result) => {
            if (result.isConfirmed) {
                // Mostrar estado de carga
                let timerInterval;
                Swal.fire({
                    title: 'Restaurando...',
                    html: 'Por favor espera, procesando la base de datos.',
                    timerProgressBar: true,
                    didOpen: () => {
                        Swal.showLoading();
                        // Enviar el formulario
                        formRestaurar.submit(); 
                    }
                });
            }
        });
    });
});
</script>