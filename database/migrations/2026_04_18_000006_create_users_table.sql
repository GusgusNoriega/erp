CREATE TABLE IF NOT EXISTS users (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    name VARCHAR(150) NOT NULL,
    email VARCHAR(180) NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    role ENUM('admin','operator') NOT NULL DEFAULT 'operator',
    active TINYINT(1) NOT NULL DEFAULT 1,
    last_login_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY users_email_unique (email),
    KEY users_role_index (role),
    KEY users_active_index (active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO users (
    name,
    email,
    password_hash,
    role,
    active,
    created_at
) VALUES (
    'Elvis',
    'elvis@mail.com',
    '$2y$10$7TbAzb9JWcu5ckrfn5joBe9F3DCcYoovkm7v/CUS1u5Qk4LVrnqVy',
    'admin',
    1,
    NOW()
) ON DUPLICATE KEY UPDATE
    name = VALUES(name),
    role = 'admin',
    active = 1,
    updated_at = NOW();

