# Documentación Técnica del Sistema Central de Calibraciones

**Versión del Documento:** 1.0  
**Fecha:** 24 de noviembre de 2025  
**Responsable:** jmuro  

---

## 1. Visión General

El **Sistema Central de Calibraciones** es una plataforma web integral diseñada para gestionar el ciclo de vida, la trazabilidad y el cumplimiento normativo de los equipos de medición y prueba en el entorno de producción.

### 1.1 Arquitectura Tecnológica
-   **Backend**: PHP 8.3 (Nativo, sin frameworks).
-   **Base de Datos**: MariaDB 10.11.
-   **Frontend**: HTML5, CSS3 (Bootstrap 5 + estilos personalizados), JavaScript (Vanilla).
-   **Servidor Web**: Apache 2.4 sobre Ubuntu 24.04 LTS.
-   **Seguridad**: Sesiones PHP seguras, protección CSRF, validación estricta de tipos MIME en subidas.

### 1.2 Roles de Usuario
El sistema implementa un control de acceso basado en roles (RBAC) simple:
1.  **Admin (`admin`)**: Acceso total (CRUD). Puede registrar, editar, dar de baja y gestionar usuarios.
2.  **Consulta (`consulta`)**: Acceso de solo lectura. Puede ver dashboards, listados y descargar certificados/evidencias.

---

## 2. Módulo de Instrumentos de Medición

Este módulo gestiona el inventario activo de equipos de medición (multímetros, osciloscopios, torquímetros, etc.) y su estado de calibración.

### 2.1 Ciclo de Vida y Registro de Calibraciones
1.  **Alta (`add.php`)**: Registro inicial con ID manual (ej. `DU7310`), datos técnicos y carga de la primera calibración, certificado (PDF) y foto.
2.  **Seguimiento (`admin.php`)**: Monitoreo de fechas de vencimiento de las calibraciones.
3.  **Actualización y Registro de Calibración (`update.php` y `history.php`)**: Renovación de la calibración del equipo.
    -   *Registro Histórico inmutable*: Cada vez que se registra una nueva calibración en `update.php`, el sistema genera automáticamente un registro en la tabla `updatehistory`. Todo el historial de calibraciones de un equipo se consulta en la vista **Historial de instrumento** (`history.php`), garantizando la trazabilidad histórica de los certificados en el tiempo.
    -   *Regla de Negocio*: Al ingresar una nueva `CalDate`, el sistema calcula automáticamente la `DueDate` a **+1 año**.
    -   *Regla de Negocio*: El estado del instrumento se actualiza automáticamente a `calibrado` o `fuera de calibracion` de acuerdo con la fecha actual.
4.  **Baja (`move_out_of_use.php`)**: Retiro del instrumento a la tabla histórica `instrumentsoutofuse`.
5.  **Reactivación (`return_to_active.php`)**: Retorno de un instrumento dado de baja al inventario activo.

### 2.2 Estados del Instrumento
-   `calibrado`: Vigente (Fecha actual <= DueDate).
-   `en proceso de calibracion`: Enviado a laboratorio externo.
-   `fuera de calibracion`: Vencido (Fecha actual > DueDate).

### 2.3 Estructura de Datos (`instruments`)
| Campo | Descripción |
|-------|-------------|
| `ID` | Identificador único manual (ej. `DU7310`). |
| `CalDate` | Fecha de última calibración. |
| `DueDate` | Fecha de vencimiento (CalDate + 1 año). |
| `CertificateNo` | Número de certificado del proveedor. |
| `PdfPath` | Ruta al archivo PDF del certificado. |
| `Picture` | Ruta a la foto del instrumento. |

---

## 3. Módulo Golden Items

Gestión especializada para unidades "Golden" (patrones de referencia) utilizadas para validar estaciones de prueba.

### 3.1 Características Únicas
-   **IDs Automáticos**: Formato `GLDTE-XXX` generado secuencialmente.
-   **Inmutabilidad Parcial**: No se eliminan registros, solo se cambia su estado a `Scrap`.
-   **Historial Detallado**: Cada cambio (creación, edición, scrap) genera un registro en `golden_history`.

### 3.2 Flujo de Trabajo
1.  **Creación (`golden_add.php`)**:
    -   Asignación automática de ID.
    -   Captura de foto desde dispositivo móvil (soporte `capture="environment"`).
    -   Estado inicial: `Activo`.
2.  **Consulta (`golden_view.php`)**: Vista de solo lectura para usuarios de consulta.
3.  **Scrap (`golden_scrap.php`)**:
    -   Proceso formal de baja.
    -   Requiere motivo obligatorio.
    -   Cambia estado a `Scrap` y bloquea ediciones futuras.

### 3.3 Estructura de Datos (`golden_items`)
| Campo | Descripción |
|-------|-------------|
| `ID` | Clave primaria `GLDTE-XXX`. |
| `Status` | `Activo` o `Scrap`. |
| `Location` | Ubicación física (Línea/Almacén). |
| `Owner` | Responsable del resguardo. |

---

## 4. Módulo Linpu F1200

*Nota: Para detalles profundos de este módulo, consultar `DOCUMENTACION_LINPU_F1200.md`.*

Módulo dedicado a la calibración de plataformas ópticas Linpu F1200 mediante comparación directa.
-   **Tolerancia**: ±0.20 dB.
-   **Longitudes de onda**: 1310 nm / 1550 nm.
-   **Almacenamiento**: Tabla `linpu_calibrations` (inmutable).

---

## 5. Seguridad y Mantenimiento

### 5.1 Gestión de Archivos
-   **Ubicación**: `/var/www/html/calibraciones/uploads/{ID}/`.
-   **Validaciones**:
    -   Imágenes: Máx 5MB (8MB para Golden), tipos JPG/PNG/WEBP. Conversión automática de HEIC a JPG (si Imagick disponible).
    -   Documentos: Máx 20MB, solo PDF.
    -   Nombres de archivo sanitizados para evitar inyecciones en el sistema de archivos.

### 5.2 Base de Datos
-   **Tablas de Historial**:
    -   `updatehistory`: Auditoría de cambios en instrumentos generales.
    -   `golden_history`: Auditoría de ciclo de vida de Golden Items.
-   **Integridad**: Uso de transacciones SQL (`beginTransaction` / `commit`) para operaciones críticas.

### 5.3 Respaldo
Se recomienda respaldar diariamente:
1.  Dump de la base de datos MySQL.
2.  Carpeta `uploads/` completa.

---

## 6. Anexos

### 6.1 Estructura de Directorios
```
/var/www/html/calibraciones/
├── api/                # Endpoints AJAX (si aplica)
├── assets/             # Recursos estáticos (JS/CSS)
├── imagenes/           # Imágenes de interfaz
├── partials/           # Fragmentos de vistas (header, footer)
├── uploads/            # Archivos de usuario (organizados por ID)
├── config.php          # Configuración DB y constantes
├── index.php           # Dashboard principal
├── db_schema.sql       # Esquema inicial de BD
└── ... (archivos .php de módulos)
```
