<?php
// Incluindo o arquivo de configuração
include_once("conexao.php");

// SQL para criar a tabela
$sql = "CREATE TABLE ip_log (
    id INT(11) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    ip VARCHAR(45) NOT NULL,
    login_time TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    successful BOOLEAN DEFAULT FALSE
)
";

// Executando o SQL
if ($conn->query($sql) === TRUE) {
    echo "Tabela Usuarios criada com sucesso";
} else {
    echo "Erro ao criar tabela: " . $conn->error;
}

$conn->close();
?>
