<?php
include_once("conexao.php");

session_start();

// Verifique se o usuário está logado e é um administrador
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true || !isset($_SESSION['is_admin']) || $_SESSION['is_admin'] != 1) {
    // Se o usuário não estiver logado, ou não for um administrador, imprima a mensagem e o botão de retorno
    echo "Você não é admin, volte para a página inicial";
    echo '<br><button onclick="location.href=\'index.php\'">Voltar para a página inicial</button>';
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

    // Adicionamos 'admin' na consulta SQL para obter o status de administrador do usuário
    $sql = "SELECT id, email, senha, is_admin FROM usuarios WHERE email = ?";
        if($stmt = $conn->prepare($sql)){
            $stmt->bind_param("s", $email);

        if($stmt->execute()){
            $stmt->store_result();

            if($stmt->num_rows == 1){
                // Adicionamos $isAdmin no bind_result para capturar o valor 'admin' do usuário 
                $stmt->bind_result($id, $email, $senha_hashed, $is_admin);
                $stmt->fetch();

                if(password_verify($senha, $hashedPassword)){
                    updateLastAccess($userId);
                
                    // Armazenando o nome de usuário, o status de admin e o status de login na sessão
                    $_SESSION['nome'] = $username;
                    $_SESSION['is_admin'] = $is_admin;
                    $_SESSION['loggedin'] = true;
                
                    // Verifique se o usuário é um administrador
                    if ($_SESSION['is_admin']) {
                        // Redirecione para a página de administração
                        header("location: admin.php");
                        exit;
                    } else {
                        // Redirecione para a página normal do usuário
                        header("location: index.php");
                        exit;
                    }
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
    <title>Horsing Bet | ADM</title>
    <link rel="stylesheet" href="style.css">
    <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.5.1/jquery.min.js"></script>
    <script src="submenuAdm.js"></script>
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
<body>
<div class="containerAdm">
    <div class="left-column">
        <div class="menu">
            <h1>Página de Administração</h1>
            <ul>
                <li><a href="admin.php">ADM</a></li>
                <br>
                <li><a href="#usuarios">Gestão de Usuários</a></li>
                <br>
                <li><a href="#" id="apostas-link">Gestão de Apostas</a></li>
                <br>
                <li><a href="#analise" id="update-stats-btn">Análise de Dados</a></li>
                <br>
                <li><a href="#mensagens">Controle de Mensagens</a></li>
            </ul>
            <br>
            <?php
                    include_once("conexao.php");

                    $sql = "SELECT * FROM controle WHERE id = 1";
                    $result = $conn->query($sql);
                    if ($result === FALSE) {
                        echo "Erro ao buscar estatísticas: " . $conn->error;
                        return;
                    }

                    $row = $result->fetch_assoc();

                    echo "<h1>Análise de Dados</h1>";
                    echo "<p>Caixa R$ " . $row['caixa'] . "</p>";
                    echo "<p>Total Apostado R$ " . $row['total_apostado'] . "</p>";
                    echo "<p>Usuários Registrados: " . $row['usuarios_registrados'] . "</p>";
                    echo "<p>Última Atualização: " . $row['data_atualizacao'] . "</p>";
                    ?>
            <section id="usuarios">
                <!-- Conteúdo de gestão de usuários vai aqui -->
            </section>
        </div>
    </div>
    <div class="right-column">
        <section id="apostas-section" class="hidden">
            <!-- Seção de Eventos -->
            <div id="eventos" class="submenu">
                <h1>Eventos</h1>
            <?php
                // Obter todos os eventos
                $sql = "SELECT * FROM eventos";
                $result = $conn->query($sql);
                while($row = $result->fetch_assoc()) {
                    echo "<a href='#' class='event-link' data-event-id='" . $row['event_id'] . "'>" . $row['name'] . "</a><br>";
                }        
                ?>
            </div>

            <!-- Seção de Categorias -->
            <div id="categorias" class="hidden">
                <h1>Categorias</h1>
                <?php
                // Obter todas as categorias para cada evento
                $sqlEventos = "SELECT * FROM eventos";
                $resultEventos = $conn->query($sqlEventos);
                while($rowEvento = $resultEventos->fetch_assoc()) {
                    $sqlCategorias = "SELECT * FROM categorias WHERE event_id = ".$rowEvento['event_id'];
                    $resultCategorias = $conn->query($sqlCategorias);
                    while($rowCategoria = $resultCategorias->fetch_assoc()) {
                        echo "<a href='#' class='category-link event-".$rowEvento['event_id']."' data-category-id='" . $rowCategoria['category_id'] . "'>" . $rowCategoria['name'] . "</a><br>";
                    }
                }
                ?>
            </div>

            <!-- Seção de Competidores -->
            <div id="competidores" class="hidden">
                <h1>Competidores</h1>
                <?php
                // Obter todos os competidores para a categoria selecionada
                $sql = "SELECT * FROM largada";
                $result = $conn->query($sql);
                while($row = $result->fetch_assoc()) {
                    echo "<div>";
                    echo $row['competitor_name'];
                    echo $row['horse_name'];
                    echo "<button type='submit' class='winner-btn' name='winner' value='" . $row['competitor_id'] . "'>Set as winner</button>";
                    echo "</div>";
                }
                ?>
            </div>
        </section>
    </div>
</div> 

    <section id="analise">
        <!-- Conteúdo de análise de dados vai aqui -->
        <div class="containerAdm">
    <div class="left-column">
    </div>
        </div>
        
    </section>
    <section id="mensagens">
        <!-- Conteúdo de controle de mensagens vai aqui -->
    </section>
    <footer>
        <!-- O conteúdo do rodapé vai aqui -->
    </footer>
    <script src="statusAdm.js"></script>
    <script src="modalPerfil.js"></script>
    <script src="menu.js"></script>
</body>
</html>
