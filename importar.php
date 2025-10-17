<?php
require 'vendor/autoload.php';

// Configurar la conexión a la base de datos
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "sistema_pedidos";

$conn = new mysqli($servername, $username, $password, $dbname);

// Verificar la conexión
if ($conn->connect_error) {
    die("Conexión fallida: " . $conn->connect_error);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['file'])) {
    $file = $_FILES['file']['tmp_name'];

    // Cargar el archivo Excel
    $spreadsheet = IOFactory::load($file);
    $sheet = $spreadsheet->getActiveSheet();

    // Leer los datos de la hoja
    $rows = $sheet->toArray();

    // Iterar sobre los datos y guardarlos en la base de datos
    foreach ($rows as $row) {
        if (!empty($row[0])) { // Verifica si la primera celda (ID) no está vacía
            $sql = "INSERT INTO pedidos (id, nombre_corto, cantidad, precio, subtotal, proveedor, cuenta, fecha_compra, fecha_llegada, forma_pago, usuario, estado, observaciones, ods, ods_id, factura, recibio, motivo, fecha_creacion, fecha_actualizacion, foto, url) 
                    VALUES ('".$row[0]."', '".$row[1]."', '".$row[2]."', '".$row[3]."', '".$row[4]."', '".$row[5]."', '".$row[6]."', '".$row[7]."', '".$row[8]."', '".$row[9]."', '".$row[10]."', '".$row[11]."', '".$row[12]."', '".$row[13]."', '".$row[14]."', '".$row[15]."', '".$row[16]."', '".$row[17]."', '".$row[18]."', '".$row[19]."', '".$row[20]."', '".$row[21]."')";
            
            if ($conn->query($sql) !== TRUE) {
                echo "Error al insertar los datos: " . $conn->error;
            }
        }
    }

    echo "Datos importados correctamente.";
}

// Cerrar la conexión
$conn->close();
?>

<form method="POST" enctype="multipart/form-data">
    <input type="file" name="file" accept=".xlsx, .xls" required>
    <button type="submit">Importar Excel</button>
</form>
