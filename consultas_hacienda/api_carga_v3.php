<?php
// api_carga_v3.php - API para carga masiva con auditoría y soft delete
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
        uploadExcel($excelDir, $pdfDir, $username);
        break;
    case 'list_pending':
        listPendingFiles($excelDir, $pdfDir);
        break;
    case 'verify_pdfs':
        verifyPdfs($excelDir, $pdfDir);
        break;
    case 'process_file':
        processFile($excelDir, $pdfDir, $processedDir, $username);
        break;
    case 'delete_excel':
        deleteExcelFile($excelDir, $username);
        break;
    case 'get_user_info':
        getUserInfo($userDir, $excelDir, $pdfDir, $processedDir);
        break;
    // Nuevas acciones para gestión de registros
    case 'list_registros':
        listRegistros($username);
        break;
    case 'deactivate_registro':
        deactivateRegistro($username);
        break;
    case 'restore_registro':
        restoreRegistro($username);
        break;
    case 'get_auditoria':
        getAuditoria($username);
        break;
    default:
        echo json_encode(['error' => 'Acción no válida']);
}

// ============================================================================
// SANITIZAR USERNAME
// ============================================================================
function sanitizeUsername($username) {
    $username = preg_replace('/[^a-zA-Z0-9._-]/', '', $username);
    if (in_array($username, ['.', '..', ''])) {
        return '';
    }
    return $username;
}

// ============================================================================
// GENERAR ID DE REGISTRO ÚNICO
// Formato: AA-YYYYMMDD-HHMMSS-XXXX (donde XXXX es un número aleatorio)
// ============================================================================
function generarIdRegistro() {
    $fecha = date('Ymd-His');
    $random = str_pad(mt_rand(0, 9999), 4, '0', STR_PAD_LEFT);
    return "AA-{$fecha}-{$random}";
}

