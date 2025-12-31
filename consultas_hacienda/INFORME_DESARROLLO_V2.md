# INFORME DE DESARROLLO - SISTEMA DE CONSULTAS HACIENDA

## Versión 3.0 - Diciembre 2025

---

## 📋 ÍNDICE

1. [Resumen Ejecutivo](#1-resumen-ejecutivo)
2. [Arquitectura del Sistema](#2-arquitectura-del-sistema)
3. [Módulos del Sistema](#3-módulos-del-sistema)
4. [Base de Datos](#4-base-de-datos)
5. [Flujo de Trabajo](#5-flujo-de-trabajo)
6. [Gestión de Usuarios](#6-gestión-de-usuarios)
7. [Sistema de Auditoría](#7-sistema-de-auditoría)
8. [Formato del Excel](#8-formato-del-excel)
9. [APIs Disponibles](#9-apis-disponibles)
10. [Guía de Pruebas](#10-guía-de-pruebas)
11. [Estructura de Archivos](#11-estructura-de-archivos)

---

## 1. RESUMEN EJECUTIVO

### Descripción

Sistema web para la gestión y consulta de actos administrativos del Departamento Administrativo de Hacienda. Permite la carga masiva de registros mediante archivos Excel y la consulta pública de notificaciones.

### Características Principales

- ✅ Consulta pública de actos administrativos (sin login)
- ✅ Carga masiva mediante archivos Excel (requiere login)
- ✅ Gestión de PDFs asociados a cada registro
- ✅ Sistema de usuarios con organismo/área asignados
- ✅ Auditoría completa de todas las acciones
- ✅ Soft Delete (borrado lógico de registros)
- ✅ Captura automática de columnas extra del Excel
- ✅ ID único por registro generado por la aplicación
- ✅ Renombrado automático de PDFs para evitar duplicados

### Tecnologías

- **Backend:** PHP 8.x
- **Base de Datos:** MySQL 8.x
- **Frontend:** HTML5, CSS3, JavaScript (Vanilla)
- **Librerías:** PhpSpreadsheet (lectura de Excel)

---

## 2. ARQUITECTURA DEL SISTEMA

```
┌─────────────────────────────────────────────────────────────────┐
│                         FRONTEND                                 │
├─────────────────────────────────────────────────────────────────┤
│  index.html        │  login.html       │  carga.html            │
│  (Consulta Pública)│  (Autenticación)  │  (Carga Masiva)        │
└─────────────────────────────────────────────────────────────────┘
                              │
                              ▼
┌─────────────────────────────────────────────────────────────────┐
│                          BACKEND (APIs)                          │
├─────────────────────────────────────────────────────────────────┤
│  api.php           │  api_auth.php     │  api_carga_v3.php      │
│  (Consultas)       │  (Login)          │  (Carga/Gestión)       │
└─────────────────────────────────────────────────────────────────┘
                              │
                              ▼
┌─────────────────────────────────────────────────────────────────┐
│                       BASE DE DATOS                              │
├─────────────────────────────────────────────────────────────────┤
│  actos_administrativos  │  usuarios  │  auditoria_cargas        │
│  cargas_masivas         │  cargas_errores                       │
└─────────────────────────────────────────────────────────────────┘
                              │
                              ▼
┌─────────────────────────────────────────────────────────────────┐
│                    ALMACENAMIENTO DE ARCHIVOS                    │
├─────────────────────────────────────────────────────────────────┤
│  uploads/                                                        │
│  └── [username]/                                                 │
│      ├── excel/      (Archivos Excel pendientes)                │
│      ├── pdfs/       (PDFs pendientes de procesar)              │
│      └── procesados/ (Archivos ya procesados)                   │
└─────────────────────────────────────────────────────────────────┘
```

---

## 3. MÓDULOS DEL SISTEMA

### 3.1 Módulo de Consulta Pública (`index.html`)

- Búsqueda por ID de predio o ID de contribuyente
- Visualización de registros "En Trámite" e "Históricos"
- Filtros por año y dependencia
- Modal de detalle con toda la información
- Botón "Info Extra" para columnas adicionales
- Descarga de PDF asociado
- **No requiere autenticación**

### 3.2 Módulo de Autenticación (`login.html`)

- Login contra base de datos
- Usuarios con organismo/área asignados
- Sesión persistente mediante localStorage
- Redirección automática si ya está logueado

### 3.3 Módulo de Carga Masiva (`carga.html`)

- **Requiere autenticación**
- Subida de archivos Excel
- Verificación de PDFs asociados
- Procesamiento de archivos
- Gestión de registros cargados (ver/eliminar/restaurar)
- Historial de auditoría
- Muestra organismo/área del usuario logueado

---

## 4. BASE DE DATOS

### 4.1 Tabla: `usuarios`

```sql
CREATE TABLE usuarios (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    nombre_completo VARCHAR(150) NOT NULL,
    email VARCHAR(150) NULL,
    organismo VARCHAR(150) NOT NULL,      -- Asignado al usuario
    area VARCHAR(255) NOT NULL,           -- Asignado al usuario
    rol ENUM('admin', 'operador', 'consulta') DEFAULT 'operador',
    activo TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
```

### 4.2 Tabla: `actos_administrativos`

```sql
CREATE TABLE actos_administrativos (
    id_interno INT AUTO_INCREMENT PRIMARY KEY,
    id_registro VARCHAR(30) NOT NULL UNIQUE,  -- ID generado: AA-YYYYMMDD-HHMMSS-XXXX
  
    -- Datos del contribuyente
    id_predio VARCHAR(50) NOT NULL,
    id_contribuyente VARCHAR(50) NOT NULL,
    razon_social VARCHAR(255) NOT NULL,
  
    -- Datos del acto
    no_acto_administrativo VARCHAR(100) NOT NULL,
    fecha_acto DATE NOT NULL,
    fecha_publicacion DATE NOT NULL,
    tipo_actuacion VARCHAR(255) NOT NULL,
    organismo VARCHAR(150) NOT NULL,          -- Viene del usuario
    area VARCHAR(255) NOT NULL,               -- Viene del usuario
    fecha_desfijacion DATE NULL,
  
    -- PDF asociado
    pdf_nombre_original VARCHAR(255) NULL,    -- Nombre en el Excel
    pdf_nombre_final VARCHAR(255) NULL,       -- Nombre renombrado
  
    -- Datos extra (columnas no mapeadas del Excel)
    mas_datos JSON NULL,
  
    -- Estados
    estado ENUM('tramite', 'finalizado') NOT NULL DEFAULT 'tramite',
    activo TINYINT(1) NOT NULL DEFAULT 1,     -- Soft delete
  
    -- Trazabilidad
    usuario_carga VARCHAR(100) NOT NULL,
    archivo_origen VARCHAR(255) NULL,
    fila_origen INT NULL,
  
    -- Timestamps
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    deleted_at TIMESTAMP NULL
);
```

### 4.3 Tabla: `auditoria_cargas`

```sql
CREATE TABLE auditoria_cargas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    usuario VARCHAR(100) NOT NULL,
    accion ENUM('upload_excel', 'upload_excel_error', 'verificar_pdfs',
                'procesar_inicio', 'procesar_exito', 'procesar_parcial',
                'procesar_error', 'borrar_registro', 'restaurar_registro',
                'eliminar_excel', 'login', 'logout') NOT NULL,
    archivo_excel VARCHAR(255) NULL,
    id_registro VARCHAR(30) NULL,
    detalles JSON NULL,
    resultado ENUM('exito', 'error', 'parcial') DEFAULT 'exito',
    mensaje TEXT NULL,
    ip_address VARCHAR(45) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
```

---

## 5. FLUJO DE TRABAJO

### 5.1 Flujo de Carga de Datos

```
┌──────────────────┐
│ 1. LOGIN         │
│ Usuario ingresa  │
│ credenciales     │
└────────┬─────────┘
         │
         ▼
┌──────────────────┐
│ 2. SUBIR EXCEL   │
│ Arrastra archivo │
│ a la zona        │
└────────┬─────────┘
         │
         ▼
┌──────────────────┐
│ 3. VERIFICACIÓN  │
│ Sistema lee el   │
│ Excel y verifica │
│ PDFs faltantes   │
└────────┬─────────┘
         │
         ▼
┌──────────────────┐     ┌──────────────────┐
│ ¿Todos los PDFs? │──NO─▶│ SUBIR PDFs       │
│                  │      │ vía FTP al       │
└────────┬─────────┘      │ directorio       │
         │                └──────────────────┘
        YES
         │
         ▼
┌──────────────────┐
│ 4. PROCESAR      │
│ - Inserta en BD  │
│ - Renombra PDFs  │
│ - Mueve archivos │
│ - Registra audit │
└────────┬─────────┘
         │
         ▼
┌──────────────────┐
│ 5. COMPLETADO    │
│ Registros        │
│ disponibles para │
│ consulta pública │
└──────────────────┘
```

### 5.2 Flujo de Consulta

```
┌──────────────────┐
│ Usuario ingresa  │
│ ID de búsqueda   │
└────────┬─────────┘
         │
         ▼
┌──────────────────┐
│ API busca por    │
│ id_predio o      │
│ id_contribuyente │
│ (solo activos)   │
└────────┬─────────┘
         │
         ▼
┌──────────────────┐
│ Muestra pestañas:│
│ - En Trámite     │
│ - Histórico      │
└────────┬─────────┘
         │
         ▼
┌──────────────────┐
│ Usuario puede:   │
│ - Ver detalle    │
│ - Ver info extra │
│ - Descargar PDF  │
└──────────────────┘
```

---

## 6. GESTIÓN DE USUARIOS

### 6.1 Usuarios de Prueba

| Usuario            | Contraseña | Organismo           | Área                                        |
| ------------------ | ----------- | ------------------- | -------------------------------------------- |
| `admin`          | `123456`  | DPTO. ADM. HACIENDA | Subdirección de Sistemas                    |
| `tesoreria1`     | `123456`  | DPTO. ADM. HACIENDA | Subdirección de Tesorería - Cobro Coactivo |
| `catastro1`      | `123456`  | DPTO. ADM. HACIENDA | Subdirección de Catastro                    |
| `fiscalizacion1` | `123456`  | DPTO. ADM. HACIENDA | Subdirección de Fiscalización              |

### 6.2 Asignación de Organismo/Área

- Cada usuario tiene asignado un **organismo** y **área**
- Al cargar registros, estos valores se toman del usuario, **NO del Excel**
- Esto garantiza que cada área solo carga registros de su competencia

---

## 7. SISTEMA DE AUDITORÍA

### 7.1 Acciones Registradas

| Acción                | Descripción                      |
| ---------------------- | --------------------------------- |
| `login`              | Inicio de sesión                 |
| `upload_excel`       | Subida de archivo Excel           |
| `upload_excel_error` | Error al subir Excel              |
| `procesar_inicio`    | Inicio de procesamiento           |
| `procesar_exito`     | Procesamiento exitoso             |
| `procesar_parcial`   | Procesamiento con algunos errores |
| `procesar_error`     | Error en procesamiento            |
| `borrar_registro`    | Desactivación de registro        |
| `restaurar_registro` | Reactivación de registro         |
| `eliminar_excel`     | Eliminación de Excel pendiente   |

### 7.2 Datos Capturados

- Usuario que realizó la acción
- Fecha y hora
- Archivo involucrado
- ID del registro (si aplica)
- Resultado (éxito/error/parcial)
- Detalles en JSON
- IP del usuario

---

## 8. FORMATO DEL EXCEL

### 8.1 Columnas Obligatorias

| Columna                       | Campo BD               | Descripción            |
| ----------------------------- | ---------------------- | ----------------------- |
| `ID_1`                      | id_predio              | Número del predio      |
| `ID_2`                      | id_contribuyente       | Cédula o NIT           |
| `Razon_Social`              | razon_social           | Nombre completo         |
| `No_Acto_Administrativo`    | no_acto_administrativo | Número del acto        |
| `Fecha_Acto_Administrativo` | fecha_acto             | Fecha del acto          |
| `Fecha_Publicacion`         | fecha_publicacion      | Fecha publicación      |
| `Tipo_Actuacion`            | tipo_actuacion         | Tipo de actuación      |
| `Fecha_Desfijacion`         | fecha_desfijacion      | Fecha desfijación      |
| `Nombre_PDF`                | pdf_nombre_original    | Nombre del PDF asociado |

### 8.2 Columnas NO incluidas (vienen del usuario)

- ~~Organismo~~ → Se toma del usuario logueado
- ~~Area~~ → Se toma del usuario logueado

### 8.3 Columnas Extra (opcionales)

Cualquier columna adicional se guarda automáticamente en `mas_datos` como JSON.

**Ejemplo de columnas extra:**

- Direccion_Predio
- Telefono_Contacto
- Email
- Barrio
- Estrato
- Valor_Deuda
- Observaciones

---

## 9. APIs DISPONIBLES

### 9.1 API de Consulta (`api.php`)

```
GET /api.php?id={id_busqueda}
```

**Respuesta:**

```json
[
  {
    "idRegistro": "AA-20251215-153001-0001",
    "id": "PREDIO-001",
    "idAlterno": "1234567890",
    "razonSocial": "JUAN GARCÍA",
    "noActoAdministrativo": "RES-2025-001",
    "fechaPublicacion": "2025-12-15",
    "estado": "tramite",
    "masDatos": "{...}"
  }
]
```

### 9.2 API de Autenticación (`api_auth.php`)

```
POST /api_auth.php
action=login
username={usuario}
password={contraseña}
```

**Respuesta:**

```json
{
  "success": true,
  "token": "abc123...",
  "user": {
    "id": 1,
    "username": "admin",
    "nombre_completo": "Administrador",
    "organismo": "DPTO. ADM. HACIENDA",
    "area": "Subdirección de Sistemas"
  }
}
```

### 9.3 API de Carga (`api_carga_v3.php`)

| Acción                 | Método | Descripción                    |
| ----------------------- | ------- | ------------------------------- |
| `upload_excel`        | POST    | Subir archivo Excel             |
| `list_pending`        | GET     | Listar archivos pendientes      |
| `verify_pdfs`         | GET     | Verificar PDFs de un archivo    |
| `process_file`        | POST    | Procesar archivo                |
| `list_registros`      | GET     | Listar registros del usuario    |
| `deactivate_registro` | POST    | Desactivar registro             |
| `restore_registro`    | POST    | Restaurar registro              |
| `get_auditoria`       | GET     | Obtener historial de auditoría |

---

## 10. GUÍA DE PRUEBAS

### 10.1 Iniciar el Sistema

```bash
cd /Users/juanito/Desktop/ARCHIVOS\ TRABAJO/consultas_hacienda
php -S localhost:8000
```

### 10.2 Generar Datos de Prueba

```bash
# Excel completo con 2 registros
php generar_excel_completo.php
```

### 10.3 Prueba de Carga

1. Ir a `http://localhost:8000/login.html`
2. Login: `admin` / `123456`
3. Verificar que muestra Organismo y Área
4. Procesar archivo pendiente
5. Verificar en "Mis Registros"
6. Verificar en "Auditoría"

### 10.4 Prueba de Consulta

1. Ir a `http://localhost:8000/`
2. Buscar: `1234567890`
3. Verificar pestañas En Trámite/Histórico
4. Click en "📋 Info Extra" para ver columnas adicionales
5. Verificar descarga de PDF

---

## 11. ESTRUCTURA DE ARCHIVOS

```
consultas_hacienda/
│
├── 📄 index.html              # Página de consulta pública
├── 📄 login.html              # Página de login
├── 📄 carga.html              # Página de carga masiva
│
├── 📄 api.php                 # API de consulta
├── 📄 api_auth.php            # API de autenticación
├── 📄 api_carga_v3.php        # API de carga masiva
├── 📄 config.php              # Configuración de BD
│
├── 📄 script.js               # JavaScript de consultas
├── 📄 alerts.js               # Alertas personalizadas
├── 📄 styles.css              # Estilos globales
│
├── 📄 setup_database_v3.sql   # Estructura de BD
│
├── 📄 generar_excel_completo.php  # Generador de pruebas
│
├── 📁 uploads/                # Archivos por usuario
│   ├── 📁 admin/
│   │   ├── 📁 excel/          # Excel pendientes
│   │   ├── 📁 pdfs/           # PDFs pendientes
│   │   └── 📁 procesados/     # Archivos procesados
│   │
│   ├── 📁 tesoreria1/
│   ├── 📁 catastro1/
│   └── 📁 fiscalizacion1/
│
└── 📁 vendor/                 # Dependencias (PhpSpreadsheet)
```

---

## 📝 NOTAS FINALES

### Seguridad

- Contraseñas hasheadas con MD5 (usar bcrypt en producción)
- Sanitización de inputs
- Protección contra path traversal en usernames
- Solo registros activos visibles en consulta pública

### Mejoras Futuras Sugeridas

- [ ] Implementar JWT para autenticación
- [ ] Usar bcrypt/password_hash para contraseñas
- [ ] Agregar paginación en listados grandes
- [ ] Implementar roles y permisos granulares
- [ ] Agregar exportación de reportes
- [ ] Notificaciones por email

---

**Fecha de generación:** Diciembre 15, 2025
**Versión del sistema:** 3.0
