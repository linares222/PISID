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
    $_SESSION['error'] = "ID do Jogo inválido para remoção.";
}

// Se não houver erros de validação
if (empty($_SESSION['error']) && $jogo_id !== null) {
    // Opcional: Verificar se o user é dono do jogo antes de remover
    // SELECT IDUtilizador FROM jogo WHERE IDJogo = ? AND IDUtilizador = ?

    $sql = "CALL Remover_Jogo(?)";

    if ($stmt = mysqli_prepare($link, $sql)) {
        mysqli_stmt_bind_param($stmt, "i", $jogo_id);

        if (mysqli_stmt_execute($stmt)) {
             // Verificar se afetou alguma linha
             if (mysqli_stmt_affected_rows($stmt) > 0) {
                  $_SESSION['message'] = "Jogo #$jogo_id removido com sucesso!";
             } else {
                  $_SESSION['error'] = "Jogo #$jogo_id não encontrado ou não pôde ser removido (verifique se pertence a si e se não tem dados associados). ";
             }
           
        } else {
            // Erro na execução - provavelmente chave estrangeira
            $_SESSION['error'] = "Erro ao executar o procedimento Remover_Jogo (pode haver dados associados a este jogo): " . mysqli_stmt_error($stmt);
        }
        mysqli_stmt_close($stmt);
    } else {
        $_SESSION['error'] = "Erro ao preparar a chamada ao procedimento Remover_Jogo: " . mysqli_error($link);
    }
}

mysqli_close($link);

// Redirecionar de volta para o dashboard
header("location: dashboard.php");
exit;

?> 