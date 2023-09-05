<?php
include_once("conexao.php");

if (isset($_POST['competitorId'])) {
    $competitorId = $_POST['competitorId'];

    echo "Competitor ID: $competitorId<br>"; // Imprimir competitorId

    // Marque o competidor como vencedor
    $sql = "UPDATE largada SET vencedor = 1 WHERE competitor_id = $competitorId";
    if ($conn->query($sql) === TRUE) {

        // Marque todas as apostas para este competidor como vencedoras
        $sql = "UPDATE apostas SET vencedor = 1, finalizar = 1 WHERE competitor_id = $competitorId";
        $conn->query($sql);
        
        // Encontre todas as apostas vencedoras
        $sql = "SELECT * FROM apostas WHERE competitor_id = '$competitorId'";
        $result = $conn->query($sql);

        // Encontre a categoria do competidor vencedor
        $sql = "SELECT categoria FROM apostas WHERE competitor_id = $competitorId LIMIT 1";
        $result = $conn->query($sql);
        $row = $result->fetch_assoc();
        $categoria = $row['categoria'];

        // Marque todas as outras apostas na mesma categoria como finalizadas
        $sql = "UPDATE apostas SET finalizar = 1 WHERE categoria = '$categoria'";
        if($conn->query($sql) === TRUE) {
            echo "Todas as apostas na categoria $categoria foram marcadas como finalizadas. <br>";
        } else {
            echo "Erro ao marcar apostas como finalizadas: " . $conn->error . "<br>";
        }


        if ($result->num_rows > 0) {
            // Atualize a carteira de cada usuário vencedor
            while($row = $result->fetch_assoc()) {
                $userId = $row['id_usuario'];
                $possibleValue = $row['valor_possivel'];

                if (!empty($userId) && is_numeric($userId)) {        
                    $sql = "UPDATE usuarios SET wallet = wallet + $possibleValue WHERE id = $userId";
                    if ($conn->query($sql) === TRUE) {
                        echo "User ID: $userId - Carteira atualizada com sucesso! <br>"; // Imprimir userId
                    } else {
                        echo "Erro ao atualizar a carteira do usuário: " . $conn->error . "<br>";
                    }
                } else {
                    echo "Usuário ID inválido: $userId<br>";
                }
            }
        } else {
            echo "Nenhuma aposta vencedora encontrada para este competidor.<br>";
        }

        echo "Vencedor declarado com sucesso!<br>";
    } else {
        echo "Erro ao atualizar o vencedor: " . $conn->error . "<br>";
    }

} else {
    echo "Dados de competidor inválidos.<br>";
}
?>
