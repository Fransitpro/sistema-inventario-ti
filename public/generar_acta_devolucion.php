<?php
// CORRECCIÓN: Suprimir advertencias de "Deprecated" que rompen la salida del PDF
error_reporting(E_ALL & ~E_DEPRECATED);

session_start();
require_once '../config/database.php';
require_once '../fpdf/fpdf.php'; // Asegúrate de que esta ruta a FPDF sea correcta

// Validar que el usuario esté logueado
if (!isset($_SESSION['user_id'])) {
    die("Error: Acceso no autorizado.");
}

// Validar que el ID de la asignación exista y sea un número
if (!isset($_GET['id_asignacion']) || !is_numeric($_GET['id_asignacion'])) {
    die("Error: ID de asignación no especificado o no válido.");
}
$id_asignacion = (int)$_GET['id_asignacion'];

// Consulta para obtener todos los datos necesarios para el acta
$sql = "SELECT 
            a.fecha_devolucion, a.observaciones_devolucion,
            e.codigo_inventario, e.numero_serie,
            t.nombre AS tipo_nombre,
            ma.nombre as marca_nombre, mo.nombre as modelo_nombre,
            emp.dni, emp.nombres, emp.apellidos,
            c.nombre AS cargo_nombre,
            s.nombre AS sucursal_nombre -- Añadido para el header del acta
        FROM asignaciones a
        JOIN equipos e ON a.id_equipo = e.id
        JOIN empleados emp ON a.id_empleado = emp.id
        JOIN tipos_equipo t ON e.id_tipo_equipo = t.id
        JOIN marcas ma ON e.id_marca = ma.id
        JOIN modelos mo ON e.id_modelo = mo.id
        LEFT JOIN cargos c ON emp.id_cargo = c.id
        LEFT JOIN sucursales s ON emp.id_sucursal = s.id -- Asumiendo que el empleado tiene la sucursal
        WHERE a.id = ?";
$stmt = $conexion->prepare($sql);
$stmt->bind_param("i", $id_asignacion);
$stmt->execute();
$datos = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$datos) {
    die("Error: Asignación no encontrada.");
}

// Usamos el email del usuario de TI que recibe, ya que 'user_nombre' no existe
$usuario_ti_recibe = $_SESSION['user_email'] ?? 'Usuario TI'; 

class PDF extends FPDF
{
    var $sucursal_nombre = ''; // Variable para almacenar el nombre de la sucursal

    function Header()
    {
        $this->SetFont('Arial', 'B', 14);
        $this->Cell(0, 10, utf8_decode('Acta de Devolución de Equipo Informático'), 0, 1, 'C');
        $this->SetFont('Arial', 'I', 12);
        $this->Cell(0, 7, utf8_decode('Sucursal: ' . $this->sucursal_nombre), 0, 1, 'C');
        $this->Ln(10);
    }
    function Footer()
    {
        $this->SetY(-15);
        $this->SetFont('Arial', 'I', 8);
        $this->Cell(0, 10, utf8_decode('Página ') . $this->PageNo(), 0, 0, 'C');
    }
    // Función para crear celdas con bordes y texto
    function FilaDato($label, $value)
    {
        $this->SetFont('Arial', 'B', 11);
        $this->Cell(50, 8, utf8_decode($label . ':'), 1, 0, 'L');
        $this->SetFont('Arial', '', 11);
        $this->Cell(0, 8, utf8_decode($value), 1, 1, 'L');
    }
}

$pdf = new PDF();
$pdf->sucursal_nombre = $datos['sucursal_nombre']; // Pasamos la sucursal al header del PDF
$pdf->AddPage();
$pdf->SetMargins(15, 15, 15);
$pdf->SetFont('Arial', '', 11);

$pdf->SetFont('Arial', 'B', 11);
$pdf->Cell(0, 10, 'Fecha de Devolucion: ' . date('d/m/Y H:i', strtotime($datos['fecha_devolucion'])), 0, 1, 'R');
$pdf->Ln(5);

