<?php
include_once("conexao.php");
session_start();

if(!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true){
    header("location: login.php");
    exit;
}

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["csrf_token"]) && $_SESSION["csrf_token"] === $_POST["csrf_token"]) {
    $maxAttempts = 5;
    $lockoutDuration = 60;
    $ip = $_SERVER["REMOTE_ADDR"];

    $blockedIPs = getBlockedIPs();
    if (in_array($ip, $blockedIPs)) {
        exit("Você excedeu o limite de tentativas. Por favor, tente novamente mais tarde.");
    }

    $email = filter_input(INPUT_POST, 'email', FILTER_VALIDATE_EMAIL);
    $senha = trim(htmlspecialchars($_POST['senha']));

    $sql = "SELECT id, username, password FROM usuarios WHERE email = ?";
    if($stmt = $conn->prepare($sql)){
        $stmt->bind_param("s", $email);

        if($stmt->execute()){
            $stmt->store_result();

            if($stmt->num_rows == 1){                    
                $stmt->bind_result($userId, $username, $hashedPassword);
                $stmt->fetch();

                if(password_verify($senha, $hashedPassword)){
                    updateLastAccess($userId);

                    // Note the use of escapeOutput function when displaying user data
                    exit("Autenticação bem-sucedida! Bem-vindo, " . escapeOutput($username) . ".");
                } else {
                    registerFailedAttempt($ip, $maxAttempts, $lockoutDuration);
                    exit("Senha incorreta.");
                }                
            } else {
                registerFailedAttempt($ip, $maxAttempts, $lockoutDuration);
                exit("Usuário não encontrado.");
            }
        } else{
            exit("Opa! Algo deu errado. Por favor, tente novamente mais tarde.");
        }

        $stmt->close();
    }
}

function registerAttempt($ip, $successful, $conn) {
    $stmt = $conn->prepare("INSERT INTO ip_log (ip, login_time, successful) VALUES (?, NOW(), ?)");
    $stmt->bind_param("si", $ip, $successful);
    $stmt->execute();
    $stmt->close();
}

function checkFailedAttempts($ip, $maxAttempts, $lockoutDuration, $conn) {
    $numFailedAttempts = 0;
    $stmt = $conn->prepare("SELECT COUNT(*) FROM ip_log WHERE ip = ? AND successful = 0 AND login_time > NOW() - INTERVAL ? MINUTE");
    $stmt->bind_param("si", $ip, $lockoutDuration);
    if ($stmt->execute()) {
        $stmt->bind_result($numFailedAttempts);
        $stmt->fetch();
    }

    if ($numFailedAttempts >= $maxAttempts) {
        blockIP($ip, $conn);
    }
    $stmt->close();
}

function blockIP($ip, $conn) {
    $stmt = $conn->prepare("INSERT INTO ip_blocked (ip, blocked_time) VALUES (?, NOW())");
    $stmt->bind_param("s", $ip);
    $stmt->execute();
    $stmt->close();
}

function getBlockedIPs($conn) {
    $stmt = $conn->prepare("SELECT ip FROM ip_blocked WHERE blocked_time > NOW() - INTERVAL 24 HOUR");
    $stmt->execute();
    $stmt->bind_result($ip);

    $blockedIPs = array();
    while ($stmt->fetch()) {
        $blockedIPs[] = $ip;
    }

    return $blockedIPs;
}


// New function to escape output
function escapeOutput($data) {
    return htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
}

if(!isset($_SESSION["csrf_token"])) {
    $_SESSION["csrf_token"] = bin2hex(random_bytes(32));
}

