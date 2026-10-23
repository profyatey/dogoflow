-- phpMyAdmin SQL Dump
-- version 5.2.3
-- https://www.phpmyadmin.net/
--
-- Host: localhost:3306
-- Generation Time: Oct 19, 2026 at 12:34 PM
-- Server version: 10.11.19-MariaDB-cll-lve
-- PHP Version: 8.3.35

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `mosktfeq_dogo`
--

-- --------------------------------------------------------

--
-- Table structure for table `affiliate_clicks`
--

CREATE TABLE `affiliate_clicks` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `affiliate_id` bigint(20) UNSIGNED NOT NULL,
  `product_id` bigint(20) UNSIGNED DEFAULT NULL,
  `ip_hash` char(64) DEFAULT NULL,
  `user_agent` varchar(500) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Dumping data for table `affiliate_clicks`
--

INSERT INTO `affiliate_clicks` (`id`, `affiliate_id`, `product_id`, `ip_hash`, `user_agent`, `created_at`) VALUES
(1, 1, NULL, 'f3e456b81d9fa4b56b8fed958b645991f30b14e8c4eebd04040cf498bb1a0e30', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Mobile Safari/537.36', '2026-10-07 10:03:38'),
(2, 1, NULL, 'aa76e2b9e33a2f308e7aff366b96c586721b18714894fcd740cf002a30b52b4c', 'WhatsApp/2.23.20.0', '2026-10-08 09:21:23'),
(3, 1, NULL, '568e2b428b93d1d1f5222f55e342aad9a329f6f4efd56cd9d525d5b6a81a44fe', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Mobile Safari/537.36', '2026-10-08 09:21:41'),
(4, 1, NULL, '568e2b428b93d1d1f5222f55e342aad9a329f6f4efd56cd9d525d5b6a81a44fe', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Mobile Safari/537.36', '2026-10-08 09:28:54'),
(5, 1, NULL, 'c77a57c06eb783396d9090f3998dbd58ee8a12c2f2e0fe66f2af839e4571a072', 'WhatsApp/2.23.20.0', '2026-10-12 18:48:44');

-- --------------------------------------------------------

--
-- Table structure for table `affiliate_profiles`
--

CREATE TABLE `affiliate_profiles` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `user_id` bigint(20) UNSIGNED NOT NULL,
  `referral_code` varchar(40) NOT NULL,
  `status` enum('active','suspended') NOT NULL DEFAULT 'active',
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Dumping data for table `affiliate_profiles`
--

INSERT INTO `affiliate_profiles` (`id`, `user_id`, `referral_code`, `status`, `created_at`) VALUES
(1, 3, 'SIM521', 'active', '2026-09-29 12:02:28');

-- --------------------------------------------------------

--
-- Table structure for table `affiliate_referrals`
--

CREATE TABLE `affiliate_referrals` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `affiliate_id` bigint(20) UNSIGNED NOT NULL,
  `referred_user_id` bigint(20) UNSIGNED NOT NULL,
  `referral_code` varchar(40) NOT NULL,
  `status` enum('registered','verified','qualified','rejected') NOT NULL DEFAULT 'registered',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `qualified_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------

--
-- Table structure for table `affiliate_rewards`
--

CREATE TABLE `affiliate_rewards` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `affiliate_id` bigint(20) UNSIGNED NOT NULL,
  `referral_id` bigint(20) UNSIGNED NOT NULL,
  `amount` decimal(12,2) NOT NULL DEFAULT 10.00,
  `status` enum('pending','available','paid','reversed') NOT NULL DEFAULT 'available',
  `reference` varchar(100) NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `paid_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------

--
-- Table structure for table `audit_logs`
--

CREATE TABLE `audit_logs` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `actor_user_id` bigint(20) UNSIGNED DEFAULT NULL,
  `action` varchar(100) NOT NULL,
  `entity_type` varchar(80) DEFAULT NULL,
  `entity_id` bigint(20) UNSIGNED DEFAULT NULL,
  `old_values` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`old_values`)),
  `new_values` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`new_values`)),
  `ip_address` varchar(45) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Dumping data for table `audit_logs`
--

INSERT INTO `audit_logs` (`id`, `actor_user_id`, `action`, `entity_type`, `entity_id`, `old_values`, `new_values`, `ip_address`, `created_at`) VALUES
(1, NULL, 'product_removed', 'product', 8, '{\"name\":\"Shirt\",\"seller_id\":11,\"status\":\"active\"}', NULL, '154.161.101.105', '2026-10-19 09:56:48'),
(9, 1, 'delete_unverified_account', 'user', 18, '{\"name\":\"Charles Quist\",\"email\":\"albertquistcharles@gmail.com\",\"role\":\"affiliate\",\"status\":\"pending\",\"email_verified_at\":null}', '{\"deleted\":true}', '154.161.101.105', '2026-10-19 10:47:12');

-- --------------------------------------------------------

--
-- Table structure for table `carts`
--

CREATE TABLE `carts` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `customer_id` bigint(20) UNSIGNED NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------

--
-- Table structure for table `cart_items`
--

CREATE TABLE `cart_items` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `cart_id` bigint(20) UNSIGNED NOT NULL,
  `product_id` bigint(20) UNSIGNED NOT NULL,
  `quantity` int(10) UNSIGNED NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------

--
-- Table structure for table `categories`
--

CREATE TABLE `categories` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `parent_id` bigint(20) UNSIGNED DEFAULT NULL,
  `name` varchar(120) NOT NULL,
  `slug` varchar(140) NOT NULL,
  `description` text DEFAULT NULL,
  `image` varchar(255) DEFAULT NULL,
  `status` enum('active','inactive') NOT NULL DEFAULT 'active',
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Dumping data for table `categories`
--

INSERT INTO `categories` (`id`, `parent_id`, `name`, `slug`, `description`, `image`, `status`, `sort_order`, `created_at`, `updated_at`) VALUES
(1, NULL, 'Fashion & Clothing', 'fashion-clothing', NULL, NULL, 'active', 0, '2026-09-29 15:03:23', '2026-09-29 15:03:23'),
(2, NULL, 'Phones & Accessories', 'phones-accessories', NULL, NULL, 'active', 0, '2026-09-29 15:04:12', '2026-09-29 15:04:12'),
(3, NULL, 'Computers & Accessories', 'computers-accessories', NULL, NULL, 'active', 0, '2026-09-29 15:04:25', '2026-09-29 15:04:25'),
(4, NULL, 'Electronics', 'electronics', NULL, NULL, 'active', 0, '2026-09-29 15:04:39', '2026-09-29 15:04:39'),
(5, NULL, 'Home & Living', 'home-living', NULL, NULL, 'active', 0, '2026-09-29 15:04:55', '2026-10-16 09:01:09'),
(6, NULL, 'Shoes & Footwear', 'shoes-footwear', NULL, NULL, 'active', 0, '2026-09-29 15:05:09', '2026-09-29 15:05:09'),
(7, NULL, 'Beauty & Personal Care', 'beauty-personal-care', NULL, NULL, 'active', 0, '2026-09-29 15:05:22', '2026-09-29 15:05:22'),
(8, NULL, 'Books & Stationery', 'books-stationery', NULL, NULL, 'active', 0, '2026-09-29 15:05:42', '2026-09-29 15:05:42');

