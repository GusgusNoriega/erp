<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;

class AuthController extends Controller
{
    public function loginForm(): void
    {
        if (Auth::check()) {
            $this->redirect('/asistencia/importar');
        }

        $this->render('auth/login', [
            'pageTitle' => 'Iniciar sesion',
            'error' => null,
            'email' => '',
        ], 'layouts/auth');
    }

    public function login(): void
    {
        $email = trim((string) ($_POST['email'] ?? ''));
        $password = (string) ($_POST['password'] ?? '');

        if (Auth::attempt($email, $password)) {
            $this->redirect('/asistencia/importar');
        }

        $this->render('auth/login', [
            'pageTitle' => 'Iniciar sesion',
            'error' => 'Correo o contrasena incorrectos, o usuario inactivo.',
            'email' => $email,
        ], 'layouts/auth');
    }

    public function logout(): void
    {
        Auth::logout();
        $this->redirect('/login');
    }
}

