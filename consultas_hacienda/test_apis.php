<?php
/**
 * Script de pruebas de APIs
 * Prueba los endpoints principales del sistema
 */

echo "\n╔══════════════════════════════════════════════════════════════════╗\n";
echo "║              PRUEBAS DE APIs - SISTEMA HACIENDA                  ║\n";
echo "╚══════════════════════════════════════════════════════════════════╝\n\n";

$baseUrl = 'http://localhost:8080';
$resultados = [];
$errores = [];

/**
 * Función para hacer peticiones HTTP
 */
function makeRequest($url, $method = 'GET', $data = []) {
    $opts = [
        'http' => [
            'method' => $method,
            'header' => "Content-Type: application/x-www-form-urlencoded\r\n",
            'timeout' => 10,
            'ignore_errors' => true
        ]
    ];
    
    if ($method === 'POST' && !empty($data)) {
        $opts['http']['content'] = http_build_query($data);
    }
    
    $context = stream_context_create($opts);
    $response = @file_get_contents($url, false, $context);
    
    if ($response === false) {
        return ['error' => 'No se pudo conectar al servidor'];
    }
    
    $json = json_decode($response, true);
    return $json ?? ['raw' => $response];
}

// ============================================================================
// 1. PRUEBA DE LOGIN
// ============================================================================
echo "📋 PRUEBA 1: API DE AUTENTICACIÓN (LOGIN)\n";
echo str_repeat("─", 50) . "\n";

// Login exitoso
$loginUrl = "$baseUrl/api_auth.php?action=login";
$loginData = ['username' => 'admin_general', 'password' => '123456'];

$response = makeRequest($loginUrl, 'POST', $loginData);

if (isset($response['success']) && $response['success'] === true) {
    echo "✅ Login con admin_general: EXITOSO\n";
    echo "   Token recibido: " . substr($response['token'] ?? 'N/A', 0, 20) . "...\n";
    echo "   Rol: " . ($response['user']['rol'] ?? 'N/A') . "\n";
    $resultados[] = "API Login: OK";
} else {
    echo "❌ Login fallido: " . ($response['error'] ?? 'Error desconocido') . "\n";
    $errores[] = "API Login fallida";
}

// Login con credenciales incorrectas
$loginData = ['username' => 'usuario_invalido', 'password' => 'clave_incorrecta'];
$response = makeRequest($loginUrl, 'POST', $loginData);

if (isset($response['success']) && $response['success'] === false) {
    echo "✅ Login inválido rechazado correctamente\n";
} else {
    echo "⚠️ Login inválido no fue rechazado\n";
}

// ============================================================================
// 2. PRUEBA DE LISTA DE USUARIOS
// ============================================================================
echo "\n📋 PRUEBA 2: API DE LISTA DE USUARIOS\n";
echo str_repeat("─", 50) . "\n";

$usersUrl = "$baseUrl/api_auth.php?action=list_users_dependencia&user=admin_general";
$response = makeRequest($usersUrl);

if (isset($response['success']) && $response['success'] === true) {
    $count = count($response['users'] ?? []);
    echo "✅ Lista de usuarios: FUNCIONAL\n";
    echo "   Usuarios encontrados: $count\n";
    $resultados[] = "API Lista usuarios: OK";
} else {
    echo "❌ Error: " . ($response['error'] ?? 'Error desconocido') . "\n";
    $errores[] = "API Lista usuarios fallida";
}

// ============================================================================
// 3. PRUEBA DE API DE CONSULTAS
// ============================================================================
echo "\n📋 PRUEBA 3: API DE CONSULTAS PÚBLICAS\n";
echo str_repeat("─", 50) . "\n";

// Buscar por ID de contribuyente de prueba
$apiUrl = "$baseUrl/api.php?id=800022504-7";
$response = makeRequest($apiUrl);

