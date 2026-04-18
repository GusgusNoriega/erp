<?php

use App\Controllers\AuthController;
use App\Controllers\AttendanceController;
use App\Controllers\UserController;
use App\Core\Router;

/** @var Router $router */
$router->get('/', static function (): void {
    header('Location: ' . url('/asistencia/importar'));
    exit;
});
$router->get('/login', [AuthController::class, 'loginForm']);
$router->post('/login', [AuthController::class, 'login']);
$router->post('/logout', [AuthController::class, 'logout']);
$router->get('/asistencia/turnos', [AttendanceController::class, 'schedules']);
$router->get('/asistencia/estadisticas', [AttendanceController::class, 'statistics']);
$router->get('/asistencia/marcaciones', [AttendanceController::class, 'punches']);
$router->get('/asistencia/importar', [AttendanceController::class, 'importForm']);
$router->post('/asistencia/importar', [AttendanceController::class, 'processImport']);
$router->get('/usuarios', [UserController::class, 'index']);
$router->post('/usuarios', [UserController::class, 'store']);
$router->post('/usuarios/password', [UserController::class, 'updatePassword']);
$router->post('/usuarios/status', [UserController::class, 'setStatus']);
