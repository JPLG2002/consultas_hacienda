<?php
// api_carga_v2.php - API para carga masiva con espacios por usuario
require_once 'config.php';
require_once 'vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\IOFactory;

// Directorio base de uploads
define('UPLOADS_BASE', __DIR__ . '/uploads/');

// Aumentar límites
ini_set('max_execution_time', 0);
ini_set('memory_limit', '512M');

header('Content-Type: application/json');

// Obtener username del request
$username = sanitizeUsername($_GET['user'] ?? $_POST['user'] ?? '');

if (empty($username)) {
    echo json_encode(['success' => false, 'error' => 'Usuario no especificado']);
    exit;
}

// Definir directorios del usuario
$userDir = UPLOADS_BASE . $username . '/';
$excelDir = $userDir . 'excel/';
$pdfDir = $userDir . 'pdfs/';
$processedDir = $userDir . 'procesados/';

// Crear directorios del usuario si no existen
foreach ([$userDir, $excelDir, $pdfDir, $processedDir] as $dir) {
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }
}

$action = $_GET['action'] ?? $_POST['action'] ?? '';

switch ($action) {
    case 'upload_excel':
        uploadExcel($excelDir, $pdfDir);
        break;
    case 'list_pending':
        listPendingFiles($excelDir, $pdfDir);
        break;
    case 'verify_pdfs':
        verifyPdfs($excelDir, $pdfDir);
        break;
    case 'process_file':
        processFile($excelDir, $pdfDir, $processedDir);
        break;
    case 'delete_file':
        deleteFile($excelDir);
        break;
    case 'get_user_info':
        getUserInfo($userDir, $excelDir, $pdfDir, $processedDir);
        break;
    default:
        echo json_encode(['error' => 'Acción no válida']);
}

// ============================================================================
// SANITIZAR USERNAME (evitar ataques de path traversal)
// ============================================================================
function sanitizeUsername($username) {
    // Solo permitir letras, números, puntos, guiones y guiones bajos
    $username = preg_replace('/[^a-zA-Z0-9._-]/', '', $username);
    // Evitar nombres peligrosos
    if (in_array($username, ['.', '..', ''])) {
        return '';
    }
    return $username;
}

// ============================================================================
// OBTENER INFO DEL USUARIO
// ============================================================================
function getUserInfo($userDir, $excelDir, $pdfDir, $processedDir) {
    $excelCount = count(glob($excelDir . '*.{xlsx,xls,csv}', GLOB_BRACE));
    $pdfCount = count(glob($pdfDir . '*.pdf'));
    $processedCount = count(glob($processedDir . '*.*'));
    
    echo json_encode([
        'success' => true,
        'user_dir' => basename(dirname($excelDir)),
        'stats' => [
            'excel_pendientes' => $excelCount,
            'pdfs_disponibles' => $pdfCount,
            'archivos_procesados' => $processedCount
        ],
        'paths' => [
            'excel' => 'uploads/' . basename(dirname($excelDir)) . '/excel/',
            'pdfs' => 'uploads/' . basename(dirname($excelDir)) . '/pdfs/',
            'procesados' => 'uploads/' . basename(dirname($excelDir)) . '/procesados/'
        ]
    ]);
}

// ============================================================================
// SUBIR EXCEL
// ============================================================================
function uploadExcel($excelDir, $pdfDir) {
    if (!isset($_FILES['archivo'])) {
        echo json_encode(['success' => false, 'error' => 'No se recibió ningún archivo']);
        return;
    }

    $file = $_FILES['archivo'];
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

    if (!in_array($ext, ['xlsx', 'xls', 'csv'])) {
        echo json_encode(['success' => false, 'error' => 'Formato no permitido']);
        return;
    }

    $originalName = pathinfo($file['name'], PATHINFO_FILENAME);
    $timestamp = date('Y-m-d_H-i-s');
    $fileName = $originalName . '_' . $timestamp . '.' . $ext;
    $filePath = $excelDir . $fileName;

    if (move_uploaded_file($file['tmp_name'], $filePath)) {
        $verificacion = verificarPdfsDelArchivo($filePath, $pdfDir);
        
        echo json_encode([
            'success' => true,
            'message' => 'Archivo subido correctamente',
            'filename' => $fileName,
            'verificacion' => $verificacion
        ]);
    } else {
        echo json_encode(['success' => false, 'error' => 'Error al guardar el archivo']);
    }
}