if (isset($response['tramite']) || isset($response['historial'])) {
    $tramite = count($response['tramite'] ?? []);
    $historial = count($response['historial'] ?? []);
    echo "✅ API de consultas: FUNCIONAL\n";
    echo "   Registros en trámite: $tramite\n";
    echo "   Registros históricos: $historial\n";
    $resultados[] = "API Consultas: OK";
} elseif (isset($response['error'])) {
    echo "❌ Error: " . $response['error'] . "\n";
    $errores[] = "API Consultas fallida";
} else {
    echo "⚠️ Respuesta inesperada de la API\n";
    print_r($response);
}

// ============================================================================
// 4. PRUEBA DE API DE CARGA
// ============================================================================
echo "\n📋 PRUEBA 4: API DE CARGA (LISTAR PENDIENTES)\n";
echo str_repeat("─", 50) . "\n";

$cargaUrl = "$baseUrl/api_carga_v3.php?action=list_pending&user=admin_general";
$response = makeRequest($cargaUrl);

if (isset($response['success']) && $response['success'] === true) {
    $count = count($response['files'] ?? []);
    echo "✅ API de carga: FUNCIONAL\n";
    echo "   Archivos pendientes: $count\n";
    $resultados[] = "API Carga: OK";
} else {
    echo "❌ Error: " . ($response['error'] ?? 'Error desconocido') . "\n";
    $errores[] = "API Carga fallida";
}

// ============================================================================
// 5. PRUEBA DE API DE AUDITORÍA
// ============================================================================
echo "\n📋 PRUEBA 5: API DE AUDITORÍA\n";
echo str_repeat("─", 50) . "\n";

$auditUrl = "$baseUrl/api_carga_v3.php?action=auditoria&user=admin_general";
$response = makeRequest($auditUrl);

if (isset($response['success']) && $response['success'] === true) {
    $count = count($response['registros'] ?? []);
    echo "✅ API de auditoría: FUNCIONAL\n";
    echo "   Registros de auditoría: $count\n";
    $resultados[] = "API Auditoría: OK";
} else {
    echo "❌ Error: " . ($response['error'] ?? 'Error desconocido') . "\n";
    $errores[] = "API Auditoría fallida";
}

// ============================================================================
// 6. PRUEBA DE API DE REGISTROS
// ============================================================================
echo "\n📋 PRUEBA 6: API DE MIS REGISTROS\n";
echo str_repeat("─", 50) . "\n";

$registrosUrl = "$baseUrl/api_carga_v3.php?action=mis_registros&user=admin_general";
$response = makeRequest($registrosUrl);

if (isset($response['success']) && $response['success'] === true) {
    $count = count($response['registros'] ?? []);
    echo "✅ API de registros: FUNCIONAL\n";
    echo "   Total registros: $count\n";
    $resultados[] = "API Registros: OK";
} else {
    echo "❌ Error: " . ($response['error'] ?? 'Error desconocido') . "\n";
    $errores[] = "API Registros fallida";
}

// ============================================================================
// RESUMEN
// ============================================================================
echo "\n\n╔══════════════════════════════════════════════════════════════════╗\n";
echo "║                 RESUMEN DE PRUEBAS DE APIs                       ║\n";
echo "╚══════════════════════════════════════════════════════════════════╝\n\n";

echo "📊 PRUEBAS EXITOSAS: " . count($resultados) . "\n";
foreach ($resultados as $r) {
    echo "   ✅ $r\n";
}

echo "\n📊 ERRORES: " . count($errores) . "\n";
if (count($errores) > 0) {
    foreach ($errores as $e) {
        echo "   ❌ $e\n";
    }
} else {
    echo "   🎉 Todas las APIs funcionan correctamente\n";
}

echo "\n" . str_repeat("═", 70) . "\n";
if (count($errores) == 0) {
    echo "🎉 TODAS LAS APIS ESTÁN FUNCIONANDO CORRECTAMENTE\n";
} else {
    echo "⚠️ Revisar las APIs con errores antes de producción\n";
}
echo str_repeat("═", 70) . "\n\n";

?>
