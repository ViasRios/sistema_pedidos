<?php
require_once 'config.php';

header('Content-Type: application/json');

// Habilitar CORS para permitir solicitudes desde el mismo dominio
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST');
header('Access-Control-Allow-Headers: Content-Type');

if (isset($_GET['term'])) {
    $term = $_GET['term'];
    
    try {
        // Buscar ODS en la base de datos externa usando Idods
        $sql = "SELECT Idods FROM ods 
                WHERE Idods LIKE ?
                ORDER BY Fecha DESC LIMIT 10";
        $stmt = $pdo_ods->prepare($sql);
        $like_term = "%$term%";
        $stmt->execute([$like_term]);
        $ods_list = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Verificar si hay resultados
        if ($ods_list) {
            echo json_encode($ods_list);
        } else {
            echo json_encode([]); // Devolver array vacío si no hay resultados
        }
        
    } catch (PDOException $e) {
        // Log del error (opcional)
        error_log("Error en buscar_ods.php: " . $e->getMessage());
        
        // Devolver error en formato JSON
        echo json_encode(['error' => 'Error en la consulta: ' . $e->getMessage()]);
    }
} else {
    echo json_encode(['error' => 'Parámetro "term" no proporcionado']);
}
