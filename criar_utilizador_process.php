<?php
session_start();
require_once "db_config.php";

// Verificar se é POST
if ($_SERVER["REQUEST_METHOD"] != "POST") {
    header("location: criar_utilizador.php"); 
    exit;
}

// --- Validação dos Dados --- 
$nome = trim($_POST['nome'] ?? '');
$telemovel = trim($_POST['telemovel'] ?? '');
$email = trim($_POST['email'] ?? '');
// $tipo = trim($_POST['tipo'] ?? ''); // Remover leitura do POST
$tipo = 'Jogador'; // Definir Tipo fixo
$idgrupo_int = 30; // Definir IDGrupo fixo
$senha = ""; // Senha não é usada pelo procedimento atual

$errors = [];
if (empty($nome)) {
    $errors[] = "Nome é obrigatório.";
}
if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
     $errors[] = "Email inválido ou em falta.";
}
if (!empty($telemovel) && !preg_match('/^[0-9]{9,12}$/', $telemovel)){
     $errors[] = "Telemóvel inválido (deve ter 9 a 12 dígitos).";
}
// Remover validação do Tipo
// if (!in_array($tipo, ['Jogador', 'Investigador', 'Administrador'])) {
//     $errors[] = "Tipo de utilizador inválido.";
// }

// Se houver erros de validação, voltar ao formulário
if (!empty($errors)) {
    $_SESSION['error'] = implode(" ", $errors);
    // Opcional: guardar os valores submetidos para repreencher o formulário
    // $_SESSION['form_data'] = $_POST;
    header("location: criar_utilizador.php");
    exit;
}

// --- Chamar Procedimento --- 
// Atenção: o SP `Criar_Utilizador` aceita `pSenha` mas não a usa no INSERT.
$sql = "CALL Criar_Utilizador(?, ?, ?, ?, ?, ?)";

if ($stmt = mysqli_prepare($link, $sql)) {
    // Vincular parâmetros (s=string, i=integer)
    // O 5º parâmetro (senha) é passado como string vazia.
    mysqli_stmt_bind_param($stmt, "sssssi", $nome, $telemovel, $email, $senha, $tipo, $idgrupo_int);
    
    // tipo e idgrupo_int já estão definidos

    if (mysqli_stmt_execute($stmt)) {
        $_SESSION['message'] = "Utilizador \"" . htmlspecialchars($nome) . "\" criado com sucesso!";
    } else {
        // Verificar erros específicos (ex: email duplicado?)
        if(mysqli_errno($link) == 1062) { // Código de erro para entrada duplicada (pode variar)
             $_SESSION['error'] = "Erro ao criar utilizador: Email ou ID de Grupo já existente.";
        } else {
             $_SESSION['error'] = "Erro ao executar o procedimento Criar_Utilizador: " . mysqli_stmt_error($stmt);
        }
    }
    mysqli_stmt_close($stmt);
} else {
    $_SESSION['error'] = "Erro ao preparar a chamada ao procedimento Criar_Utilizador: " . mysqli_error($link);
}

mysqli_close($link);

// Redirecionar de volta para a página de criação (para mostrar msg)
header("location: criar_utilizador.php");
exit;

?> 