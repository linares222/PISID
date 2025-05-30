<?php
session_start();
require_once "db_config.php";

// Verificar login
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    header("location: index.php");
    exit;
}

$user_id = $_SESSION["id"];
$user_name = $_SESSION["nome"]; 

$jogo_id = null;
$jogo_details = null;
$mensagens_jogo = []; 
$sons_jogo = [];
$marsamis_jogo = [];
$passagens_jogo = [];
$ocupacao_salas = [];
$error = '';

// Validar ID do Jogo
if (isset($_GET['id']) && is_numeric($_GET['id'])) {
    $jogo_id = (int)$_GET['id'];
} else {
    $error = "ID do Jogo inválido ou não fornecido.";
}

if ($jogo_id !== null && empty($error)) {
    // --- Buscar Detalhes Base do Jogo ---
    $sql_jogo = "SELECT * FROM jogo WHERE IDJogo = ? AND IDUtilizador = ?";
    if ($stmt_jogo = mysqli_prepare($link, $sql_jogo)) {
        mysqli_stmt_bind_param($stmt_jogo, "ii", $jogo_id, $user_id);
        if (mysqli_stmt_execute($stmt_jogo)) {
            $result_jogo = mysqli_stmt_get_result($stmt_jogo);
            if ($row_jogo = mysqli_fetch_assoc($result_jogo)) {
                $jogo_details = $row_jogo;
            } else { $error = "Jogo não encontrado ou não pertence a este utilizador."; }
        } else { $error = "Erro ao buscar detalhes do jogo."; }
        mysqli_stmt_close($stmt_jogo);
    } else { $error = "Erro ao preparar consulta (jogo)."; }

    // --- Buscar Dados Adicionais (APENAS se o jogo foi encontrado) ---
    if ($jogo_details !== null) {
        // 1. Mensagens (já existente)
        $sql_msgs = "SELECT * FROM mensagens WHERE IDJogo = ? ORDER BY Hora DESC LIMIT 100";
         if ($stmt_msgs = mysqli_prepare($link, $sql_msgs)) {
            mysqli_stmt_bind_param($stmt_msgs, "i", $jogo_id);
            if (mysqli_stmt_execute($stmt_msgs)) {
                $result_msgs = mysqli_stmt_get_result($stmt_msgs);
                while ($row_msg = mysqli_fetch_assoc($result_msgs)) { $mensagens_jogo[] = $row_msg; }
            } else { $error .= " Erro ao buscar mensagens."; }
            mysqli_stmt_close($stmt_msgs);
        } else { $error .= " Erro ao preparar consulta (mensagens)."; }
        
        // 2. Sons
        $sql_sons = "SELECT Som, Hora FROM medicoessom WHERE IDJogo = ? ORDER BY Hora DESC LIMIT 10"; // Últimas 10 leituras
         if ($stmt_sons = mysqli_prepare($link, $sql_sons)) {
            mysqli_stmt_bind_param($stmt_sons, "i", $jogo_id);
            if (mysqli_stmt_execute($stmt_sons)) {
                $result_sons = mysqli_stmt_get_result($stmt_sons);
                while ($row_son = mysqli_fetch_assoc($result_sons)) { $sons_jogo[] = $row_son; }
            } else { $error .= " Erro ao buscar sons."; }
            mysqli_stmt_close($stmt_sons);
        } else { $error .= " Erro ao preparar consulta (sons)."; }
        
        // 3. Marsamis
        $sql_marsamis = "SELECT IDMarsami, Even, Energia, HoraUltimoMovimento FROM marsami WHERE IDJogo = ? ORDER BY IDMarsami ASC"; 
         if ($stmt_marsamis = mysqli_prepare($link, $sql_marsamis)) {
            mysqli_stmt_bind_param($stmt_marsamis, "i", $jogo_id);
            if (mysqli_stmt_execute($stmt_marsamis)) {
                $result_marsamis = mysqli_stmt_get_result($stmt_marsamis);
                while ($row_mars = mysqli_fetch_assoc($result_marsamis)) { $marsamis_jogo[] = $row_mars; }
            } else { $error .= " Erro ao buscar marsamis."; }
            mysqli_stmt_close($stmt_marsamis);
        } else { $error .= " Erro ao preparar consulta (marsamis)."; }

        // 4. Passagens
        $sql_passagens = "SELECT SalaOrigem, SalaDestino, Hora FROM medicoespassagens WHERE IDJogo = ? ORDER BY Hora DESC LIMIT 20"; // Últimos 20 movimentos
         if ($stmt_passagens = mysqli_prepare($link, $sql_passagens)) {
            mysqli_stmt_bind_param($stmt_passagens, "i", $jogo_id);
            if (mysqli_stmt_execute($stmt_passagens)) {
                $result_passagens = mysqli_stmt_get_result($stmt_passagens);
                while ($row_pass = mysqli_fetch_assoc($result_passagens)) { $passagens_jogo[] = $row_pass; }
            } else { $error .= " Erro ao buscar passagens."; }
            mysqli_stmt_close($stmt_passagens);
        } else { $error .= " Erro ao preparar consulta (passagens)."; }
        
        // 5. Ocupação das Salas
         $sql_ocupacao = "SELECT IDSala, NMarsamisOdd, NMarsamisEven, GatilhosAtivados, HoraUpdate FROM ocupacaolabirinto WHERE IDJogo = ? ORDER BY IDSala ASC"; 
         if ($stmt_ocupacao = mysqli_prepare($link, $sql_ocupacao)) {
            mysqli_stmt_bind_param($stmt_ocupacao, "i", $jogo_id);
            if (mysqli_stmt_execute($stmt_ocupacao)) {
                $result_ocupacao = mysqli_stmt_get_result($stmt_ocupacao);
                while ($row_oc = mysqli_fetch_assoc($result_ocupacao)) { $ocupacao_salas[] = $row_oc; }
            } else { $error .= " Erro ao buscar ocupação."; }
            mysqli_stmt_close($stmt_ocupacao);
        } else { $error .= " Erro ao preparar consulta (ocupação)."; }
    }
} 

