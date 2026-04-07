-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Apr 06, 2026 at 03:31 PM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.0.30

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `web`
--

-- --------------------------------------------------------

--
-- Table structure for table `activity_log`
--

CREATE TABLE `activity_log` (
  `id` int(11) NOT NULL,
  `type` varchar(20) NOT NULL,
  `message` varchar(255) NOT NULL,
  `created_at` datetime NOT NULL,
  `wallet_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `activity_log`
--

INSERT INTO `activity_log` (`id`, `type`, `message`, `created_at`, `wallet_id`) VALUES
(2, 'success', 'Nouveau portefeuille créé pour test1 (TND)', '2026-04-04 13:34:59', 10),
(3, 'info', 'Nouvel objectif créé : BTC investment (Cible: 11000 TND)', '2026-04-04 13:35:11', 10),
(4, 'success', 'Deposit of 4000.00 TND added to test1\'s wallet.', '2026-04-04 13:35:47', 10),
(5, 'success', 'Deposit of 4000.00 TND added to ayoub1\'s wallet.', '2026-04-04 13:37:35', 8),
(6, 'success', 'Deposit of 2000.00 TND added to test1\'s wallet.', '2026-04-04 13:37:45', 10),
(7, 'success', 'Deposit of 70000.00 TND added to ayoub1\'s wallet.', '2026-04-04 13:37:57', 8),
(8, 'warning', 'Withdrawal of 70000.00 TND made from ayoub1\'s wallet.', '2026-04-04 13:38:11', 8),
(9, 'success', 'Deposit of 200.00 TND added to ayoub1\'s wallet.', '2026-04-04 13:52:16', 8),
(10, 'info', 'Nouvel objectif créé : BTC investment (Cible: 11000 TND)', '2026-04-04 13:52:30', 8),
(11, 'success', 'Deposit of 1000.00 TND added to ayoub1\'s wallet.', '2026-04-04 13:52:41', 8),
(12, 'warning', 'Withdrawal of 11260.00 TND made from ayoub1\'s wallet.', '2026-04-04 13:58:58', 8),
(13, 'success', 'Deposit of 50000.00 TND added to ayoub1\'s wallet.', '2026-04-04 13:59:20', 8),
(14, 'warning', 'Withdrawal of 12960.00 TND made from test1\'s wallet.', '2026-04-04 13:59:39', 10),
(15, 'info', 'Nouvel objectif créé : BTC investment (Cible: 100000 TND)', '2026-04-04 14:09:31', 8),
(16, 'success', 'Deposit of 50000.00 TND added to ayoub1\'s wallet.', '2026-04-04 14:09:55', 8),
(17, 'warning', 'Withdrawal of 50000.00 TND made from ayoub1\'s wallet.', '2026-04-04 14:19:59', 8),
(18, 'warning', 'L\'objectif \"BTC investment\" a été ANNULÉ.', '2026-04-04 14:20:13', 8),
(19, 'info', 'Nouvel objectif créé : BTC investment (Cible: 100000 TND)', '2026-04-05 15:37:28', 8),
(20, 'success', 'Deposit of 50000.00 TND added to ayoub1\'s wallet.', '2026-04-05 15:38:01', 8),
(21, 'info', 'Nouvel objectif créé : BTC investment (Cible: 200000 TND)', '2026-04-05 15:38:24', 8),
(22, 'success', 'Deposit of 50.00 TND added to ayoub1\'s wallet.', '2026-04-05 16:06:08', 8),
(23, 'success', 'Deposit of 50.00 TND added to ayoub1\'s wallet.', '2026-04-05 16:07:25', 8),
(24, 'warning', 'Withdrawal of 100.00 TND made from ayoub1\'s wallet.', '2026-04-05 16:17:22', 8),
(25, 'success', 'Deposit of 20.00 TND added to test1\'s wallet.', '2026-04-05 16:17:56', 10),
(26, 'warning', 'Withdrawal of 30.00 TND made from test1\'s wallet.', '2026-04-05 16:18:06', 10),
(27, 'success', 'Nouveau portefeuille créé pour testttttt (TND)', '2026-04-05 16:30:59', 11);

-- --------------------------------------------------------

--
-- Table structure for table `asset`
--

CREATE TABLE `asset` (
  `id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `symbol` varchar(50) NOT NULL,
  `value` double NOT NULL,
  `type` varchar(100) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `asset`
--

INSERT INTO `asset` (`id`, `name`, `symbol`, `value`, `type`) VALUES
(1, 'Bitcoin', 'BTC', 5000, 'Crypto'),
(2, 'Tesla', 'TSL', 650, 'Stock');

-- --------------------------------------------------------

--
-- Table structure for table `doctrine_migration_versions`
--

CREATE TABLE `doctrine_migration_versions` (
  `version` varchar(191) NOT NULL,
  `executed_at` datetime DEFAULT NULL,
  `execution_time` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `doctrine_migration_versions`
--

INSERT INTO `doctrine_migration_versions` (`version`, `executed_at`, `execution_time`) VALUES
('DoctrineMigrations\\Version20260404105407', '2026-04-04 10:56:32', 116),
('DoctrineMigrations\\Version20260404113041', '2026-04-04 11:30:42', 47),
('DoctrineMigrations\\Version20260406124147', '2026-04-06 12:43:35', 515),
('DoctrineMigrations\\Version20260406125701', '2026-04-06 12:57:15', 150),
('DoctrineMigrations\\Version20260407110000', '2026-04-07 11:00:00', 185);

-- --------------------------------------------------------

--
-- Table structure for table `messenger_messages`
--

CREATE TABLE `messenger_messages` (
  `id` bigint(20) NOT NULL,
  `body` longtext NOT NULL,
  `headers` longtext NOT NULL,
  `queue_name` varchar(190) NOT NULL,
  `created_at` datetime NOT NULL,
  `available_at` datetime NOT NULL,
  `delivered_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `notifications`
--

CREATE TABLE `notifications` (
  `id` int(11) NOT NULL,
  `message` varchar(255) NOT NULL,
  `type` varchar(20) NOT NULL,
  `is_read` tinyint(1) NOT NULL,
  `created_at` datetime NOT NULL,
  `wallet_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `notifications`
--

INSERT INTO `notifications` (`id`, `message`, `type`, `is_read`, `created_at`, `wallet_id`) VALUES
(1, 'Deposit of 50.00 TND added to ayoub1\'s wallet.', 'success', 1, '2026-04-05 16:06:08', 8),
(2, 'Deposit of 50.00 TND added to ayoub1\'s wallet.', 'success', 1, '2026-04-05 16:07:25', 8),
(3, 'Withdrawal of 100.00 TND made from ayoub1\'s wallet.', 'warning', 1, '2026-04-05 16:17:22', 8),
(4, 'Deposit of 20.00 TND added to test1\'s wallet.', 'success', 0, '2026-04-05 16:17:56', 10),
(5, 'Withdrawal of 30.00 TND made from test1\'s wallet.', 'warning', 0, '2026-04-05 16:18:06', 10);

-- --------------------------------------------------------

--
-- Table structure for table `orders`
--

CREATE TABLE `orders` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `quantity` int(11) NOT NULL,
  `price` double NOT NULL,
  `type` varchar(20) NOT NULL,
  `asset_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `orders`
--

INSERT INTO `orders` (`id`, `user_id`, `quantity`, `price`, `type`, `asset_id`) VALUES
(1, 11, 1, 5000, 'Buy', 1);

-- --------------------------------------------------------

--
-- Table structure for table `p2p_contract`
--

CREATE TABLE `p2p_contract` (
  `id` int(11) NOT NULL,
  `creator_id` int(11) NOT NULL,
  `quantity` int(11) NOT NULL,
  `price_per_unit` double NOT NULL,
  `contract_type` varchar(20) NOT NULL,
  `status` varchar(20) NOT NULL,
  `accepted_by` int(11) DEFAULT NULL,
  `created_at` datetime NOT NULL,
  `accepted_at` datetime DEFAULT NULL,
  `completed_at` datetime DEFAULT NULL,
  `asset_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `p2p_contract`
--

INSERT INTO `p2p_contract` (`id`, `creator_id`, `quantity`, `price_per_unit`, `contract_type`, `status`, `accepted_by`, `created_at`, `accepted_at`, `completed_at`, `asset_id`) VALUES
(1, 11, 20, 1500, 'BUY', 'OPEN', NULL, '2026-04-06 12:57:52', NULL, NULL, 1);

-- --------------------------------------------------------

--
-- Table structure for table `portfolio`
--

CREATE TABLE `portfolio` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `total_value` double NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `portfolio`
--

INSERT INTO `portfolio` (`id`, `user_id`, `total_value`) VALUES
(1, 11, 0),
(2, 12, 0);

-- --------------------------------------------------------

--
-- Table structure for table `portfolio_asset`
--

CREATE TABLE `portfolio_asset` (
  `id` int(11) NOT NULL,
  `quantity` int(11) NOT NULL,
  `avg_price` double NOT NULL,
  `portfolio_id` int(11) NOT NULL,
  `asset_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `user_reputation`
--

CREATE TABLE `user_reputation` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `completed_contracts` int(11) NOT NULL,
  `canceled_contracts` int(11) NOT NULL,
  `total_score` int(11) NOT NULL,
  `rating_count` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `user_reputation`
--

INSERT INTO `user_reputation` (`id`, `user_id`, `completed_contracts`, `canceled_contracts`, `total_score`, `rating_count`) VALUES
(1, 11, 1, 2, 4, 4);

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `email` varchar(180) NOT NULL,
  `full_name` varchar(100) NOT NULL,
  `roles` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`roles`)),
  `password` varchar(255) NOT NULL,
  `created_at` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `email`, `full_name`, `roles`, `password`, `created_at`) VALUES
(1, 'admin@nexora.tn', 'Professional Admin', '["ROLE_ADMIN"]', '$2y$10$TDRQLhQCiFfyNvPEoy.x1uxikiA/lCcZhOX1OopHLUsbmiaFrWNoe', '2026-04-07 11:00:00'),
(2, 'ayoub1@nexora.tn', 'Ayoub Investor', '["ROLE_USER"]', '$2y$10$sgOX0m6kgZ8a6f/N6jl4husPfwNJ57yuPd9LfaEUVErREPT54E41y', '2026-04-07 11:00:00'),
(3, 'test1@nexora.tn', 'Test Investor', '["ROLE_USER"]', '$2y$10$fPTODd/c4OmOwOOYCFWjae81kHloVhhUO0qBZRhXc3JqcbUPSHVju', '2026-04-07 11:00:00');

-- --------------------------------------------------------

--
-- Table structure for table `wallets`
--

CREATE TABLE `wallets` (
  `id` int(11) NOT NULL,
  `owner` varchar(100) NOT NULL,
  `balance` decimal(15,2) NOT NULL,
  `created_at` datetime NOT NULL,
  `user_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `wallets`
--

INSERT INTO `wallets` (`id`, `owner`, `balance`, `created_at`, `user_id`) VALUES
(8, 'ayoub1', 100040.00, '2026-04-04 12:55:35', 2),
(10, 'test1', 30.00, '2026-04-04 13:34:59', 3),
(11, 'testttttt', 0.00, '2026-04-05 16:30:58', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `wallet_goals`
--

CREATE TABLE `wallet_goals` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `target_amount` decimal(15,2) NOT NULL,
  `deadline` date NOT NULL,
  `status` varchar(20) NOT NULL,
  `created_at` datetime NOT NULL,
  `wallet_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `wallet_goals`
--

INSERT INTO `wallet_goals` (`id`, `name`, `target_amount`, `deadline`, `status`, `created_at`, `wallet_id`) VALUES
(2, 'BTC investment', 10000.00, '2026-04-08', 'in_progress', '2026-04-04 13:06:06', 8),
(3, '6000 dollars goal', 6000.00, '2026-04-07', 'in_progress', '2026-04-04 13:06:52', 8),
(4, 'etherium investment', 80000.00, '2026-04-05', 'in_progress', '2026-04-04 13:16:24', 8),
(6, 'BTC investment', 11000.00, '2027-09-08', 'in_progress', '2026-04-04 13:35:11', 10),
(7, 'BTC investment', 11000.00, '2026-04-05', 'in_progress', '2026-04-04 13:52:30', 8),
(8, 'BTC investment', 100000.00, '2026-04-05', 'cancelled', '2026-04-04 14:09:31', 8),
(9, 'BTC investment', 100000.00, '2026-04-07', 'in_progress', '2026-04-05 15:37:27', 8),
(10, 'BTC investment', 200000.00, '2026-04-07', 'in_progress', '2026-04-05 15:38:24', 8);

--
-- Indexes for dumped tables
--

--
-- Indexes for table `activity_log`
--
ALTER TABLE `activity_log`
  ADD PRIMARY KEY (`id`),
  ADD KEY `IDX_FD06F647712520F3` (`wallet_id`);

--
-- Indexes for table `asset`
--
ALTER TABLE `asset`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `doctrine_migration_versions`
--
ALTER TABLE `doctrine_migration_versions`
  ADD PRIMARY KEY (`version`);

--
-- Indexes for table `messenger_messages`
--
ALTER TABLE `messenger_messages`
  ADD PRIMARY KEY (`id`),
  ADD KEY `IDX_75EA56E0FB7336F0E3BD61CE16BA31DBBF396750` (`queue_name`,`available_at`,`delivered_at`,`id`);

--
-- Indexes for table `notifications`
--
ALTER TABLE `notifications`
  ADD PRIMARY KEY (`id`),
  ADD KEY `IDX_6000B0D3712520F3` (`wallet_id`);

--
-- Indexes for table `orders`
--
ALTER TABLE `orders`
  ADD PRIMARY KEY (`id`),
  ADD KEY `IDX_E52FFDEE5DA1941` (`asset_id`);

--
-- Indexes for table `p2p_contract`
--
ALTER TABLE `p2p_contract`
  ADD PRIMARY KEY (`id`),
  ADD KEY `IDX_F89649495DA1941` (`asset_id`);

--
-- Indexes for table `portfolio`
--
ALTER TABLE `portfolio`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `portfolio_asset`
--
ALTER TABLE `portfolio_asset`
  ADD PRIMARY KEY (`id`),
  ADD KEY `IDX_5FF77019B96B5643` (`portfolio_id`),
  ADD KEY `IDX_5FF770195DA1941` (`asset_id`);

--
-- Indexes for table `user_reputation`
--
ALTER TABLE `user_reputation`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uniq_users_email` (`email`);

--
-- Indexes for table `wallets`
--
ALTER TABLE `wallets`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `UNIQ_95D17D3BA76ED395` (`user_id`);

--
-- Indexes for table `wallet_goals`
--
ALTER TABLE `wallet_goals`
  ADD PRIMARY KEY (`id`),
  ADD KEY `IDX_B7237855712520F3` (`wallet_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `activity_log`
--
ALTER TABLE `activity_log`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=28;

--
-- AUTO_INCREMENT for table `asset`
--
ALTER TABLE `asset`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `messenger_messages`
--
ALTER TABLE `messenger_messages`
  MODIFY `id` bigint(20) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `notifications`
--
ALTER TABLE `notifications`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `orders`
--
ALTER TABLE `orders`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `p2p_contract`
--
ALTER TABLE `p2p_contract`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `portfolio`
--
ALTER TABLE `portfolio`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `portfolio_asset`
--
ALTER TABLE `portfolio_asset`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `user_reputation`
--
ALTER TABLE `user_reputation`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `wallets`
--
ALTER TABLE `wallets`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT for table `wallet_goals`
--
ALTER TABLE `wallet_goals`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `activity_log`
--
ALTER TABLE `activity_log`
  ADD CONSTRAINT `FK_FD06F647712520F3` FOREIGN KEY (`wallet_id`) REFERENCES `wallets` (`id`);

--
-- Constraints for table `notifications`
--
ALTER TABLE `notifications`
  ADD CONSTRAINT `FK_6000B0D3712520F3` FOREIGN KEY (`wallet_id`) REFERENCES `wallets` (`id`);

--
-- Constraints for table `orders`
--
ALTER TABLE `orders`
  ADD CONSTRAINT `FK_E52FFDEE5DA1941` FOREIGN KEY (`asset_id`) REFERENCES `asset` (`id`);

--
-- Constraints for table `p2p_contract`
--
ALTER TABLE `p2p_contract`
  ADD CONSTRAINT `FK_F89649495DA1941` FOREIGN KEY (`asset_id`) REFERENCES `asset` (`id`);

--
-- Constraints for table `portfolio_asset`
--
ALTER TABLE `portfolio_asset`
  ADD CONSTRAINT `FK_5FF770195DA1941` FOREIGN KEY (`asset_id`) REFERENCES `asset` (`id`),
  ADD CONSTRAINT `FK_5FF77019B96B5643` FOREIGN KEY (`portfolio_id`) REFERENCES `portfolio` (`id`);

--
-- Constraints for table `wallets`
--
ALTER TABLE `wallets`
  ADD CONSTRAINT `FK_95D17D3BA76ED395` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `wallet_goals`
--
ALTER TABLE `wallet_goals`
  ADD CONSTRAINT `FK_B7237855712520F3` FOREIGN KEY (`wallet_id`) REFERENCES `wallets` (`id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
