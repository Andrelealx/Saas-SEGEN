-- phpMyAdmin SQL Dump
-- version 5.2.2
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1:3306
-- Tempo de geração: 02/03/2026 às 16:49
-- Versão do servidor: 11.8.3-MariaDB-log
-- Versão do PHP: 7.2.34

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Banco de dados: `u305836601_SEGEN`
--

-- --------------------------------------------------------

--
-- Estrutura para tabela `audit_logs`
--

CREATE TABLE `audit_logs` (
  `id` bigint(20) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `action` varchar(80) NOT NULL,
  `entity` varchar(80) NOT NULL,
  `entity_id` varchar(60) DEFAULT NULL,
  `payload` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`payload`)),
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

--
-- Despejando dados para a tabela `audit_logs`
--

INSERT INTO `audit_logs` (`id`, `user_id`, `action`, `entity`, `entity_id`, `payload`, `created_at`) VALUES
(1, 1, 'CREATE', 'employees', '1', '{\"note\": \"seed employee created\"}', '2025-10-16 02:01:45'),
(2, 3, 'APPROVE', 'overtime_requests', '1', '{\"note\": \"sdr aprovada via seed\"}', '2025-10-16 02:01:45'),
(3, 2, 'PUBLISH', 'schedule_days', '1', '{\"note\": \"escala publicada\"}', '2025-10-16 02:01:45');

-- --------------------------------------------------------

--
-- Estrutura para tabela `departments`
--

CREATE TABLE `departments` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `acronym` varchar(20) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

--
-- Despejando dados para a tabela `departments`
--

INSERT INTO `departments` (`id`, `name`, `acronym`) VALUES
(1, 'Guarnição Central', 'GC'),
(2, 'Posto Norte', 'PN'),
(3, 'Posto Sul', 'PS'),
(4, 'Administração', 'ADM');

-- --------------------------------------------------------

--
-- Estrutura para tabela `doc_templates`
--

