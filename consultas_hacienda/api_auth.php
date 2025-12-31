<?php
/**
 * API de Autenticación
 * Maneja login y obtención de datos del usuario
 */

require_once 'config.php';

header('Content-Type: application/json');

$action = $_GET['action'] ?? $_POST['action'] ?? '';

switch ($action) {
    case 'login':
        login();
        break;
    case 'get_user':
        getUser();
        break;
    case 'list_users':
        listUsers();
        break;
    case 'list_users_dependencia':
        listUsersDependencia($_GET['user'] ?? $_POST['user'] ?? '');
        break;
    case 'create_user':
        createUser($_GET['user'] ?? $_POST['user'] ?? '');
        break;
    case 'update_user':
        updateUser($_GET['user'] ?? $_POST['user'] ?? '');
        break;
    case 'toggle_user_status':
        toggleUserStatus($_GET['user'] ?? $_POST['user'] ?? '');
        break;
    default:
        echo json_encode(['success' => false, 'error' => 'Acción no válida']);
}

// ============================================================================
// LOGIN
// ============================================================================
function login() {
    $username = trim($_POST['username'] ?? '');
    $password = trim($_POST['password'] ?? '');
    
    if (empty($username) || empty($password)) {
        echo json_encode(['success' => false, 'error' => 'Usuario y contraseña son requeridos']);
        return;
    }
    
    try {
        $pdo = connectDB();
        
        $sql = "SELECT id, username, password_hash, nombre_completo, email, organismo, area, rol, activo, permisos_eliminacion
                FROM usuarios
                WHERE username = ?";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$username]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$user) {
            echo json_encode(['success' => false, 'error' => 'Usuario no encontrado']);
            return;
        }
        
        if ($user['activo'] != 1) {
            echo json_encode(['success' => false, 'error' => 'Usuario desactivado']);
            return;
        }
        
        // Verificar contraseña (MD5 para simplificar, en producción usar password_verify)
        $passwordHash = md5($password);
        if ($passwordHash !== $user['password_hash']) {
            echo json_encode(['success' => false, 'error' => 'Contraseña incorrecta']);
            return;
        }
        
        // Generar token simple (en producción usar JWT)
        $token = bin2hex(random_bytes(32));
        
        // Registrar login en auditoría
        registrarAuditoria($pdo, $username, 'login', null, null, [
            'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
        ], 'exito', 'Inicio de sesión exitoso');
        
        // Devolver datos del usuario (sin password)
        unset($user['password_hash']);
        
        echo json_encode([
            'success' => true,
            'token' => $token,
            'user' => $user
        ]);
        
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => 'Error de conexión: ' . $e->getMessage()]);
    }
}

// ============================================================================
// OBTENER USUARIO POR USERNAME
// ============================================================================
function getUser() {
    $username = trim($_GET['username'] ?? '');
    
    if (empty($username)) {
        echo json_encode(['success' => false, 'error' => 'Username requerido']);
        return;
    }
    
    try {
        $pdo = connectDB();
        
        $sql = "SELECT id, username, nombre_completo, email, organismo, area, rol
                FROM usuarios 
                WHERE username = ? AND activo = 1";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$username]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$user) {
            echo json_encode(['success' => false, 'error' => 'Usuario no encontrado']);
            return;
        }
        
        echo json_encode([
            'success' => true,
            'user' => $user
        ]);
        
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
}

// ============================================================================
// LISTAR USUARIOS (solo admin)
// ============================================================================
function listUsers() {
    try {
        $pdo = connectDB();
        
        $sql = "SELECT id, username, nombre_completo, email, organismo, area, rol, activo, created_at
                FROM usuarios 
                ORDER BY nombre_completo";
        
        $stmt = $pdo->query($sql);
        $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode([
            'success' => true,
            'users' => $users
        ]);
        
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
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
        return false;
    }
}

