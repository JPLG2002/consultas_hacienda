<?php
/**
 * GENERADOR DE PRUEBA CON PDFs POR REGISTRO
 * Genera un Excel y algunos PDFs (no todos) para probar la validación
 */

require_once 'vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

echo "🚀 Generando datos de prueba con PDFs por registro...\n\n";

$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();

// Headers
$headers = [
    'ID_1', 'ID_2', 'Razon_Social', 'No_Acto_Administrativo',
    'Fecha_Acto_Administrativo', 'Fecha_Publicacion', 'Tipo_Actuacion',
    'Organismo', 'Area', 'Fecha_Desfijacion'
];

foreach ($headers as $col => $header) {
    $sheet->setCellValue(chr(65 + $col) . '1', $header);
}

// Datos de prueba con números de acto específicos
$registros = [
    ['PRD-001', '12345678-9', 'JUAN GARCIA', 'RES-2025-0001', '2025-01-15', '2025-01-20', 'Resolución de Cobro', 'DPTO HACIENDA', 'Cobro Coactivo', '2025-01-25'],
    ['PRD-002', '12345678-9', 'JUAN GARCIA', 'RES-2025-0002', '2025-02-10', '2025-02-15', 'Mandamiento de Pago', 'DPTO HACIENDA', 'Tesorería', '2025-02-20'],
    ['PRD-003', '98765432-1', 'MARIA LOPEZ', 'RES-2025-0003', '2025-03-05', '2025-03-10', 'Liquidación de Aforo', 'DPTO HACIENDA', 'Impuestos', '2025-03-15'],
    ['PRD-004', '98765432-1', 'MARIA LOPEZ', 'RES-2025-0004', '2025-04-01', '2025-04-05', 'Requerimiento Especial', 'DPTO HACIENDA', 'Fiscalización', '2025-04-10'],
    ['PRD-005', '55555555-5', 'EMPRESA ABC', 'RES-2025-0005', '2025-05-20', '2025-05-25', 'Citación', 'DPTO HACIENDA', 'Catastro', '2025-05-30'],
];

$row = 2;
foreach ($registros as $reg) {
    foreach ($reg as $col => $value) {
        $sheet->setCellValue(chr(65 + $col) . $row, $value);
    }
    $row++;
}

// Guardar Excel
$filename = "prueba_pdfs_" . date('Y-m-d_H-i-s') . ".xlsx";
$filepath = __DIR__ . '/uploads/excel/' . $filename;
$writer = new Xlsx($spreadsheet);
$writer->save($filepath);

echo "📊 Excel generado: $filename\n";
echo "   Registros: " . count($registros) . "\n\n";

// Crear solo ALGUNOS PDFs (para probar validación)
$pdfsACrear = ['RES-2025-0001', 'RES-2025-0003', 'RES-2025-0005']; // Solo 3 de 5
$pdfDir = __DIR__ . '/uploads/pdfs/';

foreach ($pdfsACrear as $noActo) {
    $pdfPath = $pdfDir . $noActo . '.pdf';
    file_put_contents($pdfPath, "PDF del acto $noActo\nGenerado: " . date('Y-m-d H:i:s'));
    echo "✅ PDF creado: $noActo.pdf\n";
}

echo "\n❌ PDFs NO creados (para probar validación):\n";
foreach ($registros as $reg) {
    if (!in_array($reg[3], $pdfsACrear)) {
        echo "   - {$reg[3]}.pdf\n";
    }
}

echo "\n📋 Resumen:\n";
echo "   Excel con 5 registros\n";
echo "   3 PDFs creados (60% completo)\n";
echo "   2 PDFs faltantes\n";

echo "\n🔗 Ve a http://localhost:8000/carga.html para probar\n";
echo "   Deberías ver que faltan 2 PDFs y no se puede procesar\n";

