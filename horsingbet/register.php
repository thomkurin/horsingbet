<?php
session_start();
include_once("conexao.php");

$nome = mysqli_real_escape_string($conn, $_POST['nome']);
$sobrenome = mysqli_real_escape_string($conn, $_POST['sobrenome']);
$data_nascimento_input = mysqli_real_escape_string($conn, $_POST['data_nascimento']);
$cpf = mysqli_real_escape_string($conn, $_POST['cpf']);
$telefone = mysqli_real_escape_string($conn, $_POST['telefone']);
$endereco = mysqli_real_escape_string($conn, $_POST['endereco']);
$email = mysqli_real_escape_string($conn, $_POST['email']);
$senha = mysqli_real_escape_string($conn, $_POST['senha']);
$receber_promocoes = isset($_POST['receber_promocoes']) ? 1 : 0;
$maior_idade = isset($_POST['18+']) ? 1 : 0;
$wallet = 0.00;

$email = filter_var($email, FILTER_VALIDATE_EMAIL);
if (!$email) {
    echo json_encode(array(
        "status" => 400,
        "message" => "Endereço de e-mail inválido."
    ));
    exit();
}

if (strlen($senha) < 8) {
    echo json_encode(array(
        "status" => 400,
        "message" => "A senha deve conter pelo menos 8 caracteres."
    ));
    exit();
}

if (!preg_match('/[A-Z]/', $senha)) {
    echo json_encode(array(
        "status" => 400,
        "message" => "A senha deve conter pelo menos uma letra maiúscula."
    ));
    exit();
}

if (!preg_match('/[a-z]/', $senha)) {
    echo json_encode(array(
        "status" => 400,
        "message" => "A senha deve conter pelo menos uma letra minúscula."
    ));
    exit();
}

if (!preg_match('/\d/', $senha)) {
    echo json_encode(array(
        "status" => 400,
        "message" => "A senha deve conter pelo menos um número."
    ));
    exit();
}

if (!preg_match('/[@$!%*?&.]/', $senha)) {
    echo json_encode(array(
        "status" => 400,
        "message" => "A senha deve conter pelo menos um caractere especial (@, $, !, %, *, ?, &, .)."
    ));
    exit();
}

$sql = "SELECT cpf FROM usuarios WHERE cpf = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param('s', $cpf);
$stmt->execute();
$result = $stmt->get_result();
if ($result->num_rows > 0) {
    echo json_encode(array(
        "status" => 400,
        "message" => "CPF já registrado."
    ));
    exit();
}

$sql = "SELECT email FROM usuarios WHERE email = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param('s', $email);
$stmt->execute();
$result = $stmt->get_result();
if ($result->num_rows > 0) {
    echo json_encode(array(
        "status" => 400,
        "message" => "Email já registrado."
    ));
    exit();
}

$data_atual = new DateTime();
$data_nascimento = DateTime::createFromFormat('Y-m-d', $data_nascimento_input);
$idade = $data_atual->diff($data_nascimento)->y;
if ($idade < 18) {
    echo json_encode(array(
        "status" => 400,
        "message" => "Você precisa ter pelo menos 18 anos para se registrar."
    ));
    exit();
}

$senha_hash = password_hash($senha, PASSWORD_DEFAULT);

$stmt = $conn->prepare("INSERT INTO usuarios (nome, sobrenome, data_nascimento, cpf, telefone, endereco, email, senha, receber_promocoes, wallet, maior_idade) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
$stmt->bind_param('ssssssssidi', $nome, $sobrenome, $data_nascimento_input, $cpf, $telefone, $endereco, $email, $senha_hash, $receber_promocoes, $wallet, $maior_idade);

if ($stmt->execute()) {
    echo json_encode(array(
        "status" => 200,
        "message" => "Novo registro inserido com sucesso"
    ));
    $_SESSION['message'] = "Registro bem-sucedido. Você pode fazer login agora!";
} else {
    echo json_encode(array(
        "status" => 500,
        "message" => "Erro: " . $stmt->error
    ));
    exit();
}

$stmt->close();
$conn->close();
?>
