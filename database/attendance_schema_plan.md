# Plan de base de datos para asistencia desde Excel

Archivo revisado: `C:\Users\Gustavo\Downloads\1_StandardReport.xls`

Periodo detectado en el archivo: `2026-04-01` a `2026-04-10`.

Hojas detectadas:

- `Reporte de Turnos`: turnos programados por empleado y fecha. Incluye codigos especiales: `25-Permiso`, `26-Salida`, `Nulo-Vacaciones`.
- `Reporte Estadistico`: resumen por empleado para el periodo: horas normales, horas reales, retardos, salidas temprano, tiempo extra, dias asistidos, salidas, faltas, permisos, incrementos, deducciones y pago real.
- `Reporte de Asistencia`: eventos de marcacion por empleado y fecha. Algunas celdas traen varias horas juntas, por ejemplo `07:1107:1107:1119:00`; se deben separar con una expresion regular de horas `HH:MM`.
- `Reporte de Excepciones`: detalle diario por empleado: fecha, primer/segundo horario, retardos, salidas temprano, faltas, total de minutos y notas.

## Principios del diseno

1. No depender de una sola forma visual del Excel. El importador debe guardar el archivo original, detectar plantilla, leer periodo, empleados y fechas, y luego transformar a tablas normalizadas.
2. Separar datos maestros de datos transaccionales.
3. Guardar trazabilidad de cada importacion para poder reprocesar, auditar y evitar duplicados.
4. Guardar duraciones como minutos enteros, no como `TIME`, porque existen valores como `72:00` o `45:00`.
5. Guardar codigos externos como texto, no como entero, porque el campo `ID` del reloj/sistema puede ser codigo corto, documento o codigo alfanumerico futuro.
6. Mantener datos crudos cuando sea util, especialmente celdas originales, hoja, fila, columna y valor antes de transformar.

## Tablas maestras

### departments

Departamentos o grupos del empleado.

Campos:

- `id` BIGINT UNSIGNED PK
- `name` VARCHAR(150) NOT NULL
- `normalized_name` VARCHAR(150) NOT NULL
- `active` TINYINT(1) NOT NULL DEFAULT 1
- `created_at` DATETIME NOT NULL
- `updated_at` DATETIME NULL

Indices:

- UNIQUE `departments_normalized_name_unique` (`normalized_name`)

### employees

Personas detectadas en el Excel.

Campos:

- `id` BIGINT UNSIGNED PK
- `external_code` VARCHAR(80) NOT NULL
- `document_type` VARCHAR(30) NULL
- `document_number` VARCHAR(80) NULL
- `first_name` VARCHAR(120) NULL
- `last_name` VARCHAR(120) NULL
- `display_name` VARCHAR(180) NOT NULL
- `current_department_id` BIGINT UNSIGNED NULL FK -> `departments.id`
- `position_title` VARCHAR(150) NULL
- `employment_type` ENUM('employee','contractor','temporary','other') NOT NULL DEFAULT 'employee'
- `employment_status` ENUM('active','inactive','terminated','suspended') NOT NULL DEFAULT 'active'
- `gender` VARCHAR(30) NULL
- `birth_date` DATE NULL
- `hire_date` DATE NULL
- `termination_date` DATE NULL
- `phone` VARCHAR(60) NULL
- `email` VARCHAR(180) NULL
- `notes` TEXT NULL
- `active` TINYINT(1) NOT NULL DEFAULT 1
- `created_at` DATETIME NOT NULL
- `updated_at` DATETIME NULL

Indices:

- UNIQUE `employees_external_code_unique` (`external_code`)
- INDEX `employees_document_number_index` (`document_number`)
- INDEX `employees_display_name_index` (`display_name`)
- INDEX `employees_current_department_index` (`current_department_id`)
- INDEX `employees_employment_status_index` (`employment_status`)
- INDEX `employees_position_title_index` (`position_title`)

Nota: en el Excel revisado el campo `Nombre` trae valores como `BRYAN`, `CARLOS`, `BENITES`. Inicialmente conviene guardar `display_name` tal como llega y despues enriquecer nombres/apellidos si el sistema lo requiere.

### employee_department_assignments

Historial de departamento por empleado.

Campos:

