<?php
// src/Controllers/UploadController.php

namespace Patriciomelor\VnChurchFinances\Controllers; // Ajusta a tu namespace

use Patriciomelor\VnChurchFinances\Lib\Database; // Ajusta a tu namespace
use PDO;
use PDOException;

class UploadController {

    private $db;
    // Directorio donde se guardarán las cartolas (relativo a la raíz del proyecto)
    // ¡ASEGÚRATE QUE ESTA CARPETA EXISTA Y TENGA PERMISOS DE ESCRITURA!
    private $uploadDir = __DIR__ . '/../../storage/uploads/';
    private $maxFileSize = 5 * 1024 * 1024; // 5 MB como máximo
    private $allowedExtensions = ['xlsx', 'xls'];

    public function __construct() {
        $this->db = Database::getConnection();
    }

    /**
     * Muestra el formulario de subida, opcionalmente con un mensaje.
     */
    public function showUploadForm($statusData = []) {
        // Extraer mensajes de $statusData si existen
        $message = $statusData['message'] ?? null;
        $messageType = $statusData['type'] ?? 'info'; // 'success' o 'error'

        // Incluir la vista del formulario
        require_once __DIR__ . '/../Views/Upload/UploadForm.php';
    }

    /**
     * Procesa la subida del archivo de la cartola.
     */
    public function handleUpload() {
        // Verificar login (aunque el router ya debería hacerlo)
        if (!isset($_SESSION['user_id'])) {
            header('Location: index.php?action=login');
            exit;
        }
        $userId = $_SESSION['user_id'];

        // Verificar método POST
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirectWithError('Método no permitido.');
            return;
        }

        // Verificar si se subió un archivo
        if (!isset($_FILES['bank_statement']) || $_FILES['bank_statement']['error'] === UPLOAD_ERR_NO_FILE) {
             $this->redirectWithError('No se seleccionó ningún archivo.');
             return;
        }

        $file = $_FILES['bank_statement'];

        // --- Validaciones ---
        // Error de subida?
        if ($file['error'] !== UPLOAD_ERR_OK) {
            $this->redirectWithError($this->getUploadErrorMessage($file['error']));
            return;
        }

        // Tamaño del archivo
        if ($file['size'] > $this->maxFileSize) {
             $this->redirectWithError('El archivo es demasiado grande (Máximo ' . ($this->maxFileSize / 1024 / 1024) . ' MB).');
             return;
        }

        // Extensión del archivo
        $originalFilename = basename($file['name']);
        $extension = strtolower(pathinfo($originalFilename, PATHINFO_EXTENSION));
        if (!in_array($extension, $this->allowedExtensions)) {
             $this->redirectWithError('Tipo de archivo no permitido. Solo se aceptan: ' . implode(', ', $this->allowedExtensions));
             return;
        }