-- --------------------------------------------------------

--
-- Table structure for table `commissions`
--

CREATE TABLE `commissions` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `affiliate_id` bigint(20) UNSIGNED NOT NULL,
  `order_id` bigint(20) UNSIGNED NOT NULL,
  `amount` decimal(12,2) NOT NULL,
  `status` enum('pending','available','paid','reversed') NOT NULL DEFAULT 'pending',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------

--
-- Table structure for table `email_verifications`
--

CREATE TABLE `email_verifications` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `user_id` bigint(20) UNSIGNED NOT NULL,
  `token_hash` char(64) NOT NULL,
  `expires_at` datetime NOT NULL,
  `verified_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Dumping data for table `email_verifications`
--

INSERT INTO `email_verifications` (`id`, `user_id`, `token_hash`, `expires_at`, `verified_at`, `created_at`) VALUES
(2, 3, 'b64f95e067f98a1f69704897b317f69cc217aadfe3d1c83759fad561ed2d67e6', '2026-09-30 12:02:28', '2026-09-29 12:02:33', '2026-09-29 12:02:28'),
(10, 11, '945ceec8203139ea7db9f4abad8a9aa7a43085545bd636c0dce0d669d6c0443c', '2026-10-14 13:34:31', '2026-10-13 13:35:06', '2026-10-13 13:34:31'),
(11, 12, 'e4c88d9ddd360b3bcc75d8b0178559196e6f6874367156f44c9b582fa3bea746', '2026-10-14 13:42:40', '2026-10-13 13:43:18', '2026-10-13 13:42:40'),
(12, 13, '0beaac238f83f19817e8525b2ce284376bc52175e3401dbab855327cdc01826c', '2026-10-14 22:20:00', '2026-10-13 22:22:17', '2026-10-13 22:20:00'),
(13, 14, 'bb14a2441bd27e217e40ac702fd4a399c0f8cc3fdec712484b704d6ac48d22d4', '2026-10-14 22:57:31', '2026-10-13 22:58:09', '2026-10-13 22:57:31'),
(14, 15, '0ebd9c0714bf70f05f1a81162598968b9632d14783d20a21d99b69932e0b13f9', '2026-10-15 04:31:04', '2026-10-14 05:31:22', '2026-10-14 04:31:04'),
(16, 17, '14a70b631ab596228b0c08f2495f25a4acaf588ea554b84338193815d5014023', '2026-10-16 18:33:09', '2026-10-15 18:34:14', '2026-10-15 18:33:09');

-- --------------------------------------------------------

--
-- Table structure for table `marketplace_view_events`
--

CREATE TABLE `marketplace_view_events` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `seller_profile_id` bigint(20) UNSIGNED NOT NULL,
  `product_id` bigint(20) UNSIGNED DEFAULT NULL,
  `event_type` enum('store_view','product_view') NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------

--
-- Table structure for table `notifications`
--

CREATE TABLE `notifications` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `user_id` bigint(20) UNSIGNED NOT NULL,
  `channel` enum('email','sms','in_app') NOT NULL,
  `subject` varchar(180) DEFAULT NULL,
  `message` text NOT NULL,
  `status` enum('queued','sent','failed') NOT NULL DEFAULT 'queued',
  `attempts` tinyint(3) UNSIGNED NOT NULL DEFAULT 0,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `sent_at` datetime DEFAULT NULL,
  `read_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Dumping data for table `notifications`
--

INSERT INTO `notifications` (`id`, `user_id`, `channel`, `subject`, `message`, `status`, `attempts`, `created_at`, `sent_at`, `read_at`) VALUES
(24, 11, 'email', 'Verify your DogoFlow account', 'Hello Simon Abayeteye,\n\nPlease verify your DogoFlow account using this link:\nhttps://www.dogoflow.com/auth/verify.php?token=8121781a2a44a2cf6f63abd5fd52132093f3f0ba8435663550478effe74908e2\n\nThis verification link expires in 24 hours.\n\nDogoFlow', 'sent', 1, '2026-10-13 13:34:31', '2026-10-13 13:34:31', NULL),
(26, 12, 'email', 'Verify your DogoFlow account', 'Hello Prof Simon,\n\nPlease verify your DogoFlow account using this link:\nhttps://www.dogoflow.com/auth/verify.php?token=812c089af522cf8c0e163d869c0ba24f3476d84614f1cc5f7d9513c1e9384ef3\n\nThis verification link expires in 24 hours.\n\nDogoFlow', 'sent', 1, '2026-10-13 13:42:40', '2026-10-13 13:42:40', NULL),
(28, 13, 'email', 'Verify your DogoFlow account', 'Hello Gideon Adjei,\n\nPlease verify your DogoFlow account using this link:\nhttps://www.dogoflow.com/auth/verify.php?token=68d2986da631ddeb9210ad8b37f2e6f716540944d276826f1e5eeb6d71ef475e\n\nThis verification link expires in 24 hours.\n\nDogoFlow', 'sent', 1, '2026-10-13 22:20:00', '2026-10-13 22:20:00', NULL),
(30, 14, 'email', 'Verify your DogoFlow account', 'Hello Felix  Konadu,\n\nPlease verify your DogoFlow account using this link:\nhttps://www.dogoflow.com/auth/verify.php?token=5238c1226c39c3bfb0061d2bab9692cce423dd4e029c543d6cb165fad2d86ab2\n\nThis verification link expires in 24 hours.\n\nDogoFlow', 'sent', 1, '2026-10-13 22:57:31', '2026-10-13 22:57:31', NULL),
(32, 15, 'email', 'Verify your DogoFlow account', 'Hello Customer,\n\nPlease verify your DogoFlow account using this link:\nhttps://www.dogoflow.com/auth/verify.php?token=ab2131b5f3be1a1056b319987fee3843f1090b8648073e3563b457d338608e97\n\nThis verification link expires in 24 hours.\n\nDogoFlow', 'sent', 1, '2026-10-14 04:31:04', '2026-10-14 04:31:04', NULL),
(35, 17, 'email', 'Verify your DogoFlow account', 'Hello Cimon,\n\nPlease verify your DogoFlow account using this link:\nhttps://www.dogoflow.com/auth/verify.php?token=4ba01254deed0f5e93799f14b386384468ea95123a1e7d88a3c30fa14e99fa7a\n\nThis verification link expires in 24 hours.\n\nDogoFlow', 'sent', 1, '2026-10-15 18:33:09', '2026-10-15 18:33:09', '2026-10-15 18:38:08');

-- --------------------------------------------------------