- `id` BIGINT UNSIGNED PK
- `employee_id` BIGINT UNSIGNED NOT NULL FK -> `employees.id`
- `department_id` BIGINT UNSIGNED NOT NULL FK -> `departments.id`
- `effective_from` DATE NOT NULL
- `effective_to` DATE NULL
- `source_import_batch_id` BIGINT UNSIGNED NULL FK -> `attendance_import_batches.id`
- `created_at` DATETIME NOT NULL

Indices:

- INDEX (`employee_id`, `effective_from`, `effective_to`)
- INDEX (`department_id`)

### shift_types

Catalogo de turnos o codigos especiales.

Campos:

- `id` BIGINT UNSIGNED PK
- `external_code` VARCHAR(50) NOT NULL
- `name` VARCHAR(120) NOT NULL
- `category` ENUM('work','permission','exit','vacation','rest','absence','unknown') NOT NULL DEFAULT 'unknown'
- `paid` TINYINT(1) NOT NULL DEFAULT 0
- `counts_as_attendance` TINYINT(1) NOT NULL DEFAULT 0
- `active` TINYINT(1) NOT NULL DEFAULT 1
- `created_at` DATETIME NOT NULL
- `updated_at` DATETIME NULL

Registros iniciales sugeridos:

- `0` -> `Turno normal` / `work`
- `25` -> `Permiso` / `permission`
- `26` -> `Salida` / `exit`
- `Nulo` -> `Vacaciones` / `vacation`

Indices:

- UNIQUE (`external_code`)

### absence_types

Tipos de novedades o ausencias que el sistema controlara, tanto desde Excel como manualmente.

Campos:

- `id` BIGINT UNSIGNED PK
- `code` VARCHAR(50) NOT NULL
- `name` VARCHAR(120) NOT NULL
- `category` ENUM('absence','permission','vacation','leave','exit','medical','other') NOT NULL
- `paid` TINYINT(1) NOT NULL DEFAULT 0
- `requires_approval` TINYINT(1) NOT NULL DEFAULT 1
- `affects_vacation_balance` TINYINT(1) NOT NULL DEFAULT 0
- `active` TINYINT(1) NOT NULL DEFAULT 1

Indices:

- UNIQUE (`code`)

## Tablas de importacion

### attendance_import_templates

Plantillas de lectura para soportar Excels futuros con la misma informacion pero estructura distinta.

Campos:

- `id` BIGINT UNSIGNED PK
- `name` VARCHAR(150) NOT NULL
- `source_system` VARCHAR(120) NULL
- `fingerprint` VARCHAR(255) NULL
- `config_json` JSON NOT NULL
- `active` TINYINT(1) NOT NULL DEFAULT 1
- `created_at` DATETIME NOT NULL
- `updated_at` DATETIME NULL

Contenido esperado de `config_json`:

- nombres o alias de hojas
- filas donde buscar periodo
- filas de encabezado
- estrategia para hojas en formato ancho por fecha
- reglas para separar marcaciones `HH:MM`
- mapeos de columnas hacia campos internos

### attendance_import_batches

Cada carga de Excel.

Campos:

- `id` BIGINT UNSIGNED PK
- `template_id` BIGINT UNSIGNED NULL FK -> `attendance_import_templates.id`
- `source_filename` VARCHAR(255) NOT NULL
- `stored_path` VARCHAR(500) NULL
- `file_hash` CHAR(64) NOT NULL
- `period_start` DATE NOT NULL
- `period_end` DATE NOT NULL
- `raw_period_text` VARCHAR(80) NULL
- `status` ENUM('pending','processing','processed','failed','reprocessed') NOT NULL DEFAULT 'pending'
- `uploaded_by_user_id` BIGINT UNSIGNED NULL
- `processed_at` DATETIME NULL
- `error_message` TEXT NULL
- `summary_json` JSON NULL
- `created_at` DATETIME NOT NULL
- `updated_at` DATETIME NULL

Indices:

- UNIQUE (`file_hash`)
- INDEX (`period_start`, `period_end`)
- INDEX (`status`)

### attendance_import_cells

Opcional pero recomendado al inicio: guarda valores crudos relevantes para auditar errores de parsing.

Campos:

