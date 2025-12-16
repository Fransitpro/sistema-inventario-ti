<?php
// =======================================================================
// CORRECCIÓN CRÍTICA: Suprimir advertencias 'Deprecated' para FPDF
// =======================================================================
error_reporting(E_ALL & ~E_DEPRECATED);
// =======================================================================

session_start();
require_once '../config/database.php';
require_once '../fpdf/fpdf.php'; // Asegúrate de que la ruta sea correcta

// Validar ID de equipo
if (!isset($_GET['id_equipo']) || !is_numeric($_GET['id_equipo'])) {
    die("Error: ID de equipo no especificado o inválido.");
}
$id_equipo = (int)$_GET['id_equipo'];

// Consulta para obtener todos los datos del equipo
$sql = "SELECT 
            e.*, 
            t.nombre AS tipo_nombre,
            ma.nombre as marca_nombre, 
            mo.nombre as modelo_nombre,
            s.nombre as sucursal_nombre
        FROM equipos e
        LEFT JOIN tipos_equipo t ON e.id_tipo_equipo = t.id
        LEFT JOIN marcas ma ON e.id_marca = ma.id
        LEFT JOIN modelos mo ON e.id_modelo = mo.id
        LEFT JOIN sucursales s ON e.id_sucursal = s.id
        WHERE e.id = ?";
$stmt = $conexion->prepare($sql);
$stmt->bind_param("i", $id_equipo);
$stmt->execute();
$equipo = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$equipo) {
    die("Error: Equipo no encontrado.");
}

// === LECTURA DE DATOS DE LA URL ===
$fecha_baja = date('d/m/Y'); 
$motivo_baja = $_GET['motivo'] ?? '[Motivo no proporcionado]'; 
$observaciones_baja = $_GET['observaciones'] ?? '[Sin observaciones]';
// ===============================================

$usuario_responsable_ti = $_SESSION['user_email'] ?? 'Usuario TI';

class PDF extends FPDF
{
    function Header()
    {
        $this->SetFont('Arial', 'B', 14);
        $this->Cell(0, 10, utf8_decode('Acta de Baja de Equipo Informático'), 0, 1, 'C');
        $this->Ln(10);
    }
    function Footer()
    {
        $this->SetY(-15);
        $this->SetFont('Arial', 'I', 8);
        $this->Cell(0, 10, utf8_decode('Página ') . $this->PageNo(), 0, 0, 'C');
    }
    function FilaDato($label, $value)
    {
        $this->SetFont('Arial', 'B', 11);
        $this->Cell(50, 8, utf8_decode($label . ':'), 1, 0);
        $this->SetFont('Arial', '', 11);
        $this->Cell(0, 8, utf8_decode($value), 1, 1);
    }
}

$pdf = new PDF();
$pdf->AddPage();
$pdf->SetFont('Arial', '', 11);

$pdf->SetFont('Arial', 'B', 11);
$pdf->Cell(0, 10, 'Fecha de Emision del Acta: ' . date('d/m/Y'), 0, 1, 'R');
$pdf->Ln(5);

// --- Sección Datos del Equipo ---
$pdf->SetFont('Arial', 'B', 12);
$pdf->Cell(0, 10, '1. Datos del Equipo Dado de Baja', 0, 1);
$pdf->SetFont('Arial', '', 11);
$pdf->FilaDato('Código de Inventario', $equipo['codigo_inventario']);
$pdf->FilaDato('Tipo de Equipo', $equipo['tipo_nombre']);
$pdf->FilaDato('Marca', $equipo['marca_nombre']);
$pdf->FilaDato('Modelo', $equipo['modelo_nombre']);
$pdf->FilaDato('Número de Serie', $equipo['numero_serie']);
$pdf->FilaDato('Fecha de Adquisición', date('d/m/Y', strtotime($equipo['fecha_adquisicion'])));
$pdf->FilaDato('Sucursal', $equipo['sucursal_nombre']);
$pdf->Ln(10);

// --- Sección Datos de la Baja ---
$pdf->SetFont('Arial', 'B', 12);
$pdf->Cell(0, 10, utf8_decode('2. Información de la Baja'), 0, 1);
$pdf->SetFont('Arial', '', 11);
$pdf->FilaDato('Fecha de Baja', $fecha_baja);
$pdf->FilaDato('Motivo de la Baja', $motivo_baja); 
$pdf->SetFont('Arial', 'B', 11);
$pdf->Cell(50, 8, utf8_decode('Observaciones:'), 'TLB', 0);
$pdf->SetFont('Arial', '', 11);
$pdf->MultiCell(0, 8, utf8_decode($observaciones_baja), 'TRB'); 
$pdf->Ln(10);

// --- Sección de Declaración y Firmas ---
$pdf->SetFont('Arial', '', 10);
$pdf->MultiCell(0, 5, utf8_decode("Por medio de la presente, se certifica que el equipo detallado anteriormente ha sido retirado del inventario activo de la empresa por el motivo indicado. El equipo será dispuesto según las políticas internas de gestión de activos y residuos electrónicos."), 0, 'J');
$pdf->Ln(20);

$pdf->Cell(95, 10, '_________________________', 0, 0, 'C');
$pdf->Cell(95, 10, '_________________________', 0, 1, 'C');
$pdf->Cell(95, 10, 'Responsable de Inventario (TI)', 0, 0, 'C');
$pdf->Cell(95, 10, 'Visto Bueno (Jefatura/Gerencia)', 0, 1, 'C');
$pdf->Cell(95, 10, utf8_decode($usuario_responsable_ti), 0, 0, 'C');
$pdf->Cell(95, 10, '', 0, 1, 'C');

$pdf->Output('I', 'Acta_Baja_' . $equipo['codigo_inventario'] . '.pdf');
?>