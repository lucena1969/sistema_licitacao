-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Tempo de geração: 15/06/2025 às 20:51
-- Versão do servidor: 10.4.32-MariaDB
-- Versão do PHP: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Banco de dados: `sistema_licitacao`
--

-- --------------------------------------------------------

--
-- Estrutura para tabela `backups_sistema`
--

CREATE TABLE `backups_sistema` (
  `id` int(11) NOT NULL,
  `tipo` enum('database','files','completo') NOT NULL,
  `status` enum('processando','sucesso','erro') NOT NULL DEFAULT 'processando',
  `inicio` datetime NOT NULL,
  `fim` datetime DEFAULT NULL,
  `tamanho_total` bigint(20) DEFAULT 0,
  `arquivo_database` varchar(255) DEFAULT NULL,
  `arquivo_files` varchar(255) DEFAULT NULL,
  `tempo_execucao` int(11) DEFAULT NULL,
  `erros` text DEFAULT NULL,
  `criado_por` varchar(100) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `licitacoes`
--

CREATE TABLE `licitacoes` (
  `id` int(11) NOT NULL,
  `nup` varchar(20) DEFAULT NULL COMMENT 'Número Único de Protocolo',
  `data_entrada_dipli` date DEFAULT NULL COMMENT 'Data de entrada na DIPLI',
  `resp_instrucao` varchar(255) DEFAULT NULL COMMENT 'Responsável pela instrução',
  `area_demandante` varchar(255) DEFAULT NULL COMMENT 'Área que demandou a licitação',
  `pregoeiro` varchar(255) DEFAULT NULL COMMENT 'Nome do pregoeiro',
  `pca_dados_id` int(11) DEFAULT NULL COMMENT 'Vinculação com PCA atual',
  `numero_processo` varchar(100) DEFAULT NULL COMMENT 'Número do processo (mantido para compatibilidade)',
  `tipo_licitacao` varchar(50) DEFAULT NULL COMMENT 'Tipo de licitação (mantido para compatibilidade)',
  `modalidade` varchar(50) DEFAULT NULL,
  `tipo` varchar(50) DEFAULT NULL COMMENT 'Tipo da licitação (TRADICIONAL, COTACAO, SRP)',
  `numero_contratacao` varchar(100) DEFAULT NULL COMMENT 'Número da contratação do PCA',
  `numero` int(11) DEFAULT NULL COMMENT 'Número sequencial da licitação',
  `ano` int(11) DEFAULT NULL COMMENT 'Ano da licitação',
  `objeto` text DEFAULT NULL,
  `valor_estimado` decimal(15,2) DEFAULT NULL,
  `qtd_itens` int(11) DEFAULT NULL COMMENT 'Quantidade de itens da licitação',
  `data_abertura` date DEFAULT NULL,
  `data_publicacao` date DEFAULT NULL COMMENT 'Data de publicação do edital',
  `data_homologacao` date DEFAULT NULL,
  `valor_homologado` decimal(15,2) DEFAULT NULL COMMENT 'Valor homologado',
  `qtd_homol` int(11) DEFAULT NULL COMMENT 'Quantidade homologada',
  `economia` decimal(15,2) DEFAULT NULL COMMENT 'Economia obtida (estimado - homologado)',
  `link` text DEFAULT NULL COMMENT 'Link para documentos/edital',
  `usuario_id` int(11) DEFAULT NULL COMMENT 'ID do usuário que criou',
  `situacao` enum('EM_ANDAMENTO','HOMOLOGADO','FRACASSADO','REVOGADO','CANCELADO','PREPARACAO') DEFAULT 'EM_ANDAMENTO',
  `observacoes` text DEFAULT NULL COMMENT 'Observações gerais',
  `usuario_criador` int(11) DEFAULT NULL COMMENT 'Usuário criador (mantido para compatibilidade)',
  `criado_em` timestamp NOT NULL DEFAULT current_timestamp(),
  `atualizado_em` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Processos licitatórios vinculados aos PCAs atuais';

--
-- Acionadores `licitacoes`
--
DELIMITER $$
CREATE TRIGGER `tr_licitacoes_calcular_economia` BEFORE UPDATE ON `licitacoes` FOR EACH ROW BEGIN
    -- Calcular economia se ambos os valores estiverem preenchidos
    IF NEW.valor_estimado IS NOT NULL AND NEW.valor_homologado IS NOT NULL THEN
        SET NEW.economia = NEW.valor_estimado - NEW.valor_homologado;
    END IF;
    
    -- Atualizar data de modificação
    SET NEW.atualizado_em = NOW();
END
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `tr_licitacoes_log_mudancas` AFTER UPDATE ON `licitacoes` FOR EACH ROW BEGIN
    -- Log mudança de situação
    IF OLD.situacao != NEW.situacao THEN
        INSERT INTO logs_sistema (usuario_id, acao, modulo, detalhes, modulo_origem, registro_afetado_id) 
        VALUES (NEW.usuario_id, 'MUDANCA_SITUACAO', 'licitacoes', 
                CONCAT('Situação alterada de "', OLD.situacao, '" para "', NEW.situacao, '"'), 
                'TRIGGER', NEW.id);
    END IF;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Estrutura para tabela `logs_sistema`
--

CREATE TABLE `logs_sistema` (
  `id` int(11) NOT NULL,
  `usuario_id` int(11) DEFAULT NULL,
  `acao` varchar(100) NOT NULL,
  `modulo` varchar(50) NOT NULL,
  `modulo_origem` varchar(100) DEFAULT NULL COMMENT 'Módulo que gerou o log',
  `detalhes` text DEFAULT NULL,
  `registro_afetado_id` int(11) DEFAULT NULL COMMENT 'ID do registro afetado',
  `ip_usuario` varchar(45) DEFAULT NULL COMMENT 'IP do usuário',
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Logs de operações e auditoria do sistema';

-- --------------------------------------------------------

--
-- Estrutura para tabela `pca_2022`
--

CREATE TABLE `pca_2022` (
  `id` int(11) NOT NULL,
  `numero_contratacao` varchar(50) DEFAULT NULL,
  `status_contratacao` varchar(100) DEFAULT NULL,
  `situacao_execucao` varchar(100) DEFAULT 'Não iniciado',
  `titulo_contratacao` varchar(500) DEFAULT NULL,
  `categoria_contratacao` varchar(200) DEFAULT NULL,
  `uasg_atual` varchar(100) DEFAULT NULL,
  `valor_total_contratacao` decimal(15,2) DEFAULT NULL,
  `data_inicio_processo` date DEFAULT NULL,
  `data_conclusao_processo` date DEFAULT NULL,
  `prazo_duracao_dias` int(11) DEFAULT NULL,
  `area_requisitante` varchar(200) DEFAULT NULL,
  `numero_dfd` varchar(50) DEFAULT NULL,
  `prioridade` varchar(50) DEFAULT NULL,
  `numero_item_dfd` varchar(50) DEFAULT NULL,
  `data_conclusao_dfd` date DEFAULT NULL,
  `classificacao_contratacao` varchar(200) DEFAULT NULL,
  `codigo_classe_grupo` varchar(50) DEFAULT NULL,
  `nome_classe_grupo` varchar(200) DEFAULT NULL,
  `codigo_pdm_material` varchar(50) DEFAULT NULL,
  `nome_pdm_material` varchar(200) DEFAULT NULL,
  `codigo_material_servico` varchar(100) DEFAULT NULL,
  `descricao_material_servico` varchar(1000) DEFAULT NULL,
  `unidade_fornecimento` varchar(50) DEFAULT NULL,
  `valor_unitario` decimal(15,2) DEFAULT NULL,
  `quantidade` int(11) DEFAULT NULL,
  `valor_total` decimal(15,2) DEFAULT NULL,
  `criado_em` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `pca_2023`
--

CREATE TABLE `pca_2023` (
  `id` int(11) NOT NULL,
  `numero_contratacao` varchar(50) DEFAULT NULL,
  `status_contratacao` varchar(100) DEFAULT NULL,
  `situacao_execucao` varchar(100) DEFAULT 'Não iniciado',
  `titulo_contratacao` varchar(500) DEFAULT NULL,
  `categoria_contratacao` varchar(200) DEFAULT NULL,
  `uasg_atual` varchar(100) DEFAULT NULL,
  `valor_total_contratacao` decimal(15,2) DEFAULT NULL,
  `data_inicio_processo` date DEFAULT NULL,
  `data_conclusao_processo` date DEFAULT NULL,
  `prazo_duracao_dias` int(11) DEFAULT NULL,
  `area_requisitante` varchar(200) DEFAULT NULL,
  `numero_dfd` varchar(50) DEFAULT NULL,
  `prioridade` varchar(50) DEFAULT NULL,
  `numero_item_dfd` varchar(50) DEFAULT NULL,
  `data_conclusao_dfd` date DEFAULT NULL,
  `classificacao_contratacao` varchar(200) DEFAULT NULL,
  `codigo_classe_grupo` varchar(50) DEFAULT NULL,
  `nome_classe_grupo` varchar(200) DEFAULT NULL,
  `codigo_pdm_material` varchar(50) DEFAULT NULL,
  `nome_pdm_material` varchar(200) DEFAULT NULL,
  `codigo_material_servico` varchar(100) DEFAULT NULL,
  `descricao_material_servico` varchar(1000) DEFAULT NULL,
  `unidade_fornecimento` varchar(50) DEFAULT NULL,
  `valor_unitario` decimal(15,2) DEFAULT NULL,
  `quantidade` int(11) DEFAULT NULL,
  `valor_total` decimal(15,2) DEFAULT NULL,
  `criado_em` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `pca_2024`
--

CREATE TABLE `pca_2024` (
  `id` int(11) NOT NULL,
  `numero_contratacao` varchar(50) DEFAULT NULL,
  `status_contratacao` varchar(100) DEFAULT NULL,
  `situacao_execucao` varchar(100) DEFAULT 'Não iniciado',
  `titulo_contratacao` varchar(500) DEFAULT NULL,
  `categoria_contratacao` varchar(200) DEFAULT NULL,
  `uasg_atual` varchar(100) DEFAULT NULL,
  `valor_total_contratacao` decimal(15,2) DEFAULT NULL,
  `data_inicio_processo` date DEFAULT NULL,
  `data_conclusao_processo` date DEFAULT NULL,
  `prazo_duracao_dias` int(11) DEFAULT NULL,
  `area_requisitante` varchar(200) DEFAULT NULL,
  `numero_dfd` varchar(50) DEFAULT NULL,
  `prioridade` varchar(50) DEFAULT NULL,
  `numero_item_dfd` varchar(50) DEFAULT NULL,
  `data_conclusao_dfd` date DEFAULT NULL,
  `classificacao_contratacao` varchar(200) DEFAULT NULL,
  `codigo_classe_grupo` varchar(50) DEFAULT NULL,
  `nome_classe_grupo` varchar(200) DEFAULT NULL,
  `codigo_pdm_material` varchar(50) DEFAULT NULL,
  `nome_pdm_material` varchar(200) DEFAULT NULL,
  `codigo_material_servico` varchar(100) DEFAULT NULL,
  `descricao_material_servico` varchar(1000) DEFAULT NULL,
  `unidade_fornecimento` varchar(50) DEFAULT NULL,
  `valor_unitario` decimal(15,2) DEFAULT NULL,
  `quantidade` int(11) DEFAULT NULL,
  `valor_total` decimal(15,2) DEFAULT NULL,
  `criado_em` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `pca_dados`
--

CREATE TABLE `pca_dados` (
  `id` int(11) NOT NULL,
  `importacao_id` int(11) NOT NULL,
  `numero_contratacao` varchar(50) DEFAULT NULL,
  `status_contratacao` varchar(100) DEFAULT NULL,
  `situacao_execucao` varchar(100) DEFAULT 'Não iniciado',
  `titulo_contratacao` varchar(500) DEFAULT NULL,
  `categoria_contratacao` varchar(200) DEFAULT NULL,
  `uasg_atual` varchar(100) DEFAULT NULL,
  `valor_total_contratacao` decimal(15,2) DEFAULT NULL,
  `data_inicio_processo` date DEFAULT NULL,
  `data_conclusao_processo` date DEFAULT NULL,
  `prazo_duracao_dias` int(11) DEFAULT NULL,
  `area_requisitante` varchar(200) DEFAULT NULL,
  `numero_dfd` varchar(50) DEFAULT NULL,
  `prioridade` varchar(50) DEFAULT NULL,
  `urgente` tinyint(1) DEFAULT 0 COMMENT 'Marca contratação como urgente',
  `numero_item_dfd` varchar(50) DEFAULT NULL,
  `data_conclusao_dfd` date DEFAULT NULL,
  `classificacao_contratacao` varchar(200) DEFAULT NULL,
  `codigo_classe_grupo` varchar(50) DEFAULT NULL,
  `nome_classe_grupo` varchar(200) DEFAULT NULL,
  `codigo_pdm_material` varchar(50) DEFAULT NULL,
  `nome_pdm_material` varchar(200) DEFAULT NULL,
  `codigo_material_servico` varchar(100) DEFAULT NULL,
  `descricao_material_servico` varchar(1000) DEFAULT NULL,
  `unidade_fornecimento` varchar(50) DEFAULT NULL,
  `valor_unitario` decimal(15,2) DEFAULT NULL,
  `quantidade` int(11) DEFAULT NULL,
  `valor_total` decimal(15,2) DEFAULT NULL,
  `criado_em` timestamp NOT NULL DEFAULT current_timestamp(),
  `atualizado_em` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Dados atuais do PCA (2025 e 2026) - Editáveis';

-- --------------------------------------------------------

--
-- Estrutura para tabela `pca_estados_tempo`
--

CREATE TABLE `pca_estados_tempo` (
  `id` int(11) NOT NULL,
  `numero_contratacao` varchar(50) NOT NULL,
  `situacao_execucao` varchar(100) NOT NULL,
  `data_inicio` date NOT NULL,
  `data_fim` date DEFAULT NULL,
  `dias_no_estado` int(11) DEFAULT NULL,
  `ativo` tinyint(1) DEFAULT 1,
  `criado_em` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Controle de tempo em cada situação de execução';

-- --------------------------------------------------------

--
-- Estrutura para tabela `pca_historico`
--

CREATE TABLE `pca_historico` (
  `id` int(11) NOT NULL,
  `numero_contratacao` varchar(50) NOT NULL,
  `campo_alterado` varchar(100) NOT NULL,
  `valor_anterior` text DEFAULT NULL,
  `valor_novo` text DEFAULT NULL,
  `importacao_id` int(11) DEFAULT NULL,
  `usuario_id` int(11) NOT NULL,
  `data_alteracao` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Auditoria de mudanças nos dados do PCA';

-- --------------------------------------------------------

--
-- Estrutura para tabela `pca_historico_anos`
--

CREATE TABLE `pca_historico_anos` (
  `id` int(11) NOT NULL,
  `ano` year(4) NOT NULL,
  `numero_contratacao` varchar(50) DEFAULT NULL,
  `status_contratacao` varchar(100) DEFAULT NULL,
  `situacao_execucao` varchar(100) DEFAULT 'Não iniciado',
  `titulo_contratacao` text DEFAULT NULL,
  `categoria_contratacao` varchar(200) DEFAULT NULL,
  `uasg_atual` varchar(100) DEFAULT NULL,
  `valor_total_contratacao` decimal(15,2) DEFAULT NULL,
  `data_inicio_processo` date DEFAULT NULL,
  `data_conclusao_processo` date DEFAULT NULL,
  `prazo_duracao_dias` int(11) DEFAULT NULL,
  `area_requisitante` varchar(200) DEFAULT NULL,
  `numero_dfd` varchar(50) DEFAULT NULL,
  `prioridade` varchar(50) DEFAULT NULL,
  `numero_item_dfd` varchar(50) DEFAULT NULL,
  `data_conclusao_dfd` date DEFAULT NULL,
  `classificacao_contratacao` varchar(200) DEFAULT NULL,
  `codigo_classe_grupo` varchar(50) DEFAULT NULL,
  `nome_classe_grupo` varchar(200) DEFAULT NULL,
  `codigo_pdm_material` varchar(50) DEFAULT NULL,
  `nome_pdm_material` varchar(200) DEFAULT NULL,
  `codigo_material_servico` varchar(100) DEFAULT NULL,
  `descricao_material_servico` text DEFAULT NULL,
  `unidade_fornecimento` varchar(50) DEFAULT NULL,
  `valor_unitario` decimal(15,2) DEFAULT NULL,
  `quantidade` int(11) DEFAULT NULL,
  `valor_total` decimal(15,2) DEFAULT NULL,
  `criado_em` timestamp NOT NULL DEFAULT current_timestamp(),
  `atualizado_em` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Dados históricos dos PCAs (2022-2024) - Somente leitura';

-- --------------------------------------------------------

--
-- Estrutura para tabela `pca_importacoes`
--

CREATE TABLE `pca_importacoes` (
  `id` int(11) NOT NULL,
  `nome_arquivo` varchar(255) NOT NULL,
  `ano_pca` year(4) NOT NULL DEFAULT 2025 COMMENT 'Ano do PCA importado',
  `usuario_id` int(11) NOT NULL,
  `status` enum('processando','concluido','erro') DEFAULT 'processando',
  `total_registros` int(11) DEFAULT 0,
  `registros_novos` int(11) DEFAULT 0,
  `registros_atualizados` int(11) DEFAULT 0,
  `observacoes` text DEFAULT NULL,
  `criado_em` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Histórico de importações de PCAs separadas por ano';

-- --------------------------------------------------------

--
-- Estrutura para tabela `pca_riscos`
--

CREATE TABLE `pca_riscos` (
  `id` int(11) NOT NULL,
  `numero_dfd` varchar(50) NOT NULL,
  `mes_relatorio` varchar(7) NOT NULL COMMENT 'Formato: YYYY-MM',
  `nivel_risco` enum('baixo','medio','alto','extremo') NOT NULL,
  `categoria_risco` varchar(100) NOT NULL,
  `descricao_risco` text NOT NULL,
  `impacto` text DEFAULT NULL,
  `probabilidade` varchar(50) DEFAULT NULL,
  `acao_mitigacao` text DEFAULT NULL,
  `responsavel` varchar(100) DEFAULT NULL,
  `prazo_mitigacao` date DEFAULT NULL,
  `status_acao` enum('pendente','em_andamento','concluida','cancelada') DEFAULT 'pendente',
  `observacoes` text DEFAULT NULL,
  `criado_em` datetime DEFAULT current_timestamp(),
  `atualizado_em` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `criado_por` varchar(100) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `usuarios`
--

CREATE TABLE `usuarios` (
  `id` int(11) NOT NULL,
  `nome` varchar(255) NOT NULL,
  `email` varchar(255) NOT NULL,
  `senha` varchar(255) NOT NULL,
  `tipo_usuario` enum('admin','usuario','coordenador','diplan','dipli','visitante') DEFAULT 'usuario',
  `nivel_acesso` tinyint(1) NOT NULL DEFAULT 3 COMMENT '1=Coordenador, 2=DIPLAN, 3=DIPLI',
  `departamento` varchar(100) DEFAULT 'CGLIC' COMMENT 'Departamento do usuário',
  `ativo` tinyint(1) DEFAULT 1,
  `ultimo_login` timestamp NULL DEFAULT NULL,
  `criado_em` timestamp NOT NULL DEFAULT current_timestamp(),
  `atualizado_em` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Sistema de usuários com níveis de acesso hierárquicos';

--
-- Acionadores `usuarios`
--
DELIMITER $$
CREATE TRIGGER `tr_usuarios_ultimo_login` BEFORE UPDATE ON `usuarios` FOR EACH ROW BEGIN
    IF NEW.ultimo_login IS NOT NULL AND NEW.ultimo_login != OLD.ultimo_login THEN
        SET NEW.atualizado_em = CURRENT_TIMESTAMP;
    END IF;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Estrutura stand-in para view `view_contratacoes_licitacoes`
-- (Veja abaixo para a visão atual)
--
CREATE TABLE `view_contratacoes_licitacoes` (
`numero_dfd` varchar(50)
,`numero_contratacao` varchar(50)
,`titulo_contratacao` varchar(500)
,`valor_total_contratacao` decimal(15,2)
,`situacao_execucao` varchar(100)
,`ano_pca` year(4)
,`processo_licitacao` varchar(100)
,`situacao_licitacao` enum('EM_ANDAMENTO','HOMOLOGADO','FRACASSADO','REVOGADO','CANCELADO','PREPARACAO')
,`data_abertura` date
,`data_homologacao` date
);

-- --------------------------------------------------------

--
-- Estrutura stand-in para view `view_dashboard_licitacoes`
-- (Veja abaixo para a visão atual)
--
CREATE TABLE `view_dashboard_licitacoes` (
`id` int(11)
,`nup` varchar(20)
,`objeto` text
,`modalidade` varchar(50)
,`situacao` enum('EM_ANDAMENTO','HOMOLOGADO','FRACASSADO','REVOGADO','CANCELADO','PREPARACAO')
,`valor_estimado` decimal(15,2)
,`valor_homologado` decimal(15,2)
,`economia` decimal(15,2)
,`data_abertura` date
,`criado_em` timestamp
,`usuario_nome` varchar(255)
,`numero_dfd` varchar(50)
,`titulo_contratacao` varchar(500)
,`area_requisitante` varchar(200)
,`status_prazo` varchar(8)
);

-- --------------------------------------------------------

--
-- Estrutura stand-in para view `view_pca_resumo_anos`
-- (Veja abaixo para a visão atual)
--
CREATE TABLE `view_pca_resumo_anos` (
`tipo` varchar(9)
,`ano` year(4)
,`total_dfds` bigint(21)
,`total_contratacoes` bigint(21)
,`valor_total` decimal(37,2)
,`concluidas` bigint(21)
);

-- --------------------------------------------------------

--
-- Estrutura stand-in para view `v_estatisticas_pca_anos`
-- (Veja abaixo para a visão atual)
--
CREATE TABLE `v_estatisticas_pca_anos` (
);

-- --------------------------------------------------------

--
-- Estrutura stand-in para view `v_pca_historico_completo`
-- (Veja abaixo para a visão atual)
--
CREATE TABLE `v_pca_historico_completo` (
);

-- --------------------------------------------------------

--
-- Estrutura para view `view_contratacoes_licitacoes`
--
DROP TABLE IF EXISTS `view_contratacoes_licitacoes`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `view_contratacoes_licitacoes`  AS SELECT `pd`.`numero_dfd` AS `numero_dfd`, `pd`.`numero_contratacao` AS `numero_contratacao`, `pd`.`titulo_contratacao` AS `titulo_contratacao`, `pd`.`valor_total_contratacao` AS `valor_total_contratacao`, `pd`.`situacao_execucao` AS `situacao_execucao`, `pi`.`ano_pca` AS `ano_pca`, `l`.`numero_processo` AS `processo_licitacao`, `l`.`situacao` AS `situacao_licitacao`, `l`.`data_abertura` AS `data_abertura`, `l`.`data_homologacao` AS `data_homologacao` FROM ((`pca_dados` `pd` join `pca_importacoes` `pi` on(`pi`.`id` = `pd`.`importacao_id`)) left join `licitacoes` `l` on(`l`.`pca_dados_id` = `pd`.`id`)) WHERE `pd`.`numero_dfd` is not null ORDER BY `pi`.`ano_pca` DESC, `pd`.`numero_dfd` ASC ;

-- --------------------------------------------------------

--
-- Estrutura para view `view_dashboard_licitacoes`
--
DROP TABLE IF EXISTS `view_dashboard_licitacoes`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `view_dashboard_licitacoes`  AS SELECT `l`.`id` AS `id`, `l`.`nup` AS `nup`, `l`.`objeto` AS `objeto`, `l`.`modalidade` AS `modalidade`, `l`.`situacao` AS `situacao`, `l`.`valor_estimado` AS `valor_estimado`, `l`.`valor_homologado` AS `valor_homologado`, `l`.`economia` AS `economia`, `l`.`data_abertura` AS `data_abertura`, `l`.`criado_em` AS `criado_em`, `u`.`nome` AS `usuario_nome`, `p`.`numero_dfd` AS `numero_dfd`, `p`.`titulo_contratacao` AS `titulo_contratacao`, `p`.`area_requisitante` AS `area_requisitante`, CASE WHEN `l`.`data_abertura` < curdate() AND `l`.`situacao` in ('PREPARACAO','EM_ANDAMENTO') THEN 'ATRASADA' WHEN `l`.`data_abertura` between curdate() and curdate() + interval 7 day THEN 'PROXIMA' ELSE 'NORMAL' END AS `status_prazo` FROM ((`licitacoes` `l` left join `usuarios` `u` on(`l`.`usuario_id` = `u`.`id`)) left join `pca_dados` `p` on(`l`.`pca_dados_id` = `p`.`id`)) ORDER BY `l`.`data_abertura` DESC ;

-- --------------------------------------------------------

--
-- Estrutura para view `view_pca_resumo_anos`
--
DROP TABLE IF EXISTS `view_pca_resumo_anos`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `view_pca_resumo_anos`  AS SELECT 'historico' AS `tipo`, `pca_historico_anos`.`ano` AS `ano`, count(distinct `pca_historico_anos`.`numero_dfd`) AS `total_dfds`, count(0) AS `total_contratacoes`, coalesce(sum(`pca_historico_anos`.`valor_total_contratacao`),0) AS `valor_total`, count(case when `pca_historico_anos`.`situacao_execucao` = 'Concluído' then 1 end) AS `concluidas` FROM `pca_historico_anos` GROUP BY `pca_historico_anos`.`ano`union all select 'atual' AS `tipo`,`pi`.`ano_pca` AS `ano`,count(distinct `pd`.`numero_dfd`) AS `total_dfds`,count(`pd`.`id`) AS `total_contratacoes`,coalesce(sum(`pd`.`valor_total_contratacao`),0) AS `valor_total`,count(case when `pd`.`situacao_execucao` = 'Concluído' then 1 end) AS `concluidas` from (`pca_importacoes` `pi` left join `pca_dados` `pd` on(`pd`.`importacao_id` = `pi`.`id`)) group by `pi`.`ano_pca` order by `ano` desc  ;

-- --------------------------------------------------------

--
-- Estrutura para view `v_estatisticas_pca_anos`
--
DROP TABLE IF EXISTS `v_estatisticas_pca_anos`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `v_estatisticas_pca_anos`  AS SELECT `v_pca_historico_completo`.`ano` AS `ano`, count(0) AS `total_contratacoes`, sum(`v_pca_historico_completo`.`valor_total_contratacao`) AS `valor_total_ano`, count(distinct `v_pca_historico_completo`.`area_requisitante`) AS `areas_distintas`, count(distinct `v_pca_historico_completo`.`numero_dfd`) AS `dfds_distintos`, avg(`v_pca_historico_completo`.`valor_total_contratacao`) AS `valor_medio_contratacao` FROM `v_pca_historico_completo` GROUP BY `v_pca_historico_completo`.`ano` ;

-- --------------------------------------------------------

--
-- Estrutura para view `v_pca_historico_completo`
--
DROP TABLE IF EXISTS `v_pca_historico_completo`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `v_pca_historico_completo`  AS SELECT 2022 AS `ano`, `pca_2022`.`id` AS `id`, `pca_2022`.`numero_contratacao` AS `numero_contratacao`, `pca_2022`.`status_contratacao` AS `status_contratacao`, `pca_2022`.`situacao_execucao` AS `situacao_execucao`, `pca_2022`.`titulo_contratacao` AS `titulo_contratacao`, `pca_2022`.`categoria_contratacao` AS `categoria_contratacao`, `pca_2022`.`uasg_atual` AS `uasg_atual`, `pca_2022`.`valor_total_contratacao` AS `valor_total_contratacao`, `pca_2022`.`data_inicio_processo` AS `data_inicio_processo`, `pca_2022`.`data_conclusao_processo` AS `data_conclusao_processo`, `pca_2022`.`prazo_duracao_dias` AS `prazo_duracao_dias`, `pca_2022`.`area_requisitante` AS `area_requisitante`, `pca_2022`.`numero_dfd` AS `numero_dfd`, `pca_2022`.`prioridade` AS `prioridade`, `pca_2022`.`numero_item_dfd` AS `numero_item_dfd`, `pca_2022`.`data_conclusao_dfd` AS `data_conclusao_dfd`, `pca_2022`.`classificacao_contratacao` AS `classificacao_contratacao`, `pca_2022`.`codigo_classe_grupo` AS `codigo_classe_grupo`, `pca_2022`.`nome_classe_grupo` AS `nome_classe_grupo`, `pca_2022`.`codigo_pdm_material` AS `codigo_pdm_material`, `pca_2022`.`nome_pdm_material` AS `nome_pdm_material`, `pca_2022`.`codigo_material_servico` AS `codigo_material_servico`, `pca_2022`.`descricao_material_servico` AS `descricao_material_servico`, `pca_2022`.`unidade_fornecimento` AS `unidade_fornecimento`, `pca_2022`.`valor_unitario` AS `valor_unitario`, `pca_2022`.`quantidade` AS `quantidade`, `pca_2022`.`valor_total` AS `valor_total`, `pca_2022`.`importado_em` AS `criado_em` FROM `pca_2022`union all select 2023 AS `ano`,`pca_2023`.`id` AS `id`,`pca_2023`.`numero_contratacao` AS `numero_contratacao`,`pca_2023`.`status_contratacao` AS `status_contratacao`,`pca_2023`.`situacao_execucao` AS `situacao_execucao`,`pca_2023`.`titulo_contratacao` AS `titulo_contratacao`,`pca_2023`.`categoria_contratacao` AS `categoria_contratacao`,`pca_2023`.`uasg_atual` AS `uasg_atual`,`pca_2023`.`valor_total_contratacao` AS `valor_total_contratacao`,`pca_2023`.`data_inicio_processo` AS `data_inicio_processo`,`pca_2023`.`data_conclusao_processo` AS `data_conclusao_processo`,`pca_2023`.`prazo_duracao_dias` AS `prazo_duracao_dias`,`pca_2023`.`area_requisitante` AS `area_requisitante`,`pca_2023`.`numero_dfd` AS `numero_dfd`,`pca_2023`.`prioridade` AS `prioridade`,`pca_2023`.`numero_item_dfd` AS `numero_item_dfd`,`pca_2023`.`data_conclusao_dfd` AS `data_conclusao_dfd`,`pca_2023`.`classificacao_contratacao` AS `classificacao_contratacao`,`pca_2023`.`codigo_classe_grupo` AS `codigo_classe_grupo`,`pca_2023`.`nome_classe_grupo` AS `nome_classe_grupo`,`pca_2023`.`codigo_pdm_material` AS `codigo_pdm_material`,`pca_2023`.`nome_pdm_material` AS `nome_pdm_material`,`pca_2023`.`codigo_material_servico` AS `codigo_material_servico`,`pca_2023`.`descricao_material_servico` AS `descricao_material_servico`,`pca_2023`.`unidade_fornecimento` AS `unidade_fornecimento`,`pca_2023`.`valor_unitario` AS `valor_unitario`,`pca_2023`.`quantidade` AS `quantidade`,`pca_2023`.`valor_total` AS `valor_total`,`pca_2023`.`importado_em` AS `criado_em` from `pca_2023` union all select 2024 AS `ano`,`pca_2024`.`id` AS `id`,`pca_2024`.`numero_contratacao` AS `numero_contratacao`,`pca_2024`.`status_contratacao` AS `status_contratacao`,`pca_2024`.`situacao_execucao` AS `situacao_execucao`,`pca_2024`.`titulo_contratacao` AS `titulo_contratacao`,`pca_2024`.`categoria_contratacao` AS `categoria_contratacao`,`pca_2024`.`uasg_atual` AS `uasg_atual`,`pca_2024`.`valor_total_contratacao` AS `valor_total_contratacao`,`pca_2024`.`data_inicio_processo` AS `data_inicio_processo`,`pca_2024`.`data_conclusao_processo` AS `data_conclusao_processo`,`pca_2024`.`prazo_duracao_dias` AS `prazo_duracao_dias`,`pca_2024`.`area_requisitante` AS `area_requisitante`,`pca_2024`.`numero_dfd` AS `numero_dfd`,`pca_2024`.`prioridade` AS `prioridade`,`pca_2024`.`numero_item_dfd` AS `numero_item_dfd`,`pca_2024`.`data_conclusao_dfd` AS `data_conclusao_dfd`,`pca_2024`.`classificacao_contratacao` AS `classificacao_contratacao`,`pca_2024`.`codigo_classe_grupo` AS `codigo_classe_grupo`,`pca_2024`.`nome_classe_grupo` AS `nome_classe_grupo`,`pca_2024`.`codigo_pdm_material` AS `codigo_pdm_material`,`pca_2024`.`nome_pdm_material` AS `nome_pdm_material`,`pca_2024`.`codigo_material_servico` AS `codigo_material_servico`,`pca_2024`.`descricao_material_servico` AS `descricao_material_servico`,`pca_2024`.`unidade_fornecimento` AS `unidade_fornecimento`,`pca_2024`.`valor_unitario` AS `valor_unitario`,`pca_2024`.`quantidade` AS `quantidade`,`pca_2024`.`valor_total` AS `valor_total`,`pca_2024`.`importado_em` AS `criado_em` from `pca_2024`  ;

--
-- Índices para tabelas despejadas
--

--
-- Índices de tabela `backups_sistema`
--
ALTER TABLE `backups_sistema`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_tipo_status` (`tipo`,`status`),
  ADD KEY `idx_inicio` (`inicio`);

--
-- Índices de tabela `licitacoes`
--
ALTER TABLE `licitacoes`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `numero_processo` (`numero_processo`),
  ADD KEY `fk_licitacoes_pca_dados` (`pca_dados_id`),
  ADD KEY `fk_licitacoes_usuario` (`usuario_criador`),
  ADD KEY `idx_situacao` (`situacao`),
  ADD KEY `idx_data_abertura` (`data_abertura`),
  ADD KEY `idx_modalidade` (`modalidade`),
  ADD KEY `idx_nup` (`nup`),
  ADD KEY `idx_numero_contratacao` (`numero_contratacao`),
  ADD KEY `idx_ano` (`ano`),
  ADD KEY `idx_tipo` (`tipo`),
  ADD KEY `idx_pregoeiro` (`pregoeiro`),
  ADD KEY `fk_licitacoes_usuario_id` (`usuario_id`),
  ADD KEY `idx_licitacoes_situacao` (`situacao`),
  ADD KEY `idx_licitacoes_modalidade` (`modalidade`),
  ADD KEY `idx_licitacoes_data_abertura` (`data_abertura`),
  ADD KEY `idx_licitacoes_numero_contratacao` (`numero_contratacao`),
  ADD KEY `idx_licitacoes_valor_estimado` (`valor_estimado`);

--
-- Índices de tabela `logs_sistema`
--
ALTER TABLE `logs_sistema`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_logs_usuario` (`usuario_id`),
  ADD KEY `idx_acao` (`acao`),
  ADD KEY `idx_modulo` (`modulo`),
  ADD KEY `idx_created_at` (`created_at`),
  ADD KEY `idx_logs_sistema_acao` (`acao`),
  ADD KEY `idx_logs_sistema_modulo` (`modulo`);

--
-- Índices de tabela `pca_2022`
--
ALTER TABLE `pca_2022`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_numero_contratacao` (`numero_contratacao`),
  ADD KEY `idx_area_requisitante` (`area_requisitante`),
  ADD KEY `idx_numero_dfd` (`numero_dfd`);

--
-- Índices de tabela `pca_2023`
--
ALTER TABLE `pca_2023`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_numero_contratacao` (`numero_contratacao`),
  ADD KEY `idx_area_requisitante` (`area_requisitante`),
  ADD KEY `idx_numero_dfd` (`numero_dfd`);

--
-- Índices de tabela `pca_2024`
--
ALTER TABLE `pca_2024`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_numero_contratacao` (`numero_contratacao`),
  ADD KEY `idx_area_requisitante` (`area_requisitante`),
  ADD KEY `idx_numero_dfd` (`numero_dfd`);

--
-- Índices de tabela `pca_dados`
--
ALTER TABLE `pca_dados`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_pca_dados_importacao` (`importacao_id`),
  ADD KEY `idx_numero_contratacao` (`numero_contratacao`),
  ADD KEY `idx_numero_dfd` (`numero_dfd`),
  ADD KEY `idx_situacao_execucao` (`situacao_execucao`),
  ADD KEY `idx_area_requisitante` (`area_requisitante`),
  ADD KEY `idx_categoria_contratacao` (`categoria_contratacao`),
  ADD KEY `idx_data_conclusao` (`data_conclusao_processo`),
  ADD KEY `idx_valor_total` (`valor_total_contratacao`),
  ADD KEY `idx_pca_dados_importacao_dfd` (`importacao_id`,`numero_dfd`),
  ADD KEY `idx_pca_dados_situacao_data` (`situacao_execucao`,`data_conclusao_processo`),
  ADD KEY `idx_pca_dados_numero_dfd` (`numero_dfd`),
  ADD KEY `idx_pca_dados_situacao` (`situacao_execucao`),
  ADD KEY `idx_pca_dados_area` (`area_requisitante`),
  ADD KEY `idx_pca_dados_categoria` (`categoria_contratacao`),
  ADD KEY `idx_pca_dados_valor` (`valor_total_contratacao`),
  ADD KEY `idx_pca_dados_data_inicio` (`data_inicio_processo`),
  ADD KEY `idx_pca_dados_data_conclusao` (`data_conclusao_processo`);

--
-- Índices de tabela `pca_estados_tempo`
--
ALTER TABLE `pca_estados_tempo`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_numero_contratacao` (`numero_contratacao`),
  ADD KEY `idx_situacao_execucao` (`situacao_execucao`),
  ADD KEY `idx_ativo` (`ativo`),
  ADD KEY `idx_data_inicio` (`data_inicio`);

--
-- Índices de tabela `pca_historico`
--
ALTER TABLE `pca_historico`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_pca_historico_importacao` (`importacao_id`),
  ADD KEY `fk_pca_historico_usuario` (`usuario_id`),
  ADD KEY `idx_numero_contratacao` (`numero_contratacao`),
  ADD KEY `idx_data_alteracao` (`data_alteracao`);

--
-- Índices de tabela `pca_historico_anos`
--
ALTER TABLE `pca_historico_anos`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_ano` (`ano`),
  ADD KEY `idx_numero_dfd` (`numero_dfd`),
  ADD KEY `idx_numero_contratacao` (`numero_contratacao`),
  ADD KEY `idx_area_requisitante` (`area_requisitante`),
  ADD KEY `idx_situacao_execucao` (`situacao_execucao`),
  ADD KEY `idx_categoria_contratacao` (`categoria_contratacao`),
  ADD KEY `idx_pca_historico_anos_ano_dfd` (`ano`,`numero_dfd`);

--
-- Índices de tabela `pca_importacoes`
--
ALTER TABLE `pca_importacoes`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_pca_importacoes_usuario` (`usuario_id`),
  ADD KEY `idx_ano_pca` (`ano_pca`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_criado_em` (`criado_em`),
  ADD KEY `idx_pca_importacoes_status` (`status`),
  ADD KEY `idx_pca_importacoes_ano` (`ano_pca`),
  ADD KEY `idx_pca_importacoes_data` (`criado_em`);

--
-- Índices de tabela `pca_riscos`
--
ALTER TABLE `pca_riscos`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_numero_dfd` (`numero_dfd`),
  ADD KEY `idx_mes_relatorio` (`mes_relatorio`),
  ADD KEY `idx_nivel_risco` (`nivel_risco`),
  ADD KEY `idx_status_acao` (`status_acao`),
  ADD KEY `idx_pca_riscos_dfd` (`numero_dfd`),
  ADD KEY `idx_pca_riscos_nivel` (`nivel_risco`),
  ADD KEY `idx_pca_riscos_mes` (`mes_relatorio`);

--
-- Índices de tabela `usuarios`
--
ALTER TABLE `usuarios`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`),
  ADD KEY `idx_usuarios_ativo` (`ativo`),
  ADD KEY `idx_usuarios_tipo` (`tipo_usuario`),
  ADD KEY `idx_usuarios_nivel` (`nivel_acesso`),
  ADD KEY `idx_usuarios_departamento` (`departamento`);

--
-- AUTO_INCREMENT para tabelas despejadas
--

--
-- AUTO_INCREMENT de tabela `backups_sistema`
--
ALTER TABLE `backups_sistema`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `licitacoes`
--
ALTER TABLE `licitacoes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `logs_sistema`
--
ALTER TABLE `logs_sistema`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `pca_2022`
--
ALTER TABLE `pca_2022`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `pca_2023`
--
ALTER TABLE `pca_2023`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `pca_2024`
--
ALTER TABLE `pca_2024`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `pca_dados`
--
ALTER TABLE `pca_dados`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `pca_estados_tempo`
--
ALTER TABLE `pca_estados_tempo`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `pca_historico`
--
ALTER TABLE `pca_historico`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `pca_historico_anos`
--
ALTER TABLE `pca_historico_anos`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `pca_importacoes`
--
ALTER TABLE `pca_importacoes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `pca_riscos`
--
ALTER TABLE `pca_riscos`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `usuarios`
--
ALTER TABLE `usuarios`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- Restrições para tabelas despejadas
--

--
-- Restrições para tabelas `licitacoes`
--
ALTER TABLE `licitacoes`
  ADD CONSTRAINT `fk_licitacoes_pca_dados` FOREIGN KEY (`pca_dados_id`) REFERENCES `pca_dados` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_licitacoes_usuario` FOREIGN KEY (`usuario_criador`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_licitacoes_usuario_id` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL;

--
-- Restrições para tabelas `logs_sistema`
--
ALTER TABLE `logs_sistema`
  ADD CONSTRAINT `fk_logs_usuario` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL;

--
-- Restrições para tabelas `pca_dados`
--
ALTER TABLE `pca_dados`
  ADD CONSTRAINT `fk_pca_dados_importacao` FOREIGN KEY (`importacao_id`) REFERENCES `pca_importacoes` (`id`) ON DELETE CASCADE;

--
-- Restrições para tabelas `pca_historico`
--
ALTER TABLE `pca_historico`
  ADD CONSTRAINT `fk_pca_historico_importacao` FOREIGN KEY (`importacao_id`) REFERENCES `pca_importacoes` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_pca_historico_usuario` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE;

--
-- Restrições para tabelas `pca_importacoes`
--
ALTER TABLE `pca_importacoes`
  ADD CONSTRAINT `fk_pca_importacoes_usuario` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
