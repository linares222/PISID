<?php
session_start();
require_once "db_config.php"; // Para fechar conexão no fim, opcional aqui

// Verificar login
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    header("location: index.php");
    exit;
}

$user_name = $_SESSION["nome"]; 

?>
<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Iscte Labs - Criar Novo Jogo</title>
    <link rel="stylesheet" href="dashboard_style.css"> 
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        /* Reutilizar estilos de criar_utilizador.php */
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
        .form-group textarea {
            width: calc(100% - 22px); /* padding + border */
            padding: 10px;
            margin-bottom: 15px;
            border: 1px solid #ccc;
            border-radius: 4px;
            box-sizing: border-box;
            min-height: 100px;
            resize: vertical;
        }
         .btn { background-color: #4a90e2; color: white; padding: 12px 20px; border: none; border-radius: 4px; cursor: pointer; font-size: 1.1em; transition: background-color 0.3s ease; width: 100%; margin-top: 10px; }
        .btn:hover { background-color: #357abd; }
        /* Mensagens de erro/sucesso aqui se necessário */
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
                <h1>Criar Novo Jogo</h1>
                <form action="criar_jogo_process_v2.php" method="post">
                    <div class="form-group">
                        <label for="descricao">Descrição do Jogo:</label>
                        <textarea id="descricao" name="descricao" rows="4" required></textarea>
                    </div>
                    <button type="submit" class="btn">Criar Jogo</button>
                    <a href="dashboard.php" style="display: block; text-align: center; margin-top: 15px;">Cancelar</a>
                </form>
            </div>
        </main>
    </div>
</body>
</html> 