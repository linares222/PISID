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

// Validar ID do Jogo
if (isset($_POST['jogo_id']) && is_numeric($_POST['jogo_id'])) {
    $jogo_id = (int)$_POST['jogo_id'];
} else {
    $_SESSION['error'] = "ID do Jogo inválido para interromper.";
}

// Se não houver erros de validação
if (empty($_SESSION['error']) && $jogo_id !== null) {
    $sql = "CALL Interromper_Jogo(?, ?)";

    if ($stmt = mysqli_prepare($link, $sql)) {
        mysqli_stmt_bind_param($stmt, "ii", $jogo_id, $user_id);

        if (mysqli_stmt_execute($stmt)) {
             if (mysqli_stmt_affected_rows($stmt) > 0) {
                  $_SESSION['message'] = "Jogo #$jogo_id interrompido com sucesso!";
             } else {
                  $_SESSION['error'] = "Não foi possível interromper o Jogo #$jogo_id (pode não estar ativo ou não pertencer a si).";
             }
        } else {
            $_SESSION['error'] = "Erro ao executar o procedimento Interromper_Jogo: " . mysqli_stmt_error($stmt);
        }
        mysqli_stmt_close($stmt);
    } else {
        $_SESSION['error'] = "Erro ao preparar a chamada ao procedimento Interromper_Jogo: " . mysqli_error($link);
    }
}

mysqli_close($link);

// Redirecionar de volta para o dashboard
header("location: dashboard.php");
exit;

?> 