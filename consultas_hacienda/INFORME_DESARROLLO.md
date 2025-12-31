# Informe de Desarrollo
## Sistema de Consulta de Actos Administrativos
### Departamento Administrativo de Hacienda - Alcaldía de Santiago de Cali

---

**Fecha:** Diciembre 2025  
**Versión:** 1.0

---

## 1. Resumen Ejecutivo

Se desarrolló un sistema web completo para la consulta pública de actos administrativos y la carga masiva de datos por parte del personal autorizado del Departamento de Hacienda.

El sistema consta de dos módulos principales:
- **Módulo de Consulta Pública:** Permite a cualquier ciudadano consultar actos administrativos usando su cédula o número de predio.
- **Módulo de Carga Masiva:** Permite al personal autorizado cargar datos desde archivos Excel con validación de PDF asociado.

---

## 2. Tecnologías Utilizadas

| Tecnología | Uso |
|------------|-----|
| PHP 8.x | Backend y API REST |
| MySQL | Base de datos |
| HTML5/CSS3 | Frontend |
| JavaScript (Vanilla) | Interactividad |
| PhpSpreadsheet | Lectura de archivos Excel |
| Composer | Gestión de dependencias PHP |

---

## 3. Arquitectura del Sistema

### 3.1 Estructura de Archivos

```
consultas_hacienda/
├── index.html          # Página principal de consultas
├── login.html          # Página de inicio de sesión
├── carga.html          # Módulo de carga masiva
├── api.php             # API de consultas
├── api_carga_v2.php    # API de carga masiva
├── config.php          # Configuración de base de datos
├── styles.css          # Estilos globales
├── script.js           # Lógica de consultas
├── alerts.js           # Sistema de alertas personalizadas
├── uploads/
│   ├── excel/          # Archivos Excel pendientes
│   ├── pdfs/           # PDFs asociados (vía FTP)
│   └── procesados/     # Archivos ya procesados
└── vendor/             # Dependencias (Composer)
```

**[CAPTURA: Estructura de carpetas en el explorador de archivos]**

---

## 4. Base de Datos

### 4.1 Esquema de la Tabla Principal

```sql
CREATE TABLE actos_administrativos (
  id_interno INT AUTO_INCREMENT PRIMARY KEY,
  id_predio VARCHAR(50) NOT NULL,
  id_contribuyente VARCHAR(50) NOT NULL,
  razon_social VARCHAR(255) NOT NULL,
  no_acto_administrativo VARCHAR(100) NOT NULL,
  fecha_acto DATE NOT NULL,
  fecha_publicacion DATE NOT NULL,
  tipo_actuacion VARCHAR(255) NOT NULL,
  organismo VARCHAR(150) NOT NULL,
  area VARCHAR(255) NOT NULL,
  fecha_desfijacion DATE NULL,
  mas_datos JSON NULL,
  estado ENUM('tramite', 'finalizado') NOT NULL DEFAULT 'tramite',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);
```

**[CAPTURA: Tabla en MySQL Workbench o phpMyAdmin]**

### 4.2 Lógica de Estados

| Estado | Condición |
|--------|-----------|
| **En Trámite** | `fecha_desfijacion` es NULL o es una fecha futura |
| **Histórico** | `fecha_desfijacion` es una fecha pasada |

---

## 5. Módulo de Consulta Pública

### 5.1 Funcionalidades

- ✅ Búsqueda por cédula de contribuyente
- ✅ Búsqueda por número de predio
- ✅ Separación de resultados en pestañas (En Trámite / Histórico)
- ✅ Vista detallada de cada acto administrativo
- ✅ Impresión de actos administrativos
- ✅ Filtros en histórico por año y dependencia

**[CAPTURA: Página principal de consultas]**

### 5.2 Interfaz de Búsqueda

La interfaz permite ingresar un ID y muestra los resultados organizados en dos pestañas:

**[CAPTURA: Resultados de búsqueda con pestañas]**

### 5.3 Tabla de Históricos con Filtros

Los resultados históricos se muestran en formato tabla con filtros:

```javascript
// Filtros disponibles
- Año de publicación (2020-2025)
- Dependencia/Área
- Botón "Aplicar Filtros" para optimizar consultas grandes
```

**[CAPTURA: Tabla de históricos con filtros aplicados]**

### 5.4 Modal de Detalle

Al hacer clic en "Ver más" se abre un modal con toda la información del acto:

**[CAPTURA: Modal de detalle de un acto administrativo]**

---

## 6. Módulo de Carga Masiva

### 6.1 Flujo de Trabajo

```
1. Usuario sube archivo Excel → Se guarda en /uploads/excel/
2. Usuario sube PDF vía FTP → Se guarda en /uploads/pdfs/
3. Sistema verifica que el PDF existe
4. Usuario procesa el archivo → Datos van a la BD
5. Archivos se mueven a /uploads/procesados/
```

**[CAPTURA: Diagrama de flujo o interfaz de carga]**

### 6.2 Interfaz de Carga

**[CAPTURA: Página de carga masiva con archivos pendientes]**

### 6.3 Asociación Excel-PDF

El sistema identifica el PDF asociado por nombre de archivo:

| Excel Subido | PDF Esperado |
|--------------|--------------|
| `datos_2025-12-10_14-30-00.xlsx` | `datos_2025-12-10_14-30-00.xlsx.pdf` |
| `datos_2025-12-10_14-30-00.xlsx` | `datos_2025-12-10_14-30-00.pdf` |
| `datos_2025-12-10_14-30-00.xlsx` | `datos.pdf` |

### 6.4 Procesamiento con Progreso

