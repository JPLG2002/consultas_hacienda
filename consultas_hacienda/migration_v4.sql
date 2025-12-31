-- ============================================================================
-- MIGRACIÓN V4: ACTUALIZAR ROLES Y AGREGAR PERMISOS
-- ============================================================================

USE hacienda_db;

-- Modificar la enumeración del rol para incluir los nuevos tipos
ALTER TABLE usuarios MODIFY COLUMN rol ENUM('admin_general', 'admin_dependencia', 'operador', 'consulta') DEFAULT 'operador';

-- Agregar nuevas columnas
ALTER TABLE usuarios
    ADD COLUMN permisos_eliminacion TINYINT(1) DEFAULT 0 COMMENT '0=No puede eliminar, 1=Sí puede eliminar' AFTER rol,
    ADD COLUMN creado_por INT NULL COMMENT 'Usuario que creó este usuario' AFTER permisos_eliminacion;

-- Agregar constraint de llave foránea
ALTER TABLE usuarios ADD CONSTRAINT fk_creado_por FOREIGN KEY (creado_por) REFERENCES usuarios(id) ON DELETE SET NULL;

-- Actualizar usuarios existentes con los nuevos roles y permisos
UPDATE usuarios SET rol = 'admin_general', permisos_eliminacion = 1 WHERE username = 'admin';
UPDATE usuarios SET rol = 'admin_dependencia', permisos_eliminacion = 1 WHERE username IN ('tesoreria1', 'catastro1', 'fiscalizacion1');
UPDATE usuarios SET rol = 'operador', permisos_eliminacion = 0 WHERE username = 'admin';

-- Agregar índices adicionales
ALTER TABLE usuarios ADD INDEX idx_rol (rol);
ALTER TABLE usuarios ADD INDEX idx_creado_por (creado_por);

-- Verificar la migración
SELECT 'Migración V4 completada exitosamente' as resultado;
SELECT username, rol, permisos_eliminacion, activo FROM usuarios ORDER BY username;
