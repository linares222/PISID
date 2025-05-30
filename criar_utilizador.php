<?php
session_start();
// Opcional: Verificar se o user logado pode criar users (ex: se for admin)
// if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true /* || $_SESSION['tipo'] !== 'Administrador' */) {
//     // Apenas permitir se logado, ou redirecionar se for restrito
//     // header("location: index.php"); 
//     // exit;
// }

$user_name = $_SESSION["nome"] ?? 'Visitante'; // Para a sidebar se aplicável

// Mensagens de feedback
$message = '';
if (isset($_SESSION['message'])) {
    $message = $_SESSION['message'];
    unset($_SESSION['message']);
}
$error = '';
if (isset($_SESSION['error'])) {
    $error = $_SESSION['error'];
    unset($_SESSION['error']);
}
?>
<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Iscte Labs - Criar Novo Utilizador</title>
    <link rel="stylesheet" href="dashboard_style.css"> 
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        /* Estilos específicos */
        .content { background: #f4f7f6; background-image: none; }
        .content::before { background: none; }
        .form-container {
            background-color: white;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            max-width: 600px; /* Limitar largura do formulário */
            margin: 20px auto; /* Centrar */
            position: relative; /* Para garantir que fica acima do overlay base */
            z-index: 3; /* Acima do z-index: 1 do overlay */
        }
         .form-container h1 {
            margin-top: 0;
            margin-bottom: 25px;
            text-align: center;
        }
         .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: bold;
        }
        .form-group input[type="text"],
        .form-group input[type="email"],
        .form-group input[type="tel"],
        .form-group input[type="number"],
        .form-group select {
            width: calc(100% - 22px); /* padding + border */
            padding: 10px;
            margin-bottom: 15px;
            border: 1px solid #ccc;
            border-radius: 4px;
            box-sizing: border-box;
        }
         .btn {
            background-color: #4a90e2;
            color: white;
            padding: 12px 20px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 1.1em;
            transition: background-color 0.3s ease;
            width: 100%; /* Botão a toda a largura */
            margin-top: 10px;
        }
        .btn:hover { background-color: #357abd; }
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
        <!-- Sidebar Simplificada -->
         <aside class="sidebar" style="justify-content: center;"> 
             <!-- Remover user-profile -->
             <nav class="menu">
                 <ul>
                     <li><a href="index.php"><i class="fas fa-sign-in-alt"></i> Voltar ao Login</a></li>
                     <li class="active"><a href="criar_utilizador.php"><i class="fas fa-user-plus"></i> Criar Utilizador</a></li>
                 </ul>
             </nav>
             <!-- Remover logout button -->
         </aside>

        <!-- Conteúdo Principal -->
        <main class="content">
             <div class="form-container">
                <h1>Criar Novo Utilizador</h1>

                <?php if ($message): ?>
                    <div class="feedback-message success"><?php echo htmlspecialchars($message); ?></div>
                <?php endif; ?>
                <?php if ($error): ?>
                    <div class="feedback-message error"><?php echo htmlspecialchars($error); ?></div>
                <?php endif; ?>

                <form action="criar_utilizador_process.php" method="post">
                    <div class="form-group">
                        <label for="nome">Nome Completo:</label>
                        <input type="text" id="nome" name="nome" required>
                    </div>
                    <div class="form-group">
                        <label for="telemovel">Telemóvel:</label>
                        <input type="tel" id="telemovel" name="telemovel" pattern="[0-9]{9,12}" title="Número de telemóvel (9 a 12 dígitos)"> 
                    </div>
                     <div class="form-group">
                        <label for="email">Email:</label>
                        <input type="email" id="email" name="email" required>
                    </div>
                     <!-- Remover Campo Tipo -->
                    <!-- <div class="form-group">
                        <label for="tipo">Tipo de Utilizador:</label>
                        <select id="tipo" name="tipo" required>
                            <option value="Jogador">Jogador</option>
                            <option value="Investigador">Investigador</option>
                            <option value="Administrador">Administrador</option>
                        </select>
                    </div> -->
                     <!-- Remover Campo ID Grupo -->
                    <!-- <div class="form-group">
                        <label for="idgrupo">ID do Grupo:</label>
                        <input type="number" id="idgrupo" name="idgrupo" required>
                    </div> -->
                    
                    <!-- Campo Senha Omitido devido a problema no Procedimento SQL -->
                    <!-- <div class="form-group">
                        <label for="senha">Senha:</label>
                        <input type="password" id="senha" name="senha" required> 
                    </div> -->

                    <button type="submit" class="btn">Criar Utilizador</button>
                </form>
            </div>
        </main>
    </div>
</body>
</html> 