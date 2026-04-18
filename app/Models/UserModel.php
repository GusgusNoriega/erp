<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;
use PDO;
use RuntimeException;

class UserModel
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::connection();
    }

    /** @return array<int, array<string, mixed>> */
    public function all(): array
    {
        return $this->db
            ->query('SELECT id, name, email, role, active, last_login_at, created_at FROM users ORDER BY active DESC, name ASC')
            ->fetchAll();
    }

    /** @param array<string, string> $data */
    public function create(array $data): void
    {
        $email = mb_strtolower(trim($data['email']), 'UTF-8');
        $existing = $this->db->prepare('SELECT COUNT(*) FROM users WHERE email = :email');
        $existing->execute(['email' => $email]);

        if ((int) $existing->fetchColumn() > 0) {
            throw new RuntimeException('Ya existe un usuario registrado con ese correo.');
        }

        $statement = $this->db->prepare('
            INSERT INTO users (
                name,
                email,
                password_hash,
                role,
                active,
                created_at
            ) VALUES (
                :name,
                :email,
                :password_hash,
                :role,
                1,
                NOW()
            )
        ');
        $statement->execute([
            'name' => trim($data['name']),
            'email' => $email,
            'password_hash' => password_hash($data['password'], PASSWORD_DEFAULT),
            'role' => $data['role'] === 'admin' ? 'admin' : 'operator',
        ]);
    }

    public function updatePassword(int $id, string $password): void
    {
        $statement = $this->db->prepare('
            UPDATE users
            SET password_hash = :password_hash, updated_at = NOW()
            WHERE id = :id
        ');
        $statement->execute([
            'id' => $id,
            'password_hash' => password_hash($password, PASSWORD_DEFAULT),
        ]);
    }

    public function setActive(int $id, bool $active): void
    {
        $statement = $this->db->prepare('
            UPDATE users
            SET active = :active, updated_at = NOW()
            WHERE id = :id
        ');
        $statement->execute([
            'id' => $id,
            'active' => $active ? 1 : 0,
        ]);
    }
}
