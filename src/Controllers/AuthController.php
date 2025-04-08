<?php
// src/Controllers/AuthController.php

namespace Patriciomelor\VnChurchFinances\Controllers; // Ajusta a tu namespace

// Importa clases necesarias
use Patriciomelor\VnChurchFinances\Lib\Database; // Ajusta a tu namespace
use PDO;

class AuthController {

    private $db;
    private $wp_config_path; // Necesitaremos la ruta a wp-config.php o wp-load.php

    public function __construct() {
        $this->db = Database::getConnection();
        // Intentar obtener la ruta de WP desde la config (¡Debes definirla!)
        $this->wp_config_path = defined('WP_INSTALL_PATH') ? WP_INSTALL_PATH : null;
    }

    /**
     * Muestra el formulario de login.
     */
    public function showLoginForm($errorMessage = null) {
        // Podemos pasar variables a la vista
        if ($errorMessage) {
            $error_message = $errorMessage;
        }
        require_once __DIR__ . '/../Views/login_form.php';
    }

    /**
     * Procesa el intento de login.
     */
    public function handleLogin() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            // Solo aceptar POST
            header('Location: index.php?action=login');
            exit;
        }

        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';

        if (empty($username) || empty($password)) {
            $this->showLoginForm("Usuario y contraseña son requeridos.");
            return;
        }

        if (!$this->db) {
             $this->showLoginForm("Error interno del servidor (No hay conexión a BD).");
             error_log("AuthController::handleLogin - No se pudo obtener conexión a BD.");
             return;
        }

        try {
            // 1. Buscar usuario en la tabla de WP (ibvn_users)
            // NOTA: WordPress por defecto usa 'wp_users'. Tu prefijo es 'ibvn_'.
            // Asegúrate que la tabla se llame 'ibvn_users'. ¡Verifica esto en tu phpMyAdmin!
            $stmt = $this->db->prepare("SELECT ID, user_pass FROM ibvn_users WHERE user_login = :username LIMIT 1");
            $stmt->bindParam(':username', $username, PDO::PARAM_STR);
            $stmt->execute();
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($user) {
                // 2. Verificar la contraseña usando la función de WordPress
                $userId = $user['ID'];
                $hashedPassword = $user['user_pass'];

                // --- Verificación de Contraseña de WordPress ---
                // Incluir la función wp_check_password
                // La forma más robusta es incluir wp-load.php si la ruta es fiable.
                // ¡ADVERTENCIA! Incluir wp-load.php carga TODO WordPress.
                // Considera alternativas si causa problemas de rendimiento o conflictos.
                if ($this->wp_config_path && file_exists($this->wp_config_path . '/wp-load.php')) {
                     // Define SHORTINIT para cargar solo lo básico (puede o no ser suficiente)
                     // define('SHORTINIT', true);
                     require_once $this->wp_config_path . '/wp-load.php';

                     if (function_exists('wp_check_password') && wp_check_password($password, $hashedPassword, $userId)) {
                         // ¡Contraseña correcta!
                         $this->establishSession($userId, $username);
                         header('Location: index.php?action=dashboard'); // Redirigir al panel
                         exit;
                     }
                } else {
                    // Error si no podemos encontrar/cargar funciones de WP
                    error_log("AuthController::handleLogin - No se pudo cargar wp-load.php o WP_INSTALL_PATH no está definida.");
                    $this->showLoginForm("Error de configuración del servidor al verificar contraseña.");
                    return;
                }
            }

            // Si el usuario no existe o la contraseña es incorrecta
            $this->showLoginForm("Nombre de usuario o contraseña incorrectos.");

        } catch (\PDOException $e) {
            error_log("Error de BD en handleLogin: " . $e->getMessage());
            $this->showLoginForm("Error interno del servidor (BD).");
        } catch (\Throwable $th) {
             error_log("Error general en handleLogin: " . $th->getMessage());
             $this->showLoginForm("Ocurrió un error inesperado.");
        }
    }

    /**
     * Establece la sesión del usuario.
     */
    private function establishSession($userId, $username) {
         if (session_status() === PHP_SESSION_NONE) {
             session_start();
         }
         // Regenerar ID de sesión por seguridad
         session_regenerate_id(true);

         // Guardar datos del usuario en la sesión
         $_SESSION['user_id'] = $userId;
         $_SESSION['user_login'] = $username;
         // Podrías guardar más datos si los necesitas (ej. rol)
    }

    /**
     * Cierra la sesión del usuario.
     */
    public function logout() {
         if (session_status() === PHP_SESSION_NONE) {
             session_start();
         }
         $_SESSION = array(); // Limpiar variables de sesión
         if (ini_get("session.use_cookies")) {
             $params = session_get_cookie_params();
             setcookie(session_name(), '', time() - 42000,
                 $params["path"], $params["domain"],
                 $params["secure"], $params["httponly"]
             );
         }
         session_destroy(); // Destruir la sesión
         header('Location: index.php?action=login'); // Redirigir al login
         exit;
    }
}
?>