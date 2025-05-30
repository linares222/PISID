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
$descricao_atual = '';
$error = '';

// Validar ID do Jogo
if (isset($_GET['id']) && is_numeric($_GET['id'])) {
    $jogo_id = (int)$_GET['id'];
} else {
    $error = "ID do Jogo inválido.";
}

// Buscar descrição atual se ID é válido
if ($jogo_id !== null) {
    $sql = "SELECT Descricao FROM jogo WHERE IDJogo = ? AND IDUtilizador = ?"; // Garantir que user é dono
    if ($stmt = mysqli_prepare($link, $sql)) {
        mysqli_stmt_bind_param($stmt, "ii", $jogo_id, $user_id);
        if (mysqli_stmt_execute($stmt)) {
            $result = mysqli_stmt_get_result($stmt);
            if ($row = mysqli_fetch_assoc($result)) {
                $descricao_atual = $row['Descricao'];
            } else {
                $error = "Jogo não encontrado ou não pertence a este utilizador.";
                $jogo_id = null; // Invalidar ID se não encontrado/pertence
            }
        } else {
            $error = "Erro ao buscar descrição atual.";
            $jogo_id = null;
        }
        mysqli_stmt_close($stmt);
    } else {
         $error = "Erro ao preparar consulta.";
         $jogo_id = null;
    }
}

mysqli_close($link);

?>
<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Iscte Labs - Alterar Descrição do Jogo</title>
    <link rel="stylesheet" href="dashboard_style.css"> 
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        /* Reutilizar estilos */
        .content { background: #f4f7f6; background-image: none; }
        .content::before { background: none; }
        .form-container { 
            background-color: white; 
            padding: 30px; 
            border-radius: 8px; 
            box-shadow: 0 2px 10px rgba(0,0,0,0.05); 
            max-width: 600px; 
            margin: 20px auto; 
            position: relative; /* Para garantir que fica acima do overlay base */
            z-index: 3; /* Acima do z-index: 1 do overlay */
        }
        .form-container h1 { margin-top: 0; margin-bottom: 25px; text-align: center; }
        .form-group label { display: block; margin-bottom: 8px; font-weight: bold; }
        .form-group textarea { width: calc(100% - 22px); padding: 10px; margin-bottom: 15px; border: 1px solid #ccc; border-radius: 4px; box-sizing: border-box; min-height: 100px; resize: vertical; }
        .btn { background-color: #f0ad4e; color: white; padding: 12px 20px; border: none; border-radius: 4px; cursor: pointer; font-size: 1.1em; transition: background-color 0.3s ease; width: 100%; margin-top: 10px; }
        .btn:hover { background-color: #ec971f; }
        .error-message { color: #d9534f; background-color: #f2dede; border: 1px solid #ebccd1; padding: 10px; border-radius: 4px; margin-top: 15px; text-align: center; }
    </style>
</head>
<body>
     <div class="dashboard-container">
        <!-- Sidebar -->
         <aside class="sidebar">
             <div class="user-profile"><div class="user-avatar"><i class="fas fa-user-circle fa-3x"></i></div><div class="user-info"><p><strong>Jogador</strong></p><p><?php echo htmlspecialchars($user_name); ?></p></div></div>
             <nav class="menu">
                 <ul>
                    <li><a href="dashboard.php"> <i class="fas fa-th-list"></i> Labirintos</a></li>
                    <li><a href="criar_utilizador.php"><i class="fas fa-user-plus"></i> Criar Utilizador</a></li>
                </ul>
             </nav>
             <a href="logout.php" class="logout-button"><i class="fas fa-sign-out-alt"></i> Logout</a>
        </aside>

        <!-- Conteúdo Principal -->
        <main class="content">
            <div class="form-container">
                <h1>Alterar Descrição do Jogo #<?php echo htmlspecialchars($jogo_id ?? ''); ?></h1>

                <?php if ($error): ?>
                    <p class="error-message"><?php echo htmlspecialchars($error); ?></p>
                    <p style="text-align:center;"><a href="dashboard.php">Voltar ao Dashboard</a></p>
                <?php elseif ($jogo_id !== null): ?>
                    <form action="alterar_descricao_process.php" method="post">
                        <input type="hidden" name="jogo_id" value="<?php echo htmlspecialchars($jogo_id); ?>">
                        <div class="form-group">
                            <label for="descricao">Nova Descrição:</label>
                            <textarea id="descricao" name="descricao" rows="4" required><?php echo htmlspecialchars($descricao_atual); ?></textarea>
                        </div>
                        <button type="submit" class="btn btn-warning">Guardar Alterações</button>
                        <a href="dashboard.php" style="display: block; text-align: center; margin-top: 15px;">Cancelar</a>
                    </form>
                <?php else: ?>
                     <p class="error-message">Ocorreu um erro inesperado.</p>
                     <p style="text-align:center;"><a href="dashboard.php">Voltar ao Dashboard</a></p>
                <?php endif; ?>
            </div>
        </main>
    </div>
</body>
</html> 