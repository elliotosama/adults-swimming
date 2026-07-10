DROP TABLE IF EXISTS `audit_log`;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `audit_log` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) DEFAULT NULL,
  `action` varchar(255) DEFAULT NULL,
  `detail` text DEFAULT NULL,
  `ip` varchar(45) DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`)
);

--
-- Table structure for table `branches`
--

DROP TABLE IF EXISTS `branches`;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `branches` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `branch_name` varchar(50) DEFAULT NULL,
  `created_at` date DEFAULT NULL,
  `visible` int(1) DEFAULT 1,
  `working_days1` set('Sunday','Monday','Tuesday','Wednesday','Thursday','Friday','Saturday') DEFAULT NULL,
  `working_days2` set('Sunday','Monday','Tuesday','Wednesday','Thursday','Friday','Saturday') DEFAULT NULL,
  `working_days3` set('Sunday','Monday','Tuesday','Wednesday','Thursday','Friday','Saturday') DEFAULT NULL,
  `country_id` int(11) DEFAULT NULL,
  `working_time_from` time DEFAULT NULL,
  `working_time_to` time DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `fk_branches_country` (`country_id`),
  CONSTRAINT `fk_branches_country` FOREIGN KEY (`country_id`) REFERENCES `countries` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
);

--
-- Table structure for table `captain_branch`
--

DROP TABLE IF EXISTS `captain_branch`;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `captain_branch` (
  `branch_id` int(11) NOT NULL,
  `captain_id` varchar(10) NOT NULL,
  PRIMARY KEY (`branch_id`,`captain_id`),
  KEY `captain_id` (`captain_id`),
  CONSTRAINT `captain_branch_ibfk_1` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`),
  CONSTRAINT `captain_branch_ibfk_2` FOREIGN KEY (`captain_id`) REFERENCES `captains` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

--
-- Table structure for table `captains`
--