- `id` BIGINT UNSIGNED PK
- `import_batch_id` BIGINT UNSIGNED NOT NULL FK -> `attendance_import_batches.id`
- `sheet_name` VARCHAR(150) NOT NULL
- `excel_row_number` INT NOT NULL
- `excel_column_number` INT NOT NULL
- `cell_address` VARCHAR(20) NULL
- `raw_value` TEXT NULL
- `normalized_value` TEXT NULL
- `target_table` VARCHAR(120) NULL
- `target_field` VARCHAR(120) NULL
- `created_at` DATETIME NOT NULL

Indices:

- INDEX (`import_batch_id`, `sheet_name`, `row_number`, `column_number`)

## Tablas operativas de asistencia

### employee_day_schedules

Turno programado por empleado y fecha, desde `Reporte de Turnos`.

Campos:

- `id` BIGINT UNSIGNED PK
- `employee_id` BIGINT UNSIGNED NOT NULL FK -> `employees.id`
- `work_date` DATE NOT NULL
- `shift_type_id` BIGINT UNSIGNED NULL FK -> `shift_types.id`
- `department_id` BIGINT UNSIGNED NULL FK -> `departments.id`
- `raw_shift_code` VARCHAR(50) NULL
- `expected_minutes` INT NULL
- `source_import_batch_id` BIGINT UNSIGNED NOT NULL FK -> `attendance_import_batches.id`
- `source_sheet` VARCHAR(150) NULL
- `source_row` INT NULL
- `source_column` INT NULL
- `created_at` DATETIME NOT NULL
- `updated_at` DATETIME NULL

Indices:

- UNIQUE (`employee_id`, `work_date`, `source_import_batch_id`)
- INDEX (`work_date`)
- INDEX (`shift_type_id`)

### attendance_punches

Marcaciones individuales extraidas de `Reporte de Asistencia`.

Campos:

- `id` BIGINT UNSIGNED PK
- `employee_id` BIGINT UNSIGNED NOT NULL FK -> `employees.id`
- `work_date` DATE NOT NULL
- `punch_time` TIME NOT NULL
- `punch_datetime` DATETIME NULL
- `sequence_number` INT NOT NULL
- `raw_cell_value` TEXT NULL
- `source_import_batch_id` BIGINT UNSIGNED NOT NULL FK -> `attendance_import_batches.id`
- `source_sheet` VARCHAR(150) NULL
- `source_row` INT NULL
- `source_column` INT NULL
- `is_duplicate` TINYINT(1) NOT NULL DEFAULT 0
- `created_at` DATETIME NOT NULL

Indices:

- INDEX (`employee_id`, `work_date`, `punch_time`)
- INDEX (`work_date`)
- INDEX (`source_import_batch_id`)

Nota: el Excel tiene marcaciones repetidas dentro de la misma celda. No se deben borrar automaticamente; primero se guardan y se marca `is_duplicate` segun reglas del sistema.

### attendance_daily_records

Estado consolidado por empleado y dia. Esta es la tabla principal para consultas del sistema.

Campos:

- `id` BIGINT UNSIGNED PK
- `employee_id` BIGINT UNSIGNED NOT NULL FK -> `employees.id`
- `department_id` BIGINT UNSIGNED NULL FK -> `departments.id`
- `work_date` DATE NOT NULL
- `schedule_id` BIGINT UNSIGNED NULL FK -> `employee_day_schedules.id`
- `status` ENUM('present','absent','permission','vacation','exit','rest','holiday','incomplete','unknown') NOT NULL DEFAULT 'unknown'
- `first_in` TIME NULL
- `first_out` TIME NULL
- `second_in` TIME NULL
- `second_out` TIME NULL
- `expected_minutes` INT NULL
- `worked_minutes` INT NULL
- `late_minutes` INT NOT NULL DEFAULT 0
- `early_leave_minutes` INT NOT NULL DEFAULT 0
- `absence_minutes` INT NOT NULL DEFAULT 0
- `extra_workday_minutes` INT NOT NULL DEFAULT 0
- `extra_holiday_minutes` INT NOT NULL DEFAULT 0
- `notes` TEXT NULL
- `last_import_batch_id` BIGINT UNSIGNED NULL FK -> `attendance_import_batches.id`
- `created_at` DATETIME NOT NULL
- `updated_at` DATETIME NULL

Indices:

