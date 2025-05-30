<?php
session_start();
require_once "db_config.php";

// Verificar login e se é POST
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true || $_SERVER["REQUEST_METHOD"] != "POST") {
    header("location: index.php"); 
    exit;
}

$user_id = $_SESSION["id"];
$jogo_id = null;

// Validar ID do Jogo
if (isset($_POST["jogo_id"]) && is_numeric($_POST["jogo_id"])) {
    $jogo_id = (int)$_POST["jogo_id"];
} else {
    $_SESSION['error'] = "ID do Jogo inválido.";
}

// Se não houver erros de validação
if (empty($_SESSION['error']) && $jogo_id !== null) {
    // Verificar se o jogo pertence mesmo ao utilizador ( segurança extra opcional )
    // SELECT IDUtilizador FROM jogo WHERE IDJogo = ? -> verificar se $user_id corresponde
    
    $sql = "CALL FecharJogoPorJogador(?)"; // Assumindo que este procedimento usa o IDUtilizador logado
    // Se o procedimento FecharJogoPorJogador na BD realmente precisar do IDJogo em vez do IDUtilizador:
    // $sql = "UPDATE jogo SET Estado = 'Desativo_jogador', HoraUpdate = NOW() WHERE IDJogo = ? AND IDUtilizador = ? AND Estado = 'Ativo'"; 
    // E ajustar o bind_param e parâmetros abaixo.
    // -> Mas vamos assumir que `FecharJogoPorJogador` funciona com IDUtilizador como no SQL original.

    if ($stmt = mysqli_prepare($link, $sql)) {
        // Assumindo que FecharJogoPorJogador recebe IDUtilizador
        mysqli_stmt_bind_param($stmt, "i", $param_user_id);
        $param_user_id = $user_id;

        /* // Se FecharJogoPorJogador receber IDJogo (ajustar SP ou usar UPDATE direto)
        mysqli_stmt_bind_param($stmt, "i", $param_jogo_id); 
        $param_jogo_id = $jogo_id;
        */

        if (mysqli_stmt_execute($stmt)) {
            // Verificar se alguma linha foi afetada (se foi fechado)
            if (mysqli_stmt_affected_rows($stmt) > 0) {
                $_SESSION['message'] = "Jogo terminado com sucesso!";
            } else {
                // Pode não ter afetado se o jogo já não estava Ativo ou não pertencia ao user
                 $_SESSION['message'] = "Jogo não foi terminado (pode já estar fechado ou não ser seu)."; 
            }
        } else {
            $_SESSION['error'] = "Erro ao executar o procedimento para terminar o jogo: " . mysqli_stmt_error($stmt);
        }
        mysqli_stmt_close($stmt);
    } else {
        $_SESSION['error'] = "Erro ao preparar a chamada ao procedimento para terminar o jogo: " . mysqli_error($link);
    }
}

mysqli_close($link);

// Redirecionar de volta para o dashboard
header("location: dashboard.php");
exit;

?> 