if (isset($_SESSION['id'])) {
    $userId = $_SESSION['id'];

    // Consulta SQL para buscar o saldo do usuário
    $sql = "SELECT wallet FROM usuarios WHERE id = ?";
    $stmt = $conn->prepare($sql);

    // Verificar se a preparação da consulta falhou
    if ($stmt === false) {
        echo "Erro na preparação da consulta: " . $conn->error;
        exit;
    }

    // Bind do parâmetro e execução da consulta
    $stmt->bind_param("i", $userId);

    // Executar a consulta e verificar se a execução falhou
    if (!$stmt->execute()) {
        echo "Erro na execução da consulta: " . $stmt->error;
        exit;
    }

    // Bind do resultado
    $stmt->bind_result($wallet);

    // Verificar se a consulta retornou algum resultado
    if ($stmt->fetch()) {
        // O saldo foi encontrado, ele está armazenado na variável $wallet
    } else {
        echo "Saldo não encontrado para o usuário.";
        exit;
    }

    // Fechar a declaração preparada
    $stmt->close();
} else {
    echo "ID do usuário não definido.";
    exit;
}

?>


<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Horsing Bet | Apostas a Cavalo</title>
    <link rel="stylesheet" href="style.css">
    <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.6.4/jquery.min.js"></script>
  </head>
<body>
<button id="menu-btn">&#9776; Menu</button>

<header id="header">
    <div id="cabecalho">
        <div class="botoes">
        <div class="img-logo">
            <img src="logo.png" alt="logo" onclick="window.location.href='index.php'">
        </div>
            <div class="links">
                <div class="link1">
                    <a href="#" class="link-baixo">Ao vivo</a> 
                </div>
                
            </div>
            <div class="info">
            <div class="info-container" id="infoContainer">
    <div class="text-info">
        <h2>Bem vindo!</h2>
        <p>Saldo: R$ <?php echo $wallet; ?></p>
    </div>
    <div class="img-info">
        <img src="favicon.ico" alt="perfil">
    </div>                
</div>
</div>
        </div>
    </div>
<!-- Modal -->
<div id="infoModal" class="modal full-width-modal">
  <div class="modal-content">
    <div class="modal-header">
      <span class="close">&times;</span>
      <h4>Informações do usuário</h2>
    </div>
    <div class="modal-body">
        <div class="tab">
          <button class="tablinks" onclick="openTab(event, 'Conta')">Conta</button>
          <button class="tablinks" onclick="openTab(event, 'Ofertas')">Minhas Ofertas</button>
          <button class="tablinks" onclick="openTab(event, 'Preferencias')">Preferencias</button>
        </div>

        <div id="Conta" class="tabcontent">
  <h5>Conta</h5>
  <div class="account-info">
    <div class="account-section">
      <a href="#">
        <img src="bank.svg" alt="Ícone" class="icon">
        Banco
      </a>
    </div>
    <div class="account-section">
      <a href="#">
        <img src="chat-dots.svg" alt="Ícone" class="icon">
        Mensagem
      </a>
    </div>
    <div class="account-section">
      <a href="minhaConta.php">
        <img src="person.svg" alt="Ícone" class="icon">
        Minha Conta
      </a> 
    </div>
    <div class="account-section">
      <a href="controleAposta.php">
        <img src="toggles2.svg" alt="Ícone" class="icon">
        Controle de Apostas
      </a>
    </div>
    <div class="account-section">
      <a href="#">
        <img src="percent.svg" alt="Ícone" class="icon">
        Minha Atividade
      </a>
    </div>
    <div class="account-section">
      <a href="#">
        <img src="clock-history.svg" alt="Ícone" class="icon">
         Histórico
      </a>
    </div>
  </div>
</div>



        <div id="Ofertas" class="tabcontent">
          <h5>Ofertas</h5>
          <p>Informações das ofertas vão aqui.</p>
        </div>

        <div id="Preferencias" class="tabcontent">
          <h5>Preferencias</h5>
          <p>Informações das preferencias vão aqui.</p>
        </div>
    </div>
    <div>
        <a href="logout.php">Sair</a>
    </div>
  </div>
</div>


            </div>
        </div>
    </div>
</header>

