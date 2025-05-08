-- phpMyAdmin SQL Dump
-- version 5.2.0
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Tempo de geração: 30-Abr-2025 às 16:10
-- Versão do servidor: 10.4.27-MariaDB
-- versão do PHP: 8.2.0

-- SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO"; -- REMOVER ESTA LINHA
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Banco de dados: `pisid20245`
--

DELIMITER $$
--
-- Procedimentos
--
CREATE DEFINER=`root`@`localhost` PROCEDURE `AdicionarMarsamiSalaDestino` (IN `p_idmarsami` INT, IN `p_idsala` INT, IN `p_idjogo` INT)   BEGIN
    DECLARE v_even INT;

    -- Obtem se o marsami é even
    SELECT Even INTO v_even
    FROM marsami
    WHERE IDMarsami = p_idmarsami AND IDJogo = p_idjogo;

    -- Atualiza a ocupação da sala conforme o tipo (even/odd)
    IF v_even = 1 THEN
        UPDATE ocupacaolabirinto
        SET NMarsamisEven = NMarsamisEven + 1,
            HoraUpdate = NOW()
        WHERE IDSala = p_idsala AND IDJogo = p_idjogo;
    ELSE
        UPDATE ocupacaolabirinto
        SET NMarsamisOdd = NMarsamisOdd + 1,
            HoraUpdate = NOW()
        WHERE IDSala = p_idsala AND IDJogo = p_idjogo;
    END IF;
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `AtualizarOcupacaoLabirinto` (IN `pSalaOrigem` INT, IN `pSalaDestino` INT, IN `pEven` BOOLEAN, IN `pHora` DATETIME, IN `pIDJogo` INT)   BEGIN
    -- Atualizar ocupação da origem (remover)
    IF pSalaOrigem != pSalaDestino THEN
        IF pEven THEN
            UPDATE ocupacaolabirinto
            SET NMarsamisEven = GREATEST(0, NMarsamisEven - 1), HoraUpdate = pHora
            WHERE IDSala = pSalaOrigem AND IDJogo = pIDJogo;
        ELSE
            UPDATE ocupacaolabirinto
            SET NMarsamisOdd = GREATEST(0, NMarsamisOdd - 1), HoraUpdate = pHora
            WHERE IDSala = pSalaOrigem AND IDJogo = pIDJogo;
        END IF;
    END IF;

    -- Atualizar ocupação da destino (adicionar)
    IF pEven THEN
        INSERT INTO ocupacaolabirinto (IDSala, NMarsamisOdd, NMarsamisEven, GatilhosAtivados, HoraUpdate, IDJogo)
        VALUES (pSalaDestino, 0, 1, 0, pHora, pIDJogo)
        ON DUPLICATE KEY UPDATE 
            NMarsamisEven = NMarsamisEven + 1,
            HoraUpdate = pHora;
    ELSE
        INSERT INTO ocupacaolabirinto (IDSala, NMarsamisOdd, NMarsamisEven, GatilhosAtivados, HoraUpdate, IDJogo)
        VALUES (pSalaDestino, 1, 0, 0, pHora, pIDJogo)
        ON DUPLICATE KEY UPDATE 
            NMarsamisOdd = NMarsamisOdd + 1,
            HoraUpdate = pHora;
    END IF;
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `AtualizarOuInserirMarsami` (IN `p_nome` INT, IN `p_even` BOOLEAN, IN `p_hora` TIME, IN `p_idjogo` INT)   BEGIN
    DECLARE v_id INT;

    SELECT IDMarsami INTO v_id FROM marsami
    WHERE IDMarsami = p_nome AND IDJogo = p_idjogo
    LIMIT 1;

    IF v_id IS NOT NULL THEN
        UPDATE marsami
        SET HoraUltimoMovimento = p_hora
        WHERE IDMarsami = v_id AND IDJogo = p_idjogo;
    ELSE
        INSERT INTO marsami (IDMarsami, Even, Energia, HoraUltimoMovimento, HoraCriacao, IDJogo)
        VALUES (p_nome, p_even, 100, p_hora, p_hora, p_idjogo);
    END IF;
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `AtualizarOuInserirSala` (IN `p_idsala` INT, IN `p_tipo` VARCHAR(4), IN `p_idjogo` INT, IN `p_hora` TIME)   BEGIN
    DECLARE v_id INT;

    SELECT IDSala INTO v_id FROM ocupacaolabirinto 
    WHERE IDSala = p_idsala AND IDJogo = p_idjogo LIMIT 1;

    IF v_id IS NOT NULL THEN
        IF p_tipo = 'odd' THEN
            UPDATE ocupacaolabirinto
            SET NMarsamisOdd = NMarsamisOdd + 1, HoraUpdate = p_hora
            WHERE IDSala = p_idsala AND IDJogo = p_idjogo;
        ELSE
            UPDATE ocupacaolabirinto
            SET NMarsamisEven = NMarsamisEven + 1, HoraUpdate = p_hora
            WHERE IDSala = p_idsala AND IDJogo = p_idjogo;
        END IF;
    ELSE
        IF p_tipo = 'odd' THEN
            INSERT INTO ocupacaolabirinto (IDSala, NMarsamisOdd, NMarsamisEven, GatilhosAtivados, HoraUpdate, IDJogo)
            VALUES (p_idsala, 1, 0, 0, p_hora, p_idjogo);
        ELSE
            INSERT INTO ocupacaolabirinto (IDSala, NMarsamisOdd, NMarsamisEven, GatilhosAtivados, HoraUpdate, IDJogo)
            VALUES (p_idsala, 0, 1, 0, p_hora, p_idjogo);
        END IF;
    END IF;
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `Criar_Jogo` (IN `utilizador_id` INT, IN `descricao` TEXT)   BEGIN
    INSERT INTO jogo (Descricao, DataHoraInicio, Estado, Pontuacao, HoraUpdate, IDUtilizador)
    VALUES (descricao, NOW(), 'Ativo', 0, NOW(), utilizador_id);
    
    SELECT LAST_INSERT_ID() AS IDJogoCriado;
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `Criar_Utilizador` (IN `pNome` VARCHAR(100), IN `pTelemovel` VARCHAR(12), IN `pEmail` VARCHAR(50), IN `pSenha` VARCHAR(50), IN `pTipo` ENUM('Administrador','Investigador','Jogador'), IN `pIDGrupo` INT)   BEGIN
    INSERT INTO utilizador (Nome, Telemovel, Email, Estado, Tipo, IDGrupo)
    VALUES (pNome, pTelemovel, pEmail, 'Ativo', pTipo, pIDGrupo);
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `FecharJogoPorAdmin` (IN `p_IDUtilizador` INT)   BEGIN
    UPDATE jogo
    SET Estado = 'Desativo_admin',
        HoraUpdate = CURRENT_TIMESTAMP
    WHERE IDUtilizador = p_IDUtilizador AND Estado = 'Ativo';
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `FecharJogoPorInativo` (IN `p_IDJogo` INT)   BEGIN
    UPDATE jogo
    SET Estado = 'Desativo_energia',
        HoraUpdate = CURRENT_TIMESTAMP
    WHERE IDJogo = p_IDJogo AND Estado = 'Ativo';
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `FecharJogoPorJogador` (IN `p_IDUtilizador` INT)   BEGIN
    UPDATE jogo
    SET Estado = 'Desativo_jogador',
        HoraUpdate = CURRENT_TIMESTAMP
    WHERE IDUtilizador = p_IDUtilizador
      AND Estado = 'Ativo';
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `FecharJogoPorSom` (IN `p_IDJogo` INT)   BEGIN
    UPDATE jogo
    SET Estado = 'Desativo_som',
        HoraUpdate = CURRENT_TIMESTAMP
    WHERE IDJogo = p_IDJogo AND Estado = 'Ativo';
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `RemoverMarsamiSalaOrigem` (IN `p_idmarsami` INT, IN `p_idsala` INT, IN `p_idjogo` INT)   BEGIN
    DECLARE v_even INT DEFAULT NULL;

    IF EXISTS (
        SELECT 1 FROM ocupacaolabirinto 
        WHERE IDSala = p_idsala AND IDJogo = p_idjogo
    ) THEN

        SELECT Even INTO v_even
        FROM marsami
        WHERE IDMarsami = p_idmarsami AND IDJogo = p_idjogo
        LIMIT 1;

        IF v_even IS NOT NULL THEN
            IF v_even = 1 THEN
                UPDATE ocupacaolabirinto
                SET NMarsamisEven = GREATEST(NMarsamisEven - 1, 0),
                    HoraUpdate = CURRENT_TIMESTAMP
                WHERE IDSala = p_idsala AND IDJogo = p_idjogo;
            ELSE
                UPDATE ocupacaolabirinto
                SET NMarsamisOdd = GREATEST(NMarsamisOdd - 1, 0),
                    HoraUpdate = CURRENT_TIMESTAMP
                WHERE IDSala = p_idsala AND IDJogo = p_idjogo;
            END IF;
        END IF;
    END IF;
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `VerificarCriarNovoJogo` (IN `p_IDGrupo` INT, IN `p_IDMarsami` INT)   BEGIN
    DECLARE v_IDUtilizador INT;
    DECLARE v_IDJogoAtual INT;
    DECLARE v_HoraInicio DATETIME;
    DECLARE v_ExisteMarsami INT DEFAULT 0;

    -- Obter IDUtilizador a partir do IDGrupo
    SELECT IDUtilizador INTO v_IDUtilizador
    FROM utilizador
    WHERE IDGrupo = p_IDGrupo
    LIMIT 1;

    -- Se encontrar utilizador
    IF v_IDUtilizador IS NOT NULL THEN

        -- Obter último jogo ativo
        SELECT IDJogo, DataHoraInicio
        INTO v_IDJogoAtual, v_HoraInicio
        FROM jogo
        WHERE Estado = 'Ativo' AND IDUtilizador = v_IDUtilizador
        ORDER BY DataHoraInicio DESC
        LIMIT 1;

        -- Se não houver jogo ativo, cria novo
        IF v_IDJogoAtual IS NULL THEN
            INSERT INTO jogo (Descricao, DataHoraInicio, Estado, Pontuacao, HoraUpdate, IDUtilizador)
            VALUES (CONCAT('Jogo iniciado automaticamente em ', NOW()), NOW(), 'Ativo', 0, NOW(), v_IDUtilizador);
        ELSE
            -- Verificar se já existe marsami no jogo
            SELECT COUNT(*) INTO v_ExisteMarsami
            FROM marsami
            WHERE IDMarsami = p_IDMarsami AND IDJogo = v_IDJogoAtual;

            -- Se já existe marsami OU o jogo foi iniciado há mais de 120 segundos -> fechar e criar novo
            IF v_ExisteMarsami > 0 OR TIMESTAMPDIFF(SECOND, v_HoraInicio, NOW()) > 120 THEN
                -- Fechar jogo antigo
                UPDATE jogo
                SET Estado = 'Desativo_jogador', HoraUpdate = NOW()
                WHERE IDJogo = v_IDJogoAtual;

                -- Criar novo jogo
                INSERT INTO jogo (Descricao, DataHoraInicio, Estado, Pontuacao, HoraUpdate, IDUtilizador)
                VALUES (CONCAT('Novo jogo iniciado automaticamente em ', NOW()), NOW(), 'Ativo', 0, NOW(), v_IDUtilizador);
            END IF;
        END IF;
    END IF;
