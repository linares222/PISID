<?php
// Iniciar a sessão
session_start();
 
// Desfazer todas as variáveis de sessão
$_SESSION = array();
 
// Destruir a sessão.
session_destroy();
 
// Redirecionar para a página de login
header("location: index.php");
exit;
?> 