<main>
    <div class="content">
        <div class="provas">
        <?php
        $event_id = $_GET['event_id'];
        $category_id = $_GET['category_id'];

        // Consulta para buscar o nome do evento
        $sqlBuscarEvento = "SELECT name FROM eventos WHERE id = ?";
        $stmtBuscarEvento = $conn->prepare($sqlBuscarEvento);
        $stmtBuscarEvento->bind_param("i", $event_id);
        $stmtBuscarEvento->execute();
        $resultadoEvento = $stmtBuscarEvento->get_result();
        $rowEvento = $resultadoEvento->fetch_assoc();
        $nomeEvento = $rowEvento['name'];
        $stmtBuscarEvento->close();
        
        // Consulta para buscar o nome da categoria
        $sqlBuscarCategoria = "SELECT name FROM categorias WHERE id = ?";
        $stmtBuscarCategoria = $conn->prepare($sqlBuscarCategoria);
        $stmtBuscarCategoria->bind_param("i", $category_id);
        $stmtBuscarCategoria->execute();
        $resultadoCategoria = $stmtBuscarCategoria->get_result();
        $rowCategoria = $resultadoCategoria->fetch_assoc();
        $nomeCategoria = $rowCategoria['name'];
        $stmtBuscarCategoria->close();
        
if (isset($nomeEvento)) {
    echo "<h2>" . $nomeEvento . "</h2>";
} else {
    echo "Evento - ";
}

if (isset($nomeCategoria)) {
    echo "<h2>" . $nomeCategoria . "</h2>";
} else {
    echo "Categoria";
}
?>
            <?php
// Verifique se os IDs do evento e da categoria estão presentes na URL
if (isset($_GET['event_id']) && isset($_GET['category_id'])) {
    $eventId = $_GET['event_id'];
    $categoryId = $_GET['category_id'];

    // Consulte o banco de dados para obter os competidores da largada com base nos IDs do evento e da categoria
    $sqlCompetidores = "SELECT * FROM largada WHERE event_id = $eventId AND category_id = $categoryId";
    $resultCompetidores = $conn->query($sqlCompetidores);

    if ($resultCompetidores === false) {
        // Houve um erro na consulta SQL
        echo "Erro na consulta de competidores: " . $conn->error;
    } else {
        if ($resultCompetidores->num_rows > 0) {
            echo "<table class='competidores-tabela'>";
            echo "<tr>";
            echo "<th>ID do Conjunto</th>";
            echo "<th>Competidor</th>";
            echo "<th>Cavalo</th>";
            echo "<th>Odds</th>";
            echo "</tr>";

            while ($rowCompetidores = $resultCompetidores->fetch_assoc()) {
                $competitorId = $rowCompetidores['id'];
                $nomeCompetidor = $rowCompetidores['competitor_name'];
                $nomeCavalo = $rowCompetidores['horse_name'];
                $odds = $rowCompetidores['odds'];
                
                echo "<tr>";
                echo "<td>$competitorId</td>";
                echo "<td>$nomeCompetidor</td>";
                echo "<td>$nomeCavalo</td>";
                echo "<td class='odds-modal' data-competidor='$nomeCompetidor' data-cavalo='$nomeCavalo' data-competitor-id='$competitorId' data-odds='$odds'>$odds</td>";
                echo "</tr>";  
            }
            echo "</table>";
        } else {
            echo "Nenhum competidor encontrado para o evento e categoria selecionados.";
        }

        // Fechar o resultado da consulta
        $resultCompetidores->close();
    }
} else {
    // IDs do evento e/ou da categoria não fornecidos na URL
    echo "IDs do evento e/ou da categoria não encontrados.";
}
?>