// ============================================================================
// LISTAR ARCHIVOS PENDIENTES
// ============================================================================
function listPendingFiles($excelDir, $pdfDir) {
    $files = [];
    
    $excelFiles = glob($excelDir . '*.{xlsx,xls,csv}', GLOB_BRACE);
    
    foreach ($excelFiles as $excelPath) {
        $excelName = basename($excelPath);
        $verificacion = verificarPdfsDelArchivo($excelPath, $pdfDir);
        
        $files[] = [
            'nombre' => $excelName,
            'fecha_subida' => date('d/m/Y H:i', filemtime($excelPath)),
            'tamano' => formatFileSize(filesize($excelPath)),
            'total_registros' => $verificacion['total'],
            'con_pdf' => $verificacion['con_pdf'],
            'sin_pdf' => $verificacion['sin_pdf'],
            'porcentaje_completo' => $verificacion['total'] > 0 
                ? round(($verificacion['con_pdf'] / $verificacion['total']) * 100) 
                : 0,
            'registros_faltantes' => $verificacion['faltantes'],
            'listo_para_procesar' => $verificacion['sin_pdf'] === 0 && $verificacion['total'] > 0
        ];
    }
    
    usort($files, function($a, $b) {
        return strtotime(str_replace('/', '-', $b['fecha_subida'])) - strtotime(str_replace('/', '-', $a['fecha_subida']));
    });
    
    echo json_encode(['success' => true, 'files' => $files]);
}

// ============================================================================
// VERIFICAR PDFs
// ============================================================================
function verifyPdfs($excelDir, $pdfDir) {
    $filename = $_GET['filename'] ?? $_POST['filename'] ?? '';
    
    if (empty($filename)) {
        echo json_encode(['success' => false, 'error' => 'Nombre de archivo no proporcionado']);
        return;
    }
    
    $excelPath = $excelDir . $filename;
    
    if (!file_exists($excelPath)) {
        echo json_encode(['success' => false, 'error' => 'Archivo no encontrado']);
        return;
    }
    
    $verificacion = verificarPdfsDelArchivo($excelPath, $pdfDir);
    echo json_encode(['success' => true, 'verificacion' => $verificacion]);
}

// ============================================================================
// FUNCIÓN AUXILIAR: Verificar PDFs de un archivo Excel
// ============================================================================
function verificarPdfsDelArchivo($excelPath, $pdfDir) {
    $resultado = [
        'total' => 0,
        'con_pdf' => 0,
        'sin_pdf' => 0,
        'faltantes' => [],
        'completos' => []
    ];
    
    try {
        $spreadsheet = IOFactory::load($excelPath);
        $worksheet = $spreadsheet->getActiveSheet();
        $highestRow = $worksheet->getHighestRow();
        $highestColumn = $worksheet->getHighestColumn();
        $highestColumnIndex = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::columnIndexFromString($highestColumn);
        
        $headers = [];
        for ($col = 1; $col <= $highestColumnIndex; $col++) {
            $colLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($col);
            $headers[$col] = $worksheet->getCell($colLetter . '1')->getValue() ?? "col_$col";
        }
        
        $pdfFiles = glob($pdfDir . '*.pdf');
        $pdfDisponibles = [];
        foreach ($pdfFiles as $pdf) {
            $pdfDisponibles[strtolower(pathinfo(basename($pdf), PATHINFO_FILENAME))] = basename($pdf);
        }
        
        for ($row = 2; $row <= $highestRow; $row++) {
            $rowData = [];
            for ($col = 1; $col <= $highestColumnIndex; $col++) {
                $colLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($col);
                $rowData[$headers[$col]] = $worksheet->getCell($colLetter . $row)->getValue();
            }
            
            $noActo = trim($rowData['No_Acto_Administrativo'] ?? $rowData['No del Acto Administrativo'] ?? $rowData['NO_ACTO'] ?? $rowData['no_acto_administrativo'] ?? '');
            
            if (empty($noActo)) {
                continue;
            }
            
            $resultado['total']++;
            
            $pdfBuscado = strtolower($noActo);
            $pdfEncontrado = isset($pdfDisponibles[$pdfBuscado]);
            
            if ($pdfEncontrado) {
                $resultado['con_pdf']++;
                $resultado['completos'][] = [
                    'fila' => $row,
                    'no_acto' => $noActo,
                    'pdf' => $pdfDisponibles[$pdfBuscado]
                ];
            } else {
                $resultado['sin_pdf']++;
                $resultado['faltantes'][] = [
                    'fila' => $row,
                    'no_acto' => $noActo,
                    'pdf_esperado' => $noActo . '.pdf'
                ];
            }
        }
        
        $spreadsheet->disconnectWorksheets();
        unset($spreadsheet);
        
    } catch (Exception $e) {
        $resultado['error'] = $e->getMessage();
    }
    
    return $resultado;
}

