<?php
/**
 * EXCEL COMPLETO CON TODOS LOS CAMBIOS:
 * - SIN Organismo/Area (vienen del usuario)
 * - CON columna Nombre_PDF
 * - CON columnas extra (irán a mas_datos)
 * - Solo 2 registros
 */

require_once 'vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

// Usuario admin para la prueba
$testDir = __DIR__ . '/uploads/admin/';
$excelDir = $testDir . 'excel/';
$pdfDir = $testDir . 'pdfs/';

foreach ([$testDir, $excelDir, $pdfDir] as $dir) {
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }
}

$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();
$sheet->setTitle('Carga Completa');

// ============================================================================
// HEADERS - FORMATO FINAL
// ============================================================================
$headers = [
    // === COLUMNAS OBLIGATORIAS (mapeadas a campos de la BD) ===
    'ID_1',                      // id_predio
    'ID_2',                      // id_contribuyente  
    'Razon_Social',              // razon_social
    'No_Acto_Administrativo',    // no_acto_administrativo
    'Fecha_Acto_Administrativo', // fecha_acto
    'Fecha_Publicacion',         // fecha_publicacion
    'Tipo_Actuacion',            // tipo_actuacion
    'Fecha_Desfijacion',         // fecha_desfijacion
    
    // === COLUMNA DEL PDF ASOCIADO ===
    'Nombre_PDF',                // ⭐ Nombre del PDF (sin extensión)
    
    // === COLUMNAS EXTRA (irán a mas_datos como JSON) ===
    'Direccion_Predio',
    'Telefono_Contacto',
    'Email',
    'Barrio',
    'Estrato',
    'Valor_Deuda',
    'Observaciones'
];

// Escribir headers con formato
foreach ($headers as $col => $header) {
    $colLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($col + 1);
    $sheet->setCellValue($colLetter . '1', $header);
    $sheet->getColumnDimension($colLetter)->setAutoSize(true);
    
    // Estilo del header
    $sheet->getStyle($colLetter . '1')->getFont()->setBold(true);
    $sheet->getStyle($colLetter . '1')->getFill()
        ->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
        ->getStartColor()->setRGB('E2E8F0');
}

// ============================================================================
// DATOS DE PRUEBA - 2 REGISTROS
// ============================================================================
$fechaHoy = date('Y-m-d');
$fechaDesfijacion = date('Y-m-d', strtotime('+5 days'));