--
-- Table structure for table `orders`
--

CREATE TABLE `orders` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `customer_id` bigint(20) UNSIGNED NOT NULL,
  `order_number` varchar(40) NOT NULL,
  `subtotal` decimal(12,2) NOT NULL,
  `delivery_fee` decimal(12,2) NOT NULL DEFAULT 0.00,
  `total` decimal(12,2) NOT NULL,
  `shipping_address` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`shipping_address`)),
  `customer_note` varchar(500) DEFAULT NULL,
  `status` enum('pending_payment','paid','processing','shipped','completed','cancelled','refunded') NOT NULL DEFAULT 'pending_payment',
  `paid_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------

--
-- Table structure for table `order_items`
--

CREATE TABLE `order_items` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `order_id` bigint(20) UNSIGNED NOT NULL,
  `product_id` bigint(20) UNSIGNED NOT NULL,
  `seller_id` bigint(20) UNSIGNED DEFAULT NULL,
  `quantity` int(10) UNSIGNED NOT NULL,
  `unit_price` decimal(12,2) NOT NULL,
  `subtotal` decimal(12,2) NOT NULL,
  `fulfillment_status` enum('paid','processing','shipped','completed','cancelled') NOT NULL DEFAULT 'paid',
  `seller_note` varchar(500) DEFAULT NULL,
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------

--
-- Table structure for table `order_status_history`
--

CREATE TABLE `order_status_history` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `order_id` bigint(20) UNSIGNED NOT NULL,
  `actor_user_id` bigint(20) UNSIGNED DEFAULT NULL,
  `from_status` varchar(40) DEFAULT NULL,
  `to_status` varchar(40) NOT NULL,
  `note` varchar(500) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------

--
-- Table structure for table `password_resets`
--

CREATE TABLE `password_resets` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `user_id` bigint(20) UNSIGNED NOT NULL,
  `token_hash` char(64) NOT NULL,
  `expires_at` datetime NOT NULL,
  `used_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------

--
-- Table structure for table `payments`
--

CREATE TABLE `payments` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `order_id` bigint(20) UNSIGNED NOT NULL,
  `reference` varchar(100) NOT NULL,
  `access_code` varchar(120) DEFAULT NULL,
  `gateway_transaction_id` bigint(20) UNSIGNED DEFAULT NULL,
  `amount` decimal(12,2) NOT NULL,
  `currency` char(3) NOT NULL DEFAULT 'GHS',
  `gateway` varchar(40) NOT NULL DEFAULT 'paystack',
  `status` enum('pending','success','failed','refunded') NOT NULL DEFAULT 'pending',
  `gateway_response` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`gateway_response`)),
  `verified_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------

--
-- Table structure for table `products`
--

CREATE TABLE `products` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `seller_id` bigint(20) UNSIGNED DEFAULT NULL,
  `name` varchar(180) NOT NULL,
  `slug` varchar(200) NOT NULL,
  `description` text DEFAULT NULL,
  `price` decimal(12,2) NOT NULL,
  `stock` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `image` varchar(255) DEFAULT NULL,
  `status` enum('draft','active','inactive','rejected') NOT NULL DEFAULT 'draft',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Dumping data for table `products`
--

INSERT INTO `products` (`id`, `seller_id`, `name`, `slug`, `description`, `price`, `stock`, `image`, `status`, `created_at`, `updated_at`) VALUES
(9, 13, 'Adidas Campus', 'adidas-campus', 'Size 38-45 available\r\nAvailable in different colours', 350.00, 8, 'uploads/products/7a9ca1963751e37e5f5327663bfc7776.jpg', 'active', '2026-10-13 22:43:29', '2026-10-13 22:53:28'),
(10, 14, 'AF1', 'af1', NULL, 300.00, 20, 'uploads/products/f5cfb22346233b70a2099eea43a29087.jpg', 'active', '2026-10-13 23:07:13', '2026-10-16 02:56:25'),
(11, 11, 'Shirt', 'shirt-2', 'Adade3', 100.00, 300, 'uploads/products/ec52c81f2e4f39f7d6b04b14e5e71120.png', 'active', '2026-10-14 05:58:14', '2026-10-16 08:57:27'),
(12, 13, 'Shoes', 'shoes', NULL, 350.00, 20, 'uploads/products/13292fb8a67ffda93b3aa8c95bafea14.jpg', 'active', '2026-10-14 07:45:11', '2026-10-14 07:49:43'),
(13, 14, 'Jordan 4', 'jordan-4', 'Quality Pink J4', 350.00, 15, 'uploads/products/dd128f7196350a06fef1abb580eae8fa.jpg', 'active', '2026-10-16 02:56:03', '2026-10-16 02:56:03');

-- --------------------------------------------------------

--
-- Table structure for table `product_categories`
--

CREATE TABLE `product_categories` (
  `product_id` bigint(20) UNSIGNED NOT NULL,
  `category_id` bigint(20) UNSIGNED NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Dumping data for table `product_categories`
--

INSERT INTO `product_categories` (`product_id`, `category_id`) VALUES
(9, 1),
(10, 6),
(11, 1),
(12, 6),
(13, 1),
(13, 6);

-- --------------------------------------------------------

--
-- Table structure for table `product_images`
--

CREATE TABLE `product_images` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `product_id` bigint(20) UNSIGNED NOT NULL,
  `image_path` varchar(255) NOT NULL,
  `is_primary` tinyint(1) NOT NULL DEFAULT 0,
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Dumping data for table `product_images`
--

INSERT INTO `product_images` (`id`, `product_id`, `image_path`, `is_primary`, `sort_order`, `created_at`) VALUES
(11, 9, 'uploads/products/7e878d50ac35deb5d6f0d6173179860d.jpg', 0, 1, '2026-10-13 22:43:29'),
(12, 9, 'uploads/products/7a9ca1963751e37e5f5327663bfc7776.jpg', 1, 0, '2026-10-13 22:43:29'),
(13, 9, 'uploads/products/d86c44b613b143aadb665604886280ca.jpg', 0, 2, '2026-10-13 22:43:29'),
(14, 10, 'uploads/products/f5cfb22346233b70a2099eea43a29087.jpg', 1, 0, '2026-10-13 23:07:13'),
(15, 11, 'uploads/products/22687c40759cd9390ec0f351c6cdf727.png', 0, 1, '2026-10-14 05:58:14'),
(16, 11, 'uploads/products/1a53fcce18a9d5de26500fbbb3f8d9cc.png', 0, 2, '2026-10-14 05:58:14'),
(17, 11, 'uploads/products/ec52c81f2e4f39f7d6b04b14e5e71120.png', 1, 0, '2026-10-14 05:58:14'),
(18, 11, 'uploads/products/63067bfe761263750794f071c008737f.png', 0, 3, '2026-10-14 05:58:14'),
(19, 11, 'uploads/products/b4e6fe5e54b26876c0db13b582c0e4ce.png', 0, 4, '2026-10-14 05:58:14'),
(20, 12, 'uploads/products/13292fb8a67ffda93b3aa8c95bafea14.jpg', 1, 0, '2026-10-14 07:45:11'),
(21, 12, 'uploads/products/2e739bb485470be9286d682c75f18c7e.jpg', 0, 1, '2026-10-14 07:45:11'),
(22, 12, 'uploads/products/86599d7cc7770c9978b79da353f4fef6.jpg', 0, 2, '2026-10-14 07:45:11'),
(23, 12, 'uploads/products/65e2a628b51eef6cee4c8d6f18e71965.jpg', 0, 3, '2026-10-14 07:45:11'),
(24, 12, 'uploads/products/52def40bc6affa38e4e5e32cec66da74.jpg', 0, 4, '2026-10-14 07:45:11'),
(25, 13, 'uploads/products/dd128f7196350a06fef1abb580eae8fa.jpg', 1, 0, '2026-10-16 02:56:03');

-- --------------------------------------------------------

--
-- Table structure for table `seller_categories`
--

CREATE TABLE `seller_categories` (
  `seller_id` bigint(20) UNSIGNED NOT NULL,
  `category_id` bigint(20) UNSIGNED NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Dumping data for table `seller_categories`
--

INSERT INTO `seller_categories` (`seller_id`, `category_id`) VALUES
(11, 1),
(14, 1),
(14, 6);

-- --------------------------------------------------------

--
-- Table structure for table `seller_profiles`
--

CREATE TABLE `seller_profiles` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `user_id` bigint(20) UNSIGNED NOT NULL,
  `business_name` varchar(180) NOT NULL,
  `store_slug` varchar(200) DEFAULT NULL,
  `store_logo` varchar(255) DEFAULT NULL,
  `store_banner` varchar(255) DEFAULT NULL,
  `store_description` text DEFAULT NULL,
  `store_location` varchar(180) DEFAULT NULL,
  `business_phone` varchar(30) DEFAULT NULL,
  `business_address` varchar(255) DEFAULT NULL,
  `status` enum('pending','active','suspended') NOT NULL DEFAULT 'pending',
  `approval_status` enum('pending','approved','rejected','suspended') NOT NULL DEFAULT 'pending',
  `rejection_reason` varchar(500) DEFAULT NULL,
  `reviewed_at` datetime DEFAULT NULL,
  `reviewed_by` bigint(20) UNSIGNED DEFAULT NULL,
  `seller_code` varchar(20) DEFAULT NULL,
  `plan` enum('free','pro') NOT NULL DEFAULT 'free',
  `photo_limit` int(10) UNSIGNED NOT NULL DEFAULT 5,
  `subscription_started_at` datetime DEFAULT NULL,
  `subscription_expires_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Dumping data for table `seller_profiles`
