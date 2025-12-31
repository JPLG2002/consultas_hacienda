<?php
// api.php
require_once 'config.php';

$idBusqueda = $_GET['id'] ?? '';

if (empty($idBusqueda)) {
    http_response_code(400);
    echo json_encode(["error" => "ID de búsqueda no proporcionado. Asegúrese de usar ?id=..."]);
    exit;
}

try {
    $pdo = connectDB();
    $idTrimmed = trim($idBusqueda);

    // Buscar por id_predio o id_contribuyente (solo registros activos)
    $sql = "SELECT 
                id_registro as idRegistro,
                id_predio as id,
                id_contribuyente as idAlterno,
                razon_social as razonSocial,
                no_acto_administrativo as noActoAdministrativo,
                fecha_acto as fechaActo,
                fecha_publicacion as fechaPublicacion,
                tipo_actuacion as tipoActuacion,
                organismo,
                area,
                fecha_desfijacion as fechaDesfijacion,
                estado,
                mas_datos as masDatos,
                pdf_nombre_final as pdfNombre
            FROM actos_administrativos
            WHERE (id_predio = ? OR id_contribuyente = ?)
              AND activo = 1";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([$idTrimmed, $idTrimmed]); 

    $resultados = $stmt->fetchAll();

    echo json_encode($resultados);

} catch (\PDOException $e) {
    http_response_code(500);
    echo json_encode(["error" => "Error al ejecutar la consulta SQL.", "details" => $e->getMessage()]);
    exit;
}
?>