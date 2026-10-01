-- phpMyAdmin SQL Dump
-- version 4.5.1
-- http://www.phpmyadmin.net
--
-- Host: 127.0.0.1
-- Generation Time: Oct 01, 2026 at 02:03 PM
-- Server version: 10.1.9-MariaDB
-- PHP Version: 5.6.15

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `lab_booking`
--

-- --------------------------------------------------------

--
-- Table structure for table `activity_logs`
--

CREATE TABLE `activity_logs` (
  `id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `action` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL,
  `entity` varchar(32) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `entity_id` int(11) DEFAULT NULL,
  `description` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `ip_address` varchar(45) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `activity_logs`
--

INSERT INTO `activity_logs` (`id`, `user_id`, `action`, `entity`, `entity_id`, `description`, `ip_address`, `created_at`) VALUES
(1, NULL, 'login', 'user', 1, 'Signed in', '127.0.0.1', '2026-09-28 20:32:19'),
(2, NULL, 'create', 'laboratory', 1, 'Created Computer Laboratory 1', '127.0.0.1', '2026-09-28 20:32:19'),
(3, NULL, 'create', 'laboratory', 3, 'Created Networking Laboratory', '127.0.0.1', '2026-09-28 20:32:19'),
(4, NULL, 'update', 'laboratory', 5, 'Set Computer Laboratory 3 to maintenance', '127.0.0.1', '2026-09-28 20:32:19'),
(5, NULL, 'login', 'user', 1, 'Signed in', '::1', '2026-09-30 19:37:07'),
(6, 3, 'login', 'user', 3, 'Signed in', '::1', '2026-09-30 19:37:09'),
(7, NULL, 'login', 'user', 1, 'Signed in', '::1', '2026-09-30 19:37:40'),
(8, 3, 'login', 'user', 3, 'Signed in', '::1', '2026-09-30 19:37:41'),
(9, 3, 'login', 'user', 3, 'Signed in', '::1', '2026-09-30 19:39:40'),
(10, 4, 'login', 'user', 4, 'Signed in', '::1', '2026-09-30 19:39:40'),
(15, 3, 'login', 'user', 3, 'Signed in', '::1', '2026-09-30 19:40:45'),
(16, 4, 'login', 'user', 4, 'Signed in', '::1', '2026-09-30 19:40:45'),
(21, 3, 'login', 'user', 3, 'Signed in', '::1', '2026-09-30 19:40:59'),
(22, 3, 'login', 'user', 3, 'Signed in', '::1', '2026-09-30 19:41:42'),
(23, 4, 'login', 'user', 4, 'Signed in', '::1', '2026-09-30 19:41:42'),
(28, NULL, 'login', 'user', 1, 'Signed in', '::1', '2026-09-30 19:43:54'),
(29, 3, 'login', 'user', 3, 'Signed in', '::1', '2026-09-30 19:43:55'),
(30, 3, 'login', 'user', 3, 'Signed in', '::1', '2026-09-30 19:43:59'),
(31, 4, 'login', 'user', 4, 'Signed in', '::1', '2026-09-30 19:43:59'),
(36, NULL, 'register', 'user', 13, 'Self-registered as a student', '::1', '2026-09-30 19:44:10'),
(37, NULL, 'login', 'user', 13, 'Signed in', '::1', '2026-09-30 19:44:12'),
(38, NULL, 'login', 'user', 1, 'Signed in', '::1', '2026-09-30 19:44:13'),
(41, NULL, 'login', 'user', 13, 'Signed in', '::1', '2026-09-30 19:44:17'),
(42, NULL, 'login', 'user', 1, 'Signed in', '::1', '2026-09-30 19:44:19'),
(43, NULL, 'login', 'user', 13, 'Signed in', '::1', '2026-09-30 19:44:20'),
(44, NULL, 'session_revoked', 'user', 13, 'Session ended: account deleted or deactivated', '::1', '2026-09-30 19:44:21'),
(46, NULL, 'login', 'user', 13, 'Signed in', '::1', '2026-09-30 19:44:22'),
(47, NULL, 'logout', 'user', 13, 'Signed out', '::1', '2026-09-30 19:44:22'),
(54, 3, 'login', 'user', 3, 'Signed in', '::1', '2026-09-30 19:44:27'),
(56, 3, 'create', 'booking', 67, 'Requested BK-2026-0021', '::1', '2026-09-30 19:47:25'),
(57, 3, 'login', 'user', 3, 'Signed in', '::1', '2026-09-30 19:52:57'),
(58, 3, 'login', 'user', 3, 'Signed in', '::1', '2026-09-30 19:53:20'),
(59, NULL, 'login', 'user', 1, 'Signed in', '::1', '2026-09-30 19:53:49'),
(60, 3, 'login', 'user', 3, 'Signed in', '::1', '2026-09-30 19:53:50'),
(61, 3, 'login', 'user', 3, 'Signed in', '::1', '2026-09-30 19:54:07'),
(62, NULL, 'register', 'user', 14, 'Self-registered as a student', '::1', '2026-09-30 19:54:22'),
(63, 3, 'login', 'user', 3, 'Signed in', '::1', '2026-09-30 19:55:14'),
(64, 4, 'login', 'user', 4, 'Signed in', '::1', '2026-09-30 19:55:15'),
(69, NULL, 'login', 'user', 1, 'Signed in', '::1', '2026-09-30 19:55:43'),
(70, 3, 'login', 'user', 3, 'Signed in', '::1', '2026-09-30 19:55:43'),
(71, 3, 'login', 'user', 3, 'Signed in', '::1', '2026-09-30 19:55:49'),
(72, 4, 'login', 'user', 4, 'Signed in', '::1', '2026-09-30 19:55:49'),
(77, NULL, 'register', 'user', 15, 'Self-registered as a student', '::1', '2026-09-30 19:55:57'),
(78, NULL, 'login', 'user', 15, 'Signed in', '::1', '2026-09-30 19:56:02'),
(79, NULL, 'login', 'user', 1, 'Signed in', '::1', '2026-09-30 19:56:03'),
(82, NULL, 'login', 'user', 15, 'Signed in', '::1', '2026-09-30 19:56:12'),
(83, NULL, 'login', 'user', 1, 'Signed in', '::1', '2026-09-30 19:56:14'),
(84, NULL, 'login', 'user', 15, 'Signed in', '::1', '2026-09-30 19:56:16'),
(85, NULL, 'session_revoked', 'user', 15, 'Session ended: account deleted or deactivated', '::1', '2026-09-30 19:56:17'),
(87, NULL, 'login', 'user', 15, 'Signed in', '::1', '2026-09-30 19:56:19'),
(88, NULL, 'logout', 'user', 15, 'Signed out', '::1', '2026-09-30 19:56:19'),
(95, 3, 'login', 'user', 3, 'Signed in', '::1', '2026-09-30 19:56:24'),
(97, NULL, 'login', 'user', 1, 'Signed in', '::1', '2026-09-30 19:56:32'),
(98, NULL, 'login', 'user', 1, 'Signed in', '::1', '2026-09-30 20:00:48'),
(99, 3, 'login', 'user', 3, 'Signed in', '::1', '2026-09-30 20:00:48'),
(100, 3, 'login', 'user', 3, 'Signed in', '::1', '2026-09-30 20:00:52'),
(101, 4, 'login', 'user', 4, 'Signed in', '::1', '2026-09-30 20:00:52'),
(106, NULL, 'register', 'user', 16, 'Self-registered as a student', '::1', '2026-09-30 20:00:56'),
(107, NULL, 'login', 'user', 16, 'Signed in', '::1', '2026-09-30 20:00:58'),
(108, NULL, 'login', 'user', 1, 'Signed in', '::1', '2026-09-30 20:00:58'),
(111, NULL, 'login', 'user', 16, 'Signed in', '::1', '2026-09-30 20:01:01'),
(112, NULL, 'login', 'user', 1, 'Signed in', '::1', '2026-09-30 20:01:03'),
(113, NULL, 'login', 'user', 16, 'Signed in', '::1', '2026-09-30 20:01:05'),
(114, NULL, 'session_revoked', 'user', 16, 'Session ended: account deleted or deactivated', '::1', '2026-09-30 20:01:05'),
(116, NULL, 'login', 'user', 16, 'Signed in', '::1', '2026-09-30 20:01:06'),
(117, NULL, 'logout', 'user', 16, 'Signed out', '::1', '2026-09-30 20:01:07'),
(124, 3, 'login', 'user', 3, 'Signed in', '::1', '2026-09-30 20:01:12'),
(126, NULL, 'login', 'user', 1, 'Signed in', '::1', '2026-09-30 20:09:34'),
(127, 3, 'login', 'user', 3, 'Signed in', '::1', '2026-09-30 20:09:34'),
(128, NULL, 'login', 'user', 1, 'Signed in', '::1', '2026-09-30 20:10:24'),
(129, 3, 'login', 'user', 3, 'Signed in', '::1', '2026-09-30 20:10:25'),
(130, NULL, 'login', 'user', 1, 'Signed in', '::1', '2026-09-30 20:13:11'),
(131, 3, 'login', 'user', 3, 'Signed in', '::1', '2026-09-30 20:13:11'),
(137, NULL, 'login', 'user', 1, 'Signed in', '::1', '2026-09-30 20:14:31'),
(138, 3, 'login', 'user', 3, 'Signed in', '::1', '2026-09-30 20:14:32'),
(139, NULL, 'login', 'user', 1, 'Signed in', '::1', '2026-09-30 20:15:21'),
(140, 3, 'login', 'user', 3, 'Signed in', '::1', '2026-09-30 20:15:22'),
(141, 3, 'login', 'user', 3, 'Signed in', '::1', '2026-09-30 20:15:30'),
(142, 4, 'login', 'user', 4, 'Signed in', '::1', '2026-09-30 20:15:31'),
(147, NULL, 'login', 'user', 1, 'Signed in', '::1', '2026-09-30 20:15:34'),
(148, 3, 'login', 'user', 3, 'Signed in', '::1', '2026-09-30 20:15:34'),
(154, NULL, 'register', 'user', 17, 'Self-registered as a student', '::1', '2026-09-30 20:15:39'),
(155, NULL, 'login', 'user', 17, 'Signed in', '::1', '2026-09-30 20:15:41'),
(156, NULL, 'login', 'user', 1, 'Signed in', '::1', '2026-09-30 20:15:42'),
(159, NULL, 'login', 'user', 17, 'Signed in', '::1', '2026-09-30 20:15:46'),
(160, NULL, 'login', 'user', 1, 'Signed in', '::1', '2026-09-30 20:15:48'),
(161, NULL, 'login', 'user', 17, 'Signed in', '::1', '2026-09-30 20:15:51'),
(162, NULL, 'session_revoked', 'user', 17, 'Session ended: account deleted or deactivated', '::1', '2026-09-30 20:15:51'),
(164, NULL, 'login', 'user', 17, 'Signed in', '::1', '2026-09-30 20:15:53'),
(165, NULL, 'logout', 'user', 17, 'Signed out', '::1', '2026-09-30 20:15:53'),
(172, 3, 'login', 'user', 3, 'Signed in', '::1', '2026-09-30 20:15:58'),
(192, NULL, 'login', 'user', 1, 'Signed in', '::1', '2026-09-30 21:52:09'),
(194, NULL, 'login', 'user', 1, 'Signed in', '::1', '2026-09-30 21:52:38'),
(195, NULL, 'login', 'user', 1, 'Signed in', '::1', '2026-09-30 21:52:49'),
(254, NULL, 'login', 'user', 1, 'Signed in', '::1', '2026-09-30 21:56:54'),
(255, 3, 'login', 'user', 3, 'Signed in', '::1', '2026-09-30 21:56:54'),
(259, NULL, 'login', 'user', 1, 'Signed in', '::1', '2026-09-30 21:57:44'),
(260, NULL, 'login', 'user', 1, 'Signed in', '::1', '2026-09-30 21:58:03'),
(261, NULL, 'login', 'user', 1, 'Signed in', '::1', '2026-09-30 21:58:58'),
(262, 3, 'login', 'user', 3, 'Signed in', '::1', '2026-09-30 21:58:58'),
(272, NULL, 'login', 'user', 1, 'Signed in', '::1', '2026-09-30 22:01:11'),
(273, 3, 'login', 'user', 3, 'Signed in', '::1', '2026-09-30 22:01:11'),
(283, NULL, 'login', 'user', 1, 'Signed in', '::1', '2026-09-30 22:01:33'),
(284, 3, 'login', 'user', 3, 'Signed in', '::1', '2026-09-30 22:01:33'),
(294, NULL, 'login', 'user', 1, 'Signed in', '::1', '2026-09-30 22:01:55'),
(295, 3, 'login', 'user', 3, 'Signed in', '::1', '2026-09-30 22:01:56'),
(305, NULL, 'login', 'user', 1, 'Signed in', '::1', '2026-09-30 22:02:05'),
(306, 3, 'login', 'user', 3, 'Signed in', '::1', '2026-09-30 22:02:05'),
(316, NULL, 'login', 'user', 1, 'Signed in', '::1', '2026-09-30 22:04:00'),
(317, 3, 'login', 'user', 3, 'Signed in', '::1', '2026-09-30 22:04:00'),
(318, NULL, 'login', 'user', 1, 'Signed in', '::1', '2026-09-30 22:04:07'),
(319, 3, 'login', 'user', 3, 'Signed in', '::1', '2026-09-30 22:04:07'),
(320, NULL, 'login', 'user', 1, 'Signed in', '::1', '2026-09-30 22:04:14'),
(321, 3, 'login', 'user', 3, 'Signed in', '::1', '2026-09-30 22:04:14'),
(322, NULL, 'login', 'user', 1, 'Signed in', '::1', '2026-09-30 22:04:29'),
(323, 3, 'login', 'user', 3, 'Signed in', '::1', '2026-09-30 22:04:29'),
(339, 3, 'login', 'user', 3, 'Signed in', '::1', '2026-09-30 22:04:59'),
(340, 4, 'login', 'user', 4, 'Signed in', '::1', '2026-09-30 22:04:59'),
(345, NULL, 'login', 'user', 1, 'Signed in', '::1', '2026-09-30 22:05:01'),
(346, 3, 'login', 'user', 3, 'Signed in', '::1', '2026-09-30 22:05:01'),
(352, NULL, 'login', 'user', 1, 'Signed in', '::1', '2026-09-30 22:05:06'),
(353, 2, 'login', 'user', 2, 'Signed in', '::1', '2026-09-30 22:05:06'),
(354, 3, 'login', 'user', 3, 'Signed in', '::1', '2026-09-30 22:05:06'),
(355, 4, 'login', 'user', 4, 'Signed in', '::1', '2026-09-30 22:05:07'),
(356, 5, 'login', 'user', 5, 'Signed in', '::1', '2026-09-30 22:05:07'),
(357, 6, 'login', 'user', 6, 'Signed in', '::1', '2026-09-30 22:05:07'),
(358, 7, 'login', 'user', 7, 'Signed in', '::1', '2026-09-30 22:05:07'),
(359, 8, 'login', 'user', 8, 'Signed in', '::1', '2026-09-30 22:05:07'),
(360, 9, 'login', 'user', 9, 'Signed in', '::1', '2026-09-30 22:05:08'),
(361, 10, 'login', 'user', 10, 'Signed in', '::1', '2026-09-30 22:05:08'),
(362, 11, 'login', 'user', 11, 'Signed in', '::1', '2026-09-30 22:05:08'),
(363, 12, 'login', 'user', 12, 'Signed in', '::1', '2026-09-30 22:05:08'),
(364, NULL, 'login', 'user', 1, 'Signed in', '::1', '2026-09-30 22:09:16'),
(365, NULL, 'login', 'user', 1, 'Signed in', '::1', '2026-09-30 22:09:29'),
(366, NULL, 'login', 'user', 1, 'Signed in', '::1', '2026-09-30 22:09:42'),
(367, 3, 'login', 'user', 3, 'Signed in', '::1', '2026-09-30 22:09:42'),
(368, NULL, 'login', 'user', 1, 'Signed in', '::1', '2026-09-30 22:09:49'),
(369, 3, 'login', 'user', 3, 'Signed in', '::1', '2026-09-30 22:09:49'),
(379, NULL, 'login', 'user', 1, 'Signed in', '::1', '2026-09-30 22:09:52'),
(395, 3, 'login', 'user', 3, 'Signed in', '::1', '2026-09-30 22:09:58'),
(396, 4, 'login', 'user', 4, 'Signed in', '::1', '2026-09-30 22:09:59'),
(401, NULL, 'login', 'user', 1, 'Signed in', '::1', '2026-09-30 22:10:01'),
(402, 3, 'login', 'user', 3, 'Signed in', '::1', '2026-09-30 22:10:01'),
(408, NULL, 'login', 'user', 1, 'Signed in', '::1', '2026-09-30 22:10:03'),
(409, 2, 'login', 'user', 2, 'Signed in', '::1', '2026-09-30 22:10:03'),
(410, 3, 'login', 'user', 3, 'Signed in', '::1', '2026-09-30 22:10:03'),
(411, 4, 'login', 'user', 4, 'Signed in', '::1', '2026-09-30 22:10:04'),
(412, 5, 'login', 'user', 5, 'Signed in', '::1', '2026-09-30 22:10:04'),
(413, 6, 'login', 'user', 6, 'Signed in', '::1', '2026-09-30 22:10:04'),
(414, 7, 'login', 'user', 7, 'Signed in', '::1', '2026-09-30 22:10:04'),
(415, 8, 'login', 'user', 8, 'Signed in', '::1', '2026-09-30 22:10:05'),
(416, 9, 'login', 'user', 9, 'Signed in', '::1', '2026-09-30 22:10:05'),
(417, 10, 'login', 'user', 10, 'Signed in', '::1', '2026-09-30 22:10:05'),
(418, 11, 'login', 'user', 11, 'Signed in', '::1', '2026-09-30 22:10:05'),
(419, 12, 'login', 'user', 12, 'Signed in', '::1', '2026-09-30 22:10:06'),
(420, NULL, 'login', 'user', 1, 'Signed in', '::1', '2026-09-30 22:10:45'),
(421, 3, 'login', 'user', 3, 'Signed in', '::1', '2026-09-30 22:10:45'),
(427, NULL, 'login', 'user', 1, 'Signed in', '::1', '2026-09-30 22:10:47'),
(428, 3, 'login', 'user', 3, 'Signed in', '::1', '2026-09-30 22:10:47'),
(434, NULL, 'login', 'user', 1, 'Signed in', '::1', '2026-09-30 22:10:59'),
(435, 3, 'login', 'user', 3, 'Signed in', '::1', '2026-09-30 22:10:59'),
(436, NULL, 'login', 'user', 1, 'Signed in', '::1', '2026-09-30 22:11:02'),
(437, 3, 'login', 'user', 3, 'Signed in', '::1', '2026-09-30 22:11:02'),
(447, NULL, 'login', 'user', 1, 'Signed in', '::1', '2026-09-30 22:11:04'),
(463, 3, 'login', 'user', 3, 'Signed in', '::1', '2026-09-30 22:11:06'),
(464, 4, 'login', 'user', 4, 'Signed in', '::1', '2026-09-30 22:11:06'),
(469, NULL, 'login', 'user', 1, 'Signed in', '::1', '2026-09-30 22:11:08'),
(470, 3, 'login', 'user', 3, 'Signed in', '::1', '2026-09-30 22:11:08'),
(476, NULL, 'login', 'user', 1, 'Signed in', '::1', '2026-09-30 22:11:11'),
(477, 2, 'login', 'user', 2, 'Signed in', '::1', '2026-09-30 22:11:11'),
(478, 3, 'login', 'user', 3, 'Signed in', '::1', '2026-09-30 22:11:11'),
(479, 4, 'login', 'user', 4, 'Signed in', '::1', '2026-09-30 22:11:11'),
(480, 5, 'login', 'user', 5, 'Signed in', '::1', '2026-09-30 22:11:12'),
(481, 6, 'login', 'user', 6, 'Signed in', '::1', '2026-09-30 22:11:12'),
(482, 7, 'login', 'user', 7, 'Signed in', '::1', '2026-09-30 22:11:12'),
(483, 8, 'login', 'user', 8, 'Signed in', '::1', '2026-09-30 22:11:12'),
(484, 9, 'login', 'user', 9, 'Signed in', '::1', '2026-09-30 22:11:12'),
(485, 10, 'login', 'user', 10, 'Signed in', '::1', '2026-09-30 22:11:13'),
(486, 11, 'login', 'user', 11, 'Signed in', '::1', '2026-09-30 22:11:13'),
(487, 12, 'login', 'user', 12, 'Signed in', '::1', '2026-09-30 22:11:13'),
(488, NULL, 'login', 'user', 1, 'Signed in', '::1', '2026-09-30 22:13:23'),
(489, NULL, 'rejected', 'booking', 67, 'Booking BK-2026-0021 (Chisoka Mwange, Networking Laboratory, 02 Oct 2026, 09:00 - 11:00) was rejected.', '::1', '2026-09-30 22:17:57'),
(490, NULL, 'update', 'laboratory', 5, 'Updated Computer Laboratory 3', '::1', '2026-09-30 22:22:02'),
(491, NULL, 'login', 'user', 1, 'Signed in', '::1', '2026-09-30 22:24:57'),
(492, NULL, 'login', 'user', 1, 'Signed in', '::1', '2026-09-30 22:25:16'),
(493, 3, 'login', 'user', 3, 'Signed in', '::1', '2026-09-30 22:25:16'),
(503, NULL, 'login', 'user', 1, 'Signed in', '::1', '2026-09-30 22:26:21'),
(504, 1, 'login', 'user', 25, 'Signed in', '::1', '2026-09-30 22:32:38'),
(510, 1, 'login', 'user', 25, 'Signed in', '::1', '2026-09-30 22:35:00'),
(511, 1, 'login', 'user', 25, 'Signed in', '::1', '2026-09-30 22:35:20'),
(526, 3, 'login', 'user', 3, 'Signed in', '::1', '2026-09-30 22:35:22'),
(527, 1, 'login', 'user', 25, 'Signed in', '::1', '2026-09-30 22:35:31'),
(542, 3, 'login', 'user', 3, 'Signed in', '::1', '2026-09-30 22:35:33'),
(543, 1, 'login', 'user', 25, 'Signed in', '::1', '2026-09-30 22:35:33'),
(558, 3, 'login', 'user', 3, 'Signed in', '::1', '2026-09-30 22:35:36'),
(559, 1, 'login', 'user', 25, 'Signed in', '::1', '2026-09-30 22:35:52'),
(560, 3, 'login', 'user', 3, 'Signed in', '::1', '2026-09-30 22:35:52'),
(561, 1, 'login', 'user', 1, 'Signed in', '::1', '2026-09-30 22:36:28'),
(562, 3, 'login', 'user', 3, 'Signed in', '::1', '2026-09-30 22:36:29'),
(593, 3, 'login', 'user', 3, 'Signed in', '::1', '2026-09-30 22:36:58'),
(594, 4, 'login', 'user', 4, 'Signed in', '::1', '2026-09-30 22:36:58'),
(599, 1, 'login', 'user', 1, 'Signed in', '::1', '2026-09-30 22:36:59'),
(600, 3, 'login', 'user', 3, 'Signed in', '::1', '2026-09-30 22:36:59'),
(606, 1, 'login', 'user', 1, 'Signed in', '::1', '2026-09-30 22:37:01'),
(607, 3, 'login', 'user', 3, 'Signed in', '::1', '2026-09-30 22:37:01'),
(617, 1, 'login', 'user', 1, 'Signed in', '::1', '2026-09-30 22:37:03'),
(618, 1, 'login', 'user', 1, 'Signed in', '::1', '2026-09-30 22:37:04'),
(633, 3, 'login', 'user', 3, 'Signed in', '::1', '2026-09-30 22:37:06'),
(634, 3, 'login', 'user', 3, 'Signed in', '::1', '2026-09-30 22:37:12'),
(635, 4, 'login', 'user', 4, 'Signed in', '::1', '2026-09-30 22:37:12'),
(640, 1, 'login', 'user', 1, 'Signed in', '::1', '2026-09-30 22:37:13'),
(641, 3, 'login', 'user', 3, 'Signed in', '::1', '2026-09-30 22:37:13'),
(647, 1, 'login', 'user', 1, 'Signed in', '::1', '2026-09-30 22:37:15'),
(648, 3, 'login', 'user', 3, 'Signed in', '::1', '2026-09-30 22:37:15'),
(658, 1, 'login', 'user', 1, 'Signed in', '::1', '2026-09-30 22:37:17'),
(674, 3, 'login', 'user', 3, 'Signed in', '::1', '2026-09-30 22:39:22'),
(675, 4, 'login', 'user', 4, 'Signed in', '::1', '2026-09-30 22:39:22'),
(680, 1, 'login', 'user', 1, 'Signed in', '::1', '2026-09-30 22:39:23'),
(681, 3, 'login', 'user', 3, 'Signed in', '::1', '2026-09-30 22:39:23'),
(687, 1, 'login', 'user', 1, 'Signed in', '::1', '2026-09-30 22:39:25'),
(688, 3, 'login', 'user', 3, 'Signed in', '::1', '2026-09-30 22:39:25'),
(698, 1, 'login', 'user', 1, 'Signed in', '::1', '2026-09-30 22:39:28'),
(699, 1, 'login', 'user', 1, 'Signed in', '::1', '2026-09-30 22:39:28'),
(714, 3, 'login', 'user', 3, 'Signed in', '::1', '2026-09-30 22:39:31'),
(715, 1, 'login', 'user', 1, 'Signed in', '::1', '2026-09-30 22:39:36'),
(716, 3, 'login', 'user', 3, 'Signed in', '::1', '2026-09-30 22:39:36'),
(717, 1, 'login', 'user', 1, 'Signed in', '::1', '2026-09-30 22:40:07'),
(732, 3, 'login', 'user', 3, 'Signed in', '::1', '2026-09-30 22:40:09'),
(733, 1, 'login', 'user', 1, 'Signed in', '::1', '2026-09-30 23:08:10'),
(734, 1, 'login', 'user', 1, 'Signed in', '::1', '2026-09-30 23:08:59'),
(735, 1, 'login', 'user', 1, 'Signed in', '::1', '2026-09-30 23:10:01'),
(736, 1, 'login', 'user', 1, 'Signed in', '::1', '2026-09-30 23:10:01'),
(737, 1, 'login', 'user', 1, 'Signed in', '::1', '2026-09-30 23:10:01'),
(738, 1, 'login', 'user', 1, 'Signed in', '::1', '2026-09-30 23:10:02'),
(739, 1, 'login', 'user', 1, 'Signed in', '::1', '2026-09-30 23:10:26'),
(740, 3, 'login', 'user', 3, 'Signed in', '::1', '2026-09-30 23:10:26'),
(741, 1, 'login', 'user', 1, 'Signed in', '::1', '2026-09-30 23:10:36'),
(742, 3, 'login', 'user', 3, 'Signed in', '::1', '2026-09-30 23:10:36'),
(743, 1, 'login', 'user', 1, 'Signed in', '::1', '2026-09-30 23:10:41'),
(758, 3, 'login', 'user', 3, 'Signed in', '::1', '2026-09-30 23:10:43'),
(759, 1, 'login', 'user', 1, 'Signed in', '::1', '2026-09-30 23:17:37'),
(760, 3, 'login', 'user', 3, 'Signed in', '::1', '2026-09-30 23:17:37'),
(761, 1, 'login', 'user', 1, 'Signed in', '::1', '2026-09-30 23:17:41'),
(762, 3, 'login', 'user', 3, 'Signed in', '::1', '2026-09-30 23:17:41'),
(763, 1, 'login', 'user', 1, 'Signed in', '::1', '2026-09-30 23:17:50'),
(764, 3, 'login', 'user', 3, 'Signed in', '::1', '2026-09-30 23:17:50'),
(765, 1, 'login', 'user', 1, 'Signed in', '::1', '2026-09-30 23:18:03'),
(766, 3, 'login', 'user', 3, 'Signed in', '::1', '2026-09-30 23:18:03'),
(767, 1, 'login', 'user', 1, 'Signed in', '::1', '2026-09-30 23:18:05'),
(782, 3, 'login', 'user', 3, 'Signed in', '::1', '2026-09-30 23:18:08'),
(783, 1, 'login', 'user', 1, 'Signed in', '::1', '2026-09-30 23:19:38'),
(784, 3, 'login', 'user', 3, 'Signed in', '::1', '2026-09-30 23:19:39'),
(785, 1, 'login', 'user', 1, 'Signed in', '::1', '2026-09-30 23:19:42'),
(786, 3, 'login', 'user', 3, 'Signed in', '::1', '2026-09-30 23:19:43'),
(787, 1, 'login', 'user', 1, 'Signed in', '::1', '2026-09-30 23:25:32'),
(788, 1, 'login', 'user', 1, 'Signed in', '::1', '2026-09-30 23:34:05'),
(789, 1, 'login', 'user', 1, 'Signed in', '::1', '2026-09-30 23:35:01'),
(790, 1, 'login', 'user', 1, 'Signed in', '::1', '2026-09-30 23:35:02'),
(791, 1, 'login', 'user', 1, 'Signed in', '::1', '2026-09-30 23:35:15'),
(792, 3, 'login', 'user', 3, 'Signed in', '::1', '2026-09-30 23:35:15'),
(793, 1, 'login', 'user', 1, 'Signed in', '::1', '2026-09-30 23:35:15'),
(794, 3, 'login', 'user', 3, 'Signed in', '::1', '2026-09-30 23:35:16'),
(795, 1, 'login', 'user', 1, 'Signed in', '::1', '2026-09-30 23:35:20'),
(810, 3, 'login', 'user', 3, 'Signed in', '::1', '2026-09-30 23:35:22'),
(811, 1, 'login', 'user', 1, 'Signed in', '::1', '2026-09-30 23:37:41'),
(812, 3, 'login', 'user', 3, 'Signed in', '::1', '2026-09-30 23:37:41'),
(813, 1, 'login', 'user', 1, 'Signed in', '::1', '2026-09-30 23:37:41'),
(814, 3, 'login', 'user', 3, 'Signed in', '::1', '2026-09-30 23:37:41'),
(815, 1, 'login', 'user', 1, 'Signed in', '::1', '2026-09-30 23:37:46'),
(830, 3, 'login', 'user', 3, 'Signed in', '::1', '2026-09-30 23:37:48'),
(831, 1, 'login', 'user', 1, 'Signed in', '::1', '2026-09-30 23:38:01'),
(832, 3, 'login', 'user', 3, 'Signed in', '::1', '2026-09-30 23:38:01'),
(833, 1, 'login', 'user', 1, 'Signed in', '::1', '2026-09-30 23:40:53'),
(834, 1, 'login', 'user', 1, 'Signed in', '::1', '2026-09-30 23:41:05'),
(835, 3, 'login', 'user', 3, 'Signed in', '::1', '2026-09-30 23:41:05'),
(836, 1, 'login', 'user', 1, 'Signed in', '::1', '2026-09-30 23:41:05'),
(837, 3, 'login', 'user', 3, 'Signed in', '::1', '2026-09-30 23:41:05'),
(838, 1, 'login', 'user', 1, 'Signed in', '::1', '2026-09-30 23:41:09'),
(853, 3, 'login', 'user', 3, 'Signed in', '::1', '2026-09-30 23:41:12'),
(854, 1, 'login', 'user', 1, 'Signed in', '::1', '2026-09-30 23:46:16'),
(855, 3, 'login', 'user', 3, 'Signed in', '::1', '2026-09-30 23:46:18'),
(856, 1, 'login', 'user', 1, 'Signed in', '::1', '2026-09-30 23:46:31'),
(857, 3, 'login', 'user', 3, 'Signed in', '::1', '2026-09-30 23:46:31'),
(858, 1, 'login', 'user', 1, 'Signed in', '::1', '2026-09-30 23:46:31'),
(859, 3, 'login', 'user', 3, 'Signed in', '::1', '2026-09-30 23:46:31'),
(860, 1, 'login', 'user', 1, 'Signed in', '::1', '2026-09-30 23:46:36'),
(875, 3, 'login', 'user', 3, 'Signed in', '::1', '2026-09-30 23:46:38'),
(876, 1, 'login', 'user', 1, 'Signed in', '::1', '2026-09-30 23:46:54'),
(877, 3, 'login', 'user', 3, 'Signed in', '::1', '2026-09-30 23:46:54'),
(878, 1, 'login', 'user', 1, 'Signed in', '::1', '2026-10-01 00:23:59'),
(879, 1, 'create', 'time_slot', 10, 'Added E2E PROBE', '::1', '2026-10-01 00:24:00'),
(880, 1, 'delete', 'time_slot', 10, 'Deleted E2E PROBE', '::1', '2026-10-01 00:24:00'),
(881, 1, 'login', 'user', 1, 'Signed in', '::1', '2026-10-01 00:24:46'),
(882, 1, 'create', 'time_slot', 11, 'Added E2E PROBE', '::1', '2026-10-01 00:24:47'),
(883, 1, 'update', 'time_slot', 11, 'Updated E2E RENAMED', '::1', '2026-10-01 00:24:47'),
(884, 1, 'update', 'time_slot', 11, 'Updated E2E RENAMED', '::1', '2026-10-01 00:24:47'),
(885, 1, 'update', 'time_slot', 11, 'Enabled E2E RENAMED', '::1', '2026-10-01 00:24:47'),
(886, 1, 'delete', 'time_slot', 11, 'Deleted E2E RENAMED', '::1', '2026-10-01 00:24:47'),
(887, 1, 'create', 'time_slot', 12, 'Added WINDOW PROBE', '::1', '2026-10-01 00:24:47'),
(888, 3, 'login', 'user', 3, 'Signed in', '::1', '2026-10-01 00:24:47'),
(889, 1, 'login', 'user', 1, 'Signed in', '::1', '2026-10-01 00:25:07'),
(890, 3, 'login', 'user', 3, 'Signed in', '::1', '2026-10-01 00:25:08'),
(906, 1, 'login', 'user', 1, 'Signed in', '::1', '2026-10-01 00:28:56'),
(907, 1, 'export', 'report', NULL, 'Exported summary report for 2026-09-10 to 2026-10-10', '::1', '2026-10-01 00:28:56'),
(908, 1, 'export', 'report', NULL, 'Exported laboratories report for 2026-09-10 to 2026-10-10', '::1', '2026-10-01 00:28:56'),
(909, 1, 'export', 'report', NULL, 'Exported students report for 2026-09-10 to 2026-10-10', '::1', '2026-10-01 00:28:56'),
(910, 1, 'export', 'report', NULL, 'Exported daily report for 2026-09-10 to 2026-10-10', '::1', '2026-10-01 00:28:56'),
(911, 1, 'export', 'report', NULL, 'Exported summary report for 2026-09-10 to 2026-10-10', '::1', '2026-10-01 00:28:56'),
(912, 1, 'export', 'report', NULL, 'Exported laboratories report for 2026-09-10 to 2026-10-10', '::1', '2026-10-01 00:28:56'),
(913, 1, 'export', 'report', NULL, 'Exported students report for 2026-09-10 to 2026-10-10', '::1', '2026-10-01 00:28:56'),
(914, 1, 'export', 'report', NULL, 'Exported bogus report for 2026-09-10 to 2026-10-10', '::1', '2026-10-01 00:28:56'),
(915, 3, 'login', 'user', 3, 'Signed in', '::1', '2026-10-01 00:28:56'),
(916, 1, 'login', 'user', 1, 'Signed in', '::1', '2026-10-01 00:29:28'),
(917, 1, 'login', 'user', 1, 'Signed in', '::1', '2026-10-01 00:29:51'),
(918, 1, 'login', 'user', 1, 'Signed in', '::1', '2026-10-01 00:34:19'),
(919, 1, 'export', 'report', NULL, 'Exported summary report for 2026-09-10 to 2026-10-10', '::1', '2026-10-01 00:34:19'),
(920, 1, 'export', 'report', NULL, 'Exported laboratories report for 2026-09-10 to 2026-10-10', '::1', '2026-10-01 00:34:19'),
(921, 1, 'export', 'report', NULL, 'Exported students report for 2026-09-10 to 2026-10-10', '::1', '2026-10-01 00:34:19'),
(922, 1, 'export', 'report', NULL, 'Exported daily report for 2026-09-10 to 2026-10-10', '::1', '2026-10-01 00:34:19'),
(923, 1, 'export', 'report', NULL, 'Exported summary report for 2026-09-10 to 2026-10-10', '::1', '2026-10-01 00:34:19'),
(924, 1, 'export', 'report', NULL, 'Exported laboratories report for 2026-09-10 to 2026-10-10', '::1', '2026-10-01 00:34:19'),
(925, 1, 'export', 'report', NULL, 'Exported students report for 2026-09-10 to 2026-10-10', '::1', '2026-10-01 00:34:19'),
(926, 1, 'export', 'report', NULL, 'Exported bogus report for 2026-09-10 to 2026-10-10', '::1', '2026-10-01 00:34:19'),
(927, 3, 'login', 'user', 3, 'Signed in', '::1', '2026-10-01 00:34:19'),
(943, 1, 'login', 'user', 1, 'Signed in', '::1', '2026-10-01 00:35:40'),
(944, 1, 'login', 'user', 1, 'Signed in', '::1', '2026-10-01 00:36:10'),
(945, 3, 'login', 'user', 3, 'Signed in', '::1', '2026-10-01 00:36:10'),
(946, 1, 'login', 'user', 1, 'Signed in', '::1', '2026-10-01 00:36:19'),
(947, 1, 'create', 'time_slot', 34, 'Added E2E PROBE', '::1', '2026-10-01 00:36:19'),
(948, 1, 'update', 'time_slot', 34, 'Updated E2E RENAMED', '::1', '2026-10-01 00:36:19'),
(949, 1, 'update', 'time_slot', 34, 'Updated E2E RENAMED', '::1', '2026-10-01 00:36:19'),
(950, 1, 'update', 'time_slot', 34, 'Enabled E2E RENAMED', '::1', '2026-10-01 00:36:19'),
(951, 1, 'delete', 'time_slot', 34, 'Deleted E2E RENAMED', '::1', '2026-10-01 00:36:19'),
(952, 1, 'create', 'time_slot', 35, 'Added WINDOW PROBE', '::1', '2026-10-01 00:36:19'),
(953, 3, 'login', 'user', 3, 'Signed in', '::1', '2026-10-01 00:36:20'),
(954, 1, 'login', 'user', 1, 'Signed in', '::1', '2026-10-01 00:36:20'),
(969, 3, 'login', 'user', 3, 'Signed in', '::1', '2026-10-01 00:36:23'),
(970, 1, 'login', 'user', 1, 'Signed in', '::1', '2026-10-01 00:38:15'),
(971, 1, 'export', 'report', NULL, 'Exported summary report for 2026-09-10 to 2026-10-10', '::1', '2026-10-01 00:38:16'),
(972, 1, 'export', 'report', NULL, 'Exported laboratories report for 2026-09-10 to 2026-10-10', '::1', '2026-10-01 00:38:16'),
(973, 1, 'export', 'report', NULL, 'Exported students report for 2026-09-10 to 2026-10-10', '::1', '2026-10-01 00:38:16'),
(974, 1, 'export', 'report', NULL, 'Exported daily report for 2026-09-10 to 2026-10-10', '::1', '2026-10-01 00:38:16'),
(975, 1, 'export', 'report', NULL, 'Exported summary report for 2026-09-10 to 2026-10-10', '::1', '2026-10-01 00:38:16'),
(976, 1, 'export', 'report', NULL, 'Exported laboratories report for 2026-09-10 to 2026-10-10', '::1', '2026-10-01 00:38:16'),
(977, 1, 'export', 'report', NULL, 'Exported students report for 2026-09-10 to 2026-10-10', '::1', '2026-10-01 00:38:16'),
(978, 1, 'export', 'report', NULL, 'Exported bogus report for 2026-09-10 to 2026-10-10', '::1', '2026-10-01 00:38:16'),
(979, 3, 'login', 'user', 3, 'Signed in', '::1', '2026-10-01 00:38:16'),
(980, 1, 'login', 'user', 1, 'Signed in', '::1', '2026-10-01 00:38:16'),
(981, 1, 'create', 'time_slot', 42, 'Added E2E PROBE', '::1', '2026-10-01 00:38:17'),
(982, 1, 'update', 'time_slot', 42, 'Updated E2E RENAMED', '::1', '2026-10-01 00:38:17'),
(983, 1, 'update', 'time_slot', 42, 'Updated E2E RENAMED', '::1', '2026-10-01 00:38:17'),
(984, 1, 'update', 'time_slot', 42, 'Enabled E2E RENAMED', '::1', '2026-10-01 00:38:17'),
(985, 1, 'delete', 'time_slot', 42, 'Deleted E2E RENAMED', '::1', '2026-10-01 00:38:17'),
(986, 1, 'create', 'time_slot', 43, 'Added WINDOW PROBE', '::1', '2026-10-01 00:38:17'),
(987, 3, 'login', 'user', 3, 'Signed in', '::1', '2026-10-01 00:38:17'),
(988, 1, 'login', 'user', 1, 'Signed in', '::1', '2026-10-01 00:38:18'),
(1003, 3, 'login', 'user', 3, 'Signed in', '::1', '2026-10-01 00:38:21'),
(1004, 1, 'login', 'user', 1, 'Signed in', '::1', '2026-10-01 00:38:21'),
(1005, 3, 'login', 'user', 3, 'Signed in', '::1', '2026-10-01 00:38:21'),
(1006, 1, 'login', 'user', 1, 'Signed in', '::1', '2026-10-01 00:38:43'),
(1007, 3, 'login', 'user', 3, 'Signed in', '::1', '2026-10-01 00:38:43'),
(1008, 1, 'login', 'user', 1, 'Signed in', '::1', '2026-10-01 00:38:52'),
(1024, 1, 'export', 'report', NULL, 'Exported summary report for 2026-09-02 to 2026-10-01', '::1', '2026-10-01 00:44:46'),
(1025, 1, 'login', 'user', 1, 'Signed in', '::1', '2026-10-01 09:15:19'),
(1026, NULL, 'password_change', 'user', 122, 'Changed own password', '0.0.0.0', '2026-10-01 10:37:48'),
(1027, NULL, 'password_change', 'user', 123, 'Changed own password', '0.0.0.0', '2026-10-01 10:41:36'),
(1028, NULL, 'password_change', 'user', 124, 'Changed own password', '0.0.0.0', '2026-10-01 10:42:43'),
(1029, NULL, 'password_change', 'user', 125, 'Changed own password', '0.0.0.0', '2026-10-01 10:45:06'),
(1030, NULL, 'password_change', 'user', 126, 'Changed own password', '0.0.0.0', '2026-10-01 10:45:16'),
(1046, NULL, 'password_change', 'user', 134, 'Changed own password', '0.0.0.0', '2026-10-01 10:46:46'),
(1047, 3, 'login', 'user', 3, 'Signed in', '::1', '2026-10-01 10:53:11'),
(1057, 1, 'login', 'user', 1, 'Signed in', '::1', '2026-10-01 10:53:30'),
(1058, 3, 'login', 'user', 3, 'Signed in', '::1', '2026-10-01 10:53:31'),
(1059, 1, 'login', 'user', 1, 'Signed in', '::1', '2026-10-01 10:53:31'),
(1060, 3, 'login', 'user', 3, 'Signed in', '::1', '2026-10-01 10:53:32'),
(1061, 1, 'login', 'user', 1, 'Signed in', '::1', '2026-10-01 10:53:32'),
(1062, 3, 'update', 'user', 3, 'Set a profile picture', '::1', '2026-10-01 10:54:46'),
(1063, 1, 'login', 'user', 1, 'Signed in', '::1', '2026-10-01 10:55:03'),
(1064, 1, 'login', 'user', 1, 'Signed in', '::1', '2026-10-01 10:55:54'),
(1065, 1, 'login', 'user', 1, 'Signed in', '::1', '2026-10-01 10:57:11'),
(1066, 1, 'login', 'user', 1, 'Signed in', '::1', '2026-10-01 10:58:45'),
(1067, 1, 'login', 'user', 1, 'Signed in', '::1', '2026-10-01 10:58:52'),
(1082, 1, 'login', 'user', 1, 'Signed in', '::1', '2026-10-01 11:00:00'),
(1083, 3, 'login', 'user', 3, 'Signed in', '::1', '2026-10-01 11:00:01'),
(1084, 1, 'login', 'user', 1, 'Signed in', '::1', '2026-10-01 11:00:01'),
(1085, 3, 'login', 'user', 3, 'Signed in', '::1', '2026-10-01 11:00:02'),
(1086, 1, 'login', 'user', 1, 'Signed in', '::1', '2026-10-01 11:00:02'),
(1087, NULL, 'password_change', 'user', 141, 'Changed own password', '0.0.0.0', '2026-10-01 11:00:13'),
(1088, NULL, 'password_change', 'user', 142, 'Changed own password', '0.0.0.0', '2026-10-01 11:00:22'),
(1098, 1, 'login', 'user', 1, 'Signed in', '::1', '2026-10-01 11:01:17'),
(1099, 3, 'login', 'user', 3, 'Signed in', '::1', '2026-10-01 11:01:18'),
(1100, 1, 'login', 'user', 1, 'Signed in', '::1', '2026-10-01 11:01:18'),
(1101, 3, 'login', 'user', 3, 'Signed in', '::1', '2026-10-01 11:01:19'),
(1102, 1, 'login', 'user', 1, 'Signed in', '::1', '2026-10-01 11:01:19'),
(1112, 1, 'login', 'user', 1, 'Signed in', '::1', '2026-10-01 11:01:44'),
(1113, 3, 'login', 'user', 3, 'Signed in', '::1', '2026-10-01 11:01:44'),
(1114, 1, 'login', 'user', 1, 'Signed in', '::1', '2026-10-01 11:01:45'),
(1115, 3, 'login', 'user', 3, 'Signed in', '::1', '2026-10-01 11:01:45'),
(1116, 1, 'login', 'user', 1, 'Signed in', '::1', '2026-10-01 11:01:45'),
(1128, 1, 'login', 'user', 1, 'Signed in', '::1', '2026-10-01 11:02:28'),
(1129, 3, 'login', 'user', 3, 'Signed in', '::1', '2026-10-01 11:02:28'),
(1130, 1, 'login', 'user', 1, 'Signed in', '::1', '2026-10-01 11:02:28'),
(1131, 3, 'login', 'user', 3, 'Signed in', '::1', '2026-10-01 11:02:29'),
(1132, 1, 'login', 'user', 1, 'Signed in', '::1', '2026-10-01 11:02:29'),
(1148, NULL, 'password_change', 'user', 156, 'Changed own password', '0.0.0.0', '2026-10-01 11:02:48'),
(1149, 1, 'login', 'user', 1, 'Signed in', '::1', '2026-10-01 11:02:53'),
(1164, 3, 'login', 'user', 3, 'Signed in', '::1', '2026-10-01 11:02:56'),
(1165, 1, 'login', 'user', 1, 'Signed in', '::1', '2026-10-01 11:02:56'),
(1166, 1, 'create', 'time_slot', 62, 'Added E2E PROBE', '::1', '2026-10-01 11:02:57'),
(1167, 1, 'update', 'time_slot', 62, 'Updated E2E RENAMED', '::1', '2026-10-01 11:02:57'),
(1168, 1, 'update', 'time_slot', 62, 'Updated E2E RENAMED', '::1', '2026-10-01 11:02:57'),
(1169, 1, 'update', 'time_slot', 62, 'Enabled E2E RENAMED', '::1', '2026-10-01 11:02:57'),
(1170, 1, 'delete', 'time_slot', 62, 'Deleted E2E RENAMED', '::1', '2026-10-01 11:02:57'),
(1171, 1, 'create', 'time_slot', 63, 'Added WINDOW PROBE', '::1', '2026-10-01 11:02:57'),
(1172, 3, 'login', 'user', 3, 'Signed in', '::1', '2026-10-01 11:02:57'),
(1173, 1, 'login', 'user', 1, 'Signed in', '::1', '2026-10-01 11:02:58'),
(1174, 1, 'export', 'report', NULL, 'Exported summary report for 2026-09-10 to 2026-10-10', '::1', '2026-10-01 11:02:58'),
(1175, 1, 'export', 'report', NULL, 'Exported laboratories report for 2026-09-10 to 2026-10-10', '::1', '2026-10-01 11:02:58'),
(1176, 1, 'export', 'report', NULL, 'Exported students report for 2026-09-10 to 2026-10-10', '::1', '2026-10-01 11:02:58'),
(1177, 1, 'export', 'report', NULL, 'Exported daily report for 2026-09-10 to 2026-10-10', '::1', '2026-10-01 11:02:58'),
(1178, 1, 'export', 'report', NULL, 'Exported summary report for 2026-09-10 to 2026-10-10', '::1', '2026-10-01 11:02:58'),
(1179, 1, 'export', 'report', NULL, 'Exported laboratories report for 2026-09-10 to 2026-10-10', '::1', '2026-10-01 11:02:58'),
(1180, 1, 'export', 'report', NULL, 'Exported students report for 2026-09-10 to 2026-10-10', '::1', '2026-10-01 11:02:58'),
(1181, 1, 'export', 'report', NULL, 'Exported bogus report for 2026-09-10 to 2026-10-10', '::1', '2026-10-01 11:02:58'),
(1182, 3, 'login', 'user', 3, 'Signed in', '::1', '2026-10-01 11:02:58'),
(1183, 1, 'login', 'user', 1, 'Signed in', '::1', '2026-10-01 11:02:59'),
(1184, 3, 'login', 'user', 3, 'Signed in', '::1', '2026-10-01 11:02:59'),
(1194, 1, 'login', 'user', 1, 'Signed in', '::1', '2026-10-01 11:03:01'),
(1195, 3, 'login', 'user', 3, 'Signed in', '::1', '2026-10-01 11:03:01'),
(1201, 1, 'login', 'user', 1, 'Signed in', '::1', '2026-10-01 11:03:07'),
(1202, 3, 'login', 'user', 3, 'Signed in', '::1', '2026-10-01 11:03:07'),
(1212, 1, 'login', 'user', 1, 'Signed in', '::1', '2026-10-01 11:03:10'),
(1213, 3, 'login', 'user', 3, 'Signed in', '::1', '2026-10-01 11:03:10'),
(1219, 1, 'login', 'user', 1, 'Signed in', '::1', '2026-10-01 11:03:26'),
(1220, 3, 'login', 'user', 3, 'Signed in', '::1', '2026-10-01 11:03:26'),
(1221, 1, 'login', 'user', 1, 'Signed in', '::1', '2026-10-01 11:04:37'),
(1222, 1, 'login', 'user', 1, 'Signed in', '::1', '2026-10-01 11:05:23'),
(1223, 1, 'login', 'user', 1, 'Signed in', '::1', '2026-10-01 11:05:24'),
(1224, 3, 'login', 'user', 3, 'Signed in', '::1', '2026-10-01 11:05:26'),
(1225, 3, 'login', 'user', 3, 'Signed in', '::1', '2026-10-01 11:05:28'),
(1226, 3, 'login', 'user', 3, 'Signed in', '::1', '2026-10-01 11:05:29'),
(1227, 1, 'login', 'user', 1, 'Signed in', '::1', '2026-10-01 11:05:30'),
(1228, 1, 'login', 'user', 1, 'Signed in', '::1', '2026-10-01 11:05:31'),
(1229, 3, 'login', 'user', 3, 'Signed in', '::1', '2026-10-01 11:05:32'),
(1230, 3, 'login', 'user', 3, 'Signed in', '::1', '2026-10-01 11:05:33'),
(1242, 1, 'login', 'user', 1, 'Signed in', '::1', '2026-10-01 11:10:09'),
(1243, 3, 'login', 'user', 3, 'Signed in', '::1', '2026-10-01 11:10:09'),
(1244, 1, 'login', 'user', 1, 'Signed in', '::1', '2026-10-01 11:10:10'),
(1245, 3, 'login', 'user', 3, 'Signed in', '::1', '2026-10-01 11:10:10'),
(1246, 1, 'login', 'user', 1, 'Signed in', '::1', '2026-10-01 11:10:10'),
(1247, 1, 'login', 'user', 1, 'Signed in', '::1', '2026-10-01 11:10:13'),
(1248, 1, 'login', 'user', 1, 'Signed in', '::1', '2026-10-01 11:10:14'),
(1249, 3, 'login', 'user', 3, 'Signed in', '::1', '2026-10-01 11:10:16'),
(1250, 3, 'login', 'user', 3, 'Signed in', '::1', '2026-10-01 11:10:17'),
(1251, 3, 'login', 'user', 3, 'Signed in', '::1', '2026-10-01 11:10:19'),
(1252, 1, 'login', 'user', 1, 'Signed in', '::1', '2026-10-01 11:10:20'),
(1253, 1, 'login', 'user', 1, 'Signed in', '::1', '2026-10-01 11:10:21'),
(1254, 3, 'login', 'user', 3, 'Signed in', '::1', '2026-10-01 11:10:22'),
(1255, 3, 'login', 'user', 3, 'Signed in', '::1', '2026-10-01 11:10:23'),
(1271, NULL, 'password_change', 'user', 167, 'Changed own password', '0.0.0.0', '2026-10-01 11:10:39'),
(1272, NULL, 'password_change', 'user', 168, 'Changed own password', '0.0.0.0', '2026-10-01 11:20:08'),
(1284, 1, 'login', 'user', 1, 'Signed in', '::1', '2026-10-01 11:20:53'),
(1285, 3, 'login', 'user', 3, 'Signed in', '::1', '2026-10-01 11:20:54'),
(1286, 1, 'login', 'user', 1, 'Signed in', '::1', '2026-10-01 11:20:54'),
(1287, 3, 'login', 'user', 3, 'Signed in', '::1', '2026-10-01 11:20:55'),
(1288, 1, 'login', 'user', 1, 'Signed in', '::1', '2026-10-01 11:20:55'),
(1289, 1, 'login', 'user', 1, 'Signed in', '::1', '2026-10-01 11:21:03'),
(1290, 1, 'login', 'user', 1, 'Signed in', '::1', '2026-10-01 11:21:05'),
(1291, 3, 'login', 'user', 3, 'Signed in', '::1', '2026-10-01 11:21:06'),
(1292, 3, 'login', 'user', 3, 'Signed in', '::1', '2026-10-01 11:21:08'),
(1293, 3, 'login', 'user', 3, 'Signed in', '::1', '2026-10-01 11:21:09'),
(1294, 1, 'login', 'user', 1, 'Signed in', '::1', '2026-10-01 11:21:10'),
(1295, 1, 'login', 'user', 1, 'Signed in', '::1', '2026-10-01 11:21:11'),
(1296, 3, 'login', 'user', 3, 'Signed in', '::1', '2026-10-01 11:21:12'),
(1297, 3, 'login', 'user', 3, 'Signed in', '::1', '2026-10-01 11:21:13'),
(1309, 1, 'login', 'user', 1, 'Signed in', '::1', '2026-10-01 11:21:36'),
(1310, 3, 'login', 'user', 3, 'Signed in', '::1', '2026-10-01 11:21:37'),
(1311, 1, 'login', 'user', 1, 'Signed in', '::1', '2026-10-01 11:21:37'),
(1312, 3, 'login', 'user', 3, 'Signed in', '::1', '2026-10-01 11:21:38'),
(1313, 1, 'login', 'user', 1, 'Signed in', '::1', '2026-10-01 11:21:38'),
(1325, 1, 'login', 'user', 1, 'Signed in', '::1', '2026-10-01 11:21:56'),
(1326, 3, 'login', 'user', 3, 'Signed in', '::1', '2026-10-01 11:21:56'),
(1327, 1, 'login', 'user', 1, 'Signed in', '::1', '2026-10-01 11:21:57'),
(1328, 3, 'login', 'user', 3, 'Signed in', '::1', '2026-10-01 11:21:57'),
(1329, 1, 'login', 'user', 1, 'Signed in', '::1', '2026-10-01 11:21:57'),
(1345, NULL, 'password_change', 'user', 182, 'Changed own password', '0.0.0.0', '2026-10-01 11:23:28'),
(1346, 1, 'login', 'user', 1, 'Signed in', '::1', '2026-10-01 11:23:34'),
(1347, 3, 'login', 'user', 3, 'Signed in', '::1', '2026-10-01 11:23:34'),
(1348, 1, 'login', 'user', 1, 'Signed in', '::1', '2026-10-01 11:38:05'),
(1349, 3, 'login', 'user', 3, 'Signed in', '::1', '2026-10-01 11:38:06'),
(1374, NULL, 'password_change', 'user', 190, 'Changed own password', '0.0.0.0', '2026-10-01 11:38:46'),
(1375, 1, 'create', 'user', 191, 'Added Bala Tanko as student', '::1', '2026-10-01 12:20:38'),
(1376, 1, 'logout', 'user', 1, 'Signed out', '::1', '2026-10-01 13:02:09');

-- --------------------------------------------------------

--
-- Table structure for table `bookings`
--

CREATE TABLE `bookings` (
  `id` int(11) NOT NULL,
  `booking_ref` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `user_id` int(11) NOT NULL,
  `laboratory_id` int(11) NOT NULL,
  `booking_date` date NOT NULL,
  `start_time` time NOT NULL,
  `end_time` time NOT NULL,
  `purpose` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `status` enum('pending','approved','rejected','cancelled','completed') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `reviewed_by` int(11) DEFAULT NULL,
  `reviewed_at` datetime DEFAULT NULL,
  `admin_note` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `bookings`
--

INSERT INTO `bookings` (`id`, `booking_ref`, `user_id`, `laboratory_id`, `booking_date`, `start_time`, `end_time`, `purpose`, `status`, `reviewed_by`, `reviewed_at`, `admin_note`, `created_at`, `updated_at`) VALUES
(1, 'BK-2026-0001', 3, 1, '2026-09-10', '08:00:00', '12:00:00', 'Data Structures practical session', 'completed', 1, '2026-09-10 09:00:00', NULL, '2026-09-10 07:30:00', '2026-09-30 22:28:27'),
(2, 'BK-2026-0002', 4, 2, '2026-09-12', '13:00:00', '16:00:00', 'Networking fundamentals practical', 'completed', 1, '2026-09-12 09:00:00', NULL, '2026-09-12 07:30:00', '2026-09-30 22:28:27'),
(3, 'BK-2026-0003', 5, 3, '2026-09-15', '08:00:00', '11:00:00', 'Linux shell scripting workshop', 'completed', 1, '2026-09-15 09:00:00', NULL, '2026-09-15 07:30:00', '2026-09-30 22:28:27'),
(4, 'BK-2026-0004', 6, 1, '2026-09-18', '13:00:00', '17:00:00', 'Web development practical', 'completed', 1, '2026-09-18 09:00:00', NULL, '2026-09-18 07:30:00', '2026-09-30 22:28:27'),
(5, 'BK-2026-0005', 7, 4, '2026-09-20', '09:00:00', '12:00:00', 'Cyber security awareness session', 'completed', 1, '2026-09-20 09:00:00', NULL, '2026-09-20 07:30:00', '2026-09-30 22:28:27'),
(6, 'BK-2026-0006', 8, 1, '2026-09-23', '08:00:00', '12:00:00', 'Operating systems practical', 'approved', 1, '2026-09-23 09:00:00', NULL, '2026-09-23 07:30:00', '2026-09-30 22:28:27'),
(7, 'BK-2026-0007', 9, 2, '2026-09-25', '13:00:00', '16:00:00', 'Cloud computing fundamentals', 'approved', 1, '2026-09-25 09:00:00', NULL, '2026-09-25 07:30:00', '2026-09-30 22:28:27'),
(8, 'BK-2026-0008', 10, 3, '2026-10-01', '08:00:00', '11:00:00', 'Database systems practical', 'approved', 1, '2026-10-01 09:00:00', NULL, '2026-10-01 07:30:00', '2026-09-30 22:28:27'),
(9, 'BK-2026-0009', 11, 1, '2026-10-02', '13:00:00', '16:00:00', 'Python programming lab', 'approved', 1, '2026-10-02 09:00:00', NULL, '2026-10-02 07:30:00', '2026-09-30 22:28:27'),
(10, 'BK-2026-0010', 12, 4, '2026-10-03', '09:00:00', '12:00:00', 'Network configuration workshop', 'approved', 1, '2026-10-03 09:00:00', NULL, '2026-10-03 07:30:00', '2026-09-30 22:28:27'),
(11, 'BK-2026-0011', 3, 2, '2026-10-04', '08:00:00', '10:00:00', 'Packet analysis practical', 'pending', NULL, NULL, NULL, '2026-10-04 07:30:00', '2026-09-30 19:32:19'),
(12, 'BK-2026-0012', 4, 1, '2026-10-05', '13:00:00', '15:00:00', 'Group project work', 'pending', NULL, NULL, NULL, '2026-10-05 07:30:00', '2026-09-30 19:32:19'),
(13, 'BK-2026-0013', 5, 3, '2026-10-06', '08:00:00', '11:00:00', 'Virtualisation hands-on', 'pending', NULL, NULL, NULL, '2026-10-06 07:30:00', '2026-09-30 19:32:19'),
(14, 'BK-2026-0014', 6, 4, '2026-10-07', '14:00:00', '17:00:00', 'Ethical hacking seminar', 'pending', NULL, NULL, NULL, '2026-10-07 07:30:00', '2026-09-30 19:32:19'),
(15, 'BK-2026-0015', 7, 1, '2026-10-08', '08:00:00', '12:00:00', 'Compiler design practical', 'pending', NULL, NULL, NULL, '2026-10-08 07:30:00', '2026-09-30 19:32:19'),
(16, 'BK-2026-0016', 8, 2, '2026-10-09', '15:00:00', '17:00:00', 'Server configuration practice', 'pending', NULL, NULL, NULL, '2026-10-09 07:30:00', '2026-09-30 19:32:19'),
(17, 'BK-2026-0017', 9, 3, '2026-09-27', '08:00:00', '12:00:00', 'Extra tutoring session', 'rejected', 1, '2026-09-27 09:00:00', 'Lab was reserved for faculty training', '2026-09-27 07:30:00', '2026-09-30 22:28:27'),
(18, 'BK-2026-0018', 10, 4, '2026-09-28', '13:00:00', '16:00:00', 'Student club meeting', 'rejected', 1, '2026-09-28 09:00:00', 'Clashes with scheduled maintenance', '2026-09-28 07:30:00', '2026-09-30 22:28:27'),
(19, 'BK-2026-0019', 11, 1, '2026-09-29', '13:00:00', '15:00:00', 'Personal study session', 'cancelled', NULL, NULL, NULL, '2026-09-29 07:30:00', '2026-09-30 19:32:19'),
(20, 'BK-2026-0020', 12, 2, '2026-10-10', '08:00:00', '11:00:00', 'Preparation for final examinations', 'cancelled', NULL, NULL, NULL, '2026-10-10 07:30:00', '2026-09-30 19:32:19'),
(67, 'BK-2026-0021', 3, 3, '2026-10-02', '09:00:00', '11:00:00', 'Praactical', 'rejected', 1, '2026-09-30 22:17:57', 'just like that', '2026-09-30 19:47:25', '2026-09-30 22:28:27');

-- --------------------------------------------------------

--
-- Table structure for table `laboratories`
--

CREATE TABLE `laboratories` (
  `id` int(11) NOT NULL,
  `name` varchar(120) COLLATE utf8mb4_unicode_ci NOT NULL,
  `location` varchar(120) COLLATE utf8mb4_unicode_ci NOT NULL,
  `capacity` int(11) NOT NULL DEFAULT '0',
  `computer_count` int(11) NOT NULL DEFAULT '0',
  `description` text COLLATE utf8mb4_unicode_ci,
  `status` enum('available','maintenance','inactive') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'available',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `laboratories`
--

INSERT INTO `laboratories` (`id`, `name`, `location`, `capacity`, `computer_count`, `description`, `status`, `created_at`, `updated_at`) VALUES
(1, 'Computer Laboratory 1', 'Block A - Room 101', 40, 40, 'General purpose teaching laboratory with 40 dual-core workstations.', 'available', '2026-09-30 19:32:19', '2026-09-30 19:32:19'),
(2, 'Computer Laboratory 2', 'Block A - Room 102', 35, 35, 'General purpose teaching laboratory with 35 dual-core workstations.', 'available', '2026-09-30 19:32:19', '2026-09-30 19:32:19'),
(3, 'Networking Laboratory', 'Block B - Lab 2', 25, 25, 'Isolated network for routing, switching and firewall practicals.', 'available', '2026-09-30 19:32:19', '2026-09-30 19:32:19'),
(4, 'Advanced Computing Lab', 'Block B - Lab 3', 20, 20, 'High-performance lab for virtualisation, cloud and cluster work.', 'available', '2026-09-30 19:32:19', '2026-09-30 19:32:19'),
(5, 'Computer Laboratory 3', 'Block C - Room 201', 28, 28, 'Reserved for remedial sessions. Currently being rewired.', 'maintenance', '2026-09-30 19:32:19', '2026-09-30 22:22:02');

-- --------------------------------------------------------

--
-- Table structure for table `notifications`
--

CREATE TABLE `notifications` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `title` varchar(120) COLLATE utf8mb4_unicode_ci NOT NULL,
  `message` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `link` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `is_read` tinyint(1) NOT NULL DEFAULT '0',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `notifications`
--

INSERT INTO `notifications` (`id`, `user_id`, `title`, `message`, `link`, `is_read`, `created_at`) VALUES
(1, 8, 'Booking approved', 'Your booking BK-2026-0006 for Computer Laboratory 1 on 2026-09-23 was approved.', NULL, 0, '2026-09-23 09:00:00'),
(2, 9, 'Booking approved', 'Your booking BK-2026-0007 for Computer Laboratory 2 on 2026-09-25 was approved.', NULL, 0, '2026-09-25 09:00:00'),
(3, 10, 'Booking approved', 'Your booking BK-2026-0008 for Networking Laboratory on 2026-10-01 was approved.', NULL, 0, '2026-10-01 09:00:00'),
(4, 11, 'Booking approved', 'Your booking BK-2026-0009 for Computer Laboratory 1 on 2026-10-02 was approved.', NULL, 0, '2026-10-02 09:00:00'),
(5, 12, 'Booking approved', 'Your booking BK-2026-0010 for Advanced Computing Lab on 2026-10-03 was approved.', NULL, 0, '2026-10-03 09:00:00'),
(6, 9, 'Booking rejected', 'Your booking BK-2026-0017 for Networking Laboratory was rejected. Lab was reserved for faculty training', NULL, 0, '2026-09-27 09:00:00'),
(7, 10, 'Booking rejected', 'Your booking BK-2026-0018 for Advanced Computing Lab was rejected. Clashes with scheduled maintenance', NULL, 0, '2026-09-28 09:00:00'),
(135, 3, 'Booking rejected', 'Networking Laboratory on 02 Oct 2026, 09:00 - 11:00. Reference BK-2026-0021. Reason: just like that', 'user/booking.php?id=67', 0, '2026-09-30 22:17:57');

-- --------------------------------------------------------

--
-- Table structure for table `time_slots`
--

CREATE TABLE `time_slots` (
  `id` int(11) NOT NULL,
  `label` varchar(60) COLLATE utf8mb4_unicode_ci NOT NULL,
  `start_time` time NOT NULL,
  `end_time` time NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `time_slots`
--

INSERT INTO `time_slots` (`id`, `label`, `start_time`, `end_time`, `is_active`, `created_at`, `updated_at`) VALUES
(1, 'Morning Session', '08:00:00', '12:00:00', 1, '2026-09-30 19:32:18', '2026-09-30 19:32:18'),
(2, 'Afternoon Session', '13:00:00', '17:00:00', 1, '2026-09-30 19:32:18', '2026-09-30 19:32:18'),
(3, 'Evening Session', '17:00:00', '20:00:00', 1, '2026-09-30 19:32:18', '2026-09-30 19:32:18'),
(4, 'Weekend Hours', '09:00:00', '13:00:00', 0, '2026-09-30 19:32:18', '2026-09-30 19:32:18');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `name` varchar(120) COLLATE utf8mb4_unicode_ci NOT NULL,
  `email` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `password` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `role` enum('admin','user') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'user',
  `status` enum('active','inactive') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'active',
  `student_no` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `phone` varchar(30) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `avatar` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `last_login_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `name`, `email`, `password`, `role`, `status`, `student_no`, `phone`, `avatar`, `last_login_at`, `created_at`, `updated_at`) VALUES
