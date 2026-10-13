<?php
// Lista de colores del formulario de equipos.
// Se usa en equipo_agregar.php y equipo_editar.php para que ambos ofrezcan
// exactamente las mismas opciones.

/**
 * Devuelve las opciones <option> del selector de color.
 *
 * Combina:
 *  - Los colores que se piden siempre: Negro, Marrón, Plateado y Verde.
 *  - Los colores que YA existen en la base de datos, para que al editar un
 *    equipo antiguo no se pierda su valor (venían como texto libre).
 *
 * Si un color ya existe pero escrito de otra forma ("negro", "MARRON"), se
 * conserva SU escritura exacta como valor y se muestra con mayúscula
 * correcta. Así guardar sin tocar el campo no altera nada.
 *
 * @param mysqli  $conexion
 * @param string  $valor_actual  Color que tiene el equipo que se está editando
 * @return string  HTML de las opciones
 */
function opciones_color($conexion, $valor_actual = '')
{
    $pedidos = ['Negro', 'Marrón', 'Plateado', 'Verde'];

    // Colores realmente usados en la base
    $existentes = [];
    try {
        $res = $conexion->query(
            "SELECT DISTINCT color FROM equipos
             WHERE color IS NOT NULL AND color <> ''
             ORDER BY color"
        );
        if ($res) {
            while ($fila = $res->fetch_row()) {
                $existentes[] = $fila[0];
            }
            $res->free();
        }
    } catch (Throwable $e) {
        $existentes = [];
    }

    // "MARRON" y "Marrón" deben considerarse el mismo color
    $normalizar = function ($texto) {
        $texto = mb_strtolower(trim((string)$texto), 'UTF-8');
        $texto = strtr($texto, ['á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ñ' => 'n']);
        return $texto;
    };

    $html = '<option value="">Seleccione...</option>';
    $actual = trim((string)$valor_actual);

    // Marca como seleccionado el color que ya tiene el equipo. La comparación
    // ignora mayúsculas y tildes, porque en la base conviven "negro" y
    // "NEGRA", "MARRON" y "Marrón".
    $marcar = function ($valor) use ($actual, $normalizar) {
        return ($actual !== '' && $normalizar($valor) === $normalizar($actual))
            ? ' selected' : '';
    };

    // 1. Colores pedidos, reutilizando la escritura que ya hay en la base
    foreach ($pedidos as $pedido) {
        $clave = $normalizar($pedido);
        $valor = $pedido;
        foreach ($existentes as $existente) {
            if ($normalizar($existente) === $clave) {
                $valor = $existente;
                break;
            }
        }
        $html .= '<option value="' . htmlspecialchars($valor) . '"' . $marcar($valor) . '>'
            . htmlspecialchars($pedido) . '</option>';
    }

    // 2. Los demás colores que ya existen en la base
    $yaCubiertos = [];
    foreach ($pedidos as $pedido) {
        $yaCubiertos[] = $normalizar($pedido);
    }
    foreach ($existentes as $existente) {
        $clave = $normalizar($existente);
        if (in_array($clave, $yaCubiertos, true)) {
            continue;
        }
        $yaCubiertos[] = $clave;
        $html .= '<option value="' . htmlspecialchars($existente) . '"' . $marcar($existente) . '>'
            . htmlspecialchars($existente) . '</option>';
    }

    // 3. Si el equipo editado tiene un color que no está en la lista,
    //    se agrega para no perderlo al guardar.
    if ($actual !== '' && !in_array($normalizar($actual), $yaCubiertos, true)) {
        $html = '<option value="' . htmlspecialchars($actual) . '" selected>'
            . htmlspecialchars($actual) . ' (actual)</option>' . $html;
    }

    return $html;
}