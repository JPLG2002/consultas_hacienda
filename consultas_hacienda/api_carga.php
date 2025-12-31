<?php
// api_carga.php - API para carga masiva de datos
require_once 'config.php';
require_once 'vendor/autoload.php'; // PhpSpreadsheet

use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Reader\Xlsx;
use PhpOffice\PhpSpreadsheet\Reader\Xls;
use PhpOffice\PhpSpreadsheet\Reader\Csv;

// Aumentar límites para archivos grandes
ini_set('max_execution_time', 0);
ini_set('memory_limit', '512M');

$action = $_GET['action'] ?? $_POST['action'] ?? '';

switch ($action) {
    case 'upload':
        handleUpload();
        break;
    case 'process':
        processChunk();
        break;
    case 'status':
        getStatus();
        break;
    case 'cancel':
        cancelProcess();
        break;
    default:
        echo json_encode(['error' => 'Acción no válida']);
}

// ============================================================================
// SUBIR ARCHIVO
// ============================================================================
function handleUpload() {
    if (!isset($_FILES['archivo'])) {
        http_response_code(400);
        echo json_encode(['error' => 'No se recibió ningún archivo']);
        return;
    }

    $file = $_FILES['archivo'];
    $allowedTypes = [
        'application/vnd.ms-excel',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'text/csv',
        'text/plain'
    ];

    // Validar extensión
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, ['xlsx', 'xls', 'csv'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Formato no permitido. Use Excel (.xlsx, .xls) o CSV']);
        return;
    }

    // Crear directorio de uploads si no existe
    $uploadDir = __DIR__ . '/uploads/';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }

    // Guardar archivo con nombre único
    $fileName = uniqid('carga_') . '.' . $ext;
    $filePath = $uploadDir . $fileName;

    if (!move_uploaded_file($file['tmp_name'], $filePath)) {
        http_response_code(500);
        echo json_encode(['error' => 'Error al guardar el archivo']);
        return;
    }

    // Contar líneas/registros
    $totalRows = countFileRows($filePath, $ext);

    // Registrar en base de datos
    $pdo = connectDB();
    $stmt = $pdo->prepare("INSERT INTO cargas_masivas (nombre_archivo, total_registros, estado) VALUES (?, ?, 'pendiente')");
    $stmt->execute([$fileName, $totalRows]);
    $cargaId = $pdo->lastInsertId();

    echo json_encode([
        'success' => true,
        'carga_id' => $cargaId,
        'archivo' => $fileName,
        'total_registros' => $totalRows
    ]);
}

