<?php
/**
 * GENERADOR DE DATOS HISTÓRICOS PARA PROBAR FILTROS
 * Genera datos con diferentes años y dependencias
 */

require_once 'vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

echo "🚀 Generando datos históricos para prueba de filtros...\n";

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

// Datos de prueba con variedad
$areas = [
    'Subdirección de Impuestos y Rentas',
    'Subdirección de Tesorería',
    'Subdirección de Catastro',
    'Cobro Coactivo',
    'Fiscalización Tributaria',
    'Gestión de Ingresos',
    'Control de Obligaciones'
];

$tiposActuacion = [
    'Resolución de Cobro Coactivo',
    'Mandamiento de Pago',
    'Liquidación Oficial de Aforo',
    'Requerimiento Especial',
    'Citación para Notificación'
];

$nombres = ['JUAN GARCIA', 'MARIA RODRIGUEZ', 'CARLOS MARTINEZ', 'ANA LOPEZ', 'PEDRO GONZALEZ', 
            'LUCIA HERNANDEZ', 'DIEGO PEREZ', 'SOFIA SANCHEZ', 'ANDRES RAMIREZ', 'VALENTINA TORRES',
            'EMPRESA ABC S.A.S', 'COMERCIAL XYZ LTDA', 'INVERSIONES DEL VALLE', 'CONSTRUCTORA CALI'];

// Años para generar datos (2020-2025)
$years = [2020, 2021, 2022, 2023, 2024, 2025];

$row = 2;
$totalRows = 0;

// Generar datos para cada combinación de año y área
foreach ($years as $year) {
    // Generar entre 5-15 registros por año
    $registrosPorAno = rand(8, 15);
    
    for ($i = 0; $i < $registrosPorAno; $i++) {
        $idPredio = 'PRD-' . str_pad(rand(1, 999999), 6, '0', STR_PAD_LEFT);
        $idContribuyente = rand(10000000, 99999999) . '-' . rand(0, 9);
        $razonSocial = $nombres[array_rand($nombres)];
        $noActo = 'RES-' . $year . '-' . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);
        
        // Fecha del acto en el año especificado
        $mes = str_pad(rand(1, 12), 2, '0', STR_PAD_LEFT);
        $dia = str_pad(rand(1, 28), 2, '0', STR_PAD_LEFT);
        $fechaActo = "{$year}-{$mes}-{$dia}";
        
        // Fecha publicación (unos días después)
        $fechaPublicacion = date('Y-m-d', strtotime($fechaActo . ' +' . rand(1, 15) . ' days'));
        
        // IMPORTANTE: Fecha desfijación en el PASADO para que sea histórico
        $fechaDesfijacion = date('Y-m-d', strtotime($fechaPublicacion . ' +5 days'));
        
        // Si la fecha de desfijación es futura, ajustarla al pasado
        if (strtotime($fechaDesfijacion) > time()) {
            $fechaDesfijacion = date('Y-m-d', strtotime('-' . rand(1, 30) . ' days'));
        }
        
        $tipoActuacion = $tiposActuacion[array_rand($tiposActuacion)];
        $organismo = 'DEPARTAMENTO ADMINISTRATIVO DE HACIENDA';
        $area = $areas[array_rand($areas)];
        
        $sheet->setCellValue('A' . $row, $idPredio);
        $sheet->setCellValue('B' . $row, $idContribuyente);
        $sheet->setCellValue('C' . $row, $razonSocial);
        $sheet->setCellValue('D' . $row, $noActo);
        $sheet->setCellValue('E' . $row, $fechaActo);
        $sheet->setCellValue('F' . $row, $fechaPublicacion);
        $sheet->setCellValue('G' . $row, $tipoActuacion);
        $sheet->setCellValue('H' . $row, $organismo);
        $sheet->setCellValue('I' . $row, $area);
        $sheet->setCellValue('J' . $row, $fechaDesfijacion);
        
        $row++;
        $totalRows++;
    }
    
    echo "   Año {$year}: {$registrosPorAno} registros generados\n";
}

// Guardar archivo
$filename = "historicos_filtros_prueba_" . date('Y-m-d_H-i-s') . ".xlsx";
$filepath = __DIR__ . '/uploads/excel/' . $filename;

$writer = new Xlsx($spreadsheet);
$writer->save($filepath);

// Crear PDF asociado
$pdfPath = __DIR__ . '/uploads/pdfs/' . $filename . '.pdf';
file_put_contents($pdfPath, "PDF de prueba para filtros históricos\nGenerado: " . date('Y-m-d H:i:s'));

echo "\n✅ ¡Archivos generados!\n";
echo "📊 Excel: {$filename}\n";
echo "📄 PDF: {$filename}.pdf\n";
echo "📈 Total registros: {$totalRows}\n";
echo "\n📋 Distribución por área:\n";

// Mostrar resumen
echo "\n🔗 Ve a http://localhost:8000/carga.html para procesar\n";
echo "🔍 Luego consulta cualquier ID para probar los filtros\n";

