<?php
// public/index.php

// Iniciar/Reanudar sesión
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Autoloader, Config, etc.
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../config/database.php'; // Asegúrate que WP_INSTALL_PATH esté definido aquí si usas wp-load.php

// Importar Clases
use Patriciomelor\VnChurchFinances\Lib\Database;     // Ajusta namespace
use Patriciomelor\VnChurchFinances\Controllers\AuthController; // Ajusta namespace
use Patriciomelor\VnChurchFinances\Controllers\UploadController; // Ajusta namespace
// --- Conexión BD (Ya la probamos) ---
$db = Database::getConnection();
if (!$db) {
    // Mostrar error crítico si no hay BD
    die("Error fatal: No se pudo conectar a la base de datos. Revise la configuración y los logs del servidor.");
}

// --- Instanciar Controladores ---
$authController = new AuthController();
$uploadController = new UploadController();
// --- Ruteo Básico ---
$action = $_GET['action'] ?? 'default'; // Acción por defecto

// --- Protección de Rutas ---
$isLoggedIn = isset($_SESSION['user_id']);

// Rutas que requieren login
$protectedActions = ['dashboard', 'upload_form', 'process_upload', 'reports', 'settings', /* ...añade otras aquí... */];

if (in_array($action, $protectedActions) && !$isLoggedIn) {
    // Si intenta acceder a ruta protegida sin login -> redirigir a login
    header('Location: index.php?action=login');
    exit;
}

if ($action === 'login' && $isLoggedIn) {
    // Si intenta acceder a login estando ya logueado -> redirigir a dashboard
    header('Location: index.php?action=dashboard');
    exit;
}


// --- Manejar Acciones ---
switch ($action) {
    case 'login':
        $authController->showLoginForm($_GET['error'] ?? null ? "Nombre de usuario o contraseña incorrectos." : null);
        break;
    case 'do_login':
        $authController->handleLogin();
        break;
    case 'logout':
        $authController->logout();
        break;
    case 'dashboard':
        echo "<h1>Panel Principal (Dashboard)</h1>";
        echo "<p>Bienvenido, usuario con ID: " . htmlspecialchars($_SESSION['user_id']) . " (" . htmlspecialchars($_SESSION['user_login']) . ")</p>";
        echo '<p><a href="index.php?action=upload_form">Subir Nueva Cartola</a></p>'; // <-- NUEVO ENLACE
        // Aquí podrías empezar a listar las cartolas ya subidas
        // echo '<p><a href="index.php?action=list_periods">Ver Cartolas Subidas</a></p>'; // Futuro
         echo '<hr>';
        echo '<a href="index.php?action=logout">Cerrar Sesión</a>';
        break;
    case 'upload_form':
        // Preparar datos del mensaje para la vista
        $statusData = [];
        if (isset($_GET['status'])) {
            $statusData['type'] = $_GET['status']; // 'success' o 'error'
            $statusData['message'] = $_GET['msg'] ?? ($_GET['status'] === 'success' ? 'Acción completada.' : 'Ocurrió un error.');
        }
        $uploadController->showUploadForm($statusData);
        break;
    case 'process_upload':
        $uploadController->handleUpload();
        break;    
    case 'default':
        // Redirigir a dashboard si está logueado, sino a login
        if ($isLoggedIn) {
            header('Location: index.php?action=dashboard');
        } else {
            header('Location: index.php?action=login');
        }
        exit;
    default:
        // Acción no encontrada - Podrías mostrar un error 404
        header("HTTP/1.0 404 Not Found");
        echo "Página no encontrada (Error 404)";
        exit;
}

?>