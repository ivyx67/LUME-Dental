-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Oct 06, 2026 at 09:04 PM
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
-- Database: `lume_dental`
--

-- --------------------------------------------------------

--
-- Table structure for table `employees`
--

CREATE TABLE `employees` (
  `id` int(10) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED NOT NULL,
  `description` text DEFAULT NULL,
  `active` tinyint(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_polish_ci;

--
-- Dumping data for table `employees`
--

INSERT INTO `employees` (`id`, `user_id`, `description`, `active`) VALUES
(1, 2, 'Lekarz dentysta specjalizujący się w stomatologii zachowawczej.', 1),
(2, 3, 'Lekarz dentysta zajmujący się chirurgią stomatologiczną i diagnostyką.', 1);

-- --------------------------------------------------------

--
-- Table structure for table `employee_availability`
--

CREATE TABLE `employee_availability` (
  `id` int(10) UNSIGNED NOT NULL,
  `employee_id` int(10) UNSIGNED NOT NULL,
  `day_of_week` tinyint(3) UNSIGNED NOT NULL,
  `start_time` time NOT NULL,
  `end_time` time NOT NULL
) ;

--
-- Dumping data for table `employee_availability`
--

INSERT INTO `employee_availability` (`id`, `employee_id`, `day_of_week`, `start_time`, `end_time`) VALUES
(1, 1, 1, '08:00:00', '16:00:00'),
(2, 1, 2, '08:00:00', '16:00:00'),
(3, 1, 3, '10:00:00', '18:00:00'),
(4, 1, 4, '08:00:00', '16:00:00'),
(5, 1, 5, '08:00:00', '14:00:00'),
(6, 2, 1, '10:00:00', '18:00:00'),
(7, 2, 2, '10:00:00', '18:00:00'),
(8, 2, 3, '08:00:00', '16:00:00'),
(9, 2, 4, '10:00:00', '18:00:00'),
(10, 2, 5, '08:00:00', '16:00:00');

-- --------------------------------------------------------

--
-- Table structure for table `employee_services`
--

CREATE TABLE `employee_services` (
  `employee_id` int(10) UNSIGNED NOT NULL,
  `service_id` int(10) UNSIGNED NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_polish_ci;

--
-- Dumping data for table `employee_services`
--

INSERT INTO `employee_services` (`employee_id`, `service_id`) VALUES
(1, 1),
(1, 2),
(1, 3),
(1, 4),
(1, 5),
(1, 9),
(2, 6),
(2, 7),
(2, 8),
(2, 9);

-- --------------------------------------------------------

--
-- Table structure for table `reservations`
--

CREATE TABLE `reservations` (
  `id` int(10) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED NOT NULL,
  `employee_id` int(10) UNSIGNED NOT NULL,
  `service_id` int(10) UNSIGNED NOT NULL,
  `reservation_date` date NOT NULL,
  `start_time` time NOT NULL,
  `end_time` time NOT NULL,
  `status` enum('pending','confirmed','completed','cancelled') NOT NULL DEFAULT 'pending',
  `comment` text DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ;

--
-- Dumping data for table `reservations`
--

INSERT INTO `reservations` (`id`, `user_id`, `employee_id`, `service_id`, `reservation_date`, `start_time`, `end_time`, `status`, `comment`, `created_at`) VALUES
(1, 1, 1, 1, '2026-10-05', '10:00:00', '11:00:00', 'confirmed', 'Wizyta kontrolna', '2026-09-22 20:28:18');

-- --------------------------------------------------------

--
-- Table structure for table `services`
--

CREATE TABLE `services` (
  `id` int(10) UNSIGNED NOT NULL,
  `category_id` int(10) UNSIGNED NOT NULL,
  `name` varchar(150) NOT NULL,
  `description` text DEFAULT NULL,
  `duration` int(10) UNSIGNED NOT NULL,
  `price` decimal(10,2) NOT NULL,
  `active` tinyint(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_polish_ci;

--
-- Dumping data for table `services`
--

INSERT INTO `services` (`id`, `category_id`, `name`, `description`, `duration`, `price`, `active`) VALUES
(1, 1, 'Wypełnienie kompozytowe', 'Estetyczne wypełnienie ubytku zęba', 60, 250.00, 1),
(2, 1, 'Leczenie próchnicy', 'Leczenie zęba dotkniętego próchnicą', 60, 200.00, 1),
(3, 2, 'Higienizacja', 'Kompleksowa higienizacja jamy ustnej', 60, 250.00, 1),
(4, 2, 'Skaling', 'Usuwanie kamienia nazębnego', 45, 180.00, 1),
(5, 2, 'Piaskowanie', 'Usuwanie osadu i przebarwień', 45, 180.00, 1),
(6, 3, 'Konsultacja protetyczna', 'Konsultacja dotycząca leczenia protetycznego', 30, 150.00, 1),
(7, 4, 'Ekstrakcja zęba', 'Usunięcie zęba', 45, 250.00, 1),
(8, 5, 'Konsultacja ortodontyczna', 'Ocena wady zgryzu i możliwości leczenia', 30, 150.00, 1),
(9, 6, 'Konsultacja stomatologiczna', 'Ogólna konsultacja stomatologiczna', 30, 120.00, 1);

-- --------------------------------------------------------

--
-- Table structure for table `service_categories`
--

CREATE TABLE `service_categories` (
  `id` int(10) UNSIGNED NOT NULL,
  `name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_polish_ci;

--
-- Dumping data for table `service_categories`
--

INSERT INTO `service_categories` (`id`, `name`, `description`) VALUES
(1, 'Stomatologia zachowawcza', 'Leczenie i odbudowa zębów'),
(2, 'Higienizacja', 'Zabiegi związane z higieną jamy ustnej'),
(3, 'Protetyka', 'Odbudowa brakujących lub uszkodzonych zębów'),
(4, 'Chirurgia stomatologiczna', 'Zabiegi chirurgiczne w obrębie jamy ustnej'),
(5, 'Ortodoncja', 'Diagnostyka i leczenie wad zgryzu'),
(6, 'Diagnostyka', 'Badania i konsultacje stomatologiczne');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(10) UNSIGNED NOT NULL,
  `name` varchar(50) NOT NULL,
  `surname` varchar(80) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `role` enum('client','employee','admin') NOT NULL DEFAULT 'client',
  `active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_polish_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `name`, `surname`, `email`, `password`, `phone`, `role`, `active`, `created_at`) VALUES
(1, 'Jan', 'Kowalski', 'jan.kowalski@example.com', 'test123', '501234567', 'client', 1, '2026-09-22 20:28:18'),
(2, 'Anna', 'Nowak', 'anna.nowak@lume-dental.pl', 'test123', '502345678', 'employee', 1, '2026-09-22 20:28:18'),
(3, 'Piotr', 'Wisniewski', 'piotr.wisniewski@lume-dental.pl', 'test123', '503456789', 'employee', 1, '2026-09-22 20:28:18'),
(4, 'Admin', 'Lume', 'admin@lume-dental.pl', 'test123', '504567890', 'admin', 1, '2026-09-22 20:28:18');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `employees`
--
ALTER TABLE `employees`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `user_id` (`user_id`);

--
-- Indexes for table `employee_availability`
--
ALTER TABLE `employee_availability`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_availability_employee` (`employee_id`);

--
-- Indexes for table `employee_services`
--
ALTER TABLE `employee_services`
  ADD PRIMARY KEY (`employee_id`,`service_id`),
  ADD KEY `fk_employee_services_service` (`service_id`);

--
-- Indexes for table `reservations`
--
ALTER TABLE `reservations`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_reservations_user` (`user_id`),
  ADD KEY `fk_reservations_employee` (`employee_id`),
  ADD KEY `fk_reservations_service` (`service_id`);

--
-- Indexes for table `services`
--
ALTER TABLE `services`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_services_category` (`category_id`);

--
-- Indexes for table `service_categories`
--
ALTER TABLE `service_categories`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `name` (`name`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `employees`
--
ALTER TABLE `employees`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `employee_availability`
--
ALTER TABLE `employee_availability`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `reservations`
--
ALTER TABLE `reservations`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `services`
--
ALTER TABLE `services`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT for table `service_categories`
--
ALTER TABLE `service_categories`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `employees`
--
ALTER TABLE `employees`
  ADD CONSTRAINT `fk_employees_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `employee_availability`
--
ALTER TABLE `employee_availability`
  ADD CONSTRAINT `fk_availability_employee` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `employee_services`
--
ALTER TABLE `employee_services`
  ADD CONSTRAINT `fk_employee_services_employee` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_employee_services_service` FOREIGN KEY (`service_id`) REFERENCES `services` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `reservations`
--
ALTER TABLE `reservations`
  ADD CONSTRAINT `fk_reservations_employee` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`id`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_reservations_service` FOREIGN KEY (`service_id`) REFERENCES `services` (`id`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_reservations_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON UPDATE CASCADE;

--
-- Constraints for table `services`
--
ALTER TABLE `services`
  ADD CONSTRAINT `fk_services_category` FOREIGN KEY (`category_id`) REFERENCES `service_categories` (`id`) ON UPDATE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
