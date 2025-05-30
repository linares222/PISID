<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Iscte Labs - Login</title>
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
</head>
<body>
    <header class="header-main">
        <div class="header-logo">
            <i class="fas fa-flask"></i> Iscte Labs
        </div>
    </header>
    <div class="login-container">
        <div class="login-info">
            <div class="login-info-logo">
                <svg width="100" height="100" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <path d="M12 1C11.4477 1 11 1.44772 11 2V4H9C7.89543 4 7 4.89543 7 6V8H5C4.44772 5 4 5.44772 4 6V8H2C1.44772 8 1 8.44772 1 9V11H4V13H1V15C1 15.5523 1.44772 16 2 16H4V18H1V20C1 20.5523 1.44772 21 2 21H4V23C4 23.5523 4.44772 24 5 24H7C7.55228 24 8 23.5523 8 23V21H10V24H12C12.5523 24 13 23.5523 13 23V21H15V24H17C17.5523 24 18 23.5523 18 23V21H20V24H22C22.5523 24 23 23.5523 23 23V21H21V18H23V16C23 15.4477 22.5523 15 22 15H20V13H23V11C23 10.4477 22.5523 10 22 10H20V8H23V6C23 5.44772 22.5523 5 22 5H20V2C20 1.44772 19.5523 1 19 1H17V4H15V1H13C13 1.44772 12.5523 1 12 1ZM11 6V8H13V6H11ZM9 6H7V8H9V6ZM9 10H11V12H9V10ZM11 14H9V16H11V14ZM13 10V12H15V10H13ZM13 14V16H15V14H13ZM7 10V12H5V10H7ZM7 14V16H5V14H7ZM9 18V20H7V18H9ZM11 18V20H13V18H11ZM15 18V20H17V18H15ZM17 14H19V16H17V14ZM17 10H19V12H17V10ZM17 6H19V8H17V6Z" fill="white"/>
                </svg>
            </div>
            <h1>Faça login na sua conta Iscte Labs</h1>
        </div>
        <div class="login-form">
            <h2>Log in</h2>
            <form action="login_process.php" method="post">
                <label for="email">Email</label>
                <input type="email" id="email" name="email" required>
                <button type="submit">Login</button>
            </form>
            <div class="login-options">
                <a href="criar_utilizador.php">Não tem conta? Crie uma aqui!</a>
            </div>
            <?php
                session_start();
                if (isset($_SESSION['login_error'])) {
                    echo '<p class="error-message">' . htmlspecialchars($_SESSION['login_error']) . '</p>';
                    unset($_SESSION['login_error']); // Clear the error message after displaying it
                }
            ?>
        </div>
    </div>
</body>
</html> 