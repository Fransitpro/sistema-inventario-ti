</main>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.1/js/dataTables.buttons.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.1/js/buttons.bootstrap5.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.1.53/pdfmake.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.1.53/vfs_fonts.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.1/js/buttons.html5.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.1/js/buttons.print.min.js"></script>
<script src="assets/js/exportar-a4.js?ver=<?php echo time(); ?>"></script>

<script src="assets/js/barra-scroll.js?ver=<?php echo time(); ?>"></script>
<script src="assets/js/barra-horizontal.js?ver=<?php echo time(); ?>"></script>
<script>
document.addEventListener("DOMContentLoaded", function() {
    const menuToggle = document.getElementById('menu-toggle');
    const sidebar = document.getElementById('sidebar');
    const overlay = document.getElementById('sidebar-overlay');

    // Función para abrir/cerrar menú
    function toggleMenu() {
        sidebar.classList.toggle('active');
        overlay.classList.toggle('active');
    }

    // Evento Click en Botón Hamburguesa
    if (menuToggle) {
        menuToggle.addEventListener('click', function(e) {
            e.stopPropagation(); // Evitar que el clic se propague
            toggleMenu();
        });
    }

    // Evento Click en el Overlay (Fondo oscuro) para cerrar
    if (overlay) {
        overlay.addEventListener('click', function() {
            sidebar.classList.remove('active');
            overlay.classList.remove('active');
        });
    }

    // ---- Desplazamiento horizontal con la rueda (Shift + rueda) ----
    // Las tablas tienen muchas columnas y sobresalen a la derecha. El
    // desplazamiento horizontal nativo con Shift+rueda no es fiable en todos
    // los navegadores, así que aquí se fuerza sobre el contenedor que tenga
    // scroll horizontal. Sin Shift la rueda sigue bajando la página: no se
    // secuestra el desplazamiento vertical, que es el uso principal.
    document.addEventListener('wheel', function (ev) {
        // Shift + rueda, o bien el gesto lateral de un trackpad (deltaX).
        // Sin ninguno de los dos, la rueda sigue bajando la página: no se
        // secuestra el desplazamiento vertical, que es el uso principal.
        var gestoHorizontal = ev.shiftKey || Math.abs(ev.deltaX) > Math.abs(ev.deltaY);
        if (!gestoHorizontal) { return; }

        var el = ev.target;
        while (el && el !== document.body) {
            if (el.scrollWidth > el.clientWidth + 1) {
                var overflowX = getComputedStyle(el).overflowX;
                if (overflowX === 'auto' || overflowX === 'scroll' || overflowX === 'overlay') {
                    ev.preventDefault();
                    // Con Shift el navegador invierte los ejes: el gesto llega
                    // en deltaY aunque el dedo se mueva en horizontal.
                    el.scrollLeft += ev.deltaX || ev.deltaY;
                    return;
                }
            }
            el = el.parentElement;
        }
    }, { passive: false });

    // ---- Recordar la posición del scroll al volver ----
    // Al abrir el detalle de un registro y volver atrás, el listado se
    // devuelve a la fila donde se estaba, en lugar de saltar arriba.
    // Se usa sessionStorage: se conserva mientras la pestaña siga abierta.
    (function () {
        var clave = 'scrollPos:' + location.pathname;
        var pendiente = null;

        try {
            var guardado = sessionStorage.getItem(clave);
            if (guardado !== null) {
                pendiente = parseInt(guardado, 10);
            }
        } catch (e) {
            // Modo privado o almacenamiento bloqueado: se sigue sin recordar
        }

        function guardar() {
            try {
                sessionStorage.setItem(clave, String(Math.round(window.scrollY)));
            } catch (e) { }
        }

        window.addEventListener('pagehide', guardar);
        window.addEventListener('beforeunload', guardar);

        // También al pulsar un enlace, porque en algunos navegadores
        // 'beforeunload' no llega a ejecutarse.
        document.addEventListener('click', function (ev) {
            var enlace = ev.target.closest ? ev.target.closest('a[href]') : null;
            if (!enlace) { return; }
            var href = enlace.getAttribute('href') || '';
            if (href.charAt(0) === '#' || href.indexOf('javascript:') === 0) { return; }
            guardar();
        }, true);

        if (pendiente !== null && !isNaN(pendiente) && pendiente > 0) {
            // El navegador gestiona su propia restauración al usar "atrás" y
            // puede competir con la nuestra, así que se le aparta.
            if ('scrollRestoration' in history) {
                history.scrollRestoration = 'manual';
            }

            // Un solo scrollTo no basta: si la tabla aún no tiene su alto
            // definitivo (DataTables, fuentes web), la posición se recorta.
            // Se reintenta hasta que la posición se sostenga.
var intentos = 0;
            var maxIntentos = 90; // ~1,5 s a 60 fps
            var cancelado = false;

            // Si el usuario toca la rueda, el dedo o el teclado nada más volver,
            // se abandona la restauración: si no, el bucle le pelea el scroll.
            var abandonar = function () { cancelado = true; };
            ['wheel', 'touchstart', 'keydown', 'mousedown'].forEach(function (evt) {
                window.addEventListener(evt, abandonar, { passive: true });
            });

            var intentarRestaurar = function () {
                if (cancelado) { return; }
                window.scrollTo(0, pendiente);

                if (window.scrollY + 5 >= pendiente) {
                    return; // Ya está en el sitio: seguir insistir no aporta
                }
                intentos++;
                if (intentos < maxIntentos) {
                    requestAnimationFrame(intentarRestaurar);
                }
            };

            window.addEventListener('load', function () {
                requestAnimationFrame(intentarRestaurar);
            });
            if (document.readyState === 'complete') {
                requestAnimationFrame(intentarRestaurar);
            }
        }
    })();

    // ---- La rueda del mouse sobre el menú lateral ----
    // Con ventanas bajas el sidebar se vuelve desplazable y se queda con la
    // rueda: al llegar a su tope o a su fondo la página dejaba de responder.
    // Aquí se reenvía la rueda a la página cuando el menú ya no puede más.
    if (sidebar) {
        sidebar.addEventListener('wheel', function(ev) {
            const alTope = sidebar.scrollTop <= 0;
            const alFondo = (sidebar.scrollTop + sidebar.clientHeight) >= (sidebar.scrollHeight - 1);

            if ((ev.deltaY < 0 && alTope) || (ev.deltaY > 0 && alFondo)) {
                // Se cancela el scroll nativo del menú y se desplaza la página.
                ev.preventDefault();
                window.scrollBy(0, ev.deltaY);
            }
        }, { passive: false });
    }

    // ---- Scroll suave del menú lateral ----
    if (sidebar) {
        const activeLink = sidebar.querySelector('.nav-link.active');

        if (activeLink) {
            // requestAnimationFrame: espera a que el layout esté calculado.
            requestAnimationFrame(() => {
                const sb = sidebar.getBoundingClientRect();
                const al = activeLink.getBoundingClientRect();

                // Solo si el enlace activo no cabe en la zona visible del menú.
                if (al.top < sb.top || al.bottom > sb.bottom) {
                    activeLink.scrollIntoView({ behavior: 'smooth', block: 'center' });
                    activeLink.classList.add('nav-link-resaltado');
                    setTimeout(() => activeLink.classList.remove('nav-link-resaltado'), 1300);
                }
            });
        }
    }

    // Scroll suave para anclas internas (#ancla)
    document.querySelectorAll('a[href^="#"]:not([data-bs-toggle])').forEach((enlace) => {
        enlace.addEventListener('click', function(ev) {
            const destino = document.querySelector(this.getAttribute('href'));
            if (destino) {
                ev.preventDefault();
                destino.scrollIntoView({ behavior: 'smooth', block: 'start' });
            }
        });
    });
});
</script>

</body>
</html>
<?php
if (isset($conexion) && $conexion instanceof mysqli) {
    $conexion->close();
}
?>