// ============================================================================
// PROCESAR CHUNK
// ============================================================================
function processChunk() {
    $cargaId = $_POST['carga_id'] ?? 0;
    $offset = intval($_POST['offset'] ?? 0);
    $limit = intval($_POST['limit'] ?? 50); // Procesar 50 registros por chunk

    if (!$cargaId) {
        http_response_code(400);
        echo json_encode(['error' => 'ID de carga no proporcionado']);
        return;
    }

    $pdo = connectDB();
    
    // Obtener info de la carga
    $stmt = $pdo->prepare("SELECT * FROM cargas_masivas WHERE id = ?");
    $stmt->execute([$cargaId]);
    $carga = $stmt->fetch();

    if (!$carga) {
        http_response_code(404);
        echo json_encode(['error' => 'Carga no encontrada']);
        return;
    }

    if ($carga['estado'] === 'cancelado') {
        echo json_encode(['cancelled' => true]);
        return;
    }

    // Actualizar estado a procesando
    if ($carga['estado'] === 'pendiente') {
        $stmt = $pdo->prepare("UPDATE cargas_masivas SET estado = 'procesando' WHERE id = ?");
        $stmt->execute([$cargaId]);
    }

    $filePath = __DIR__ . '/uploads/' . $carga['nombre_archivo'];
    $ext = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));

    // Leer chunk del archivo
    $rows = readFileChunk($filePath, $ext, $offset, $limit);
    
    $processed = 0;
    $success = 0;
    $failed = 0;
    $errors = [];

    foreach ($rows as $index => $row) {
        $rowNumber = $offset + $index + 2; // +2 porque el header es fila 1
        $result = insertRow($pdo, $row, $cargaId, $rowNumber);
        
        $processed++;
        if ($result['success']) {
            $success++;
        } else {
            $failed++;
            $errors[] = [
                'fila' => $rowNumber,
                'error' => $result['error']
            ];
        }
    }

    // Actualizar progreso
    $stmt = $pdo->prepare("UPDATE cargas_masivas SET 
        registros_procesados = registros_procesados + ?,
        registros_exitosos = registros_exitosos + ?,
        registros_fallidos = registros_fallidos + ?
        WHERE id = ?");
    $stmt->execute([$processed, $success, $failed, $cargaId]);

    // Verificar si terminó
    $stmt = $pdo->prepare("SELECT * FROM cargas_masivas WHERE id = ?");
    $stmt->execute([$cargaId]);
    $cargaActualizada = $stmt->fetch();

    $finished = $cargaActualizada['registros_procesados'] >= $carga['total_registros'];

    if ($finished) {
        $stmt = $pdo->prepare("UPDATE cargas_masivas SET estado = 'completado' WHERE id = ?");
        $stmt->execute([$cargaId]);
    }

    echo json_encode([
        'success' => true,
        'processed' => $processed,
        'chunk_success' => $success,
        'chunk_failed' => $failed,
        'errors' => $errors,
        'total_procesados' => $cargaActualizada['registros_procesados'],
        'total_registros' => $carga['total_registros'],
        'porcentaje' => round(($cargaActualizada['registros_procesados'] / $carga['total_registros']) * 100, 1),
        'finished' => $finished
    ]);
}

// ============================================================================
// OBTENER ESTADO
// ============================================================================
function getStatus() {
    $cargaId = $_GET['carga_id'] ?? 0;
    
    if (!$cargaId) {
        http_response_code(400);
        echo json_encode(['error' => 'ID de carga no proporcionado']);
        return;
    }

    $pdo = connectDB();
    $stmt = $pdo->prepare("SELECT * FROM cargas_masivas WHERE id = ?");
    $stmt->execute([$cargaId]);
    $carga = $stmt->fetch();

    if (!$carga) {
        http_response_code(404);
        echo json_encode(['error' => 'Carga no encontrada']);
        return;
    }

    // Obtener errores si los hay
    $stmt = $pdo->prepare("SELECT fila_numero, mensaje_error FROM cargas_errores WHERE carga_id = ? ORDER BY fila_numero LIMIT 100");
    $stmt->execute([$cargaId]);
    $errores = $stmt->fetchAll();

    $porcentaje = $carga['total_registros'] > 0 
        ? round(($carga['registros_procesados'] / $carga['total_registros']) * 100, 1) 
        : 0;

    echo json_encode([
        'id' => $carga['id'],
        'archivo' => $carga['nombre_archivo'],
        'estado' => $carga['estado'],
        'total_registros' => $carga['total_registros'],
        'registros_procesados' => $carga['registros_procesados'],
        'registros_exitosos' => $carga['registros_exitosos'],
        'registros_fallidos' => $carga['registros_fallidos'],
        'porcentaje' => $porcentaje,
        'errores' => $errores
    ]);
}

// ============================================================================
// CANCELAR PROCESO
// ============================================================================
function cancelProcess() {
    $cargaId = $_POST['carga_id'] ?? 0;
    
    if (!$cargaId) {
        http_response_code(400);
        echo json_encode(['error' => 'ID de carga no proporcionado']);
        return;
    }

    $pdo = connectDB();
    $stmt = $pdo->prepare("UPDATE cargas_masivas SET estado = 'cancelado' WHERE id = ?");
    $stmt->execute([$cargaId]);

    echo json_encode(['success' => true, 'message' => 'Proceso cancelado']);
}

// ============================================================================
// FUNCIONES AUXILIARES
// ============================================================================

function countFileRows($filePath, $ext) {
    if ($ext === 'csv') {
        $count = 0;
        $handle = fopen($filePath, 'r');
        while (fgets($handle) !== false) $count++;
        fclose($handle);
        return max(0, $count - 1); // Restar header
    } else {
        // Usar PhpSpreadsheet para contar filas en Excel
        return countExcelRows($filePath);
    }
}