--

INSERT INTO `seller_profiles` (`id`, `user_id`, `business_name`, `store_slug`, `store_logo`, `store_banner`, `store_description`, `store_location`, `business_phone`, `business_address`, `status`, `approval_status`, `rejection_reason`, `reviewed_at`, `reviewed_by`, `seller_code`, `plan`, `photo_limit`, `subscription_started_at`, `subscription_expires_at`, `created_at`, `updated_at`) VALUES
(7, 11, 'My shop', 'ms', NULL, NULL, 'I sell all cloths and fabrics', 'Accra', '+233273406734', NULL, 'active', 'approved', NULL, NULL, NULL, 'DF-00001', 'pro', 30, '2026-10-13 13:37:59', '2027-10-13 13:37:59', '2026-10-13 13:34:31', '2026-10-13 16:25:41'),
(8, 12, 'SHOPPY', NULL, NULL, NULL, NULL, NULL, '+233273406734', NULL, 'active', 'approved', NULL, '2026-10-13 16:02:11', 1, 'DF-00002', 'pro', 30, '2026-10-13 13:44:51', '2027-10-13 13:44:51', '2026-10-13 13:42:40', '2026-10-13 16:25:41'),
(9, 13, 'Sneaker Hub', 'sneaker-hub', NULL, NULL, NULL, NULL, '053 640 9030', NULL, 'active', 'approved', NULL, '2026-10-13 22:23:16', 1, 'DF-00003', 'pro', 30, '2026-10-13 22:24:51', '2027-10-13 22:24:51', '2026-10-13 22:20:00', '2026-10-14 07:48:42'),
(10, 14, 'Kings\' Lux', 'kingslux', NULL, NULL, NULL, 'Accra, Ghana', '0594782488', NULL, 'active', 'approved', NULL, NULL, NULL, 'DF-00004', 'pro', 30, '2026-10-13 22:59:35', '2027-10-13 22:59:35', '2026-10-13 22:57:31', '2026-10-13 23:00:57');

-- --------------------------------------------------------

--
-- Table structure for table `seller_subscription_payments`
--

