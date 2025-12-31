<?php
/**
 * Genera un Excel SIN columnas Organismo/Area (estas vienen del usuario logueado)
 */

require_once 'vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

// Crear directorio de pruebas
$testDir = __DIR__ . '/uploads/tesoreria1/';
$excelDir = $testDir . 'excel/';
$pdfDir = $testDir . 'pdfs/';

foreach ([$testDir, $excelDir, $pdfDir] as $dir) {
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }
}

$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();
$sheet->setTitle('Sin Organismo Area');

// Headers - SIN Organismo y Area (vienen del usuario)
$headers = [
    'ID_1',                      
    'ID_2',                      
    'Razon_Social',              
    'No_Acto_Administrativo',    
    'Fecha_Acto_Administrativo', 
    'Fecha_Publicacion',         
    'Tipo_Actuacion',            
    // NO incluimos Organismo ni Area          
    'Fecha_Desfijacion',         
    'Nombre_PDF',
    // Columnas extra para probar
    'Direccion',
    'Telefono'
];

foreach ($headers as $col => $header) {
    $colLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($col + 1);
    $sheet->setCellValue($colLetter . '1', $header);
    $sheet->getColumnDimension($colLetter)->setAutoSize(true);
}

$fechaHoy = date('Y-m-d');
$fechaDesfijacion = date('Y-m-d', strtotime('+5 days'));

$datos = [
    [
        'PREDIO-501',
        '55555555',
        'CONTRIBUYENTE DE TESORERÍA UNO',
        'TES-2025-0001',
        $fechaHoy,
        $fechaHoy,
        'COBRO COACTIVO - IMPUESTO PREDIAL',
        $fechaDesfijacion,
        'pdf_tesoreria_001',
        'Calle 100 #50-30',
        '3101112233'
    ],
    [
        'PREDIO-502',
        '66666666',
        'CONTRIBUYENTE DE TESORERÍA DOS',
        'TES-2025-0002',
        $fechaHoy,
        $fechaHoy,
        'NOTIFICACIÓN DE EMBARGO',
        $fechaDesfijacion,
        'pdf_tesoreria_002',
        'Av. Roosevelt #45-12',
        '3204445566'
    ]
];

$row = 2;
foreach ($datos as $registro) {
    foreach ($registro as $col => $valor) {
        $colLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($col + 1);
        $sheet->setCellValue($colLetter . $row, $valor);
    }
    $row++;
}

$timestamp = date('Y-m-d_H-i-s');
$excelFilename = "carga_tesoreria_{$timestamp}.xlsx";
$excelPath = $excelDir . $excelFilename;

$writer = new Xlsx($spreadsheet);
$writer->save($excelPath);

echo "✅ Excel creado: $excelFilename\n\n";

// Crear PDFs
$pdfsCreados = [];
foreach ($datos as $registro) {
    $nombrePdf = $registro[8];
    $pdfPath = $pdfDir . $nombrePdf . '.pdf';
    file_put_contents($pdfPath, "%PDF-1.4\n%%EOF\n");
    $pdfsCreados[] = $nombrePdf . '.pdf';
}

echo "✅ PDFs creados:\n";
foreach ($pdfsCreados as $pdf) {
    echo "   📄 $pdf\n";
}

echo "\n═══════════════════════════════════════════════════════\n";
echo "     PRUEBA: ORGANISMO/ÁREA DESDE USUARIO\n";
echo "═══════════════════════════════════════════════════════\n\n";

echo "📋 Este Excel NO tiene columnas Organismo ni Area.\n";
echo "   Estos valores se tomarán del usuario logueado.\n\n";

echo "👤 USUARIOS DE PRUEBA (contraseña: 123456):\n\n";

echo "   ┌──────────────┬─────────────────────────────────────────────────────┐\n";
echo "   │ Usuario      │ Área asignada                                        │\n";
echo "   ├──────────────┼─────────────────────────────────────────────────────┤\n";
echo "   │ admin        │ SUBDIRECCIÓN DE SISTEMAS                            │\n";
echo "   │ tesoreria1   │ SUBDIRECCIÓN DE TESORERÍA - COBRO COACTIVO          │\n";
echo "   │ catastro1    │ SUBDIRECCIÓN DE CATASTRO                            │\n";
echo "   │ fiscalizacion1│ SUBDIRECCIÓN DE FISCALIZACIÓN                       │\n";
echo "   └──────────────┴─────────────────────────────────────────────────────┘\n\n";

echo "🔍 PARA PROBAR:\n";
echo "   1. Ve a http://localhost:8000/login.html\n";
echo "   2. Inicia sesión como 'tesoreria1' / '123456'\n";
echo "   3. Verás tu Organismo y Área en la página de carga\n";
echo "   4. Procesa el archivo\n";
echo "   5. Los registros tendrán el organismo/área de tesoreria1\n\n";

echo "═══════════════════════════════════════════════════════\n";