function countExcelRows($filePath) {
    try {
        $spreadsheet = IOFactory::load($filePath);
        $worksheet = $spreadsheet->getActiveSheet();
        $highestRow = $worksheet->getHighestRow();
        $spreadsheet->disconnectWorksheets();
        unset($spreadsheet);
        return max(0, $highestRow - 1); // Restar header
    } catch (Exception $e) {
        return 0;
    }
}

function readFileChunk($filePath, $ext, $offset, $limit) {
    $rows = [];
    
    if ($ext === 'csv') {
        $handle = fopen($filePath, 'r');
        $lineNumber = 0;
        $headers = [];
        
        while (($data = fgetcsv($handle, 0, ',')) !== false) {
            if ($lineNumber === 0) {
                $headers = $data;
                $lineNumber++;
                continue;
            }
            
            if ($lineNumber > $offset && $lineNumber <= $offset + $limit) {
                $row = [];
                foreach ($headers as $i => $header) {
                    $row[$header] = $data[$i] ?? '';
                }
                $rows[] = $row;
            }
            
            if ($lineNumber > $offset + $limit) break;
            $lineNumber++;
        }
        fclose($handle);
    } else {
        // Usar PhpSpreadsheet para Excel
        $rows = readExcelChunk($filePath, $offset, $limit);
    }
    
    return $rows;
}

function readExcelChunk($filePath, $offset, $limit) {
    $rows = [];
    
    try {
        $spreadsheet = IOFactory::load($filePath);
        $worksheet = $spreadsheet->getActiveSheet();
        
        // Obtener headers (primera fila)
        $headers = [];
        $highestColumn = $worksheet->getHighestColumn();
        $highestColumnIndex = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::columnIndexFromString($highestColumn);
        
        for ($col = 1; $col <= $highestColumnIndex; $col++) {
            $colLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($col);
            $headers[$col] = $worksheet->getCell($colLetter . '1')->getValue() ?? "col_$col";
        }
        
        // Leer filas del chunk
        $startRow = $offset + 2; // +2 porque la fila 1 es el header y el offset empieza en 0
        $endRow = $startRow + $limit - 1;
        $highestRow = $worksheet->getHighestRow();
        
        for ($row = $startRow; $row <= min($endRow, $highestRow); $row++) {
            $rowData = [];
            for ($col = 1; $col <= $highestColumnIndex; $col++) {
                $colLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($col);
                $cellValue = $worksheet->getCell($colLetter . $row)->getValue();
                $headerName = $headers[$col];
                $rowData[$headerName] = $cellValue;
            }
            $rows[] = $rowData;
        }
        
        $spreadsheet->disconnectWorksheets();
        unset($spreadsheet);
        
    } catch (Exception $e) {
        error_log("Error leyendo Excel: " . $e->getMessage());
    }
    
    return $rows;
}