// --- 1. Datos del Colaborador ---
$pdf->SetFont('Arial', 'B', 12);
$pdf->Cell(0, 10, '1. Datos del Colaborador que Devuelve', 0, 1, 'L');
$pdf->SetFont('Arial', '', 11);
$pdf->FilaDato('Nombres y Apellidos', $datos['apellidos'] . ', ' . $datos['nombres']);
$pdf->FilaDato('DNI', $datos['dni']);
$pdf->FilaDato('Cargo', $datos['cargo_nombre']);
$pdf->Ln(10);

// --- 2. Datos del Equipo Devuelto ---
$pdf->SetFont('Arial', 'B', 12);
$pdf->Cell(0, 10, '2. Datos del Equipo Devuelto', 0, 1, 'L');
$pdf->SetFont('Arial', '', 11);
$pdf->FilaDato('Código de Inventario', $datos['codigo_inventario']);
$pdf->FilaDato('Tipo de Equipo', $datos['tipo_nombre']);
$pdf->FilaDato('Marca y Modelo', $datos['marca_nombre'] . ' ' . $datos['modelo_nombre']);
$pdf->FilaDato('Número de Serie', $datos['numero_serie']);
$pdf->Ln(10);

// --- 3. Detalles de la Devolución ---
$pdf->SetFont('Arial', 'B', 12);
$pdf->Cell(0, 10, utf8_decode('3. Detalles de la Devolución'), 0, 1, 'L');
$pdf->SetFont('Arial', 'B', 11);
$pdf->Cell(50, 8, 'Equipo Recibido por:', 1, 0);
$pdf->SetFont('Arial', '', 11);
$pdf->Cell(0, 8, utf8_decode($usuario_ti_recibe), 1, 1);
$pdf->SetFont('Arial', 'B', 11);
$pdf->Cell(50, 8, 'Observaciones:', 'TLB');
$pdf->SetFont('Arial', '', 11);
$pdf->MultiCell(0, 8, utf8_decode($datos['observaciones_devolucion'] ?: 'Sin observaciones.'), 'TRB');
$pdf->Ln(10);

// --- 4. Evidencia Fotográfica ---
$stmt_img = $conexion->prepare("SELECT imagen_devolucion_1, imagen_devolucion_2, imagen_devolucion_3 FROM asignaciones WHERE id = ?");
$stmt_img->bind_param("i", $id_asignacion);
$stmt_img->execute();
$imagenes = $stmt_img->get_result()->fetch_assoc();
$stmt_img->close();
$imagenes_adjuntas = array_filter([$imagenes['imagen_devolucion_1'] ?? null, $imagenes['imagen_devolucion_2'] ?? null, $imagenes['imagen_devolucion_3'] ?? null]);
if (!empty($imagenes_adjuntas)) {
    $pdf->SetFont('Arial', 'B', 11);
    $pdf->Cell(0, 8, utf8_decode('Evidencia Fotográfica'), 0, 1);
    $pdf->SetFont('Arial', '', 9);
    $pdf->MultiCell(0, 5, utf8_decode(count($imagenes_adjuntas) . " imagen(es) adjunta(s) en el registro digital de esta devolución."), 0, 'J');
}

// --- 5. Firmas ---
$pdf->Ln(20);
$pdf->SetFont('Arial', '', 11);
$pdf->Cell(95, 10, '_________________________', 0, 0, 'C');
$pdf->Cell(95, 10, '_________________________', 0, 1, 'C');
$pdf->Cell(95, 10, 'Firma del Empleado (Devuelve)', 0, 0, 'C');
$pdf->Cell(95, 10, 'Recibido por (TI)', 0, 1, 'C');
$pdf->Cell(95, 10, utf8_decode($datos['apellidos'] . ', ' . $datos['nombres']), 0, 0, 'C');
$pdf->Cell(95, 10, utf8_decode($usuario_ti_recibe), 0, 1, 'C');
$pdf->Cell(95, 10, 'DNI: ' . $datos['dni'], 0, 0, 'C');
$pdf->Cell(95, 10, utf8_decode('Área de TI'), 0, 1, 'C');

$pdf->Output('I', 'Acta_Devolucion_' . $datos['codigo_inventario'] . '.pdf');
?>