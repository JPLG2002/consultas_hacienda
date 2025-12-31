<?php
/**
 * Genera un Excel con columnas EXTRA (no mapeadas) para probar la captura de info adicional
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
// CREAR EXCEL CON COLUMNAS EXTRA
// ============================================================================
$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();
$sheet->setTitle('Actos con Info Extra');

// Headers - incluye columnas CONOCIDAS + EXTRA
$headers = [
    // Columnas conocidas (mapeadas)
    'ID_1',                      
    'ID_2',                      
    'Razon_Social',              
    'No_Acto_Administrativo',    
    'Fecha_Acto_Administrativo', 
    'Fecha_Publicacion',         
    'Tipo_Actuacion',            
    'Organismo',                 
    'Area',                      
    'Fecha_Desfijacion',         
    'Nombre_PDF',                
    // ⭐ COLUMNAS EXTRA (no mapeadas - irán a mas_datos)
    'Direccion_Predio',
    'Telefono_Contacto',
    'Email',
    'Barrio',
    'Estrato',
    'Metros_Cuadrados',
    'Valor_Avaluo',
    'Observaciones'
];

// Escribir headers
foreach ($headers as $col => $header) {
    $colLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($col + 1);
    $sheet->setCellValue($colLetter . '1', $header);
    $sheet->getColumnDimension($colLetter)->setAutoSize(true);
}

// Datos de prueba (2 registros con info extra)
$fechaHoy = date('Y-m-d');
$fechaDesfijacion = date('Y-m-d', strtotime('+5 days'));

$datos = [
    [
        // Columnas conocidas
        'PREDIO-100',
        '99999999',
        'PEDRO MARTÍNEZ LÓPEZ',
        'RES-2025-EXTRA-001',
        $fechaHoy,
        $fechaHoy,
        'MANDAMIENTO DE PAGO - IMPUESTO PREDIAL',
        'DEPARTAMENTO ADMINISTRATIVO DE HACIENDA',
        'SUBDIRECCIÓN DE TESORERÍA',
        $fechaDesfijacion,
        'pdf_pedro_martinez',
        // ⭐ Columnas EXTRA
        'Calle 45 #23-67 Apto 501',
        '3001234567',
        'pedro.martinez@email.com',
        'San Fernando',
        '4',
        '120',
        '$250.000.000',
        'Contribuyente con mora de 3 años. Requiere seguimiento.'
    ],
    [
        // Columnas conocidas
        'PREDIO-200',
        '88888888',
        'INVERSIONES DEL VALLE S.A.',
        'RES-2025-EXTRA-002',
        $fechaHoy,
        $fechaHoy,
        'NOTIFICACIÓN DE AVALÚO CATASTRAL',
        'DEPARTAMENTO ADMINISTRATIVO DE HACIENDA',
        'SUBDIRECCIÓN DE CATASTRO',
        $fechaDesfijacion,
        'pdf_inversiones_valle',
        // ⭐ Columnas EXTRA
        'Av. 6 Norte #15-30 Local 12',
        '3109876543',
        'contabilidad@inversionesvalle.com',
        'Granada',
        '6',
        '350',
        '$850.000.000',
        'Predio comercial. Representante legal: María García.'
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
$excelFilename = "prueba_columnas_extra_{$timestamp}.xlsx";
$excelPath = $excelDir . $excelFilename;

$writer = new Xlsx($spreadsheet);
$writer->save($excelPath);

echo "✅ Excel creado: $excelFilename\n\n";

// ============================================================================
// CREAR PDFs DE PRUEBA
// ============================================================================
$pdfsCreados = [];

foreach ($datos as $registro) {
    $nombrePdf = $registro[10]; // Columna Nombre_PDF
    $pdfPath = $pdfDir . $nombrePdf . '.pdf';
    
    $contenido = "%PDF-1.4\n1 0 obj\n<< /Type /Catalog >>\nendobj\n%%EOF\n";
    file_put_contents($pdfPath, $contenido);
    $pdfsCreados[] = $nombrePdf . '.pdf';
}

echo "✅ PDFs creados:\n";
foreach ($pdfsCreados as $pdf) {
    echo "   📄 $pdf\n";
}

// ============================================================================
// RESUMEN
// ============================================================================
echo "\n═══════════════════════════════════════════════════════\n";
echo "              PRUEBA DE COLUMNAS EXTRA\n";
echo "═══════════════════════════════════════════════════════\n\n";

echo "📊 Este Excel tiene las siguientes COLUMNAS EXTRA:\n";
echo "   • Direccion_Predio\n";
echo "   • Telefono_Contacto\n";
echo "   • Email\n";
echo "   • Barrio\n";
echo "   • Estrato\n";
echo "   • Metros_Cuadrados\n";
echo "   • Valor_Avaluo\n";
echo "   • Observaciones\n\n";

echo "📋 Estas columnas se guardarán en 'mas_datos' (JSON)\n";
echo "   y aparecerán en el botón '📋 Info Extra' al consultar.\n\n";

echo "🔍 PARA PROBAR:\n";
echo "   1. Ve a http://localhost:8000/carga.html\n";
echo "   2. Procesa el archivo\n";
echo "   3. Busca con ID: 99999999 o 88888888\n";
echo "   4. Verás el botón '📋 Info Extra' en las tarjetas\n\n";

echo "═══════════════════════════════════════════════════════\n";

