<?php
error_reporting(E_ALL & ~E_DEPRECATED); // <-- ESTA ES LA CORRECCIÓN
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

// Consulta para obtener todos los detalles de la asignación, equipo y empleado
$sql = "SELECT 
            a.fecha_entrega, a.observaciones_entrega,
            emp.nombres AS emp_nombres, emp.apellidos AS emp_apellidos, emp.dni AS emp_dni,
            cargo.nombre AS cargo_nombre,
            area.nombre AS area_nombre,
            eq.codigo_inventario, eq.numero_serie, eq.caracteristicas,
            te.nombre AS tipo_equipo_nombre,
            ma.nombre AS marca_nombre,
            mo.nombre AS modelo_nombre,
            s.nombre AS sucursal_nombre
        FROM asignaciones a
        JOIN empleados emp ON a.id_empleado = emp.id
        JOIN equipos eq ON a.id_equipo = eq.id
        LEFT JOIN cargos cargo ON emp.id_cargo = cargo.id
        LEFT JOIN areas area ON emp.id_area = area.id
        LEFT JOIN tipos_equipo te ON eq.id_tipo_equipo = te.id
        LEFT JOIN modelos mo ON eq.id_modelo = mo.id
        LEFT JOIN marcas ma ON mo.id_marca = ma.id
        LEFT JOIN sucursales s ON emp.id_sucursal = s.id
        WHERE a.id = ?";

$stmt = $conexion->prepare($sql);
$stmt->bind_param("i", $id_asignacion);
$stmt->execute();
$data = $stmt->get_result()->fetch_assoc();

if (!$data) {
    die("Error: Asignación no encontrada.");
}

// Obtener el nombre del usuario de TI que entrega (desde la sesión)
$usuario_ti_entrega = $_SESSION['user_nombre'] ?? $_SESSION['user_email'];

class PDF extends FPDF
{
    // Cabecera de página
    function Header()
    {
        // Título
        $this->SetFont('Arial', 'B', 14);
        $this->Cell(0, 10, utf8_decode('Acta de Entrega de Equipo Informático'), 0, 1, 'C');
        $this->Ln(10);
    }

    // Pie de página
    function Footer()
    {
        $this->SetY(-15);
        $this->SetFont('Arial', 'I', 8);
        $this->Cell(0, 10, utf8_decode('Página ') . $this->PageNo(), 0, 0, 'C');
    }

    // Función para crear una fila de datos con bordes
    function FilaDato($label, $value)
    {
        $this->SetFont('Arial', 'B', 11);
        $this->Cell(50, 8, utf8_decode($label . ':'), 1, 0, 'L');
        $this->SetFont('Arial', '', 11);
        $this->Cell(0, 8, utf8_decode($value), 1, 1, 'L');
    }
}

// --- Creación del PDF ---
$pdf = new PDF();
$pdf->AddPage();
$pdf->SetFont('Arial', '', 11);
$pdf->SetMargins(15, 15, 15);

$pdf->SetFont('Arial', 'B', 11);
$pdf->Cell(0, 10, 'Fecha de Entrega: ' . date('d/m/Y H:i', strtotime($data['fecha_entrega'])), 0, 1, 'R');
$pdf->Ln(5);

// --- 1. Datos del Colaborador ---
$pdf->SetFont('Arial', 'B', 12);
$pdf->Cell(0, 10, utf8_decode('1. Datos del Colaborador que Recibe'), 0, 1, 'L');
$pdf->SetFont('Arial', '', 11);
$pdf->FilaDato('Nombres y Apellidos', $data['emp_apellidos'] . ', ' . $data['emp_nombres']);
$pdf->FilaDato('DNI', $data['emp_dni']);
$pdf->FilaDato('Sucursal', $data['sucursal_nombre']);
$pdf->FilaDato(utf8_decode('Área'), $data['area_nombre']);
$pdf->FilaDato('Cargo', $data['cargo_nombre']);
$pdf->Ln(10);

// --- 2. Datos del Equipo Entregado ---
$pdf->SetFont('Arial', 'B', 12);
$pdf->Cell(0, 10, utf8_decode('2. Datos del Equipo Entregado'), 0, 1, 'L');
$pdf->SetFont('Arial', '', 11);
$pdf->FilaDato('Tipo de Equipo', $data['tipo_equipo_nombre']);
$pdf->FilaDato('Marca', $data['marca_nombre']);
$pdf->FilaDato('Modelo', $data['modelo_nombre']);
$pdf->FilaDato(utf8_decode('Código de Inventario'), $data['codigo_inventario']);
$pdf->FilaDato(utf8_decode('Número de Serie'), $data['numero_serie']);
$pdf->Ln(5);
// Características
$pdf->SetFont('Arial', 'B', 11);
$pdf->Cell(50, 8, utf8_decode('Características:'), 'TLB');
$pdf->SetFont('Arial', '', 11);
$pdf->MultiCell(0, 8, utf8_decode($data['caracteristicas'] ?: 'N/A'), 'TRB');
// Observaciones
$pdf->SetFont('Arial', 'B', 11);
$pdf->Cell(50, 8, utf8_decode('Obs. de Entrega:'), 'TLB');
$pdf->SetFont('Arial', '', 11);
$pdf->MultiCell(0, 8, utf8_decode($data['observaciones_entrega'] ?: 'Ninguna.'), 'TRB');
$pdf->Ln(10);

// --- 3. Declaración y Firmas ---
$pdf->SetFont('Arial', '', 10);
$pdf->MultiCell(0, 5, utf8_decode("Declaro haber recibido el equipo detallado en el presente documento, el cual se encuentra en óptimas condiciones operativas. Me comprometo a utilizarlo exclusivamente para fines laborales, cuidarlo y reportar cualquier incidencia al área de Soporte TI."), 0, 'J');
$pdf->Ln(20);

// Firmas
$pdf->SetFont('Arial', '', 11);
$pdf->Cell(95, 10, '_________________________', 0, 0, 'C');
$pdf->Cell(95, 10, '_________________________', 0, 1, 'C');
$pdf->Cell(95, 10, 'Firma del Colaborador', 0, 0, 'C');
$pdf->Cell(95, 10, 'Entregado por (TI)', 0, 1, 'C');
$pdf->Cell(95, 10, utf8_decode($data['emp_apellidos'] . ', ' . $data['emp_nombres']), 0, 0, 'C');
$pdf->Cell(95, 10, utf8_decode($usuario_ti_entrega), 0, 1, 'C');
$pdf->Cell(95, 10, 'DNI: ' . $data['emp_dni'], 0, 0, 'C');
$pdf->Cell(95, 10, utf8_decode('Área de TI'), 0, 1, 'C');


// Salida del PDF
$pdf->Output('I', 'Acta_Entrega_' . $data['codigo_inventario'] . '.pdf');
?>