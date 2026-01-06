# Documentación Técnica: Módulo de Auditoría Golden

**Versión del Documento:** 1.0  
**Stack Tecnológico:** PHP 8.1, MySQL (MariaDB), JavaScript (ES6+), Bootstrap 5

---

## 1. Arquitectura del Sistema

### Backend (PHP)
El núcleo del sistema reside en `golden_audit.php`. Este script maneja toda la lógica de negocio, incluyendo:
*   **Gestión de Estado (State Management):** Determina si una auditoría está 'Open' (En curso) o 'Closed' (Finalizada).
*   **Transacciones Atómicas:** Utiliza `PDO::beginTransaction()` para asegurar que la actualización de cabeceras, detalles de ítems y movimiento de inventario ocurra de forma ("todo o nada"), previniendo corrupción de datos.
*   **Validación de Logica de Negocio:**
    *   *Regla de Foto Nueva:* Compara el timestamp de la foto con la fecha de inicio de la auditoría.
    *   *Regla de Faltantes:* Marca automáticamente como 'Missing' los ítems no verificados al cerrar.

### Frontend (HTML5/JS)
La interfaz es una aplicación de página única (SPA-like) renderizada por el servidor.
*   **Diseño Adaptativo (Responsive):**
    *   Utiliza un sistema de Grid de Bootstrap que transforma filas de tabla (Desktop) en Tarjetas (Mobile/Tablet) automáticamente.
    *   Clases clave: `.item-card-col`, `d-none d-md-block`.
*   **Lógica de Cliente (JavaScript):**
    *   **Compresión de Imágenes:** Implementada con `OFFSCREEN CANVAS`. Las imágenes de cámara (5-10MB) se redimensionan y comprimen a JPEG 70% (<1MB) en el navegador *antes* de subir, reduciendo la carga del servidor y el uso de datos móviles.
    *   **Validación Reactiva:** Los checkboxes de verificación física (`.phys-chk`) están deshabilitados por defecto y solo se activan mediante eventos de JS cuando se detecta una foto válida o una justificación de "Faltante".

## 2. Flujo de Datos (Data Flow)

1.  **Inicio (GET):** Se carga la auditoría y el inventario maestro (`golden_items`).
2.  **Captura (JS):**
    *   Usuario toma foto -> `FileReader` genera vista previa -> `Canvas` comprime -> `DataTransfer` reemplaza el input del archivo.
    *   Checkbox "Físico" se habilita automáticamente.
3.  **Guardado (POST):**
    *   El formulario se envía como `multipart/form-data`.
    *   PHP recibe archivos, valida extensiones y tamaños.
    *   Actualiza `golden_audit_items` (histórico de esta auditoría).
    *   Actualiza `golden_items` (inventario vivo) con la nueva ubicación y foto.
4.  **Cierre:** Se calculan estadísticas finales y se congela la auditoría.

## 3. Seguridad y Estabilidad

*   **Protección SQL:** Uso estricto de Prepared Statements para prevenir inyección SQL.
*   **Escapado de Salida:** Función helper `h()` (htmlspecialchars) aplicada a todo output para prevenir XSS.
*   **Manejo de Errores:**
    *   Logs detallados en `uploads/audit_debug.log` para trazar problemas de carga.
    *   `try-catch` blocks globales para capturar errores críticos sin revelar stack traces al usuario final.

## 4. Estructura de Base de Datos
Tablas principales involucradas:
*   `golden_items`: Inventario maestro actual.
*   `golden_audits`: Cabecera de auditoría (ID, Fecha, Responsable, Totales).
*   `golden_audit_items`: Detalle de auditoría (Snapshot del estado de cada ítem en ese momento específico).

## 5. Guía de Mantenimiento

### Ajuste de Límites de Carga
Para ajustar el tamaño máximo de fotos aceptadas, modificar `.htaccess` o `php.ini`:
```ini
upload_max_filesize 10M
post_max_size 12M
```

### Depuración
Para habilitar la visualización de errores en pantalla (solo desarrollo), cambiar en `golden_audit.php`:
```php
ini_set('display_errors', '1');
```
**(¡Revertir a '0' en producción!)**
