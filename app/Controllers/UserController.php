<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Models\UserModel;
use Throwable;

class UserController extends Controller
{
    public function index(string $currentPath = '/usuarios'): void
    {
        Auth::requireAdmin();
        $model = new UserModel();

        $this->render('users/index', [
            'pageTitle' => 'Usuarios',
            'currentPath' => $currentPath,
            'viewTag' => 'Administracion',
            'viewTitle' => 'Usuarios del sistema',
            'viewDescription' => 'Crea usuarios, asigna rol, cambia contrasenas y activa o desactiva accesos al sistema.',
            'users' => $model->all(),
            'error' => null,
            'success' => null,
        ]);
    }

    public function store(string $currentPath = '/usuarios'): void
    {
        Auth::requireAdmin();
        $model = new UserModel();
        $error = null;
        $success = null;

        try {
            $this->validateCreate();
            $model->create([
                'name' => trim((string) $_POST['name']),
                'email' => trim((string) $_POST['email']),
                'password' => (string) $_POST['password'],
                'role' => (string) ($_POST['role'] ?? 'operator'),
            ]);
            $success = 'Usuario creado correctamente.';
        } catch (Throwable $exception) {
            $error = $exception->getMessage();
        }

        $this->render('users/index', [
            'pageTitle' => 'Usuarios',
            'currentPath' => $currentPath,
            'viewTag' => 'Administracion',
            'viewTitle' => 'Usuarios del sistema',
            'viewDescription' => 'Crea usuarios, asigna rol, cambia contrasenas y activa o desactiva accesos al sistema.',
            'users' => $model->all(),
            'error' => $error,
            'success' => $success,
        ]);
    }

    public function updatePassword(string $currentPath = '/usuarios'): void
    {
        Auth::requireAdmin();
        $model = new UserModel();
        $error = null;
        $success = null;

        try {
            $id = (int) ($_POST['user_id'] ?? 0);
            $password = (string) ($_POST['password'] ?? '');

            if ($id <= 0 || strlen($password) < 6) {
                throw new \RuntimeException('Selecciona un usuario y una contrasena de minimo 6 caracteres.');
            }

            $model->updatePassword($id, $password);
            $success = 'Contrasena actualizada correctamente.';
        } catch (Throwable $exception) {
            $error = $exception->getMessage();
        }

        $this->render('users/index', [
            'pageTitle' => 'Usuarios',
            'currentPath' => $currentPath,
            'viewTag' => 'Administracion',
            'viewTitle' => 'Usuarios del sistema',
            'viewDescription' => 'Crea usuarios, asigna rol, cambia contrasenas y activa o desactiva accesos al sistema.',
            'users' => $model->all(),
            'error' => $error,
            'success' => $success,
        ]);
    }

    public function setStatus(string $currentPath = '/usuarios'): void
    {
        Auth::requireAdmin();
        $model = new UserModel();
        $id = (int) ($_POST['user_id'] ?? 0);
        $active = (string) ($_POST['active'] ?? '1') === '1';

        if ($id > 0 && $id !== Auth::id()) {
            $model->setActive($id, $active);
        }

        $this->redirect('/usuarios');
    }

    private function validateCreate(): void
    {
        $name = trim((string) ($_POST['name'] ?? ''));
        $email = trim((string) ($_POST['email'] ?? ''));
        $password = (string) ($_POST['password'] ?? '');

        if ($name === '') {
            throw new \RuntimeException('El nombre es obligatorio.');
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new \RuntimeException('Ingresa un correo valido.');
        }

        if (strlen($password) < 6) {
            throw new \RuntimeException('La contrasena debe tener minimo 6 caracteres.');
        }
    }
}

