<?php

define('DB_SERVER', 'localhost'); // Ou o teu host, ex: 127.0.0.1
define('DB_USERNAME', 'root');      // O teu username da BD (ex: root)
define('DB_PASSWORD', '');          // A tua password da BD
define('DB_NAME', 'pisid20245');    // O nome da base de dados

/* Tentativa de conexão à base de dados MySQL */
$link = mysqli_connect(DB_SERVER, DB_USERNAME, DB_PASSWORD, DB_NAME);

// Verificar conexão
if($link === false){
    // Não mostrar erros detalhados em produção
    // die("ERRO: Não foi possível conectar. " . mysqli_connect_error());
    die("ERRO: Não foi possível conectar à base de dados.");
}

// Opcional: Definir charset para utf8mb4 (recomendado)
mysqli_set_charset($link, "utf8mb4");

?> 