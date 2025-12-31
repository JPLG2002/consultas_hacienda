-- ============================================================================
-- ESTRUCTURA DE BASE DE DATOS V2 - CARGA MASIVA
-- ============================================================================

-- Crear la base de datos si no existe
CREATE DATABASE IF NOT EXISTS hacienda_db;
USE hacienda_db;

-- Eliminar tabla anterior si existe
DROP TABLE IF EXISTS actos_administrativos;

-- Nueva estructura según especificaciones de la alcaldía
CREATE TABLE IF NOT EXISTS actos_administrativos (
    id_interno INT AUTO_INCREMENT PRIMARY KEY COMMENT 'ID interno autoincremental',
    id_predio VARCHAR(50) NOT NULL COMMENT 'Número de identificación del predio',
    id_contribuyente VARCHAR(50) NOT NULL COMMENT 'Cédula o NIT del contribuyente',
    razon_social VARCHAR(255) NOT NULL COMMENT 'Nombre o Razón Social',
    no_acto_administrativo VARCHAR(100) NOT NULL COMMENT 'Número del acto administrativo',
    fecha_acto DATE NOT NULL COMMENT 'Fecha del acto administrativo (AAAAMMDD)',
    fecha_publicacion DATE NOT NULL COMMENT 'Fecha de publicación (AAAAMMDD)',
    tipo_actuacion VARCHAR(255) NOT NULL COMMENT 'Descripción del tipo de actuación',
    organismo VARCHAR(150) NOT NULL COMMENT 'Organismo generador del acto',
    area VARCHAR(255) NOT NULL COMMENT 'Área dentro del organismo',
    fecha_desfijacion DATE NULL COMMENT 'Fecha de desfijación (5 días hábiles)',
    mas_datos JSON NULL COMMENT 'Campos adicionales: ciudad, departamento, país, etc.',
    estado ENUM('tramite', 'finalizado') NOT NULL DEFAULT 'tramite',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    -- Índices para búsquedas rápidas
    INDEX idx_id_predio (id_predio),
    INDEX idx_id_contribuyente (id_contribuyente),
    INDEX idx_no_acto (no_acto_administrativo),
    INDEX idx_fecha_publicacion (fecha_publicacion),
    INDEX idx_estado (estado)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabla para tracking de cargas masivas
CREATE TABLE IF NOT EXISTS cargas_masivas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nombre_archivo VARCHAR(255) NOT NULL,
    total_registros INT NOT NULL DEFAULT 0,
    registros_procesados INT NOT NULL DEFAULT 0,
    registros_exitosos INT NOT NULL DEFAULT 0,
    registros_fallidos INT NOT NULL DEFAULT 0,
    estado ENUM('pendiente', 'procesando', 'completado', 'error') DEFAULT 'pendiente',
    mensaje_error TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabla para errores de carga (log detallado)
CREATE TABLE IF NOT EXISTS cargas_errores (
    id INT AUTO_INCREMENT PRIMARY KEY,
    carga_id INT NOT NULL,
    fila_numero INT NOT NULL,
    datos_fila TEXT,
    mensaje_error VARCHAR(500),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (carga_id) REFERENCES cargas_masivas(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Datos de prueba iniciales
INSERT INTO actos_administrativos 
    (id_predio, id_contribuyente, razon_social, no_acto_administrativo, fecha_acto, fecha_publicacion, tipo_actuacion, organismo, area, fecha_desfijacion, mas_datos, estado) 
VALUES
('1115075600', '800022504-7', 'CONSTRUCTORA RIO AGUACATAL LIMITADA', '4131.032.9.5.111906', '2025-01-29', '2025-01-29', 'MANDAMIENTO DE PAGO IMPUESTO PREDIAL UNIFICADO', 'DEPARTAMENTO ADMINISTRATIVO DE HACIENDA', 'SUBDIRECCIÓN DE TESORERÍA - OFICINA TÉCNICA OPERATIVA DE COBRO COACTIVO', '2025-02-05', '{"ciudad": "Cali", "departamento": "Valle", "pais": "Colombia"}', 'tramite'),
('1115075601', '123456789', 'JUAN PÉREZ GARCÍA', '4131.032.9.5.111907', '2025-01-20', '2025-01-21', 'NOTIFICACIÓN DE AVALÚO CATASTRAL', 'DEPARTAMENTO ADMINISTRATIVO DE HACIENDA', 'SUBDIRECCIÓN DE CATASTRO', '2025-01-28', '{"ciudad": "Cali", "departamento": "Valle", "pais": "Colombia"}', 'finalizado'),
('1115075602', '987654321', 'EMPRESA COMERCIAL XYZ S.A.S', '4131.032.9.5.111908', '2025-01-15', '2025-01-16', 'RESOLUCIÓN DE SANCIÓN POR MORA', 'DEPARTAMENTO ADMINISTRATIVO DE HACIENDA', 'SUBDIRECCIÓN DE FISCALIZACIÓN', '2025-01-23', '{"ciudad": "Cali", "departamento": "Valle", "pais": "Colombia"}', 'finalizado');