```javascript
// El sistema muestra progreso en tiempo real
- Total de archivos
- Archivos procesados
- Exitosos
- Fallidos
```

**[CAPTURA: Barra de progreso durante procesamiento]**

---

## 7. Sistema de Autenticación

### 7.1 Flujo de Login

```
1. Usuario accede a /login.html
2. Ingresa credenciales
3. Sistema valida (actualmente modo prueba: admin/admin123)
4. Se guarda sesión en localStorage
5. Redirección a /carga.html
```

**[CAPTURA: Página de login]**

### 7.2 Persistencia de Sesión

- La sesión se mantiene entre navegaciones
- El usuario logueado ve su nombre en la barra superior
- Opción de "Cerrar Sesión" con confirmación

**[CAPTURA: Barra de navegación con usuario logueado]**

### 7.3 Control de Acceso

| Página | Acceso |
|--------|--------|
| Consultas (index.html) | Público |
| Carga Masiva (carga.html) | Solo autenticados |

---

## 8. Sistema de Alertas Personalizadas

Se implementó un sistema de alertas con estilo institucional que reemplaza los `alert()` y `confirm()` nativos del navegador.

### 8.1 Tipos de Alertas

```javascript
// Alerta informativa
showAlert('Mensaje', 'info');

// Alerta de éxito
showAlert('Operación exitosa', 'success');

// Alerta de error
showAlert('Error en la operación', 'error');

// Alerta de advertencia
showAlert('Advertencia', 'warning');

// Confirmación
const result = await showConfirm('¿Está seguro?', 'Confirmar');

// Toast (notificación pequeña)
showToast('Archivo eliminado', 'success');
```

**[CAPTURA: Ejemplo de alerta personalizada]**

**[CAPTURA: Ejemplo de modal de confirmación]**

---

## 9. Diseño Visual

### 9.1 Estilo Institucional

Se aplicó un diseño institucional con:
- Colores corporativos (azul #3366cc, verde #28a745)
- Barra superior con franja de gobierno
- Logo institucional
- Tipografía profesional

**[CAPTURA: Header institucional]**

### 9.2 Responsive Design

El sistema es completamente responsive y funciona en:
- Computadores de escritorio
- Tablets
- Teléfonos móviles

**[CAPTURA: Vista móvil de la aplicación]**

---

## 10. API REST

### 10.1 Endpoints de Consulta (api.php)

```
GET /api.php?id={id_contribuyente_o_predio}

Respuesta:
{
  "tramite": [...],    // Actos en trámite
  "historial": [...]   // Actos históricos
}
```

### 10.2 Endpoints de Carga (api_carga_v2.php)

| Acción | Método | Descripción |
|--------|--------|-------------|
| `upload_excel` | POST | Sube archivo Excel |
| `list_pending` | GET | Lista archivos pendientes |
| `process_file` | POST | Procesa archivo a BD |
| `delete_file` | POST | Elimina archivo |

---

## 11. Formato de Archivo Excel

### 11.1 Columnas Requeridas

| Columna | Descripción | Ejemplo |
|---------|-------------|---------|
| ID_1 | ID del Predio | PRD-000001 |
| ID_2 | ID del Contribuyente | 12345678-9 |
| Razon_Social | Nombre/Razón Social | JUAN GARCIA |
| No_Acto_Administrativo | Número del acto | RES-2025-0001 |
| Fecha_Acto_Administrativo | Fecha del acto | 2025-01-15 |
| Fecha_Publicacion | Fecha de publicación | 2025-01-20 |
| Tipo_Actuacion | Tipo de actuación | Resolución de Cobro |
| Organismo | Organismo emisor | DPTO. HACIENDA |
| Area | Área/Dependencia | Cobro Coactivo |
| Fecha_Desfijacion | Fecha desfijación | 2025-01-25 |

**[CAPTURA: Ejemplo de archivo Excel con datos]**

---

## 12. Scripts de Prueba

Se crearon scripts PHP para generar datos de prueba:

### 12.1 Generador de Datos Masivos
```bash
php generar_datos_prueba.php 5000  # Genera 5000 registros
```

### 12.2 Generador de Históricos para Filtros
```bash
php generar_historicos_v2.php  # Genera datos con mismo ID, múltiples años
```

---

## 13. Instalación y Configuración

### 13.1 Requisitos
- PHP 8.0+
- MySQL 5.7+
- Composer

### 13.2 Pasos de Instalación

```bash
# 1. Clonar/copiar archivos al servidor

# 2. Instalar dependencias
composer install

# 3. Configurar base de datos en config.php
$db_host = 'localhost';
$db_name = 'hacienda_db';
$db_user = 'root';
$db_pass = '';

# 4. Ejecutar script SQL
mysql -u root < setup_database_v2.sql

# 5. Iniciar servidor (desarrollo)
php -S localhost:8000
```

---

## 14. Conclusiones

El sistema desarrollado cumple con los siguientes objetivos:

✅ Consulta pública de actos administrativos  
✅ Separación de trámites activos e históricos  
✅ Filtros avanzados por año y dependencia  
✅ Carga masiva de datos con validación  
✅ Asociación de documentos PDF  
✅ Autenticación para módulo administrativo  
✅ Diseño institucional profesional  
✅ Sistema de alertas personalizadas  
✅ Interfaz responsive  

---

## 15. Próximos Pasos (Sugeridos)

- [ ] Integrar API de autenticación real
- [ ] Conectar con base de datos de producción
- [ ] Implementar descarga de PDF desde el sistema
- [ ] Agregar auditoría de acciones de usuarios
- [ ] Implementar búsqueda avanzada con más filtros

---

**Documento generado:** Diciembre 2025  
**Desarrollado con:** Cursor AI + Claude


