CREATE TABLE IF NOT EXISTS attendance_import_templates (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    name VARCHAR(150) NOT NULL,
    source_system VARCHAR(120) NULL,
    fingerprint VARCHAR(255) NULL,
    config_json JSON NOT NULL,
    active TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY attendance_import_templates_fingerprint_index (fingerprint),
    KEY attendance_import_templates_active_index (active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS attendance_import_batches (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    template_id BIGINT UNSIGNED NULL,
    source_filename VARCHAR(255) NOT NULL,
    stored_path VARCHAR(500) NULL,
    file_hash CHAR(64) NOT NULL,
    period_start DATE NOT NULL,
    period_end DATE NOT NULL,
    raw_period_text VARCHAR(80) NULL,
    status ENUM('pending','processing','processed','failed','reprocessed') NOT NULL DEFAULT 'pending',
    uploaded_by_user_id BIGINT UNSIGNED NULL,
    processed_at DATETIME NULL,
    error_message TEXT NULL,
    summary_json JSON NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY attendance_import_batches_file_hash_unique (file_hash),
    KEY attendance_import_batches_template_index (template_id),
    KEY attendance_import_batches_period_index (period_start, period_end),
    KEY attendance_import_batches_status_index (status),
    CONSTRAINT attendance_import_batches_template_fk
        FOREIGN KEY (template_id) REFERENCES attendance_import_templates (id)
        ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS attendance_import_cells (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    import_batch_id BIGINT UNSIGNED NOT NULL,
    sheet_name VARCHAR(150) NOT NULL,
    excel_row_number INT NOT NULL,
    excel_column_number INT NOT NULL,
    cell_address VARCHAR(20) NULL,
    raw_value TEXT NULL,
    normalized_value TEXT NULL,
    target_table VARCHAR(120) NULL,
    target_field VARCHAR(120) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY attendance_import_cells_location_index (import_batch_id, sheet_name, excel_row_number, excel_column_number),
    CONSTRAINT attendance_import_cells_batch_fk
        FOREIGN KEY (import_batch_id) REFERENCES attendance_import_batches (id)
        ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS employee_department_assignments (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    employee_id BIGINT UNSIGNED NOT NULL,
    department_id BIGINT UNSIGNED NOT NULL,
    effective_from DATE NOT NULL,
    effective_to DATE NULL,
    source_import_batch_id BIGINT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY employee_department_assignments_employee_index (employee_id, effective_from, effective_to),
    KEY employee_department_assignments_department_index (department_id),
    KEY employee_department_assignments_batch_index (source_import_batch_id),
    CONSTRAINT employee_department_assignments_employee_fk
        FOREIGN KEY (employee_id) REFERENCES employees (id)
        ON UPDATE CASCADE ON DELETE CASCADE,
    CONSTRAINT employee_department_assignments_department_fk
        FOREIGN KEY (department_id) REFERENCES departments (id)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT employee_department_assignments_batch_fk
        FOREIGN KEY (source_import_batch_id) REFERENCES attendance_import_batches (id)
        ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