- UNIQUE (`employee_id`, `work_date`)
- INDEX (`work_date`, `status`)
- INDEX (`department_id`, `work_date`)

### attendance_daily_exceptions

Detalle tomado de `Reporte de Excepciones`.

Campos:

- `id` BIGINT UNSIGNED PK
- `daily_record_id` BIGINT UNSIGNED NOT NULL FK -> `attendance_daily_records.id`
- `employee_id` BIGINT UNSIGNED NOT NULL FK -> `employees.id`
- `work_date` DATE NOT NULL
- `first_schedule_in` TIME NULL
- `first_schedule_out` TIME NULL
- `second_schedule_in` TIME NULL
- `second_schedule_out` TIME NULL
- `late_minutes` INT NOT NULL DEFAULT 0
- `early_leave_minutes` INT NOT NULL DEFAULT 0
- `absence_minutes` INT NOT NULL DEFAULT 0
- `total_minutes` INT NOT NULL DEFAULT 0
- `notes` TEXT NULL
- `source_import_batch_id` BIGINT UNSIGNED NOT NULL FK -> `attendance_import_batches.id`
- `created_at` DATETIME NOT NULL

Indices:

- UNIQUE (`employee_id`, `work_date`, `source_import_batch_id`)
- INDEX (`daily_record_id`)

### attendance_day_events

Novedades por dia: faltas, permisos, vacaciones, salidas, licencias u otros eventos.

Campos:

- `id` BIGINT UNSIGNED PK
- `daily_record_id` BIGINT UNSIGNED NOT NULL FK -> `attendance_daily_records.id`
- `employee_id` BIGINT UNSIGNED NOT NULL FK -> `employees.id`
- `event_date` DATE NOT NULL
- `absence_type_id` BIGINT UNSIGNED NULL FK -> `absence_types.id`
- `event_type` ENUM('absence','permission','vacation','exit','leave','manual_adjustment','note') NOT NULL
- `minutes` INT NULL
- `days` DECIMAL(6,2) NULL
- `source` ENUM('excel','manual','system') NOT NULL DEFAULT 'excel'
- `source_import_batch_id` BIGINT UNSIGNED NULL FK -> `attendance_import_batches.id`
- `notes` TEXT NULL
- `created_at` DATETIME NOT NULL
- `updated_at` DATETIME NULL

Indices:

- INDEX (`employee_id`, `event_date`, `event_type`)
- INDEX (`daily_record_id`)

### attendance_period_summaries

Resumen por empleado y periodo desde `Reporte Estadistico`.

Campos:

- `id` BIGINT UNSIGNED PK
- `import_batch_id` BIGINT UNSIGNED NOT NULL FK -> `attendance_import_batches.id`
- `employee_id` BIGINT UNSIGNED NOT NULL FK -> `employees.id`
- `department_id` BIGINT UNSIGNED NULL FK -> `departments.id`
- `period_start` DATE NOT NULL
- `period_end` DATE NOT NULL
- `normal_work_minutes` INT NOT NULL DEFAULT 0
- `actual_work_minutes` INT NOT NULL DEFAULT 0
- `late_count` INT NOT NULL DEFAULT 0
- `late_minutes` INT NOT NULL DEFAULT 0
- `early_leave_count` INT NOT NULL DEFAULT 0
- `early_leave_minutes` INT NOT NULL DEFAULT 0
- `extra_workday_minutes` INT NOT NULL DEFAULT 0
- `extra_holiday_minutes` INT NOT NULL DEFAULT 0
- `scheduled_days` DECIMAL(6,2) NULL
- `attended_days` DECIMAL(6,2) NULL
- `exit_days` DECIMAL(6,2) NOT NULL DEFAULT 0
- `absence_days` DECIMAL(6,2) NOT NULL DEFAULT 0
- `permission_days` DECIMAL(6,2) NOT NULL DEFAULT 0
- `increment_notes` TEXT NULL
- `increment_overtime_amount` DECIMAL(12,2) NULL
- `increment_subsidy_amount` DECIMAL(12,2) NULL
- `deduction_late_early_amount` DECIMAL(12,2) NULL
- `deduction_permission_amount` DECIMAL(12,2) NULL
- `deduction_charge_amount` DECIMAL(12,2) NULL
- `real_payment_amount` DECIMAL(12,2) NULL
- `notes` TEXT NULL
- `created_at` DATETIME NOT NULL

