<?php
// src/Lib/Database.php

namespace Patriciomelor\VnChurchFinances\Lib; // <-- ¡Usa tu namespace! // Asegúrate que coincida con tu namespace de composer.json (PSR-4)

use PDO;
use PDOException;

class Database {
    private static $connection = null; // Almacena la conexión para reutilizarla

    /**
     * Obtiene la instancia de la conexión PDO a la base de datos.
     * Utiliza el patrón Singleton simple para evitar múltiples conexiones.
     *
     * @return PDO|null Retorna la instancia de PDO o null si falla la conexión.
     */
    public static function getConnection(): ?PDO {
        // Si ya existe una conexión, retórnala
        if (self::$connection !== null) {
            return self::$connection;
        }

        // Carga la configuración solo si no se ha cargado antes
        // (Asume que este archivo está en config/database.php respecto a la raíz del proyecto)
        require_once __DIR__ . '/../../config/database.php';

        // Define el Data Source Name (DSN) para PDO
        $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=' . DB_CHARSET;

        // Opciones de PDO
        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION, // Lanza excepciones en errores
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,       // Devuelve arrays asociativos por defecto
            PDO::ATTR_EMULATE_PREPARES   => false,                  // Usa preparaciones nativas de la BD
        ];

        try {
            // Intenta crear la instancia de PDO
            self::$connection = new PDO($dsn, DB_USER, DB_PASSWORD, $options);
            return self::$connection;
        } catch (PDOException $e) {
            // Manejo básico de errores (en producción querrías loggear esto)
            error_log('Error de Conexión a BD: ' . $e->getMessage());
            // Puedes decidir qué hacer aquí: mostrar un error, lanzar la excepción, etc.
            // Por ahora, retornamos null para indicar fallo.
            return null;
        }
    }
}
?>