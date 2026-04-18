INSERT IGNORE INTO shift_types (
    external_code,
    name,
    category,
    paid,
    counts_as_attendance,
    active,
    created_at
) VALUES
    ('0', 'Turno normal', 'work', 1, 1, 1, NOW()),
    ('25', 'Permiso', 'permission', 1, 0, 1, NOW()),
    ('26', 'Salida', 'exit', 1, 1, 1, NOW()),
    ('Nulo', 'Vacaciones', 'vacation', 1, 0, 1, NOW());

INSERT IGNORE INTO absence_types (
    code,
    name,
    category,
    paid,
    requires_approval,
    affects_vacation_balance,
    active
) VALUES
    ('ABSENCE', 'Falta', 'absence', 0, 1, 0, 1),
    ('PERMISSION', 'Permiso', 'permission', 1, 1, 0, 1),
    ('VACATION', 'Vacaciones', 'vacation', 1, 1, 1, 1),
    ('EXIT', 'Salida autorizada', 'exit', 1, 1, 0, 1),
    ('MEDICAL', 'Incapacidad medica', 'medical', 1, 1, 0, 1),
    ('OTHER', 'Otra novedad', 'other', 0, 1, 0, 1);