// ============================================================================
// REGISTRAR AUDITORÍA
// ============================================================================
function registrarAuditoria($pdo, $usuario, $accion, $archivoExcel = null, $idRegistro = null, $detalles = [], $resultado = 'exito', $mensaje = null) {
    try {
        $sql = "INSERT INTO auditoria_cargas 
                (usuario, accion, archivo_excel, id_registro, detalles, resultado, mensaje, ip_address, user_agent)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            $usuario,
            $accion,
            $archivoExcel,
            $idRegistro,
            json_encode($detalles),
            $resultado,
            $mensaje,
            $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            $_SERVER['HTTP_USER_AGENT'] ?? 'unknown'
        ]);
        
        return true;
    } catch (Exception $e) {
        error_log("Error en auditoría: " . $e->getMessage());
        return false;
    }
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
function uploadExcel($excelDir, $pdfDir, $username) {
    $pdo = connectDB();
    
    if (!isset($_FILES['archivo'])) {
        registrarAuditoria($pdo, $username, 'upload_excel_error', null, null, [], 'error', 'No se recibió ningún archivo');
        echo json_encode(['success' => false, 'error' => 'No se recibió ningún archivo']);
        return;
    }

    $file = $_FILES['archivo'];
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

    if (!in_array($ext, ['xlsx', 'xls', 'csv'])) {
        registrarAuditoria($pdo, $username, 'upload_excel_error', $file['name'], null, ['extension' => $ext], 'error', 'Formato no permitido');
        echo json_encode(['success' => false, 'error' => 'Formato no permitido']);
        return;
    }

    $originalName = pathinfo($file['name'], PATHINFO_FILENAME);
    $timestamp = date('Y-m-d_H-i-s');
    $fileName = $originalName . '_' . $timestamp . '.' . $ext;
    $filePath = $excelDir . $fileName;

    if (move_uploaded_file($file['tmp_name'], $filePath)) {
        $verificacion = verificarPdfsDelArchivo($filePath, $pdfDir);
        
        registrarAuditoria($pdo, $username, 'upload_excel', $fileName, null, [
            'total_registros' => $verificacion['total'],
            'con_pdf' => $verificacion['con_pdf'],
            'sin_pdf' => $verificacion['sin_pdf']
        ], 'exito', 'Archivo subido correctamente');
        
        echo json_encode([
            'success' => true,
            'message' => 'Archivo subido correctamente',
            'filename' => $fileName,
            'verificacion' => $verificacion
        ]);
    } else {
        registrarAuditoria($pdo, $username, 'upload_excel_error', $file['name'], null, [], 'error', 'Error al guardar el archivo');
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
// Ahora lee la columna "Nombre_PDF" del Excel
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
        
        // Leer headers
        $headers = [];
        for ($col = 1; $col <= $highestColumnIndex; $col++) {
            $colLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($col);
            $headers[$col] = trim($worksheet->getCell($colLetter . '1')->getValue() ?? "col_$col");
        }
        
        // Buscar la columna del nombre del PDF
        $pdfColumnIndex = null;
        $pdfColumnNames = ['Nombre_PDF', 'nombre_pdf', 'PDF', 'pdf', 'Archivo_PDF', 'archivo_pdf', 'NombrePDF'];
        
        foreach ($headers as $idx => $header) {
            if (in_array(trim($header), $pdfColumnNames)) {
                $pdfColumnIndex = $idx;
                break;
            }
        }
        
        // PDFs disponibles
        $pdfFiles = glob($pdfDir . '*.pdf');
        $pdfDisponibles = [];
        foreach ($pdfFiles as $pdf) {
            // Guardamos con y sin extensión para flexibilidad
            $nombreSinExt = strtolower(pathinfo(basename($pdf), PATHINFO_FILENAME));
            $nombreConExt = strtolower(basename($pdf));
            $pdfDisponibles[$nombreSinExt] = basename($pdf);
            $pdfDisponibles[$nombreConExt] = basename($pdf);
        }
        
        // Procesar filas
        for ($row = 2; $row <= $highestRow; $row++) {
            $rowData = [];
            for ($col = 1; $col <= $highestColumnIndex; $col++) {
                $colLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($col);
                $rowData[$headers[$col]] = $worksheet->getCell($colLetter . $row)->getValue();
            }
            
            // Obtener nombre del PDF de la columna específica o del No_Acto
            $nombrePdf = '';
            if ($pdfColumnIndex !== null) {
                $colLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($pdfColumnIndex);
                $nombrePdf = trim($worksheet->getCell($colLetter . $row)->getValue() ?? '');
            }
            
            // Si no hay columna de PDF, usar No_Acto_Administrativo como antes
            if (empty($nombrePdf)) {
                $nombrePdf = trim($rowData['No_Acto_Administrativo'] ?? $rowData['No del Acto Administrativo'] ?? $rowData['NO_ACTO'] ?? $rowData['no_acto_administrativo'] ?? '');
            }
            
            if (empty($nombrePdf)) {
                continue;
            }
            
            $resultado['total']++;
            
            // Buscar el PDF (con o sin extensión)
            $pdfBuscado = strtolower($nombrePdf);
            $pdfBuscadoSinExt = strtolower(pathinfo($nombrePdf, PATHINFO_FILENAME));
            
            $pdfEncontrado = isset($pdfDisponibles[$pdfBuscado]) || isset($pdfDisponibles[$pdfBuscadoSinExt]);
            $pdfNombreReal = $pdfDisponibles[$pdfBuscado] ?? $pdfDisponibles[$pdfBuscadoSinExt] ?? null;
            
            if ($pdfEncontrado) {
                $resultado['con_pdf']++;
                $resultado['completos'][] = [
                    'fila' => $row,
                    'nombre_pdf_excel' => $nombrePdf,
                    'pdf_encontrado' => $pdfNombreReal
                ];
            } else {
                $resultado['sin_pdf']++;
                $pdfEsperado = pathinfo($nombrePdf, PATHINFO_EXTENSION) === 'pdf' ? $nombrePdf : $nombrePdf . '.pdf';
                $resultado['faltantes'][] = [
                    'fila' => $row,
                    'nombre_pdf_excel' => $nombrePdf,
                    'pdf_esperado' => $pdfEsperado
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
function processFile($excelDir, $pdfDir, $processedDir, $username) {
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
        
        // ⭐ Obtener datos del usuario (organismo y área)
        $sqlUser = "SELECT organismo, area FROM usuarios WHERE username = ? AND activo = 1";
        $stmtUser = $pdo->prepare($sqlUser);
        $stmtUser->execute([$username]);
        $userData = $stmtUser->fetch(PDO::FETCH_ASSOC);
        
        if (!$userData) {
            echo json_encode(['success' => false, 'error' => 'Usuario no encontrado o inactivo']);
            return;
        }
        
        $organismoUsuario = $userData['organismo'];
        $areaUsuario = $userData['area'];
        
        // Registrar inicio del procesamiento
        registrarAuditoria($pdo, $username, 'procesar_inicio', $excelFile, null, [
            'organismo' => $organismoUsuario,
            'area' => $areaUsuario
        ], 'exito', 'Iniciando procesamiento');
        
        $spreadsheet = IOFactory::load($excelPath);
        $worksheet = $spreadsheet->getActiveSheet();
        $highestRow = $worksheet->getHighestRow();
        $highestColumn = $worksheet->getHighestColumn();
        $highestColumnIndex = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::columnIndexFromString($highestColumn);
        
        // Leer headers
        $headers = [];
        for ($col = 1; $col <= $highestColumnIndex; $col++) {
            $colLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($col);
            $headers[$col] = trim($worksheet->getCell($colLetter . '1')->getValue() ?? "col_$col");
        }
        
        // Buscar columna de PDF
        $pdfColumnIndex = null;
        $pdfColumnNames = ['Nombre_PDF', 'nombre_pdf', 'PDF', 'pdf', 'Archivo_PDF', 'archivo_pdf', 'NombrePDF'];
        foreach ($headers as $idx => $header) {
            if (in_array(trim($header), $pdfColumnNames)) {
                $pdfColumnIndex = $idx;
                break;
            }
        }
        
        // PDFs disponibles
        $pdfFiles = glob($pdfDir . '*.pdf');
        $pdfDisponibles = [];
        foreach ($pdfFiles as $pdf) {
            $nombreSinExt = strtolower(pathinfo(basename($pdf), PATHINFO_FILENAME));
            $nombreConExt = strtolower(basename($pdf));
            $pdfDisponibles[$nombreSinExt] = $pdf;
            $pdfDisponibles[$nombreConExt] = $pdf;
        }
        
        $insertados = 0;
        $sinPdf = 0;
        $errores = 0;
        $erroresDetalle = [];
        $sinPdfDetalle = [];
        $pdfsMovidos = [];
        $registrosInsertados = [];
        
        $timestampProceso = date('Ymd_His');
        
        for ($row = 2; $row <= $highestRow; $row++) {
            $rowData = [];
            for ($col = 1; $col <= $highestColumnIndex; $col++) {
                $colLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($col);
                $rowData[$headers[$col]] = $worksheet->getCell($colLetter . $row)->getValue();
            }
            
            // Obtener nombre del PDF
            $nombrePdfOriginal = '';
            if ($pdfColumnIndex !== null) {
                $colLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($pdfColumnIndex);
                $nombrePdfOriginal = trim($worksheet->getCell($colLetter . $row)->getValue() ?? '');
            }
            
            if (empty($nombrePdfOriginal)) {
                $nombrePdfOriginal = trim($rowData['No_Acto_Administrativo'] ?? $rowData['No del Acto Administrativo'] ?? '');
            }
            
            if (empty($nombrePdfOriginal)) {
                continue;
            }
            
            // Buscar el PDF
            $pdfBuscado = strtolower($nombrePdfOriginal);
            $pdfBuscadoSinExt = strtolower(pathinfo($nombrePdfOriginal, PATHINFO_FILENAME));
            
            $pdfPathOriginal = $pdfDisponibles[$pdfBuscado] ?? $pdfDisponibles[$pdfBuscadoSinExt] ?? null;
            
            if (!$pdfPathOriginal) {
                $sinPdf++;
                $sinPdfDetalle[] = [
                    'fila' => $row,
                    'nombre_pdf' => $nombrePdfOriginal,
                    'pdf_esperado' => $nombrePdfOriginal . '.pdf'
                ];
                continue;
            }
            
            // Generar ID único para el registro
            $idRegistro = generarIdRegistro();
            
            // Generar nombre final para el PDF (con timestamp para evitar duplicados)
            $pdfExtension = pathinfo($pdfPathOriginal, PATHINFO_EXTENSION);
            $pdfNombreFinal = pathinfo($nombrePdfOriginal, PATHINFO_FILENAME) . '_' . $timestampProceso . '_' . str_pad($row, 4, '0', STR_PAD_LEFT) . '.' . $pdfExtension;
            $pdfRutaFinal = $processedDir . $pdfNombreFinal;
            
            // Insertar en la base de datos (organismo y área vienen del usuario)
            $result = insertRow($pdo, $rowData, $row, $idRegistro, $username, $excelFile, $nombrePdfOriginal, $pdfNombreFinal, $organismoUsuario, $areaUsuario);
            
            if ($result['success']) {
                $insertados++;
                
                // Mover y renombrar el PDF
                if (!isset($pdfsMovidos[$pdfPathOriginal])) {
                    copy($pdfPathOriginal, $pdfRutaFinal);
                    $pdfsMovidos[$pdfPathOriginal] = true;
                }
                
                $registrosInsertados[] = $idRegistro;
            } else {
                $errores++;
                $erroresDetalle[] = "Fila $row: " . $result['error'];
            }
        }
        
        $spreadsheet->disconnectWorksheets();
        unset($spreadsheet);
        
        // Mover Excel a procesados
        $timestamp = date('Y-m-d_H-i-s');
        rename($excelPath, $processedDir . pathinfo($excelFile, PATHINFO_FILENAME) . '_procesado_' . $timestamp . '.' . pathinfo($excelFile, PATHINFO_EXTENSION));
        
        // Eliminar PDFs originales que fueron procesados
        foreach ($pdfsMovidos as $pdfPath => $v) {
            if (file_exists($pdfPath)) {
                unlink($pdfPath);
            }
        }
        
        // Determinar resultado
        $resultadoAuditoria = 'exito';
        $accionAuditoria = 'procesar_exito';
        if ($errores > 0 || $sinPdf > 0) {
            $resultadoAuditoria = 'parcial';
            $accionAuditoria = 'procesar_parcial';
        }
        if ($insertados === 0) {
            $resultadoAuditoria = 'error';
            $accionAuditoria = 'procesar_error';
        }
        
        // Registrar en auditoría
        registrarAuditoria($pdo, $username, $accionAuditoria, $excelFile, null, [
            'insertados' => $insertados,
            'sin_pdf' => $sinPdf,
            'errores' => $errores,
            'registros' => $registrosInsertados
        ], $resultadoAuditoria, "Procesamiento completado: $insertados insertados, $sinPdf sin PDF, $errores errores");
        
        echo json_encode([
            'success' => true,
            'insertados' => $insertados,
            'sin_pdf' => $sinPdf,
            'errores' => $errores,
            'sin_pdf_detalle' => array_slice($sinPdfDetalle, 0, 20),
            'errores_detalle' => array_slice($erroresDetalle, 0, 10),
            'registros_insertados' => $registrosInsertados
        ]);
        
    } catch (Exception $e) {
        if (isset($pdo)) {
            registrarAuditoria($pdo, $username, 'procesar_error', $excelFile, null, [], 'error', $e->getMessage());
        }
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
}

// ============================================================================
// LISTAR REGISTROS CARGADOS POR EL USUARIO
// ============================================================================
function listRegistros($username) {
    try {
        $pdo = connectDB();
        
        $mostrarInactivos = $_GET['incluir_inactivos'] ?? '0';
        
        $sql = "SELECT id_registro, id_predio, id_contribuyente, razon_social, 
                       no_acto_administrativo, fecha_publicacion, tipo_actuacion,
                       pdf_nombre_original, estado, activo, created_at, deleted_at
                FROM actos_administrativos 
                WHERE usuario_carga = ?";
        
        if ($mostrarInactivos !== '1') {
            $sql .= " AND activo = 1";
        }
        
        $sql .= " ORDER BY created_at DESC";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$username]);
        
        $registros = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode([
            'success' => true,
            'total' => count($registros),
            'registros' => $registros
        ]);
        
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
}

// ============================================================================
// DESACTIVAR REGISTRO (SOFT DELETE)
// ============================================================================
function deactivateRegistro($username) {
    try {
        $idRegistro = $_POST['id_registro'] ?? '';
        $motivo = $_POST['motivo'] ?? 'Sin motivo especificado';

        if (empty($idRegistro)) {
            echo json_encode(['success' => false, 'error' => 'ID de registro no proporcionado']);
            return;
        }

        $pdo = connectDB();

        // Verificar permisos del usuario para eliminar
        $sqlPermisos = "SELECT rol, permisos_eliminacion, organismo FROM usuarios WHERE username = ? AND activo = 1";
        $stmtPermisos = $pdo->prepare($sqlPermisos);
        $stmtPermisos->execute([$username]);
        $permisos = $stmtPermisos->fetch(PDO::FETCH_ASSOC);

        if (!$permisos) {
            echo json_encode(['success' => false, 'error' => 'Usuario no encontrado']);
            return;
        }

        // Los admin_general y admin_dependencia SIEMPRE pueden eliminar registros
        // Los demás usuarios solo si tienen permisos_eliminacion = 1
        $esAdmin = in_array($permisos['rol'], ['admin_general', 'admin_dependencia']);
        $tienePermiso = $permisos['permisos_eliminacion'] == 1;

        if (!$esAdmin && !$tienePermiso) {
            echo json_encode(['success' => false, 'error' => 'No tienes permisos para eliminar registros']);
            return;
        }
        
        // Verificar que el registro existe y pertenece al usuario o su organismo
        if ($permisos['rol'] === 'admin_general') {
            // Admin general puede eliminar cualquier registro
            $sqlCheck = "SELECT id_registro, activo FROM actos_administrativos WHERE id_registro = ?";
            $stmtCheck = $pdo->prepare($sqlCheck);
            $stmtCheck->execute([$idRegistro]);
        } elseif ($permisos['rol'] === 'admin_dependencia') {
            // Admin de dependencia puede eliminar registros de su organismo
            $sqlCheck = "SELECT id_registro, activo FROM actos_administrativos WHERE id_registro = ? AND organismo = ?";
            $stmtCheck = $pdo->prepare($sqlCheck);
            $stmtCheck->execute([$idRegistro, $permisos['organismo']]);
        } else {
            // Usuarios normales solo pueden eliminar sus propios registros
            $sqlCheck = "SELECT id_registro, activo FROM actos_administrativos WHERE id_registro = ? AND usuario_carga = ?";
            $stmtCheck = $pdo->prepare($sqlCheck);
            $stmtCheck->execute([$idRegistro, $username]);
        }
        $registro = $stmtCheck->fetch(PDO::FETCH_ASSOC);
        
        if (!$registro) {
            echo json_encode(['success' => false, 'error' => 'Registro no encontrado o no tienes permisos']);
            return;
        }
        
        if ($registro['activo'] == 0) {
            echo json_encode(['success' => false, 'error' => 'El registro ya está desactivado']);
            return;
        }
        
        // Desactivar el registro
        $sql = "UPDATE actos_administrativos SET activo = 0, deleted_at = NOW() WHERE id_registro = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$idRegistro]);
        
        // Registrar en auditoría
        registrarAuditoria($pdo, $username, 'borrar_registro', null, $idRegistro, [
            'motivo' => $motivo
        ], 'exito', "Registro $idRegistro desactivado: $motivo");
        
        echo json_encode([
            'success' => true,
            'message' => 'Registro desactivado correctamente'
        ]);
        
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
}

// ============================================================================
// RESTAURAR REGISTRO
// ============================================================================
function restoreRegistro($username) {
    try {
        $idRegistro = $_POST['id_registro'] ?? '';
        
        if (empty($idRegistro)) {
            echo json_encode(['success' => false, 'error' => 'ID de registro no proporcionado']);
            return;
        }
        
        $pdo = connectDB();

        // Obtener rol y organismo del usuario
        $sqlUser = "SELECT rol, organismo, permisos_eliminacion FROM usuarios WHERE username = ? AND activo = 1";
        $stmtUser = $pdo->prepare($sqlUser);
        $stmtUser->execute([$username]);
        $userData = $stmtUser->fetch(PDO::FETCH_ASSOC);

        if (!$userData) {
            echo json_encode(['success' => false, 'error' => 'Usuario no encontrado']);
            return;
        }

        // Los admin_general y admin_dependencia SIEMPRE pueden restaurar registros
        $esAdmin = in_array($userData['rol'], ['admin_general', 'admin_dependencia']);
        $tienePermiso = $userData['permisos_eliminacion'] == 1;

        if (!$esAdmin && !$tienePermiso) {
            echo json_encode(['success' => false, 'error' => 'No tienes permisos para restaurar registros']);
            return;
        }
        
        // Verificar que el registro existe y pertenece al usuario o su organismo
        if ($userData['rol'] === 'admin_general') {
            // Admin general puede restaurar cualquier registro
            $sqlCheck = "SELECT id_registro, activo FROM actos_administrativos WHERE id_registro = ?";
            $stmtCheck = $pdo->prepare($sqlCheck);
            $stmtCheck->execute([$idRegistro]);
        } elseif ($userData['rol'] === 'admin_dependencia') {
            // Admin de dependencia puede restaurar registros de su organismo
            $sqlCheck = "SELECT id_registro, activo FROM actos_administrativos WHERE id_registro = ? AND organismo = ?";
            $stmtCheck = $pdo->prepare($sqlCheck);
            $stmtCheck->execute([$idRegistro, $userData['organismo']]);
        } else {
            // Usuarios normales solo pueden restaurar sus propios registros
            $sqlCheck = "SELECT id_registro, activo FROM actos_administrativos WHERE id_registro = ? AND usuario_carga = ?";
            $stmtCheck = $pdo->prepare($sqlCheck);
            $stmtCheck->execute([$idRegistro, $username]);
        }
        $registro = $stmtCheck->fetch(PDO::FETCH_ASSOC);
        
        if (!$registro) {
            echo json_encode(['success' => false, 'error' => 'Registro no encontrado o no tienes permisos']);
            return;
        }
        
        if ($registro['activo'] == 1) {
            echo json_encode(['success' => false, 'error' => 'El registro ya está activo']);
            return;
        }
        
        // Restaurar el registro
        $sql = "UPDATE actos_administrativos SET activo = 1, deleted_at = NULL WHERE id_registro = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$idRegistro]);
        
        // Registrar en auditoría
        registrarAuditoria($pdo, $username, 'restaurar_registro', null, $idRegistro, [], 'exito', "Registro $idRegistro restaurado");
        
        echo json_encode([
            'success' => true,
            'message' => 'Registro restaurado correctamente'
        ]);
        
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
}

// ============================================================================
// OBTENER AUDITORÍA (con filtros por dependencia)
// ============================================================================
function getAuditoria($username) {
    try {
        $pdo = connectDB();

        // Obtener rol y organismo del usuario actual
        $sqlUser = "SELECT rol, organismo FROM usuarios WHERE username = ? AND activo = 1";
        $stmtUser = $pdo->prepare($sqlUser);
        $stmtUser->execute([$username]);
        $userData = $stmtUser->fetch(PDO::FETCH_ASSOC);

        if (!$userData) {
            echo json_encode(['success' => false, 'error' => 'Usuario no encontrado']);
            return;
        }

        $rol = $userData['rol'];
        $organismo = $userData['organismo'];

        $limit = intval($_GET['limit'] ?? 50);
        $limit = min($limit, 200);

        $filtroUsuario = $_GET['filtro_usuario'] ?? '';
        $filtroAccion = $_GET['filtro_accion'] ?? '';

        // Construir consulta según permisos
        $whereConditions = [];
        $params = [];

        if ($rol === 'admin_general') {
            // Admin general ve todo
        } elseif ($rol === 'admin_dependencia') {
            // Admin de dependencia ve usuarios de su organismo
            $whereConditions[] = "u.organismo = ?";
            $params[] = $organismo;
        } else {
            // Operador solo ve su propia actividad
            $whereConditions[] = "a.usuario = ?";
            $params[] = $username;
        }

        // Filtros adicionales
        if (!empty($filtroUsuario)) {
            $whereConditions[] = "a.usuario LIKE ?";
            $params[] = "%$filtroUsuario%";
        }

        if (!empty($filtroAccion)) {
            $whereConditions[] = "a.accion = ?";
            $params[] = $filtroAccion;
        }

        $whereClause = !empty($whereConditions) ? "WHERE " . implode(" AND ", $whereConditions) : "";

        $sql = "SELECT a.id, a.accion, a.archivo_excel, a.id_registro, a.detalles,
                       a.resultado, a.mensaje, a.created_at, a.usuario,
                       u.nombre_completo, u.organismo, u.area
                FROM auditoria_cargas a
                LEFT JOIN usuarios u ON a.usuario = u.username
                $whereClause
                ORDER BY a.created_at DESC
                LIMIT ?";

        $params[] = $limit;

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);

        $auditoria = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Parsear detalles JSON y agregar info del usuario
        foreach ($auditoria as &$registro) {
            $registro['detalles'] = json_decode($registro['detalles'], true) ?? [];
        }

        // Obtener estadísticas para filtros
        $stats = getAuditoriaStats($pdo, $rol, $organismo, $username);

        echo json_encode([
            'success' => true,
            'total' => count($auditoria),
            'auditoria' => $auditoria,
            'stats' => $stats,
            'permisos' => [
                'puede_filtrar_usuarios' => in_array($rol, ['admin_general', 'admin_dependencia']),
                'puede_ver_todo' => $rol === 'admin_general'
            ]
        ]);

    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
}

// ============================================================================
// OBTENER ESTADÍSTICAS DE AUDITORÍA PARA FILTROS
// ============================================================================
function getAuditoriaStats($pdo, $rol, $organismo, $username) {
    $stats = [];

    // Usuarios disponibles para filtro
    if ($rol === 'admin_general') {
        $sqlUsers = "SELECT username, nombre_completo FROM usuarios WHERE activo = 1 ORDER BY nombre_completo";
    } elseif ($rol === 'admin_dependencia') {
        $sqlUsers = "SELECT username, nombre_completo FROM usuarios WHERE organismo = ? AND activo = 1 ORDER BY nombre_completo";
        $stmtUsers = $pdo->prepare($sqlUsers);
        $stmtUsers->execute([$organismo]);
        $stats['usuarios'] = $stmtUsers->fetchAll(PDO::FETCH_ASSOC);
    } else {
        $stats['usuarios'] = []; // Operador no puede filtrar por otros usuarios
    }

    // Tipos de acciones disponibles
    $stats['acciones'] = [
        'upload_excel' => '📤 Subir Excel',
        'upload_excel_error' => '❌ Error Subida',
        'procesar_inicio' => '⚙️ Inicio Proceso',
        'procesar_exito' => '✅ Proceso Exitoso',
        'procesar_parcial' => '⚠️ Proceso Parcial',
        'procesar_error' => '❌ Error Proceso',
        'borrar_registro' => '🗑️ Eliminar Registro',
        'restaurar_registro' => '♻️ Restaurar Registro',
        'eliminar_excel' => '🗑️ Eliminar Excel',
        'login' => '🔐 Login',
        'logout' => '🚪 Logout'
    ];

    return $stats;
}

// ============================================================================
// ELIMINAR ARCHIVO EXCEL PENDIENTE
// ============================================================================
function deleteExcelFile($excelDir, $username) {
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
    
    $pdo = connectDB();
    
    if (unlink($filePath)) {
        registrarAuditoria($pdo, $username, 'eliminar_excel', $filename, null, [], 'exito', 'Excel eliminado');
        echo json_encode(['success' => true, 'message' => 'Archivo eliminado']);
    } else {
        registrarAuditoria($pdo, $username, 'eliminar_excel', $filename, null, [], 'error', 'Error al eliminar');
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

function insertRow($pdo, $row, $rowNumber, $idRegistro, $username, $archivoOrigen, $pdfNombreOriginal, $pdfNombreFinal, $organismoUsuario, $areaUsuario) {
    try {
        // Columnas conocidas (mapeadas a campos de la BD)
        // NOTA: Organismo y Area NO se toman del Excel, vienen del usuario
        $columnasConocidas = [
            'ID_1', '#ID', 'ID_PREDIO', 'id_predio', 'Id_1',
            'ID_2', '#ID.1', 'ID_CONTRIBUYENTE', 'id_contribuyente', 'Id_2', 'CEDULA',
            'Razon_Social', 'Razón Social', 'RAZON_SOCIAL', 'razon_social',
            'No_Acto_Administrativo', 'No del Acto Administrativo', 'NO_ACTO', 'no_acto_administrativo',
            'Fecha_Acto_Administrativo', 'Fecha del acto Administrativo', 'FECHA_ACTO',
            'Fecha_Publicacion', 'Fecha Publicación', 'FECHA_PUBLICACION',
            'Tipo_Actuacion', 'Tipo de Actuación', 'TIPO_ACTUACION',
            'Fecha_Desfijacion', 'Fecha Desfijación', 'FECHA_DESFIJACION',
            'Nombre_PDF', 'nombre_pdf', 'PDF', 'pdf', 'Archivo_PDF', 'archivo_pdf', 'NombrePDF',
            'Mas_Datos', 'mas datos', 'MAS_DATOS'
            // Organismo y Area se ignoran del Excel, pero si vienen, irán a mas_datos
        ];
        
        $idPredio = trim($row['ID_1'] ?? $row['#ID'] ?? $row['ID_PREDIO'] ?? $row['id_predio'] ?? $row['Id_1'] ?? '');
        $idContribuyente = trim($row['ID_2'] ?? $row['#ID.1'] ?? $row['ID_CONTRIBUYENTE'] ?? $row['id_contribuyente'] ?? $row['Id_2'] ?? $row['CEDULA'] ?? '');
        $razonSocial = trim($row['Razon_Social'] ?? $row['Razón Social'] ?? $row['RAZON_SOCIAL'] ?? $row['razon_social'] ?? '');
        $noActo = trim($row['No_Acto_Administrativo'] ?? $row['No del Acto Administrativo'] ?? $row['NO_ACTO'] ?? $row['no_acto_administrativo'] ?? '');
        $fechaActo = parseDate($row['Fecha_Acto_Administrativo'] ?? $row['Fecha del acto Administrativo'] ?? $row['FECHA_ACTO'] ?? '');
        $fechaPublicacion = parseDate($row['Fecha_Publicacion'] ?? $row['Fecha Publicación'] ?? $row['FECHA_PUBLICACION'] ?? '');
        $tipoActuacion = trim($row['Tipo_Actuacion'] ?? $row['Tipo de Actuación'] ?? $row['TIPO_ACTUACION'] ?? '');
        
        // ⭐ ORGANISMO Y ÁREA VIENEN DEL USUARIO, NO DEL EXCEL
        $organismo = $organismoUsuario;
        $area = $areaUsuario;
        $fechaDesfijacion = parseDate($row['Fecha_Desfijacion'] ?? $row['Fecha Desfijación'] ?? $row['FECHA_DESFIJACION'] ?? '');
        
        // Inicializar mas_datos con el campo JSON si existe
        $masDatos = [];
        $jsonField = $row['Mas_Datos'] ?? $row['mas datos'] ?? $row['MAS_DATOS'] ?? '';
        if (!empty($jsonField)) {
            $masDatos = json_decode($jsonField, true) ?: [];
        }
        
        // ⭐ CAPTURAR COLUMNAS EXTRA (no mapeadas)
        foreach ($row as $columna => $valor) {
            // Si la columna NO está en la lista de conocidas Y tiene valor
            if (!in_array($columna, $columnasConocidas) && !empty(trim($valor ?? ''))) {
                // Limpiar el nombre de la columna para usarlo como clave
                $claveJson = trim($columna);
                $masDatos[$claveJson] = trim($valor);
            }
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
                (id_registro, id_predio, id_contribuyente, razon_social, no_acto_administrativo, 
                 fecha_acto, fecha_publicacion, tipo_actuacion, organismo, area, 
                 fecha_desfijacion, pdf_nombre_original, pdf_nombre_final, mas_datos, 
                 estado, activo, usuario_carga, archivo_origen, fila_origen)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1, ?, ?, ?)";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            $idRegistro,
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
            $pdfNombreOriginal,
            $pdfNombreFinal,
            json_encode($masDatos),
            $estado,
            $username,
            $archivoOrigen,
            $rowNumber
        ]);

        return ['success' => true, 'id_registro' => $idRegistro];

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

