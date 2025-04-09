<?php
// src/Lib/ExcelProcessor.php

namespace Patriciomelor\VnChurchFinances\Services; // Ajusta a tu namespace

// Importar clases de PhpSpreadsheet
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;

// Importar clases de la aplicación
use Patriciomelor\VnChurchFinances\Lib\Database; // Ajusta a tu namespace
use PDO;
use PDOException;
use Exception;
use DateTime; // Para manejo de fechas

class ExcelProcessor {

    private $db;

    // --- Configuración de Columnas (¡AJUSTADA SEGÚN TU EXCEL!) ---
    private $dateColumn = 'A';       // Columna para Fecha
    private $descriptionColumn = 'B'; // Columna para Desglose
    private $debitColumn = 'D';     // Columna para Cargo (Valores negativos aquí!)
    private $creditColumn = 'E';    // Columna para Depósito (Valores positivos aquí!)
    private $startRow = 2;          // Fila donde empiezan los datos (Fila 2!)
    // -------------------------------------------------------------

    // Palabras clave para detectar transferencias (case-insensitive)
    private $transferKeywords = ['TRANSF', 'TEF'];

    public function __construct() {
        $this->db = Database::getConnection();
        if (!$this->db) {
            throw new Exception("ExcelProcessor: No se pudo obtener conexión a la base de datos.");
        }
    }