(1, 'System Administrator', 'admin@lab.edu.zm', '$2y$10$iBgFKJJHoPgDluNGWmZ2G.z.et9xESfCbGvDAQbUia.eUIcfoGOPG', 'admin', 'active', 'NCC-STAFF-001', '+260 971 000 101', NULL, '2026-10-01 11:38:05', '2026-09-30 22:27:56', '2026-10-01 11:38:45'),
(2, 'Dr. Thandiwe Banda', 'lecturer@lab.edu.zm', '$2y$10$Vd1by8YJUVZIasI9CoYpzOHdbfYf6RCpoaFllg6D2IrBdDrVwhGA.', 'admin', 'active', 'NCC-STAFF-002', '+260 971 000 102', NULL, '2026-09-30 22:11:11', '2026-09-30 19:32:19', '2026-10-01 11:38:45'),
(3, 'Chisoka Mwange', 'student@lab.edu.zm', '$2y$10$74tTsMi4VtD.BOBKoWaY7e0AFwVYiCGoWIsKt2Uqy7T/SvKnvJqum', 'user', 'active', 'NCC-2026-001', '+260 972 100 001', NULL, '2026-10-01 11:38:06', '2026-09-30 19:32:19', '2026-10-01 11:38:06'),
(4, 'Mwape Chanda', 'mubanga@lab.edu.zm', '$2y$10$RoVMQqAeymtcBxmNN42V4.mblbxluL2EbTgnyLZKEtkOFNn1xPIYy', 'user', 'active', 'NCC-2026-002', '+260 972 100 002', NULL, '2026-09-30 22:39:22', '2026-09-30 19:32:19', '2026-09-30 22:39:22'),
(5, 'Kalusha Phiri', 'kphiri@lab.edu.zm', '$2y$10$s57IETkV9.Cv6zIU.OTli.uq0yuWsWYPiv9txk1a0OjEQVnRI2XI.', 'user', 'active', 'NCC-2026-003', '+260 972 100 003', NULL, '2026-09-30 22:11:12', '2026-09-30 19:32:19', '2026-09-30 22:11:12'),
(6, 'Naledi Zulu', 'nzulu@lab.edu.zm', '$2y$10$1C.K0PZR2JuAxhbF.HWoi.Dha6Z6aV48BQmAKKQMlg8zTm86bHRSC', 'user', 'active', 'NCC-2026-004', '+260 972 100 004', NULL, '2026-09-30 22:11:12', '2026-09-30 19:32:19', '2026-09-30 22:11:12'),
(7, 'Chibwe Mumba', 'cmumba@lab.edu.zm', '$2y$10$YHT7VEZSBrI6tVut2Ah4IO4/qJCM/65ayj/zhMSLxsy32LsdCM4V2', 'user', 'active', 'NCC-2026-005', '+260 972 100 005', NULL, '2026-09-30 22:11:12', '2026-09-30 19:32:19', '2026-09-30 22:11:12'),
(8, 'Sikwandiwe Banda', 'sbanda@lab.edu.zm', '$2y$10$o7xcD6FfMeg5gdCOpcHQNuR/N9gXvIFqDTjCL6TX1V5u9XLPbi0Iu', 'user', 'active', 'NCC-2026-006', '+260 972 100 006', NULL, '2026-09-30 22:11:12', '2026-09-30 19:32:19', '2026-09-30 22:11:12'),
(9, 'Mutinta Kasonde', 'mkasonde@lab.edu.zm', '$2y$10$pG6fFfX1DFu.XzeHL3dNBuRAiIgZvLRLWMKTYxBw2jpAz8jnDgrba', 'user', 'active', 'NCC-2026-007', '+260 972 100 007', NULL, '2026-09-30 22:11:12', '2026-09-30 19:32:19', '2026-09-30 22:11:12'),
(10, 'Joseph Mwape', 'jmwape@lab.edu.zm', '$2y$10$/z0nq3Pjr0UN6sX1z/65BO2LUPBMsNqMS/ltbjvm9bAevryNUVWmq', 'user', 'active', 'NCC-2026-008', '+260 972 100 008', NULL, '2026-09-30 22:11:13', '2026-09-30 19:32:19', '2026-09-30 22:11:13'),
(11, 'Thandiwe Ncube', 'tncube@lab.edu.zm', '$2y$10$knaIP6KFv5tf2AiQYv6lROVZMhYOlN/z4hWnfTEvTCZNNDLrFLiKq', 'user', 'active', 'NCC-2026-009', '+260 972 100 009', NULL, '2026-09-30 22:11:13', '2026-09-30 19:32:19', '2026-09-30 22:11:13'),
(12, 'Kabaso Phiri', 'kphiri2@lab.edu.zm', '$2y$10$rdeZjEZk8yPQooTsmjeWJOmrJp3EnJ10lPdG3fvjtdUlUQhdH27g2', 'user', 'active', 'NCC-2026-010', '+260 972 100 010', NULL, '2026-09-30 22:11:13', '2026-09-30 19:32:19', '2026-09-30 22:11:13'),
(191, 'Bala Tanko', 'bala@gmail.com', '$2y$10$zTbPjwUPLFrm9pY5wIuWA.XigWxX0wf1T6H/Lu7o.uV9S4O9S3rl2', 'user', 'active', 'KPT/CST/COM/0001', '09086543456789', NULL, NULL, '2026-10-01 12:20:38', '2026-10-01 12:20:38');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `activity_logs`
--
ALTER TABLE `activity_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_activity_user` (`user_id`),
  ADD KEY `idx_activity_created` (`created_at`);

