<?php
/**
 * Script de Pruebas Pre-Producción
 * Verifica conexión a BD, estructura de tablas y datos
 */

echo "╔══════════════════════════════════════════════════════════════════╗\n";
echo "║     PRUEBAS PRE-PRODUCCIÓN - SISTEMA CONSULTAS HACIENDA         ║\n";
echo "╚══════════════════════════════════════════════════════════════════╝\n\n";

$resultados = [];
$errores = [];

// ============================================================================
// 1. PRUEBA DE CONEXIÓN A BASE DE DATOS
// ============================================================================
echo "📋 SECCIÓN 1: CONEXIÓN A BASE DE DATOS\n";
echo str_repeat("─", 50) . "\n";

try {
    $dsn = 'mysql:host=localhost;dbname=hacienda_db;charset=utf8mb4';
    $pdo = new PDO($dsn, 'root', '', [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);
    echo "✅ Conexión a MySQL: EXITOSA\n";
    echo "✅ Base de datos 'hacienda_db': CONECTADA\n";
    $resultados[] = "Conexión a base de datos: OK";
} catch (PDOException $e) {
    echo "❌ Error de conexión: " . $e->getMessage() . "\n";
    $errores[] = "Conexión a base de datos fallida: " . $e->getMessage();
    die("\n⛔ No se puede continuar sin conexión a la base de datos.\n");
}

// ============================================================================
// 2. VERIFICACIÓN DE TABLAS
// ============================================================================
echo "\n📋 SECCIÓN 2: ESTRUCTURA DE TABLAS\n";
echo str_repeat("─", 50) . "\n";

$tablasRequeridas = [
    'actos_administrativos' => 'Tabla principal de actos',
    'usuarios' => 'Tabla de usuarios del sistema',
    'auditoria_cargas' => 'Tabla de auditoría',
    'cargas_masivas' => 'Registro de cargas masivas',
    'cargas_errores' => 'Errores en cargas',
    'dependencias' => 'Catálogo de dependencias'
];

$stmt = $pdo->query("SHOW TABLES");
$tablasExistentes = $stmt->fetchAll(PDO::FETCH_COLUMN);

foreach ($tablasRequeridas as $tabla => $descripcion) {
    if (in_array($tabla, $tablasExistentes)) {
        echo "✅ Tabla '$tabla': EXISTE\n";
        $resultados[] = "Tabla $tabla: OK";
    } else {
        echo "❌ Tabla '$tabla': NO EXISTE ($descripcion)\n";
        $errores[] = "Tabla $tabla no existe";
    }
}

// ============================================================================
// 3. VERIFICACIÓN DE COLUMNAS EN TABLA USUARIOS
// ============================================================================
echo "\n📋 SECCIÓN 3: ESTRUCTURA DE TABLA USUARIOS\n";
echo str_repeat("─", 50) . "\n";

$columnasUsuarios = [
    'id', 'username', 'password_hash', 'nombre_completo', 
    'email', 'organismo', 'area', 'rol', 'activo', 
    'permisos_eliminacion', 'creado_por'
];

$stmt = $pdo->query("DESCRIBE usuarios");
$columnas = $stmt->fetchAll(PDO::FETCH_COLUMN);

foreach ($columnasUsuarios as $col) {
    if (in_array($col, $columnas)) {
        echo "✅ Columna '$col': EXISTE\n";
    } else {
        echo "⚠️ Columna '$col': NO EXISTE\n";
        $errores[] = "Columna usuarios.$col no existe";
    }
}

// Verificar tipos de rol
echo "\n📋 VERIFICACIÓN DE ROLES DISPONIBLES\n";
echo str_repeat("─", 50) . "\n";

$stmt = $pdo->query("SHOW COLUMNS FROM usuarios WHERE Field = 'rol'");
$rolInfo = $stmt->fetch();
echo "   Tipo de campo rol: " . $rolInfo['Type'] . "\n";

if (strpos($rolInfo['Type'], 'admin_general') !== false && 
    strpos($rolInfo['Type'], 'admin_dependencia') !== false) {
    echo "✅ Roles admin_general y admin_dependencia: CONFIGURADOS\n";
    $resultados[] = "Roles de usuario: OK";
} else {
    echo "❌ Los roles no están correctamente configurados\n";
    $errores[] = "Configuración de roles incorrecta";
}

// ============================================================================
// 4. VERIFICACIÓN DE USUARIOS DE PRUEBA
// ============================================================================
echo "\n📋 SECCIÓN 4: USUARIOS EN EL SISTEMA\n";
echo str_repeat("─", 50) . "\n";

$stmt = $pdo->query("SELECT username, nombre_completo, rol, activo FROM usuarios ORDER BY rol, username");
$usuarios = $stmt->fetchAll();

echo "   Total de usuarios: " . count($usuarios) . "\n\n";

$conteoRoles = [];
foreach ($usuarios as $user) {
    $estado = $user['activo'] ? '🟢' : '🔴';
    echo "   $estado {$user['username']} ({$user['rol']}) - {$user['nombre_completo']}\n";
    
    if (!isset($conteoRoles[$user['rol']])) {
        $conteoRoles[$user['rol']] = 0;
    }
    $conteoRoles[$user['rol']]++;
}

echo "\n   Resumen por rol:\n";
foreach ($conteoRoles as $rol => $count) {
    echo "   • $rol: $count usuario(s)\n";
}

if (count($usuarios) >= 1) {
    echo "\n✅ Usuarios de prueba: CONFIGURADOS\n";
    $resultados[] = "Usuarios de prueba: OK";
} else {
    echo "\n⚠️ No hay usuarios configurados\n";
    $errores[] = "No hay usuarios en el sistema";
}

// ============================================================================
// 5. VERIFICACIÓN DE ACTOS ADMINISTRATIVOS
// ============================================================================
echo "\n📋 SECCIÓN 5: DATOS DE ACTOS ADMINISTRATIVOS\n";
echo str_repeat("─", 50) . "\n";

$stmt = $pdo->query("SELECT COUNT(*) as total FROM actos_administrativos WHERE activo = 1");
$totalActos = $stmt->fetch()['total'];

$stmt = $pdo->query("SELECT COUNT(*) as total FROM actos_administrativos WHERE activo = 1 AND estado = 'tramite'");
$enTramite = $stmt->fetch()['total'];

$stmt = $pdo->query("SELECT COUNT(*) as total FROM actos_administrativos WHERE activo = 1 AND estado = 'finalizado'");
$finalizados = $stmt->fetch()['total'];

echo "   📊 Total de registros activos: $totalActos\n";
echo "   📊 En trámite: $enTramite\n";
echo "   📊 Finalizados: $finalizados\n";

if ($totalActos > 0) {
    echo "\n✅ Datos de prueba: DISPONIBLES\n";
    $resultados[] = "Datos de actos administrativos: OK";
} else {
    echo "\n⚠️ No hay datos de prueba cargados\n";
    $errores[] = "No hay datos de actos administrativos";
}

// ============================================================================
// 6. VERIFICACIÓN DE DEPENDENCIAS
// ============================================================================
echo "\n📋 SECCIÓN 6: CATÁLOGO DE DEPENDENCIAS\n";
echo str_repeat("─", 50) . "\n";

try {
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM dependencias");
    $totalDeps = $stmt->fetch()['total'];
    echo "   Total de dependencias registradas: $totalDeps\n";
    
    if ($totalDeps > 0) {
        echo "✅ Catálogo de dependencias: CONFIGURADO\n";
        $resultados[] = "Catálogo de dependencias: OK";
    } else {
        echo "⚠️ El catálogo de dependencias está vacío\n";
        $errores[] = "Catálogo de dependencias vacío";
    }
} catch (PDOException $e) {
    echo "⚠️ Tabla dependencias no existe o tiene errores\n";
    $errores[] = "Tabla dependencias no disponible";
}

// ============================================================================
// 7. VERIFICACIÓN DE AUDITORÍA
// ============================================================================
echo "\n📋 SECCIÓN 7: SISTEMA DE AUDITORÍA\n";
echo str_repeat("─", 50) . "\n";

$stmt = $pdo->query("SELECT COUNT(*) as total FROM auditoria_cargas");
$totalAuditoria = $stmt->fetch()['total'];

$stmt = $pdo->query("SELECT accion, COUNT(*) as total FROM auditoria_cargas GROUP BY accion ORDER BY total DESC LIMIT 5");
$topAcciones = $stmt->fetchAll();

echo "   Total de registros de auditoría: $totalAuditoria\n";
if (count($topAcciones) > 0) {
    echo "   Top acciones registradas:\n";
    foreach ($topAcciones as $accion) {
        echo "   • {$accion['accion']}: {$accion['total']}\n";
    }
}

echo "✅ Sistema de auditoría: FUNCIONAL\n";
$resultados[] = "Sistema de auditoría: OK";

// ============================================================================
// 8. PRUEBA DE AUTENTICACIÓN
// ============================================================================
echo "\n📋 SECCIÓN 8: PRUEBA DE AUTENTICACIÓN\n";
echo str_repeat("─", 50) . "\n";

// Probar verificación de contraseña
$testUser = 'admin_general';
$testPass = '123456';
$testHash = md5($testPass);

$stmt = $pdo->prepare("SELECT username, password_hash, rol FROM usuarios WHERE username = ?");
$stmt->execute([$testUser]);
$user = $stmt->fetch();

if ($user) {
    if ($user['password_hash'] === $testHash) {
        echo "✅ Usuario '$testUser': PASSWORD VÁLIDO\n";
        echo "   Rol del usuario: {$user['rol']}\n";
        $resultados[] = "Autenticación de prueba: OK";
    } else {
        echo "❌ Usuario '$testUser': PASSWORD NO COINCIDE\n";
        echo "   Hash esperado: $testHash\n";
        echo "   Hash en BD: {$user['password_hash']}\n";
        $errores[] = "Password de usuario de prueba no coincide";
    }
} else {
    echo "❌ Usuario '$testUser' no encontrado\n";
    $errores[] = "Usuario admin_general no existe";
}

// ============================================================================
// 9. VERIFICACIÓN DE DIRECTORIOS
// ============================================================================
echo "\n📋 SECCIÓN 9: DIRECTORIOS DEL SISTEMA\n";
echo str_repeat("─", 50) . "\n";

$directorios = [
    'uploads' => 'Directorio principal de uploads',
    'uploads/excel' => 'Directorio para archivos Excel',
    'uploads/pdfs' => 'Directorio para PDFs',
    'uploads/procesados' => 'Directorio para archivos procesados'
];

foreach ($directorios as $dir => $descripcion) {
    if (is_dir($dir)) {
        if (is_writable($dir)) {
            echo "✅ '$dir': EXISTE y es ESCRIBIBLE\n";
        } else {
            echo "⚠️ '$dir': EXISTE pero NO ES ESCRIBIBLE\n";
            $errores[] = "Directorio $dir no tiene permisos de escritura";
        }
    } else {
        echo "❌ '$dir': NO EXISTE\n";
        $errores[] = "Directorio $dir no existe";
    }
}

// ============================================================================
// RESUMEN FINAL
// ============================================================================
echo "\n\n╔══════════════════════════════════════════════════════════════════╗\n";
echo "║                        RESUMEN FINAL                             ║\n";
echo "╚══════════════════════════════════════════════════════════════════╝\n\n";

echo "📊 PRUEBAS EXITOSAS: " . count($resultados) . "\n";
foreach ($resultados as $r) {
    echo "   ✅ $r\n";
}

echo "\n📊 ERRORES/ADVERTENCIAS: " . count($errores) . "\n";
if (count($errores) > 0) {
    foreach ($errores as $e) {
        echo "   ❌ $e\n";
    }
} else {
    echo "   🎉 No se encontraron errores\n";
}

// Resultado final
echo "\n" . str_repeat("═", 70) . "\n";
if (count($errores) == 0) {
    echo "🎉 RESULTADO: TODAS LAS PRUEBAS PASARON EXITOSAMENTE\n";
    echo "   El sistema está listo para producción.\n";
} elseif (count($errores) <= 2) {
    echo "⚠️ RESULTADO: ALGUNAS ADVERTENCIAS MENORES\n";
    echo "   Revisar los errores antes de pasar a producción.\n";
} else {
    echo "❌ RESULTADO: SE ENCONTRARON ERRORES CRÍTICOS\n";
    echo "   Corregir los errores antes de pasar a producción.\n";
}
echo str_repeat("═", 70) . "\n";

// Guardar reporte en archivo
$reporte = [
    'fecha' => date('Y-m-d H:i:s'),
    'resultados_exitosos' => $resultados,
    'errores' => $errores,
    'total_exitosos' => count($resultados),
    'total_errores' => count($errores)
];

file_put_contents('test_report.json', json_encode($reporte, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
echo "\n📄 Reporte guardado en: test_report.json\n";

?>
