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

                        // Armazenando o nome de usuário na sessão
                        $_SESSION['nome'] = $username;
    

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
    <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.5.1/jquery.min.js"></script>
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
            <h1>Eventos Acontecendo</h1>
            <?php
            // Consulta SQL para buscar os eventos que estão acontecendo hoje
            $sql = "SELECT * FROM eventos";
            $result = $conn->query($sql);
        
            if ($result === false) {
                // Houve um erro na consulta SQL
                echo "Erro na consulta: " . $conn->error;
            } else {
                if ($result->num_rows > 0) {
                    $row = $result->fetch_assoc();

                    while ($row = $result->fetch_assoc()) {
                        echo "<a href='categorias.php?event_id=" . $row['id'] . "' class='evento'>";
                        echo "<img src='" . $row['image_url'] . "' alt='" . $row['name'] . " logo' />";  
                        echo "<h3>" . $row['name'] . "</h3>";
                        echo "<p>" . $row['dia_inicio'] . " a " . $row['dia_fim'] . " </p>";
                        echo "</a>";
                        echo "<br>";
                    }
                    echo "</div>";               
                } else {
                    echo "Nenhum evento encontrado.";
                }

                // Fechar a consulta
                $result->close();
            }
            ?>
        </div>
        <div class="apostados">
            <h1>Top apostas</h1>
            <?php
    include_once("conexao.php");

    $sql = "SELECT competidor, cavalo, valor_apostado FROM apostas ORDER BY valor_apostado DESC LIMIT 5";
    $result = $conn->query($sql);
    
    if ($result === false) {
        die("Erro: " . $conn->error);
    } elseif ($result->num_rows > 0) {
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
$conn->close();
?>



        </div>

       
    </div>
        </div>
    </main>
    <footer>
        <!-- O conteúdo do rodapé vai aqui -->
    </footer>
    <script src="menu.js"></script>
    <script src="modal.js"></script>
    <script src="modalPerfil.js"></script>
</body>
</html>