DROP TABLE IF EXISTS `captains`;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `captains` (
  `id` varchar(10) NOT NULL,
  `captain_name` varchar(255) NOT NULL,
  `phone_number` varchar(50) DEFAULT NULL,
  `created_at` date DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `visible` int(1) DEFAULT 1,
  PRIMARY KEY (`id`),
  KEY `fk_one` (`created_by`),
  CONSTRAINT `fk_one` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

--
-- Table structure for table `client_updates`
--

DROP TABLE IF EXISTS `client_updates`;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `client_updates` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `previous_phone` varchar(255) DEFAULT NULL,
  `phone` varchar(255) DEFAULT NULL,
  `client_id` int(11) DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` date DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `client_id` (`client_id`),
  KEY `created_by` (`created_by`),
  CONSTRAINT `client_updates_ibfk_1` FOREIGN KEY (`client_id`) REFERENCES `clients` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `client_updates_ibfk_2` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

--
-- Table structure for table `clients`
--

DROP TABLE IF EXISTS `clients`;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `clients` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `client_name` varchar(255) NOT NULL,
  `phone` varchar(255) NOT NULL,
  `created_by` int(11) DEFAULT NULL,
  `age` int(2) DEFAULT NULL,
  `gender` varchar(10) DEFAULT NULL,
  `created_at` date DEFAULT NULL,
  `email` varchar(255) DEFAULT NULL,
  `visible` int(1) DEFAULT 1,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_phone` (`phone`),
  UNIQUE KEY `email` (`email`),
  KEY `created_by` (`created_by`),
  CONSTRAINT `clients_ibfk_1` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

--
-- Table structure for table `countries`
--

DROP TABLE IF EXISTS `countries`;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `countries` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `country` varchar(255) DEFAULT NULL,
  `country_code` varchar(5) DEFAULT NULL,
  `created_at` date DEFAULT NULL,
  `visible` int(1) DEFAULT 1,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

--
-- Table structure for table `prices`
--

DROP TABLE IF EXISTS `prices`;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `prices` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `description` varchar(255) DEFAULT NULL,
  `price` decimal(8,2) DEFAULT NULL,
  `created_at` date DEFAULT NULL,
  `updated_at` date DEFAULT NULL,
  `visible` int(1) DEFAULT 1,
  `number_of_sessions` int(3) DEFAULT NULL,
  `country_id` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `fk_country` (`country_id`),
  CONSTRAINT `fk_country` FOREIGN KEY (`country_id`) REFERENCES `countries` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

--
-- Table structure for table `receipt_audit_log`
--

DROP TABLE IF EXISTS `receipt_audit_log`;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `receipt_audit_log` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `receipt_id` bigint(20) NOT NULL,
  `changed_by` int(11) NOT NULL,
  `changed_at` datetime NOT NULL DEFAULT current_timestamp(),
  `role` varchar(50) NOT NULL,
  `field_name` varchar(100) NOT NULL,
  `old_value` text DEFAULT NULL,
  `new_value` text DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_receipt_id` (`receipt_id`),
  KEY `idx_changed_by` (`changed_by`),
  KEY `idx_changed_at` (`changed_at`),
  CONSTRAINT `audit_log` FOREIGN KEY (`receipt_id`) REFERENCES `receipts` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_audit_user` FOREIGN KEY (`changed_by`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

--
-- Table structure for table `receipts`
--

DROP TABLE IF EXISTS `receipts`;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `receipts` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `receipt_ref` varchar(20) DEFAULT NULL COMMENT 'Human-readable receipt ID: YYMM + padded sequence, e.g. 26060042',
  `client_id` int(11) NOT NULL,
  `creator_id` int(11) NOT NULL,
  `captain_id` varchar(10) NOT NULL,
  `branch_id` int(11) NOT NULL,
  `first_session` date DEFAULT NULL,
  `last_session` date DEFAULT NULL,
  `renewal_session` date DEFAULT NULL,
  `created_at` date DEFAULT NULL,
  `renewal_type` varchar(50) DEFAULT NULL,
  `receipt_status` varchar(50) DEFAULT 'not_completed',
  `exercise_time` time DEFAULT NULL,
  `plan_id` int(11) DEFAULT NULL,
  `level` int(1) DEFAULT NULL,
  `pdf_path` varchar(255) DEFAULT NULL,
  `is_refunded` int(1) DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `client_id` (`client_id`),
  KEY `creator_id` (`creator_id`),
  KEY `captain_id` (`captain_id`),
  KEY `branch_id` (`branch_id`),
  KEY `fk_two` (`plan_id`),
  KEY `idx_receipts_receipt_ref` (`receipt_ref`),
  CONSTRAINT `fk_two` FOREIGN KEY (`plan_id`) REFERENCES `prices` (`id`),
  CONSTRAINT `receipts_ibfk_1` FOREIGN KEY (`client_id`) REFERENCES `clients` (`id`),
  CONSTRAINT `receipts_ibfk_2` FOREIGN KEY (`creator_id`) REFERENCES `users` (`id`),
  CONSTRAINT `receipts_ibfk_3` FOREIGN KEY (`client_id`) REFERENCES `clients` (`id`),
  CONSTRAINT `receipts_ibfk_4` FOREIGN KEY (`creator_id`) REFERENCES `users` (`id`),
  CONSTRAINT `receipts_ibfk_5` FOREIGN KEY (`captain_id`) REFERENCES `captains` (`id`),
  CONSTRAINT `receipts_ibfk_6` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

--
-- Table structure for table `settings`
--

DROP TABLE IF EXISTS `settings`;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `settings` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `setting_key` varchar(100) NOT NULL,
  `setting_value` text NOT NULL,
  `label` varchar(255) DEFAULT NULL COMMENT 'Human-readable label',
  `updated_by` int(10) unsigned DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_setting_key` (`setting_key`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

--
-- Table structure for table `transactions`
--

DROP TABLE IF EXISTS `transactions`;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `transactions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `payment_method` varchar(255) DEFAULT NULL,
  `amount` decimal(8,2) DEFAULT NULL,
  `receipt_id` bigint(20) DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` date DEFAULT NULL,
  `attachment` varchar(255) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `type` varchar(50) DEFAULT 'payment',
  PRIMARY KEY (`id`),
  KEY `created_by` (`created_by`),
  KEY `receipt_id_fk` (`receipt_id`),
  CONSTRAINT `receipt_id_fk` FOREIGN KEY (`receipt_id`) REFERENCES `receipts` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `transactions_ibfk_2` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

--
-- Table structure for table `user_branch`
--

DROP TABLE IF EXISTS `user_branch`;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `user_branch` (
  `user_id` int(11) NOT NULL,
  `branch_id` int(11) NOT NULL,
  PRIMARY KEY (`user_id`,`branch_id`),
  KEY `branch_id` (`branch_id`),
  CONSTRAINT `user_branch_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`),
  CONSTRAINT `user_branch_ibfk_2` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

--
-- Table structure for table `users`
--

DROP TABLE IF EXISTS `users`;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `username` varchar(255) NOT NULL,
  `email` varchar(255) DEFAULT NULL,
  `password_hash` varchar(255) DEFAULT NULL,
  `role` varchar(50) DEFAULT NULL,
  `phone` varchar(50) DEFAULT NULL,
  `visible` int(1) DEFAULT 1,
  `created_at` date DEFAULT NULL,
  `last_login` datetime DEFAULT NULL,
  `is_active` int(1) DEFAULT 1,
  PRIMARY KEY (`id`),
  UNIQUE KEY `email` (`email`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

--


-- Dump completed on 2026-06-28 20:16:35
