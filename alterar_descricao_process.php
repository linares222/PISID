<?php
session_start();
require_once "db_config.php";

// Verificar login e POST
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true || $_SERVER["REQUEST_METHOD"] != "POST") {
    header("location: index.php"); 
    exit;
}

$user_id = $_SESSION["id"];
$jogo_id = null;
$nova_descricao = '';

// Validar dados recebidos
if (isset($_POST['jogo_id']) && is_numeric($_POST['jogo_id'])) {
    $jogo_id = (int)$_POST['jogo_id'];
} else {
    $_SESSION['error'] = "ID do Jogo inválido.";
}

if (empty(trim($_POST["descricao"]))) {
    $_SESSION['error'] = ($_SESSION['error'] ?? '') . " A nova descrição não pode estar vazia.";
} else {
    $nova_descricao = trim($_POST["descricao"]);
}

// Se não houver erros de validação
if (empty($_SESSION['error']) && $jogo_id !== null) {
    // Opcional: Verificar novamente se o user é dono do jogo antes de alterar
    // SELECT IDUtilizador FROM jogo WHERE IDJogo = ? -> verificar se $user_id corresponde

    $sql = "CALL Alterar_Descricao_Jogo(?, ?)";

    if ($stmt = mysqli_prepare($link, $sql)) {
        mysqli_stmt_bind_param($stmt, "is", $jogo_id, $nova_descricao);

        if (mysqli_stmt_execute($stmt)) {
             $_SESSION['message'] = "Descrição do jogo #$jogo_id alterada com sucesso!";
        } else {
            $_SESSION['error'] = "Erro ao executar o procedimento Alterar_Descricao_Jogo: " . mysqli_stmt_error($stmt);
        }
        mysqli_stmt_close($stmt);
    } else {
        $_SESSION['error'] = "Erro ao preparar a chamada ao procedimento Alterar_Descricao_Jogo: " . mysqli_error($link);
    }
}

mysqli_close($link);

// Redirecionar de volta para o dashboard
header("location: dashboard.php");
exit;

?> 