function insertRow($pdo, $row, $cargaId, $rowNumber) {
    try {
        // Mapear columnas del Excel a la BD (soporta múltiples formatos de nombres)
        $idPredio = trim(
            $row['ID_1'] ?? $row['#ID'] ?? $row['ID_PREDIO'] ?? $row['id_predio'] ?? 
            $row['Id_1'] ?? $row['id_1'] ?? $row['PREDIO'] ?? ''
        );
        $idContribuyente = trim(
            $row['ID_2'] ?? $row['#ID.1'] ?? $row['ID_CONTRIBUYENTE'] ?? $row['id_contribuyente'] ?? 
            $row['Id_2'] ?? $row['id_2'] ?? $row['CEDULA'] ?? $row['NIT'] ?? ''
        );
        $razonSocial = trim(
            $row['Razon_Social'] ?? $row['Razón Social'] ?? $row['RAZON_SOCIAL'] ?? 
            $row['razon_social'] ?? $row['RazonSocial'] ?? $row['NOMBRE'] ?? ''
        );
        $noActo = trim(
            $row['No_Acto_Administrativo'] ?? $row['No del Acto Administrativo'] ?? 
            $row['NO_ACTO'] ?? $row['no_acto_administrativo'] ?? $row['NoActo'] ?? ''
        );
        $fechaActo = parseDate(
            $row['Fecha_Acto_Administrativo'] ?? $row['Fecha del acto Administrativo'] ?? 
            $row['FECHA_ACTO'] ?? $row['fecha_acto'] ?? $row['FechaActo'] ?? ''
        );
        $fechaPublicacion = parseDate(
            $row['Fecha_Publicacion'] ?? $row['Fecha Publicación'] ?? 
            $row['FECHA_PUBLICACION'] ?? $row['fecha_publicacion'] ?? $row['FechaPublicacion'] ?? ''
        );
        $tipoActuacion = trim(
            $row['Tipo_Actuacion'] ?? $row['Tipo de Actuación'] ?? 
            $row['TIPO_ACTUACION'] ?? $row['tipo_actuacion'] ?? $row['TipoActuacion'] ?? ''
        );
        $organismo = trim(
            $row['Organismo'] ?? $row['ORGANISMO'] ?? $row['organismo'] ?? 
            'DEPARTAMENTO ADMINISTRATIVO DE HACIENDA'
        );
        $area = trim(
            $row['Area'] ?? $row['Área'] ?? $row['AREA'] ?? $row['area'] ?? 
            $row['Dependencia'] ?? $row['DEPENDENCIA'] ?? ''
        );
        $fechaDesfijacion = parseDate(
            $row['Fecha_Desfijacion'] ?? $row['Fecha Desfijación'] ?? 
            $row['FECHA_DESFIJACION'] ?? $row['fecha_desfijacion'] ?? $row['FechaDesfijacion'] ?? ''
        );
        
        // Datos adicionales en JSON
        $masDatos = [];
        $jsonField = $row['Mas_Datos'] ?? $row['mas datos'] ?? $row['MAS_DATOS'] ?? $row['mas_datos'] ?? '';
        if (!empty($jsonField)) {
            $masDatos = json_decode($jsonField, true) ?: [];
        }
        // Agregar campos adicionales si existen como columnas separadas
        if (isset($row['ciudad']) || isset($row['CIUDAD']) || isset($row['Ciudad'])) {
            $masDatos['ciudad'] = $row['ciudad'] ?? $row['CIUDAD'] ?? $row['Ciudad'] ?? '';
        }
        if (isset($row['departamento']) || isset($row['DEPARTAMENTO']) || isset($row['Departamento'])) {
            $masDatos['departamento'] = $row['departamento'] ?? $row['DEPARTAMENTO'] ?? $row['Departamento'] ?? '';
        }
        if (isset($row['pais']) || isset($row['PAIS']) || isset($row['Pais'])) {
            $masDatos['pais'] = $row['pais'] ?? $row['PAIS'] ?? $row['Pais'] ?? 'Colombia';
        }

        // Validaciones
        if (empty($idContribuyente)) {
            throw new Exception('ID del contribuyente es requerido');
        }
        if (empty($razonSocial)) {
            throw new Exception('Razón Social es requerida');
        }
        if (empty($noActo)) {
            throw new Exception('Número de acto es requerido');
        }

        // Determinar estado basado en fecha de desfijación
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
        // Guardar error en log
        $stmtError = $pdo->prepare("INSERT INTO cargas_errores (carga_id, fila_numero, datos_fila, mensaje_error) VALUES (?, ?, ?, ?)");
        $stmtError->execute([$cargaId, $rowNumber, json_encode($row), $e->getMessage()]);
        
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

function parseDate($dateString) {
    if (empty($dateString)) return null;
    
    // Intentar varios formatos
    $formats = ['Y-m-d', 'd/m/Y', 'm/d/Y', 'Y/m/d', 'Ymd'];
    
    foreach ($formats as $format) {
        $date = DateTime::createFromFormat($format, trim($dateString));
        if ($date) {
            return $date->format('Y-m-d');
        }
    }
    
    // Intentar con strtotime
    $timestamp = strtotime($dateString);
    if ($timestamp) {
        return date('Y-m-d', $timestamp);
    }
    
    return null;
}
?>

