<?php
/**
 * Script de prueba para verificar que la gestión de usuarios funciona
 */

echo "=== PRUEBA DE GESTIÓN DE USUARIOS ===\n\n";

// 1. Verificar usuarios en BD
echo "1. Usuarios en base de datos:\n";
$usuarios = shell_exec("mysql -u root hacienda_db -e \"SELECT username, rol, area FROM usuarios WHERE activo = 1 ORDER BY username;\" 2>/dev/null");
echo $usuarios . "\n";

// 2. Probar API de login
echo "2. Probando login de admin_tesoreria:\n";
$loginResult = shell_exec("cd /Users/juanito/Desktop/ARCHIVOS\ TRABAJO/consultas_hacienda && curl -s -X POST 'http://localhost:8000/api_auth.php' -d 'action=login&username=admin_tesoreria&password=123456' | jq -r '.success, .user.rol' 2>/dev/null");
if ($loginResult) {
    echo "Login exitoso. Rol: " . trim($loginResult) . "\n";
} else {
    echo "Error en login\n";
}
echo "\n";

// 3. Probar API de usuarios
echo "3. Probando obtención de usuarios de tesorería:\n";
$usersResult = shell_exec("cd /Users/juanito/Desktop/ARCHIVOS\ TRABAJO/consultas_hacienda && curl -s -X GET 'http://localhost:8000/api_auth.php?action=list_users_dependencia&user=admin_tesoreria' | jq -r '.users[]?.username' 2>/dev/null");
if ($usersResult) {
    echo "Usuarios encontrados:\n" . $usersResult . "\n";
} else {
    echo "Error obteniendo usuarios\n";
}

echo "\n=== INSTRUCCIONES PARA EL USUARIO ===\n\n";
echo "1. Ve a http://localhost:8000/login.html\n";
echo "2. Inicia sesión como: admin_tesoreria / 123456\n";
echo "3. Ve a la pestaña '👥 Gestión de Usuarios'\n";
echo "4. Si no se muestra nada, haz clic en '🔄 Refrescar'\n";
echo "5. Si aún no funciona, haz clic en '🔍 Debug' y revisa la consola\n";
echo "\n";

echo "=== POSIBLES PROBLEMAS ===\n\n";
echo "• La sesión puede estar desactualizada. Usa '🔄 Refrescar'\n";
echo "• Verifica que estés logueado como 'admin_tesoreria'\n";
echo "• Revisa la consola del navegador (F12) para errores\n";
echo "• El servidor PHP debe estar corriendo: php -S localhost:8000\n";
echo "\n";
?>
