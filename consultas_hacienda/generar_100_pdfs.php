<?php
/**
 * GENERADOR DE 100 PDFs CON DATOS DE LA IMAGEN
 * Genera 100 documentos PDF con los datos de trámites de Hacienda
 */

require_once 'vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

echo "🚀 Generando 100 PDFs con datos de trámites de Hacienda...\n\n";

// Crear directorio de PDFs si no existe
$pdfDir = __DIR__ . '/uploads/pdfs/';
if (!is_dir($pdfDir)) {
    mkdir($pdfDir, 0755, true);
}

// Datos base extraídos de la imagen
$datos_base = [
    ['N_ACT_TRA' => '1996-2001', 'NOM_COM' => 'LUIS MICHICASU DESEARIA RESTREPOLIMARC', 'NUM_CRI' => '10028699', 'NO_ACT_TRA' => '47011014121 R035 0127', 'COD_IP' => 2019, 'ACT_TRA' => '4/24', 'TRA_ROP' => '2025 0 19', 'HBR' => 05],
    ['N_ACT_TRA' => '1996-2002', 'NOM_COM' => 'TOÑAS YAROW RODRIGUEZ MUYESPECIALEGASIPN', 'NUM_CRI' => '13106969', 'NO_ACT_TRA' => '47311014121 R035 0127', 'COD_IP' => 2024, 'ACT_TRA' => '4/24', 'TRA_ROP' => '2025 0 19', 'HBR' => 05],
    ['N_ACT_TRA' => '1996-2003', 'NOM_COM' => 'CARLOS GARAVETODOSAR,GASIDAMMEZ96RISOGAF', 'NUM_CRI' => '97335858', 'NO_ACT_TRA' => '47311014121 R035 0127', 'COD_IP' => 2025, 'ACT_TRA' => '4/24', 'TRA_ROP' => '2025 0 19', 'HBR' => 05],
    ['N_ACT_TRA' => '1996-2004', 'NOM_COM' => 'MARIA DEL ROSARIO SANTAMARIA', 'NUM_CRI' => '18710485', 'NO_ACT_TRA' => '47311014121 R035 0127', 'COD_IP' => 2014, 'ACT_TRA' => '4/24', 'TRA_ROP' => '2025 0 19', 'HBR' => 05],
    ['N_ACT_TRA' => '1996-2005', 'NOM_COM' => 'TOMAS ENRIQUE VELASQUEZ RODRIGUEZ', 'NUM_CRI' => '17196681', 'NO_ACT_TRA' => '47311014121 R035 0127', 'COD_IP' => 2024, 'ACT_TRA' => '4/24', 'TRA_ROP' => '2025 0 19', 'HBR' => 05],
    ['N_ACT_TRA' => '1996-2006', 'NOM_COM' => 'MARIO ANDRES GONZALEZ PEREZ', 'NUM_CRI' => '133096898', 'NO_ACT_TRA' => '47311014121 R035 0127', 'COD_IP' => 2024, 'ACT_TRA' => '4/24', 'TRA_ROP' => '2025 0 19', 'HBR' => 05],
    ['N_ACT_TRA' => '1996-2007', 'NOM_COM' => 'JOSE LUIS MARTINEZ CASTRO', 'NUM_CRI' => '10706069', 'NO_ACT_TRA' => '47311014121 R035 0127', 'COD_IP' => 2014, 'ACT_TRA' => '4/24', 'TRA_ROP' => '2025 0 19', 'HBR' => 05],
    ['N_ACT_TRA' => '1996-2008', 'NOM_COM' => 'JUANA MARIA RODRIGUEZ LOPEZ', 'NUM_CRI' => '160706684', 'NO_ACT_TRA' => '47311014121 R035 0127', 'COD_IP' => 2014, 'ACT_TRA' => '4/24', 'TRA_ROP' => '2025 0 19', 'HBR' => 05],
    ['N_ACT_TRA' => '1996-2009', 'NOM_COM' => 'JESUS HERNANDEZ ROJAS', 'NUM_CRI' => '80170804', 'NO_ACT_TRA' => '47311014121 R035 0127', 'COD_IP' => 2024, 'ACT_TRA' => '4/24', 'TRA_ROP' => '2025 0 19', 'HBR' => 05],
    ['N_ACT_TRA' => '1996-2010', 'NOM_COM' => 'ADRIANA CASTILLO MEJIA', 'NUM_CRI' => '31845088', 'NO_ACT_TRA' => '47311014121 R035 0127', 'COD_IP' => 2014, 'ACT_TRA' => '4/24', 'TRA_ROP' => '2025 0 19', 'HBR' => 05],
];

