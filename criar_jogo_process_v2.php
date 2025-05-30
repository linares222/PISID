<?php
session_start();
require_once "db_config.php";

// Verificar login e se é POST
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true || $_SERVER["REQUEST_METHOD"] != "POST") {
    header("location: index.php"); 
    exit;
}

$user_id = $_SESSION["id"];
$descricao = "";

// Validar descrição
if (empty(trim($_POST["descricao"]))) {
    $_SESSION['error'] = "A descrição do jogo não pode estar vazia.";
} else {
    $descricao = trim($_POST["descricao"]);
}

// Se não houver erros de validação
if (empty($_SESSION['error'])) {
    $sql = "CALL Criar_Jogo(?, ?)";

    if ($stmt = mysqli_prepare($link, $sql)) {
        mysqli_stmt_bind_param($stmt, "is", $param_user_id, $param_descricao);
        
        $param_user_id = $user_id;
        $param_descricao = $descricao;

        if (mysqli_stmt_execute($stmt)) {
            $result = mysqli_stmt_get_result($stmt);
            if($row = mysqli_fetch_assoc($result)){
                 $novo_jogo_id = $row['IDJogoCriado'];
                 $_SESSION['message'] = "Novo jogo criado com sucesso! ID: " . $novo_jogo_id;
            } else {
                 $_SESSION['message'] = "Novo jogo criado com sucesso! (Não foi possível obter o ID)";
            }
        } else {
            $_SESSION['error'] = "Erro ao executar o procedimento Criar_Jogo: " . mysqli_stmt_error($stmt);
        }
        mysqli_stmt_close($stmt);
    } else {
        $_SESSION['error'] = "Erro ao preparar a chamada ao procedimento Criar_Jogo: " . mysqli_error($link);
    }
}

mysqli_close($link);

// Redirecionar de volta para o dashboard
header("location: dashboard.php");
exit;

?> 