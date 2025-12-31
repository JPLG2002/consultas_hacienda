-- Crear usuarios de prueba con los nuevos roles
-- Ejecutar después de la migración

USE hacienda_db;

-- Limpiar usuarios existentes (excepto admin si existe)
DELETE FROM usuarios WHERE username != 'admin';

-- Insertar usuarios con nuevos roles
INSERT INTO usuarios (username, password_hash, nombre_completo, email, organismo, area, rol, permisos_eliminacion, activo) VALUES
-- Admin General (todo el sistema)
('admin_general', 'e10adc3949ba59abbe56e057f20f883e', 'Administrador General del Sistema', 'admin@hacienda.gov.co',
 'DEPARTAMENTO ADMINISTRATIVO DE HACIENDA', 'DIRECCIÓN GENERAL', 'admin_general', 1, 1),

-- Admins de Dependencia
('admin_tesoreria', 'e10adc3949ba59abbe56e057f20f883e', 'Administrador de Tesorería', 'admin.tesoreria@hacienda.gov.co',
 'DEPARTAMENTO ADMINISTRATIVO DE HACIENDA', 'SUBDIRECCIÓN DE TESORERÍA', 'admin_dependencia', 1, 1),

('admin_catastro', 'e10adc3949ba59abbe56e057f20f883e', 'Administrador de Catastro', 'admin.catastro@hacienda.gov.co',
 'DEPARTAMENTO ADMINISTRATIVO DE HACIENDA', 'SUBDIRECCIÓN DE CATASTRO', 'admin_dependencia', 1, 1),

('admin_fiscalizacion', 'e10adc3949ba59abbe56e057f20f883e', 'Administrador de Fiscalización', 'admin.fiscalizacion@hacienda.gov.co',
 'DEPARTAMENTO ADMINISTRATIVO DE HACIENDA', 'SUBDIRECCIÓN DE FISCALIZACIÓN', 'admin_dependencia', 1, 1),

-- Operadores de Tesorería
('operador_tes1', 'e10adc3949ba59abbe56e057f20f883e', 'María García López - Operadora Tesorería', 'maria.garcia@hacienda.gov.co',
 'DEPARTAMENTO ADMINISTRATIVO DE HACIENDA', 'SUBDIRECCIÓN DE TESORERÍA - OFICINA TÉCNICA OPERATIVA DE COBRO COACTIVO', 'operador', 1, 1),

('operador_tes2', 'e10adc3949ba59abbe56e057f20f883e', 'Pedro Sánchez Ruiz - Operador Tesorería', 'pedro.sanchez@hacienda.gov.co',
 'DEPARTAMENTO ADMINISTRATIVO DE HACIENDA', 'SUBDIRECCIÓN DE TESORERÍA - OFICINA TÉCNICA OPERATIVA DE COBRO COACTIVO', 'operador', 0, 1),

-- Operadores de Catastro
('operador_cat1', 'e10adc3949ba59abbe56e057f20f883e', 'Carlos Rodríguez Pérez - Operador Catastro', 'carlos.rodriguez@hacienda.gov.co',
 'DEPARTAMENTO ADMINISTRATIVO DE HACIENDA', 'SUBDIRECCIÓN DE CATASTRO', 'operador', 1, 1),

('operador_cat2', 'e10adc3949ba59abbe56e057f20f883e', 'Luisa Morales Díaz - Operadora Catastro', 'luisa.morales@hacienda.gov.co',
 'DEPARTAMENTO ADMINISTRATIVO DE HACIENDA', 'SUBDIRECCIÓN DE CATASTRO', 'operador', 0, 1),

-- Operadores de Fiscalización
('operador_fis1', 'e10adc3949ba59abbe56e057f20f883e', 'Ana Martínez Silva - Operadora Fiscalización', 'ana.martinez@hacienda.gov.co',
 'DEPARTAMENTO ADMINISTRATIVO DE HACIENDA', 'SUBDIRECCIÓN DE FISCALIZACIÓN', 'operador', 1, 1),

('operador_fis2', 'e10adc3949ba59abbe56e057f20f883e', 'Roberto Díaz Castro - Operador Fiscalización', 'roberto.diaz@hacienda.gov.co',
 'DEPARTAMENTO ADMINISTRATIVO DE HACIENDA', 'SUBDIRECCIÓN DE FISCALIZACIÓN', 'operador', 0, 1),

-- Usuarios de solo consulta
('consulta_tes', 'e10adc3949ba59abbe56e057f20f883e', 'Usuario Consulta Tesorería', 'consulta.tes@hacienda.gov.co',
 'DEPARTAMENTO ADMINISTRATIVO DE HACIENDA', 'SUBDIRECCIÓN DE TESORERÍA', 'consulta', 0, 1),

('consulta_cat', 'e10adc3949ba59abbe56e057f20f883e', 'Usuario Consulta Catastro', 'consulta.cat@hacienda.gov.co',
 'DEPARTAMENTO ADMINISTRATIVO DE HACIENDA', 'SUBDIRECCIÓN DE CATASTRO', 'consulta', 0, 1);

-- Mostrar usuarios creados
SELECT 'Usuarios de prueba creados exitosamente' as mensaje;
SELECT username, rol, organismo, area, permisos_eliminacion, activo
FROM usuarios
ORDER BY rol, username;