END$$

CREATE PROCEDURE `Alterar_Descricao_Jogo` (IN `p_IDJogo` INT, IN `p_NovaDescricao` TEXT)
BEGIN
    UPDATE jogo
    SET Descricao = p_NovaDescricao,
        HoraUpdate = NOW()
    WHERE IDJogo = p_IDJogo;
END$$

CREATE PROCEDURE `Remover_Jogo` (IN `p_IDJogo` INT)
BEGIN
    -- Atenção: Isto pode falhar devido a restrições de chave estrangeira.
    -- Dados relacionados em tabelas como marsami, medicoes*, mensagens, ocupacaolabirinto
    -- podem precisar ser removidos primeiro ou usar ON DELETE CASCADE nas constraints.
    DELETE FROM jogo
    WHERE IDJogo = p_IDJogo;
END$$

-- Procedimento para Interromper Jogo
DELIMITER $$
CREATE PROCEDURE `Interromper_Jogo` (IN `p_IDJogo` INT, IN `p_IDUtilizador` INT)
BEGIN
    UPDATE jogo
    SET Estado = 'Pausado',
        HoraUpdate = NOW()
    WHERE IDJogo = p_IDJogo AND IDUtilizador = p_IDUtilizador AND Estado = 'Ativo';
END$$
DELIMITER ;

