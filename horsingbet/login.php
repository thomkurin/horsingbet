<?php

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

include_once("conexao.php");
session_start();

if(isset($_SESSION['loggedin']) && $_SESSION['loggedin'] === true){
    header("location: index.php");
    exit;
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = filter_input(INPUT_POST, 'email', FILTER_VALIDATE_EMAIL);
    $senha = trim($_POST['senha']);

    // Validação de entrada
    if (!$email || !$senha) {
        echo "Por favor, preencha todos os campos corretamente.";
        exit;
    }

    // Proteção contra ataques de força bruta
    $maxAttempts = 5; // Número máximo de tentativas permitidas
    $lockoutDuration = 60; // Tempo de bloqueio após exceder o limite de tentativas (em segundos)
    $ip = $_SERVER["REMOTE_ADDR"]; // Obtendo o endereço IP do cliente 

    // Verificando se o IP do cliente está bloqueado
    $blockedIPs = getBlockedIPs($conn);
    if (isIPBlocked($ip, $conn)) {
        // IP bloqueado, redirecionando para uma página de erro ou exibindo uma mensagem adequada
        exit("Você excedeu o limite de tentativas. Por favor, tente novamente mais tarde.");
    }

// Definindo a consulta SQL
$sql = "SELECT id, email, senha, is_admin FROM usuarios WHERE email = ?";

    if ($stmt = $conn->prepare($sql)) {
        // Filtrar e sanitizar os dados de entrada
        $email = filter_var($email, FILTER_SANITIZE_EMAIL);
    
        // bind parameters to the prepared statement
        $stmt->bind_param("s", $email);
    
        if ($stmt->execute()) {
            $stmt->store_result();
    
            if ($stmt->num_rows == 1) {
                // Adicionado $is_admin aqui
                $stmt->bind_result($id, $email, $senha_hashed, $is_admin);
                if ($stmt->fetch()) {
                    // Verifique a senha fornecida
                    if (password_verify($senha, $senha_hashed)) {
                        $_SESSION["loggedin"] = true;
                        $_SESSION["id"] = $id;
                        $_SESSION["email"] = $email;
                        $_SESSION["is_admin"] = $is_admin;  // Adiciona o status do administrador à sessão
                        // Se o login for bem-sucedido, registre a tentativa como bem-sucedida
                        registerAttempt($ip, true, $conn);
    
                        if ($is_admin) {
                            // Se o usuário for um administrador, redirecione-o para admin.php
                            header("location: admin.php");
                            exit;
                        } else {
                            // Se o usuário não for um administrador, redirecione-o para index.php
                            header("location: index.php");
                            exit;
                        }
                    } else {
                        // Senha incorreta, registre uma tentativa falha
                        registerAttempt($ip, false, $conn);
                        checkFailedAttempts($ip, $maxAttempts, $lockoutDuration, $conn);
                        exit("Email ou senha inválidos.");
                    }
                } else {
                    // Usuário não encontrado, registre uma tentativa falha
                    registerAttempt($ip, false, $conn);
                    checkFailedAttempts($ip, $maxAttempts, $lockoutDuration, $conn);
                    exit("Email ou senha inválidos.");
                }
            } else {
                echo "Opa! Algo deu errado. Por favor, tente novamente mais tarde.";
            }
    
            $stmt->close();
        }
    }
    

}

function isIPBlocked($ip, $conn) {
    $stmt = $conn->prepare("SELECT 1 FROM ip_blocked WHERE ip = ? AND blocked_time > NOW() - INTERVAL 24 HOUR");
    $stmt->bind_param("s", $ip);
    $stmt->execute();
    $stmt->store_result();
    $isBlocked = $stmt->num_rows > 0;
    $stmt->close();
    return $isBlocked;
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
<header id="header">
    <div id="cabecalho">
    <div class="botoes">

        <div class="links">
            <div class="link1">
            <a href="#" class="link-baixo">Ao vivo</a> 
        </div>
        <div class="img-logo">
            <img src="logo.png" alt="logo" onclick="window.location.href='index.php'">
        </div>
        <div class="link2">
            <div>
            <a href="#" class="link-baixo" id="register-btn">Registre-se</a>
        </div>
            <br>
            <a href="#" class="link-baixo2" id="login-btn">Login</a>
        </div> 
        </div>
    </div>
    
    <div id="register-modal" class="modal">
            <div class="modal-content">
              <span class="close">&times;</span>
              <h1>Abra sua Conta</h1>
                <div class="registro">
                <form id="registro-form" action="register.php" method="post">
                        <div><h3>Informação Pessoal</h3>
                            <p>Nome</p>
                            <input type="text" name="nome">
                            <p>Sobrenome</p>
                            <input type="text" name="sobrenome">
                            <p>Data de nacismento</p>
                            <input type="date" name="data_nascimento">
                            <p>CPF</p>
                            <input type="text" name="cpf">
                        </div>
                        <div><h3>Informação Para Contato</h3>
                            <p>Telefone</p>
                            <input type="text" name="telefone">
                        </div>
                        <div><h3>Receber informações sobre ofertas e promoções? </h3>
                            <input type="checkbox" id="receber_promocoes" name="receber_promocoes" value="1">
                            <label for="receber_promocoes">Sim, por favor</label>
                        </div>
                        <div><h3>Endereço</h3>
                            <input type="text" name="endereco">
                        </div>
                        <div><h3>Criar Login</h3>
                            <p>E-mail</p>
                            <input type="email" name="email">
                            <p>Senha</p>
                            <input type="password" name="senha">
                        </div>
                        <div>   
                        <br>                        
                        <input type="checkbox" id="maiorIdade" name="maiorIdade" value="1">
                        <label for="maiorIdade">Eu tenho pelo menos 18 anos e li, aceito e concordo com o(a) </label>
                        </div>
                        <br>
                        <button type="submit"><h3>Registre-se na Horsing Bet</h3></button>
                        <p id="error-message" style="color: red;"></p>

                        <script>
                            $('#registro-form').submit(function(e) {
                                e.preventDefault();
                                $.ajax({
                                    type: "POST",
                                    url: "register.php",
                                    data: $("#registro-form").serialize(),
                                    dataType: "json",
                                    success: function(response) {
                                        if(response.status === 200) {
                                            window.location.href = "http://localhost/horsingbet/login.php";
                                        } else {
                                            $('#error-message').text(response.message);
                                        }
                                    },
                                    error: function(jqXHR, textStatus, errorThrown) {
                                        $('#error-message').text("Houve um erro na solicitação. Tente novamente mais tarde.");
                                    }
                                });
                            });
                        </script>
                </div>
                </div>
                </form>
            </div>
        </div>

    <div id="login-modal" class="modal">
        <div class="modal-content">
          <span class="close">&times;</span>
          <h1>Login</h1>
          <form action="login.php" method="post">
            <p>E-mail</p>
            <input type="email" name="email">
            <p>Senha</p>
            <input type="password" name="senha">
            <br>
            <button type="submit" id="btnAcess"><h3>Acessar</h3></button>
        </form>        
        </div>
    </div>      
    </header>
    <main>

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
                    // Loop para exibir os eventos encontrados
                    echo "<div class='evento-container'>";  // Adicione esta linha
                    // Loop para exibir os eventos
                    while ($row = $result->fetch_assoc()) {
                        echo "<a href='categorias.php?event_id=" . $row['event_id'] . "' class='evento'>";
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
    </main>



    <footer>

    </footer>
    <script src="modal.js"></script>
</body>
</html>

<?php 
// Fechando a conexão com o banco de dados
$conn->close(); 
?>