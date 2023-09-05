<?php
$host = "localhost"; // host do servidor MySQL
$db = "horsingbet"; // nome do banco de dados
$user = "root"; // nome do usuário do MySQL
$pass = ""; // senha do usuário do MySQL

// Criando a conexão com o banco de dados
$conn = new mysqli($host, $user, $pass, $db);

// Verificando a conexão
if ($conn->connect_error) {
    die("Falha na conexão: " . $conn->connect_error);
} 

// Configurando o charset para UTF8
if (!$conn->set_charset("utf8")) {
    printf("Erro ao carregar o conjunto de caracteres utf8: %s\n", $conn->error);
    exit();
}
?>