-- Procedimento para Resumir Jogo
DELIMITER $$
CREATE PROCEDURE `Resumir_Jogo` (IN `p_IDJogo` INT, IN `p_IDUtilizador` INT)
BEGIN
    UPDATE jogo
    SET Estado = 'Ativo',
        HoraUpdate = NOW()
    WHERE IDJogo = p_IDJogo AND IDUtilizador = p_IDUtilizador AND Estado = 'Pausado';
END$$
DELIMITER ;

-- Fim dos Novos Procedimentos --

COMMIT;

DELIMITER ;

-- --------------------------------------------------------

--
-- Estrutura da tabela `jogo`
--

CREATE TABLE `jogo` (
  `IDJogo` int(11) NOT NULL,
  `Descricao` text DEFAULT NULL,
  `DataHoraInicio` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `Estado` enum('Ativo','Pausado','Desativo_som','Desativo_energia','Desativo_jogador','Desativo_admin') DEFAULT NULL,
  `Pontuacao` decimal(10,2) DEFAULT NULL,
  `HoraUpdate` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `IDUtilizador` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Extraindo dados da tabela `jogo`
--

INSERT INTO `jogo` (`IDJogo`, `Descricao`, `DataHoraInicio`, `Estado`, `Pontuacao`, `HoraUpdate`, `IDUtilizador`) VALUES
(111, 'Jogo iniciado automaticamente em 2025-04-30 14:56:43', '2025-04-30 13:56:45', 'Desativo_jogador', '0.00', '2025-04-30 13:56:45', 1),
(112, 'Novo jogo iniciado automaticamente em 2025-04-30 14:56:45', '2025-04-30 13:56:46', 'Desativo_jogador', '0.00', '2025-04-30 13:56:46', 1),
(113, 'Novo jogo iniciado automaticamente em 2025-04-30 14:56:46', '2025-04-30 13:56:49', 'Desativo_jogador', '0.00', '2025-04-30 13:56:49', 1),
(114, 'Novo jogo iniciado automaticamente em 2025-04-30 14:56:49', '2025-04-30 13:56:51', 'Desativo_jogador', '0.00', '2025-04-30 13:56:51', 1),
(115, 'Novo jogo iniciado automaticamente em 2025-04-30 14:56:51', '2025-04-30 13:56:52', 'Desativo_jogador', '0.00', '2025-04-30 13:56:52', 1),
(116, 'Novo jogo iniciado automaticamente em 2025-04-30 14:56:52', '2025-04-30 13:56:53', 'Desativo_jogador', '0.00', '2025-04-30 13:56:53', 1),
(117, 'Novo jogo iniciado automaticamente em 2025-04-30 14:56:53', '2025-04-30 13:56:55', 'Desativo_jogador', '0.00', '2025-04-30 13:56:55', 1),
(118, 'Novo jogo iniciado automaticamente em 2025-04-30 14:56:55', '2025-04-30 14:04:15', 'Desativo_jogador', '0.00', '2025-04-30 14:04:15', 1),
(119, 'Novo jogo iniciado automaticamente em 2025-04-30 15:04:15', '2025-04-30 14:04:17', 'Desativo_jogador', '0.00', '2025-04-30 14:04:17', 1),
(120, 'Novo jogo iniciado automaticamente em 2025-04-30 15:04:17', '2025-04-30 14:04:19', 'Desativo_jogador', '0.00', '2025-04-30 14:04:19', 1),
(121, 'Novo jogo iniciado automaticamente em 2025-04-30 15:04:19', '2025-04-30 14:04:21', 'Desativo_jogador', '0.00', '2025-04-30 14:04:21', 1),
(122, 'Novo jogo iniciado automaticamente em 2025-04-30 15:04:21', '2025-04-30 14:04:22', 'Desativo_jogador', '0.00', '2025-04-30 14:04:22', 1),
(123, 'Novo jogo iniciado automaticamente em 2025-04-30 15:04:22', '2025-04-30 14:04:23', 'Desativo_jogador', '0.00', '2025-04-30 14:04:23', 1),
(124, 'Novo jogo iniciado automaticamente em 2025-04-30 15:04:23', '2025-04-30 14:04:24', 'Desativo_jogador', '0.00', '2025-04-30 14:04:24', 1),
(125, 'Novo jogo iniciado automaticamente em 2025-04-30 15:04:24', '2025-04-30 14:04:24', 'Ativo', '0.00', '2025-04-30 14:04:24', 1);

-- --------------------------------------------------------

--
-- Estrutura da tabela `marsami`
--

CREATE TABLE `marsami` (
  `IDMarsami` int(11) NOT NULL,
  `Even` tinyint(1) DEFAULT NULL,
  `Energia` decimal(10,0) DEFAULT NULL,
  `HoraUltimoMovimento` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `HoraCriacao` timestamp NULL DEFAULT NULL,
  `IDJogo` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Extraindo dados da tabela `marsami`
--

INSERT INTO `marsami` (`IDMarsami`, `Even`, `Energia`, `HoraUltimoMovimento`, `HoraCriacao`, `IDJogo`) VALUES
(1, 0, '100', '2025-04-30 14:04:14', '2025-04-30 13:57:40', 118),
(2, 1, '100', '2025-04-30 13:56:42', '2025-04-30 13:56:42', 111),
(3, 0, '100', '2025-04-30 13:56:43', '2025-04-30 13:56:43', 112),
(4, 1, '100', '2025-04-30 13:57:32', '2025-04-30 13:57:30', 118),
(5, 0, '100', '2025-04-30 13:57:29', '2025-04-30 13:57:06', 118),
(6, 1, '100', '2025-04-30 13:57:39', '2025-04-30 13:57:13', 118),
(7, 0, '100', '2025-04-30 13:57:38', '2025-04-30 13:57:38', 118),
(8, 1, '100', '2025-04-30 13:57:40', '2025-04-30 13:57:11', 118),
(9, 0, '100', '2025-04-30 14:04:16', '2025-04-30 14:04:16', 119),
(11, 0, '100', '2025-04-30 13:56:48', '2025-04-30 13:56:48', 113),
(11, 0, '100', '2025-04-30 13:57:08', '2025-04-30 13:57:05', 118),
(12, 1, '100', '2025-04-30 13:57:48', '2025-04-30 13:57:35', 118),
(13, 0, '100', '2025-04-30 13:57:51', '2025-04-30 13:57:51', 118),
(14, 1, '100', '2025-04-30 13:57:25', '2025-04-30 13:57:25', 118),
(14, 1, '100', '2025-04-30 14:04:17', '2025-04-30 14:04:17', 120),
(17, 0, '100', '2025-04-30 13:57:53', '2025-04-30 13:57:37', 118),
(18, 1, '100', '2025-04-30 13:57:26', '2025-04-30 13:57:15', 118),
(19, 0, '100', '2025-04-30 14:04:19', '2025-04-30 14:04:19', 121),
(20, 1, '100', '2025-04-30 13:57:44', '2025-04-30 13:57:19', 118),
(20, 1, '100', '2025-04-30 14:04:20', '2025-04-30 14:04:20', 122),
(21, 0, '100', '2025-04-30 13:57:45', '2025-04-30 13:57:08', 118),
(22, 1, '100', '2025-04-30 13:57:44', '2025-04-30 13:57:33', 118),
(23, 0, '100', '2025-04-30 13:56:48', '2025-04-30 13:56:48', 114),
(24, 1, '100', '2025-04-30 13:56:49', '2025-04-30 13:56:49', 115),
(24, 1, '100', '2025-04-30 13:57:47', '2025-04-30 13:57:12', 118),
(24, 1, '100', '2025-04-30 14:04:21', '2025-04-30 14:04:21', 123),
(25, 0, '100', '2025-04-30 13:57:36', '2025-04-30 13:57:09', 118),
(25, 0, '100', '2025-04-30 14:04:22', '2025-04-30 14:04:22', 124),
(26, 1, '100', '2025-04-30 13:56:50', '2025-04-30 13:56:50', 116),
(26, 1, '100', '2025-04-30 13:57:50', '2025-04-30 13:57:22', 118),
(27, 0, '100', '2025-04-30 13:56:53', '2025-04-30 13:56:53', 117),
(28, 1, '100', '2025-04-30 13:57:42', '2025-04-30 13:57:23', 118);

-- --------------------------------------------------------

--
-- Estrutura da tabela `medicoespassagens`
--

CREATE TABLE `medicoespassagens` (
  `IDMedicao` int(11) NOT NULL,
  `SalaOrigem` int(11) DEFAULT NULL,
  `SalaDestino` int(11) DEFAULT NULL,
  `Estado` int(11) DEFAULT NULL,
  `Hora` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `IDJogo` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Extraindo dados da tabela `medicoespassagens`
--

INSERT INTO `medicoespassagens` (`IDMedicao`, `SalaOrigem`, `SalaDestino`, `Estado`, `Hora`, `IDJogo`) VALUES
(1030, 0, 7, 1, '2025-04-30 13:56:42', 111),
(1031, 0, 5, 1, '2025-04-30 13:56:43', 112),
(1032, 0, 9, 1, '2025-04-30 13:56:48', 113),
(1033, 0, 10, 1, '2025-04-30 13:56:48', 114),
(1034, 0, 1, 1, '2025-04-30 13:56:49', 115),
(1035, 0, 2, 1, '2025-04-30 13:56:50', 116),
(1036, 0, 1, 1, '2025-04-30 13:56:53', 117),
(1037, 9, 7, 1, '2025-04-30 13:57:05', 118),
(1038, 8, 10, 1, '2025-04-30 13:57:06', 118),
(1039, 7, 5, 1, '2025-04-30 13:57:08', 118),
(1040, 7, 5, 1, '2025-04-30 13:57:08', 118),
(1041, 7, 5, 1, '2025-04-30 13:57:09', 118),
(1042, 7, 5, 1, '2025-04-30 13:57:11', 118),
(1043, 1, 2, 1, '2025-04-30 13:57:12', 118),
(1044, 10, 1, 1, '2025-04-30 13:57:13', 118),
(1045, 8, 10, 1, '2025-04-30 13:57:15', 118),
(1046, 6, 8, 1, '2025-04-30 13:57:19', 118),
(1047, 10, 1, 1, '2025-04-30 13:57:20', 118),
(1048, 5, 7, 1, '2025-04-30 13:57:22', 118),
(1049, 5, 7, 1, '2025-04-30 13:57:23', 118),
(1050, 10, 1, 1, '2025-04-30 13:57:23', 118),
(1051, 1, 2, 1, '2025-04-30 13:57:25', 118),
(1052, 1, 3, 1, '2025-04-30 13:57:26', 118),
(1053, 1, 3, 1, '2025-04-30 13:57:26', 118),
(1054, 1, 3, 1, '2025-04-30 13:57:28', 118),
(1055, 10, 1, 1, '2025-04-30 13:57:29', 118),
(1056, 4, 5, 1, '2025-04-30 13:57:30', 118),
(1057, 5, 7, 1, '2025-04-30 13:57:31', 118),
(1058, 5, 7, 1, '2025-04-30 13:57:32', 118),
(1059, 2, 4, 1, '2025-04-30 13:57:32', 118),
(1060, 5, 3, 1, '2025-04-30 13:57:33', 118),
(1061, 4, 5, 1, '2025-04-30 13:57:34', 118),
(1062, 3, 2, 1, '2025-04-30 13:57:35', 118),
(1063, 7, 5, 1, '2025-04-30 13:57:36', 118),
(1064, 3, 2, 1, '2025-04-30 13:57:37', 118),
(1065, 8, 9, 1, '2025-04-30 13:57:38', 118),
(1066, 3, 2, 1, '2025-04-30 13:57:39', 118),
(1067, 1, 2, 1, '2025-04-30 13:57:40', 118),
(1068, 5, 7, 1, '2025-04-30 13:57:40', 118),
(1069, 7, 5, 1, '2025-04-30 13:57:42', 118),
(1070, 2, 5, 1, '2025-04-30 13:57:43', 118),
(1071, 3, 2, 1, '2025-04-30 13:57:44', 118),
(1072, 10, 1, 1, '2025-04-30 13:57:44', 118),
(1073, 7, 5, 1, '2025-04-30 13:57:45', 118),
(1074, 5, 7, 1, '2025-04-30 13:57:47', 118),
(1075, 2, 5, 1, '2025-04-30 13:57:48', 118),
(1076, 7, 5, 1, '2025-04-30 13:57:50', 118),
(1077, 5, 7, 1, '2025-04-30 13:57:50', 118),
(1078, 2, 5, 1, '2025-04-30 13:57:51', 118),
(1079, 5, 7, 1, '2025-04-30 13:57:53', 118),
(1080, 0, 1, 1, '2025-04-30 14:04:14', 118),
(1081, 0, 3, 1, '2025-04-30 14:04:16', 119),
(1082, 0, 6, 1, '2025-04-30 14:04:17', 120),
(1083, 0, 7, 1, '2025-04-30 14:04:19', 121),
(1084, 0, 10, 1, '2025-04-30 14:04:20', 122),
(1085, 0, 7, 1, '2025-04-30 14:04:21', 123),
(1086, 0, 2, 1, '2025-04-30 14:04:22', 124);

-- --------------------------------------------------------

--
-- Estrutura da tabela `medicoessom`
--

CREATE TABLE `medicoessom` (
  `IDSom` int(11) NOT NULL,
  `Som` decimal(6,2) DEFAULT NULL,
  `Hora` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `IDJogo` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Extraindo dados da tabela `medicoessom`
--

INSERT INTO `medicoessom` (`IDSom`, `Som`, `Hora`, `IDJogo`) VALUES
(624, '19.04', '2025-04-30 13:57:06', 118),
(625, '19.08', '2025-04-30 13:57:08', 118),
(626, '19.12', '2025-04-30 13:57:09', 118),
(627, '19.12', '2025-04-30 13:57:10', 118),
(628, '19.16', '2025-04-30 13:57:11', 118),
(629, '19.18', '2025-04-30 13:57:12', 118),
(630, '19.11', '2025-04-30 13:57:13', 118),
(631, '19.24', '2025-04-30 13:57:14', 118),
(632, '19.32', '2025-04-30 13:57:17', 118),
(633, '19.40', '2025-04-30 13:57:20', 118),
(634, '19.42', '2025-04-30 13:57:21', 118),
(635, '19.46', '2025-04-30 13:57:22', 118),
(636, '19.48', '2025-04-30 13:57:23', 118),
(637, '19.63', '2025-04-30 13:57:24', 118),
(638, '19.54', '2025-04-30 13:57:25', 118),
(639, '19.52', '2025-04-30 13:57:27', 118),
(640, '19.54', '2025-04-30 13:57:28', 118),
(641, '19.52', '2025-04-30 13:57:29', 118),
(642, '19.54', '2025-04-30 13:57:30', 118),
(643, '19.58', '2025-04-30 13:57:31', 118),
(644, '19.65', '2025-04-30 13:57:32', 118),
(645, '19.64', '2025-04-30 13:57:34', 118),
(646, '19.62', '2025-04-30 13:57:36', 118),
(647, '19.64', '2025-04-30 13:57:37', 118),
(648, '19.77', '2025-04-30 13:57:38', 118),
(649, '19.64', '2025-04-30 13:57:39', 118),
(650, '19.57', '2025-04-30 13:57:41', 118),
(651, '19.70', '2025-04-30 13:57:42', 118),
(652, '19.72', '2025-04-30 13:57:43', 118),
(653, '19.68', '2025-04-30 13:57:44', 118),
(654, '19.74', '2025-04-30 13:57:45', 118),
(655, '19.80', '2025-04-30 13:57:46', 118),
(656, '19.74', '2025-04-30 13:57:47', 118),
(657, '19.76', '2025-04-30 13:57:48', 118),
(658, '19.80', '2025-04-30 13:57:49', 118),
(659, '19.76', '2025-04-30 13:57:51', 118),
(660, '19.74', '2025-04-30 13:57:52', 118),
(661, '19.70', '2025-04-30 13:57:53', 118);

--
-- Acionadores `medicoessom`
--
DELIMITER $$
CREATE TRIGGER `TRG_SomCritico` AFTER INSERT ON `medicoessom` FOR EACH ROW BEGIN
    -- Se o som for maior que 21, fecha o jogo
    IF NEW.Som > 21 THEN
        CALL FecharJogoPorSom(NEW.IDJogo);
    END IF;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Estrutura da tabela `mensagens`
--

CREATE TABLE `mensagens` (
  `IDMensagem` int(11) NOT NULL,
  `Hora` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `Sala` int(11) DEFAULT NULL,
  `Sensor` int(11) DEFAULT NULL,
  `Leitura` decimal(6,2) DEFAULT NULL,
  `TipoAlerta` varchar(50) DEFAULT NULL,
  `Msg` varchar(100) DEFAULT NULL,
  `HoraEscrita` timestamp NOT NULL DEFAULT current_timestamp(),
  `IDJogo` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Acionadores `mensagens`
--
DELIMITER $$
CREATE TRIGGER `TRG_AlertaInatividade` AFTER INSERT ON `mensagens` FOR EACH ROW BEGIN
    -- Se o tipo de alerta for 'ALERTA_INATIVIDADE' ou 'ALERTA_MARSAMI_PARADO'
    IF NEW.TipoAlerta IN ('ALERTA_INATIVIDADE', 'ALERTA_MARSAMI_PARADO') THEN
        CALL FecharJogoPorInativo(NEW.IDJogo);
    END IF;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Estrutura da tabela `ocupacaolabirinto`
--

CREATE TABLE `ocupacaolabirinto` (
  `IDSala` int(11) NOT NULL,
  `NMarsamisOdd` int(11) DEFAULT NULL,
  `NMarsamisEven` int(11) DEFAULT NULL,
  `GatilhosAtivados` int(11) DEFAULT NULL,
  `HoraUpdate` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `IDJogo` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Extraindo dados da tabela `ocupacaolabirinto`
--

INSERT INTO `ocupacaolabirinto` (`IDSala`, `NMarsamisOdd`, `NMarsamisEven`, `GatilhosAtivados`, `HoraUpdate`, `IDJogo`) VALUES
(1, 0, 1, 0, '2025-04-30 13:56:49', 115),
(1, 1, 0, 0, '2025-04-30 13:56:53', 117),
(1, 1, 1, 0, '2025-04-30 14:04:14', 118),
(2, 0, 1, 0, '2025-04-30 13:56:50', 116),
(2, 0, 3, 0, '2025-04-30 13:57:51', 118),
(2, 1, 0, 0, '2025-04-30 14:04:22', 124),
(3, 0, 1, 0, '2025-04-30 13:57:44', 118),
(3, 1, 0, 0, '2025-04-30 14:04:16', 119),
(4, 0, 0, 0, '2025-04-30 13:57:34', 118),
(5, 1, 0, 0, '2025-04-30 13:56:43', 112),
(5, 5, 1, 0, '2025-04-30 13:57:53', 118),
(6, 0, 1, 0, '2025-04-30 14:04:17', 120),
(7, 0, 1, 0, '2025-04-30 13:56:42', 111),
(7, 1, 4, 0, '2025-04-30 13:57:53', 118),
(7, 1, 0, 0, '2025-04-30 14:04:19', 121),
(7, 0, 1, 0, '2025-04-30 14:04:21', 123),
(8, 0, 1, 0, '2025-04-30 13:57:38', 118),
(9, 1, 0, 0, '2025-04-30 13:56:48', 113),
(9, 1, 0, 0, '2025-04-30 13:57:38', 118),
(10, 1, 0, 0, '2025-04-30 13:56:48', 114),
(10, 0, 0, 0, '2025-04-30 13:57:44', 118),
(10, 0, 1, 0, '2025-04-30 14:04:20', 122);

-- --------------------------------------------------------

--
-- Estrutura da tabela `utilizador`
--

CREATE TABLE `utilizador` (
  `IDUtilizador` int(11) NOT NULL,
  `Nome` varchar(100) DEFAULT NULL,
  `Telemovel` varchar(12) DEFAULT NULL,
  `Tipo` enum('Administrador','Investigador','Jogador') DEFAULT NULL,
  `Email` varchar(50) DEFAULT NULL,
  `Estado` enum('Ativo','Desativo') DEFAULT NULL,
  `IDGrupo` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Extraindo dados da tabela `utilizador`
--

INSERT INTO `utilizador` (`IDUtilizador`, `Nome`, `Telemovel`, `Tipo`, `Email`, `Estado`, `IDGrupo`) VALUES
(1, 'João Teste', '912345678', 'Jogador', 'joao@teste.com', 'Ativo', 30);

--
-- Índices para tabelas despejadas
--

--
-- Índices para tabela `jogo`
--
ALTER TABLE `jogo`
  ADD PRIMARY KEY (`IDJogo`),
  ADD KEY `IDUtilizador` (`IDUtilizador`);

-- Modificar Enum para incluir 'Pausado'
ALTER TABLE `jogo` 
MODIFY COLUMN `Estado` enum('Ativo','Pausado','Desativo_som','Desativo_energia','Desativo_jogador','Desativo_admin') DEFAULT NULL;

--
-- Índices para tabela `marsami`
--
ALTER TABLE `marsami`
  ADD PRIMARY KEY (`IDMarsami`,`IDJogo`),
  ADD KEY `IDJogo` (`IDJogo`);

--
-- Índices para tabela `medicoespassagens`
--
ALTER TABLE `medicoespassagens`
  ADD PRIMARY KEY (`IDMedicao`),
  ADD KEY `IDJogo` (`IDJogo`);

--
-- Índices para tabela `medicoessom`
--
ALTER TABLE `medicoessom`
  ADD PRIMARY KEY (`IDSom`),
  ADD KEY `IDJogo` (`IDJogo`);

--
-- Índices para tabela `mensagens`
--
ALTER TABLE `mensagens`
  ADD PRIMARY KEY (`IDMensagem`),
  ADD KEY `IDJogo` (`IDJogo`);

--
-- Índices para tabela `ocupacaolabirinto`
--
ALTER TABLE `ocupacaolabirinto`
  ADD PRIMARY KEY (`IDSala`,`IDJogo`),
  ADD KEY `IDJogo` (`IDJogo`);

--
-- Índices para tabela `utilizador`
--
ALTER TABLE `utilizador`
  ADD PRIMARY KEY (`IDUtilizador`);

-- Tentar remover a constraint se ela já existir (ignorar erro se não existir)
-- O nome da constraint pode variar, mas 'IDGrupo' é comum para unique keys criadas automaticamente ou explicitamente
SET @exist := (SELECT COUNT(*) FROM information_schema.statistics WHERE table_schema = DATABASE() AND table_name = 'utilizador' AND index_name = 'IDGrupo');
SET @sql := IF(@exist > 0, 'ALTER TABLE `utilizador` DROP INDEX `IDGrupo`', 'SELECT \'Index IDGrupo already removed or does not exist.\'');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

--
-- AUTO_INCREMENT de tabelas despejadas
--

--
-- AUTO_INCREMENT de tabela `jogo`
--
ALTER TABLE `jogo`
  MODIFY `IDJogo` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=126;

--
-- AUTO_INCREMENT de tabela `medicoespassagens`
--
ALTER TABLE `medicoespassagens`
  MODIFY `IDMedicao` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=1087;

--
-- AUTO_INCREMENT de tabela `medicoessom`
--
ALTER TABLE `medicoessom`
  MODIFY `IDSom` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=662;

--
-- AUTO_INCREMENT de tabela `mensagens`
--
ALTER TABLE `mensagens`
  MODIFY `IDMensagem` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT de tabela `utilizador`
--
ALTER TABLE `utilizador`
  MODIFY `IDUtilizador` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- Restrições para despejos de tabelas
--

--
-- Limitadores para a tabela `jogo`
--
ALTER TABLE `jogo`
  ADD CONSTRAINT `jogo_ibfk_1` FOREIGN KEY (`IDUtilizador`) REFERENCES `utilizador` (`IDUtilizador`);

--
-- Limitadores para a tabela `marsami`
--
ALTER TABLE `marsami`
  ADD CONSTRAINT `marsami_ibfk_1` FOREIGN KEY (`IDJogo`) REFERENCES `jogo` (`IDJogo`);

--
-- Limitadores para a tabela `medicoespassagens`
--
ALTER TABLE `medicoespassagens`
  ADD CONSTRAINT `medicoespassagens_ibfk_1` FOREIGN KEY (`IDJogo`) REFERENCES `jogo` (`IDJogo`);

--
-- Limitadores para a tabela `medicoessom`
--
ALTER TABLE `medicoessom`
  ADD CONSTRAINT `medicoessom_ibfk_1` FOREIGN KEY (`IDJogo`) REFERENCES `jogo` (`IDJogo`);

--
-- Limitadores para a tabela `mensagens`
--
ALTER TABLE `mensagens`
  ADD CONSTRAINT `mensagens_ibfk_1` FOREIGN KEY (`IDJogo`) REFERENCES `jogo` (`IDJogo`);

--
-- Limitadores para a tabela `ocupacaolabirinto`
--
ALTER TABLE `ocupacaolabirinto`
  ADD CONSTRAINT `ocupacaolabirinto_ibfk_1` FOREIGN KEY (`IDJogo`) REFERENCES `jogo` (`IDJogo`);

COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