// ============================================================================
// PROCESAR ARCHIVO
// ============================================================================
function processFile($excelDir, $pdfDir, $processedDir) {
    $excelFile = $_POST['excel_file'] ?? '';
    
    if (empty($excelFile)) {
        echo json_encode(['success' => false, 'error' => 'Nombre de archivo no proporcionado']);
        return;
    }
    
    $excelPath = $excelDir . $excelFile;
    
    if (!file_exists($excelPath)) {
        echo json_encode(['success' => false, 'error' => 'Archivo Excel no encontrado']);
        return;
    }
    
    try {
        $pdo = connectDB();
        
        $spreadsheet = IOFactory::load($excelPath);
        $worksheet = $spreadsheet->getActiveSheet();
        $highestRow = $worksheet->getHighestRow();
        $highestColumn = $worksheet->getHighestColumn();
        $highestColumnIndex = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::columnIndexFromString($highestColumn);
        
        $headers = [];
        for ($col = 1; $col <= $highestColumnIndex; $col++) {
            $colLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($col);
            $headers[$col] = $worksheet->getCell($colLetter . '1')->getValue() ?? "col_$col";
        }
        
        $pdfFiles = glob($pdfDir . '*.pdf');
        $pdfDisponibles = [];
        foreach ($pdfFiles as $pdf) {
            $pdfDisponibles[strtolower(pathinfo(basename($pdf), PATHINFO_FILENAME))] = basename($pdf);
        }
        
        $insertados = 0;
        $sinPdf = 0;
        $errores = 0;
        $erroresDetalle = [];
        $sinPdfDetalle = [];
        $pdfsMovidos = [];
        
        for ($row = 2; $row <= $highestRow; $row++) {
            $rowData = [];
            for ($col = 1; $col <= $highestColumnIndex; $col++) {
                $colLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($col);
                $rowData[$headers[$col]] = $worksheet->getCell($colLetter . $row)->getValue();
            }
            
            $noActo = trim($rowData['No_Acto_Administrativo'] ?? $rowData['No del Acto Administrativo'] ?? $rowData['NO_ACTO'] ?? $rowData['no_acto_administrativo'] ?? '');
            
            if (empty($noActo)) {
                continue;
            }
            
            $pdfBuscado = strtolower($noActo);
            if (!isset($pdfDisponibles[$pdfBuscado])) {
                $sinPdf++;
                $sinPdfDetalle[] = [
                    'fila' => $row,
                    'no_acto' => $noActo,
                    'pdf_esperado' => $noActo . '.pdf'
                ];
                continue;
            }
            
            $result = insertRow($pdo, $rowData, $row);
            if ($result['success']) {
                $insertados++;
                $pdfsMovidos[$pdfDisponibles[$pdfBuscado]] = true;
            } else {
                $errores++;
                $erroresDetalle[] = "Fila $row ($noActo): " . $result['error'];
            }
        }
        
        $spreadsheet->disconnectWorksheets();
        unset($spreadsheet);
        
        $timestamp = date('Y-m-d_H-i-s');
        
        rename($excelPath, $processedDir . pathinfo($excelFile, PATHINFO_FILENAME) . '_procesado_' . $timestamp . '.' . pathinfo($excelFile, PATHINFO_EXTENSION));
        
        foreach ($pdfsMovidos as $pdfName => $v) {
            $pdfPath = $pdfDir . $pdfName;
            if (file_exists($pdfPath)) {
                rename($pdfPath, $processedDir . pathinfo($pdfName, PATHINFO_FILENAME) . '_procesado_' . $timestamp . '.pdf');
            }
        }
        
        echo json_encode([
            'success' => true,
            'insertados' => $insertados,
            'sin_pdf' => $sinPdf,
            'errores' => $errores,
            'sin_pdf_detalle' => array_slice($sinPdfDetalle, 0, 20),
            'errores_detalle' => array_slice($erroresDetalle, 0, 10)
        ]);
        
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
}

// ============================================================================
// ELIMINAR ARCHIVO
// ============================================================================
function deleteFile($excelDir) {
    $filename = $_POST['filename'] ?? '';
    
    if (empty($filename)) {
        echo json_encode(['success' => false, 'error' => 'Nombre de archivo no proporcionado']);
        return;
    }
    
    $filePath = $excelDir . $filename;
    
    if (!file_exists($filePath)) {
        echo json_encode(['success' => false, 'error' => 'Archivo no encontrado']);
        return;
    }
    
    if (unlink($filePath)) {
        echo json_encode(['success' => true, 'message' => 'Archivo eliminado']);
    } else {
        echo json_encode(['success' => false, 'error' => 'Error al eliminar el archivo']);
    }
}

// ============================================================================
// FUNCIONES AUXILIARES
// ============================================================================

function formatFileSize($bytes) {
    if ($bytes === 0) return '0 Bytes';
    $k = 1024;
    $sizes = ['Bytes', 'KB', 'MB', 'GB'];
    $i = floor(log($bytes) / log($k));
    return round($bytes / pow($k, $i), 2) . ' ' . $sizes[$i];
}

function insertRow($pdo, $row, $rowNumber) {
    try {
        $idPredio = trim($row['ID_1'] ?? $row['#ID'] ?? $row['ID_PREDIO'] ?? $row['id_predio'] ?? $row['Id_1'] ?? '');
        $idContribuyente = trim($row['ID_2'] ?? $row['#ID.1'] ?? $row['ID_CONTRIBUYENTE'] ?? $row['id_contribuyente'] ?? $row['Id_2'] ?? $row['CEDULA'] ?? '');
        $razonSocial = trim($row['Razon_Social'] ?? $row['Razón Social'] ?? $row['RAZON_SOCIAL'] ?? $row['razon_social'] ?? '');
        $noActo = trim($row['No_Acto_Administrativo'] ?? $row['No del Acto Administrativo'] ?? $row['NO_ACTO'] ?? $row['no_acto_administrativo'] ?? '');
        $fechaActo = parseDate($row['Fecha_Acto_Administrativo'] ?? $row['Fecha del acto Administrativo'] ?? $row['FECHA_ACTO'] ?? '');
        $fechaPublicacion = parseDate($row['Fecha_Publicacion'] ?? $row['Fecha Publicación'] ?? $row['FECHA_PUBLICACION'] ?? '');
        $tipoActuacion = trim($row['Tipo_Actuacion'] ?? $row['Tipo de Actuación'] ?? $row['TIPO_ACTUACION'] ?? '');
        $organismo = trim($row['Organismo'] ?? $row['ORGANISMO'] ?? 'DEPARTAMENTO ADMINISTRATIVO DE HACIENDA');
        $area = trim($row['Area'] ?? $row['Área'] ?? $row['AREA'] ?? '');
        $fechaDesfijacion = parseDate($row['Fecha_Desfijacion'] ?? $row['Fecha Desfijación'] ?? $row['FECHA_DESFIJACION'] ?? '');
        
        $masDatos = [];
        $jsonField = $row['Mas_Datos'] ?? $row['mas datos'] ?? $row['MAS_DATOS'] ?? '';
        if (!empty($jsonField)) {
            $masDatos = json_decode($jsonField, true) ?: [];
        }

        if (empty($idContribuyente)) {
            throw new Exception('ID del contribuyente es requerido');
        }
        if (empty($razonSocial)) {
            throw new Exception('Razón Social es requerida');
        }

        $estado = 'tramite';
        if ($fechaDesfijacion && strtotime($fechaDesfijacion) < time()) {
            $estado = 'finalizado';
        }

        $sql = "INSERT INTO actos_administrativos 
                (id_predio, id_contribuyente, razon_social, no_acto_administrativo, 
                 fecha_acto, fecha_publicacion, tipo_actuacion, organismo, area, 
                 fecha_desfijacion, mas_datos, estado)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            $idPredio,
            $idContribuyente,
            $razonSocial,
            $noActo,
            $fechaActo,
            $fechaPublicacion,
            $tipoActuacion,
            $organismo,
            $area,
            $fechaDesfijacion,
            json_encode($masDatos),
            $estado
        ]);

        return ['success' => true];

    } catch (Exception $e) {
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

function parseDate($dateString) {
    if (empty($dateString)) return null;
    
    $formats = ['Y-m-d', 'd/m/Y', 'm/d/Y', 'Y/m/d', 'Ymd'];
    
    foreach ($formats as $format) {
        $date = DateTime::createFromFormat($format, trim($dateString));
        if ($date) return $date->format('Y-m-d');
    }
    
    $timestamp = strtotime($dateString);
    if ($timestamp) return date('Y-m-d', $timestamp);
    
    return null;
}
?>