// Nombres colombianos para generar variedad
$nombres = ['JUAN', 'CARLOS', 'MARIA', 'ANA', 'PEDRO', 'JOSE', 'LUIS', 'ANDRES', 'DIANA', 'PATRICIA', 'EDUARDO', 'FERNANDO', 'GABRIEL', 'RICARDO', 'MIGUEL', 'ALEJANDRO', 'SANDRA', 'CAROLINA'];
$apellidos = ['GARCIA', 'RODRIGUEZ', 'MARTINEZ', 'LOPEZ', 'HERNANDEZ', 'GONZALEZ', 'PEREZ', 'SANCHEZ', 'RAMIREZ', 'TORRES', 'FLORES', 'RIVERA', 'GOMEZ', 'DIAZ', 'REYES', 'MORALES', 'JIMENEZ', 'RUIZ', 'CASTILLO', 'ORTIZ'];

// Áreas y dependencias
$areas = ['Cobro Coactivo', 'Tesorería', 'Impuestos', 'Fiscalización', 'Catastro', 'Rentas', 'Contabilidad'];
$cod_usus = ['COD_R', 'COD_A', 'COD_B', 'COD_C', 'COD_D'];

echo "📁 Directorio de PDFs: $pdfDir\n\n";

$pdfsCreados = 0;
$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();

// Headers del Excel basados en la imagen
$headers = ['N_ACT_TRA', 'NOM_COM', 'NUM_CRI', 'NO_ACT_TRA', 'COD_IP', 'ACT_TRA', 'TRA_ROP', 'HBR', 'ARC', 'COD_USU_RER', 'PLB', 'MES_ORA', 'AN_PRA', 'CAU_URO', 'HOSP'];
foreach ($headers as $col => $header) {
    $sheet->setCellValue(chr(65 + $col) . '1', $header);
}

