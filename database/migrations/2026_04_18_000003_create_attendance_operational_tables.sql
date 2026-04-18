CREATE TABLE IF NOT EXISTS employee_day_schedules (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    employee_id BIGINT UNSIGNED NOT NULL,
    work_date DATE NOT NULL,
    shift_type_id BIGINT UNSIGNED NULL,
    department_id BIGINT UNSIGNED NULL,
    raw_shift_code VARCHAR(50) NULL,
    expected_minutes INT NULL,
    source_import_batch_id BIGINT UNSIGNED NOT NULL,
    source_sheet VARCHAR(150) NULL,
    source_row INT NULL,
    source_column INT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY employee_day_schedules_employee_date_batch_unique (employee_id, work_date, source_import_batch_id),
    KEY employee_day_schedules_date_index (work_date),
    KEY employee_day_schedules_shift_type_index (shift_type_id),
    KEY employee_day_schedules_department_date_index (department_id, work_date),
    KEY employee_day_schedules_batch_index (source_import_batch_id),
    CONSTRAINT employee_day_schedules_employee_fk
        FOREIGN KEY (employee_id) REFERENCES employees (id)
        ON UPDATE CASCADE ON DELETE CASCADE,
    CONSTRAINT employee_day_schedules_shift_type_fk
        FOREIGN KEY (shift_type_id) REFERENCES shift_types (id)
        ON UPDATE CASCADE ON DELETE SET NULL,
    CONSTRAINT employee_day_schedules_department_fk
        FOREIGN KEY (department_id) REFERENCES departments (id)
        ON UPDATE CASCADE ON DELETE SET NULL,
    CONSTRAINT employee_day_schedules_batch_fk
        FOREIGN KEY (source_import_batch_id) REFERENCES attendance_import_batches (id)
        ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS attendance_punches (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    employee_id BIGINT UNSIGNED NOT NULL,
    work_date DATE NOT NULL,
    punch_time TIME NOT NULL,
    punch_datetime DATETIME NULL,
    sequence_number INT NOT NULL,
    raw_cell_value TEXT NULL,
    source_import_batch_id BIGINT UNSIGNED NOT NULL,
    source_sheet VARCHAR(150) NULL,
    source_row INT NULL,
    source_column INT NULL,
    is_duplicate TINYINT(1) NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY attendance_punches_employee_date_time_index (employee_id, work_date, punch_time),
    KEY attendance_punches_date_index (work_date),
    KEY attendance_punches_batch_index (source_import_batch_id),
    CONSTRAINT attendance_punches_employee_fk
        FOREIGN KEY (employee_id) REFERENCES employees (id)
        ON UPDATE CASCADE ON DELETE CASCADE,
    CONSTRAINT attendance_punches_batch_fk
        FOREIGN KEY (source_import_batch_id) REFERENCES attendance_import_batches (id)
        ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS attendance_daily_records (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    employee_id BIGINT UNSIGNED NOT NULL,
    department_id BIGINT UNSIGNED NULL,
    work_date DATE NOT NULL,
    schedule_id BIGINT UNSIGNED NULL,
    status ENUM('present','absent','permission','vacation','exit','rest','holiday','incomplete','unknown') NOT NULL DEFAULT 'unknown',
    first_in TIME NULL,
    first_out TIME NULL,
    second_in TIME NULL,
    second_out TIME NULL,
    expected_minutes INT NULL,
    worked_minutes INT NULL,
    late_minutes INT NOT NULL DEFAULT 0,
    early_leave_minutes INT NOT NULL DEFAULT 0,
    absence_minutes INT NOT NULL DEFAULT 0,
    extra_workday_minutes INT NOT NULL DEFAULT 0,
    extra_holiday_minutes INT NOT NULL DEFAULT 0,
    notes TEXT NULL,
    last_import_batch_id BIGINT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY attendance_daily_records_employee_date_unique (employee_id, work_date),
    KEY attendance_daily_records_date_status_index (work_date, status),
    KEY attendance_daily_records_department_date_index (department_id, work_date),
    KEY attendance_daily_records_schedule_index (schedule_id),
    KEY attendance_daily_records_batch_index (last_import_batch_id),
    CONSTRAINT attendance_daily_records_employee_fk
        FOREIGN KEY (employee_id) REFERENCES employees (id)
        ON UPDATE CASCADE ON DELETE CASCADE,
    CONSTRAINT attendance_daily_records_department_fk
        FOREIGN KEY (department_id) REFERENCES departments (id)
        ON UPDATE CASCADE ON DELETE SET NULL,
    CONSTRAINT attendance_daily_records_schedule_fk
        FOREIGN KEY (schedule_id) REFERENCES employee_day_schedules (id)
        ON UPDATE CASCADE ON DELETE SET NULL,
    CONSTRAINT attendance_daily_records_batch_fk
        FOREIGN KEY (last_import_batch_id) REFERENCES attendance_import_batches (id)
        ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS attendance_daily_exceptions (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    daily_record_id BIGINT UNSIGNED NOT NULL,
    employee_id BIGINT UNSIGNED NOT NULL,
    work_date DATE NOT NULL,
    first_schedule_in TIME NULL,
    first_schedule_out TIME NULL,
    second_schedule_in TIME NULL,
    second_schedule_out TIME NULL,
    late_minutes INT NOT NULL DEFAULT 0,
    early_leave_minutes INT NOT NULL DEFAULT 0,
    absence_minutes INT NOT NULL DEFAULT 0,
    total_minutes INT NOT NULL DEFAULT 0,
    notes TEXT NULL,
    source_import_batch_id BIGINT UNSIGNED NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY attendance_daily_exceptions_employee_date_batch_unique (employee_id, work_date, source_import_batch_id),
    KEY attendance_daily_exceptions_daily_record_index (daily_record_id),
    KEY attendance_daily_exceptions_batch_index (source_import_batch_id),
    CONSTRAINT attendance_daily_exceptions_daily_record_fk
        FOREIGN KEY (daily_record_id) REFERENCES attendance_daily_records (id)
        ON UPDATE CASCADE ON DELETE CASCADE,
    CONSTRAINT attendance_daily_exceptions_employee_fk
        FOREIGN KEY (employee_id) REFERENCES employees (id)
        ON UPDATE CASCADE ON DELETE CASCADE,
    CONSTRAINT attendance_daily_exceptions_batch_fk
        FOREIGN KEY (source_import_batch_id) REFERENCES attendance_import_batches (id)
        ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS attendance_day_events (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    daily_record_id BIGINT UNSIGNED NOT NULL,
    employee_id BIGINT UNSIGNED NOT NULL,
    event_date DATE NOT NULL,
    absence_type_id BIGINT UNSIGNED NULL,
    event_type ENUM('absence','permission','vacation','exit','leave','manual_adjustment','note') NOT NULL,
    minutes INT NULL,
    days DECIMAL(6,2) NULL,
    source ENUM('excel','manual','system') NOT NULL DEFAULT 'excel',
    source_import_batch_id BIGINT UNSIGNED NULL,
    notes TEXT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY attendance_day_events_employee_date_type_index (employee_id, event_date, event_type),
    KEY attendance_day_events_daily_record_index (daily_record_id),
    KEY attendance_day_events_absence_type_index (absence_type_id),
    KEY attendance_day_events_batch_index (source_import_batch_id),
    CONSTRAINT attendance_day_events_daily_record_fk
        FOREIGN KEY (daily_record_id) REFERENCES attendance_daily_records (id)
        ON UPDATE CASCADE ON DELETE CASCADE,
    CONSTRAINT attendance_day_events_employee_fk
        FOREIGN KEY (employee_id) REFERENCES employees (id)
        ON UPDATE CASCADE ON DELETE CASCADE,
    CONSTRAINT attendance_day_events_absence_type_fk
        FOREIGN KEY (absence_type_id) REFERENCES absence_types (id)
        ON UPDATE CASCADE ON DELETE SET NULL,
    CONSTRAINT attendance_day_events_batch_fk
        FOREIGN KEY (source_import_batch_id) REFERENCES attendance_import_batches (id)
        ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS attendance_period_summaries (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    import_batch_id BIGINT UNSIGNED NOT NULL,
    employee_id BIGINT UNSIGNED NOT NULL,
    department_id BIGINT UNSIGNED NULL,
    period_start DATE NOT NULL,
    period_end DATE NOT NULL,
    normal_work_minutes INT NOT NULL DEFAULT 0,
    actual_work_minutes INT NOT NULL DEFAULT 0,
    late_count INT NOT NULL DEFAULT 0,
    late_minutes INT NOT NULL DEFAULT 0,
    early_leave_count INT NOT NULL DEFAULT 0,
    early_leave_minutes INT NOT NULL DEFAULT 0,
    extra_workday_minutes INT NOT NULL DEFAULT 0,
    extra_holiday_minutes INT NOT NULL DEFAULT 0,
    scheduled_days DECIMAL(6,2) NULL,
    attended_days DECIMAL(6,2) NULL,
    exit_days DECIMAL(6,2) NOT NULL DEFAULT 0,
    absence_days DECIMAL(6,2) NOT NULL DEFAULT 0,
    permission_days DECIMAL(6,2) NOT NULL DEFAULT 0,
    increment_notes TEXT NULL,
    increment_overtime_amount DECIMAL(12,2) NULL,
    increment_subsidy_amount DECIMAL(12,2) NULL,
    deduction_late_early_amount DECIMAL(12,2) NULL,
    deduction_permission_amount DECIMAL(12,2) NULL,
    deduction_charge_amount DECIMAL(12,2) NULL,
    real_payment_amount DECIMAL(12,2) NULL,
    notes TEXT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY attendance_period_summaries_batch_employee_unique (import_batch_id, employee_id),
    KEY attendance_period_summaries_employee_period_index (employee_id, period_start, period_end),
    KEY attendance_period_summaries_department_period_index (department_id, period_start, period_end),
    CONSTRAINT attendance_period_summaries_batch_fk
        FOREIGN KEY (import_batch_id) REFERENCES attendance_import_batches (id)
        ON UPDATE CASCADE ON DELETE CASCADE,
    CONSTRAINT attendance_period_summaries_employee_fk
        FOREIGN KEY (employee_id) REFERENCES employees (id)
        ON UPDATE CASCADE ON DELETE CASCADE,
    CONSTRAINT attendance_period_summaries_department_fk
        FOREIGN KEY (department_id) REFERENCES departments (id)
        ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