mysqli_close($link);

// Funções auxiliares (redefinidas aqui, idealmente num ficheiro include)
function formatarDataHora($dataSql) {
    if (!$dataSql) return '-';
    try { return (new DateTime($dataSql))->format("d/m/Y H:i:s"); } catch (Exception $e) { return 'Inválida'; }
}
function mapearEstado($estadoSql) {
    switch ($estadoSql) {
        case 'Ativo': return 'A decorrer';
        case 'Pausado': return 'Pausado';
        case 'Desativo_som': case 'Desativo_energia': case 'Desativo_jogador': case 'Desativo_admin': return 'Terminado';
        default: return htmlspecialchars($estadoSql ?? 'Desconhecido');
    }
}
function formatarTipoMarsami($even) {
    return ($even == 1) ? 'Par' : 'Ímpar';
}
?>
<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Iscte Labs - Detalhes do Jogo</title>
    <link rel="stylesheet" href="dashboard_style.css"> 
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
     <style>
        /* Reutilizar estilos */
        .content { background: #f4f7f6; background-image: none; }
        .content::before { background: none; }
        .details-container, .table-container {
            background-color: white; padding: 25px; margin-bottom: 20px; border-radius: 8px; box-shadow: 0 2px 5px rgba(0,0,0,0.1);
            position: relative;
            z-index: 2;
        }
        .details-list { list-style: none; padding: 0; margin: 0; }
        .details-list li { margin-bottom: 10px; padding-bottom: 10px; border-bottom: 1px dashed #eee; }
        .details-list li:last-child { border-bottom: none; }
        .details-list strong { display: inline-block; width: 150px; color: #555; }
        table { width: 100%; border-collapse: collapse; margin-top: 15px; }
        th, td { padding: 10px 12px; text-align: left; border-bottom: 1px solid #ddd; font-size: 0.9em; }
        th { background-color: #f2f2f2; font-weight: bold; }
        td.wrap { white-space: normal; }
        .error-message { color: #d9534f; background-color: #f2dede; border: 1px solid #ebccd1; padding: 10px; border-radius: 4px; margin-bottom: 15px; text-align: center; }
        .back-link {
            display: inline-block; 
            margin-bottom: 15px; 
            text-decoration: none;
            position: relative;
            z-index: 2;
        }
        .grid-container {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); /* Layout responsivo */
            gap: 20px;
            margin-top: 20px;
        }
        .grid-item {
            background-color: white; 
            padding: 20px;
            border-radius: 8px; 
            box-shadow: 0 2px 5px rgba(0,0,0,0.05);
             position: relative; 
            z-index: 2; 
        }
        .grid-item h2 {
            margin-top: 0;
            margin-bottom: 15px;
            border-bottom: 1px solid #eee;
            padding-bottom: 10px;
            font-size: 1.2em;
        }
        .small-table th, .small-table td {
            font-size: 0.85em;
            padding: 8px 10px;
        }
    </style>
</head>
<body>
    <div class="dashboard-container">
        <!-- Sidebar -->
         <aside class="sidebar">
             <div class="user-profile"><div class="user-avatar"><i class="fas fa-user-circle fa-3x"></i></div><div class="user-info"><p><strong>Jogador</strong></p><p><?php echo htmlspecialchars($user_name); ?></p></div></div>
             <nav class="menu">
                 <ul>
                    <li class="active"><a href="dashboard.php"> <i class="fas fa-th-list"></i> Labirintos</a></li>
                    <li><a href="criar_utilizador.php"><i class="fas fa-user-plus"></i> Criar Utilizador</a></li>
                 </ul>
             </nav>
             <a href="logout.php" class="logout-button"><i class="fas fa-sign-out-alt"></i> Logout</a>
         </aside>

        <!-- Conteúdo Principal -->
        <main class="content">
            <h1>Detalhes do Jogo <?php echo ($jogo_id !== null) ? '#' . htmlspecialchars($jogo_id) : ''; ?></h1>
             <a href="dashboard.php" class="back-link">&laquo; Voltar ao Dashboard</a>

            <?php if ($error): ?>
                <p class="error-message"><?php echo htmlspecialchars($error); ?></p>
            <?php endif; ?>

            <?php if ($jogo_details): ?>
                <div class="details-container">
                    <h2>Informações do Jogo</h2>
                    <ul class="details-list">
                        <li><strong>ID Jogo:</strong> <?php echo htmlspecialchars($jogo_details['IDJogo']); ?></li>
                        <li><strong>Descrição:</strong> <?php echo nl2br(htmlspecialchars($jogo_details['Descricao'] ?? 'N/A')); ?></li>
                        <li><strong>Início:</strong> <?php echo formatarDataHora($jogo_details['DataHoraInicio']); ?></li>
                        <li><strong>Estado:</strong> <?php echo mapearEstado($jogo_details['Estado']); ?></li>
                        <li><strong>Pontuação:</strong> <?php echo htmlspecialchars(number_format($jogo_details['Pontuacao'] ?? 0, 2)); ?></li>
                        <li><strong>Última Atualização:</strong> <?php echo formatarDataHora($jogo_details['HoraUpdate']); ?></li>
                        <!-- <li><strong>ID Utilizador:</strong> <?php // echo htmlspecialchars($jogo_details['IDUtilizador']); // Não relevante mostrar ao próprio user ?></li> -->
                    </ul>
                </div>

                <!-- Grid para Dados Adicionais -->
                <div class="grid-container">
                
                    <!-- Marsamis -->
                    <div class="grid-item">
                        <h2>Marsamis no Jogo</h2>
                        <?php if (empty($marsamis_jogo)): ?>
                            <p>Nenhum marsami registado para este jogo.</p>
                        <?php else: ?>
                             <table class="small-table">
                                <thead><tr><th>ID</th><th>Tipo</th><th>Energia</th><th>Últ. Mov.</th></tr></thead>
                                <tbody>
                                    <?php foreach ($marsamis_jogo as $mars): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($mars['IDMarsami']); ?></td>
                                        <td><?php echo formatarTipoMarsami($mars['Even']); ?></td>
                                        <td><?php echo htmlspecialchars(number_format($mars['Energia'] ?? 0, 0)); ?></td>
                                        <td><?php echo formatarDataHora($mars['HoraUltimoMovimento']); ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        <?php endif; ?>
                    </div>

                    <!-- Ocupação das Salas -->
                    <div class="grid-item">
                         <h2>Ocupação das Salas</h2>
                         <?php if (empty($ocupacao_salas)): ?>
                            <p>Nenhuma informação de ocupação disponível.</p>
                        <?php else: ?>
                             <table class="small-table">
                                <thead><tr><th>Sala</th><th>Nº Ímpares</th><th>Nº Pares</th><th>Gatilhos</th><th>Hora Atualiz.</th></tr></thead>
                                <tbody>
                                    <?php foreach ($ocupacao_salas as $oc): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($oc['IDSala']); ?></td>
                                        <td><?php echo htmlspecialchars($oc['NMarsamisOdd'] ?? 0); ?></td>
                                        <td><?php echo htmlspecialchars($oc['NMarsamisEven'] ?? 0); ?></td>
                                         <td><?php echo htmlspecialchars($oc['GatilhosAtivados'] ?? 0); ?></td>
                                         <td><?php echo formatarDataHora($oc['HoraUpdate']); ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Últimas Leituras de Som -->
                    <div class="grid-item">
                        <h2>Últimas Leituras de Som (Máx 10)</h2>
                        <?php if (empty($sons_jogo)): ?>
                            <p>Nenhuma leitura de som registada.</p>
                        <?php else: ?>
                             <table class="small-table">
                                <thead><tr><th>Hora</th><th>Valor (dB)</th></tr></thead>
                                <tbody>
                                    <?php foreach ($sons_jogo as $som): ?>
                                    <tr>
                                        <td><?php echo formatarDataHora($som['Hora']); ?></td>
                                        <td><?php echo htmlspecialchars(number_format($som['Som'] ?? 0, 2)); ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        <?php endif; ?>
                    </div>

                    <!-- Últimas Passagens -->
                    <div class="grid-item">
                        <h2>Últimas Passagens (Máx 20)</h2>
                         <?php if (empty($passagens_jogo)): ?>
                            <p>Nenhuma passagem registada.</p>
                        <?php else: ?>
                             <table class="small-table">
                                <thead><tr><th>Hora</th><th>Origem</th><th>Destino</th></tr></thead>
                                <tbody>
                                    <?php foreach ($passagens_jogo as $pass): ?>
                                    <tr>
                                        <td><?php echo formatarDataHora($pass['Hora']); ?></td>
                                        <td><?php echo htmlspecialchars($pass['SalaOrigem'] ?? 'Entrada'); ?></td>
                                        <td><?php echo htmlspecialchars($pass['SalaDestino'] ?? 'N/A'); ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        <?php endif; ?>
                    </div>
                    
                </div> <!-- Fim Grid Container -->

                 <!-- Mensagens (Tabela Original - agora pode ficar abaixo do grid) -->
                <div class="table-container" style="margin-top: 30px;"> 
                    <h2>Últimas Mensagens do Jogo (Máx 100)</h2>
                     <?php if (empty($mensagens_jogo)): ?>
                        <p>Nenhuma mensagem encontrada para este jogo.</p>
                    <?php else: ?>
                         <table>
                            <thead><tr><th>ID</th><th>Hora</th><th>Sala</th><th>Sensor</th><th>Leitura</th><th>Tipo Alerta</th><th style="white-space:normal;">Mensagem</th><th>Hora Escrita</th></tr></thead>
                            <tbody>
                                <?php foreach ($mensagens_jogo as $msg): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($msg['IDMensagem']); ?></td>
                                        <td><?php echo formatarDataHora($msg['Hora']); ?></td>
                                        <td><?php echo htmlspecialchars($msg['Sala'] ?? '-'); ?></td>
                                        <td><?php echo htmlspecialchars($msg['Sensor'] ?? '-'); ?></td>
                                        <td><?php echo htmlspecialchars($msg['Leitura'] ?? '-'); ?></td>
                                        <td><?php echo htmlspecialchars($msg['TipoAlerta'] ?? '-'); ?></td>
                                        <td class="wrap"><?php echo htmlspecialchars($msg['Msg'] ?? '-'); ?></td>
                                        <td><?php echo formatarDataHora($msg['HoraEscrita']); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>
                
            <?php elseif (!$error && $jogo_id === null): ?>
                 <p class="error-message">ID de Jogo não fornecido.</p>
            <?php endif; ?>

        </main>
    </div>
</body>
</html> 