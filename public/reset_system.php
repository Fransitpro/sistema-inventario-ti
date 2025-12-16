<?php
require_once '../templates/header.php';

// SEGURIDAD: Solo administradores pueden ver esto
if (!isset($_SESSION['user_rol']) || (strtolower($_SESSION['user_rol']) !== 'administrador' && strtolower($_SESSION['user_rol']) !== 'admin')) {
    echo '<div class="container mt-5"><div class="alert alert-danger shadow-sm border-0">⛔ Acceso denegado. Se requieren permisos de Administrador Global.</div></div>';
    require_once '../templates/footer.php';
    exit();
}
?>

<div class="container d-flex flex-column justify-content-center py-4" style="min-height: 85vh;">
    
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h1 class="h4 text-danger fw-bold m-0"><i class="bi bi-exclamation-octagon-fill me-2"></i> Zona de Peligro</h1>
        <a href="index.php" class="btn btn-outline-secondary btn-sm">Volver</a>
    </div>

    <?php
    if (isset($_GET['status'])) {
        if ($_GET['status'] === 'wrong_password') {
            echo '<div class="alert alert-danger py-2 shadow-sm border-0 mb-3"><i class="bi bi-shield-lock-fill me-2"></i> <strong>Error:</strong> Contraseña incorrecta.</div>';
        } elseif ($_GET['status'] === 'success') {
            echo '<div class="alert alert-success py-2 shadow-sm border-0 mb-3"><i class="bi bi-check-circle-fill me-2"></i> <strong>¡Listo!</strong> Sistema reiniciado correctamente.</div>';
        } elseif ($_GET['status'] === 'error') {
            echo '<div class="alert alert-danger py-2 shadow-sm border-0 mb-3"><i class="bi bi-bug-fill me-2"></i> Error: ' . htmlspecialchars($_GET['msg'] ?? '') . '</div>';
        }
    }
    ?>

    <div class="card border-danger shadow">
        <div class="card-header bg-danger text-white fw-bold py-2">
            <i class="bi bi-trash3-fill me-2"></i> Confirmación de Borrado Total
        </div>
        <div class="card-body text-center p-4">
            
            <div class="mb-2 text-danger animate-pulse">
                <i class="bi bi-radioactive" style="font-size: 3.5rem;"></i>
            </div>

            <h3 class="card-title text-danger fw-bold mb-2">¿Restablecer el sistema de fábrica?</h3>
            
            <p class="card-text text-muted mb-3 small">
                Esta acción eliminará <strong>PERMANENTEMENTE</strong> los siguientes datos:
            </p>

            <div class="row justify-content-center mb-3">
                <div class="col-lg-8">
                    <div class="row g-2 text-start">
                        <div class="col-md-6">
                            <div class="border rounded p-2 bg-light text-danger small">
                                <i class="bi bi-laptop me-2"></i> Inventario de Equipos
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="border rounded p-2 bg-light text-danger small">
                                <i class="bi bi-people me-2"></i> Registro de Empleados
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="border rounded p-2 bg-light text-danger small">
                                <i class="bi bi-card-checklist me-2"></i> Asignaciones
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="border rounded p-2 bg-light text-danger small">
                                <i class="bi bi-tools me-2"></i> Reparaciones y Bajas
                            </div>
                        </div>
                        <div class="col-12">
                            <div class="border rounded p-2 bg-light text-danger small text-center">
                                <i class="bi bi-tags me-2"></i> Catálogos (Marcas, Modelos, Tipos, Áreas)
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="alert alert-warning py-2 px-3 d-inline-block shadow-sm mb-3 border-warning small">
                <i class="bi bi-info-circle-fill me-1"></i> 
                <strong>Nota:</strong> Tu usuario, contraseña y rol de administrador <u>SE CONSERVARÁN</u>.
            </div>

            <form action="procesar_reset.php" method="POST" id="formReset">
                <div class="row justify-content-center">
                    <div class="col-md-5">
                        <input type="password" name="password_confirm" class="form-control text-center border-danger mb-3" placeholder="Ingresa tu contraseña para confirmar" required autocomplete="new-password">
                    </div>
                </div>

                <div class="d-flex justify-content-center gap-2">
                    <button type="button" class="btn btn-secondary px-4" onclick="window.location.href='index.php'">
                        Cancelar
                    </button>
                    <button type="button" id="btnConfirmar" class="btn btn-danger px-4 fw-bold">
                        <i class="bi bi-eraser-fill me-2"></i> BORRAR TODO
                    </button>
                </div>
            </form>

        </div>
    </div>
</div>

<?php require_once '../templates/footer.php'; ?>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
document.getElementById('btnConfirmar').addEventListener('click', function() {
    // Validar que haya escrito algo en la contraseña antes de mostrar la alerta
    const passInput = document.querySelector('input[name="password_confirm"]');
    if(passInput.value.trim() === ""){
        passInput.focus();
        passInput.classList.add('is-invalid');
        return;
    }

    Swal.fire({
        title: '¿ESTÁS SEGURO?',
        text: "¡Se borrarán todos los datos operativos! No hay vuelta atrás.",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#dc3545',
        cancelButtonColor: '#6c757d',
        confirmButtonText: 'Sí, Borrar',
        cancelButtonText: 'Cancelar',
        reverseButtons: true
    }).then((result) => {
        if (result.isConfirmed) {
            document.getElementById('formReset').submit();
        }
    })
});
</script>

<style>
@keyframes pulse-red {
    0% { transform: scale(1); opacity: 1; }
    50% { transform: scale(1.1); opacity: 0.8; }
    100% { transform: scale(1); opacity: 1; }
}
.animate-pulse {
    animation: pulse-red 2s infinite;
}
</style>