CREATE TABLE `doc_templates` (
  `id` int(11) NOT NULL,
  `code` varchar(50) NOT NULL,
  `title` varchar(120) NOT NULL,
  `scope` enum('POST','GLOBAL') NOT NULL DEFAULT 'POST',
  `post_id` int(11) DEFAULT NULL,
  `content` mediumtext NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Despejando dados para a tabela `doc_templates`
--

INSERT INTO `doc_templates` (`id`, `code`, `title`, `scope`, `post_id`, `content`, `is_active`, `updated_at`, `created_at`) VALUES
(1, 'ROTINA_ALPHA', 'Rotina Alpha - Fixo', 'POST', NULL, '<h1>ROTINA {{POSTO}}</h1><p>Data: {{DATA}}</p>{{EQUIPE}}<hr><p>{{OBS_DO_DIA}}</p>', 1, '2025-12-17 23:22:50', '2025-12-17 23:22:50');

-- --------------------------------------------------------

--
-- Estrutura para tabela `employees`
--

CREATE TABLE `employees` (
  `id` int(11) NOT NULL,
  `registration` varchar(40) NOT NULL,
  `name` varchar(150) NOT NULL,
  `cpf` varchar(14) DEFAULT NULL,
  `birth_date` date DEFAULT NULL,
  `email` varchar(160) DEFAULT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `hire_date` date DEFAULT NULL,
  `department_id` int(11) DEFAULT NULL,
  `position_id` int(11) DEFAULT NULL,
  `base_shift` enum('12x36','24x72') NOT NULL DEFAULT '12x36',
  `status` enum('ATIVO','AFASTADO','LICENCA','FERIAS','INATIVO') DEFAULT 'ATIVO',
  `notes` text DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL,
  `photo` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

--
-- Despejando dados para a tabela `employees`
--

INSERT INTO `employees` (`id`, `registration`, `name`, `cpf`, `birth_date`, `email`, `phone`, `hire_date`, `department_id`, `position_id`, `base_shift`, `status`, `notes`, `created_at`, `updated_at`, `photo`) VALUES
(1, 'M-1001', 'José Almeida', '123.456.789-01', '1986-05-12', 'jose.almeida@example.com', '(21)99911-0001', '2015-03-01', 1, 3, '12x36', 'ATIVO', 'Único dono; curso de primeiros socorros', '2025-10-16 02:01:45', '2025-12-17 22:54:27', NULL),
(2, 'M-1002', 'Carlos Pereira', '234.567.890-02', '1988-11-20', 'carlos.pereira@example.com', '(21)99911-0002', '2016-07-15', 1, 4, '12x36', 'ATIVO', 'Motorista - CNH D', '2025-10-16 02:01:45', '2025-12-17 22:54:27', NULL),
(3, 'M-1003', 'Mariana Costa', '345.678.901-03', '1992-02-02', 'mariana.costa@example.com', '(21)99911-0003', '2018-01-10', 2, 3, '24x72', 'ATIVO', 'Treinamento em andamento', '2025-10-16 02:01:45', '2025-12-17 22:54:27', NULL),
(4, 'M-1004', 'Rafael Souza', '456.789.012-04', '1990-09-09', 'rafael.souza@example.com', '(21)99911-0004', '2017-04-22', 2, 1, '12x36', 'ATIVO', 'Chefe de posto - turno diurno', '2025-10-16 02:01:45', '2025-12-17 22:54:27', NULL),
(5, 'M-1005', 'Paula Lima', '567.890.123-05', '1995-12-01', 'paula.lima@example.com', '(21)99911-0005', '2019-06-05', 3, 3, '12x36', 'ATIVO', 'Atua em escola municipal', '2025-10-16 02:01:45', '2025-12-17 22:54:27', NULL),
(6, 'M-1006', 'Bruno Alves', '678.901.234-06', '1984-03-30', 'bruno.alves@example.com', '(21)99911-0006', '2014-09-12', 3, 2, '12x36', 'ATIVO', 'Supervisor de área', '2025-10-16 02:01:45', '2025-12-17 22:54:27', NULL),
(7, 'M-1007', 'Ana Rodrigues', '789.012.345-07', '1993-07-07', 'ana.rodrigues@example.com', '(21)99911-0007', '2020-02-20', NULL, NULL, '12x36', 'ATIVO', 'Auxiliar administrativo', '2025-10-16 02:01:45', '2025-12-17 19:55:31', '/storage/employees/employee_7_1766013195_cdbd4c57.webp'),
(8, 'M-1008', 'Felipe Gomes', '890.123.456-08', '1991-10-10', 'felipe.gomes@example.com', '(21)99911-0008', '2016-11-11', 1, 3, '12x36', 'ATIVO', 'Responsável por ronda noturna', '2025-10-16 02:01:45', '2025-12-17 22:54:27', NULL),
(9, 'M-1009', 'Lucas Martins', '901.234.567-09', '1994-06-06', 'lucas.martins@example.com', '(21)99911-0009', '2018-05-05', 2, 3, '24x72', 'ATIVO', 'Designado para eventos', '2025-10-16 02:01:45', '2025-12-17 22:54:27', NULL),
(10, 'M-1010', 'Sofia Nunes', '012.345.678-10', '1996-08-08', 'sofia.nunes@example.com', '(21)99911-0010', '2021-03-03', 3, 3, '12x36', 'ATIVO', 'Recente contratação', '2025-10-16 02:01:45', '2025-12-17 22:54:27', NULL),
(11, '12094020', 'André lucas Peterson Leal', '16156032711', NULL, 'a.peterson.leal@outlook.com', '21988798777', NULL, 4, 3, '12x36', 'ATIVO', '', '2026-01-08 08:43:29', NULL, NULL);

-- --------------------------------------------------------

--
-- Estrutura para tabela `groups`
--

CREATE TABLE `groups` (
  `id` int(11) NOT NULL,
  `name` varchar(80) NOT NULL,
  `description` varchar(200) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

--
-- Despejando dados para a tabela `groups`
--

INSERT INTO `groups` (`id`, `name`, `description`) VALUES
(1, 'Alpha', 'Equipe fixa para operações na área central'),
(2, 'Escala Escolar', 'Equipe designada para unidades escolares'),
(3, 'bravo', NULL),
(4, 'DELTA', 'DDE'),
(5, 'charlie', NULL);

-- --------------------------------------------------------

--
-- Estrutura para tabela `group_fixed_rules`
--

CREATE TABLE `group_fixed_rules` (
  `id` int(11) NOT NULL,
  `group_id` int(11) NOT NULL,
  `weekday` tinyint(4) NOT NULL,
  `post_id` int(11) DEFAULT NULL,
  `shift_id` int(11) DEFAULT NULL,
  `doc_template_id` int(11) DEFAULT NULL,
  `notes` varchar(200) DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `group_members`
--

CREATE TABLE `group_members` (
  `id` int(11) NOT NULL,
  `group_id` int(11) NOT NULL,
  `employee_id` int(11) NOT NULL,
  `role_in_group` varchar(60) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

--
-- Despejando dados para a tabela `group_members`
--

INSERT INTO `group_members` (`id`, `group_id`, `employee_id`, `role_in_group`) VALUES
(4, 2, 5, 'Agente'),
(5, 2, 3, 'Agente'),
(6, 1, 8, NULL),
(7, 1, 1, NULL),
(8, 1, 4, NULL),
(30, 3, 2, NULL),
(31, 3, 1, NULL),
(32, 3, 3, NULL),
(36, 4, 7, NULL),
(37, 4, 8, NULL),
(38, 4, 3, NULL),
(39, 5, 6, NULL),
(40, 5, 2, NULL),
(41, 5, 9, NULL),
(42, 5, 10, NULL);

-- --------------------------------------------------------

--
-- Estrutura para tabela `leaves`
--

CREATE TABLE `leaves` (
  `id` int(11) NOT NULL,
  `employee_id` int(11) NOT NULL,
  `type` enum('FERIAS','ABONO','ATESTADO','LICENCA') NOT NULL,
  `start_date` date NOT NULL,
  `end_date` date NOT NULL,
  `status` enum('PENDENTE','APROVADO','NEGADO') DEFAULT 'PENDENTE',
  `created_by` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

--
-- Despejando dados para a tabela `leaves`
--

INSERT INTO `leaves` (`id`, `employee_id`, `type`, `start_date`, `end_date`, `status`, `created_by`) VALUES
(1, 6, 'FERIAS', '2025-10-26', '2025-11-05', 'APROVADO', 3),
(2, 10, 'ATESTADO', '2025-10-13', '2025-10-17', 'APROVADO', 3);

-- --------------------------------------------------------

--
-- Estrutura para tabela `occurrences`
--

CREATE TABLE `occurrences` (
  `id` bigint(20) NOT NULL,
  `protocol` varchar(30) NOT NULL,
  `sector` varchar(50) NOT NULL,
  `occurred_at` datetime NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  `status` enum('draft','registered','closed','canceled') NOT NULL DEFAULT 'registered',
  `location` varchar(180) NOT NULL,
  `reference_point` varchar(180) DEFAULT NULL,
  `nature` varchar(80) NOT NULL,
  `involved` text DEFAULT NULL,
  `agencies` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`agencies`)),
  `description` mediumtext NOT NULL,
  `actions_taken` mediumtext DEFAULT NULL,
  `observations` text DEFAULT NULL,
  `vehicle_prefix` varchar(30) DEFAULT NULL,
  `vehicle_plate` varchar(20) DEFAULT NULL,
  `km_start` int(11) DEFAULT NULL,
  `km_end` int(11) DEFAULT NULL,
  `created_by` bigint(20) NOT NULL,
  `closed_by` bigint(20) DEFAULT NULL,
  `closed_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

--
-- Despejando dados para a tabela `occurrences`
--

INSERT INTO `occurrences` (`id`, `protocol`, `sector`, `occurred_at`, `created_at`, `updated_at`, `status`, `location`, `reference_point`, `nature`, `involved`, `agencies`, `description`, `actions_taken`, `observations`, `vehicle_prefix`, `vehicle_plate`, `km_start`, `km_end`, `created_by`, `closed_by`, `closed_at`) VALUES
(1, 'BRAVO-2025-000124', 'BRAVO', '2025-12-17 09:15:00', '2025-12-17 13:34:36', NULL, 'registered', 'Av. Dedo de Deus, Centro - Guapimirim/RJ', 'Próximo à praça central', 'Averiguação', 'Solicitante: morador (nome não informado).', '[\"PM\"]', 'Guarnição deslocou ao local após solicitação via telefone informando possível tentativa de arrombamento. Realizada varredura no perímetro, contato com o solicitante e orientação de segurança. Não foi constatado dano aparente no momento da chegada.', 'Realizada averiguação, orientações ao solicitante e registro para acompanhamento.', 'Sem alteração no serviço. Área ficou sob monitoramento.', 'VTR-02', 'ABC1D23', 15420, 15435, 1, NULL, NULL),
(3, 'BRAVO-2025-000125', 'BRAVO', '2025-12-17 09:25:00', '2025-12-17 13:37:53', NULL, 'registered', 'Av. Dedo de Deus, Centro - Guapimirim/RJ', 'Próximo à praça central', 'Averiguação', 'Solicitante: morador (nome não informado).', '[\"PM\"]', 'Teste: ocorrência inserida via SQL para validar listagem/visualização.', 'Providências de teste.', 'Observação de teste.', 'VTR-02', 'ABC1D23', 15436, 15440, 1, NULL, NULL),
(4, 'CHARLIE-2026-000001', 'CHARLIE', '2026-01-08 08:39:00', '2026-01-08 08:41:02', NULL, 'registered', 'rua saturnino rocha, 267', 'atras do campo de modelo', 'averiguacao', 'andré lucas peterson leal', '[\"PM\",\"Outro\"]', 'o individuo estava fazendo uso de php e javascript', 'bloqueio imediato de acesso a qualquer console log', NULL, NULL, NULL, NULL, NULL, 5, NULL, NULL);

-- --------------------------------------------------------

--
-- Estrutura para tabela `occurrence_attachments`
--

CREATE TABLE `occurrence_attachments` (
  `id` bigint(20) NOT NULL,
  `occurrence_id` bigint(20) NOT NULL,
  `original_name` varchar(255) NOT NULL,
  `stored_name` varchar(255) NOT NULL,
  `mime` varchar(100) NOT NULL,
  `size_bytes` bigint(20) NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `created_by` bigint(20) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

--
-- Despejando dados para a tabela `occurrence_attachments`
--

INSERT INTO `occurrence_attachments` (`id`, `occurrence_id`, `original_name`, `stored_name`, `mime`, `size_bytes`, `created_at`, `created_by`) VALUES
(1, 3, 'foto_teste.jpg', 'foto_teste_exemplo.jpg', 'image/jpeg', 120000, '2025-12-17 13:37:53', 1);

-- --------------------------------------------------------

--
-- Estrutura para tabela `occurrence_audit`
--

CREATE TABLE `occurrence_audit` (
  `id` bigint(20) NOT NULL,
  `occurrence_id` bigint(20) NOT NULL,
  `action` varchar(60) NOT NULL,
  `meta` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`meta`)),
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `created_by` bigint(20) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

--
-- Despejando dados para a tabela `occurrence_audit`
--

INSERT INTO `occurrence_audit` (`id`, `occurrence_id`, `action`, `meta`, `created_at`, `created_by`) VALUES
(1, 3, 'created', '{\"seed\": \"sql_example_2\"}', '2025-12-17 13:37:53', 1),
(2, 4, 'created', '{\"protocol\":\"CHARLIE-2026-000001\"}', '2026-01-08 08:41:02', 5);

-- --------------------------------------------------------

--
-- Estrutura para tabela `occurrence_sequences`
--

CREATE TABLE `occurrence_sequences` (
  `id` int(11) NOT NULL,
  `year` int(11) NOT NULL,
  `sector` varchar(50) NOT NULL,
  `last_number` int(11) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

--
-- Despejando dados para a tabela `occurrence_sequences`
--

INSERT INTO `occurrence_sequences` (`id`, `year`, `sector`, `last_number`) VALUES
(1, 2025, 'BRAVO', 123),
(3, 2026, 'CHARLIE', 1);

-- --------------------------------------------------------

--
-- Estrutura para tabela `overtime_requests`
--

CREATE TABLE `overtime_requests` (
  `id` int(11) NOT NULL,
  `employee_id` int(11) NOT NULL,
  `ref_date` date NOT NULL,
  `start_time` time NOT NULL,
  `end_time` time NOT NULL,
  `hours` decimal(5,2) NOT NULL,
  `reason` varchar(200) DEFAULT NULL,
  `status` enum('PENDENTE','APROVADO','NEGADO','LANÇADO') DEFAULT 'PENDENTE',
  `requested_by` int(11) DEFAULT NULL,
  `approved_by` int(11) DEFAULT NULL,
  `approved_at` datetime DEFAULT NULL,
  `approval_note` text DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

--
-- Despejando dados para a tabela `overtime_requests`
--

INSERT INTO `overtime_requests` (`id`, `employee_id`, `ref_date`, `start_time`, `end_time`, `hours`, `reason`, `status`, `requested_by`, `approved_by`, `approved_at`, `approval_note`, `created_at`) VALUES
(1, 1, '2025-10-15', '18:00:00', '21:00:00', 3.00, 'Apoio operação evento', 'LANÇADO', 2, 1, '2025-10-15 23:21:44', NULL, '2025-10-16 02:01:45'),
(2, 8, '2025-10-16', '20:00:00', '23:30:00', 3.50, 'Ronda extraordinária', 'PENDENTE', 5, NULL, NULL, NULL, '2025-10-16 02:01:45'),
(3, 3, '2025-10-18', '06:00:00', '09:00:00', 3.00, 'Cobertura faltas', 'PENDENTE', 3, NULL, NULL, NULL, '2025-10-16 02:01:45'),
(4, 8, '2025-11-30', '13:00:00', '16:00:00', 3.00, 'NATAL', 'APROVADO', 5, 5, '2025-12-07 04:45:22', NULL, '2025-11-28 14:37:06'),
(5, 7, '2025-12-11', '15:00:00', '18:00:00', 3.00, 'guarda', 'APROVADO', 5, 5, '2025-12-07 04:55:28', NULL, '2025-12-07 07:54:21'),
(6, 1, '2025-12-08', '12:00:00', '15:00:00', 3.00, NULL, 'APROVADO', 5, 5, '2025-12-07 05:14:42', NULL, '2025-12-07 05:13:29'),
(12, 9, '2025-12-11', '15:00:00', '18:00:00', 3.00, 'guarda', 'PENDENTE', 5, NULL, NULL, NULL, '2025-12-07 05:23:56'),
(13, 3, '2025-12-11', '12:00:00', '15:00:00', 3.00, 'sdr normal', 'APROVADO', 5, 5, '2025-12-07 15:16:12', 'confirmado pelo nilmar', '2025-12-07 15:15:45'),
(14, 11, '2026-01-08', '12:00:00', '12:00:00', 24.00, NULL, 'PENDENTE', 5, NULL, NULL, NULL, '2026-01-08 08:48:47'),
(15, 11, '2026-01-22', '12:00:00', '12:00:00', 24.00, 'reforço', 'APROVADO', 5, 5, '2026-01-08 14:07:43', 'confirmado pelo Nilmar', '2026-01-08 14:07:25'),
(16, 2, '2026-01-21', '12:00:00', '12:00:00', 24.00, NULL, 'PENDENTE', 5, NULL, NULL, NULL, '2026-01-08 17:12:08');

-- --------------------------------------------------------

--
-- Estrutura para tabela `permissions`
--

CREATE TABLE `permissions` (
  `id` int(11) NOT NULL,
  `code` varchar(80) NOT NULL,
  `name` varchar(80) NOT NULL,
  `description` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

--
-- Despejando dados para a tabela `permissions`
--

INSERT INTO `permissions` (`id`, `code`, `name`, `description`) VALUES
(1, 'employees.view', 'Ver Funcionários', NULL),
(2, 'employees.create', 'Criar Funcionário', NULL),
(3, 'employees.edit', 'Editar Funcionário', NULL),
(4, 'overtime.view', 'Ver SDR', NULL),
(5, 'overtime.create', 'Criar SDR', NULL),
(6, 'overtime.edit', 'Editar SDR', NULL),
(7, 'scales.view', 'Ver Escalas', NULL),
(8, 'scales.edit', 'Editar Escalas', NULL),
(9, 'rbac.manage', 'Gerenciar Acessos (RBAC)', NULL),
(10, 'employees.delete', '', 'Excluir funcionário'),
(11, 'employees.import', '', 'Importar CSV funcionários'),
(12, 'employees.export', '', 'Exportar CSV funcionários'),
(13, 'overtime.delete', '', 'Excluir SDR'),
(14, 'overtime.approve', '', 'Aprovar/Negar/Lançar SDR'),
(15, 'overtime.duplicate', '', 'Duplicar SDR'),
(23, 'access.users.manage', '', 'Gerenciar usuários do sistema');

-- --------------------------------------------------------

--
-- Estrutura para tabela `positions`
--

CREATE TABLE `positions` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

--
-- Despejando dados para a tabela `positions`
--

INSERT INTO `positions` (`id`, `name`) VALUES
(1, 'Chefe de Posto'),
(2, 'Supervisor de Área'),
(3, 'Agente de Segurança'),
(4, 'Motorista'),
(5, 'Auxiliar Administrativo');

-- --------------------------------------------------------

--
-- Estrutura para tabela `posts`
--

CREATE TABLE `posts` (
  `id` int(11) NOT NULL,
  `name` varchar(120) NOT NULL,
  `location` varchar(200) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

--
-- Despejando dados para a tabela `posts`
--

INSERT INTO `posts` (`id`, `name`, `location`) VALUES
(1, 'Praça Central', 'Praça Central - Centro'),
(2, 'Terminal Rodoviário', 'Av. Principal, s/n'),
(3, 'Unidade Escolar - Norte', 'Rua das Flores, 123');

-- --------------------------------------------------------

--
-- Estrutura para tabela `roles`
--

CREATE TABLE `roles` (
  `id` int(11) NOT NULL,
  `name` varchar(50) NOT NULL,
  `description` varchar(255) DEFAULT NULL,
  `label` varchar(80) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

--
-- Despejando dados para a tabela `roles`
--

INSERT INTO `roles` (`id`, `name`, `description`, `label`) VALUES
(1, 'admin', NULL, 'Administrador'),
(2, 'rh', NULL, 'RH'),
(3, 'comando', NULL, 'Comando'),
(4, 'supervisor', NULL, 'Supervisor'),
(5, 'agente', NULL, 'Agente'),
(6, 'SUPERADMIN', 'Acesso total', ''),
(7, 'GUARDA_CADASTRO', 'Acesso limitado para cadastro/consulta', '');

-- --------------------------------------------------------

--
-- Estrutura para tabela `role_permissions`
--

CREATE TABLE `role_permissions` (
  `role_id` int(11) NOT NULL,
  `permission_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

--
-- Despejando dados para a tabela `role_permissions`
--

INSERT INTO `role_permissions` (`role_id`, `permission_id`) VALUES
(4, 1),
(6, 1),
(7, 1),
(4, 2),
(6, 2),
(7, 2),
(4, 3),
(6, 3),
(7, 3),
(4, 4),
(6, 4),
(7, 4),
(4, 5),
(6, 5),
(7, 5),
(4, 6),
(6, 6),
(4, 7),
(6, 7),
(4, 8),
(6, 8),
(4, 9),
(6, 9),
(4, 10),
(6, 10),
(4, 11),
(6, 11),
(4, 12),
(6, 12),
(4, 13),
(6, 13),
(4, 14),
(6, 14),
(4, 15),
(6, 15),
(4, 23),
(6, 23);

-- --------------------------------------------------------

--
-- Estrutura para tabela `schedule_days`
--

CREATE TABLE `schedule_days` (
  `id` int(11) NOT NULL,
  `ref_date` date NOT NULL,
  `post_id` int(11) DEFAULT NULL,
  `notes` varchar(200) DEFAULT NULL,
  `published` tinyint(1) DEFAULT 0,
  `doc_template_id` int(11) DEFAULT NULL,
  `doc_notes` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

--
-- Despejando dados para a tabela `schedule_days`
--

INSERT INTO `schedule_days` (`id`, `ref_date`, `post_id`, `notes`, `published`, `doc_template_id`, `doc_notes`) VALUES
(9, '2026-01-08', NULL, NULL, 1, NULL, 'OBSERVAÇÕES:\r\n 	\r\n1.	Durante as rondas em que a viatura ficar parada, sempre haver pelo menos um GCM desembarcado da viatura, em caso de tempo chuvoso, se possível buscar abrigo para evitar que ambos GCMs fiquem embarcados na viatura.\r\n2.	    Treinamento do cão e limpeza das instalações das 09:00h às 12:00h e 17:00h às 19:00h.\r\n3.	    O GCM 2ª CLASSE 126233-11 PASSOS, trocou seu serviço de hoje para o dia 03/01/2026 com o GCM 3ª CLASSE 113336-11 AFONSO.\r\n4.	    Evento: 17:30h às 01:00h - Fantástico Natal de Guapi 2025 - Praça da Cotia.\r\n5.	    Conselho Tutelar: 08:00h às 17:00h - Permanência de um GCM no local de segunda a sexta-feira.\r\n6.	    A escala poderá sofrer alterações mediante informações posteriores do DP da SETRAN e/ou outros.'),
(10, '2026-01-08', 1, NULL, 0, NULL, NULL),
(11, '2026-01-08', NULL, 'plantao praca', 1, NULL, 'rotina padrao');

-- --------------------------------------------------------

--
-- Estrutura para tabela `shifts`
--

CREATE TABLE `shifts` (
  `id` int(11) NOT NULL,
  `code` varchar(20) NOT NULL,
  `start_time` time NOT NULL,
  `end_time` time NOT NULL,
  `hours` decimal(5,2) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

--
-- Despejando dados para a tabela `shifts`
--

INSERT INTO `shifts` (`id`, `code`, `start_time`, `end_time`, `hours`) VALUES
(5, '12H-D', '07:00:00', '19:00:00', 12.00),
(6, '12H-N', '19:00:00', '07:00:00', 12.00),
(7, '24H', '07:00:00', '07:00:00', 24.00);

-- --------------------------------------------------------

--
-- Estrutura para tabela `shift_assignments`
--

CREATE TABLE `shift_assignments` (
  `id` int(11) NOT NULL,
  `schedule_day_id` int(11) NOT NULL,
  `employee_id` int(11) NOT NULL,
  `shift_id` int(11) NOT NULL,
  `origin` enum('ESCALA','TROCA','COBERTURA','AJUSTE') DEFAULT 'ESCALA',
  `created_by` int(11) DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

--
-- Despejando dados para a tabela `shift_assignments`
--

INSERT INTO `shift_assignments` (`id`, `schedule_day_id`, `employee_id`, `shift_id`, `origin`, `created_by`, `created_at`) VALUES
(28, 10, 7, 5, 'ESCALA', 5, '2026-01-08 09:34:06'),
(29, 11, 11, 5, 'ESCALA', 5, '2026-01-08 15:05:41'),
(30, 11, 6, 5, 'ESCALA', 5, '2026-01-08 15:05:41'),
(31, 11, 1, 6, 'ESCALA', 5, '2026-01-08 15:05:41'),
(32, 9, 7, 5, 'ESCALA', 5, '2026-01-08 15:06:36');

-- --------------------------------------------------------

--
-- Estrutura para tabela `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `name` varchar(120) NOT NULL,
  `email` varchar(160) NOT NULL,
  `username` varchar(80) DEFAULT NULL,
  `password_hash` varchar(255) NOT NULL,
  `role_id` int(11) NOT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

--
-- Despejando dados para a tabela `users`
--

INSERT INTO `users` (`id`, `name`, `email`, `username`, `password_hash`, `role_id`, `is_active`, `created_at`) VALUES
(1, 'Administrador', 'admin@segen.gov.br', 'admin', '$2y$10$8N3n4M3b7b9fLq1R8m3s0O1b4m7l6q7yH2JHq7iZlQzVtWf8j6b3a', 1, 1, '2025-10-16 02:01:45'),
(2, 'Supervisor João', 'joao.silva@segen.gov.br', 'joao.silva', '$2y$10$8N3n4M3b7b9fLq1R8m3s0O1b4m7l6q7yH2JHq7iZlQzVtWf8j6b3a', 4, 1, '2025-10-16 02:01:45'),
(3, 'RH Maria', 'maria.rh@segen.gov.br', 'maria.rh', '$2y$10$8N3n4M3b7b9fLq1R8m3s0O1b4m7l6q7yH2JHq7iZlQzVtWf8j6b3a', 2, 1, '2025-10-16 02:01:45'),
(4, 'Comando Carlos', 'carlos.comando@segen.gov.br', 'carlos.comando', '$2y$10$8N3n4M3b7b9fLq1R8m3s0O1b4m7l6q7yH2JHq7iZlQzVtWf8j6b3a', 3, 1, '2025-10-16 02:01:45'),
(5, 'Lealx', 'lealx@segen.gov.br', 'lealx', '$2b$12$0oihJ4asHn8u4GEGPJXbkOuP6HD9FQtmBbRPNNj9xIhWbpHnjJCWq', 6, 1, '2025-10-16 02:01:45'),
(6, 'teste', 'teste@segen.gov.br', 'teste', '$2y$10$PuvLIEcOu1IWb12v2yE8L.11OVYfnRg2T3I9lcWLz2vLTNXRrtEcG', 6, 1, '2025-11-27 14:15:12'),
(7, 'Teste Login', 'login@teste.com', 'login', '$2y$10$RLsaKFucafIrCKyaeyjlh.T/SFkV51tmqFhxBiR6keebuOMnvLV3u', 1, 1, '2025-11-27 14:16:47');

-- --------------------------------------------------------

--
-- Estrutura para tabela `user_roles`
--

CREATE TABLE `user_roles` (
  `user_id` int(11) NOT NULL,
  `role_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

--
-- Despejando dados para a tabela `user_roles`
--

INSERT INTO `user_roles` (`user_id`, `role_id`) VALUES
(6, 4),
(7, 5),
(5, 6);

--
-- Índices para tabelas despejadas
--

--
-- Índices de tabela `audit_logs`
--
ALTER TABLE `audit_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Índices de tabela `departments`
--
ALTER TABLE `departments`
  ADD PRIMARY KEY (`id`);

--
-- Índices de tabela `doc_templates`
--
ALTER TABLE `doc_templates`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `code` (`code`),
  ADD KEY `post_id` (`post_id`);

--
-- Índices de tabela `employees`
--
ALTER TABLE `employees`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `registration` (`registration`),
  ADD UNIQUE KEY `cpf` (`cpf`),
  ADD KEY `department_id` (`department_id`),
  ADD KEY `position_id` (`position_id`);

--
-- Índices de tabela `groups`
--
ALTER TABLE `groups`
  ADD PRIMARY KEY (`id`);

--
-- Índices de tabela `group_fixed_rules`
--
ALTER TABLE `group_fixed_rules`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_rule` (`group_id`,`weekday`,`post_id`),
  ADD KEY `fk_gfr_post` (`post_id`),
  ADD KEY `fk_gfr_shift` (`shift_id`),
  ADD KEY `fk_gfr_tpl` (`doc_template_id`);

--
-- Índices de tabela `group_members`
--
ALTER TABLE `group_members`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_group_member` (`group_id`,`employee_id`),
  ADD KEY `employee_id` (`employee_id`);

--
-- Índices de tabela `leaves`
--
ALTER TABLE `leaves`
  ADD PRIMARY KEY (`id`),
  ADD KEY `employee_id` (`employee_id`),
  ADD KEY `created_by` (`created_by`);

--
-- Índices de tabela `occurrences`
--
ALTER TABLE `occurrences`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `protocol` (`protocol`),
  ADD KEY `idx_sector_date` (`sector`,`occurred_at`);
ALTER TABLE `occurrences` ADD FULLTEXT KEY `ft_desc` (`location`,`nature`,`description`,`involved`);

--
-- Índices de tabela `occurrence_attachments`
--
ALTER TABLE `occurrence_attachments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_occ` (`occurrence_id`);

--
-- Índices de tabela `occurrence_audit`
--
ALTER TABLE `occurrence_audit`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_audit` (`occurrence_id`,`created_at`);

--
-- Índices de tabela `occurrence_sequences`
--
ALTER TABLE `occurrence_sequences`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_year_sector` (`year`,`sector`);

--
-- Índices de tabela `overtime_requests`
--
ALTER TABLE `overtime_requests`
  ADD PRIMARY KEY (`id`),
  ADD KEY `employee_id` (`employee_id`),
  ADD KEY `requested_by` (`requested_by`),
  ADD KEY `approved_by` (`approved_by`);

--
-- Índices de tabela `permissions`
--
ALTER TABLE `permissions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `code` (`code`);

--
-- Índices de tabela `positions`
--
ALTER TABLE `positions`
  ADD PRIMARY KEY (`id`);

--
-- Índices de tabela `posts`
--
ALTER TABLE `posts`
  ADD PRIMARY KEY (`id`);

--
-- Índices de tabela `roles`
--
ALTER TABLE `roles`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `name` (`name`);

--
-- Índices de tabela `role_permissions`
--
ALTER TABLE `role_permissions`
  ADD PRIMARY KEY (`role_id`,`permission_id`),
  ADD KEY `fk_rp_perm` (`permission_id`);

--
-- Índices de tabela `schedule_days`
--
ALTER TABLE `schedule_days`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_day_post` (`ref_date`,`post_id`),
  ADD UNIQUE KEY `uq_schedule_day` (`ref_date`,`post_id`),
  ADD KEY `post_id` (`post_id`),
  ADD KEY `fk_schedule_doc_tpl` (`doc_template_id`);

--
-- Índices de tabela `shifts`
--
ALTER TABLE `shifts`
  ADD PRIMARY KEY (`id`);

--
-- Índices de tabela `shift_assignments`
--
ALTER TABLE `shift_assignments`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_assign` (`schedule_day_id`,`employee_id`),
  ADD KEY `employee_id` (`employee_id`),
  ADD KEY `shift_id` (`shift_id`),
  ADD KEY `created_by` (`created_by`);

--
-- Índices de tabela `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`),
  ADD UNIQUE KEY `username` (`username`),
  ADD KEY `role_id` (`role_id`);

--
-- Índices de tabela `user_roles`
--
ALTER TABLE `user_roles`
  ADD PRIMARY KEY (`user_id`,`role_id`),
  ADD KEY `fk_ur_role` (`role_id`);

--
-- AUTO_INCREMENT para tabelas despejadas
--

--
-- AUTO_INCREMENT de tabela `audit_logs`
--
ALTER TABLE `audit_logs`
  MODIFY `id` bigint(20) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT de tabela `departments`
--
ALTER TABLE `departments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT de tabela `doc_templates`
--
ALTER TABLE `doc_templates`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT de tabela `employees`
--
ALTER TABLE `employees`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT de tabela `groups`
--
ALTER TABLE `groups`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT de tabela `group_fixed_rules`
--
ALTER TABLE `group_fixed_rules`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `group_members`
--
ALTER TABLE `group_members`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=43;

--
-- AUTO_INCREMENT de tabela `leaves`
--
ALTER TABLE `leaves`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT de tabela `occurrences`
--
ALTER TABLE `occurrences`
  MODIFY `id` bigint(20) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT de tabela `occurrence_attachments`
--
ALTER TABLE `occurrence_attachments`
  MODIFY `id` bigint(20) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT de tabela `occurrence_audit`
--
ALTER TABLE `occurrence_audit`
  MODIFY `id` bigint(20) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT de tabela `occurrence_sequences`
--
ALTER TABLE `occurrence_sequences`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT de tabela `overtime_requests`
--
ALTER TABLE `overtime_requests`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=17;

--
-- AUTO_INCREMENT de tabela `permissions`
--
ALTER TABLE `permissions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=24;

--
-- AUTO_INCREMENT de tabela `positions`
--
ALTER TABLE `positions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT de tabela `posts`
--
ALTER TABLE `posts`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT de tabela `roles`
--
ALTER TABLE `roles`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT de tabela `schedule_days`
--
ALTER TABLE `schedule_days`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT de tabela `shifts`
--
ALTER TABLE `shifts`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT de tabela `shift_assignments`
--
ALTER TABLE `shift_assignments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=33;

--
-- AUTO_INCREMENT de tabela `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- Restrições para tabelas despejadas
--

--
-- Restrições para tabelas `audit_logs`
--
ALTER TABLE `audit_logs`
  ADD CONSTRAINT `audit_logs_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`);

--
-- Restrições para tabelas `doc_templates`
--
ALTER TABLE `doc_templates`
  ADD CONSTRAINT `doc_templates_ibfk_1` FOREIGN KEY (`post_id`) REFERENCES `posts` (`id`) ON DELETE SET NULL;

--
-- Restrições para tabelas `employees`
--
ALTER TABLE `employees`
  ADD CONSTRAINT `employees_ibfk_1` FOREIGN KEY (`department_id`) REFERENCES `departments` (`id`),
  ADD CONSTRAINT `employees_ibfk_2` FOREIGN KEY (`position_id`) REFERENCES `positions` (`id`);

--
-- Restrições para tabelas `group_fixed_rules`
--
ALTER TABLE `group_fixed_rules`
  ADD CONSTRAINT `fk_gfr_group` FOREIGN KEY (`group_id`) REFERENCES `groups` (`id`),
  ADD CONSTRAINT `fk_gfr_post` FOREIGN KEY (`post_id`) REFERENCES `posts` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_gfr_shift` FOREIGN KEY (`shift_id`) REFERENCES `shifts` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_gfr_tpl` FOREIGN KEY (`doc_template_id`) REFERENCES `doc_templates` (`id`) ON DELETE SET NULL;

--
-- Restrições para tabelas `group_members`
--
ALTER TABLE `group_members`
  ADD CONSTRAINT `group_members_ibfk_1` FOREIGN KEY (`group_id`) REFERENCES `groups` (`id`),
  ADD CONSTRAINT `group_members_ibfk_2` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`id`);

--
-- Restrições para tabelas `leaves`
--
ALTER TABLE `leaves`
  ADD CONSTRAINT `leaves_ibfk_1` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`id`),
  ADD CONSTRAINT `leaves_ibfk_2` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`);

--
-- Restrições para tabelas `occurrence_attachments`
--
ALTER TABLE `occurrence_attachments`
  ADD CONSTRAINT `occurrence_attachments_ibfk_1` FOREIGN KEY (`occurrence_id`) REFERENCES `occurrences` (`id`) ON DELETE CASCADE;

--
-- Restrições para tabelas `occurrence_audit`
--
ALTER TABLE `occurrence_audit`
  ADD CONSTRAINT `occurrence_audit_ibfk_1` FOREIGN KEY (`occurrence_id`) REFERENCES `occurrences` (`id`) ON DELETE CASCADE;

--
-- Restrições para tabelas `overtime_requests`
--
ALTER TABLE `overtime_requests`
  ADD CONSTRAINT `overtime_requests_ibfk_1` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`id`),
  ADD CONSTRAINT `overtime_requests_ibfk_2` FOREIGN KEY (`requested_by`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `overtime_requests_ibfk_3` FOREIGN KEY (`approved_by`) REFERENCES `users` (`id`);

--
-- Restrições para tabelas `role_permissions`
--
ALTER TABLE `role_permissions`
  ADD CONSTRAINT `fk_rp_perm` FOREIGN KEY (`permission_id`) REFERENCES `permissions` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_rp_role` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE CASCADE;

--
-- Restrições para tabelas `schedule_days`
--
ALTER TABLE `schedule_days`
  ADD CONSTRAINT `fk_schedule_doc_tpl` FOREIGN KEY (`doc_template_id`) REFERENCES `doc_templates` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `schedule_days_ibfk_1` FOREIGN KEY (`post_id`) REFERENCES `posts` (`id`);

--
-- Restrições para tabelas `shift_assignments`
--
ALTER TABLE `shift_assignments`
  ADD CONSTRAINT `shift_assignments_ibfk_1` FOREIGN KEY (`schedule_day_id`) REFERENCES `schedule_days` (`id`),
  ADD CONSTRAINT `shift_assignments_ibfk_2` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`id`),
  ADD CONSTRAINT `shift_assignments_ibfk_3` FOREIGN KEY (`shift_id`) REFERENCES `shifts` (`id`),
  ADD CONSTRAINT `shift_assignments_ibfk_4` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`);

--
-- Restrições para tabelas `users`
--
ALTER TABLE `users`
  ADD CONSTRAINT `users_ibfk_1` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`);

--
-- Restrições para tabelas `user_roles`
--
ALTER TABLE `user_roles`
  ADD CONSTRAINT `fk_ur_role` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_ur_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