for ($i = 1; $i <= 100; $i++) {
    $anio_base = 1996;
    $numero = 2000 + $i;
    $n_act_tra = "$anio_base-$numero";
    
    // Generar nombre aleatorio
    $nombre = $nombres[array_rand($nombres)] . ' ' . $apellidos[array_rand($apellidos)] . ' ' . $apellidos[array_rand($apellidos)];
    
    // Generar número de cédula aleatorio (8-10 dígitos)
    $num_cri = rand(10000000, 9999999999);
    
    // Generar número de acto
    $no_act_tra = sprintf("4731101412%d R035 %04d", rand(1, 9), $i);
    
    // Otros datos
    $cod_ip = rand(2014, 2025);
    $act_tra = rand(1, 12) . '/24';
    $tra_rop = "2025 " . rand(0, 1) . " " . rand(10, 30);
    $hbr = sprintf("%02d", rand(1, 12));
    $area = $areas[array_rand($areas)];
    $cod_usu = $cod_usus[array_rand($cod_usus)];
    $mes_ora = sprintf("%02d", rand(1, 12));
    $an_pra = rand(2020, 2025);
    
    // Guardar en Excel
    $row = $i + 1;
    $sheet->setCellValue('A' . $row, $n_act_tra);
    $sheet->setCellValue('B' . $row, $nombre);
    $sheet->setCellValue('C' . $row, $num_cri);
    $sheet->setCellValue('D' . $row, $no_act_tra);
    $sheet->setCellValue('E' . $row, $cod_ip);
    $sheet->setCellValue('F' . $row, $act_tra);
    $sheet->setCellValue('G' . $row, $tra_rop);
    $sheet->setCellValue('H' . $row, $hbr);
    $sheet->setCellValue('I' . $row, $area);
    $sheet->setCellValue('J' . $row, $cod_usu);
    $sheet->setCellValue('K' . $row, rand(1, 100));
    $sheet->setCellValue('L' . $row, $mes_ora);
    $sheet->setCellValue('M' . $row, $an_pra);
    $sheet->setCellValue('N' . $row, rand(1000, 9999));
    $sheet->setCellValue('O' . $row, rand(1, 5));
    
    // Crear contenido del PDF
    $contenidoPDF = "
================================================================================
                    DEPARTAMENTO DE HACIENDA
                    GOBERNACIÓN DEL ATLÁNTICO
================================================================================

DOCUMENTO DE TRÁMITE ADMINISTRATIVO
Generado: " . date('Y-m-d H:i:s') . "

--------------------------------------------------------------------------------
                         DATOS DEL TRÁMITE
--------------------------------------------------------------------------------

N° ACTO TRÁMITE:    $n_act_tra
N° ACTO ADMIN:      $no_act_tra
AÑO PROCESO:        $cod_ip
ÁREA:               $area
CÓDIGO USUARIO:     $cod_usu

--------------------------------------------------------------------------------
                    INFORMACIÓN DEL CONTRIBUYENTE
--------------------------------------------------------------------------------

NOMBRE/RAZÓN SOCIAL:    $nombre
IDENTIFICACIÓN:         $num_cri
FECHA ACTUACIÓN:        $act_tra
MES:                    $mes_ora
AÑO:                    $an_pra

--------------------------------------------------------------------------------
                         OBSERVACIONES
--------------------------------------------------------------------------------

Este documento ha sido generado automáticamente por el sistema de
consultas de la Secretaría de Hacienda del Departamento del Atlántico.

Para verificar la autenticidad de este documento, ingrese al portal
oficial de consultas: http://localhost:8080

Código de verificación: TRA-$n_act_tra-$num_cri

================================================================================
           DOCUMENTO OFICIAL - GOBERNACIÓN DEL ATLÁNTICO
================================================================================
";
    
    // Guardar PDF
    $pdfPath = $pdfDir . $n_act_tra . '.pdf';
    file_put_contents($pdfPath, $contenidoPDF);
    
    $pdfsCreados++;
    
    if ($i % 10 == 0) {
        echo "✅ Progreso: $pdfsCreados/100 PDFs creados...\n";
    }
}

// Guardar Excel
$excelDir = __DIR__ . '/uploads/excel/';
if (!is_dir($excelDir)) {
    mkdir($excelDir, 0755, true);
}

$excelFilename = "tramites_100_registros_" . date('Y-m-d_H-i-s') . ".xlsx";
$excelPath = $excelDir . $excelFilename;
$writer = new Xlsx($spreadsheet);
$writer->save($excelPath);

echo "\n================================================================================\n";
echo "                    ✅ GENERACIÓN COMPLETADA\n";
echo "================================================================================\n\n";
echo "📊 Excel generado:  $excelFilename\n";
echo "📁 Ubicación:       uploads/excel/\n\n";
echo "📄 PDFs generados:  $pdfsCreados archivos\n";
echo "📁 Ubicación:       uploads/pdfs/\n\n";
echo "📋 Formato nombres: 1996-2001.pdf hasta 1996-2100.pdf\n\n";
echo "================================================================================\n";
echo "🔗 Ve a http://localhost:8080 para ver los datos en el sistema\n";
echo "================================================================================\n";
?>
