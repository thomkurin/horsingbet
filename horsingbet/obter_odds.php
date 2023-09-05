<?php
// Verifique se o ID do competidor foi fornecido
if (isset($_GET['competitor_id'])) {
    $competitorId = $_GET['competitor_id'];

    // Realize a consulta ao banco de dados para obter as odds do competidor
    $sql = "SELECT odds1, odds2, odds3 FROM largada WHERE competitor_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $competitorId);
    $stmt->execute();
    $stmt->bind_result($odds1, $odds2, $odds3);

    if ($stmt->fetch()) {
        $response = array(
            'odds1' => $odds1,
            'odds2' => $odds2,
            'odds3' => $odds3
        );
        echo json_encode($response);
    } else {
        // O competidor não foi encontrado ou não possui odds definidas
        $response = array(
            'odds1' => '',
            'odds2' => '',
            'odds3' => ''
        );
        echo json_encode($response);
    }

    $stmt->close();
} else {
    // O ID do competidor não foi fornecido
    $response = array(
        'odds1' => '',
        'odds2' => '',
        'odds3' => ''
    );
    echo json_encode($response);
}
?>