        // Validar Mes y Año recibidos del formulario
        $month = filter_input(INPUT_POST, 'period_month', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 12]]);
        $year = filter_input(INPUT_POST, 'period_year', FILTER_VALIDATE_INT, ['options' => ['min_range' => 2000, 'max_range' => date('Y') + 5]]); // Rango razonable

        if ($month === false || $year === false) {
            $this->redirectWithError('Mes o año del período inválido.');
            return;
        }

        // --- Mover Archivo ---
        // Asegúrate que el directorio exista y sea escribible
        if (!is_dir($this->uploadDir)) {
             if (!mkdir($this->uploadDir, 0755, true)) { // Intentar crear con permisos 755
                 error_log("Error: No se pudo crear el directorio de subida: " . $this->uploadDir);
                 $this->redirectWithError('Error interno del servidor al preparar almacenamiento.');
                 return;
             }
        }
         if (!is_writable($this->uploadDir)) {
             error_log("Error: El directorio de subida no tiene permisos de escritura: " . $this->uploadDir . " - Intenta chmod 775 o ajusta propietario/grupo.");
             $this->redirectWithError('Error interno del servidor (permisos de almacenamiento).');
             return;
         }


        // Generar nombre único para el archivo guardado
        $uniqueFilename = uniqid('cartola_' . $year . '_' . $month . '_', true) . '.' . $extension;
        $targetPath = $this->uploadDir . $uniqueFilename;

  // Dentro de UploadController.php, en el método handleUpload()

        // ... (Validaciones previas del archivo y mes/año) ...

        if (move_uploaded_file($file['tmp_name'], $targetPath)) {
            // --- Archivo movido con éxito -> Registrar en BD 'periods' ---
            try {
                $sql = "INSERT INTO periods (month, year, original_filename, stored_filename, uploaded_by_user_id, uploaded_at)
                        VALUES (:month, :year, :original_filename, :stored_filename, :user_id, NOW())";
                $stmt = $this->db->prepare($sql);

                $stmt->bindParam(':month', $month, PDO::PARAM_INT);
                $stmt->bindParam(':year', $year, PDO::PARAM_INT);
                $stmt->bindParam(':original_filename', $originalFilename, PDO::PARAM_STR);
                $stmt->bindParam(':stored_filename', $uniqueFilename, PDO::PARAM_STR);
                $stmt->bindParam(':user_id', $userId, PDO::PARAM_INT);

                if ($stmt->execute()) {
                    // --- Registro en 'periods' exitoso ---

                    // 1. Obtener el ID del período recién insertado
                    $periodId = $this->db->lastInsertId();

                    // 2. Instanciar y llamar al procesador de Excel
                    //    (Asegúrate que el namespace sea el correcto)
                    try {
                        $processor = new \Patriciomelor\VnChurchFinances\Lib\ExcelProcessor();
                        $processingResult = $processor->processStatement($targetPath, (int)$periodId);

                        // 3. Redirigir según el resultado del procesamiento
                        if ($processingResult['success']) {
                             $this->redirectWithSuccess($processingResult['message']); // Mensaje viene del procesador
                        } else {
                             // Éxito al subir y registrar período, pero error al procesar transacciones
                             $this->redirectWithError($processingResult['message']); // Mensaje viene del procesador
                        }
                        // ¡Importante! Salir después de la redirección
                        exit;

                    } catch (\Exception $procErr) {
                        // Error al instanciar o ejecutar el procesador
                        error_log("Error llamando a ExcelProcessor para period_id $periodId: " . $procErr->getMessage());
                        // Dejamos el registro en 'periods' pero informamos del fallo de procesamiento
                        $this->redirectWithError('Cartola subida, pero falló el procesamiento de transacciones. Revise los logs.');
                        exit;
                    }

                } else {
                     // Error al ejecutar INSERT en 'periods'
                     error_log("Error al ejecutar INSERT en periods. Info: " . print_r($stmt->errorInfo(), true));
                     unlink($targetPath); // Borrar archivo físico si falló el registro en BD
                     $this->redirectWithError('Error al guardar la información inicial en la base de datos.');
                     return; // Salir
                }

            } catch (PDOException $e) {
                // Error de PDO al insertar en 'periods'
                error_log("PDOException al insertar en periods: " . $e->getMessage());
                unlink($targetPath); // Borrar archivo físico si falló el registro en BD
                $this->redirectWithError('Error interno del servidor (BD al registrar período).');
                return; // Salir
            }

        } else {
            // Error al mover el archivo subido
            error_log("Error: move_uploaded_file falló para " . $targetPath . " desde " . $file['tmp_name']);
            $this->redirectWithError('Error al guardar el archivo subido en el servidor.');
            return; // Salir
        }
    }
    /**
     * Redirige de vuelta al formulario con un mensaje de éxito.
     */
    private function redirectWithSuccess($message) {
        header('Location: index.php?action=upload_form&status=success&msg=' . urlencode($message));
        exit;
    }

    /**
     * Redirige de vuelta al formulario con un mensaje de error.
     */
    private function redirectWithError($errorMessage) {
         header('Location: index.php?action=upload_form&status=error&msg=' . urlencode($errorMessage));
         exit;
    }

     /**
      * Convierte códigos de error de subida a mensajes legibles.
      */
     private function getUploadErrorMessage($errorCode) {
         switch ($errorCode) {
             case UPLOAD_ERR_INI_SIZE:
             case UPLOAD_ERR_FORM_SIZE:
                 return 'El archivo excede el tamaño máximo permitido.';
             case UPLOAD_ERR_PARTIAL:
                 return 'El archivo se subió solo parcialmente.';
             case UPLOAD_ERR_NO_TMP_DIR:
                 return 'Error del servidor: Falta carpeta temporal.';
             case UPLOAD_ERR_CANT_WRITE:
                 return 'Error del servidor: No se pudo escribir el archivo en disco.';
             case UPLOAD_ERR_EXTENSION:
                 return 'Error del servidor: Una extensión PHP detuvo la subida.';
             default:
                 return 'Error desconocido durante la subida del archivo.';
         }
     }
}
?>