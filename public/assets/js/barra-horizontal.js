/* =============================================================
   Barra de desplazamiento horizontal propia
   -------------------------------------------------------------
   Muchas tablas del sistema tienen mas columnas que la pantalla
   (reportes de equipos, empleados, asignaciones, inventario...).
   Chrome en Windows usa barras "Fluent" superpuestas que solo
   aparecen al pasar el raton, asi que no hay forma de saber que
   la tabla se desplaza a la derecha. Aqui se dibuja una barra
   propia, visible siempre y arrastrable, al pie de la tabla.

   Solo se inserta en los contenedores que de verdad se desbordan:
   si la tabla cabe, no se pinta nada.
   ============================================================= */
(function () {
    'use strict';

    var ANCHO_MINIMO_CURSOR = 36; // lo minimo para poder agarrar el cursor
    var MARGEN = 2;               // holgura del cursor dentro de la pista
    var CLASE_BARRA = 'barra-horizontal';

    function sobra(contenedor) {
        return contenedor.scrollWidth - contenedor.clientWidth;
    }

    /* Agrupa los contenedores con scroll horizontal. Se procesan todos a la
       vez, sin que unos aniden a otros (si uno esta dentro de otro, se queda
       solo el mas externo: es el unico que puede desplazarse de verdad). */
    function agrupar() {
        var todos = Array.prototype.filter.call(
            document.querySelectorAll('.table-responsive'),
            function (el) { return sobra(el) > 1 && getComputedStyle(el).overflowX !== 'hidden'; }
        );

        var externos = [];
        todos.forEach(function (el) {
            var dentroDeOtro = todos.some(function (otro) {
                return otro !== el && otro.contains(el);
            });
            if (!dentroDeOtro) { externos.push(el); }
        });

        return externos;
    }

    function montar(contenedor) {
        // Wrapper: envuelve tabla y barra para poder pegar la barra al borde
        // superior de la pantalla con "sticky". Asi sigue a la vista durante
        // toda la tabla, en vez de quedar al final de las 30 filas.
        var envoltorio = document.createElement('div');
        envoltorio.className = 'contenedor-tabla-desplazable';
        contenedor.parentNode.insertBefore(envoltorio, contenedor);

        var pista = document.createElement('div');
        pista.className = CLASE_BARRA;
        pista.setAttribute('aria-hidden', 'true');

        var cursor = document.createElement('div');
        cursor.className = 'bh-cursor';
        pista.appendChild(cursor);
        // Antes de la tabla: asi se ve al llegar al reporte y queda pegada
        // arriba mientras se baja por las filas.
        envoltorio.appendChild(contenedor);
        envoltorio.insertBefore(pista, contenedor);

        var arrastrando = false;
        var inicioX = 0;
        var inicioScroll = 0;

        function dibujar() {
            var desvio = sobra(contenedor);
            if (desvio <= 1) {
                pista.classList.add('oculta');
                return;
            }
            pista.classList.remove('oculta');

            var anchoPista = pista.clientWidth - MARGEN * 2;
            var anchoCursor = Math.max(
                ANCHO_MINIMO_CURSOR,
                Math.round(anchoPista * contenedor.clientWidth / contenedor.scrollWidth)
            );
            // Si el cursor no cabe en la pista (tabla immense), se reduce.
            anchoCursor = Math.min(anchoCursor, Math.max(ANCHO_MINIMO_CURSOR, anchoPista));

            var recorrido = Math.max(0, anchoPista - anchoCursor);
            var avance = contenedor.scrollLeft / desvio;

            cursor.style.width = anchoCursor + 'px';
            cursor.style.transform = 'translateX(' + Math.round(avance * recorrido) + 'px)';
        }

        contenedor.addEventListener('scroll', dibujar, { passive: true });
        window.addEventListener('resize', dibujar);

        // ---- Arrastrar el cursor ----
        cursor.addEventListener('pointerdown', function (ev) {
            arrastrando = true;
            inicioX = ev.clientX;
            inicioScroll = contenedor.scrollLeft;
            cursor.classList.add('arrastrando');
            cursor.setPointerCapture(ev.pointerId);
            ev.preventDefault();
        });

        cursor.addEventListener('pointermove', function (ev) {
            if (!arrastrando) { return; }
            // El cursor representa la parte visible de la tabla, asi que se
            // desplaza 1 a 1 con el raton: cada pixel del cursor son
            // scrollWidth/clientWidth pixeles de tabla.
            var porPixeles = contenedor.scrollWidth / contenedor.clientWidth;
            contenedor.scrollLeft = inicioScroll + (ev.clientX - inicioX) * porPixeles;
        });

        function soltar(ev) {
            if (!arrastrando) { return; }
            arrastrando = false;
            cursor.classList.remove('arrastrando');
            try { cursor.releasePointerCapture(ev.pointerId); } catch (e) { }
        }
        cursor.addEventListener('pointerup', soltar);
        cursor.addEventListener('pointercancel', soltar);

        // ---- Clic en la pista: saltar a esa posicion ----
        pista.addEventListener('pointerdown', function (ev) {
            if (ev.target === cursor) { return; }
            var caja = pista.getBoundingClientRect();
            var proporcion = (ev.clientX - caja.left - MARGEN) / caja.width;
            proporcion = Math.min(1, Math.max(0, proporcion));
            contenedor.scrollLeft = proporcion * sobra(contenedor);
        });

        dibujar();
        return dibujar;
    }

    function iniciar() {
        var pendientes = [];

        agrupar().forEach(function (contenedor) {
            if (contenedor.parentNode.classList &&
                contenedor.parentNode.classList.contains('contenedor-tabla-desplazable')) {
                return; // ya montada
            }
            pendientes.push(montar(contenedor));
        });

        if (!pendientes.length) { return; }

        function redibujarTodas() {
            pendientes.forEach(function (dibujar) { dibujar(); });
        }

        // DataTables, las fuentes web y las imagenes cambian el ancho despues
        // de pintar: hay que recalcular la barra cuando ya esta todo en su sitio.
        redibujarTodas();
        setTimeout(redibujarTodas, 250);
        setTimeout(redibujarTodas, 1000);
        window.addEventListener('load', redibujarTodas);

        // Al imprimir no pinta nada, pero el zoom del navegador cambia los anchos.
        window.addEventListener('beforeprint', redibujarTodas);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', iniciar);
    } else {
        iniciar();
    }
})();