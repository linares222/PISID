<?php
session_start();

// Verificar se o utilizador está logado, senão redirecionar para a página de login
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    header("location: index.php");
    exit;
}

// Incluir ficheiro de configuração da BD
require_once "db_config.php";

// Buscar dados do utilizador da sessão
$user_id = $_SESSION["id"];
$user_name = $_SESSION["nome"];

// Inicializar vitórias
$vitorias = 0; 

// Buscar jogos do utilizador
$jogos = [];
$sql_jogos = "SELECT IDJogo, DataHoraInicio, Estado, Pontuacao FROM jogo WHERE IDUtilizador = ? ORDER BY DataHoraInicio DESC";

if ($stmt_jogos = mysqli_prepare($link, $sql_jogos)) {
    mysqli_stmt_bind_param($stmt_jogos, "i", $user_id);
    if (mysqli_stmt_execute($stmt_jogos)) {
        $result_jogos = mysqli_stmt_get_result($stmt_jogos);
        while ($row = mysqli_fetch_assoc($result_jogos)) {
            $jogos[] = $row;
        }
    } else {
        // Em produção, seria melhor logar o erro do que mostrá-lo
        error_log("Erro ao buscar jogos: " . mysqli_error($link));
        // Pode-se mostrar uma mensagem genérica para o utilizador
        // echo "Erro ao carregar os jogos.";
    }
    mysqli_stmt_close($stmt_jogos);
} else {
     error_log("Erro na preparação da consulta de jogos: " . mysqli_error($link));
     // echo "Erro ao preparar a consulta de jogos.";
}

// Calcular vitórias (contando jogos não 'Ativo')
$sql_vitorias = "SELECT COUNT(IDJogo) as total_vitorias FROM jogo WHERE IDUtilizador = ? AND Estado <> 'Ativo'";
if ($stmt_vitorias = mysqli_prepare($link, $sql_vitorias)) {
    mysqli_stmt_bind_param($stmt_vitorias, "i", $user_id);
    if (mysqli_stmt_execute($stmt_vitorias)) {
        $result_vitorias = mysqli_stmt_get_result($stmt_vitorias);
        if ($row_vitorias = mysqli_fetch_assoc($result_vitorias)) {
            $vitorias = $row_vitorias['total_vitorias'] ?? 0; // Usar ?? 0 para garantir que é um número
        }
    } else {
         error_log("Erro ao calcular vitórias: " . mysqli_error($link));
    }
    mysqli_stmt_close($stmt_vitorias);
} else {
     error_log("Erro na preparação da consulta de vitórias: " . mysqli_error($link));
}

mysqli_close($link); // Fechar conexão

// Função auxiliar para formatar data
function formatarData($dataSql) {
    if (!$dataSql) return 'Data inválida';
    try {
        $date = new DateTime($dataSql);
        return $date->format("d/m/Y"); // Formato DD/MM/AAAA
    } catch (Exception $e) {
        error_log("Erro ao formatar data: " . $e->getMessage());
        return 'Data inválida';
    }
}

// Função auxiliar para mapear estado
function mapearEstado($estadoSql) {
    switch ($estadoSql) {
        case 'Ativo': return 'A decorrer';
        case 'Pausado': return 'Pausado';
        case 'Desativo_som': case 'Desativo_energia': case 'Desativo_jogador': case 'Desativo_admin': return 'Terminado';
        default: return htmlspecialchars($estadoSql ?? 'Desconhecido');
    }
}

?>

