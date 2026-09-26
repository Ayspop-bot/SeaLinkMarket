-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Sep 19, 2026 at 07:01 AM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `sealink_database`
--

-- --------------------------------------------------------

--
-- Table structure for table `admin_support_reply_tbl`
--

CREATE TABLE `admin_support_reply_tbl` (
  `reply_id` int(11) NOT NULL,
  `support_id` int(11) NOT NULL,
  `sender_type` enum('user','admin') NOT NULL,
  `sender_id` int(11) NOT NULL,
  `sender_role` varchar(50) NOT NULL,
  `sender_name` varchar(150) NOT NULL,
  `message` text NOT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `admin_support_reply_tbl`
--

INSERT INTO `admin_support_reply_tbl` (`reply_id`, `support_id`, `sender_type`, `sender_id`, `sender_role`, `sender_name`, `message`, `created_at`) VALUES
(1, 2, 'admin', 11, 'User Admin', 'User Administrator', 'Can you describe in a much-detailed po on why you cant log in?', '2026-08-18 22:07:42'),
(2, 4, 'admin', 11, 'User Admin', 'User Administrator', 'Under Profile po sa upper left ng website. Click that po, then choose the logout', '2026-08-18 22:06:37'),
(3, 2, 'admin', 10, 'Content Admin', 'Content Administrator', 'Make sure na tama po username and password nyo po when logging in', '2026-08-20 22:48:48'),
(4, 4, 'admin', 11, 'User Admin', 'User Administrator', 'okay na po ba?', '2026-08-24 09:45:14'),
(5, 2, 'admin', 11, 'User Admin', 'User Administrator', 'bb', '2026-08-24 09:47:02'),
(6, 5, 'admin', 10, 'Content Admin', 'Content Administrator', 'bxnvg', '2026-08-25 08:48:11'),
(7, 5, 'admin', 10, 'Content Admin', 'Content Administrator', 'dwjhdgjw', '2026-08-25 08:48:16');

-- --------------------------------------------------------

--
-- Table structure for table `admin_support_tbl`
--

CREATE TABLE `admin_support_tbl` (
  `support_id` int(11) NOT NULL,
  `farmer_id` int(11) DEFAULT NULL,
  `buyer_id` int(11) DEFAULT NULL,
  `message` text NOT NULL,
  `admin_id` int(11) DEFAULT NULL,
  `admin_reply` text DEFAULT NULL,
  `status` enum('Open','Replied','Closed') NOT NULL DEFAULT 'Open',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `admin_support_tbl`
--

INSERT INTO `admin_support_tbl` (`support_id`, `farmer_id`, `buyer_id`, `message`, `admin_id`, `admin_reply`, `status`, `created_at`, `updated_at`) VALUES
(1, 1, NULL, 'bjhevhg', 11, NULL, 'Closed', '2026-08-15 16:05:36', '2026-08-25 08:42:14'),
(2, NULL, 1, 'di po ako maka log in sa acc ko', 11, 'bb', 'Closed', '2026-08-15 20:43:30', '2026-08-24 09:47:02'),
(3, NULL, 1, 'vgvcgbc', NULL, NULL, 'Open', '2026-08-16 21:51:26', '2026-08-16 21:51:26'),
(4, 3, NULL, 'pano po mag log-out?', 11, 'okay na po ba?', 'Closed', '2026-08-18 14:49:47', '2026-08-24 09:49:43'),
(5, 1, NULL, 'jhgjg', 10, 'dwjhdgjw', 'Replied', '2026-08-24 17:34:06', '2026-08-25 08:48:17');

-- --------------------------------------------------------

--
-- Table structure for table `admin_tbl`
--

CREATE TABLE `admin_tbl` (
  `admin_id` int(11) NOT NULL,
  `role` enum('Content Admin','User Admin') NOT NULL,
  `username` varchar(50) NOT NULL,
  `email` varchar(50) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `full_name` varchar(100) NOT NULL,
  `contact_number` varchar(15) NOT NULL,
  `address` text NOT NULL,
  `profile_image` varchar(255) DEFAULT NULL,
  `status` enum('Active','Inactive') NOT NULL DEFAULT 'Active',
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `admin_tbl`
--

INSERT INTO `admin_tbl` (`admin_id`, `role`, `username`, `email`, `password_hash`, `full_name`, `contact_number`, `address`, `profile_image`, `status`, `created_at`) VALUES
(10, 'Content Admin', 'content_admin', 'content@sealink.local', '$2y$10$i75tzHlnDeJid5PTZKm4p./vtrG628Wth/.cNQQoFfZUZfgchWih.', 'Content Administrator', '09123456789', 'RSU Santa Fe Campus', NULL, 'Active', '2026-07-31 02:12:55'),
(11, 'User Admin', 'user_admin', 'useradmin@sealink.local', '$2y$10$i75tzHlnDeJid5PTZKm4p./vtrG628Wth/.cNQQoFfZUZfgchWih.', 'User Administrator', '09987654321', 'Municipality of Santa Fe', NULL, 'Active', '2026-07-31 02:12:55'),
(12, 'User Admin', 'jhana', 'jhana@gmail.com', '$2y$10$KggkaI/p0/AhtZaQKsHUYuSZWpIS18EzLSdQTnV9//7Q6bJ7ZlkwS', 'Wayne Gajulin', '+639609426661', '', NULL, 'Active', '2026-08-25 08:40:54');

-- --------------------------------------------------------

--
-- Table structure for table `buyer_address_tbl`
--

CREATE TABLE `buyer_address_tbl` (
  `address_id` int(11) NOT NULL,
  `buyer_id` int(11) NOT NULL,
  `street` varchar(50) NOT NULL,
  `municipality` varchar(100) NOT NULL,
  `province` varchar(100) NOT NULL DEFAULT 'Romblon',
  `zip_code` varchar(10) DEFAULT NULL,
  `is_default` tinyint(1) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `buyer_address_tbl`
--

INSERT INTO `buyer_address_tbl` (`address_id`, `buyer_id`, `street`, `municipality`, `province`, `zip_code`, `is_default`) VALUES
(6, 1, 'Guinbirayan', 'Santa Fe', 'Romblon', '5505', 1),
(7, 1, 'Guinbirayan', 'Santa Fe', 'Romblon', '5505', 0),
(8, 1, 'Guinbirayan', 'Santa Fe', 'Romblon', '5505', 0),
(9, 1, 'Guinbirayan', 'Santa Fe', 'Romblon', '5505', 0),
(10, 1, 'Guinbirayan', 'Santa Fe', 'Romblon', '5505', 0),
(11, 1, 'Tabugon', 'Santa Fe', 'Romblon', '5508', 0);

-- --------------------------------------------------------

--
-- Table structure for table `buyer_tbl`
--

CREATE TABLE `buyer_tbl` (
  `buyer_id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `full_name` varchar(100) NOT NULL,
  `email` varchar(50) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `contact_number` varchar(15) NOT NULL,
  `facebook_account` varchar(255) NOT NULL,
  `profile_image` varchar(255) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `status` enum('Active','Inactive') NOT NULL DEFAULT 'Active'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `buyer_tbl`
--

INSERT INTO `buyer_tbl` (`buyer_id`, `username`, `full_name`, `email`, `password_hash`, `contact_number`, `facebook_account`, `profile_image`, `created_at`, `status`) VALUES
(1, 'shenmea', 'Shen Mea Felizario', 'shen@gmail.com', '$2y$10$1GPMhs46bu7jLzoXI119kOfvO2Kpg7rg6JwS1L3aq6ZaOe09/jGmy', '09978725646', 'https://www.facebook.com/beryshiii', 'uploads/profiles/profile_1_1787236336_ba272c8f.jpg', '2026-08-15 14:48:49', 'Active');

-- --------------------------------------------------------

--
-- Table structure for table `cart_item_tbl`
--

CREATE TABLE `cart_item_tbl` (
  `cart_item_id` int(11) NOT NULL,
  `buyer_id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `quantity` int(11) NOT NULL,
  `added_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `cart_item_tbl`
--

INSERT INTO `cart_item_tbl` (`cart_item_id`, `buyer_id`, `product_id`, `quantity`, `added_at`) VALUES
(10, 1, 3, 2, '2026-08-24 10:53:09'),
(11, 1, 7, 1, '2026-09-18 19:59:22');

-- --------------------------------------------------------

--
-- Table structure for table `category_tbl`
--

CREATE TABLE `category_tbl` (
  `category_id` int(11) NOT NULL,
  `category_name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `category_tbl`
--

INSERT INTO `category_tbl` (`category_id`, `category_name`, `description`) VALUES
(22, 'Fish (Bangus, Tilapia, Tulingan)', ''),
(23, 'Shrimps / Crabs (Hipon, Alimango)', ''),
(24, 'Shellfish (Tahong, Talaba)', ''),
(25, 'Squid (Pusit)', ''),
(26, 'Octopus (Pugita)', ''),
(27, 'Seaweeds (Lato, Guso)', '');

-- --------------------------------------------------------

--
-- Table structure for table `farmer_tbl`
--

CREATE TABLE `farmer_tbl` (
  `farmer_id` int(11) NOT NULL,
  `username` varchar(100) NOT NULL,
  `full_name` varchar(100) NOT NULL,
  `email` varchar(50) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `contact_number` varchar(15) NOT NULL,
  `facebook_account` varchar(255) NOT NULL,
  `address` text NOT NULL,
  `profile_image` varchar(255) DEFAULT NULL,
  `permit_number` varchar(50) NOT NULL,
  `verification_status` enum('Pending','Verified','Rejected') NOT NULL DEFAULT 'Pending',
  `status` enum('Active','Inactive') NOT NULL DEFAULT 'Active',
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `farmer_tbl`
--

INSERT INTO `farmer_tbl` (`farmer_id`, `username`, `full_name`, `email`, `password_hash`, `contact_number`, `facebook_account`, `address`, `profile_image`, `permit_number`, `verification_status`, `status`, `created_at`) VALUES
(1, 'strawberry', 'Jocel Felizario', 'berry@gmail.com', '$2y$10$QlKj1RFKK4bFOz1jzBYf8eZQ/rmG9x1FWTgTIqV0ZpA9bQv1o2Mj2', '09757532156', 'https://www.facebook.com/beryshiii', 'Guinbirayan, Santa Fe', 'uploads/profiles/profile_1_1787233532_35c6941e.jpg', 'bwsh', 'Verified', 'Active', '2026-08-15 15:44:55'),
(2, 'demo_farmer', 'Demo Farmer', 'demo@farmer.com', '$2y$10$abcdefghijklmnopqrstuv', '09123456789', 'demo.facebook', 'Sample Address', NULL, 'PERMIT-123', 'Verified', 'Active', '2026-08-15 15:47:55'),
(3, 'Tindahan ni Shen', 'Shen Mea Felizario', 'shenmea@gmail.com', '$2y$10$dCef82R11xeO8VyLDL73Fe757SI1RPuZUaqtSG.2WEp74IlpW5HbW', '09978725646', 'https://www.facebook.com/beryshiii', 'Guinbirayan, Santa Fe', 'uploads/profiles/farmer_1787035704_f2eda1d22dae.jpg', 'PERMIT-2025-0005', 'Verified', 'Active', '2026-08-18 14:48:25'),
(4, 'lester store', 'John Lester Salango', 'mendozalester012@gmail.com', '$2y$10$KbCDE8WeYDpzIH3GSsozCeg3DCEYoFrtLZAp8Tfy2fZkjj00DK6Am', '09708408607', 'https://www.facebook.com/JohnLester', 'Tabugon Sta.Fe Romblon', NULL, 'PERMIT-2025-0006', 'Verified', 'Active', '2026-08-24 09:02:54'),
(5, 'Huli ni Mang Kanor', 'Kurt Castro', 'castro@gmail.com', '$2y$10$AJSD53HSths0fe9wOnBjH.pdPRiNch1gDbPjNHlLGZ43CreNf5u4G', '09609426661', 'https://www.facebook.com/kurt.castro9231', 'Mat-i, Sante Fe, Romblon', 'uploads/profiles/farmer_1789626616_6ef61705929d.jpg', 'PERMIT-2025-0010', 'Verified', 'Active', '2026-09-17 14:30:16'),
(6, 'Shop ni Wayne', 'Wayne Gajulin', 'gajulin@gmail.com', '$2y$10$PKCt0UVtPsS8nHdi6f73iOwSnDASnrIIiPH1gkvLja1F8THAdAHFq', '09872673519', 'https://www.facebook.com/wayne.gajulin', 'Poblacion, Santa Fe, Romblon', 'uploads/profiles/farmer_1789627519_15a668a1f73d.jpg', 'PERMIT-2025-0011', 'Verified', 'Active', '2026-09-17 14:45:19'),
(7, 'Rain Shop', 'Marianne Solangon', 'mar@gmail.com', '$2y$10$33/H.H9EMFyCFvfWvjx/ceP9VhA6S7VHHq6.yMDvg3EQz0d3CFmXK', '09762571920', 'https://www.facebook.com/akiarhaine0911', 'Pandan, Santa Fe, Romblon', 'uploads/profiles/farmer_1789627693_9487b85529bf.jpg', 'PERMIT-2025-0012', 'Verified', 'Active', '2026-09-17 14:48:14');

-- --------------------------------------------------------

--
-- Table structure for table `feedback_tbl`
--

CREATE TABLE `feedback_tbl` (
  `feedback_id` int(11) NOT NULL,
  `buyer_id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `farmer_id` int(11) DEFAULT NULL,
  `rating` tinyint(4) DEFAULT NULL,
  `comment` text DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `feedback_tbl`
--

INSERT INTO `feedback_tbl` (`feedback_id`, `buyer_id`, `product_id`, `farmer_id`, `rating`, `comment`, `created_at`) VALUES
(1, 1, 2, 1, 5, 'fresh', '2026-08-24 09:21:16');

-- --------------------------------------------------------

--
-- Table structure for table `forum_comment_tbl`
--

CREATE TABLE `forum_comment_tbl` (
  `comment_id` int(11) NOT NULL,
  `post_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `user_role` enum('farmer','buyer','Content Admin','User Admin') NOT NULL DEFAULT 'buyer',
  `author_name` varchar(150) NOT NULL,
  `content` text NOT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `forum_post_tbl`
--

CREATE TABLE `forum_post_tbl` (
  `post_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `user_role` enum('farmer','buyer','Content Admin','User Admin') NOT NULL DEFAULT 'farmer',
  `author_name` varchar(150) NOT NULL,
  `title` varchar(255) NOT NULL,
  `category` varchar(100) DEFAULT 'General Discussion',
  `content` text NOT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `forum_post_tbl`
--

INSERT INTO `forum_post_tbl` (`post_id`, `user_id`, `user_role`, `author_name`, `title`, `category`, `content`, `created_at`, `updated_at`) VALUES
(1, 1, 'farmer', 'Jocel Felizario', 'Optimal Salinity and Feeding Times for Milkfish (Bangus) Ponds in Santa Fe', 'Aquaculture & Techniques', 'Sharing observations on water temperature and salinity management for Santa Fe coastal pens during dry season. What feeding schedules have worked best for you?', '2026-09-18 16:22:46', '2026-09-18 18:22:46'),
(2, 1, 'farmer', 'Jocel Felizario', 'Live Hipon & Sugpo Handling Guidelines for Direct Buyer Deliveries', 'Market & Pricing', 'We have tested aerated containers for local transport across Romblon. Sharing best temperature ranges to maintain 100% survival rate upon pickup.', '2026-09-17 18:22:46', '2026-09-18 18:22:46');

-- --------------------------------------------------------

--
-- Table structure for table `guest_alert_tbl`
--

CREATE TABLE `guest_alert_tbl` (
  `alert_id` int(11) NOT NULL,
  `email` varchar(100) NOT NULL,
  `category_interest` varchar(100) DEFAULT 'All',
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `info_hub_tbl`
--

CREATE TABLE `info_hub_tbl` (
  `info_hub_id` int(11) NOT NULL,
  `admin_id` int(11) DEFAULT NULL,
  `title` varchar(255) NOT NULL,
  `type` enum('Article','Study','Tutorial','Recipe','Dish Guide','Research','Recipes','Forum') DEFAULT 'Article',
  `category` varchar(100) DEFAULT 'Article',
  `image_url` varchar(255) DEFAULT NULL,
  `content` text NOT NULL,
  `status` enum('Draft','Published','Unpublished') NOT NULL DEFAULT 'Draft',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `info_hub_tbl`
--

INSERT INTO `info_hub_tbl` (`info_hub_id`, `admin_id`, `title`, `type`, `category`, `image_url`, `content`, `status`, `created_at`, `updated_at`) VALUES
(3, NULL, 'Sustainable Bangus & Tilapia Pond Management in Coastal Romblon', 'Article', 'Research', 'uploads/infohub/article_1787235829_2427.jpg', 'Proper water aeration and monitoring of salinity levels are essential for healthy bangus (milkfish) growth in island fish pens. Maintaining a steady water exchange cycle during high tides reduces ammonia buildup and promotes disease resistance.\r\n\r\nKey Recommendations:\r\n1. Check water pH level twice weekly (optimal range: 7.5 - 8.5).\r\n2. Feed formulated pellets in regulated portions to prevent pond bottom sludge accumulation.\r\n3. Implement rotational harvesting to allow pond soil revitalization.', 'Published', '2026-08-18 14:38:47', '2026-08-25 09:18:10'),
(4, NULL, 'Best Practices for Seaweed (Eucheuma & Kappaphycus) Cultivation', 'Article', 'Article', 'uploads/infohub/article_1787235859_4526.jpg', 'Seaweed farming in Santa Fe coastal areas requires careful site selection where moderate water currents provide essential nutrients while preventing excessive sediment deposition.\r\n\r\nKey Steps:\r\n- Ensure planting lines are submerged at least 0.5m below low tide level.\r\n- Regular weeding of epiphytes and removal of grazers ensures maximum carrageenan yield.\r\n- Sun-dry harvests on elevated drying platforms rather than directly on the ground to preserve export quality.', 'Published', '2026-08-18 14:38:47', '2026-08-20 22:24:19'),
(5, NULL, 'Post-Harvest Handling & Cold Chain Storage for Local Fisherfolk', 'Article', 'Article', 'uploads/infohub/article_1787235877_6868.jpg', 'Maintaining product freshness from boat to market significantly increases selling prices for aquatic producers. Applying clean crushed ice at a 1:1 ratio immediately upon catch slows bacterial degradation and ensures firm texture.\r\n\r\nHandling Protocol:\r\n- Wash fish with clean seawater before chilling.\r\n- Use insulated iceboxes with drain plugs to prevent fish from soaking in melted dirty ice water.\r\n- Separate high-value crustaceans (crabs/shrimps) to prevent limb breakage during transport.', 'Published', '2026-08-18 14:38:47', '2026-08-20 22:24:37'),
(6, NULL, 'Understanding BFAR Quality and Sizing Standards for Trade', 'Article', 'Research', 'uploads/infohub/article_1787235901_9627.jpg', 'Adhering to municipal fisheries standards protects marine breeding cycles and guarantees fair pricing for consumers and commercial buyers.\r\n\r\nStandard Categories:\r\n- Milkfish (Bangus): Medium (3-4 pcs/kg), Large (1-2 pcs/kg).\r\n- Crabs: Minimum carapace width of 10cm for commercial sale.\r\n- Dried Aquatic Products: Moisture content below 15% to prevent mold growth during packaging.', 'Published', '2026-08-18 14:38:47', '2026-08-20 22:25:01');

-- --------------------------------------------------------

--
-- Table structure for table `message_tbl`
--

CREATE TABLE `message_tbl` (
  `message_id` int(11) NOT NULL,
  `buyer_id` int(11) NOT NULL,
  `farmer_id` int(11) NOT NULL,
  `sender_type` enum('Farmer','Buyer') NOT NULL,
  `content` text NOT NULL,
  `sent_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `message_tbl`
--

INSERT INTO `message_tbl` (`message_id`, `buyer_id`, `farmer_id`, `sender_type`, `content`, `sent_at`) VALUES
(1, 1, 1, 'Buyer', 'hello po, kelan ko po matatanggap ang order ko?', '2026-08-15 20:40:47'),
(2, 1, 1, 'Buyer', 'hjhgfjhgs', '2026-08-15 21:01:28'),
(3, 1, 1, 'Buyer', 'andbggwfv', '2026-08-15 21:01:31'),
(4, 1, 1, 'Buyer', 'hgfdyqtdqcd', '2026-08-15 21:01:33'),
(5, 1, 1, 'Buyer', 'jdqdyqthfdqc', '2026-08-15 21:01:35'),
(6, 1, 1, 'Buyer', 'nbhgcgvs', '2026-08-15 21:03:40'),
(7, 1, 1, 'Buyer', 'nbnbdb', '2026-08-15 23:21:20'),
(8, 1, 1, 'Farmer', 'processing na po ang order nyo, pa wait na lang po for further details', '2026-08-16 17:20:39'),
(9, 1, 1, 'Buyer', 'gfg', '2026-08-17 10:52:55'),
(10, 1, 1, 'Buyer', 'bjehfe', '2026-08-20 22:42:31'),
(11, 1, 1, 'Farmer', 'hello', '2026-08-24 09:11:54'),
(12, 1, 1, 'Buyer', 'hi', '2026-08-24 09:28:19'),
(13, 1, 1, 'Buyer', 'hello', '2026-08-24 11:00:49'),
(14, 1, 1, 'Farmer', 'ghff', '2026-08-24 17:29:18'),
(15, 1, 1, 'Buyer', 'hello', '2026-08-24 17:44:22'),
(16, 1, 1, 'Farmer', 'bdhgg', '2026-08-25 09:17:01'),
(17, 1, 1, 'Farmer', 'ejfeh', '2026-08-25 09:17:11');

-- --------------------------------------------------------

--
-- Table structure for table `order_item_tbl`
--

CREATE TABLE `order_item_tbl` (
  `order_item_id` int(11) NOT NULL,
  `order_id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `quantity` int(11) NOT NULL,
  `price_at_purchase` decimal(10,2) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `order_item_tbl`
--

INSERT INTO `order_item_tbl` (`order_item_id`, `order_id`, `product_id`, `quantity`, `price_at_purchase`) VALUES
(1, 1, 1, 1, 150.00),
(2, 1, 2, 1, 200.00),
(3, 2, 2, 1, 200.00),
(4, 3, 1, 1, 150.00),
(5, 3, 2, 4, 200.00),
(6, 4, 1, 1, 150.00),
(7, 5, 2, 1, 200.00),
(8, 6, 3, 1, 250.00),
(9, 7, 2, 1, 200.00),
(10, 8, 1, 1, 150.00),
(11, 8, 2, 1, 200.00),
(12, 9, 3, 1, 250.00);

-- --------------------------------------------------------

--
-- Table structure for table `order_tbl`
--

CREATE TABLE `order_tbl` (
  `order_id` int(11) NOT NULL,
  `buyer_id` int(11) NOT NULL,
  `farmer_id` int(11) NOT NULL,
  `address_id` int(11) NOT NULL,
  `order_date` datetime NOT NULL DEFAULT current_timestamp(),
  `subtotal_amount` decimal(10,2) NOT NULL DEFAULT 0.00,
  `platform_fee` decimal(10,2) NOT NULL DEFAULT 0.00,
  `farmer_payout` decimal(10,2) NOT NULL DEFAULT 0.00,
  `total_amount` decimal(10,2) NOT NULL,
  `shipping_fee` decimal(10,2) NOT NULL DEFAULT 0.00,
  `payment_method` enum('COD','GCash') NOT NULL,
  `payment_status` enum('Pending','Paid','Failed','Cancelled') NOT NULL DEFAULT 'Pending',
  `gcash_screenshot_url` varchar(255) DEFAULT NULL,
  `verified_by` enum('Farmer','User Admin') DEFAULT NULL,
  `fulfillment_type` enum('Delivery','Drop-off','Pickup') NOT NULL,
  `order_status` enum('Order Placed','Confirmed','Ready for fulfillment','Ready for Pickup','Ready for Delivery','Completed','Cancelled') NOT NULL DEFAULT 'Order Placed',
  `cancellation_reason` text DEFAULT NULL,
  `cancelled_by` enum('Buyer','Farmer','Admin') DEFAULT NULL,
  `cancelled_at` datetime DEFAULT NULL,
  `cancel_reason` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `order_tbl`
--

INSERT INTO `order_tbl` (`order_id`, `buyer_id`, `farmer_id`, `address_id`, `order_date`, `subtotal_amount`, `platform_fee`, `farmer_payout`, `total_amount`, `shipping_fee`, `payment_method`, `payment_status`, `gcash_screenshot_url`, `verified_by`, `fulfillment_type`, `order_status`, `cancellation_reason`, `cancelled_by`, `cancelled_at`, `cancel_reason`) VALUES
(1, 1, 1, 6, '2026-08-15 20:39:15', 0.00, 0.00, 0.00, 350.00, 0.00, 'COD', 'Pending', NULL, NULL, 'Delivery', 'Completed', NULL, NULL, NULL, NULL),
(2, 1, 1, 7, '2026-08-15 22:55:45', 0.00, 0.00, 0.00, 200.00, 0.00, 'COD', 'Pending', NULL, NULL, 'Delivery', 'Cancelled', NULL, NULL, NULL, NULL),
(3, 1, 1, 8, '2026-08-15 23:15:01', 0.00, 0.00, 0.00, 950.00, 0.00, 'COD', 'Pending', NULL, NULL, 'Delivery', 'Confirmed', NULL, NULL, NULL, NULL),
(4, 1, 1, 9, '2026-08-15 23:18:44', 0.00, 0.00, 0.00, 150.00, 0.00, 'COD', 'Pending', NULL, NULL, 'Delivery', 'Cancelled', NULL, NULL, NULL, 'Delivery location unreachable: masyado pong malayo ang location nyo'),
(5, 1, 1, 10, '2026-08-17 10:42:24', 0.00, 0.00, 0.00, 200.00, 0.00, 'COD', 'Pending', NULL, NULL, 'Delivery', 'Cancelled', NULL, NULL, NULL, NULL),
(6, 1, 1, 6, '2026-08-19 11:30:00', 0.00, 0.00, 0.00, 300.00, 50.00, 'COD', 'Pending', NULL, NULL, 'Delivery', 'Cancelled', NULL, NULL, NULL, NULL),
(7, 1, 1, 6, '2026-08-19 11:33:36', 0.00, 0.00, 0.00, 250.00, 50.00, 'COD', 'Pending', NULL, NULL, 'Delivery', 'Completed', NULL, NULL, NULL, NULL),
(8, 1, 1, 11, '2026-08-24 09:26:39', 0.00, 0.00, 0.00, 400.00, 50.00, 'COD', 'Pending', NULL, NULL, 'Pickup', 'Cancelled', NULL, NULL, NULL, NULL),
(9, 1, 1, 9, '2026-08-24 17:43:04', 0.00, 0.00, 0.00, 300.00, 50.00, 'GCash', 'Pending', 'uploads/gcash_screenshots/gcash_1_1787564584_e3b9c611b28a.jpg', NULL, 'Delivery', 'Cancelled', NULL, NULL, NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `product_boost_tbl`
--

CREATE TABLE `product_boost_tbl` (
  `boost_id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `farmer_id` int(11) NOT NULL,
  `amount_paid` decimal(10,2) NOT NULL DEFAULT 50.00,
  `gcash_reference` varchar(100) DEFAULT NULL,
  `receipt_image` varchar(255) NOT NULL,
  `status` enum('Pending','Approved','Active','Rejected','Expired') NOT NULL DEFAULT 'Active',
  `duration_days` int(11) NOT NULL DEFAULT 3,
  `promotion_plan` varchar(50) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `approved_at` datetime DEFAULT NULL,
  `expires_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `product_boost_tbl`
--

INSERT INTO `product_boost_tbl` (`boost_id`, `product_id`, `farmer_id`, `amount_paid`, `gcash_reference`, `receipt_image`, `status`, `duration_days`, `promotion_plan`, `created_at`, `approved_at`, `expires_at`) VALUES
(2, 7, 1, 30.00, '', 'uploads/gcash_screenshots/promo_1_7_1789630507_3b32c844.png', 'Active', 3, '3 Days Pinned (₱30.00)', '2026-09-17 15:35:07', '2026-09-17 15:35:48', '2026-09-20 15:35:48'),
(9, 13, 7, 30.00, '', 'uploads/gcash_screenshots/promo_7_13_1789718596_4c053f5b.jpg', 'Active', 3, '3-Day Featured Catch (₱30.00)', '2026-09-18 16:03:16', '2026-09-18 16:04:49', '2026-09-21 16:04:49'),
(10, 9, 5, 30.00, '', 'uploads/gcash_screenshots/promo_5_9_1789740641_2e777a7f.jpg', 'Active', 3, '3-Day Featured Catch (₱30.00)', '2026-09-18 22:10:41', '2026-09-18 22:11:06', '2026-09-21 22:11:06'),
(11, 12, 6, 30.00, '', 'uploads/gcash_screenshots/promo_6_12_1789752036_a84c75a5.jpg', 'Active', 3, '3-Day Featured Catch (₱30.00)', '2026-09-19 01:20:36', '2026-09-19 01:21:05', '2026-09-22 01:21:05');

-- --------------------------------------------------------

--
-- Table structure for table `product_tbl`
--

CREATE TABLE `product_tbl` (
  `product_id` int(11) NOT NULL,
  `farmer_id` int(11) NOT NULL,
  `category_id` int(11) NOT NULL,
  `name` varchar(150) NOT NULL,
  `description` text DEFAULT NULL,
  `harvested_at` datetime DEFAULT NULL,
  `shelf_life_hours` int(11) DEFAULT 24,
  `selling_deadline` datetime DEFAULT NULL,
  `price` decimal(10,2) NOT NULL,
  `discounted_price` decimal(10,2) DEFAULT NULL,
  `is_sale` tinyint(1) NOT NULL DEFAULT 0,
  `sale_price` decimal(10,2) DEFAULT NULL,
  `stock_quantity` int(11) NOT NULL DEFAULT 0,
  `image_url` varchar(255) DEFAULT NULL,
  `status` varchar(50) NOT NULL DEFAULT 'Active',
  `is_promoted` tinyint(1) NOT NULL DEFAULT 0,
  `promotion_start_date` datetime DEFAULT NULL,
  `promotion_end_date` datetime DEFAULT NULL,
  `receipt_image_path` varchar(255) DEFAULT NULL,
  `gcash_reference_number` varchar(100) DEFAULT NULL,
  `promotion_status` enum('None','Pending','Active','Expired','Rejected') NOT NULL DEFAULT 'None',
  `promotion_plan` varchar(50) DEFAULT NULL,
  `is_boosted` tinyint(1) NOT NULL DEFAULT 0,
  `boost_tier` varchar(50) DEFAULT NULL,
  `boost_expires_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `product_tbl`
--

INSERT INTO `product_tbl` (`product_id`, `farmer_id`, `category_id`, `name`, `description`, `harvested_at`, `shelf_life_hours`, `selling_deadline`, `price`, `discounted_price`, `is_sale`, `sale_price`, `stock_quantity`, `image_url`, `status`, `is_promoted`, `promotion_start_date`, `promotion_end_date`, `receipt_image_path`, `gcash_reference_number`, `promotion_status`, `promotion_plan`, `is_boosted`, `boost_tier`, `boost_expires_at`, `created_at`, `updated_at`) VALUES
(1, 1, 22, 'Tilapia', 'malaki', '0000-00-00 00:00:00', 24, NULL, 150.00, NULL, 0, NULL, 7, 'uploads/products/product_1_1787037380_21d2fafe464d.jpg', 'Active', 0, NULL, NULL, NULL, NULL, 'None', '3-Day Featured Catch (₱30.00)', 0, 'Featured Fresh Catch', NULL, '2026-08-15 16:03:20', '2026-09-18 16:01:20'),
(2, 1, 22, 'Bangus', 'fresh', NULL, 24, NULL, 200.00, 160.00, 1, 160.00, 2, 'uploads/products/product_1_1787037360_f4d6d44305ae.jpg', 'Active', 0, NULL, NULL, NULL, NULL, 'None', NULL, 0, NULL, NULL, '2026-08-15 16:04:54', '2026-09-17 14:02:01'),
(3, 1, 23, 'Hipon', 'fresh, bagung huli, at malalaki', NULL, 24, NULL, 250.00, 200.00, 0, NULL, 14, 'uploads/products/product_1_1787037447_8d3ee816a9d7.jpg', 'Active', 0, NULL, NULL, NULL, NULL, 'Expired', NULL, 0, 'Flash Banner', '2026-09-18 08:38:57', '2026-08-18 15:17:27', '2026-09-18 15:33:40'),
(7, 1, 24, 'Talaba', 'medium to large size', '0000-00-00 00:00:00', 24, NULL, 300.00, NULL, 0, NULL, 10, 'uploads/products/product_1_1789626294_cd689a6e80ff.jpg', 'Active', 1, '2026-09-17 15:35:48', '2026-09-20 15:35:48', 'uploads/gcash_screenshots/promo_1_7_1789630507_3b32c844.png', NULL, 'Active', '3 Days Pinned (₱30.00)', 1, 'Featured Fresh Catch', '2026-09-20 15:35:48', '2026-09-17 14:23:23', '2026-09-17 15:35:48'),
(8, 5, 24, 'Halaan', 'bagong huli', '0000-00-00 00:00:00', 24, NULL, 230.00, NULL, 0, NULL, 15, 'uploads/products/product_5_1789626779_71bebfa88752.jpg', 'Active', 0, NULL, NULL, NULL, NULL, 'None', NULL, 0, NULL, NULL, '2026-09-17 14:32:59', '2026-09-17 14:32:59'),
(9, 5, 22, 'Galunggong', 'fresh', '0000-00-00 00:00:00', 3, NULL, 120.00, NULL, 0, NULL, 10, 'uploads/products/product_5_1789626850_1f459e444fae.jpg', 'Active', 1, '2026-09-18 22:11:06', '2026-09-21 22:11:06', 'uploads/gcash_screenshots/promo_5_9_1789740641_2e777a7f.jpg', '', 'Active', '3-Day Featured Catch (₱30.00)', 1, 'Featured Fresh Catch', '2026-09-21 22:11:06', '2026-09-17 14:34:10', '2026-09-19 01:12:04'),
(10, 5, 23, 'Alimango', 'karamihan babae', '0000-00-00 00:00:00', 12, NULL, 250.00, NULL, 0, NULL, 10, 'uploads/products/product_5_1789627198_a7f20ab58f80.jpg', 'Active', 0, NULL, NULL, NULL, NULL, 'None', NULL, 0, NULL, NULL, '2026-09-17 14:39:58', '2026-09-18 15:33:40'),
(11, 6, 26, 'Pugita', 'maayos na ni store sa freezer para fresh lagi', '0000-00-00 00:00:00', 24, NULL, 200.00, NULL, 0, NULL, 20, 'uploads/products/product_6_1789627858_7f6215e52a6f.jpg', 'Active', 0, NULL, NULL, NULL, NULL, 'None', NULL, 0, NULL, NULL, '2026-09-17 14:50:58', '2026-09-17 14:50:58'),
(12, 6, 25, 'Pusit', 'open for bulk orders', '0000-00-00 00:00:00', 24, NULL, 500.00, NULL, 0, NULL, 0, 'uploads/products/product_6_1789627918_aae1eb91c392.jpg', 'Active', 1, '2026-09-19 01:21:05', '2026-09-22 01:21:05', 'uploads/gcash_screenshots/promo_6_12_1789752036_a84c75a5.jpg', '', 'Active', '3-Day Featured Catch (₱30.00)', 1, 'Featured Fresh Catch', '2026-09-22 01:21:05', '2026-09-17 14:51:58', '2026-09-19 01:21:42'),
(13, 7, 24, 'Tahong', 'bulk orders', '0000-00-00 00:00:00', 3, NULL, 300.00, NULL, 0, NULL, 20, 'uploads/products/product_7_1789628154_2a588a0eda7f.jpg', 'Active', 1, '2026-09-18 16:04:49', '2026-09-21 16:04:49', 'uploads/gcash_screenshots/promo_7_13_1789718596_4c053f5b.jpg', '', 'Active', '3-Day Featured Catch (₱30.00)', 1, 'Featured Fresh Catch', '2026-09-21 16:04:49', '2026-09-17 14:55:54', '2026-09-18 19:41:12'),
(14, 7, 27, 'Lato(Sea Grapes)', 'order before we harvest to ensure freshness', '0000-00-00 00:00:00', 24, NULL, 150.00, NULL, 0, NULL, 17, 'uploads/products/product_7_1789628241_0db6e05239d7.jpg', 'Active', 0, NULL, NULL, NULL, NULL, 'None', NULL, 0, NULL, NULL, '2026-09-17 14:57:21', '2026-09-17 14:57:21');

-- --------------------------------------------------------

--
-- Table structure for table `promote_settings_tbl`
--

CREATE TABLE `promote_settings_tbl` (
  `id` int(11) NOT NULL DEFAULT 1,
  `gcash_name` varchar(100) NOT NULL DEFAULT 'SeaLink Admin',
  `gcash_number` varchar(50) NOT NULL DEFAULT '09123456789',
  `gcash_qr_image` varchar(255) DEFAULT NULL,
  `promo_price_3_days` decimal(10,2) NOT NULL DEFAULT 50.00,
  `promo_price_7_days` decimal(10,2) NOT NULL DEFAULT 100.00,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `promote_settings_tbl`
--

INSERT INTO `promote_settings_tbl` (`id`, `gcash_name`, `gcash_number`, `gcash_qr_image`, `promo_price_3_days`, `promo_price_7_days`, `updated_at`) VALUES
(1, 'SeaLink Admin Official', '09978725646', 'uploads/qr_codes/gcash_qr_1789651034_6f591aa0.jpg', 50.00, 100.00, '2026-09-17 13:17:14');

-- --------------------------------------------------------

--
-- Table structure for table `promotion_packages_tbl`
--

CREATE TABLE `promotion_packages_tbl` (
  `id` int(11) NOT NULL,
  `package_name` varchar(150) NOT NULL,
  `duration_days` int(11) NOT NULL,
  `price` decimal(10,2) NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `promotion_packages_tbl`
--

INSERT INTO `promotion_packages_tbl` (`id`, `package_name`, `duration_days`, `price`, `is_active`, `created_at`, `updated_at`) VALUES
(1, '3-Day Featured Catch', 3, 30.00, 1, '2026-09-17 14:17:34', '2026-09-17 14:19:55'),
(2, '7-Day Featured Catch', 7, 70.00, 1, '2026-09-17 14:17:34', '2026-09-17 14:20:04');

-- --------------------------------------------------------

--
-- Table structure for table `site_settings_tbl`
--

CREATE TABLE `site_settings_tbl` (
  `id` int(11) NOT NULL,
  `setting_key` varchar(100) NOT NULL,
  `setting_value` text NOT NULL DEFAULT '',
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `site_settings_tbl`
--

INSERT INTO `site_settings_tbl` (`id`, `setting_key`, `setting_value`, `updated_at`) VALUES
(1, 'site_name', 'SeaLink', '2026-08-18 22:08:00'),
(2, 'support_email', 'sealink@support.com', '2026-08-18 22:08:00'),
(3, 'office_address', 'Santa Fe, Romblon, Philippines', '2026-08-18 22:08:00'),
(4, 'contact_number', '+63 900 000 0000', '2026-08-18 22:08:00'),
(5, 'description', 'SeaLink is an integrated web-based platform that combines a marketplace and an information hub connecting aquatic farmers and buyers in Santa Fe, Romblon..', '2026-08-24 10:01:51'),
(6, 'site_logo', '', '2026-08-18 22:08:00');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `admin_support_reply_tbl`
--
ALTER TABLE `admin_support_reply_tbl`
  ADD PRIMARY KEY (`reply_id`),
  ADD KEY `support_id` (`support_id`);

--
-- Indexes for table `admin_support_tbl`
--
ALTER TABLE `admin_support_tbl`
  ADD PRIMARY KEY (`support_id`),
  ADD KEY `farmer_id` (`farmer_id`),
  ADD KEY `buyer_id` (`buyer_id`),
  ADD KEY `admin_id` (`admin_id`);

--
-- Indexes for table `admin_tbl`
--
ALTER TABLE `admin_tbl`
  ADD PRIMARY KEY (`admin_id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD UNIQUE KEY `email` (`email`);

--
-- Indexes for table `buyer_address_tbl`
--
ALTER TABLE `buyer_address_tbl`
  ADD PRIMARY KEY (`address_id`),
  ADD KEY `buyer_id` (`buyer_id`);

--
-- Indexes for table `buyer_tbl`
--
ALTER TABLE `buyer_tbl`
  ADD PRIMARY KEY (`buyer_id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD UNIQUE KEY `email` (`email`);

--
-- Indexes for table `cart_item_tbl`
--
ALTER TABLE `cart_item_tbl`
  ADD PRIMARY KEY (`cart_item_id`),
  ADD UNIQUE KEY `product_id` (`product_id`),
  ADD KEY `buyer_id` (`buyer_id`);

--
-- Indexes for table `category_tbl`
--
ALTER TABLE `category_tbl`
  ADD PRIMARY KEY (`category_id`),
  ADD UNIQUE KEY `category_name` (`category_name`);

--
-- Indexes for table `farmer_tbl`
--
ALTER TABLE `farmer_tbl`
  ADD PRIMARY KEY (`farmer_id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD UNIQUE KEY `email` (`email`),
  ADD UNIQUE KEY `permit_number` (`permit_number`);

--
-- Indexes for table `feedback_tbl`
--
ALTER TABLE `feedback_tbl`
  ADD PRIMARY KEY (`feedback_id`),
  ADD KEY `buyer_id` (`buyer_id`),
  ADD KEY `product_id` (`product_id`),
  ADD KEY `farmer_id` (`farmer_id`);

--
-- Indexes for table `forum_comment_tbl`
--
ALTER TABLE `forum_comment_tbl`
  ADD PRIMARY KEY (`comment_id`),
  ADD KEY `post_id` (`post_id`);

--
-- Indexes for table `forum_post_tbl`
--
ALTER TABLE `forum_post_tbl`
  ADD PRIMARY KEY (`post_id`);

--
-- Indexes for table `guest_alert_tbl`
--
ALTER TABLE `guest_alert_tbl`
  ADD PRIMARY KEY (`alert_id`);

--
-- Indexes for table `info_hub_tbl`
--
ALTER TABLE `info_hub_tbl`
  ADD PRIMARY KEY (`info_hub_id`),
  ADD KEY `admin_id` (`admin_id`);

--
-- Indexes for table `message_tbl`
--
ALTER TABLE `message_tbl`
  ADD PRIMARY KEY (`message_id`),
  ADD KEY `buyer_id` (`buyer_id`),
  ADD KEY `farmer_id` (`farmer_id`);

--
-- Indexes for table `order_item_tbl`
--
ALTER TABLE `order_item_tbl`
  ADD PRIMARY KEY (`order_item_id`),
  ADD KEY `order_id` (`order_id`),
  ADD KEY `product_id` (`product_id`);

--
-- Indexes for table `order_tbl`
--
ALTER TABLE `order_tbl`
  ADD PRIMARY KEY (`order_id`),
  ADD KEY `buyer_id` (`buyer_id`),
  ADD KEY `farmer_id` (`farmer_id`),
  ADD KEY `address_id` (`address_id`);

--
-- Indexes for table `product_boost_tbl`
--
ALTER TABLE `product_boost_tbl`
  ADD PRIMARY KEY (`boost_id`),
  ADD KEY `fk_boost_product` (`product_id`),
  ADD KEY `fk_boost_farmer` (`farmer_id`);

--
-- Indexes for table `product_tbl`
--
ALTER TABLE `product_tbl`
  ADD PRIMARY KEY (`product_id`),
  ADD KEY `farmer_id` (`farmer_id`),
  ADD KEY `category_id` (`category_id`);

--
-- Indexes for table `promote_settings_tbl`
--
ALTER TABLE `promote_settings_tbl`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `promotion_packages_tbl`
--
ALTER TABLE `promotion_packages_tbl`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `site_settings_tbl`
--
ALTER TABLE `site_settings_tbl`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `setting_key` (`setting_key`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `admin_support_reply_tbl`
--
ALTER TABLE `admin_support_reply_tbl`
  MODIFY `reply_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `admin_support_tbl`
--
ALTER TABLE `admin_support_tbl`
  MODIFY `support_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `admin_tbl`
--
ALTER TABLE `admin_tbl`
  MODIFY `admin_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT for table `buyer_address_tbl`
--
ALTER TABLE `buyer_address_tbl`
  MODIFY `address_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT for table `buyer_tbl`
--
ALTER TABLE `buyer_tbl`
  MODIFY `buyer_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `cart_item_tbl`
--
ALTER TABLE `cart_item_tbl`
  MODIFY `cart_item_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT for table `category_tbl`
--
ALTER TABLE `category_tbl`
  MODIFY `category_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=28;

--
-- AUTO_INCREMENT for table `farmer_tbl`
--
ALTER TABLE `farmer_tbl`
  MODIFY `farmer_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `feedback_tbl`
--
ALTER TABLE `feedback_tbl`
  MODIFY `feedback_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `forum_comment_tbl`
--
ALTER TABLE `forum_comment_tbl`
  MODIFY `comment_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `forum_post_tbl`
--
ALTER TABLE `forum_post_tbl`
  MODIFY `post_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `guest_alert_tbl`
--
ALTER TABLE `guest_alert_tbl`
  MODIFY `alert_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `info_hub_tbl`
--
ALTER TABLE `info_hub_tbl`
  MODIFY `info_hub_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `message_tbl`
--
ALTER TABLE `message_tbl`
  MODIFY `message_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=18;

--
-- AUTO_INCREMENT for table `order_item_tbl`
--
ALTER TABLE `order_item_tbl`
  MODIFY `order_item_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT for table `order_tbl`
--
ALTER TABLE `order_tbl`
  MODIFY `order_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT for table `product_boost_tbl`
--
ALTER TABLE `product_boost_tbl`
  MODIFY `boost_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT for table `product_tbl`
--
ALTER TABLE `product_tbl`
  MODIFY `product_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=15;

--
-- AUTO_INCREMENT for table `promotion_packages_tbl`
--
ALTER TABLE `promotion_packages_tbl`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `site_settings_tbl`
--
ALTER TABLE `site_settings_tbl`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=216;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `admin_support_tbl`
--
ALTER TABLE `admin_support_tbl`
  ADD CONSTRAINT `fk_support_admin` FOREIGN KEY (`admin_id`) REFERENCES `admin_tbl` (`admin_id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_support_buyer` FOREIGN KEY (`buyer_id`) REFERENCES `buyer_tbl` (`buyer_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_support_farmer` FOREIGN KEY (`farmer_id`) REFERENCES `farmer_tbl` (`farmer_id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `buyer_address_tbl`
--
ALTER TABLE `buyer_address_tbl`
  ADD CONSTRAINT `fk_address_buyer` FOREIGN KEY (`buyer_id`) REFERENCES `buyer_tbl` (`buyer_id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `cart_item_tbl`
--
ALTER TABLE `cart_item_tbl`
  ADD CONSTRAINT `fk_cart_buyer` FOREIGN KEY (`buyer_id`) REFERENCES `buyer_tbl` (`buyer_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_cart_product` FOREIGN KEY (`product_id`) REFERENCES `product_tbl` (`product_id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `feedback_tbl`
--
ALTER TABLE `feedback_tbl`
  ADD CONSTRAINT `fk_feedback_buyer` FOREIGN KEY (`buyer_id`) REFERENCES `buyer_tbl` (`buyer_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_feedback_farmer` FOREIGN KEY (`farmer_id`) REFERENCES `farmer_tbl` (`farmer_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_feedback_product` FOREIGN KEY (`product_id`) REFERENCES `product_tbl` (`product_id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `info_hub_tbl`
--
ALTER TABLE `info_hub_tbl`
  ADD CONSTRAINT `fk_infohub_admin` FOREIGN KEY (`admin_id`) REFERENCES `admin_tbl` (`admin_id`) ON UPDATE CASCADE;

--
-- Constraints for table `message_tbl`
--
ALTER TABLE `message_tbl`
  ADD CONSTRAINT `fk_message_buyer` FOREIGN KEY (`buyer_id`) REFERENCES `buyer_tbl` (`buyer_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_message_farmer` FOREIGN KEY (`farmer_id`) REFERENCES `farmer_tbl` (`farmer_id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `order_item_tbl`
--
ALTER TABLE `order_item_tbl`
  ADD CONSTRAINT `fk_orderitem_order` FOREIGN KEY (`order_id`) REFERENCES `order_tbl` (`order_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_orderitem_product` FOREIGN KEY (`product_id`) REFERENCES `product_tbl` (`product_id`) ON UPDATE CASCADE;

--
-- Constraints for table `order_tbl`
--
ALTER TABLE `order_tbl`
  ADD CONSTRAINT `fk_order_address` FOREIGN KEY (`address_id`) REFERENCES `buyer_address_tbl` (`address_id`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_order_buyer` FOREIGN KEY (`buyer_id`) REFERENCES `buyer_tbl` (`buyer_id`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_order_farmer` FOREIGN KEY (`farmer_id`) REFERENCES `farmer_tbl` (`farmer_id`) ON UPDATE CASCADE;

--
-- Constraints for table `product_tbl`
--
ALTER TABLE `product_tbl`
  ADD CONSTRAINT `fk_product_category` FOREIGN KEY (`category_id`) REFERENCES `category_tbl` (`category_id`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_product_farmer` FOREIGN KEY (`farmer_id`) REFERENCES `farmer_tbl` (`farmer_id`) ON DELETE CASCADE ON UPDATE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
