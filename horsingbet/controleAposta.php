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
    <div class="container">
        <div class="left-column">
            <div class="menu">
                <h2>Minha Conta</h2>
                <ul>
                    <li><a href="#" onclick="showSection('minha-atividade')">Minha Atividade</a></li>
                    <li><a href="#" onclick="showSection('limites-credito')">Limites de Crédito</a></li>
                    <li><a href="#" onclick="showSection('periodo-pausa')">Período de Pausa</a></li>
                    <li><a href="#" onclick="showSection('autoexclusao')">Autoexclusão</a></li>
                    <li><a href="#" onclick="showSection('encerramento-conta')">Encerramento de Conta</a></li>
                    <li><a href="#" onclick="showSection('alertas-atividades')">Alertas de Atividades</a></li>
                </ul>
            </div>
        </div>
        <div class="right-column">
            <div id="minha-atividade" class="section">
                <h3>Minha Atividade</h3>
                <div class="tabs">
                    <button class="tablinks active" onclick="openTab(event, '7-dias')">7 Dias</button>
                    <button class="tablinks" onclick="openTab(event, '30-dias')">30 Dias</button>
                    <button class="tablinks" onclick="openTab(event, '12-meses')">12 Meses</button>
                </div>
                <div id="7-dias" class="tabcontent active">
                    <div class="data-item">
                        <h4>Ganhos/Perdas</h4>
                        <p>O seu retorno de ganhos menos o valor das suas apostas:</p>
                        <p id="ganhos-perdas-7-dias">R$ 0,00</p>
                    </div>
                    <div class="data-item">
                        <h4>Depósitos Líquidos</h4>
                        <p>O seu total de depósitos menos o total de saques:</p>
                        <div class="deposit-withdrawal">
                            <p>Total de Depósitos</p>
                            <p id="total-depositos-7-dias">R$ 0,00</p>
                        </div>
                        <div class="deposit-withdrawal">
                            <p>Total de Saques</p>
                            <p id="total-saques-7-dias">R$ 0,00</p>
                        </div>
                    </div>
                    <div class="data-item">
                        <h4>Limites de Depósitos</h4>
                        <p>Limite o valor que você pode depositar:</p>
                        <div class="limits">
                            <p>24 horas</p>
                            <button class="alterar">Alterar</button>
                            <p id="limite-24-horas-7-dias">R$ 0,00</p>
                        </div>
                        <div class="limits">
                            <p>7 dias</p>
                            <button class="alterar">Alterar</button>
                            <p id="limite-7-dias-7-dias">R$ 0,00</p>
                        </div>
                        <div class="limits">
                            <p>30 dias</p>
                            <button class="alterar">Alterar</button>
                            <p id="limite-30-dias-7-dias">R$ 0,00</p>
                        </div>
                    </div>
                    <div class="data-item">
                        <h4>Valor Apostado</h4>
                        <p>Valor que você apostou nos últimos 7 dias:</p>
                        <p id="valor-apostado-7-dias">R$ 0,00</p>
                    </div>
                </div>
                <div id="30-dias" class="tabcontent">
                <div id="7-dias" class="tabcontent active">
                    <div class="data-item">
                        <h4>Ganhos/Perdas</h4>
                        <p>O seu retorno de ganhos menos o valor das suas apostas:</p>
                        <p id="ganhos-perdas-7-dias">R$ 0,00</p>
                    </div>
                    <div class="data-item">
                        <h4>Depósitos Líquidos</h4>
                        <p>O seu total de depósitos menos o total de saques:</p>
                        <div class="deposit-withdrawal">
                            <p>Total de Depósitos</p>
                            <p id="total-depositos-7-dias">R$ 0,00</p>
                        </div>
                        <div class="deposit-withdrawal">
                            <p>Total de Saques</p>
                            <p id="total-saques-7-dias">R$ 0,00</p>
                        </div>
                    </div>
                    <div class="data-item">
                        <h4>Limites de Depósitos</h4>
                        <p>Limite o valor que você pode depositar:</p>
                        <div class="limits">
                            <p>24 horas</p>
                            <button class="alterar">Alterar</button>
                            <p id="limite-24-horas-7-dias">R$ 0,00</p>
                        </div>
                        <div class="limits">
                            <p>7 dias</p>
                            <button class="alterar">Alterar</button>
                            <p id="limite-7-dias-7-dias">R$ 0,00</p>
                        </div>
                        <div class="limits">
                            <p>30 dias</p>
                            <button class="alterar">Alterar</button>
                            <p id="limite-30-dias-7-dias">R$ 0,00</p>
                        </div>
                    </div>
                    <div class="data-item">
                        <h4>Valor Apostado</h4>
                        <p>Valor que você apostou nos últimos 7 dias:</p>
                        <p id="valor-apostado-7-dias">R$ 0,00</p>
                    </div>
                </div>
                <div id="12-meses" class="tabcontent">
                <div id="12-meses" class="tabcontent active">
                    <div class="data-item">
                        <h4>Ganhos/Perdas</h4>
                        <p>O seu retorno de ganhos menos o valor das suas apostas:</p>
                        <p id="ganhos-perdas-7-dias">R$ 0,00</p>
                    </div>
                    <div class="data-item">
                        <h4>Depósitos Líquidos</h4>
                        <p>O seu total de depósitos menos o total de saques:</p>
                        <div class="deposit-withdrawal">
                            <p>Total de Depósitos</p>
                            <p id="total-depositos-7-dias">R$ 0,00</p>
                        </div>
                        <div class="deposit-withdrawal">
                            <p>Total de Saques</p>
                            <p id="total-saques-7-dias">R$ 0,00</p>
                        </div>
                    </div>
                    <div class="data-item">
                        <h4>Limites de Depósitos</h4>
                        <p>Limite o valor que você pode depositar:</p>
                        <div class="limits">
                            <p>24 horas</p>
                            <button class="alterar">Alterar</button>
                            <p id="limite-24-horas-7-dias">R$ 0,00</p>
                        </div>
                        <div class="limits">
                            <p>7 dias</p>
                            <button class="alterar">Alterar</button>
                            <p id="limite-7-dias-7-dias">R$ 0,00</p>
                        </div>
                        <div class="limits">
                            <p>30 dias</p>
                            <button class="alterar">Alterar</button>
                            <p id="limite-30-dias-7-dias">R$ 0,00</p>
                        </div>
                    </div>
                    <div class="data-item">
                        <h4>Valor Apostado</h4>
                        <p>Valor que você apostou nos últimos 7 dias:</p>
                        <p id="valor-apostado-7-dias">R$ 0,00</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</main>

<footer>
        <!-- O conteúdo do rodapé vai aqui -->
    </footer>
    <script src="minhaConta.js"></script>
    <script src="menu.js"></script>
    <script src="modalPerfil.js"></script>
    <script src="minhatividade.js"></script>
</body>
</html>