<div id="apostaModal" class="modal">
    <div class="modal-content">
        <span class="close">&times;</span>
        <h2>Apostar</h2>
        <div id="apostaInfo">
            <table>
                <tr>
                    <td><strong>Competidor:</strong></td>
                    <td><span id="competidor"></span></td>
                </tr>
                <tr>
                    <td><strong>Cavalo:</strong></td>
                    <td><span id="cavalo"></span></td>
                </tr>
                <tr>
                    <td><strong>Categoria:</strong></td>
                    <td><span id="categoria"><?php echo $nomeCategoria; ?></span></td>
                </tr>
                <tr>
                    <td><strong>Evento:</strong></td>
                    <td><span id="evento"><?php echo $nomeEvento; ?></span></td>
                </tr>
            </table>
        </div>
        <form id="formAposta" action="apostar.php" method="POST">
            <label for="odds">Primeiro Lugar (Odds: <span id="odds"></span>):</label>
            <input type="text" name="valor_apostado" id="valor_apostado">
            <br>
            <span id="possivel-ganho1"></span>
            <br>
            
            <input type="hidden" name="odds" id="oddsInput">
            <input type="hidden" name="competidor" id="competidorInput">
            <input type="hidden" name="cavalo" id="cavaloInput">
            <input type="hidden" name="competitor_id" id="competitorId">
            <input type="hidden" name="evento" id="eventoHidden" value="<?php echo $nomeEvento; ?>">
            <input type="hidden" name="categoria" id="categoriaHidden" value="<?php echo $nomeCategoria; ?>">
            <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
            <input type="submit" value="Confirmar Aposta">
        </form>

    </div>
</div>
</div>
<div class="apostados">
            <h1>Top apostas</h1>
            <?php
    include_once("conexao.php");

    $sql = "SELECT competidor, cavalo, valor_apostado FROM apostas ORDER BY valor_apostado DESC LIMIT 5";
    $result = $conn->query($sql);

    if ($result->num_rows > 0) {
        echo "<table class='top-apostas-tabela'>";
        echo "<tr>";
        echo "<th>Competidor</th>";
        echo "<th> - </th>";
        echo "<th>Cavalo</th>";
        echo "<th> - </th>";
        echo "<th>Valor Apostado</th>";
        echo "</tr>";

        while($row = $result->fetch_assoc()) {
            $competidor = $row['competidor'];
            $cavalo = $row['cavalo'];
            $valor_apostado = $row['valor_apostado'];

            echo "<tr>";
            echo "<td>$competidor</td>";
            echo "<th> - </th>";
            echo "<td>$cavalo</td>";
            echo "<th> - </th>";
            echo "<td>R$ " . number_format($valor_apostado, 2, ',', '.') . "</td>";
            echo "</tr>";
        }

        echo "</table>";
    } else {
        echo "Nenhuma aposta encontrada.";
    }

    $result->close();
?>
        <h1>Minhas Apostas</h1>
        <?php
include_once("conexao.php");

// Pegar o ID do usuário logado
$idUsuarioLogado = $_SESSION['id'];

$sql = "SELECT competidor, cavalo, valor_apostado FROM apostas WHERE id_usuario = ? ORDER BY valor_apostado DESC";
$stmt = $conn->prepare($sql);

if ($stmt === false) {
    die('prepare() failed: ' . htmlspecialchars($conn->error));
}

$stmt->bind_param("i", $idUsuarioLogado);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    echo "<table class='top-apostas-tabela'>";
    echo "<tr>";
    echo "<th>Competidor</th>";
    echo "<th> - </th>";
    echo "<th>Cavalo</th>";
    echo "<th> - </th>";
    echo "<th>Valor Apostado</th>";
    echo "</tr>";

    while($row = $result->fetch_assoc()) {
        $competidor = $row['competidor'];
        $cavalo = $row['cavalo'];
        $valor_apostado = $row['valor_apostado'];

        echo "<tr>";
        echo "<td>$competidor</td>";
        echo "<th> - </th>";
        echo "<td>$cavalo</td>";
        echo "<th> - </th>";
        echo "<td>R$ " . number_format($valor_apostado, 2, ',', '.') . "</td>";
        echo "</tr>";
    }

    echo "</table>";
} else {
    echo "Nenhuma aposta encontrada.";
}

$stmt->close();

?>
        </div>

</main>

    <footer>
        <!-- O conteúdo do rodapé vai aqui -->
    </footer>
    <script src="menu.js"></script>
    <script src="modalPerfil.js"></script>
    <script src="modalAposta.js"></script>
</body>
</html>

