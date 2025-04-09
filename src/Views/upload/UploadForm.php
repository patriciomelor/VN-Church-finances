<?php
// src/Views/Upload/UploadForm.php

$pageTitle = "Subir Cartola Bancaria";

// Placeholder para incluir header si lo usas
// include __DIR__ . '/../partials/header.php';

// Determinar año actual para el input
$currentYear = date('Y');
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($pageTitle); ?></title>
    <style> /* Estilos básicos de ejemplo */
         body { font-family: sans-serif; padding: 20px; }
        .container { max-width: 600px; margin: auto; background: #f9f9f9; padding: 20px; border-radius: 5px; }
        .form-group { margin-bottom: 15px; }
        label { display: block; margin-bottom: 5px; }
        input[type="file"], input[type="number"], select, button { width: 100%; padding: 10px; margin-top: 5px; box-sizing: border-box; }
        button { background-color: #28a745; color: white; border: none; cursor: pointer; }
        button:hover { background-color: #218838; }
        .message { padding: 10px; margin-bottom: 15px; border-radius: 4px; }
        .message.success { background-color: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .message.error { background-color: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        nav { margin-bottom: 20px; } /* Para el link de volver */
    </style>
</head>
<body>
    <div class="container">
        <nav>
            <a href="index.php?action=dashboard">← Volver al Dashboard</a>
        </nav>

        <h2><?php echo htmlspecialchars($pageTitle); ?></h2>

        <?php if (isset($message)): ?>
            <div class="message <?php echo $messageType === 'success' ? 'success' : 'error'; ?>">
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>

        <form action="index.php?action=process_upload" method="post" enctype="multipart/form-data">
            <div class="form-group">
                <label for="bank_statement">Selecciona la Cartola (Excel: .xlsx, .xls):</label>
                <input type="file" name="bank_statement" id="bank_statement" accept=".xlsx, .xls" required>
            </div>

            <div class="form-group">
                <label for="period_month">Mes del Período:</label>
                <select name="period_month" id="period_month" required>
                    <?php for ($m = 1; $m <= 12; $m++): ?>
                        <option value="<?php echo $m; ?>" <?php echo (date('n') == $m) ? 'selected' : ''; ?>>
                            <?php echo DateTime::createFromFormat('!m', $m)->format('F'); // Nombre del mes ?>
                        </option>
                    <?php endfor; ?>
                </select>
            </div>

            <div class="form-group">
                <label for="period_year">Año del Período:</label>
                <input type="number" name="period_year" id="period_year" min="2020" max="<?php echo $currentYear + 1; ?>" value="<?php echo $currentYear; ?>" required>
            </div>

            <button type="submit">Subir y Registrar</button>
        </form>
    </div>

     <?php
     // Placeholder para incluir footer si lo usas
     // include __DIR__ . '/../partials/footer.php';
     ?>
</body>
</html>