CREATE TABLE `seller_subscription_payments` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `user_id` bigint(20) UNSIGNED NOT NULL,
  `reference` varchar(100) NOT NULL,
  `access_code` varchar(120) DEFAULT NULL,
  `amount` decimal(12,2) NOT NULL DEFAULT 30.00,
  `currency` char(3) NOT NULL DEFAULT 'GHS',
  `gateway` varchar(40) NOT NULL DEFAULT 'paystack',
  `status` enum('pending','success','failed','refunded') NOT NULL DEFAULT 'pending',
  `gateway_transaction_id` bigint(20) UNSIGNED DEFAULT NULL,
  `gateway_response` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`gateway_response`)),
  `verified_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Dumping data for table `seller_subscription_payments`
--

INSERT INTO `seller_subscription_payments` (`id`, `user_id`, `reference`, `access_code`, `amount`, `currency`, `gateway`, `status`, `gateway_transaction_id`, `gateway_response`, `verified_at`, `created_at`) VALUES
(15, 11, 'DFPRO-11-8925043622356012', '1jd2380czg4fyhl', 30.00, 'GHS', 'paystack', 'success', 6653964433, '{\"id\":6653964433,\"domain\":\"test\",\"status\":\"success\",\"reference\":\"DFPRO-11-8925043622356012\",\"receipt_number\":\"10101\",\"amount\":3000,\"message\":null,\"gateway_response\":\"Approved\",\"paid_at\":\"2026-10-13T13:37:54.000Z\",\"created_at\":\"2026-10-13T13:37:45.000Z\",\"channel\":\"mobile_money\",\"currency\":\"GHS\",\"ip_address\":\"154.161.68.48\",\"metadata\":{\"purpose\":\"seller_pro_subscription\",\"user_id\":\"11\",\"plan\":\"pro\",\"referrer\":\"https://www.dogoflow.com/\"},\"log\":{\"start_time\":1791898669,\"time_spent\":4,\"attempts\":1,\"errors\":0,\"success\":false,\"mobile\":true,\"input\":[],\"history\":[{\"type\":\"action\",\"message\":\"Set payment method to: mobile_money\",\"time\":2},{\"type\":\"action\",\"message\":\"Attempted to pay with mobile money\",\"time\":4}]},\"fees\":59,\"fees_split\":null,\"authorization\":{\"authorization_code\":\"AUTH_zj7n7ivbub\",\"bin\":\"055XXX\",\"last4\":\"X987\",\"exp_month\":\"12\",\"exp_year\":\"9999\",\"channel\":\"mobile_money\",\"card_type\":\"\",\"bank\":\"MTN\",\"country_code\":\"GH\",\"brand\":\"Mtn\",\"reusable\":false,\"signature\":null,\"account_name\":null,\"mobile_money_number\":\"0551234987\",\"receiver_bank_account_number\":null,\"receiver_bank\":null},\"customer\":{\"id\":390482963,\"first_name\":null,\"last_name\":null,\"email\":\"professorsimon622@gmail.com\",\"customer_code\":\"CUS_a1mrvgpzmwgni2c\",\"phone\":null,\"metadata\":null,\"risk_action\":\"default\",\"international_format_phone\":null},\"plan\":null,\"split\":[],\"order_id\":null,\"paidAt\":\"2026-10-13T13:37:54.000Z\",\"createdAt\":\"2026-10-13T13:37:45.000Z\",\"requested_amount\":3000,\"pos_transaction_data\":null,\"source\":null,\"fees_breakdown\":null,\"connect\":null,\"transaction_date\":\"2026-10-13T13:37:45.000Z\",\"plan_object\":[],\"subaccount\":[]}', '2026-10-13 13:37:59', '2026-10-13 13:37:44'),
(16, 12, 'DFPRO-12-451221914FFD868E', 'm54sfx2lwb107l7', 30.00, 'GHS', 'paystack', 'success', 6653981962, '{\"id\":6653981962,\"domain\":\"test\",\"status\":\"success\",\"reference\":\"DFPRO-12-451221914FFD868E\",\"receipt_number\":\"10101\",\"amount\":3000,\"message\":null,\"gateway_response\":\"Approved\",\"paid_at\":\"2026-10-13T13:44:47.000Z\",\"created_at\":\"2026-10-13T13:44:29.000Z\",\"channel\":\"mobile_money\",\"currency\":\"GHS\",\"ip_address\":\"154.161.68.48\",\"metadata\":{\"purpose\":\"seller_pro_subscription\",\"user_id\":\"12\",\"plan\":\"pro\",\"referrer\":\"https://www.dogoflow.com/\"},\"log\":{\"start_time\":1791899073,\"time_spent\":13,\"attempts\":1,\"errors\":0,\"success\":false,\"mobile\":true,\"input\":[],\"history\":[{\"type\":\"action\",\"message\":\"Set payment method to: mobile_money\",\"time\":12},{\"type\":\"action\",\"message\":\"Attempted to pay with mobile money\",\"time\":13}]},\"fees\":59,\"fees_split\":null,\"authorization\":{\"authorization_code\":\"AUTH_ckzh9pj5xo\",\"bin\":\"055XXX\",\"last4\":\"X987\",\"exp_month\":\"12\",\"exp_year\":\"9999\",\"channel\":\"mobile_money\",\"card_type\":\"\",\"bank\":\"MTN\",\"country_code\":\"GH\",\"brand\":\"Mtn\",\"reusable\":false,\"signature\":null,\"account_name\":null,\"mobile_money_number\":\"0551234987\",\"receiver_bank_account_number\":null,\"receiver_bank\":null},\"customer\":{\"id\":393184489,\"first_name\":null,\"last_name\":null,\"email\":\"webyatey@gmail.com\",\"customer_code\":\"CUS_w4pyqd205q7plt9\",\"phone\":null,\"metadata\":null,\"risk_action\":\"default\",\"international_format_phone\":null},\"plan\":null,\"split\":[],\"order_id\":null,\"paidAt\":\"2026-10-13T13:44:47.000Z\",\"createdAt\":\"2026-10-13T13:44:29.000Z\",\"requested_amount\":3000,\"pos_transaction_data\":null,\"source\":null,\"fees_breakdown\":null,\"connect\":null,\"transaction_date\":\"2026-10-13T13:44:29.000Z\",\"plan_object\":[],\"subaccount\":[]}', '2026-10-13 13:44:51', '2026-10-13 13:44:29'),
(17, 13, 'DFPRO-13-57256E980917EA28', '6j9vlpykvy29vlb', 30.00, 'GHS', 'paystack', 'success', 6655424720, '{\"id\":6655424720,\"domain\":\"test\",\"status\":\"success\",\"reference\":\"DFPRO-13-57256E980917EA28\",\"receipt_number\":\"10101\",\"amount\":3000,\"message\":null,\"gateway_response\":\"Approved\",\"paid_at\":\"2026-10-13T22:24:42.000Z\",\"created_at\":\"2026-10-13T22:24:23.000Z\",\"channel\":\"mobile_money\",\"currency\":\"GHS\",\"ip_address\":\"154.161.77.210\",\"metadata\":{\"purpose\":\"seller_pro_subscription\",\"user_id\":\"13\",\"plan\":\"pro\",\"referrer\":\"https://www.dogoflow.com/\"},\"log\":{\"start_time\":1791930267,\"time_spent\":15,\"attempts\":1,\"errors\":0,\"success\":false,\"mobile\":false,\"input\":[],\"history\":[{\"type\":\"action\",\"message\":\"Attempted to pay with mobile money\",\"time\":15}]},\"fees\":59,\"fees_split\":null,\"authorization\":{\"authorization_code\":\"AUTH_f5rvupp3kz\",\"bin\":\"055XXX\",\"last4\":\"X987\",\"exp_month\":\"12\",\"exp_year\":\"9999\",\"channel\":\"mobile_money\",\"card_type\":\"\",\"bank\":\"MTN\",\"country_code\":\"GH\",\"brand\":\"Mtn\",\"reusable\":false,\"signature\":null,\"account_name\":null,\"mobile_money_number\":\"0551234987\",\"receiver_bank_account_number\":null,\"receiver_bank\":null},\"customer\":{\"id\":408420380,\"first_name\":null,\"last_name\":null,\"email\":\"yfwdeon006@gmail.com\",\"customer_code\":\"CUS_11diu7apawqo2fu\",\"phone\":null,\"metadata\":null,\"risk_action\":\"default\",\"international_format_phone\":null},\"plan\":null,\"split\":[],\"order_id\":null,\"paidAt\":\"2026-10-13T22:24:42.000Z\",\"createdAt\":\"2026-10-13T22:24:23.000Z\",\"requested_amount\":3000,\"pos_transaction_data\":null,\"source\":null,\"fees_breakdown\":null,\"connect\":null,\"transaction_date\":\"2026-10-13T22:24:23.000Z\",\"plan_object\":[],\"subaccount\":[]}', '2026-10-13 22:24:51', '2026-10-13 22:24:23'),
(18, 14, 'DFPRO-14-BF08B6A7374F8320', 'u7wyc9husxmtmvk', 30.00, 'GHS', 'paystack', 'success', 6655456521, '{\"id\":6655456521,\"domain\":\"test\",\"status\":\"success\",\"reference\":\"DFPRO-14-BF08B6A7374F8320\",\"receipt_number\":\"10101\",\"amount\":3000,\"message\":null,\"gateway_response\":\"Approved\",\"paid_at\":\"2026-10-13T22:59:26.000Z\",\"created_at\":\"2026-10-13T22:59:04.000Z\",\"channel\":\"mobile_money\",\"currency\":\"GHS\",\"ip_address\":\"154.162.86.93\",\"metadata\":{\"purpose\":\"seller_pro_subscription\",\"user_id\":\"14\",\"plan\":\"pro\",\"referrer\":\"https://www.dogoflow.com/\"},\"log\":{\"start_time\":1791932350,\"time_spent\":16,\"attempts\":1,\"errors\":0,\"success\":false,\"mobile\":true,\"input\":[],\"history\":[{\"type\":\"action\",\"message\":\"Set payment method to: mobile_money\",\"time\":13},{\"type\":\"action\",\"message\":\"Attempted to pay with mobile money\",\"time\":16}]},\"fees\":59,\"fees_split\":null,\"authorization\":{\"authorization_code\":\"AUTH_cncr9hou7c\",\"bin\":\"055XXX\",\"last4\":\"X987\",\"exp_month\":\"12\",\"exp_year\":\"9999\",\"channel\":\"mobile_money\",\"card_type\":\"\",\"bank\":\"MTN\",\"country_code\":\"GH\",\"brand\":\"Mtn\",\"reusable\":false,\"signature\":null,\"account_name\":null,\"mobile_money_number\":\"0551234987\",\"receiver_bank_account_number\":null,\"receiver_bank\":null},\"customer\":{\"id\":408425067,\"first_name\":null,\"last_name\":null,\"email\":\"konadufelix916@gmail.com\",\"customer_code\":\"CUS_o05nx0gh7smwwtj\",\"phone\":null,\"metadata\":null,\"risk_action\":\"default\",\"international_format_phone\":null},\"plan\":null,\"split\":[],\"order_id\":null,\"paidAt\":\"2026-10-13T22:59:26.000Z\",\"createdAt\":\"2026-10-13T22:59:04.000Z\",\"requested_amount\":3000,\"pos_transaction_data\":null,\"source\":null,\"fees_breakdown\":null,\"connect\":null,\"transaction_date\":\"2026-10-13T22:59:04.000Z\",\"plan_object\":[],\"subaccount\":[]}', '2026-10-13 22:59:35', '2026-10-13 22:59:03');

-- --------------------------------------------------------

--
-- Table structure for table `site_settings`
--

CREATE TABLE `site_settings` (
  `setting_key` varchar(80) NOT NULL,
  `setting_value` varchar(255) NOT NULL,
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Dumping data for table `site_settings`
--

INSERT INTO `site_settings` (`setting_key`, `setting_value`, `updated_at`) VALUES
('cart_enabled', '0', '2026-10-16 13:54:40'),
('signup_affiliate', '1', '2026-10-16 09:51:40'),
('signup_customer', '1', '2026-10-19 09:20:22'),
('signup_seller', '1', '2026-10-16 09:51:40');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `name` varchar(120) NOT NULL,
  `email` varchar(190) NOT NULL,
  `phone` varchar(30) DEFAULT NULL,
  `password_hash` varchar(255) NOT NULL,
  `role` enum('customer','seller','affiliate','admin') NOT NULL DEFAULT 'customer',
  `status` enum('active','pending','suspended','blocked') NOT NULL DEFAULT 'pending',
  `email_verified_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `name`, `email`, `phone`, `password_hash`, `role`, `status`, `email_verified_at`, `created_at`, `updated_at`) VALUES
