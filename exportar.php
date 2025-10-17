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

// Obtener los datos de la base de datos
$sql = "SELECT * FROM pedidos"; // Aquí seleccionas los campos que necesitas
$result = $conn->query($sql);

// Crear el objeto Spreadsheet
$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();

// Escribir los encabezados en la primera fila
$headers = ['ID', 'Nombre Corto', 'Cantidad', 'Precio', 'Subtotal', 'Proveedor', 'Cuenta', 
            'Fecha Compra', 'Fecha Llegada', 'Forma de Pago', 'Usuario', 'Estado', 'Observaciones', 
            'ODS', 'ODS ID', 'Factura', 'Recibió', 'Motivo', 'Fecha Creación', 'Fecha Actualización', 
            'Foto', 'URL'];

$column = 1; // Comienza en la primera columna
foreach ($headers as $header) {
    $sheet->setCellValueByColumnAndRow($column++, 1, $header);
}

// Escribir los datos en la hoja
$rowNumber = 2;
if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $sheet->setCellValueByColumnAndRow(1, $rowNumber, $row['id']);
        $sheet->setCellValueByColumnAndRow(2, $rowNumber, $row['nombre_corto']);
        $sheet->setCellValueByColumnAndRow(3, $rowNumber, $row['cantidad']);
        $sheet->setCellValueByColumnAndRow(4, $rowNumber, $row['precio']);
        $sheet->setCellValueByColumnAndRow(5, $rowNumber, $row['subtotal']);
        $sheet->setCellValueByColumnAndRow(6, $rowNumber, $row['proveedor']);
        $sheet->setCellValueByColumnAndRow(7, $rowNumber, $row['cuenta']);
        $sheet->setCellValueByColumnAndRow(8, $rowNumber, $row['fecha_compra']);
        $sheet->setCellValueByColumnAndRow(9, $rowNumber, $row['fecha_llegada']);
        $sheet->setCellValueByColumnAndRow(10, $rowNumber, $row['forma_pago']);
        $sheet->setCellValueByColumnAndRow(11, $rowNumber, $row['usuario']);
        $sheet->setCellValueByColumnAndRow(12, $rowNumber, $row['estado']);
        $sheet->setCellValueByColumnAndRow(13, $rowNumber, $row['observaciones']);
        $sheet->setCellValueByColumnAndRow(14, $rowNumber, $row['ods']);
        $sheet->setCellValueByColumnAndRow(15, $rowNumber, $row['ods_id']);
        $sheet->setCellValueByColumnAndRow(16, $rowNumber, $row['factura']);
        $sheet->setCellValueByColumnAndRow(17, $rowNumber, $row['recibio']);
        $sheet->setCellValueByColumnAndRow(18, $rowNumber, $row['motivo']);
        $sheet->setCellValueByColumnAndRow(19, $rowNumber, $row['fecha_creacion']);
        $sheet->setCellValueByColumnAndRow(20, $rowNumber, $row['fecha_actualizacion']);
        $sheet->setCellValueByColumnAndRow(21, $rowNumber, $row['foto']);
        $sheet->setCellValueByColumnAndRow(22, $rowNumber, $row['url']);
        
        $rowNumber++;
    }
}

// Escribir el archivo Excel
$writer = new Xlsx($spreadsheet);
$filename = 'export_pedidos.xlsx';

// Enviar el archivo al navegador para descargarlo
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment;filename="' . $filename . '"');
header('Cache-Control: max-age=0');
$writer->save('php://output');

// Cerrar la conexión
$conn->close();
?>
