<?php
// --- DEBUGGING: MOSTRAR ERROS --- (Remover depois)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
// --- FIM DEBUGGING ---

session_start(); // Iniciar a sessão no topo

// Incluir ficheiro de configuração da BD
require_once "db_config.php";

// Verificar se o formulário foi submetido
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = "";
    $login_error = "";

    // Validar email (simples validação se não está vazio)
    if (empty(trim($_POST["email"]))) {
        $login_error = "Por favor, insira o seu email.";
    } else {
        $email = trim($_POST["email"]);
    }

    // Se não houver erros de validação
    if (empty($login_error)) {
        // Preparar a declaração SELECT
        $sql = "SELECT IDUtilizador, Nome, Email FROM utilizador WHERE Email = ? AND Estado = 'Ativo'";

        if ($stmt = mysqli_prepare($link, $sql)) {
            // Vincular variáveis à declaração preparada como parâmetros
            mysqli_stmt_bind_param($stmt, "s", $param_email);

            // Definir parâmetros
            $param_email = $email;

            // Tentar executar a declaração preparada
            if (mysqli_stmt_execute($stmt)) {
                // Armazenar resultado
                mysqli_stmt_store_result($stmt);

                // Verificar se o email existe
                if (mysqli_stmt_num_rows($stmt) == 1) {
                    // Vincular variáveis de resultado
                    mysqli_stmt_bind_result($stmt, $id, $nome, $email_db);
                    if (mysqli_stmt_fetch($stmt)) {
                        // Email existe, iniciar sessão
                        // session_start(); // Já iniciada no topo

                        // Armazenar dados na sessão
                        $_SESSION["loggedin"] = true;
                        $_SESSION["id"] = $id;
                        $_SESSION["nome"] = $nome;
                        $_SESSION["email"] = $email_db;

                        // Redirecionar para a página do dashboard
                        header("location: dashboard.php");
                        exit; // É importante chamar exit() após header() para parar a execução do script
                    }
                } else {
                    // Email não encontrado ou utilizador não ativo
                    $login_error = "Email não encontrado ou conta inativa.";
                }
            } else {
                // Usar uma mensagem genérica em produção
                // echo "Oops! Algo correu mal. Por favor, tente novamente mais tarde.";
                 $login_error = "Erro ao tentar verificar o email.";
            }

            // Fechar declaração
            mysqli_stmt_close($stmt);
        } else {
             $login_error = "Erro na preparação da consulta SQL.";
        }
    }

    // Se houve um erro de login, guardar na sessão e redirecionar de volta para index.php
    if (!empty($login_error)) {
        $_SESSION['login_error'] = $login_error;
        header("location: index.php");
        exit;
    }

    // Fechar conexão
    mysqli_close($link);
}
?> 