(1, 'DogoFlow Administrator', 'admin@dogoflow.com', NULL, '$2y$10$7Y7wfcdNsOtJinY7FZJM1.m4dFs2w70.866tIKhoA0vdBsDG2qffS', 'admin', 'active', '2026-09-28 15:14:07', '2026-09-28 15:14:07', '2026-09-28 15:14:07'),
(3, 'AFFTEST', 'affliate@gmail.com', '0240000000', '$2y$10$ECOC4zyxN8NT7Pm6AFwF7uamqkvh1QB7fFgmezhq5DXEtpI9uZfEy', 'affiliate', 'active', '2026-09-29 12:02:33', '2026-09-29 12:02:28', '2026-09-29 12:02:33'),
(11, 'Simon Abayeteye', 'professorsimon622@gmail.com', '+233273406734', '$2y$10$lhE91.MeOsjf7jKiCIWMaO1mZGjzWQyjiO1EMmON0oLg/1Jj1u7ci', 'seller', 'active', '2026-10-13 13:35:06', '2026-10-13 13:34:31', '2026-10-13 13:35:06'),
(12, 'Prof Simon', 'webyatey@gmail.com', '+233273406734', '$2y$10$EjYj/xqYBGnJmdj7n6QWfukgMb8VsKPciQ63aAjC.FiFxOB0O7v1S', 'seller', 'active', '2026-10-13 13:43:18', '2026-10-13 13:42:40', '2026-10-13 16:02:11'),
(13, 'Gideon Adjei', 'yfwdeon006@gmail.com', '053 640 9030', '$2y$10$zaHJ5LJ2T./py4Xo.d4MA.6TsrK6kORmDkF9IybRobAPm6x/DpUU6', 'seller', 'active', '2026-10-13 22:22:17', '2026-10-13 22:20:00', '2026-10-13 22:23:16'),
(14, 'Felix  Konadu', 'konadufelix916@gmail.com', '0594782488', '$2y$10$NkbhJi039MJAi0g95fzi9OFeXixZid7a//6chIku/GUsq0UmLllHG', 'seller', 'active', '2026-10-13 22:58:09', '2026-10-13 22:57:31', '2026-10-13 22:58:09'),
(15, 'Customer', 'profcimon@icloud.com', '0558206490', '$2y$10$5DH6H3TdQx0KLGLy/VSRkekH/FlreKwJ2xksJDMsJDEM2WV6JpUhO', 'customer', 'active', '2026-10-14 05:31:22', '2026-10-14 04:31:04', '2026-10-14 05:31:22'),
(17, 'Cimon', 'abayateyesimon799@gmail.com', NULL, '$2y$10$igTHKuC7jPj6/./jm.IuGuHSIPrh3rdOFuXgJ/tQqpf2pI9SAybXu', 'customer', 'active', '2026-10-15 18:34:14', '2026-10-15 18:33:09', '2026-10-15 18:34:14');

-- --------------------------------------------------------

--
-- Table structure for table `wallet_transactions`
--

