<?php
/**
 * GENERADOR DE DATOS HISTÓRICOS V2
 * Un mismo ID con múltiples registros en diferentes años y dependencias
 */

require_once 'vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

echo "🚀 Generando datos históricos para prueba de filtros...\n";
echo "   (Mismo ID con múltiples registros)\n\n";

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

// Áreas/Dependencias para filtrar
$areas = [
    'Subdirección de Impuestos y Rentas',
    'Subdirección de Tesorería',
    'Subdirección de Catastro',
    'Cobro Coactivo',
    'Fiscalización Tributaria'
];

$tiposActuacion = [
    'Resolución de Cobro Coactivo',
    'Mandamiento de Pago',
    'Liquidación Oficial de Aforo',
    'Requerimiento Especial',
    'Citación para Notificación'
];

// IDs FIJOS que tendrán múltiples registros
$contribuyentes = [
    ['id' => '12345678-9', 'predio' => 'PRD-000001', 'nombre' => 'JUAN CARLOS GARCIA MARTINEZ'],
    ['id' => '98765432-1', 'predio' => 'PRD-000002', 'nombre' => 'MARIA FERNANDA LOPEZ RODRIGUEZ'],
    ['id' => '55555555-5', 'predio' => 'PRD-000003', 'nombre' => 'EMPRESA ABC S.A.S'],
];

$years = [2020, 2021, 2022, 2023, 2024, 2025];

$row = 2;
$totalRows = 0;

echo "📋 Generando registros por contribuyente:\n";

foreach ($contribuyentes as $contribuyente) {
    $registrosContribuyente = 0;
    
    // Generar registros para CADA año y CADA área
    foreach ($years as $year) {
        foreach ($areas as $area) {
            // Generar 1-2 registros por combinación año/área
            $cantidad = rand(1, 2);
            
            for ($i = 0; $i < $cantidad; $i++) {
                $noActo = 'RES-' . $year . '-' . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);
                
                $mes = str_pad(rand(1, 12), 2, '0', STR_PAD_LEFT);
                $dia = str_pad(rand(1, 28), 2, '0', STR_PAD_LEFT);
                $fechaActo = "{$year}-{$mes}-{$dia}";
                $fechaPublicacion = date('Y-m-d', strtotime($fechaActo . ' +' . rand(1, 10) . ' days'));
                
                // Fecha desfijación PASADA (para que sea histórico)
                $fechaDesfijacion = date('Y-m-d', strtotime($fechaPublicacion . ' +5 days'));
                if (strtotime($fechaDesfijacion) > time()) {
                    $fechaDesfijacion = date('Y-m-d', strtotime('-' . rand(1, 60) . ' days'));
                }
                
                $tipoActuacion = $tiposActuacion[array_rand($tiposActuacion)];
                
                $sheet->setCellValue('A' . $row, $contribuyente['predio']);
                $sheet->setCellValue('B' . $row, $contribuyente['id']);
                $sheet->setCellValue('C' . $row, $contribuyente['nombre']);
                $sheet->setCellValue('D' . $row, $noActo);
                $sheet->setCellValue('E' . $row, $fechaActo);
                $sheet->setCellValue('F' . $row, $fechaPublicacion);
                $sheet->setCellValue('G' . $row, $tipoActuacion);
                $sheet->setCellValue('H' . $row, 'DEPARTAMENTO ADMINISTRATIVO DE HACIENDA');
                $sheet->setCellValue('I' . $row, $area);
                $sheet->setCellValue('J' . $row, $fechaDesfijacion);
                
                $row++;
                $totalRows++;
                $registrosContribuyente++;
            }
        }
    }
    
    echo "   ✅ {$contribuyente['id']} ({$contribuyente['nombre']}): {$registrosContribuyente} registros\n";
}

// Guardar archivo
$filename = "historicos_multiples_" . date('Y-m-d_H-i-s') . ".xlsx";
$filepath = __DIR__ . '/uploads/excel/' . $filename;

$writer = new Xlsx($spreadsheet);
$writer->save($filepath);

// Crear PDF asociado
$pdfPath = __DIR__ . '/uploads/pdfs/' . $filename . '.pdf';
file_put_contents($pdfPath, "PDF de prueba - Históricos Múltiples\nGenerado: " . date('Y-m-d H:i:s'));

echo "\n✅ ¡Archivos generados!\n";
echo "📊 Excel: {$filename}\n";
echo "📄 PDF: {$filename}.pdf\n";
echo "📈 Total registros: {$totalRows}\n";

echo "\n🔍 IDs PARA BUSCAR Y PROBAR FILTROS:\n";
echo "   ➡️  12345678-9  (JUAN CARLOS GARCIA)\n";
echo "   ➡️  98765432-1  (MARIA FERNANDA LOPEZ)\n";
echo "   ➡️  55555555-5  (EMPRESA ABC S.A.S)\n";

echo "\n📅 Años disponibles: 2020, 2021, 2022, 2023, 2024, 2025\n";
echo "🏢 Dependencias: Impuestos, Tesorería, Catastro, Cobro Coactivo, Fiscalización\n";

echo "\n🔗 Pasos:\n";
echo "   1. Ve a http://localhost:8000/carga.html\n";
echo "   2. Procesa el archivo\n";
echo "   3. Busca uno de los IDs arriba\n";
echo "   4. Ve a pestaña 'Histórico' y prueba los filtros\n";