Indices:

- UNIQUE (`import_batch_id`, `employee_id`)
- INDEX (`employee_id`, `period_start`, `period_end`)

## Relaciones principales

- `departments` 1:N `employee_department_assignments`
- `employees` 1:N `employee_department_assignments`
- `attendance_import_templates` 1:N `attendance_import_batches`
- `attendance_import_batches` 1:N `employee_day_schedules`
- `attendance_import_batches` 1:N `attendance_punches`
- `attendance_import_batches` 1:N `attendance_daily_exceptions`
- `attendance_import_batches` 1:N `attendance_period_summaries`
- `employees` 1:N `employee_day_schedules`
- `employees` 1:N `attendance_punches`
- `employees` 1:N `attendance_daily_records`
- `attendance_daily_records` 1:N `attendance_punches` por `employee_id + work_date`
- `attendance_daily_records` 1:N `attendance_daily_exceptions`
- `attendance_daily_records` 1:N `attendance_day_events`

## Flujo recomendado de importacion

1. Subir archivo y guardar:
   - nombre original
   - ruta de almacenamiento
   - hash SHA-256
   - usuario que subio
2. Detectar plantilla:
   - nombres de hojas
   - celda o texto de periodo
   - encabezados conocidos
   - patron de bloque por empleado en asistencia
3. Crear `attendance_import_batches` en estado `processing`.
4. Parsear periodo y normalizar fechas.
5. Leer empleados desde cualquiera de las hojas con columnas `ID`, `Nombre`, `Departamento`.
6. Hacer upsert de:
   - `departments`
   - `employees`
   - `employee_department_assignments`
7. Leer `Reporte de Turnos`:
   - convertir columnas de dias en registros `employee_day_schedules`
   - mapear codigos a `shift_types`
8. Leer `Reporte de Asistencia`:
   - extraer tokens `HH:MM`
   - crear un registro en `attendance_punches` por cada hora detectada
   - conservar celda original, fila y columna
9. Leer `Reporte de Excepciones`:
   - crear o actualizar `attendance_daily_records`
   - crear `attendance_daily_exceptions`
10. Leer `Reporte Estadistico`:
   - convertir duraciones `72:00`, `4:46`, etc. a minutos
   - parsear `Días Asistidos (Normal/Real)` como `scheduled_days` y `attended_days`
   - crear `attendance_period_summaries`
11. Consolidar `attendance_daily_records`:
   - asignar estado diario segun turno, excepcion y eventos
   - calcular primera entrada, primera salida, segunda entrada y segunda salida desde marcaciones
   - registrar faltas, permisos y vacaciones en `attendance_day_events`
12. Marcar importacion como `processed` o `failed`.

## Reglas de idempotencia

- Si el mismo archivo se sube dos veces, `file_hash` debe impedir duplicado exacto.
- Si se sube un archivo corregido para el mismo periodo, se debe crear otro `attendance_import_batches`.
- Las tablas crudas mantienen historico por `source_import_batch_id`.
- `attendance_daily_records` debe reflejar la ultima version aprobada por empleado y fecha.
- Para auditoria avanzada, se puede agregar una tabla `attendance_daily_record_versions` antes de sobrescribir consolidados.

## Validaciones importantes

- Periodo obligatorio.
- Empleado obligatorio: `external_code` y `display_name`.
- Fecha diaria debe estar dentro del periodo del archivo.
- Duraciones deben convertirse a minutos.
- Horas deben cumplir formato `HH:MM`.
- No eliminar marcaciones duplicadas sin regla explicita.
- Validar que los totales de `attendance_period_summaries` coincidan razonablemente con la suma de `attendance_daily_records`.
- Registrar errores por fila/celda para que el usuario pueda corregir el Excel o ajustar la plantilla.

## Consultas que este modelo soporta

- Asistencia diaria por empleado.
- Faltas por periodo, empleado o departamento.
- Vacaciones y permisos por empleado.
- Retardos y salidas temprano acumuladas.
- Marcaciones crudas por dia.
- Diferencia entre horas programadas y horas reales.
- Comparacion entre cargas del mismo periodo.
- Auditoria de que archivo genero cada dato.