CREATE TABLE `wallet_transactions` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `user_id` bigint(20) UNSIGNED NOT NULL,
  `type` varchar(50) NOT NULL,
  `amount` decimal(12,2) NOT NULL,
  `reference` varchar(100) NOT NULL,
  `direction` enum('credit','debit') NOT NULL,
  `status` enum('pending','completed','reversed') NOT NULL DEFAULT 'completed',
  `metadata` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`metadata`)),
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------

--
-- Table structure for table `withdrawals`
--

CREATE TABLE `withdrawals` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `user_id` bigint(20) UNSIGNED NOT NULL,
  `amount` decimal(12,2) NOT NULL,
  `method` varchar(40) NOT NULL,
  `account_details` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`account_details`)),
  `status` enum('pending','approved','processing','paid','rejected','failed') NOT NULL DEFAULT 'pending',
  `reference` varchar(100) NOT NULL,
  `processed_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `payout_reference` varchar(120) DEFAULT NULL,
  `payout_note` varchar(500) DEFAULT NULL,
  `reviewed_by` bigint(20) UNSIGNED DEFAULT NULL,
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `affiliate_clicks`
--
ALTER TABLE `affiliate_clicks`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_click_product` (`product_id`),
  ADD KEY `idx_clicks_affiliate_created` (`affiliate_id`,`created_at`);

--
-- Indexes for table `affiliate_profiles`
--
ALTER TABLE `affiliate_profiles`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `user_id` (`user_id`),
  ADD UNIQUE KEY `referral_code` (`referral_code`);

--
-- Indexes for table `affiliate_referrals`
--
ALTER TABLE `affiliate_referrals`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_affiliate_referred_user` (`referred_user_id`),
  ADD KEY `idx_affiliate_referrals_affiliate_status` (`affiliate_id`,`status`);

--
-- Indexes for table `affiliate_rewards`
--
ALTER TABLE `affiliate_rewards`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `reference` (`reference`),
  ADD UNIQUE KEY `uq_affiliate_reward_referral` (`referral_id`),
  ADD KEY `idx_affiliate_rewards_owner_status` (`affiliate_id`,`status`);

--
-- Indexes for table `audit_logs`
--
ALTER TABLE `audit_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_audit_entity` (`entity_type`,`entity_id`),
  ADD KEY `idx_audit_actor_created` (`actor_user_id`,`created_at`);

--
-- Indexes for table `carts`
--
ALTER TABLE `carts`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_carts_customer` (`customer_id`);

--
-- Indexes for table `cart_items`
--
ALTER TABLE `cart_items`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_cart_product` (`cart_id`,`product_id`),
  ADD KEY `idx_cart_items_product` (`product_id`);

--
-- Indexes for table `categories`
--
ALTER TABLE `categories`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `slug` (`slug`),
  ADD KEY `idx_categories_parent_status` (`parent_id`,`status`),
  ADD KEY `idx_categories_status_sort` (`status`,`sort_order`);

--
-- Indexes for table `commissions`
--
ALTER TABLE `commissions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_commission_order_affiliate` (`affiliate_id`,`order_id`),
  ADD KEY `fk_commission_order` (`order_id`);

--
-- Indexes for table `email_verifications`
--
ALTER TABLE `email_verifications`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `token_hash` (`token_hash`),
  ADD KEY `fk_verification_user` (`user_id`),
  ADD KEY `idx_email_verification_expiry` (`expires_at`);

--
-- Indexes for table `marketplace_view_events`
--
ALTER TABLE `marketplace_view_events`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_marketplace_view_seller_type_date` (`seller_profile_id`,`event_type`,`created_at`),
  ADD KEY `idx_marketplace_view_product_type_date` (`product_id`,`event_type`,`created_at`);

--
-- Indexes for table `notifications`
--
ALTER TABLE `notifications`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_notifications_status_created` (`status`,`created_at`),
  ADD KEY `idx_notifications_user_read_created` (`user_id`,`read_at`,`created_at`);

--
-- Indexes for table `orders`
--
ALTER TABLE `orders`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `order_number` (`order_number`),
  ADD KEY `idx_orders_customer_status` (`customer_id`,`status`),
  ADD KEY `idx_orders_status_created` (`status`,`created_at`);

--
-- Indexes for table `order_items`
--
ALTER TABLE `order_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_item_product` (`product_id`),
  ADD KEY `idx_order_items_order` (`order_id`),
  ADD KEY `idx_order_items_seller` (`seller_id`),
  ADD KEY `idx_order_items_seller_status` (`seller_id`,`fulfillment_status`,`order_id`);

--
-- Indexes for table `order_status_history`
--
ALTER TABLE `order_status_history`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_order_history_order_created` (`order_id`,`created_at`),
  ADD KEY `idx_order_history_actor` (`actor_user_id`,`created_at`);

--
-- Indexes for table `password_resets`
--
ALTER TABLE `password_resets`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `token_hash` (`token_hash`),
  ADD KEY `fk_reset_user` (`user_id`),
  ADD KEY `idx_password_reset_expiry` (`expires_at`);

--
-- Indexes for table `payments`
--
ALTER TABLE `payments`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `reference` (`reference`),
  ADD KEY `idx_payments_order_status` (`order_id`,`status`),
  ADD KEY `idx_payments_reference_status` (`reference`,`status`);

--
-- Indexes for table `products`
--
ALTER TABLE `products`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `slug` (`slug`),
  ADD KEY `idx_products_status` (`status`),
  ADD KEY `idx_products_seller_status` (`seller_id`,`status`),
  ADD KEY `idx_products_price` (`price`),
  ADD KEY `idx_products_created_at` (`created_at`),
  ADD KEY `idx_products_name` (`name`);

--
-- Indexes for table `product_categories`
--
ALTER TABLE `product_categories`
  ADD PRIMARY KEY (`product_id`,`category_id`),
  ADD KEY `idx_pc_category_product` (`category_id`,`product_id`);

--
-- Indexes for table `product_images`
--
ALTER TABLE `product_images`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_product_images_product` (`product_id`,`sort_order`),
  ADD KEY `idx_product_images_primary` (`product_id`,`is_primary`);

--
-- Indexes for table `seller_categories`
--
ALTER TABLE `seller_categories`
  ADD PRIMARY KEY (`seller_id`,`category_id`),
  ADD KEY `idx_sc_category_seller` (`category_id`,`seller_id`);

--
-- Indexes for table `seller_profiles`
--
ALTER TABLE `seller_profiles`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `user_id` (`user_id`),
  ADD UNIQUE KEY `store_slug` (`store_slug`),
  ADD UNIQUE KEY `seller_code` (`seller_code`);

--
-- Indexes for table `seller_subscription_payments`
--
ALTER TABLE `seller_subscription_payments`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `reference` (`reference`),
  ADD KEY `idx_seller_subscription_user_status` (`user_id`,`status`),
  ADD KEY `idx_seller_subscription_reference_status` (`reference`,`status`);

--
-- Indexes for table `site_settings`
--
ALTER TABLE `site_settings`
  ADD PRIMARY KEY (`setting_key`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`),
  ADD KEY `idx_users_role_status` (`role`,`status`);

