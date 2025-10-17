<?php
$host = 'localhost';
$dbname = 'sistema_pedidos';
$dbname_ods = 'sistema'; // Base de datos externa para ODS
$username = 'root';
$password = '';

try {
    // Conexión principal
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Conexión para ODS (desde otra base de datos)
    $pdo_ods = new PDO("mysql:host=$host;dbname=$dbname_ods;charset=utf8", $username, $password);
    $pdo_ods->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Error de conexión: " . $e->getMessage());
}