<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Iscte Labs - Dashboard</title>
    <link rel="stylesheet" href="dashboard_style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        /* Estilos adicionais para botões de ação */
        .content-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 1px solid #eee;
        }
         .btn {
            background-color: #4a90e2;
            color: white;
            padding: 8px 12px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 0.9em;
            text-decoration: none; /* Para links estilizados como botões */
            display: inline-block; /* Para links */
            transition: background-color 0.3s ease;
            margin-left: 5px; /* Espaço entre botões */
        }
        .btn:hover { background-color: #357abd; }
        .btn-danger { background-color: #d9534f; }
        .btn-danger:hover { background-color: #c9302c; }
        .btn-warning { background-color: #f0ad4e; }
        .btn-warning:hover { background-color: #ec971f; }
        .btn-info { background-color: #5bc0de; }
        .btn-info:hover { background-color: #31b0d5; }
        .labirinto-actions {
             /* Ajustar layout dos botões */
            display: flex;
            align-items: center;
            margin-left: auto; /* Empurra para a direita */
            padding-left: 15px;
             flex-shrink: 0; /* Evita que os botões quebrem linha facilmente */
        }
         .labirinto-actions form {
             margin: 0 0 0 5px; /* Espaçamento entre botões de formulário */
         }
         .feedback-message {
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 4px;
            text-align: center;
        }
        .feedback-message.success { background-color: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .feedback-message.error { background-color: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
    </style>
</head>
<body>
    <div class="dashboard-container">
        <aside class="sidebar">
            <div class="user-profile">
                <!-- Adicionar um avatar default se não houver imagem -->
                <div class="user-avatar"><i class="fas fa-user-circle fa-3x"></i></div> 
                <div class="user-info">
                    <p><strong>Jogador</strong></p>
                    <p><?php echo htmlspecialchars($user_name); ?></p>
                    <p>Vitórias: <?php echo $vitorias; ?></p>
                </div>
            </div>
            <nav class="menu">
                <ul>
                    <li class="active"><a href="dashboard.php"> <i class="fas fa-th-list"></i> Labirintos</a></li>
                    <!-- Remover link Criar Utilizador daqui -->
                    <!-- <li><a href="criar_utilizador.php"><i class="fas fa-user-plus"></i> Criar Utilizador</a></li> -->
                </ul>
            </nav>
             <a href="logout.php" class="logout-button"><i class="fas fa-sign-out-alt"></i> Logout</a>
        </aside>
        <main class="content">
            <header class="content-header">
                 <h1>Meus Labirintos</h1>
                 <a href="criar_jogo_form.php" class="btn"><i class="fas fa-plus"></i> Criar Novo Jogo</a>
            </header>

             <?php 
                // Mostrar mensagens de feedback da sessão
                if (isset($_SESSION['message'])) {
                    echo '<div class="feedback-message success">' . htmlspecialchars($_SESSION['message']) . '</div>';
                    unset($_SESSION['message']);
                }
                if (isset($_SESSION['error'])) {
                    echo '<div class="feedback-message error">' . htmlspecialchars($_SESSION['error']) . '</div>';
                    unset($_SESSION['error']);
                }
            ?>

            <section class="labirintos-section">
                <ul class="labirintos-list">
                    <?php if (empty($jogos)): ?>
                        <li>
                            <p>Nenhum jogo registado para este utilizador.</p>
                        </li>
                    <?php else: ?>
                        <?php foreach ($jogos as $jogo): 
                            $is_active = ($jogo['Estado'] == 'Ativo');
                        ?>
                            <li class="labirinto-item <?php echo $is_active ? 'ativo' : 'terminado'; ?>">
                                <div class="labirinto-icon">
                                     <svg width="40" height="40" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                        <path d="M12 1C11.4477 1 11 1.44772 11 2V4H9C7.89543 4 7 4.89543 7 6V8H5C4.44772 5 4 5.44772 4 6V8H2C1.44772 8 1 8.44772 1 9V11H4V13H1V15C1 15.5523 1.44772 16 2 16H4V18H1V20C1 20.5523 1.44772 21 2 21H4V23C4 23.5523 4.44772 24 5 24H7C7.55228 24 8 23.5523 8 23V21H10V24H12C12.5523 24 13 23.5523 13 23V21H15V24H17C17.5523 24 18 23.5523 18 23V21H20V24H22C22.5523 24 23 23.5523 23 23V21H21V18H23V16C23 15.4477 22.5523 15 22 15H20V13H23V11C23 10.4477 22.5523 10 22 10H20V8H23V6C23 5.44772 22.5523 5 22 5H20V2C20 1.44772 19.5523 1 19 1H17V4H15V1H13C13 1.44772 12.5523 1 12 1ZM11 6V8H13V6H11ZM9 6H7V8H9V6ZM9 10H11V12H9V10ZM11 14H9V16H11V14ZM13 10V12H15V10H13ZM13 14V16H15V14H13ZM7 10V12H5V10H7ZM7 14V16H5V14H7ZM9 18V20H7V18H9ZM11 18V20H13V18H11ZM15 18V20H17V18H15ZM17 14H19V16H17V14ZM17 10H19V12H17V10ZM17 6H19V8H17V6Z" fill="#4a90e2"/>
                                    </svg>
                                </div>
                                <div class="labirinto-details">
                                    <p class="data"><strong><?php echo formatarData($jogo['DataHoraInicio']); ?></strong> <?php if ($is_active): ?><span class="status-dot"></span><?php endif; ?></p>
                                    <p>Jogo ID: <?php echo htmlspecialchars($jogo['IDJogo']); ?></p>
                                    <p>Status: <?php echo mapearEstado($jogo['Estado']); ?></p>
                                </div>
                                <div class="labirinto-pontos">
                                    <?php echo htmlspecialchars(number_format($jogo['Pontuacao'] ?? 0, 0)); ?>p
                                </div>
                                
                                <!-- Botões de Ação -->
                                <div class="labirinto-actions">
                                    <a href="ver_jogo_detalhes.php?id=<?php echo $jogo['IDJogo']; ?>" class="btn btn-info" title="Ver Detalhes"><i class="fas fa-eye"></i></a>
                                    <a href="alterar_descricao_form.php?id=<?php echo $jogo['IDJogo']; ?>" class="btn btn-warning" title="Alterar Descrição"><i class="fas fa-edit"></i></a>
                                    
                                    <?php // Botão Interromper / Resumir
                                        if ($jogo['Estado'] == 'Ativo') {
                                            echo '<form action="interromper_jogo_process.php" method="post" style="display: inline-block; margin: 0; padding: 0;" onsubmit="return confirm(\'Interromper este jogo?\');">
                                                    <input type="hidden" name="jogo_id" value="'. $jogo['IDJogo'] .'">
                                                    <button type="submit" class="btn btn-secondary" style="background-color:#6c757d;" title="Interromper Jogo"><i class="fas fa-pause"></i></button>
                                                  </form>';
                                        } elseif ($jogo['Estado'] == 'Pausado') {
                                            echo '<form action="resumir_jogo_process.php" method="post" style="display: inline-block; margin: 0; padding: 0;" onsubmit="return confirm(\'Resumir este jogo?\');">
                                                    <input type="hidden" name="jogo_id" value="'. $jogo['IDJogo'] .'">
                                                    <button type="submit" class="btn btn-success" style="background-color:#28a745;" title="Resumir Jogo"><i class="fas fa-play"></i></button>
                                                  </form>';
                                        }
                                    ?>
                                    
                                    <?php // Botão Terminar (só aparece se não estiver Pausado)
                                        if ($jogo['Estado'] != 'Pausado') { ?> 
                                            <form action="terminar_jogo_process.php" method="post" style="display: inline-block; margin: 0; padding: 0;" onsubmit="return confirm('Tem a certeza que quer terminar este jogo?');">
                                                <input type="hidden" name="jogo_id" value="<?php echo $jogo['IDJogo']; ?>">
                                                <button type="submit" class="btn btn-danger" title="Terminar Jogo"><i class="fas fa-stop-circle"></i></button>
                                            </form>
                                        <?php } ?> 
                                    
                                    <!-- Botão Remover -->
                                     <form action="remover_jogo_process.php" method="post" style="display: inline-block; margin: 0; padding: 0;" onsubmit="return confirm('ATENÇÃO: Remover este jogo é irreversível e pode falhar se houver dados associados. Continuar?');">
                                        <input type="hidden" name="jogo_id" value="<?php echo $jogo['IDJogo']; ?>">
                                        <button type="submit" class="btn btn-danger" title="Remover Jogo"><i class="fas fa-trash"></i></button>
                                    </form>
                                </div>
                            </li>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </ul>
            </section>
        </main>
    </div>
</body>
</html> 