--
-- Indexes for table `wallet_transactions`
--
ALTER TABLE `wallet_transactions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `reference` (`reference`),
  ADD KEY `idx_wallet_user_created` (`user_id`,`created_at`);

--
-- Indexes for table `withdrawals`
--
ALTER TABLE `withdrawals`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `reference` (`reference`),
  ADD KEY `idx_withdrawals_user_status` (`user_id`,`status`),
  ADD KEY `idx_withdrawals_status_created` (`status`,`created_at`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `affiliate_clicks`
--
ALTER TABLE `affiliate_clicks`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `affiliate_profiles`
--
ALTER TABLE `affiliate_profiles`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `affiliate_referrals`
--
ALTER TABLE `affiliate_referrals`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `affiliate_rewards`
--
ALTER TABLE `affiliate_rewards`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `audit_logs`
--
ALTER TABLE `audit_logs`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT for table `carts`
--
ALTER TABLE `carts`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `cart_items`
--
ALTER TABLE `cart_items`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `categories`
--
ALTER TABLE `categories`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `commissions`
--
ALTER TABLE `commissions`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `email_verifications`
--
ALTER TABLE `email_verifications`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=19;

--
-- AUTO_INCREMENT for table `marketplace_view_events`
--
ALTER TABLE `marketplace_view_events`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `notifications`
--
ALTER TABLE `notifications`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=38;

--
-- AUTO_INCREMENT for table `orders`
--
ALTER TABLE `orders`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `order_items`
--
ALTER TABLE `order_items`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `order_status_history`
--
ALTER TABLE `order_status_history`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `password_resets`
--
ALTER TABLE `password_resets`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `payments`
--
ALTER TABLE `payments`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `products`
--
ALTER TABLE `products`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;

--
-- AUTO_INCREMENT for table `product_images`
--
ALTER TABLE `product_images`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=26;

--
-- AUTO_INCREMENT for table `seller_profiles`
--
ALTER TABLE `seller_profiles`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `seller_subscription_payments`
--
ALTER TABLE `seller_subscription_payments`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=19;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=20;

--
-- AUTO_INCREMENT for table `wallet_transactions`
--
ALTER TABLE `wallet_transactions`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `withdrawals`
--
ALTER TABLE `withdrawals`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `affiliate_clicks`
--
ALTER TABLE `affiliate_clicks`
  ADD CONSTRAINT `fk_click_affiliate` FOREIGN KEY (`affiliate_id`) REFERENCES `affiliate_profiles` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_click_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `affiliate_profiles`
--
ALTER TABLE `affiliate_profiles`
  ADD CONSTRAINT `fk_affiliate_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `affiliate_referrals`
--
ALTER TABLE `affiliate_referrals`
  ADD CONSTRAINT `fk_referral_affiliate` FOREIGN KEY (`affiliate_id`) REFERENCES `affiliate_profiles` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_referral_user` FOREIGN KEY (`referred_user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `affiliate_rewards`
--
ALTER TABLE `affiliate_rewards`
  ADD CONSTRAINT `fk_reward_affiliate` FOREIGN KEY (`affiliate_id`) REFERENCES `affiliate_profiles` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_reward_referral` FOREIGN KEY (`referral_id`) REFERENCES `affiliate_referrals` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `audit_logs`
--
ALTER TABLE `audit_logs`
  ADD CONSTRAINT `fk_audit_actor` FOREIGN KEY (`actor_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `carts`
--
ALTER TABLE `carts`
  ADD CONSTRAINT `fk_cart_customer` FOREIGN KEY (`customer_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `cart_items`
--
ALTER TABLE `cart_items`
  ADD CONSTRAINT `fk_cart_item_cart` FOREIGN KEY (`cart_id`) REFERENCES `carts` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_cart_item_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `categories`
--
ALTER TABLE `categories`
  ADD CONSTRAINT `fk_category_parent` FOREIGN KEY (`parent_id`) REFERENCES `categories` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `commissions`
--
ALTER TABLE `commissions`
  ADD CONSTRAINT `fk_commission_affiliate` FOREIGN KEY (`affiliate_id`) REFERENCES `affiliate_profiles` (`id`),
  ADD CONSTRAINT `fk_commission_order` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`);

--
-- Constraints for table `email_verifications`
--
ALTER TABLE `email_verifications`
  ADD CONSTRAINT `fk_verification_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `marketplace_view_events`
--
ALTER TABLE `marketplace_view_events`
  ADD CONSTRAINT `fk_marketplace_view_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_marketplace_view_seller_profile` FOREIGN KEY (`seller_profile_id`) REFERENCES `seller_profiles` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `notifications`
--
ALTER TABLE `notifications`
  ADD CONSTRAINT `fk_notification_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`);

--
-- Constraints for table `orders`
--
ALTER TABLE `orders`
  ADD CONSTRAINT `fk_order_customer` FOREIGN KEY (`customer_id`) REFERENCES `users` (`id`);

--
-- Constraints for table `order_items`
--
ALTER TABLE `order_items`
  ADD CONSTRAINT `fk_item_order` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_item_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`),
  ADD CONSTRAINT `fk_item_seller` FOREIGN KEY (`seller_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `order_status_history`
--
ALTER TABLE `order_status_history`
  ADD CONSTRAINT `fk_order_history_actor` FOREIGN KEY (`actor_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_order_history_order` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `password_resets`
--
ALTER TABLE `password_resets`
  ADD CONSTRAINT `fk_reset_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `payments`
--
ALTER TABLE `payments`
  ADD CONSTRAINT `fk_payment_order` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`);

--
-- Constraints for table `products`
--
ALTER TABLE `products`
  ADD CONSTRAINT `fk_product_seller` FOREIGN KEY (`seller_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `product_categories`
--
ALTER TABLE `product_categories`
  ADD CONSTRAINT `fk_pc_category` FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_pc_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `product_images`
--
ALTER TABLE `product_images`
  ADD CONSTRAINT `fk_product_images_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `seller_categories`
--
ALTER TABLE `seller_categories`
  ADD CONSTRAINT `fk_sc_category` FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_sc_seller` FOREIGN KEY (`seller_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `seller_profiles`
--
ALTER TABLE `seller_profiles`
  ADD CONSTRAINT `fk_seller_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `seller_subscription_payments`
--
ALTER TABLE `seller_subscription_payments`
  ADD CONSTRAINT `fk_seller_subscription_payment_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `wallet_transactions`
--
ALTER TABLE `wallet_transactions`
  ADD CONSTRAINT `fk_wallet_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`);

--
-- Constraints for table `withdrawals`
--
ALTER TABLE `withdrawals`
  ADD CONSTRAINT `fk_withdrawal_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
