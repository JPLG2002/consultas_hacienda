<?php
/**
 * GENERADOR DE ARCHIVO EXCEL GRANDE PARA PRUEBAS
 * 
 * Uso: php generar_datos_prueba.php [cantidad_filas]
 * Ejemplo: php generar_datos_prueba.php 5000
 */

require_once 'vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

// Cantidad de filas (default: 1000)
$cantidadFilas = isset($argv[1]) ? (int)$argv[1] : 1000;

echo "🚀 Generando archivo Excel con {$cantidadFilas} filas...\n";

$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();

// Headers (igual que tu plantilla)
$headers = [
    'ID_1',           // ID Predio
    'ID_2',           // ID Contribuyente
    'Razon_Social',
    'No_Acto_Administrativo',
    'Fecha_Acto_Administrativo',
    'Fecha_Publicacion',
    'Tipo_Actuacion',
    'Organismo',
    'Area',
    'Fecha_Desfijacion'
];

// Escribir headers
foreach ($headers as $col => $header) {
    $sheet->setCellValue(chr(65 + $col) . '1', $header);
}

// Datos aleatorios
$tiposActuacion = [
    'Resolución de Cobro Coactivo',
    'Mandamiento de Pago',
    'Liquidación Oficial de Aforo',
    'Requerimiento Especial',
    'Citación para Notificación',
    'Auto de Archivo',
    'Resolución Sancionatoria',
    'Emplazamiento para Declarar',
    'Liquidación de Corrección',
    'Resolución de Facilidad de Pago'
];

$areas = [
    'Subdirección de Impuestos y Rentas',
    'Subdirección de Tesorería',
    'Subdirección de Catastro',
    'Cobro Coactivo',
    'Fiscalización Tributaria',
    'Gestión de Ingresos',
    'Control de Obligaciones'
];

$nombres = ['Juan', 'María', 'Carlos', 'Ana', 'Pedro', 'Lucía', 'Diego', 'Sofía', 'Andrés', 'Valentina'];
$apellidos = ['García', 'Rodríguez', 'Martínez', 'López', 'González', 'Hernández', 'Pérez', 'Sánchez', 'Ramírez', 'Torres'];

echo "⏳ Escribiendo datos...\n";

$startTime = microtime(true);

for ($i = 2; $i <= $cantidadFilas + 1; $i++) {
    // Generar datos aleatorios
    $idPredio = 'PRD-' . str_pad(rand(1, 999999), 6, '0', STR_PAD_LEFT);
    $idContribuyente = rand(10000000, 99999999) . '-' . rand(0, 9);
    $nombre = $nombres[array_rand($nombres)];
    $apellido = $apellidos[array_rand($apellidos)];
    $razonSocial = strtoupper($nombre . ' ' . $apellido . ' ' . $apellidos[array_rand($apellidos)]);
    $noActo = 'RES-' . date('Y') . '-' . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);
    
    // Fechas
    $fechaActo = date('Y-m-d', strtotime('-' . rand(1, 365) . ' days'));
    $fechaPublicacion = date('Y-m-d', strtotime($fechaActo . ' +' . rand(1, 30) . ' days'));
    
    // 70% sin fecha desfijación (en trámite), 30% con fecha (histórico)
    $fechaDesfijacion = '';
    if (rand(1, 100) <= 30) {
        $fechaDesfijacion = date('Y-m-d', strtotime($fechaPublicacion . ' +5 days'));
    }
    
    $tipoActuacion = $tiposActuacion[array_rand($tiposActuacion)];
    $organismo = 'DEPARTAMENTO ADMINISTRATIVO DE HACIENDA';
    $area = $areas[array_rand($areas)];
    
    // Escribir fila
    $sheet->setCellValue('A' . $i, $idPredio);
    $sheet->setCellValue('B' . $i, $idContribuyente);
    $sheet->setCellValue('C' . $i, $razonSocial);
    $sheet->setCellValue('D' . $i, $noActo);
    $sheet->setCellValue('E' . $i, $fechaActo);
    $sheet->setCellValue('F' . $i, $fechaPublicacion);
    $sheet->setCellValue('G' . $i, $tipoActuacion);
    $sheet->setCellValue('H' . $i, $organismo);
    $sheet->setCellValue('I' . $i, $area);
    $sheet->setCellValue('J' . $i, $fechaDesfijacion);
    
    // Mostrar progreso cada 500 filas
    if ($i % 500 === 0) {
        $porcentaje = round(($i / $cantidadFilas) * 100);
        echo "   Progreso: {$porcentaje}% ({$i}/{$cantidadFilas} filas)\n";
    }
}

echo "💾 Guardando archivo...\n";

// Guardar archivo
$filename = "datos_prueba_{$cantidadFilas}_filas_" . date('Y-m-d_H-i-s') . ".xlsx";
$filepath = __DIR__ . '/uploads/excel/' . $filename;

$writer = new Xlsx($spreadsheet);
$writer->save($filepath);

$endTime = microtime(true);
$duration = round($endTime - $startTime, 2);
$fileSize = round(filesize($filepath) / 1024 / 1024, 2);

echo "\n✅ ¡Archivo generado exitosamente!\n";
echo "📁 Archivo: {$filename}\n";
echo "📊 Filas: {$cantidadFilas}\n";
echo "💾 Tamaño: {$fileSize} MB\n";
echo "⏱️  Tiempo: {$duration} segundos\n";
echo "\n🔗 Ahora ve a http://localhost:8000/carga.html para probarlo\n";

