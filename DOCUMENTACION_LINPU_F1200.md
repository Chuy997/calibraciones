# Documentación Técnica: Módulo de Calibración Linpu F1200  
**Sistema Central de Calibraciones – Versión 1.0**  
**Fecha:** 21 de noviembre de 2025  
**Responsable:** jmuro (Ingeniería de Pruebas)  
**Sistema:** `/var/www/html/calibraciones` (Ubuntu 24.04, Apache, MariaDB 10.11, PHP 8.3)

---

## 1. Objetivo

Garantizar la **trazabilidad metrológica** y el **cumplimiento normativo** en la calibración de equipos **Linpu F1200 (光综合测试平台)** mediante comparación directa contra un equipo de referencia certificado (Chiclint OPM/VFL), bajo los principios de la **norma ISO/IEC 17025:2017**.

---

## 2. Alcance

- Equipos Linpu F1200 en uso en producción:
  - `C023000012111160007` (2 slots activos: 1 y 2)
  - `C023000012111160008` (3 slots activos: 1, 2 y 3)
- Cada **slot** y **canal** (puerto) se trata como un módulo independiente.
- Longitudes de onda calibradas: **1310 nm** y **1550 nm** (estándar en redes de fibra óptica).

---

## 3. Fundamento Normativo

| Norma | Requisito | Aplicación |
|------|----------|-----------|
| **IEC 61315:2017** | Calibración de medidores de potencia óptica | Define tolerancias para equipos de clase 1: **±0.20 dB** en 1310/1550 nm. |
| **ITU-T L.52** | Prácticas de medición en campo | Recomienda ±0.25 dB como límite operativo; se adopta **0.20 dB** para mayor rigor. |
| **ISO/IEC 17025:2017** | Requisitos generales de competencia | Exige trazabilidad, incertidumbre controlada, registros permanentes y personal competente. |

>  **Tolerancia aplicada:** **0.20 dB** (conservadora, alineada con IEC 61315 Clase 1).

---

## 4. Método de Calibración

### 4.1 Principio
Comparación directa **simultánea** del equipo bajo prueba (Linpu F1200) y un equipo de referencia **certificado** (Chiclint OPM/VFL), expuestos a la misma fuente de luz estable.

### 4.2 Procedimiento
1. Conectar fuente de luz estable a un divisor 1×2.
2. Conectar una salida al **equipo de referencia (Chiclint)**.
3. Conectar la otra salida al **Linpu F1200 (slot + canal específico)**.
4. Registrar lecturas de potencia óptica (dBm) en **1310 nm** y **1550 nm**.
5. Calcular desviación absoluta:  
   `|Lectura_ref − Lectura_Linpu|`
6. Comparar contra tolerancia: **0.20 dB**.
   - **≤ 0.20 dB** → **Aprobado**
   - **> 0.20 dB** → **Fuera de tolerancia** (requiere ajuste o verificación técnica)

### 4.3 Incertidumbre
- La incertidumbre combinada está dominada por la del equipo de referencia (certificado por proveedor acreditado).
- El método de comparación directa minimiza errores de alineación y estabilidad de fuente.
- **No se corrigen errores**; se declara estado conforme/no conforme.

---

## 5. Registro de Datos

### 5.1 Tabla en base de datos: `linpu_calibrations`
| Campo | Tipo | Descripción |
|------|------|------------|
| `serial_number` | VARCHAR(100) | Número de serie del Linpu F1200 |
| `slot` | TINYINT | Slot del módulo (1–3) |
| `channel` | TINYINT | Canal/puerto (1–4) |
| `wavelength_nm` | INT | 1310 o 1550 nm |
| `reference_reading_dbm` | DECIMAL(6,3) | Lectura del equipo de referencia |
| `dut_reading_dbm` | DECIMAL(6,3) | Lectura del Linpu F1200 |
| `deviation_db` | DECIMAL(5,3) | `ABS(ref − dut)` (calculado automáticamente) |
| `tolerance_db` | DECIMAL(3,2) | 0.20 (fijo, con comentario normativo) |
| `result` | ENUM | `aprobado` / `fuera de tolerancia` |
| `operator` | VARCHAR(100) | Usuario registrado en el sistema |
| `calibration_date` | DATETIME | Fecha/hora exacta del registro |
| `picture_path` | VARCHAR(255) | Foto del setup (opcional, respaldo visual) |
| `comments` | TEXT | Observaciones adicionales |

>  **Integridad:** No se permiten eliminaciones físicas (solo desactivación lógica en otros módulos). Los registros son inmutables una vez creados.

---

## 6. Control de Acceso y Seguridad

- **Solo usuarios con rol `admin`** pueden:
  - Registrar calibraciones (`linpu_add.php`)
  - Consultar listado consolidado (`linpu_admin.php`)
  - Ver historial detallado (`linpu_history.php`)
- **Autenticación centralizada** vía `login.php`.
- **CSRF protection**, validación de MIME en subidas, y `htmlspecialchars()` para prevenir XSS.
- **Sesiones seguras** con `httponly` y `secure` flags (configurado en `php.ini` del sistema).

---

## 7. Trazabilidad y Auditoría

- Cada registro incluye:
  - Operador identificado.
  - Fecha/hora exacta.
  - Foto del setup (opcional pero recomendada).
  - Comentarios libres.
- Historial completo por número de serie accesible en `linpu_history.php`.
- Todos los cambios están respaldados en **GitHub** (rama `main`), con estrategia de ramas (`main`/`dev`/`feature`).

---

## 8. Validación del Sistema

- El módulo fue validado contra:
  - `golden_add.php` y `torque/` como patrones de referencia funcional.
  - Normas IEC 61315 e ISO/IEC 17025.
- Pruebas realizadas:
  - Registro de ambas longitudes en un solo canal.
  - Filtro por resultado en `linpu_admin.php`.
  - Visualización de desviación en `linpu_history.php`.
- **No se usan dependencias externas** (sin CDN, sin frameworks).

---

## 9. Mantenimiento Futuro

- Para añadir nuevos equipos Linpu: editar array `$linpu_units` en `linpu_add.php`.
- Para ajustar tolerancia: modificar valor en código y actualizar comentario SQL.
- La estructura permite evolución a **API REST** o **interfaz SCPI** en fases futuras.

---

## 10. Aprobación

Este procedimiento y su implementación digital cumplen con los requisitos de:
- **ISO/IEC 17025:2017**, secciones 6.4 (Equipamiento), 7.7 (Aseguramiento de validez), y 8.8 (Gestión de registros).
- **IEC 61315:2017**, sección 5 (Procedimiento de calibración).
- Política interna de **no eliminación de registros** y **trazabilidad total**.

**Firma del responsable de calidad:**  
`jmuro`  
`asdrubalmuro90@gmail.com`  
21/11/2025

---

 **Nota para auditoría:**  
Todos los archivos del módulo están en:  
`/var/www/html/calibraciones/linpu_*.php`  
La tabla SQL incluye comentario técnico sobre la tolerancia.  
El código fuente está versionado en GitHub bajo el usuario `Chuy997`.
