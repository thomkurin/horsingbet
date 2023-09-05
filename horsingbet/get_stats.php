<?php
include_once("conexao.php");

function atualizarControle($conn) {
    // Obtenha o total de dinheiro em caixa
    $sql = "SELECT COALESCE(SUM(wallet), 0) AS caixa FROM usuarios";
    $result = $conn->query($sql);
    if ($result === FALSE) {
        echo "Erro ao consultar o total de dinheiro em caixa: " . $conn->error;
        return FALSE;
    }
    $row = $result->fetch_assoc();
    $caixa = $row['caixa'];

    // Obtenha o total apostado
    $sql = "SELECT COALESCE(SUM(valor_apostado), 0) AS total_apostado FROM apostas";
    $result = $conn->query($sql);
    if ($result === FALSE) {
        echo "Erro ao consultar o total apostado: " . $conn->error;
        return FALSE;
    }
    $row = $result->fetch_assoc();
    $total_apostado = $row['total_apostado'];

    // Obtenha o número total de usuários registrados
    $sql = "SELECT COUNT(*) AS usuarios_registrados FROM usuarios";
    $result = $conn->query($sql);
    if ($result === FALSE) {
        echo "Erro ao consultar o número total de usuários registrados: " . $conn->error;
        return FALSE;
    }
    $row = $result->fetch_assoc();
    $usuarios_registrados = $row['usuarios_registrados'];

    // Atualize a tabela controle
    $sql = "UPDATE controle SET 
            caixa = $caixa,
            total_apostado = $total_apostado,
            usuarios_registrados = $usuarios_registrados,
            data_atualizacao = NOW()
            WHERE id = 1";
    if ($conn->query($sql) === FALSE) {
        echo "Erro ao atualizar a tabela controle: " . $conn->error;
        return FALSE;
    }

    return TRUE;
}

if (atualizarControle($conn)) {
    echo "Estatísticas atualizadas com sucesso!";
} else {
    echo "Houve um erro ao atualizar as estatísticas.";
}
?>