    /**
     * Procesa un archivo Excel de cartola bancaria y guarda las transacciones.
     *
     * @param string $storedFilePath Ruta completa al archivo Excel guardado.
     * @param int $periodId ID del período (tabla periods) al que pertenece.
     * @return array Retorna un array con el estado: ['success' => bool, 'message' => string, 'processed_rows' => int]
     */
    public function processStatement(string $storedFilePath, int $periodId): array {
        $processedRowCount = 0;
        $insertedRowCount = 0;
        $errors = [];

        try {
            // 1. Cargar el archivo Excel
            $spreadsheet = IOFactory::load($storedFilePath);
            $sheet = $spreadsheet->getActiveSheet();
            $highestRow = $sheet->getHighestRow();

            // 2. Preparar sentencia SQL para inserción (más eficiente fuera del loop)
            $sql = "INSERT INTO transactions (period_id, transaction_date, description, debit, credit, is_transfer, category)
                    VALUES (:period_id, :trans_date, :description, :debit, :credit, :is_transfer, :category)";
            $stmt = $this->db->prepare($sql);

            // Convertir letras de columna a índices numéricos (1-based)
            $dateColIndex = Coordinate::columnIndexFromString($this->dateColumn);
            $descColIndex = Coordinate::columnIndexFromString($this->descriptionColumn);
            $debitColIndex = Coordinate::columnIndexFromString($this->debitColumn);
            $creditColIndex = Coordinate::columnIndexFromString($this->creditColumn);

            // 3. Iterar sobre las filas (empezando desde $startRow)
            for ($rowIndex = $this->startRow; $rowIndex <= $highestRow; $rowIndex++) {
                $processedRowCount++; // Contar fila procesada

                // --- Leer datos crudos ---
                $cellAddress = Coordinate::stringFromColumnIndex($dateColIndex) . $rowIndex;
                $dateValue = $sheet->getCell($cellAddress)->getValue();
                $cellAddress = Coordinate::stringFromColumnIndex($descColIndex) . $rowIndex;
                $descriptionRaw = $sheet->getCell($cellAddress)->getValue();
                $cellAddress = Coordinate::stringFromColumnIndex($debitColIndex) . $rowIndex;
                $debitValueRaw = $sheet->getCell($cellAddress)->getValue();
                $cellAddress = Coordinate::stringFromColumnIndex($creditColIndex) . $rowIndex;
                $creditValueRaw = $sheet->getCell($cellAddress)->getValue();

                // --- Validar / Saltar fila ---
                // Saltar si la fecha está vacía
                if (empty($dateValue)) {
                    continue;
                }
                // Limpiar descripción ahora para usarla en la validación
                 $description = trim((string) $descriptionRaw);
                // Saltar si la descripción está vacía (después de trim)
                 if (empty($description)) {
                    continue;
                 }
                 // Detener si parece una fila de total (ej. empieza con "Total")
                 if (stripos($description, 'Total') === 0) {
                     break; // Detener procesamiento del archivo
                 }
                // Saltar si no hay montos (o son cero después de limpiar)
                 $debitCleaned = $this->cleanAmount($debitValueRaw);
                 $creditCleaned = $this->cleanAmount($creditValueRaw);
                 if (abs($debitCleaned) < 0.01 && abs($creditCleaned) < 0.01) {
                     continue; // No hay monto significativo
                 }


                // --- Limpiar y Convertir Datos ---
                // Fecha:
                $transactionDate = null;
                try {
                    if (is_numeric($dateValue)) {
                        // Es un número serial de Excel
                        $dateTimeObject = Date::excelToDateTimeObject($dateValue);
                        $transactionDate = $dateTimeObject->format('Y-m-d');
                    } elseif (is_string($dateValue) && !empty($dateValue)) {
                        // Intentar parsear como string DD-MM-YYYY (o formatos comunes)
                        $dateTimeObject = DateTime::createFromFormat('d-m-Y', $dateValue); // Tu formato
                        if (!$dateTimeObject) { // Intentar otros formatos si falla
                             $dateTimeObject = DateTime::createFromFormat('Y-m-d', $dateValue);
                             // Añadir más formatos si es necesario
                        }
                         if ($dateTimeObject) {
                             $transactionDate = $dateTimeObject->format('Y-m-d');
                         } else {
                            throw new Exception("Formato de fecha no reconocido: " . $dateValue);
                         }
                    }
                    if ($transactionDate === null) {
                         throw new Exception("Valor de fecha inválido.");
                    }
                } catch (Exception $e) {
                    $errors[] = "Fila $rowIndex: Error procesando fecha (" . $e->getMessage() . ")";
                    continue; // Saltar esta fila
                }

                // Montos (Ya limpiados arriba con cleanAmount)
                // Como D es negativo y E es positivo en tu excel:
                $finalDebit = abs($debitCleaned);   // Guardamos el valor absoluto del cargo
                $finalCredit = $creditCleaned; // Guardamos el valor positivo del abono

                // --- Detección de Transferencias ---
                $isTransfer = false;
                foreach ($this->transferKeywords as $keyword) {
                    if (stripos($description, $keyword) !== false) {
                        $isTransfer = true;
                        break;
                    }
                }

                // --- Insertar en Base de Datos ---
                try {
                    $category = 'Indefinido'; // Categoría por defecto

                    $stmt->bindParam(':period_id', $periodId, PDO::PARAM_INT);
                    $stmt->bindParam(':trans_date', $transactionDate, PDO::PARAM_STR);
                    $stmt->bindParam(':description', $description, PDO::PARAM_STR);
                    $stmt->bindParam(':debit', $finalDebit); // PDO::PARAM_STR o dejar que PDO decida
                    $stmt->bindParam(':credit', $finalCredit); // PDO::PARAM_STR o dejar que PDO decida
                    $stmt->bindParam(':is_transfer', $isTransfer, PDO::PARAM_BOOL);
                    $stmt->bindParam(':category', $category, PDO::PARAM_STR);

                    if ($stmt->execute()) {
                        $insertedRowCount++; // Contar inserción exitosa
                    } else {
                        $errors[] = "Fila $rowIndex: Error de BD al insertar.";
                        // Loggear $stmt->errorInfo() si es necesario
                    }
                } catch (PDOException $e) {
                     // Capturar error específico de esta fila sin detener todo
                     $errors[] = "Fila $rowIndex: Error PDO (" . $e->getCode() . ") al insertar.";
                     error_log("Error PDO en fila $rowIndex, Periodo $periodId: " . $e->getMessage());
                }

            } // Fin del bucle for rows

            // 4. Construir resultado final
             $finalMessage = "Procesado finalizado. Filas leídas del Excel: $processedRowCount. Transacciones guardadas: $insertedRowCount.";
             if (!empty($errors)) {
                 $finalMessage .= " Se encontraron " . count($errors) . " errores en filas específicas (revisar logs para detalles). Primeros errores: " . implode("; ", array_slice($errors, 0, 3)); // Muestra los primeros 3 errores
                 return ['success' => false, 'message' => $finalMessage, 'processed_rows' => $insertedRowCount];
             } else {
                 return ['success' => true, 'message' => $finalMessage, 'processed_rows' => $insertedRowCount];
             }

        } catch (\PhpOffice\PhpSpreadsheet\Reader\Exception $e) {
             error_log("Error al leer el archivo Excel ($storedFilePath): " . $e->getMessage());
             return ['success' => false, 'message' => 'Error: El archivo subido no parece ser un archivo Excel válido o está corrupto.', 'processed_rows' => 0];
        } catch (PDOException $e) { // Errores de BD fuera del loop (ej. prepare falló)
             error_log("Error de Base de Datos (PDO) al procesar transacciones para period_id $periodId: " . $e->getMessage());
             return ['success' => false, 'message' => 'Error interno del servidor al guardar las transacciones (BD).', 'processed_rows' => $insertedRowCount];
        } catch (Exception $e) { // Otros errores inesperados
             error_log("Error general al procesar cartola ($storedFilePath) para period_id $periodId: " . $e->getMessage());
             return ['success' => false, 'message' => 'Error inesperado durante el procesamiento: ' . $e->getMessage(), 'processed_rows' => $insertedRowCount];
        }
    } // Fin de processStatement


    /**
     * Limpia un valor de monto extraído del Excel.
     * Elimina símbolos de moneda, separadores de miles, ajusta decimales.
     *
     * @param mixed $value El valor de la celda.
     * @return float El valor numérico limpio, o 0.0 si es inválido.
     */
    private function cleanAmount($value): float {
        if ($value === null || $value === '') {
            return 0.0;
        }

        // Convertir a string por si viene como número
        $amountStr = (string) $value;

        // Eliminar símbolos comunes de moneda y espacios
        $amountStr = str_replace(['$', 'CLP', ' '], '', $amountStr);

        // Eliminar separador de miles (punto en tu caso)
        $amountStr = str_replace('.', '', $amountStr);

        // Reemplazar separador decimal (coma en tu caso) por punto
        $amountStr = str_replace(',', '.', $amountStr);

        // Validar si es numérico después de la limpieza
        if (is_numeric($amountStr)) {
            return (float) $amountStr;
        }

        return 0.0; // Retornar 0.0 si no se pudo convertir
    }

} // Fin de la clase ExcelProcessor
?>