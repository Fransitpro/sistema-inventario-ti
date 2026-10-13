/* =============================================================
   Barra de desplazamiento vertical personalizada
   -------------------------------------------------------------
   Motivo: Chrome y Edge en Windows usan barras "Fluent"
   superpuestas que se ocultan solas y que IGNORAN el estilo
   CSS ::-webkit-scrollbar. Por eso el estilo del sistema nunca
   se ve en esos navegadores.

   Aqui se dibuja una barra propia, controlable y arrastrable.
   No se secuestra la rueda: el desplazamiento sigue siendo el
   nativo, esta barra solo lo refleja y permite arrastrarlo.
   ============================================================= */
(function () {
    'use strict';

    // Solo para raton y pantallas grandes: en tactil la barra nativa
    // (que se oculta sola) es mejor y no estorba.
    // Solo en pantallas con ratón: en táctil la barra del sistema, que se
    // oculta sola, es mejor y no estorba al deslizar el dedo.
    // Se comprueba 'pointer: coarse' y no 'hover/pointer: fine' porque hay
    // entornos (navegadores sin ventana) donde eso da falso y se perdería.
    var esTactil = window.matchMedia('(pointer: coarse)').matches;
    if (esTactil) { return; }

    // Firefox SÍ respeta scrollbar-width/scrollbar-color, así que ya muestra
    // la barra con el estilo buscado y no necesita esta.
    // No se puede detectar con CSS.supports: Chrome también lo soporta, pero
    // lo ignora por usar barras superpuestas.
    // OJO: no se busca 'Gecko' porque TODOS los navegadores lo incluyen en
    // "AppleWebKit/537.36 (KHTML, like Gecko)". Solo se busca 'Firefox'.
    var esFirefox = navigator.userAgent.indexOf('Firefox') !== -1;
    if (esFirefox) { return; }

    var ANCHO_PISTA = 10;   // ancho de la pista
    var ALTO_MINIMO = 44;   // alto minimo del cursor para poder agarrarlo
    var OCULTAR_MS = 2200;  // se oculta tras este tiempo sin actividad

    var pista = document.createElement('div');
    pista.id = 'barra-scroll-personalizada';
    pista.setAttribute('aria-hidden', 'true');

    var cursor = document.createElement('div');
    cursor.className = 'bs-cursor';
    pista.appendChild(cursor);
    document.body.appendChild(pista);

    var oculto = false;

    function mostrar() {
        if (oculto) {
            oculto = false;
            pista.classList.add('visible');
        }
        reiniciarTemporizador();
    }

    function ocultar() {
        if (!pista.classList.contains('visible')) { return; }
        pista.classList.remove('visible');
    }

    var temporizador = null;
    function reiniciarTemporizador() {
        if (temporizador) { clearTimeout(temporizador); }
        temporizador = setTimeout(ocultar, OCULTAR_MS);
    }

    // Datos reales del documento
    function medidas() {
        var doc = document.documentElement;
        return {
            alto: doc.scrollHeight,
            visible: window.innerHeight,
        };
    }

    function actualizar() {
        var m = medidas();
        var maxScroll = m.alto - m.visible;

        // Si no hay nada que desplazar, no se muestra
        if (maxScroll <= 0) {
            pista.classList.remove('visible', 'disponible');
            return;
        }
        pista.classList.add('disponible');

        var altoPista = pista.clientHeight - ALTO_MINIMO;
        var proporcion = m.visible / m.alto;
        var altoCursor = Math.max(ALTO_MINIMO, Math.round(m.visible * proporcion));

        var avance = window.scrollY / maxScroll;
        var tope = Math.round(avance * Math.max(0, altoPista));

        cursor.style.height = altoCursor + 'px';
        cursor.style.transform = 'translateY(' + tope + 'px)';
    }

    // ---- Arrastrar el cursor ----
    var arrastrando = false;
    var inicioY = 0;
    var inicioCursor = 0;

    cursor.addEventListener('pointerdown', function (ev) {
        arrastrando = true;
        inicioY = ev.clientY;
        inicioCursor = cursor.getBoundingClientRect().top;
        cursor.classList.add('arrastrando');
        cursor.setPointerCapture(ev.pointerId);
        ev.preventDefault();
    });

    cursor.addEventListener('pointermove', function (ev) {
        if (!arrastrando) { return; }

        var doc = document.documentElement;
        var maxScroll = doc.scrollHeight - window.innerHeight;
        var altoPista = pista.clientHeight - ALTO_MINIMO;
        if (maxScroll <= 0 || altoPista <= 0) { return; }

        var delta = ev.clientY - inicioY;
        var nuevoTope = inicioCursor + delta;

        if (nuevoTope < 0) { nuevoTope = 0; }
        if (nuevoTope > altoPista) { nuevoTope = altoPista; }

        window.scrollTo(0, (nuevoTope / altoPista) * maxScroll);
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

        var doc = document.documentElement;
        var maxScroll = doc.scrollHeight - window.innerHeight;
        if (maxScroll <= 0) { return; }

        var cajaPista = pista.getBoundingClientRect();
        var proporcion = (ev.clientY - cajaPista.top) / cajaPista.height;
        proporcion = Math.min(1, Math.max(0, proporcion));

        window.scrollTo(0, proporcion * maxScroll);
    });

    // ---- Reflejar el scroll y detectar contenido que cambia de alto ----
    window.addEventListener('scroll', function () { actualizar(); mostrar(); }, { passive: true });

    window.addEventListener('resize', actualizar);

    // DataTables y los modales cambian el alto del documento en caliente
    if (window.ResizeObserver) {
        new ResizeObserver(actualizar).observe(document.body);
    }

    // Al ocultar la barra no debe quedar el cursor flotando
    window.addEventListener('beforeprint', function () { ocultar(); });

    actualizar();
    mostrar();

    // Se expone para poder depurar desde la consola
    window.__barraScroll = { actualizar: actualizar, ocultar: ocultar, mostrar: mostrar };
})();