--
-- Indexes for table `bookings`
--
ALTER TABLE `bookings`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_bookings_ref` (`booking_ref`),
  ADD KEY `idx_bookings_conflict` (`laboratory_id`,`booking_date`,`status`),
  ADD KEY `idx_bookings_user` (`user_id`),
  ADD KEY `idx_bookings_status` (`status`),
  ADD KEY `idx_bookings_created` (`created_at`),
  ADD KEY `fk_bookings_reviewer` (`reviewed_by`);

--
-- Indexes for table `laboratories`
--
ALTER TABLE `laboratories`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_laboratories_name` (`name`),
  ADD KEY `idx_laboratories_status` (`status`);

--
-- Indexes for table `notifications`
--
ALTER TABLE `notifications`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_notifications_user_unread` (`user_id`,`is_read`);

--
-- Indexes for table `time_slots`
--
ALTER TABLE `time_slots`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_time_slots_range` (`start_time`,`end_time`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_users_email` (`email`),
  ADD UNIQUE KEY `uq_users_student_no` (`student_no`),
  ADD KEY `idx_users_role_status` (`role`,`status`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `activity_logs`
--
ALTER TABLE `activity_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=1377;
--
-- AUTO_INCREMENT for table `bookings`
--
ALTER TABLE `bookings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=663;
--
-- AUTO_INCREMENT for table `laboratories`
--
ALTER TABLE `laboratories`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=83;
--
-- AUTO_INCREMENT for table `notifications`
--
ALTER TABLE `notifications`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=296;
--
-- AUTO_INCREMENT for table `time_slots`
--
ALTER TABLE `time_slots`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=76;
--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=192;
--
-- Constraints for dumped tables
--

--
-- Constraints for table `activity_logs`
--
ALTER TABLE `activity_logs`
  ADD CONSTRAINT `fk_activity_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Constraints for table `bookings`
--
ALTER TABLE `bookings`
  ADD CONSTRAINT `fk_bookings_laboratory` FOREIGN KEY (`laboratory_id`) REFERENCES `laboratories` (`id`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_bookings_reviewer` FOREIGN KEY (`reviewed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_bookings_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON UPDATE CASCADE;

--
-- Constraints for table `notifications`
--
ALTER TABLE `notifications`
  ADD CONSTRAINT `fk_notifications_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
