/* Exportación de reportes en A4 (Excel, PDF e Impresión) */
(function (global) {
    'use strict';

    function inyectarEstilos() {
        if (document.getElementById('estilos-a4')) return;

        var estilo = document.createElement('style');
        estilo.id = 'estilos-a4';
        estilo.textContent = [
            '@page { size: A4 landscape; margin: 10mm; }',
            '@media print {',
            '  body { background: #fff !important; }',
            '  .sidebar, .mobile-header, .no-print,',
            '  .dataTables_wrapper .dataTables_filter,',
            '  .dataTables_wrapper .dataTables_info,',
            '  .dataTables_wrapper .dataTables_paginate,',
            '  .dataTables_wrapper .dataTables_length { display: none !important; }',
            '  .main-content { margin: 0 !important; padding: 0 !important; }',
            '  .card { border: 1px solid #999 !important; box-shadow: none !important; break-inside: avoid; }',
            '  .table { font-size: 10px !important; }',
            '  .table th, .table td { padding: 3px 5px !important; }',
            '  thead { display: table-header-group; }',
            '}',
            '#a4-impresion { display: none; }',
            '@media print {',
            '  body > *:not(#a4-impresion) { display: none !important; }',
            '  body > #a4-impresion { display: block !important; }',
            '  #a4-impresion h1 { font-size: 18pt; margin: 0 0 2mm; }',
            '  #a4-impresion h2 { font-size: 10pt; font-weight: 400; color: #555; margin: 0 0 4mm; }',
            '  #a4-impresion table { width: 100%; border-collapse: collapse; font-size: 9pt; }',
            '  #a4-impresion th, #a4-impresion td { border: 1px solid #999; padding: 2px 4px; text-align: left; }',
            '  #a4-impresion thead { display: table-header-group; }',
            '}'
        ].join('\n');
        document.head.appendChild(estilo);
    }

    function columnaAcciones(tabla) {
        var cabeceras = tabla.querySelectorAll('thead th');
        for (var i = 0; i < cabeceras.length; i++) {
            if (cabeceras[i].textContent.trim() === 'Acciones') return i;
        }
        return -1;
    }

    function filasVisibles(tabla) {
        return Array.prototype.filter.call(tabla.querySelectorAll('tbody tr'), function (fila) {
            if (fila.style.display === 'none' || fila.classList.contains('d-none')) return false;
            if (fila.children.length === 1 && fila.children[0].hasAttribute('colspan')) return false;
            return true;
        });
    }

    function resolver(objetivo) {
        if (!objetivo) return null;
        if (typeof objetivo !== 'string') return objetivo;
        return document.getElementById(objetivo.replace(/^#/, '')) || document.querySelector(objetivo);
    }

    function datos(objetivo) {
        var tabla = resolver(objetivo);
        if (!tabla) return null;

        var acciones = columnaAcciones(tabla);
        var cabeceras = Array.prototype.slice.call(tabla.querySelectorAll('thead th'))
            .filter(function (th, i) { return i !== acciones; })
            .map(function (th) { return th.textContent.trim(); });

        var filas = filasVisibles(tabla).map(function (fila) {
            return Array.prototype.slice.call(fila.children)
                .filter(function (td, i) { return i !== acciones; })
                .map(function (td) { return td.textContent.trim().replace(/\s+/g, ' '); });
        });

        // data-a4-subtitulo admite varias lineas separadas por "|".
        // Cada linea puede traer su alineacion: "center:TEXTO" (por defecto left).
        var subtitulos = (tabla.getAttribute('data-a4-subtitulo') || '')
            .split('|')
            .map(function (s) { return s.trim(); })
            .filter(function (s) { return s !== ''; })
            .map(function (s) {
                var partes = s.split(':');
                var alineacion = (partes.length > 1 ? partes.shift().trim().toLowerCase() : 'left');
                if (['left', 'center', 'right'].indexOf(alineacion) === -1) {
                    alineacion = 'left';
                    return { texto: s, alineacion: 'left' };
                }
                return { texto: partes.join(':').trim(), alineacion: alineacion };
            });

        // data-a4-fila: textos que van en una misma linea, repartidos
        // como izquierda / centro / derecha segun cuantos sean.
        var fila = (tabla.getAttribute('data-a4-fila') || '')
            .split('|')
            .map(function (s) { return s.trim(); })
            .filter(function (s) { return s !== ''; });

        return {
            cabeceras: cabeceras,
            filas: filas,
            centrar: tabla.getAttribute('data-a4-centrar') === '1',
            subtitulos: subtitulos,
            fila: fila
        };
    }

    function htmlTabla(datosTabla) {
        if (!datosTabla.filas.length) {
            return '<p><em>No hay registros para los filtros seleccionados.</em></p>';
        }

        var alineacion = datosTabla.centrar ? ' style="text-align:center"' : '';
        var html = '<table><thead><tr>';
        datosTabla.cabeceras.forEach(function (c) { html += '<th' + alineacion + '>' + c + '</th>'; });
        html += '</tr></thead><tbody>';

        datosTabla.filas.forEach(function (fila) {
            html += '<tr>';
            fila.forEach(function (celda) { html += '<td' + alineacion + '>' + celda + '</td>'; });
            html += '</tr>';
        });

        return html + '</tbody></table>';
    }

    // Reparte los textos de una misma linea en izquierda / centro / derecha
    function posicionesFila(textos) {
        var columnas = ['', '', ''];
        textos.forEach(function (t, i) { if (i < 3) { columnas[i] = t; } });
        return columnas;
    }

    function documento(titulo, subtitulo, datosTabla) {
        var estiloTitulo = datosTabla.centrar ? ' style="text-align:center"' : '';

        var htmlFila = '';
        if (datosTabla.fila && datosTabla.fila.length) {
            // La fila de encabezado usa las mismas columnas que la tabla:
            // el primer texto en A2 y el segundo (LOCAL) en la columna G2.
            var total = Math.max(datosTabla.cabeceras.length, 1);
            var colLocal = Math.min(7, total);
            var textos = {};
            textos[1] = { t: datosTabla.fila[0], a: 'left' };
            if (datosTabla.fila.length > 1) { textos[colLocal] = { t: datosTabla.fila[1], a: 'center' }; }
            if (datosTabla.fila.length > 2) { textos[total] = { t: datosTabla.fila[2], a: 'right' }; }

            var celdas = '';
            for (var i = 1; i <= total; i++) {
                var celda = textos[i];
                celdas += '<td style="border:0;font-weight:bold;font-size:10pt;text-align:'
                    + (celda ? celda.a : 'left') + '">' + (celda ? celda.t : '') + '</td>';
            }

            htmlFila = '<table class="sub" style="width:100%;border-collapse:collapse;margin:0 0 2px">'
                + '<tr>' + celdas + '</tr></table>';
        }

        var lineas = (datosTabla.subtitulos || []).map(function (linea) {
            return '<p class="sub" style="text-align:' + linea.alineacion + '; font-weight:bold; font-size:10pt; margin:0 0 2px">' + linea.texto + '</p>';
        }).join('');

        return '<!DOCTYPE html><html lang="es"><head><meta charset="utf-8"><title>' + titulo + '</title><style>'
            + '@page { size: A4 landscape; margin: 10mm; }'
            + 'body { font-family: Arial, Helvetica, sans-serif; font-size: 10pt; color: #000; margin: 0; }'
            + 'h1 { font-size: 14pt; margin: 0 0 2px; }'
            + 'p.sub { margin: 0 0 10px; font-size: 9pt; color: #444; }'
            + 'table { border-collapse: collapse; width: 100%; }'
            + 'th, td { border: 1px solid #333; padding: 3px 5px; text-align: left; font-size: 9pt; }'
            + 'th { background: #e9ecef; }'
            + 'tr { page-break-inside: avoid; }'
            + '</style></head><body><h1' + estiloTitulo + '>' + titulo + '</h1>'
            + htmlFila
            + lineas
            + '<p class="sub">' + subtitulo + ' &middot; Generado: ' + new Date().toLocaleDateString('es-ES') + '</p>'
            + htmlTabla(datosTabla) + '</body></html>';
    }

    function textoFiltros() {
        var partes = [];

        Array.prototype.forEach.call(
            document.querySelectorAll(
                '#estado, select[name="sucursal"], select[name="marca"], select[name="tipo"],' +
                ' select[name="estado"], input[name="texto"], input[name="anio"],' +
                ' input[name="fecha_desde"], input[name="fecha_hasta"]'
            ),
            function (campo) {
                if (!campo.value) return;

                var valor = campo.value;
                if (campo.tagName === 'SELECT') {
                    valor = campo.options[campo.selectedIndex].text.trim();
                }

                var contenedor = campo.closest('.col, .col-md-3, .col-md-6, .col-md-2');
                var etiqueta = (contenedor && contenedor.querySelector('label'))
                    ? contenedor.querySelector('label').textContent.trim()
                    : campo.name;

                partes.push(etiqueta + ': ' + valor);
            }
        );

        return partes.length ? partes.join('  |  ') : 'Sin filtros aplicados';
    }

    function nombreArchivo(titulo) {
        return titulo
            .normalize('NFD')
            .replace(/[\u0300-\u036f]/g, '')
            .replace(/[^A-Za-z0-9]+/g, '_');
    }

    function descargar(contenido, nombre, tipo) {
        var blob = new Blob(['﻿' + contenido], { type: tipo });
        var enlace = document.createElement('a');
        enlace.href = URL.createObjectURL(blob);
        enlace.download = nombre;
        document.body.appendChild(enlace);
        enlace.click();
        document.body.removeChild(enlace);
        setTimeout(function () { URL.revokeObjectURL(enlace.href); }, 1000);
    }

    function excel(opciones) {
        var d = datos(opciones.tabla);
        if (!d) return;

        descargar(
            documento(opciones.titulo, opciones.subtitulo, d),
            nombreArchivo(opciones.titulo) + '.xls',
            'application/vnd.ms-excel;charset=utf-8'
        );
    }

    function pdf(opciones) {
        var d = datos(opciones.tabla);
        if (!d) return;

        if (!d.filas.length) {
            alert('No hay registros para exportar.');
            return;
        }

        if (!global.pdfMake) {
            imprimir(opciones);
            return;
        }

        var cuerpo = [d.cabeceras].concat(d.filas);

        if (d.centrar) {
            cuerpo = cuerpo.map(function (fila) {
                return fila.map(function (celda) { return { text: celda, alignment: 'center' }; });
            });
        }

        var titulo = { text: opciones.titulo, fontSize: 14, bold: true, margin: [0, 0, 0, 2] };
        if (d.centrar) { titulo.alignment = 'center'; }

        var cabecera = [titulo];

        if (d.fila && d.fila.length) {
            var col = posicionesFila(d.fila);
            cabecera.push({
                table: {
                    widths: ['40%', '20%', '40%'],
                    body: [[
                        { text: col[0], alignment: 'left', bold: true, fontSize: 11 },
                        { text: col[1], alignment: 'center', bold: true, fontSize: 11 },
                        { text: col[2], alignment: 'right', bold: true, fontSize: 11 }
                    ]]
                },
                margin: [0, 0, 0, 2],
                layout: 'noBorders'
            });
        }

        (d.subtitulos || []).forEach(function (linea) {
            cabecera.push({
                text: linea.texto,
                fontSize: 11,
                bold: true,
                alignment: linea.alineacion,
                margin: [0, 0, 0, 2]
            });
        });

        global.pdfMake.createPdf({
            pageSize: 'A4',
            pageOrientation: 'landscape',
            pageMargins: [28, 32, 28, 32],
            defaultStyle: { fontSize: 8, lineWidth: 0.5, cellPadding: 3 },
            footer: function (paginas) {
                return {
                    text: 'Pagina ' + paginas.pageNumber + ' de ' + paginas.pages,
                    alignment: 'right',
                    fontSize: 8,
                    margin: [0, 8, 0, 0]
                };
            },
            content: cabecera.concat([
                {
                    text: opciones.subtitulo + '  |  Generado: ' + new Date().toLocaleDateString('es-ES'),
                    fontSize: 9,
                    color: '#444',
                    margin: [0, 0, 0, 8]
                },
                {
                    table: { headerRows: 1, widths: 'auto', body: cuerpo },
                    layout: 'lightHorizontalLines'
                }
            ])
        }).download(nombreArchivo(opciones.titulo) + '.pdf');
    }

    function imprimir(opciones) {
        var d = datos(opciones.tabla);
        if (!d) return;

        var ventana = global.open('', '_blank', 'width=1152,height=794');
        if (!ventana) {
            alert('Permite las ventanas emergentes para poder imprimir.');
            return;
        }

        ventana.document.open();
        ventana.document.write(documento(opciones.titulo, opciones.subtitulo, d));
        ventana.document.close();
        ventana.focus();
        setTimeout(function () { ventana.print(); }, 400);
    }

    function imprimirEnEstaPagina(opciones) {
        var d = datos(opciones.tabla);
        if (!d) return;

        var contenedor = document.getElementById('a4-impresion');
        if (!contenedor) {
            contenedor = document.createElement('div');
            contenedor.id = 'a4-impresion';
            document.body.appendChild(contenedor);
        }
        contenedor.innerHTML = documento(opciones.titulo, opciones.subtitulo, d);

        function limpiar() {
            if (contenedor && contenedor.parentNode) contenedor.parentNode.removeChild(contenedor);
        }

        global.addEventListener('afterprint', limpiar, { once: true });
        global.print();
        setTimeout(limpiar, 3000);
    }

    function conectar(botonId, accion) {
        var boton = document.getElementById(botonId);
        if (boton) boton.addEventListener('click', accion);
    }

    var registros = [];

    function registrar(config) {
        registros.push(config);

        conectar(config.excel, function () { excel(config); });
        conectar(config.pdf, function () { pdf(config); });
        conectar(config.print, function () { imprimir(config); });
    }

    function autoEjecutar() {
        if (registros.length === 0) return;

        var parametros = new URLSearchParams(window.location.search);
        var tipo = parametros.get('a4');
        if (!tipo) return;

        var acciones = { excel: excel, pdf: pdf, imprimir: imprimirEnEstaPagina };
        if (!acciones[tipo]) return;

        var clave = parametros.get('reporte');
        var elegido = clave
            ? registros.filter(function (r) { return r.clave === clave; })[0]
            : registros[0];

        var url = new URL(window.location.href);
        url.searchParams.delete('a4');
        url.searchParams.delete('reporte');
        window.history.replaceState({}, '', url.toString());

        if (!elegido) return;
        setTimeout(function () { acciones[tipo](elegido); }, 250);
    }

    function opcionesDe(elemento) {
        var tabla = document.querySelector(elemento.getAttribute('data-a4-tabla'));
        var titulo = elemento.getAttribute('data-a4-titulo')
            || (document.querySelector('main h1') || {}).textContent
            || document.title;

        return {
            tabla: tabla,
            titulo: titulo.trim(),
            subtitulo: elemento.getAttribute('data-a4-subtitulo') || textoFiltros()
        };
    }

    function iniciar() {
        inyectarEstilos();

        Array.prototype.forEach.call(
            document.querySelectorAll('main table[data-a4-titulo]'),
            function (tabla) {
                registrar({
                    tabla: tabla,
                    titulo: (tabla.getAttribute('data-a4-titulo') || '').trim(),
                    subtitulo: tabla.getAttribute('data-a4-subtitulo') || textoFiltros(),
                    excel: 'export-excel',
                    pdf: 'export-pdf',
                    print: 'export-print'
                });
            }
        );
    }

    global.A4 = {
        excel: excel,
        pdf: pdf,
        imprimir: imprimir,
        imprimirEnEstaPagina: imprimirEnEstaPagina,
        datos: datos,
        documento: documento,
        textoFiltros: textoFiltros,
        opcionesDe: opcionesDe,
        conectar: conectar,
        registrar: registrar,
        autoEjecutar: autoEjecutar,
        iniciar: iniciar
    };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', iniciar);
    } else {
        iniciar();
    }

    window.addEventListener('load', autoEjecutar);
})(window);
