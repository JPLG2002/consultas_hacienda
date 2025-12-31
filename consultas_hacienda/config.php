<?php
// config.php

// =================================================================
// 0. ENTORNO Y CABECERAS
// =================================================================
// Habilitar reporte de errores para debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Configuración de la cabecera para devolver JSON
header("Content-Type: application/json; charset=UTF-8");
// Configuración para permitir CORS (necesario si el frontend corre en un puerto diferente)
// header("Access-Control-Allow-Origin: *"); 

// =================================================================
// 1. CONFIGURACIÓN DE LA BASE DE DATOS
// =================================================================
// **¡IMPORTANTE!** Reemplaza estos valores si tu configuración no es la predeterminada de XAMPP.
define('DB_HOST', 'localhost'); // Servidor de la BD
define('DB_NAME', 'hacienda_db'); // Nombre de la base de datos
define('DB_USER', 'root'); // Usuario predeterminado de MySQL en XAMPP
define('DB_PASS', ''); // Contraseña predeterminada de MySQL en XAMPP (vacía)

// =================================================================
// 2. FUNCIÓN DE CONEXIÓN PDO
// =================================================================

/**
 * Establece y devuelve la conexión a la base de datos usando PDO.
 * @return PDO Objeto PDO.
 */
function connectDB() {
    $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4';
    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION, // Lanzar excepciones en errores
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,       // Obtener resultados como array asociativo
        PDO::ATTR_EMULATE_PREPARES   => false,
    ];

    try {
        $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        return $pdo;
    } catch (\PDOException $e) {
        // En caso de error de conexión, terminamos la ejecución y devolvemos un JSON de error 500.
        http_response_code(500);
        // El mensaje de error de la BDD se expone solo en un entorno de desarrollo para ayudar a depurar.
        // En producción, usaríamos un mensaje genérico.
        echo json_encode(["error" => "Error de conexión con la base de datos.", "details" => $e->getMessage()]);
        exit;
    }
}