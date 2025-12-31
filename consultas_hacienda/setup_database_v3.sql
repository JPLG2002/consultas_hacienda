-- ============================================================================
-- ESTRUCTURA DE BASE DE DATOS V3 - CON AUDITORÍA Y SOFT DELETE
-- ============================================================================

-- Crear la base de datos si no existe
CREATE DATABASE IF NOT EXISTS hacienda_db;
USE hacienda_db;

-- ============================================================================
-- TABLA PRINCIPAL: actos_administrativos
-- ============================================================================
DROP TABLE IF EXISTS cargas_errores;
DROP TABLE IF EXISTS cargas_masivas;
DROP TABLE IF EXISTS auditoria_cargas;
DROP TABLE IF EXISTS actos_administrativos;

CREATE TABLE actos_administrativos (
    -- ID interno (autoincremental)
    id_interno INT AUTO_INCREMENT PRIMARY KEY COMMENT 'ID interno autoincremental',
    
    -- ID de registro único generado por la app (para identificación pública)
    id_registro VARCHAR(30) NOT NULL UNIQUE COMMENT 'ID único generado por la app (AA-YYYYMMDD-HHMMSS-XXXX)',
    
    -- Datos del contribuyente/predio
    id_predio VARCHAR(50) NOT NULL COMMENT 'Número de identificación del predio',
    id_contribuyente VARCHAR(50) NOT NULL COMMENT 'Cédula o NIT del contribuyente',
    razon_social VARCHAR(255) NOT NULL COMMENT 'Nombre o Razón Social',
    
    -- Datos del acto administrativo
    no_acto_administrativo VARCHAR(100) NOT NULL COMMENT 'Número del acto administrativo',
    fecha_acto DATE NOT NULL COMMENT 'Fecha del acto administrativo',
    fecha_publicacion DATE NOT NULL COMMENT 'Fecha de publicación',
    tipo_actuacion VARCHAR(255) NOT NULL COMMENT 'Descripción del tipo de actuación',
    organismo VARCHAR(150) NOT NULL COMMENT 'Organismo generador del acto',
    area VARCHAR(255) NOT NULL COMMENT 'Área dentro del organismo',
    fecha_desfijacion DATE NULL COMMENT 'Fecha de desfijación (5 días hábiles)',
    
    -- PDF asociado
    pdf_nombre_original VARCHAR(255) NULL COMMENT 'Nombre original del PDF (del Excel)',
    pdf_nombre_final VARCHAR(255) NULL COMMENT 'Nombre final del PDF (renombrado con timestamp)',
    pdf_ruta VARCHAR(500) NULL COMMENT 'Ruta completa donde se guardó el PDF',
    
    -- Campos adicionales
    mas_datos JSON NULL COMMENT 'Campos adicionales: ciudad, departamento, país, etc.',
    
    -- Estado del trámite (tramite/finalizado)
    estado ENUM('tramite', 'finalizado') NOT NULL DEFAULT 'tramite' COMMENT 'Estado del trámite',
    
    -- SOFT DELETE: activo = 1 (visible), activo = 0 (borrado lógicamente)
    activo TINYINT(1) NOT NULL DEFAULT 1 COMMENT '1=Activo, 0=Inactivo (borrado lógico)',
    
    -- Información de carga
    usuario_carga VARCHAR(100) NOT NULL COMMENT 'Usuario que realizó la carga',
    archivo_origen VARCHAR(255) NULL COMMENT 'Nombre del archivo Excel de donde se cargó',
    fila_origen INT NULL COMMENT 'Número de fila en el Excel original',
    
    -- Timestamps
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at TIMESTAMP NULL COMMENT 'Fecha de borrado lógico',
    
    -- Índices para búsquedas rápidas
    INDEX idx_id_registro (id_registro),
    INDEX idx_id_predio (id_predio),
    INDEX idx_id_contribuyente (id_contribuyente),
    INDEX idx_no_acto (no_acto_administrativo),
    INDEX idx_fecha_publicacion (fecha_publicacion),
    INDEX idx_estado (estado),
    INDEX idx_activo (activo),
    INDEX idx_usuario_carga (usuario_carga)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ============================================================================
-- TABLA DE AUDITORÍA: auditoria_cargas
-- Registra todas las acciones de los usuarios
-- ============================================================================
CREATE TABLE auditoria_cargas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    
    -- Usuario que realizó la acción
    usuario VARCHAR(100) NOT NULL COMMENT 'Usuario que realizó la acción',
    
    -- Tipo de acción
    accion ENUM(
        'upload_excel',           -- Subió un archivo Excel
        'upload_excel_error',     -- Error al subir Excel
        'verificar_pdfs',         -- Verificó PDFs de un archivo
        'procesar_inicio',        -- Inició procesamiento
        'procesar_exito',         -- Procesamiento exitoso
        'procesar_parcial',       -- Procesamiento parcial (algunos errores)
        'procesar_error',         -- Error en procesamiento
        'borrar_registro',        -- Borró (desactivó) un registro
        'restaurar_registro',     -- Restauró un registro borrado
        'eliminar_excel',         -- Eliminó un Excel pendiente
        'login',                  -- Inició sesión
        'logout'                  -- Cerró sesión
    ) NOT NULL COMMENT 'Tipo de acción realizada',
    
    -- Archivos relacionados
    archivo_excel VARCHAR(255) NULL COMMENT 'Nombre del archivo Excel involucrado',
    
    -- Registro relacionado (si aplica)
    id_registro VARCHAR(30) NULL COMMENT 'ID del registro afectado (si aplica)',
    
    -- Detalles adicionales en JSON
    detalles JSON NULL COMMENT 'Información adicional de la acción',
    
    -- Resultado
    resultado ENUM('exito', 'error', 'parcial') DEFAULT 'exito',
    mensaje TEXT NULL COMMENT 'Mensaje descriptivo o de error',
    
    -- IP y navegador (para seguridad)
    ip_address VARCHAR(45) NULL COMMENT 'Dirección IP del usuario',
    user_agent TEXT NULL COMMENT 'Navegador/dispositivo del usuario',
    
    -- Timestamp
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    -- Índices
    INDEX idx_usuario (usuario),
    INDEX idx_accion (accion),
    INDEX idx_fecha (created_at),
    INDEX idx_archivo (archivo_excel),
    INDEX idx_resultado (resultado)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ============================================================================
-- TABLA: cargas_masivas (resumen de cada carga)
-- ============================================================================
CREATE TABLE cargas_masivas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    
    -- Usuario
    usuario VARCHAR(100) NOT NULL,
    
    -- Archivo
    nombre_archivo VARCHAR(255) NOT NULL,
    
    -- Estadísticas
    total_registros INT NOT NULL DEFAULT 0,
    registros_procesados INT NOT NULL DEFAULT 0,
    registros_exitosos INT NOT NULL DEFAULT 0,
    registros_fallidos INT NOT NULL DEFAULT 0,
    registros_sin_pdf INT NOT NULL DEFAULT 0,
    
    -- Estado
    estado ENUM('pendiente', 'procesando', 'completado', 'completado_parcial', 'error') DEFAULT 'pendiente',
    mensaje_error TEXT NULL,
    
    -- Timestamps
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    -- Índices
    INDEX idx_usuario (usuario),
    INDEX idx_estado (estado)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ============================================================================
-- TABLA: cargas_errores (errores detallados por fila)
-- ============================================================================
CREATE TABLE cargas_errores (
    id INT AUTO_INCREMENT PRIMARY KEY,
    carga_id INT NOT NULL,
    fila_numero INT NOT NULL,
    datos_fila TEXT,
    mensaje_error VARCHAR(500),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (carga_id) REFERENCES cargas_masivas(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ============================================================================
-- TABLA DE USUARIOS
-- ============================================================================
DROP TABLE IF EXISTS usuarios;

CREATE TABLE usuarios (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE COMMENT 'Nombre de usuario para login',
    password_hash VARCHAR(255) NOT NULL COMMENT 'Contraseña hasheada',
    nombre_completo VARCHAR(150) NOT NULL COMMENT 'Nombre completo del usuario',
    email VARCHAR(150) NULL,
    organismo VARCHAR(150) NOT NULL COMMENT 'Organismo al que pertenece',
    area VARCHAR(255) NOT NULL COMMENT 'Área dentro del organismo',
    rol ENUM('admin', 'operador', 'consulta') DEFAULT 'operador',
    activo TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    INDEX idx_username (username),
    INDEX idx_organismo (organismo),
    INDEX idx_activo (activo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insertar usuarios de prueba
-- Contraseña: 123456 (hash MD5 para simplificar, en producción usar password_hash de PHP)
INSERT INTO usuarios (username, password_hash, nombre_completo, email, organismo, area, rol, permisos_eliminacion) VALUES
('admin_general', 'e10adc3949ba59abbe56e057f20f883e', 'Administrador General del Sistema', 'admin@hacienda.gov.co',
 'DEPARTAMENTO ADMINISTRATIVO DE HACIENDA', 'DIRECCIÓN GENERAL', 'admin_general', 1),

('admin_tesoreria', 'e10adc3949ba59abbe56e057f20f883e', 'Administrador de Tesorería', 'admin.tesoreria@hacienda.gov.co',
 'DEPARTAMENTO ADMINISTRATIVO DE HACIENDA', 'SUBDIRECCIÓN DE TESORERÍA', 'admin_dependencia', 1),

('admin_catastro', 'e10adc3949ba59abbe56e057f20f883e', 'Administrador de Catastro', 'admin.catastro@hacienda.gov.co',
 'DEPARTAMENTO ADMINISTRATIVO DE HACIENDA', 'SUBDIRECCIÓN DE CATASTRO', 'admin_dependencia', 1),

('tesoreria1', 'e10adc3949ba59abbe56e057f20f883e', 'María García López - Operadora', 'maria.garcia@hacienda.gov.co',
 'DEPARTAMENTO ADMINISTRATIVO DE HACIENDA', 'SUBDIRECCIÓN DE TESORERÍA - OFICINA TÉCNICA OPERATIVA DE COBRO COACTIVO', 'operador', 1),

('tesoreria2', 'e10adc3949ba59abbe56e057f20f883e', 'Pedro Sánchez Ruiz - Operador', 'pedro.sanchez@hacienda.gov.co',
 'DEPARTAMENTO ADMINISTRATIVO DE HACIENDA', 'SUBDIRECCIÓN DE TESORERÍA - OFICINA TÉCNICA OPERATIVA DE COBRO COACTIVO', 'operador', 0),

('catastro1', 'e10adc3949ba59abbe56e057f20f883e', 'Carlos Rodríguez Pérez - Operador', 'carlos.rodriguez@hacienda.gov.co',
 'DEPARTAMENTO ADMINISTRATIVO DE HACIENDA', 'SUBDIRECCIÓN DE CATASTRO', 'operador', 1),

('catastro2', 'e10adc3949ba59abbe56e057f20f883e', 'Luisa Morales Díaz - Operadora', 'luisa.morales@hacienda.gov.co',
 'DEPARTAMENTO ADMINISTRATIVO DE HACIENDA', 'SUBDIRECCIÓN DE CATASTRO', 'operador', 0),

('fiscalizacion1', 'e10adc3949ba59abbe56e057f20f883e', 'Ana Martínez Silva - Operadora', 'ana.martinez@hacienda.gov.co',
 'DEPARTAMENTO ADMINISTRATIVO DE HACIENDA', 'SUBDIRECCIÓN DE FISCALIZACIÓN', 'operador', 1);


-- ============================================================================
-- DATOS DE PRUEBA
-- ============================================================================
INSERT INTO actos_administrativos 
    (id_registro, id_predio, id_contribuyente, razon_social, no_acto_administrativo, 
     fecha_acto, fecha_publicacion, tipo_actuacion, organismo, area, fecha_desfijacion, 
     pdf_nombre_original, pdf_nombre_final, mas_datos, estado, activo, usuario_carga) 
VALUES
(
    'AA-20250129-100000-0001',
    '1115075600', 
    '800022504-7', 
    'CONSTRUCTORA RIO AGUACATAL LIMITADA', 
    '4131.032.9.5.111906', 
    '2025-01-29', 
    '2025-01-29', 
    'MANDAMIENTO DE PAGO IMPUESTO PREDIAL UNIFICADO', 
    'DEPARTAMENTO ADMINISTRATIVO DE HACIENDA', 
    'SUBDIRECCIÓN DE TESORERÍA - OFICINA TÉCNICA OPERATIVA DE COBRO COACTIVO', 
    '2025-02-05', 
    'mandamiento_pago_111906.pdf',
    'mandamiento_pago_111906_20250129_100000.pdf',
    '{"ciudad": "Cali", "departamento": "Valle", "pais": "Colombia"}', 
    'tramite',
    1,
    'admin'
),
(
    'AA-20250120-100000-0002',
    '1115075601', 
    '123456789', 
    'JUAN PÉREZ GARCÍA', 
    '4131.032.9.5.111907', 
    '2025-01-20', 
    '2025-01-21', 
    'NOTIFICACIÓN DE AVALÚO CATASTRAL', 
    'DEPARTAMENTO ADMINISTRATIVO DE HACIENDA', 
    'SUBDIRECCIÓN DE CATASTRO', 
    '2025-01-28', 
    'avaluo_111907.pdf',
    'avaluo_111907_20250120_100000.pdf',
    '{"ciudad": "Cali", "departamento": "Valle", "pais": "Colombia"}', 
    'finalizado',
    1,
    'admin'
),
(
    'AA-20250115-100000-0003',
    '1115075602', 
    '987654321', 
    'EMPRESA COMERCIAL XYZ S.A.S', 
    '4131.032.9.5.111908', 
    '2025-01-15', 
    '2025-01-16', 
    'RESOLUCIÓN DE SANCIÓN POR MORA', 
    'DEPARTAMENTO ADMINISTRATIVO DE HACIENDA', 
    'SUBDIRECCIÓN DE FISCALIZACIÓN', 
    '2025-01-23', 
    'sancion_111908.pdf',
    'sancion_111908_20250115_100000.pdf',
    '{"ciudad": "Cali", "departamento": "Valle", "pais": "Colombia"}', 
    'finalizado',
    1,
    'admin'
);

-- Insertar registro de auditoría inicial
INSERT INTO auditoria_cargas (usuario, accion, detalles, resultado, mensaje)
VALUES ('sistema', 'login', '{"descripcion": "Inicialización del sistema"}', 'exito', 'Base de datos inicializada correctamente');

SELECT 'Base de datos V3 creada exitosamente con auditoría y soft delete' AS mensaje;

