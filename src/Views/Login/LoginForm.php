<?php
// src/Views/login_form.php

// Define un título para la página (opcional)
$pageTitle = "Iniciar Sesión - Panel de Finanzas";

// Incluir un posible encabezado si lo tienes (opcional)
// include __DIR__ . '/partials/header.php';
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle; ?></title>
    <style>
        body { font-family: sans-serif; display: flex; justify-content: center; align-items: center; min-height: 100vh; background-color: #f4f4f4; }
        .login-container { background-color: #fff; padding: 2em; border-radius: 8px; box-shadow: 0 0 10px rgba(0,0,0,0.1); }
        .form-group { margin-bottom: 1em; }
        label { display: block; margin-bottom: 0.5em; }
        input[type="text"], input[type="password"] { width: 100%; padding: 0.8em; border: 1px solid #ccc; border-radius: 4px; box-sizing: border-box; }
        button { padding: 0.8em 1.5em; background-color: #007bff; color: white; border: none; border-radius: 4px; cursor: pointer; }
        button:hover { background-color: #0056b3; }
        .error-message { color: red; margin-bottom: 1em; }
    </style>
</head>
<body>
    <div class="login-container">
        <h2><?php echo $pageTitle; ?></h2>

        <?php
        // Mostrar mensaje de error si existe (lo pasaremos desde el controlador)
        if (isset($error_message) && !empty($error_message)) {
            echo '<p class="error-message">' . htmlspecialchars($error_message) . '</p>';
        }
        ?>

        <form action="index.php?action=do_login" method="post">
            <div class="form-group">
                <label for="username">Nombre de Usuario (WordPress):</label>
                <input type="text" id="username" name="username" required>
            </div>
            <div class="form-group">
                <label for="password">Contraseña:</label>
                <input type="password" id="password" name="password" required>
            </div>
            <button type="submit">Entrar</button>
        </form>
    </div>

    <?php
    // Incluir un posible pie de página si lo tienes (opcional)
    // include __DIR__ . '/partials/footer.php';
    ?>
</body>
</html>