// ============================================================================
// LISTAR USUARIOS DE LA DEPENDENCIA (admin_general o admin_dependencia)
// ============================================================================
function listUsersDependencia($currentUser) {
    try {
        $pdo = connectDB();

        // Obtener el organismo y área del usuario actual
        $sqlUser = "SELECT rol, organismo, area FROM usuarios WHERE username = ? AND activo = 1";
        $stmtUser = $pdo->prepare($sqlUser);
        $stmtUser->execute([$currentUser]);
        $userData = $stmtUser->fetch(PDO::FETCH_ASSOC);

        if (!$userData) {
            echo json_encode(['success' => false, 'error' => 'Usuario no encontrado']);
            return;
        }

        $rol = $userData['rol'];
        $organismo = $userData['organismo'];
        $area = $userData['area'];

        // Solo admin_general y admin_dependencia pueden usar esta función
        if ($rol !== 'admin_general' && $rol !== 'admin_dependencia') {
            echo json_encode(['success' => false, 'error' => 'No tienes permisos para esta acción']);
            return;
        }

        // Listar usuarios según permisos
        if ($rol === 'admin_general') {
            // Admin general ve TODOS los usuarios excepto otros admin_general
            $sql = "SELECT id, username, nombre_completo, email, organismo, area, rol, activo, permisos_eliminacion, created_at
                    FROM usuarios
                    WHERE rol IN ('admin_dependencia', 'operador', 'consulta')
                      AND username != ?
                    ORDER BY area, rol, nombre_completo";

            $stmt = $pdo->prepare($sql);
            $stmt->execute([$currentUser]);
        } else {
            // Admin de dependencia ve usuarios de su área y sub-áreas (que empiecen con el mismo prefijo de área)
            $areaBase = $area;
            $sql = "SELECT id, username, nombre_completo, email, organismo, area, rol, activo, permisos_eliminacion, created_at
                    FROM usuarios
                    WHERE organismo = ?
                      AND (area = ? OR area LIKE CONCAT(?, ' - %'))
                      AND rol IN ('operador', 'consulta')
                      AND username != ?
                    ORDER BY area, rol, nombre_completo";

            $stmt = $pdo->prepare($sql);
            $stmt->execute([$organismo, $areaBase, $areaBase, $currentUser]);
        }
        $users = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Obtener lista de áreas disponibles para el frontend
        $sqlAreas = "SELECT DISTINCT area FROM usuarios WHERE organismo = ? ORDER BY area";
        $stmtAreas = $pdo->prepare($sqlAreas);
        $stmtAreas->execute([$organismo]);
        $areas = $stmtAreas->fetchAll(PDO::FETCH_COLUMN);

        echo json_encode([
            'success' => true,
            'users' => $users,
            'organismo' => $organismo,
            'area' => $area,
            'areas_disponibles' => $areas
        ]);

    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
}

// ============================================================================
// CREAR USUARIO (admin_general o admin_dependencia)
// ============================================================================
function createUser($currentUser) {
    try {
        $pdo = connectDB();

        // Obtener datos del usuario actual
        $sqlUser = "SELECT id, rol, organismo, area FROM usuarios WHERE username = ? AND activo = 1";
        $stmtUser = $pdo->prepare($sqlUser);
        $stmtUser->execute([$currentUser]);
        $userData = $stmtUser->fetch(PDO::FETCH_ASSOC);

        if (!$userData) {
            echo json_encode(['success' => false, 'error' => 'Usuario no encontrado']);
            return;
        }

        $currentRol = $userData['rol'];

        if ($currentRol !== 'admin_general' && $currentRol !== 'admin_dependencia') {
            echo json_encode(['success' => false, 'error' => 'No tienes permisos para crear usuarios']);
            return;
        }

        $creadoPor = $userData['id'];
        $organismo = $userData['organismo'];

        // Datos del nuevo usuario
        $username = trim($_POST['username'] ?? '');
        $password = trim($_POST['password'] ?? '');
        $nombreCompleto = trim($_POST['nombre_completo'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $rol = $_POST['rol'] ?? 'operador';
        $permisosEliminacion = isset($_POST['permisos_eliminacion']) && $_POST['permisos_eliminacion'] === '1' ? 1 : 0;

        // Validaciones
        if (empty($username) || empty($password) || empty($nombreCompleto)) {
            echo json_encode(['success' => false, 'error' => 'Usuario, contraseña y nombre completo son obligatorios']);
            return;
        }

        if (strlen($username) < 3) {
            echo json_encode(['success' => false, 'error' => 'El nombre de usuario debe tener al menos 3 caracteres']);
            return;
        }

        if (strlen($password) < 6) {
            echo json_encode(['success' => false, 'error' => 'La contraseña debe tener al menos 6 caracteres']);
            return;
        }

        // Validar qué roles puede crear cada tipo de admin
        $rolesPermitidos = [];
        if ($currentRol === 'admin_general') {
            // Admin general puede crear cualquier rol excepto otro admin_general
            $rolesPermitidos = ['admin_dependencia', 'operador', 'consulta'];
        } elseif ($currentRol === 'admin_dependencia') {
            // Admin de dependencia solo puede crear operador y consulta
            $rolesPermitidos = ['operador', 'consulta'];
        }

        if (!in_array($rol, $rolesPermitidos)) {
            echo json_encode(['success' => false, 'error' => 'No tienes permisos para crear usuarios con ese rol']);
            return;
        }

        // Verificar si el username ya existe
        $sqlCheck = "SELECT id FROM usuarios WHERE username = ?";
        $stmtCheck = $pdo->prepare($sqlCheck);
        $stmtCheck->execute([$username]);
        if ($stmtCheck->fetch()) {
            echo json_encode(['success' => false, 'error' => 'El nombre de usuario ya existe']);
            return;
        }

        // Determinar el área del nuevo usuario
        // Si es admin_general, puede especificar el área; si no, usa el área del admin que crea
        $area = $userData['area'];
        
        if ($currentRol === 'admin_general') {
            // El admin general puede especificar el área para cualquier usuario
            $areaSeleccionada = trim($_POST['area'] ?? '');
            if (!empty($areaSeleccionada)) {
                $area = $areaSeleccionada;
            }
        }

        // Crear el usuario
        $passwordHash = md5($password); // MD5 para simplificar, en producción usar password_hash

        $sql = "INSERT INTO usuarios
                (username, password_hash, nombre_completo, email, organismo, area, rol, activo, permisos_eliminacion, creado_por)
                VALUES (?, ?, ?, ?, ?, ?, ?, 1, ?, ?)";

        $stmt = $pdo->prepare($sql);
        $stmt->execute([$username, $passwordHash, $nombreCompleto, $email, $organismo, $area, $rol, $permisosEliminacion, $creadoPor]);

        $newUserId = $pdo->lastInsertId();

        // Registrar auditoría
        registrarAuditoria($pdo, $currentUser, 'login', null, null, [
            'accion' => 'create_user',
            'new_user_id' => $newUserId,
            'new_username' => $username,
            'new_rol' => $rol,
            'new_area' => $area
        ], 'exito', "Usuario $username creado con rol $rol en área $area");

        echo json_encode([
            'success' => true,
            'message' => 'Usuario creado exitosamente',
            'user_id' => $newUserId
        ]);

    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
}

// ============================================================================
// ACTUALIZAR USUARIO (solo admin_dependencia)
// ============================================================================
function updateUser($currentUser) {
    try {
        $pdo = connectDB();

        // Obtener datos del usuario actual
        $sqlUser = "SELECT rol, organismo FROM usuarios WHERE username = ? AND activo = 1";
        $stmtUser = $pdo->prepare($sqlUser);
        $stmtUser->execute([$currentUser]);
        $userData = $stmtUser->fetch(PDO::FETCH_ASSOC);

        $currentRol = $userData['rol'];

        if ($currentRol !== 'admin_general' && $currentRol !== 'admin_dependencia') {
            echo json_encode(['success' => false, 'error' => 'No tienes permisos para actualizar usuarios']);
            return;
        }

        $organismo = $userData['organismo'];

        // Datos a actualizar
        $userId = intval($_POST['user_id'] ?? 0);
        $nombreCompleto = trim($_POST['nombre_completo'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $permisosEliminacion = isset($_POST['permisos_eliminacion']) ? 1 : 0;

        if (!$userId || empty($nombreCompleto)) {
            echo json_encode(['success' => false, 'error' => 'Datos inválidos']);
            return;
        }

        // Verificar permisos según el rol del admin
        if ($currentRol === 'admin_general') {
            // Admin general puede actualizar cualquier usuario operador/consulta
            $sqlCheck = "SELECT username FROM usuarios
                         WHERE id = ? AND rol IN ('operador', 'consulta', 'admin_dependencia') AND activo = 1";
            $stmtCheck = $pdo->prepare($sqlCheck);
            $stmtCheck->execute([$userId]);
        } else {
            // Admin de dependencia solo puede actualizar usuarios de su organismo
            $sqlCheck = "SELECT username FROM usuarios
                         WHERE id = ? AND organismo = ? AND rol IN ('operador', 'consulta') AND activo = 1";
            $stmtCheck = $pdo->prepare($sqlCheck);
            $stmtCheck->execute([$userId, $organismo]);
        }
        $userToUpdate = $stmtCheck->fetch(PDO::FETCH_ASSOC);

        if (!$userToUpdate) {
            echo json_encode(['success' => false, 'error' => 'Usuario no encontrado o no tienes permisos']);
            return;
        }

        // Actualizar
        $sql = "UPDATE usuarios SET nombre_completo = ?, email = ?, permisos_eliminacion = ? WHERE id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$nombreCompleto, $email, $permisosEliminacion, $userId]);

        // Registrar auditoría
        registrarAuditoria($pdo, $currentUser, 'login', null, null, [
            'accion' => 'update_user',
            'updated_user_id' => $userId,
            'updated_username' => $userToUpdate['username']
        ], 'exito', "Usuario {$userToUpdate['username']} actualizado");

        echo json_encode([
            'success' => true,
            'message' => 'Usuario actualizado exitosamente'
        ]);

    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
}

// ============================================================================
// ACTIVAR/DESACTIVAR USUARIO (solo admin_dependencia)
// ============================================================================
function toggleUserStatus($currentUser) {
    try {
        $pdo = connectDB();

        // Obtener datos del usuario actual
        $sqlUser = "SELECT rol, organismo FROM usuarios WHERE username = ? AND activo = 1";
        $stmtUser = $pdo->prepare($sqlUser);
        $stmtUser->execute([$currentUser]);
        $userData = $stmtUser->fetch(PDO::FETCH_ASSOC);

        $currentRol = $userData['rol'];

        if ($currentRol !== 'admin_general' && $currentRol !== 'admin_dependencia') {
            echo json_encode(['success' => false, 'error' => 'No tienes permisos para esta acción']);
            return;
        }

        $organismo = $userData['organismo'];

        // Usuario a cambiar
        $userId = intval($_POST['user_id'] ?? 0);
        $nuevoEstado = intval($_POST['activo'] ?? 0);

        if (!$userId) {
            echo json_encode(['success' => false, 'error' => 'Usuario inválido']);
            return;
        }

        // Verificar permisos según el rol del admin
        if ($currentRol === 'admin_general') {
            // Admin general puede cambiar estado de cualquier usuario operador/consulta/admin_dependencia
            $sqlCheck = "SELECT username, activo FROM usuarios
                         WHERE id = ? AND rol IN ('operador', 'consulta', 'admin_dependencia') AND username != ?";
            $stmtCheck = $pdo->prepare($sqlCheck);
            $stmtCheck->execute([$userId, $currentUser]); // No puede desactivarse a sí mismo
        } else {
            // Admin de dependencia solo puede cambiar usuarios de su organismo
            $sqlCheck = "SELECT username, activo FROM usuarios
                         WHERE id = ? AND organismo = ? AND rol IN ('operador', 'consulta')";
            $stmtCheck = $pdo->prepare($sqlCheck);
            $stmtCheck->execute([$userId, $organismo]);
        }
        $userToUpdate = $stmtCheck->fetch(PDO::FETCH_ASSOC);

        if (!$userToUpdate) {
            echo json_encode(['success' => false, 'error' => 'Usuario no encontrado o no tienes permisos']);
            return;
        }

        // Actualizar estado
        $sql = "UPDATE usuarios SET activo = ? WHERE id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$nuevoEstado, $userId]);

        $accion = $nuevoEstado ? 'activado' : 'desactivado';

        // Registrar auditoría
        registrarAuditoria($pdo, $currentUser, 'login', null, null, [
            'accion' => 'toggle_user_status',
            'updated_user_id' => $userId,
            'updated_username' => $userToUpdate['username'],
            'nuevo_estado' => $nuevoEstado
        ], 'exito', "Usuario {$userToUpdate['username']} $accion");

        echo json_encode([
            'success' => true,
            'message' => "Usuario $accion exitosamente"
        ]);

    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
}
?>

