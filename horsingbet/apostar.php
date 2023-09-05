<?php

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();
include_once("conexao.php");

// Verificar se o usuário está autenticado
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header("location: login.php");
    exit;
}

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["csrf_token"]) && $_SESSION["csrf_token"] === $_POST["csrf_token"]) {
    // Verifique se todas as chaves necessárias estão presentes em $_POST
    $requiredKeys = ['competitor_id', 'competitor', 'cavalo', 'categoria', 'evento', 'odds', 'valor_apostado'];
    foreach ($requiredKeys as $key) {
        if (!isset($_POST[$key])) {
            echo json_encode(["success" => false, "message" => "Erro: falta o campo {$key}."]);
            exit;
        }
    }

    // Se chegamos até aqui, todas as chaves necessárias estão presentes em $_POST
    // Receber os dados do formulário
    $competitorId = $_POST['competitor_id'];
    $competitor = $_POST['competitor'];
    $cavalo = $_POST['cavalo'];
    $categoria = $_POST['categoria'];
    $evento = $_POST['evento'];
    $odds = $_POST['odds'];  // Recebendo como um único valor, não um array
    $valor_apostado = floatval($_POST['valor_apostado']);  // Conversão do valor apostado para float    
    $now = new DateTime();

    // Buscar o ID do evento com base no nome
    $sqlEventoId = "SELECT event_id FROM eventos WHERE name = ?";
    $stmtEventoId = $conn->prepare($sqlEventoId);
    $stmtEventoId->bind_param("s", $evento);
    $stmtEventoId->execute();
    $resultadoEvento = $stmtEventoId->get_result();
    $rowEvento = $resultadoEvento->fetch_assoc();
    $eventoId = $rowEvento['event_id']; 
    $stmtEventoId->close();

    // Buscar o ID da categoria com base no nome e no event_id
    $sqlCategoriaId = "SELECT category_id FROM categorias WHERE name = ? AND event_id = ?";
    $stmtCategoriaId = $conn->prepare($sqlCategoriaId);
    $stmtCategoriaId->bind_param("si", $categoria, $eventoId);
    $stmtCategoriaId->execute();
    $resultadoCategoria = $stmtCategoriaId->get_result();
    $rowCategoria = $resultadoCategoria->fetch_assoc();
    $categoriaId = $rowCategoria['category_id'];
    $stmtCategoriaId->close();
    
    // Buscar CPF, nome e saldo do usuário logado
    $idUsuarioLogado = $_SESSION['id']; 
    $sqlBuscarDadosUsuario = "SELECT id, wallet FROM usuarios WHERE id = ?";
    $stmtBuscarDadosUsuario = $conn->prepare($sqlBuscarDadosUsuario);
    $stmtBuscarDadosUsuario->bind_param("i", $idUsuarioLogado);
    $stmtBuscarDadosUsuario->execute();
    $resultado = $stmtBuscarDadosUsuario->get_result();
    $row = $resultado->fetch_assoc();
    $id_usuario = $row['id'];
    $wallet = $row['wallet'];
    $stmtBuscarDadosUsuario->close();

    // Verificar se o usuário tem saldo suficiente
    if ($wallet < $valor_apostado) {
        echo json_encode(["success" => false, "message" => "Saldo insuficiente para realizar a aposta."]);
        exit;
    }

    // Calcular possíveis ganhos
    $valor_possivel = floatval($odds) * floatval($valor_apostado);  // Calcula o ganho com base nas odds e no valor de aposta

    // Inserir a aposta na tabela de apostas
    $sqlInsertAposta = "INSERT INTO apostas (competitor_id, competidor, cavalo, categoria, evento, id_usuario, odds, valor_apostado, valor_possivel) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
    $stmtInsertAposta = $conn->prepare($sqlInsertAposta);

    if ($stmtInsertAposta === false) {
        echo json_encode(["success" => false, "message" => 'prepare() failed: ' . htmlspecialchars($conn->error)]);
        exit;
    }

    $stmtInsertAposta->bind_param("issssiidd", $competitorId, $competitor, $cavalo, $categoria, $evento, $id_usuario, $odds, $valor_apostado, $valor_possivel);

    if ($stmtInsertAposta->execute()) {
        // Se a aposta foi inserida com sucesso, atualizar o saldo do usuário
        $novoSaldo = $wallet - $valor_apostado;
        $sqlUpdateSaldo = "UPDATE usuarios SET wallet = ? WHERE id = ?";
        $stmtUpdateSaldo = $conn->prepare($sqlUpdateSaldo);
        $stmtUpdateSaldo->bind_param("di", $novoSaldo, $idUsuarioLogado);

        if ($stmtUpdateSaldo->execute()) {
            echo json_encode(["success" => true, "message" => "Aposta registrada com sucesso!"]);

            // Buscar todas as apostas para o competidor e cavalo no evento atual
            $sqlBuscarApostas = "SELECT * FROM apostas WHERE competitor_id = ? AND competidor = ? AND cavalo = ? AND evento = ? AND categoria = ?";
            $stmtBuscarApostas = $conn->prepare($sqlBuscarApostas);
            $stmtBuscarApostas->bind_param("isssi", $competitorId, $competitor, $cavalo, $evento, $categoria);
            $stmtBuscarApostas->execute();
            $resultado = $stmtBuscarApostas->get_result();
            $apostas = $resultado->fetch_all(MYSQLI_ASSOC);
            $stmtBuscarApostas->close();

            // Calcular o total apostado e a responsabilidade total para o competidor e cavalo no evento atual
            $total_staked = 0;
            $total_liability = 0;
            foreach ($apostas as $aposta) {
                $total_staked += $aposta['valor_apostado'];
                $total_liability += $aposta['valor_apostado'] * $aposta['odds'];
            }

            // Calcular a nova odd
            $bookmaker_margin = 1.3;  // 30% de margem para a casa
            $new_odd = $total_staked > 0 ? $total_liability / $total_staked : 0;
            $new_odd *= $bookmaker_margin;  // Ajustar com a margem da casa

            // Atualizar a odd na base de dados
            $sqlUpdateOdds = "UPDATE largada SET odds1 = ? WHERE competitor_id = ? AND competidor = ? AND cavalo = ? AND evento = ? AND categoria = ?";
            $stmtUpdateOdds = $conn->prepare($sqlUpdateOdds);
            $stmtUpdateOdds->bind_param("dissii", $new_odd, $competitorId, $competitor, $cavalo, $evento, $categoria);

            if ($stmtUpdateOdds->execute()) {
                // As odds foram atualizadas com sucesso
            } else {
                // Tratar o erro
            }

            $stmtUpdateOdds->close();
        } else {
            echo json_encode(["success" => false, "message" => "Erro ao atualizar saldo do usuário: " . $stmtUpdateSaldo->error]);
        }

        $stmtUpdateSaldo->close();
    } else {
        echo json_encode(["success" => false, "message" => "Erro ao registrar a aposta: " . $stmtInsertAposta->error]);
    }
    
    $stmtInsertAposta->close();
}

?>
