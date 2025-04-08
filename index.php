<?php
/**
 * Punto de entrada frontal que carga el index real desde la carpeta /public.
 */

// Carga y ejecuta el archivo index.php principal que está en la carpeta 'public'.
// __DIR__ se asegura de que la ruta sea relativa al directorio de este archivo.
require __DIR__ . '/public/index.php';

?>