$datos = [
    // REGISTRO 1
    [
        'PREDIO-001',                           // ID_1
        '1234567890',                           // ID_2 (cédula)
        'JUAN CARLOS GARCÍA LÓPEZ',             // Razon_Social
        'RES-HAC-2025-001',                     // No_Acto_Administrativo
        $fechaHoy,                              // Fecha_Acto_Administrativo
        $fechaHoy,                              // Fecha_Publicacion
        'MANDAMIENTO DE PAGO - IMPUESTO PREDIAL VIGENCIAS 2022-2024',  // Tipo_Actuacion
        $fechaDesfijacion,                      // Fecha_Desfijacion
        'acto_juan_garcia',                     // ⭐ Nombre_PDF (sin .pdf)
        'Calle 45 #23-67 Apto 501',             // Direccion_Predio
        '3001234567',                           // Telefono_Contacto
        'juan.garcia@email.com',                // Email
        'San Fernando',                         // Barrio
        '4',                                    // Estrato
        '$3.500.000',                           // Valor_Deuda
        'Deuda acumulada por 3 vigencias. Notificación inicial.'  // Observaciones
    ],
    // REGISTRO 2
    [
        'PREDIO-002',                           // ID_1
        '900123456-1',                          // ID_2 (NIT empresa)
        'INVERSIONES COMERCIALES ABC S.A.S.',   // Razon_Social
        'RES-HAC-2025-002',                     // No_Acto_Administrativo
        $fechaHoy,                              // Fecha_Acto_Administrativo
        $fechaHoy,                              // Fecha_Publicacion
        'NOTIFICACIÓN DE EMBARGO - CUENTA BANCARIA',  // Tipo_Actuacion
        $fechaDesfijacion,                      // Fecha_Desfijacion
        'acto_inversiones_abc',                 // ⭐ Nombre_PDF (sin .pdf)
        'Av. 6 Norte #15-30 Local 12-15',       // Direccion_Predio
        '3109876543',                           // Telefono_Contacto
        'contabilidad@inversionesabc.com',      // Email
        'Granada',                              // Barrio
        '6',                                    // Estrato
        '$12.800.000',                          // Valor_Deuda
        'Empresa con proceso de embargo activo. Rep. Legal: María Rodríguez.'  // Observaciones
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
$excelFilename = "excel_completo_{$timestamp}.xlsx";
$excelPath = $excelDir . $excelFilename;

$writer = new Xlsx($spreadsheet);
$writer->save($excelPath);

// ============================================================================
// CREAR LOS 2 PDFs CORRESPONDIENTES
// ============================================================================
$pdfsCreados = [];
foreach ($datos as $registro) {
    $nombrePdf = $registro[8]; // Columna Nombre_PDF
    $pdfPath = $pdfDir . $nombrePdf . '.pdf';
    
    // Crear PDF simple
    $contenido = "%PDF-1.4\n";
    $contenido .= "% Documento de prueba\n";
    $contenido .= "1 0 obj << /Type /Catalog >> endobj\n";
    $contenido .= "%%EOF\n";
    
    file_put_contents($pdfPath, $contenido);
    $pdfsCreados[] = $nombrePdf . '.pdf';
}

// ============================================================================
// RESUMEN
// ============================================================================
echo "\n";
echo "╔═══════════════════════════════════════════════════════════════════════╗\n";
echo "║              EXCEL COMPLETO CON TODOS LOS CAMBIOS                     ║\n";
echo "╚═══════════════════════════════════════════════════════════════════════╝\n\n";

echo "📊 EXCEL CREADO:\n";
echo "   Archivo: $excelFilename\n";
echo "   Ubicación: uploads/admin/excel/\n\n";

echo "📄 PDFs CREADOS:\n";
foreach ($pdfsCreados as $pdf) {
    echo "   ✅ $pdf\n";
}
echo "   Ubicación: uploads/admin/pdfs/\n\n";

echo "═══════════════════════════════════════════════════════════════════════\n";
echo "                         ESTRUCTURA DEL EXCEL\n";
echo "═══════════════════════════════════════════════════════════════════════\n\n";

echo "📋 COLUMNAS OBLIGATORIAS (van a campos de la BD):\n";
echo "   • ID_1                      → id_predio\n";
echo "   • ID_2                      → id_contribuyente\n";
echo "   • Razon_Social              → razon_social\n";
echo "   • No_Acto_Administrativo    → no_acto_administrativo\n";
echo "   • Fecha_Acto_Administrativo → fecha_acto\n";
echo "   • Fecha_Publicacion         → fecha_publicacion\n";
echo "   • Tipo_Actuacion            → tipo_actuacion\n";
echo "   • Fecha_Desfijacion         → fecha_desfijacion\n\n";

echo "📎 COLUMNA DEL PDF:\n";
echo "   • Nombre_PDF                → Nombre del archivo PDF (sin .pdf)\n\n";

echo "📦 COLUMNAS EXTRA (van a 'mas_datos' como JSON):\n";
echo "   • Direccion_Predio\n";
echo "   • Telefono_Contacto\n";
echo "   • Email\n";
echo "   • Barrio\n";
echo "   • Estrato\n";
echo "   • Valor_Deuda\n";
echo "   • Observaciones\n\n";

echo "⚠️  COLUMNAS QUE NO VAN EN EL EXCEL:\n";
echo "   • Organismo  → Se toma del usuario logueado\n";
echo "   • Area       → Se toma del usuario logueado\n\n";

echo "═══════════════════════════════════════════════════════════════════════\n";
echo "                           DATOS DE PRUEBA\n";
echo "═══════════════════════════════════════════════════════════════════════\n\n";

echo "┌─────────────────────────────────────────────────────────────────────┐\n";
echo "│ REGISTRO 1                                                          │\n";
echo "├─────────────────────────────────────────────────────────────────────┤\n";
echo "│ ID: 1234567890                                                      │\n";
echo "│ Nombre: JUAN CARLOS GARCÍA LÓPEZ                                    │\n";
echo "│ Acto: RES-HAC-2025-001                                              │\n";
echo "│ PDF: acto_juan_garcia.pdf                                           │\n";
echo "├─────────────────────────────────────────────────────────────────────┤\n";
echo "│ REGISTRO 2                                                          │\n";
echo "├─────────────────────────────────────────────────────────────────────┤\n";
echo "│ ID: 900123456-1                                                     │\n";
echo "│ Nombre: INVERSIONES COMERCIALES ABC S.A.S.                          │\n";
echo "│ Acto: RES-HAC-2025-002                                              │\n";
echo "│ PDF: acto_inversiones_abc.pdf                                       │\n";
echo "└─────────────────────────────────────────────────────────────────────┘\n\n";

echo "═══════════════════════════════════════════════════════════════════════\n";
echo "                         🚀 PARA PROBAR\n";
echo "═══════════════════════════════════════════════════════════════════════\n\n";

echo "1. Ve a http://localhost:8000/login.html\n";
echo "2. Inicia sesión como 'admin' / '123456'\n";
echo "3. El Excel aparecerá en 'Archivos Pendientes' con ✅ Listo\n";
echo "4. Procesa el archivo\n";
echo "5. Busca con ID: 1234567890 o 900123456-1\n";
echo "6. Verás el botón '📋 Info Extra' con los datos adicionales\n\n";

echo "═══════════════════════════════════════════════════════════════════════\n";

