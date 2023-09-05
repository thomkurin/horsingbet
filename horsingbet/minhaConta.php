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
    <?php
    if (isset($_SESSION['id'])) {
        $userId = $_SESSION['id'];
    
        // Preparar a consulta SQL
        $sql = "SELECT telefone, endereco, email FROM usuarios WHERE id = ?";
    
        // Preparar a declaração
        $stmt = $conn->prepare($sql);
    
        // Vincular parâmetros
        $stmt->bind_param('i', $userId);
    
        // Executar a declaração
        $stmt->execute();
    
        // Obter os resultados
        $result = $stmt->get_result();
    
        if ($result->num_rows > 0) {
            $user = $result->fetch_assoc();
    
            $telefone = $user['telefone'];
            $endereco = $user['endereco'];
            $email = $user['email'];
        } else {
            echo 'Usuário não encontrado.';
        }
    }
?>    
    <div class="container">
        <div class="left-column">
            <div class="menu">
                <h2>Minha Conta</h2>
                <ul>
                    <li><a href="#" onclick="showSection('dados-contato')">Dados de Contato</a></li>
                    <li><a href="#" onclick="showSection('endereco-residencial')">Endereço Residencial</a></li>
                    <li><a href="#" onclick="showSection('alterar-senha')">Alterar Senha</a></li>
                    <li><a href="#" onclick="showSection('verificacao')">Verificação</a></li>
                </ul>
            </div>
        </div>
        <div class="right-column">
    <div id="dados-contato" class="section">
    <h3>Dados de Contato</h3>
    <div class="data-item">
        <label for="telefone">Telefone:</label>
        <p id="telefone"><?php echo $telefone ?></p> 
        <div id="telefoneUpdate" style="display: none;">
            <label for="novo-telefone">Atualizar Telefone:</label>
            <input type="text" id="novo-telefone" name="novo-telefone">
            <button id="updateTelefoneBtn" onclick="atualizarTelefone()">Atualizar Telefone</button>
        </div>
        <button id="alterarTelefoneBtn" onclick="alterarTelefone()">Alterar Telefone</button>
    </div>
    <br>
    <div class="data-item">
        <label for="email">Email:</label>
        <p id="email"><?php echo $_SESSION['email']; ?></p>
        <div id="emailUpdate" style="display: none;">
            <label for="novo-email">Atualizar Email:</label>
            <input type="email" id="novo-email" name="novo-email">
            <button id="updateEmailBtn" onclick="atualizarEmail()">Atualizar Email</button>
        </div>
        <button id="alterarEmailBtn" onclick="alterarEmail()">Alterar Email</button>
    </div>
</div>
<div id="endereco-residencial" class="section">
    <h3>Endereço Residencial</h3>
    <p id="endereco"><?php echo $endereco ?></p> 
    <div id="enderecoUpdate" style="display: none;">
        <label for="novo-endereco">Atualizar Endereço:</label>
        <input type="text" id="novo-endereco" name="novo-endereco">
        <button id="updateEnderecoBtn" onclick="atualizarEndereco()">Atualizar Endereço</button>
    </div>
    <button id="alterarEnderecoBtn" onclick="alterarEndereco()">Alterar Endereço</button>
</div>
<div id="alterar-senha" class="section">
    <h3>Alterar Senha</h3>
    <p>Por favor utilize letras, números e símbolos, sem espaços, com um mínimo de 6 e um máximo de 32 caracteres.</p>
    <br>
    <p>A sua palavra-passe não deverá conter o seu nome de utilizador, o seu nome, o seu endereço de e-mail nem o seu ano de nascimento. 
        Poderá aumentar o grau de segurança da sua palavra-passe utilizando uma mistura de letras, números e símbolos.
         Por favor recorde-se que as palavras-passe são sensíveis a maiúsculas. </p>
         <br>
        <p>Os símbolos que se seguem podem ser utilizados na sua palavra-passe:</p>
        <br>
        <p>! " # $ % &amp; ' ( ) * + , - . / : ; &lt; = &gt; ? _ @ [ \ ] ^ ` { | } ~</p>

    <div id="senhaUpdate" style="display: none;">
        <label for="senha-atual">Senha Atual:</label>
        <input type="password" id="senha-atual">
        <label for="nova-senha">Nova Senha:</label>
        <input type="password" id="nova-senha">
        <button id="updateSenhaBtn" onclick="atualizarSenha()">Atualizar Senha</button>
    </div>
    <button id="alterarSenhaBtn" onclick="alterarSenha()">Alterar Senha</button>
</div>

            <div id="verificacao" class="section">
                <h3>Verificação</h3>
                <p>Aqui você pode enviar seu documento para verificação.</p>
                <a href="#" id="enviar-documento">Enviar Documento</a>
            </div>
        </div>
    </div>
</main>

<script>
    function showSection(sectionId) {
        var sections = document.getElementsByClassName("section");
        for (var i = 0; i < sections.length; i++) {
            sections[i].style.display = "none";
        }
        document.getElementById(sectionId).style.display = "block";
    }
</script>
    <footer>
        <!-- O conteúdo do rodapé vai aqui -->
    </footer>
    <script src="minhaConta.js"></script>
    <script src="menu.js"></script>
    <script src="modalPerfil.js"></script>
</body>
</html>

