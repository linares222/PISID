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
    $_SESSION['error'] = "ID do Jogo inválido para resumir.";
}

// Se não houver erros de validação
// Se não houver erros de validação
if (empty($_SESSION['error']) && $jogo_id !== null) {
    $sql = "CALL Resumir_Jogo(?, ?)";

    if ($stmt = mysqli_prepare($link, $sql)) {
        mysqli_stmt_bind_param($stmt, "ii", $jogo_id, $user_id);

        // Obter IDGrupo associado ao jogo
        $queryGrupo = "SELECT IDGrupo FROM utilizador u
                       JOIN jogo j ON j.IDUtilizador = u.IDUtilizador
                       WHERE j.IDJogo = ?";
        if ($stmtGrupo = mysqli_prepare($link, $queryGrupo)) {
            mysqli_stmt_bind_param($stmtGrupo, "i", $jogo_id);
            mysqli_stmt_execute($stmtGrupo);
            mysqli_stmt_bind_result($stmtGrupo, $id_grupo);
            mysqli_stmt_fetch($stmtGrupo);
            mysqli_stmt_close($stmtGrupo);
        }

        if (mysqli_stmt_execute($stmt)) {
            if (mysqli_stmt_affected_rows($stmt) > 0) {
                // Abrir o mazerun.exe numa nova janela do cmd com o IDGrupo
                if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
                    $cmd = 'start cmd /K "cd .. && mazerun.exe ' . escapeshellarg($id_grupo) . ' 1 2"';
                    pclose(popen($cmd, "r"));
                }

                $_SESSION['message'] = "Jogo #$jogo_id resumido com sucesso!";
            } else {
                $_SESSION['error'] = "Não foi possível resumir o Jogo #$jogo_id (pode não estar pausado ou não pertencer a si).";
            }
        } else {
            $_SESSION['error'] = "Erro ao executar o procedimento Resumir_Jogo: " . mysqli_stmt_error($stmt);
        }

        mysqli_stmt_close($stmt);
    } else {
        $_SESSION['error'] = "Erro ao preparar a chamada ao procedimento Resumir_Jogo: " . mysqli_error($link);
    }
}


mysqli_close($link);

// Redirecionar de volta para o dashboard
header("location: dashboard.php");
exit;

?> 