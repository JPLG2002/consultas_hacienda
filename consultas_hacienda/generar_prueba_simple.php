<?php
/**
 * Genera un Excel simple con 2 registros y sus PDFs correspondientes para pruebas
 */

require_once 'vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

// Crear directorio de pruebas si no existe
$testDir = __DIR__ . '/uploads/admin/';
$excelDir = $testDir . 'excel/';
$pdfDir = $testDir . 'pdfs/';

foreach ([$testDir, $excelDir, $pdfDir] as $dir) {
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }
}

// ============================================================================
// CREAR EXCEL CON 2 REGISTROS
// ============================================================================
$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();
$sheet->setTitle('Actos Administrativos');

// Headers (incluye la nueva columna Nombre_PDF)
$headers = [
    'ID_1',                      // id_predio
    'ID_2',                      // id_contribuyente
    'Razon_Social',              // razon_social
    'No_Acto_Administrativo',    // no_acto_administrativo
    'Fecha_Acto_Administrativo', // fecha_acto
    'Fecha_Publicacion',         // fecha_publicacion
    'Tipo_Actuacion',            // tipo_actuacion
    'Organismo',                 // organismo
    'Area',                      // area
    'Fecha_Desfijacion',         // fecha_desfijacion
    'Nombre_PDF'                 // ⭐ NUEVA COLUMNA: nombre del PDF asociado
];

// Escribir headers
foreach ($headers as $col => $header) {
    $colLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($col + 1);
    $sheet->setCellValue($colLetter . '1', $header);
    $sheet->getColumnDimension($colLetter)->setAutoSize(true);
}

// Datos de prueba (2 registros)
$fechaHoy = date('Y-m-d');
$fechaDesfijacion = date('Y-m-d', strtotime('+5 days'));

$datos = [
    [
        'PREDIO-001',
        '12345678',
        'JUAN CARLOS PÉREZ GÓMEZ',
        'RES-2025-0001',
        $fechaHoy,
        $fechaHoy,
        'MANDAMIENTO DE PAGO - IMPUESTO PREDIAL',
        'DEPARTAMENTO ADMINISTRATIVO DE HACIENDA',
        'SUBDIRECCIÓN DE TESORERÍA',
        $fechaDesfijacion,
        'documento_juan_perez'  // ⭐ Nombre del PDF (sin extensión)
    ],
    [
        'PREDIO-002',
        '87654321',
        'EMPRESA COMERCIAL ABC S.A.S.',
        'RES-2025-0002',
        $fechaHoy,
        $fechaHoy,
        'NOTIFICACIÓN DE AVALÚO CATASTRAL',
        'DEPARTAMENTO ADMINISTRATIVO DE HACIENDA',
        'SUBDIRECCIÓN DE CATASTRO',
        $fechaDesfijacion,
        'documento_empresa_abc'  // ⭐ Nombre del PDF (sin extensión)
    ]
];

// Escribir datos
$row = 2;
foreach ($datos as $registro) {
    foreach ($registro as $col => $valor) {
        $colLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($col + 1);
        $sheet->setCellValue($colLetter . $row, $valor);
    }
    $row++;
}

// Guardar Excel
$timestamp = date('Y-m-d_H-i-s');
$excelFilename = "prueba_simple_{$timestamp}.xlsx";
$excelPath = $excelDir . $excelFilename;

$writer = new Xlsx($spreadsheet);
$writer->save($excelPath);

echo "✅ Excel creado: $excelFilename\n";
echo "   📁 Ubicación: $excelPath\n\n";

// ============================================================================
// CREAR PDFs DE PRUEBA
// ============================================================================
$pdfsCreados = [];

foreach ($datos as $registro) {
    $nombrePdf = $registro[10]; // Columna Nombre_PDF
    $pdfPath = $pdfDir . $nombrePdf . '.pdf';
    
    // Crear un PDF simple (archivo de texto que simula PDF)
    $contenido = "%PDF-1.4\n";
    $contenido .= "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n";
    $contenido .= "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n";
    $contenido .= "3 0 obj\n<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] >>\nendobj\n";
    $contenido .= "xref\n0 4\ntrailer\n<< /Root 1 0 R >>\n%%EOF\n";
    
    file_put_contents($pdfPath, $contenido);
    $pdfsCreados[] = $nombrePdf . '.pdf';
}

echo "✅ PDFs creados:\n";
foreach ($pdfsCreados as $pdf) {
    echo "   📄 $pdf\n";
}
echo "   📁 Ubicación: $pdfDir\n\n";

// ============================================================================
// RESUMEN
// ============================================================================
echo "═══════════════════════════════════════════════════════\n";
echo "                    RESUMEN DE PRUEBA\n";
echo "═══════════════════════════════════════════════════════\n\n";

echo "📊 EXCEL:\n";
echo "   Archivo: $excelFilename\n";
echo "   Registros: 2\n\n";

echo "📄 PDFs CREADOS:\n";
foreach ($pdfsCreados as $pdf) {
    echo "   - $pdf\n";
}

echo "\n📋 DATOS DE PRUEBA:\n";
echo "   ┌─────────────────────────────────────────────────────┐\n";
echo "   │ Registro 1:                                          │\n";
echo "   │   ID: 12345678 (Juan Carlos Pérez)                  │\n";
echo "   │   PDF: documento_juan_perez.pdf                     │\n";
echo "   ├─────────────────────────────────────────────────────┤\n";
echo "   │ Registro 2:                                          │\n";
echo "   │   ID: 87654321 (Empresa ABC S.A.S.)                 │\n";
echo "   │   PDF: documento_empresa_abc.pdf                    │\n";
echo "   └─────────────────────────────────────────────────────┘\n\n";

echo "🔍 PARA PROBAR:\n";
echo "   1. Inicia sesión en http://localhost:8000/login.html\n";
echo "   2. Ve a 'Carga Masiva'\n";
echo "   3. El Excel ya está en tu carpeta de pendientes\n";
echo "   4. Los PDFs ya están subidos\n";
echo "   5. ¡Procesa el archivo!\n\n";

echo "═══════════════════════════════════════════════════════\n";

