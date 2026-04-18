CREATE TABLE IF NOT EXISTS departments (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    name VARCHAR(150) NOT NULL,
    normalized_name VARCHAR(150) NOT NULL,
    active TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY departments_normalized_name_unique (normalized_name),
    KEY departments_active_index (active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS employees (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    external_code VARCHAR(80) NOT NULL,
    document_type VARCHAR(30) NULL,
    document_number VARCHAR(80) NULL,
    first_name VARCHAR(120) NULL,
    last_name VARCHAR(120) NULL,
    display_name VARCHAR(180) NOT NULL,
    current_department_id BIGINT UNSIGNED NULL,
    position_title VARCHAR(150) NULL,
    employment_type ENUM('employee','contractor','temporary','other') NOT NULL DEFAULT 'employee',
    employment_status ENUM('active','inactive','terminated','suspended') NOT NULL DEFAULT 'active',
    gender VARCHAR(30) NULL,
    birth_date DATE NULL,
    hire_date DATE NULL,
    termination_date DATE NULL,
    phone VARCHAR(60) NULL,
    email VARCHAR(180) NULL,
    notes TEXT NULL,
    active TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY employees_external_code_unique (external_code),
    KEY employees_document_number_index (document_number),
    KEY employees_display_name_index (display_name),
    KEY employees_current_department_index (current_department_id),
    KEY employees_employment_status_index (employment_status),
    KEY employees_position_title_index (position_title),
    CONSTRAINT employees_current_department_fk
        FOREIGN KEY (current_department_id) REFERENCES departments (id)
        ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS shift_types (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    external_code VARCHAR(50) NOT NULL,
    name VARCHAR(120) NOT NULL,
    category ENUM('work','permission','exit','vacation','rest','absence','unknown') NOT NULL DEFAULT 'unknown',
    paid TINYINT(1) NOT NULL DEFAULT 0,
    counts_as_attendance TINYINT(1) NOT NULL DEFAULT 0,
    active TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY shift_types_external_code_unique (external_code),
    KEY shift_types_category_index (category),
    KEY shift_types_active_index (active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS absence_types (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    code VARCHAR(50) NOT NULL,
    name VARCHAR(120) NOT NULL,
    category ENUM('absence','permission','vacation','leave','exit','medical','other') NOT NULL,
    paid TINYINT(1) NOT NULL DEFAULT 0,
    requires_approval TINYINT(1) NOT NULL DEFAULT 1,
    affects_vacation_balance TINYINT(1) NOT NULL DEFAULT 0,
    active TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY absence_types_code_unique (code),
    KEY absence_types_category_index (category),
    KEY absence_types_active_index (active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

