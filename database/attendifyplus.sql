-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Jun 28, 2025 at 02:18 PM
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
-- Database: `attendifyplus_fainal`
--

-- --------------------------------------------------------

--
-- Table structure for table `admins`
--

CREATE TABLE `admins` (
  `AdminID` int(11) NOT NULL,
  `FullName` varchar(100) NOT NULL,
  `Contact` varchar(20) DEFAULT NULL,
  `Address` varchar(200) DEFAULT NULL,
  `PhotoURL` varchar(255) DEFAULT NULL,
  `LoginID` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `admins`
--

INSERT INTO `admins` (`AdminID`, `FullName`, `Contact`, `Address`, `PhotoURL`, `LoginID`) VALUES
(1, 'System Administrator', '9800000001', 'Pokhara, Nepal', NULL, 1);

-- --------------------------------------------------------

--
-- Table structure for table `attendance_records`
--

CREATE TABLE `attendance_records` (
  `AttendanceID` int(11) NOT NULL,
  `StudentID` int(11) NOT NULL,
  `SubjectID` int(11) NOT NULL,
  `TeacherID` int(11) NOT NULL,
  `DateTime` datetime DEFAULT current_timestamp(),
  `Status` enum('present','absent','late') DEFAULT 'present',
  `Method` enum('manual','qr') DEFAULT 'manual'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `attendance_records`
--

INSERT INTO `attendance_records` (`AttendanceID`, `StudentID`, `SubjectID`, `TeacherID`, `DateTime`, `Status`, `Method`) VALUES
(1, 1, 1, 1, '2024-12-20 09:00:00', 'present', 'manual'),
(2, 1, 2, 1, '2024-12-20 10:00:00', 'present', 'qr'),
(3, 1, 3, 1, '2024-12-20 11:00:00', 'late', 'manual'),
(4, 2, 6, 1, '2024-12-20 09:00:00', 'present', 'qr'),
(5, 2, 7, 1, '2024-12-20 10:00:00', 'absent', 'manual'),
(6, 3, 11, 1, '2024-12-20 09:00:00', 'present', 'qr'),
(7, 4, 16, 2, '2024-12-20 10:00:00', 'present', 'manual'),
(8, 5, 21, 2, '2024-12-20 11:00:00', 'late', 'qr'),
(9, 7, 29, 3, '2024-12-20 09:00:00', 'present', 'qr'),
(10, 7, 30, 3, '2024-12-20 10:00:00', 'present', 'manual'),
(11, 8, 34, 3, '2024-12-20 11:00:00', 'late', 'manual'),
(12, 9, 39, 3, '2024-12-20 09:00:00', 'present', 'qr'),
(13, 10, 44, 4, '2024-12-20 10:00:00', 'present', 'qr'),
(14, 11, 3, 1, '2025-06-28 11:55:34', 'present', 'qr'),
(15, 1, 3, 1, '2025-06-28 11:57:12', 'absent', 'manual'),
(16, 11, 1, 1, '2025-06-28 12:01:51', 'present', 'qr'),
(17, 1, 1, 1, '2025-06-28 12:02:13', 'absent', 'manual'),
(18, 11, 2, 1, '2025-06-28 12:05:59', 'present', 'qr');

-- --------------------------------------------------------

--
-- Table structure for table `departments`
--

CREATE TABLE `departments` (
  `DepartmentID` int(11) NOT NULL,
  `DepartmentName` varchar(100) NOT NULL,
  `DepartmentCode` varchar(10) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `departments`
--

INSERT INTO `departments` (`DepartmentID`, `DepartmentName`, `DepartmentCode`) VALUES
(1, 'Bachelor of Computer Application', 'BCA'),
(2, 'Bachelor of Business Administration', 'BBA');

-- --------------------------------------------------------

--
-- Table structure for table `device_registration_tokens`
--

CREATE TABLE `device_registration_tokens` (
  `TokenID` int(11) NOT NULL,
  `StudentID` int(11) NOT NULL,
  `Token` varchar(64) NOT NULL,
  `ExpiresAt` datetime NOT NULL,
  `Used` tinyint(1) DEFAULT 0,
  `CreatedAt` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `device_registration_tokens`
--

INSERT INTO `device_registration_tokens` (`TokenID`, `StudentID`, `Token`, `ExpiresAt`, `Used`, `CreatedAt`) VALUES
(1, 2, 'abc123def456token789', '2025-06-28 11:24:47', 0, '2025-06-28 11:14:47'),
(2, 7, 'xyz987uvw654token321', '2025-06-28 11:29:47', 0, '2025-06-28 11:14:47'),
(3, 8, 'token456def789abc123', '2025-06-28 11:19:47', 0, '2025-06-28 11:14:47'),
(4, 4, 'used123token456def789', '2025-06-28 11:09:47', 1, '2025-06-28 11:14:47'),
(5, 11, 'e3851569cc4091347c0faf8f19efae4e532a5f39c700f8d4b1fa2c86c30a7f86', '2025-06-28 11:51:52', 1, '2025-06-28 11:41:52'),
(6, 2, '1afdc836544296a83d09109025f3ccabf6547d19312f354ff8c6f5e127f1c7ce', '2025-06-28 16:30:00', 1, '2025-06-28 16:20:00');

-- --------------------------------------------------------

--
-- Table structure for table `login_tbl`
--

CREATE TABLE `login_tbl` (
  `LoginID` int(11) NOT NULL,
  `Email` varchar(100) NOT NULL,
  `Password` varchar(255) NOT NULL,
  `Role` enum('admin','teacher','student') NOT NULL,
  `Status` enum('active','inactive') DEFAULT 'active',
  `CreatedDate` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `login_tbl`
--

INSERT INTO `login_tbl` (`LoginID`, `Email`, `Password`, `Role`, `Status`, `CreatedDate`) VALUES
(1, 'admin@lagrandee.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin', 'active', '2025-06-28 11:14:46'),
(2, 'teacher.bca1@lagrandee.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'teacher', 'active', '2025-06-28 11:14:46'),
(3, 'teacher.bca2@lagrandee.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'teacher', 'active', '2025-06-28 11:14:46'),
(4, 'teacher.bba1@lagrandee.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'teacher', 'active', '2025-06-28 11:14:46'),
(5, 'teacher.bba2@lagrandee.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'teacher', 'active', '2025-06-28 11:14:46'),
(6, 'student.ram@lagrandee.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'student', 'active', '2025-06-28 11:14:46'),
(7, 'student.sita@lagrandee.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'student', 'active', '2025-06-28 11:14:46'),
(8, 'student.hari@lagrandee.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'student', 'active', '2025-06-28 11:14:46'),
(9, 'student.gita@lagrandee.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'student', 'active', '2025-06-28 11:14:46'),
(10, 'student.rita@lagrandee.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'student', 'active', '2025-06-28 11:14:46'),
(11, 'student.maya@lagrandee.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'student', 'active', '2025-06-28 11:14:46'),
(12, 'student.kiran@lagrandee.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'student', 'active', '2025-06-28 11:14:46'),
(13, 'student.deepak@lagrandee.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'student', 'active', '2025-06-28 11:14:46'),
(14, 'student.binita@lagrandee.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'student', 'active', '2025-06-28 11:14:46'),
(15, 'student.suresh@lagrandee.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'student', 'active', '2025-06-28 11:14:46'),
(16, 'ramon1@lagrandee.com', '$2y$10$vY8P6qf2TYwgtFkTv.J/N.1pspXqr3zXMDSeoTc2yu4PXV3G5FHB.', 'student', 'active', '2025-06-28 11:41:52');

-- --------------------------------------------------------

--
-- Table structure for table `materials`
--

CREATE TABLE `materials` (
  `MaterialID` int(11) NOT NULL,
  `TeacherID` int(11) NOT NULL,
  `SubjectID` int(11) NOT NULL,
  `Title` varchar(255) NOT NULL,
  `Description` text DEFAULT NULL,
  `FileName` varchar(255) NOT NULL,
  `OriginalFileName` varchar(255) NOT NULL,
  `FileSize` bigint(20) NOT NULL,
  `FileType` varchar(50) NOT NULL,
  `FilePath` varchar(500) NOT NULL,
  `UploadDateTime` datetime NOT NULL DEFAULT current_timestamp(),
  `IsActive` tinyint(1) NOT NULL DEFAULT 1,
  `DownloadCount` int(11) DEFAULT 0,
  `Tags` varchar(500) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `materials`
--

INSERT INTO `materials` (`MaterialID`, `TeacherID`, `SubjectID`, `Title`, `Description`, `FileName`, `OriginalFileName`, `FileSize`, `FileType`, `FilePath`, `UploadDateTime`, `IsActive`, `DownloadCount`, `Tags`) VALUES
(1, 1, 1, 'Introduction to IT - Chapter 1', 'Basic concepts of Information Technology', 'it_chapter1_20241220.pdf', 'IT_Chapter1.pdf', 2048576, 'application/pdf', '/uploads/materials/it_chapter1_20241220.pdf', '2025-06-28 11:14:47', 1, 0, 'IT,Fundamentals,Chapter1'),
(2, 1, 6, 'C Programming Basics', 'Getting started with C programming language', 'c_programming_basics.pdf', 'C_Programming_Basics.pdf', 1536000, 'application/pdf', '/uploads/materials/c_programming_basics.pdf', '2025-06-28 11:14:47', 1, 0, 'C,Programming,Basics'),
(3, 3, 39, 'Marketing Mix - 4Ps', 'Understanding Product, Price, Place, Promotion', 'marketing_mix_4ps.pptx', 'Marketing_Mix_4Ps.pptx', 3072000, 'application/vnd.openxmlformats-officedocument.pres', '/uploads/materials/marketing_mix_4ps.pptx', '2025-06-28 11:14:47', 1, 0, 'Marketing,4Ps,Mix'),
(4, 4, 44, 'Financial Management Notes', 'Introduction to Financial Management principles', 'financial_mgmt_notes.pdf', 'Financial_Management_Notes.pdf', 1024000, 'application/pdf', '/uploads/materials/financial_mgmt_notes.pdf', '2025-06-28 11:14:47', 1, 0, 'Finance,Management,BBA');

-- --------------------------------------------------------

--
-- Table structure for table `material_access_logs`
--

CREATE TABLE `material_access_logs` (
  `LogID` int(11) NOT NULL,
  `MaterialID` int(11) NOT NULL,
  `StudentID` int(11) DEFAULT NULL,
  `TeacherID` int(11) DEFAULT NULL,
  `AccessDateTime` datetime NOT NULL DEFAULT current_timestamp(),
  `ActionType` enum('view','download') NOT NULL,
  `IPAddress` varchar(45) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `qr_attendance_sessions`
--

CREATE TABLE `qr_attendance_sessions` (
  `SessionID` int(11) NOT NULL,
  `TeacherID` int(11) NOT NULL,
  `SubjectID` int(11) NOT NULL,
  `Date` date NOT NULL,
  `QRToken` varchar(255) NOT NULL,
  `ExpiresAt` datetime NOT NULL,
  `IsActive` tinyint(1) DEFAULT 1,
  `CreatedAt` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `qr_attendance_sessions`
--

INSERT INTO `qr_attendance_sessions` (`SessionID`, `TeacherID`, `SubjectID`, `Date`, `QRToken`, `ExpiresAt`, `IsActive`, `CreatedAt`) VALUES
(1, 1, 3, '2025-06-28', 'e4b774805c2eb87c470435af8fd83ff5588e47608d2e3e9622e86dab66ea26a2', '2025-06-28 11:20:36', 0, '2025-06-28 11:15:36'),
(2, 1, 3, '2025-06-28', '4ce39e26561eafb9f21ff11e9f42309bc4f0cbcd79b25e49dca2d33967ce4bb7', '2025-06-28 11:20:44', 0, '2025-06-28 11:15:44'),
(3, 1, 3, '2025-06-28', '4f8a0ddded2ffc1c004b3391e2cc3a80af94b12e3f7f88352ebd1ea0876f94ff', '2025-06-28 11:20:45', 0, '2025-06-28 11:15:45'),
(4, 1, 3, '2025-06-28', 'ee7715bf816cfee2039ebefd216cfe263fd471dda2076e62553c90792b300f2b', '2025-06-28 11:20:46', 0, '2025-06-28 11:15:46'),
(5, 1, 3, '2025-06-28', 'a8c39411d7148fdd1d351f439aa035fc21b1cc081f8759aa3232f5135e7a966f', '2025-06-28 11:24:44', 0, '2025-06-28 11:19:45'),
(6, 1, 3, '2025-06-28', '4eb434e34370b93df894922dad340fa7931621af7505e3d2103508d7e021f879', '2025-06-28 11:24:46', 0, '2025-06-28 11:19:46'),
(7, 1, 3, '2025-06-28', 'ea1247aad2ee0731351a9e7462cfd7d2b9356e5568c46b8b9785ae1ceafb2fac', '2025-06-28 11:30:25', 0, '2025-06-28 11:25:25'),
(8, 1, 3, '2025-06-28', 'b09eeab08e3d28577af90cc65bbd01c1f80324a0882bcb6036aab1bfb3a63185', '2025-06-28 11:30:35', 0, '2025-06-28 11:25:35'),
(9, 1, 3, '2025-06-28', '429f14947f3efbdf0c7a542f6f595fa3ddc47c0d671b5a9f3af4365e1ef7a3cb', '2025-06-28 11:35:36', 0, '2025-06-28 11:30:36'),
(10, 1, 3, '2025-06-28', 'aa18c5960e4c8f9039104098d8a6f5aafc5687509cb552fefc1d02784e943064', '2025-06-28 11:42:31', 0, '2025-06-28 11:37:31'),
(11, 1, 3, '2025-06-28', '1e84d872a1b30dc89957813a65fbd1919a5b5850366b520c303c28c0f014876d', '2025-06-28 11:42:38', 0, '2025-06-28 11:37:38'),
(12, 1, 3, '2025-06-28', '0acacfa79a0d45a58d0058be6da6a5633ac80b7b64ea57f84657b751ec6e7bc6', '2025-06-28 11:48:02', 0, '2025-06-28 11:43:03'),
(13, 1, 3, '2025-06-28', '538eef477aea3698f6ec581e3860c42701d03e44d1a99c0b31bce03425adb153', '2025-06-28 11:59:54', 0, '2025-06-28 11:54:54'),
(14, 1, 1, '2025-06-28', '59b40513614690ba7f114428f766c698a8f4c11f2edbe1ee20d30da98199b0ee', '2025-06-28 12:06:33', 0, '2025-06-28 12:01:33'),
(15, 1, 12, '2025-06-28', '3d63ba60d5bdd4c8d44b80bd91c360f0d2925b5c194cead34d7b5e898826216c', '2025-06-28 12:10:31', 1, '2025-06-28 12:05:31'),
(16, 1, 2, '2025-06-28', 'b726d365948e29b527375c9c7039d65e7a373fb812802962e5f6fbe8e15bdd54', '2025-06-28 12:10:58', 0, '2025-06-28 12:05:58'),
(17, 1, 2, '2025-06-28', '1719991860d0d8a5a7f4f4c028272d533e18c4d27d2945fece8be797694ad09a', '2025-06-28 13:29:00', 1, '2025-06-28 13:24:00'),
(18, 1, 3, '2025-06-28', '565684209e455cfdf07ce6cb640626607dac6a890e7d2a2c4bbedc39286ff4ff', '2025-06-28 16:18:56', 1, '2025-06-28 16:13:56'),
(19, 1, 1, '2025-06-28', '036a07e1e024795bd131cc7064a79c71369a8116d933d6e34a46fcbe67aaa047', '2025-06-28 18:05:31', 1, '2025-06-28 18:00:31');

-- --------------------------------------------------------

--
-- Table structure for table `semesters`
--

CREATE TABLE `semesters` (
  `SemesterID` int(11) NOT NULL,
  `SemesterNumber` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `semesters`
--

INSERT INTO `semesters` (`SemesterID`, `SemesterNumber`) VALUES
(1, 1),
(2, 2),
(3, 3),
(4, 4),
(5, 5),
(6, 6),
(7, 7),
(8, 8);

-- --------------------------------------------------------

--
-- Table structure for table `students`
--

CREATE TABLE `students` (
  `StudentID` int(11) NOT NULL,
  `FullName` varchar(100) NOT NULL,
  `Contact` varchar(20) DEFAULT NULL,
  `Address` varchar(200) DEFAULT NULL,
  `PhotoURL` varchar(255) DEFAULT NULL,
  `DepartmentID` int(11) NOT NULL,
  `SemesterID` int(11) NOT NULL,
  `JoinYear` year(4) DEFAULT year(curdate()),
  `ProgramCode` varchar(50) DEFAULT NULL,
  `DeviceRegistered` tinyint(1) DEFAULT 0,
  `LoginID` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `students`
--

INSERT INTO `students` (`StudentID`, `FullName`, `Contact`, `Address`, `PhotoURL`, `DepartmentID`, `SemesterID`, `JoinYear`, `ProgramCode`, `DeviceRegistered`, `LoginID`) VALUES
(1, 'Ram Bahadur Thapa', '9801111111', 'Pokhara-15, Kaski', NULL, 1, 1, '2024', 'BCA-2024', 1, 6),
(2, 'Sita Kumari Poudel', '9802222222', 'Kathmandu-10, Bagmati', NULL, 1, 2, '2024', 'BCA-2024', 1, 7),
(3, 'Gita Devi Acharya', '9804444444', 'Chitwan-2, Bharatpur', NULL, 1, 3, '2024', 'BCA-2024', 1, 9),
(4, 'Maya Laxmi Shrestha', '9807777777', 'Lalitpur-3, Bagmati', NULL, 1, 4, '2023', 'BCA-2023', 0, 11),
(5, 'Kiran Bahadur Magar', '9808888888', 'Pokhara-17, Kaski', NULL, 1, 5, '2023', 'BCA-2023', 1, 12),
(6, 'Deepak Gurung', '9809999999', 'Butwal-5, Rupandehi', NULL, 1, 6, '2022', 'BCA-2022', 0, 13),
(7, 'Hari Prasad Sharma', '9803333333', 'Butwal-8, Rupandehi', NULL, 2, 1, '2024', 'BBA-2024', 0, 8),
(8, 'Rita Kumari Gurung', '9805555555', 'Pokhara-12, Kaski', NULL, 2, 2, '2024', 'BBA-2024', 0, 10),
(9, 'Binita Rai', '9806666666', 'Dharan-5, Sunsari', NULL, 2, 3, '2024', 'BBA-2024', 1, 14),
(10, 'Suresh Tamang', '9800111222', 'Kathmandu-5, Bagmati', NULL, 2, 4, '2023', 'BBA-2023', 1, 15),
(11, 'Ramon Tiwari', '9812310292', 'Pokhara-21', '../uploads/students/student_685f8423e859a4.21870885.jpg', 1, 1, '2025', 'BACHELOR OF COMPUTER APPLICATION-2025', 1, 16);

-- --------------------------------------------------------

--
-- Table structure for table `student_devices`
--

CREATE TABLE `student_devices` (
  `DeviceID` int(11) NOT NULL,
  `StudentID` int(11) NOT NULL,
  `DeviceFingerprint` varchar(255) NOT NULL,
  `DeviceName` varchar(100) DEFAULT NULL,
  `DeviceInfo` text DEFAULT NULL,
  `IsActive` tinyint(1) DEFAULT 1,
  `RegisteredAt` datetime DEFAULT current_timestamp(),
  `LastUsed` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `student_devices`
--

INSERT INTO `student_devices` (`DeviceID`, `StudentID`, `DeviceFingerprint`, `DeviceName`, `DeviceInfo`, `IsActive`, `RegisteredAt`, `LastUsed`) VALUES
(1, 11, '4a922aff', 'Device-4a922aff', '{\"user_agent\":\"Mozilla\\/5.0 (iPhone; CPU iPhone OS 18_5_0 like Mac OS X) AppleWebKit\\/605.1.15 (KHTML, like Gecko) CriOS\\/137.0.7151.107 Mobile\\/15E148 Safari\\/604.1\",\"registered_at\":\"2025-06-28 11:42:26\"}', 1, '2025-06-28 11:42:26', NULL),
(2, 2, '4a922aff', 'Device-4a922aff', '{\"user_agent\":\"Mozilla\\/5.0 (iPhone; CPU iPhone OS 18_5_0 like Mac OS X) AppleWebKit\\/605.1.15 (KHTML, like Gecko) CriOS\\/137.0.7151.107 Mobile\\/15E148 Safari\\/604.1\",\"registered_at\":\"2025-06-28 16:20:39\"}', 1, '2025-06-28 16:20:39', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `subjects`
--

CREATE TABLE `subjects` (
  `SubjectID` int(11) NOT NULL,
  `SubjectCode` varchar(20) NOT NULL,
  `SubjectName` varchar(100) NOT NULL,
  `CreditHour` int(11) NOT NULL,
  `LectureHour` int(11) DEFAULT 48,
  `IsElective` tinyint(1) DEFAULT 0,
  `DepartmentID` int(11) NOT NULL,
  `SemesterID` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `subjects`
--

INSERT INTO `subjects` (`SubjectID`, `SubjectCode`, `SubjectName`, `CreditHour`, `LectureHour`, `IsElective`, `DepartmentID`, `SemesterID`) VALUES
(1, 'BCA101', 'Fundamentals of Information Technology', 3, 48, 0, 1, 1),
(2, 'BCA102', 'Mathematics I (Calculus & Algebra)', 3, 48, 0, 1, 1),
(3, 'BCA103', 'Digital Logic and Computer Organization', 3, 48, 0, 1, 1),
(4, 'BCA104', 'English Communication', 3, 48, 0, 1, 1),
(5, 'BCA105', 'Physics for Computing', 3, 48, 0, 1, 1),
(6, 'BCA201', 'C Programming Language', 4, 64, 0, 1, 2),
(7, 'BCA202', 'Mathematics II (Statistics & Probability)', 3, 48, 0, 1, 2),
(8, 'BCA203', 'Microprocessor and Assembly Language', 3, 48, 0, 1, 2),
(9, 'BCA204', 'Discrete Mathematical Structures', 3, 48, 0, 1, 2),
(10, 'BCA205', 'Financial Accounting', 3, 48, 0, 1, 2),
(11, 'BCA301', 'Data Structures and Algorithms', 4, 64, 0, 1, 3),
(12, 'BCA302', 'Object Oriented Programming (Java)', 4, 64, 0, 1, 3),
(13, 'BCA303', 'Computer Graphics and Animation', 3, 48, 0, 1, 3),
(14, 'BCA304', 'Web Technology I (HTML, CSS, JS)', 3, 48, 0, 1, 3),
(15, 'BCA305', 'Mathematics III (Numerical Methods)', 3, 48, 0, 1, 3),
(16, 'BCA401', 'Database Management System', 4, 64, 0, 1, 4),
(17, 'BCA402', 'Operating Systems', 3, 48, 0, 1, 4),
(18, 'BCA403', 'Web Technology II (PHP, MySQL)', 4, 64, 0, 1, 4),
(19, 'BCA404', 'Software Engineering', 3, 48, 0, 1, 4),
(20, 'BCA405', 'Computer Networks', 3, 48, 0, 1, 4),
(21, 'BCA501', 'Mobile Application Development', 4, 64, 0, 1, 5),
(22, 'BCA502', 'System Analysis and Design', 3, 48, 0, 1, 5),
(23, 'BCA503', 'Advanced Java Programming', 4, 64, 0, 1, 5),
(24, 'BCA504', 'E-commerce and Digital Marketing', 3, 48, 1, 1, 5),
(25, 'BCA505', 'Project Management', 3, 48, 0, 1, 5),
(26, 'BCA601', 'Artificial Intelligence', 3, 48, 0, 1, 6),
(27, 'BCA602', 'Cyber Security and Ethical Hacking', 3, 48, 0, 1, 6),
(28, 'BCA603', 'Cloud Computing', 3, 48, 0, 1, 6),
(29, 'BCA604', 'Final Year Project I', 6, 96, 0, 1, 6),
(30, 'BBA101', 'Principles of Management', 3, 48, 0, 2, 1),
(31, 'BBA102', 'Business Mathematics', 3, 48, 0, 2, 1),
(32, 'BBA103', 'Microeconomics', 3, 48, 0, 2, 1),
(33, 'BBA104', 'Business English', 3, 48, 0, 2, 1),
(34, 'BBA105', 'Computer Applications in Business', 3, 48, 0, 2, 1),
(35, 'BBA201', 'Macroeconomics', 3, 48, 0, 2, 2),
(36, 'BBA202', 'Financial Accounting', 3, 48, 0, 2, 2),
(37, 'BBA203', 'Business Statistics', 3, 48, 0, 2, 2),
(38, 'BBA204', 'Organizational Behavior', 3, 48, 0, 2, 2),
(39, 'BBA205', 'Business Communication', 3, 48, 0, 2, 2),
(40, 'BBA301', 'Marketing Management', 3, 48, 0, 2, 3),
(41, 'BBA302', 'Human Resource Management', 3, 48, 0, 2, 3),
(42, 'BBA303', 'Business Law', 3, 48, 0, 2, 3),
(43, 'BBA304', 'Cost and Management Accounting', 3, 48, 0, 2, 3),
(44, 'BBA305', 'Research Methodology', 3, 48, 0, 2, 3),
(45, 'BBA401', 'Financial Management', 3, 48, 0, 2, 4),
(46, 'BBA402', 'Operations Management', 3, 48, 0, 2, 4),
(47, 'BBA403', 'International Business', 3, 48, 0, 2, 4),
(48, 'BBA404', 'Entrepreneurship Development', 3, 48, 0, 2, 4),
(49, 'BBA405', 'Business Ethics', 3, 48, 0, 2, 4),
(50, 'BBA501', 'Strategic Management', 3, 48, 0, 2, 5),
(51, 'BBA502', 'Investment and Portfolio Management', 3, 48, 0, 2, 5),
(52, 'BBA503', 'Digital Marketing', 3, 48, 0, 2, 5),
(53, 'BBA504', 'Supply Chain Management', 3, 48, 0, 2, 5),
(54, 'BBA505', 'Business Analytics', 3, 48, 1, 2, 5),
(55, 'BBA601', 'Corporate Governance', 3, 48, 0, 2, 6),
(56, 'BBA602', 'Risk Management', 3, 48, 0, 2, 6),
(57, 'BBA603', 'Final Year Project I', 6, 96, 0, 2, 6);

-- --------------------------------------------------------

--
-- Table structure for table `teachers`
--

CREATE TABLE `teachers` (
  `TeacherID` int(11) NOT NULL,
  `FullName` varchar(100) NOT NULL,
  `Contact` varchar(20) DEFAULT NULL,
  `Address` varchar(200) DEFAULT NULL,
  `PhotoURL` varchar(255) DEFAULT NULL,
  `LoginID` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `teachers`
--

INSERT INTO `teachers` (`TeacherID`, `FullName`, `Contact`, `Address`, `PhotoURL`, `LoginID`) VALUES
(1, 'Dr. Rajesh Kumar Sharma', '9801234567', 'Pokhara-8, Kaski', NULL, 2),
(2, 'Prof. Anjali Thapa', '9802345678', 'Kathmandu-7, Bagmati', NULL, 3),
(3, 'Er. Bikash Adhikari', '9803456789', 'Butwal-11, Rupandehi', NULL, 4),
(4, 'Dr. Sunita Poudel', '9804567890', 'Chitwan-5, Bharatpur', NULL, 5);

-- --------------------------------------------------------

--
-- Table structure for table `teacher_department_map`
--

CREATE TABLE `teacher_department_map` (
  `MapID` int(11) NOT NULL,
  `TeacherID` int(11) NOT NULL,
  `DepartmentID` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `teacher_department_map`
--

INSERT INTO `teacher_department_map` (`MapID`, `TeacherID`, `DepartmentID`) VALUES
(1, 1, 1),
(2, 2, 1),
(3, 3, 2),
(4, 4, 2);

-- --------------------------------------------------------

--
-- Table structure for table `teacher_subject_map`
--

CREATE TABLE `teacher_subject_map` (
  `MapID` int(11) NOT NULL,
  `TeacherID` int(11) NOT NULL,
  `SubjectID` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `teacher_subject_map`
--

INSERT INTO `teacher_subject_map` (`MapID`, `TeacherID`, `SubjectID`) VALUES
(1, 1, 1),
(2, 1, 2),
(3, 1, 3),
(4, 1, 6),
(5, 1, 7),
(6, 1, 11),
(7, 1, 12),
(8, 1, 15),
(9, 2, 16),
(10, 2, 17),
(11, 2, 18),
(12, 2, 21),
(13, 2, 22),
(14, 2, 23),
(15, 2, 26),
(16, 2, 27),
(17, 2, 28),
(18, 3, 29),
(19, 3, 30),
(20, 3, 31),
(21, 3, 34),
(22, 3, 35),
(23, 3, 36),
(24, 3, 39),
(25, 3, 40),
(26, 3, 41),
(27, 4, 44),
(28, 4, 45),
(29, 4, 46),
(30, 4, 49),
(31, 4, 50),
(32, 4, 51),
(33, 4, 54),
(34, 4, 55),
(35, 4, 56);

--
-- Indexes for dumped tables
--

--
-- Indexes for table `admins`
--
ALTER TABLE `admins`
  ADD PRIMARY KEY (`AdminID`),
  ADD UNIQUE KEY `LoginID` (`LoginID`);

--
-- Indexes for table `attendance_records`
--
ALTER TABLE `attendance_records`
  ADD PRIMARY KEY (`AttendanceID`),
  ADD KEY `StudentID` (`StudentID`),
  ADD KEY `SubjectID` (`SubjectID`),
  ADD KEY `TeacherID` (`TeacherID`);

--
-- Indexes for table `departments`
--
ALTER TABLE `departments`
  ADD PRIMARY KEY (`DepartmentID`),
  ADD UNIQUE KEY `DepartmentName` (`DepartmentName`),
  ADD UNIQUE KEY `DepartmentCode` (`DepartmentCode`);

--
-- Indexes for table `device_registration_tokens`
--
ALTER TABLE `device_registration_tokens`
  ADD PRIMARY KEY (`TokenID`),
  ADD KEY `StudentID` (`StudentID`),
  ADD KEY `idx_token` (`Token`),
  ADD KEY `idx_expires` (`ExpiresAt`);

--
-- Indexes for table `login_tbl`
--
ALTER TABLE `login_tbl`
  ADD PRIMARY KEY (`LoginID`),
  ADD UNIQUE KEY `Email` (`Email`);

--
-- Indexes for table `materials`
--
ALTER TABLE `materials`
  ADD PRIMARY KEY (`MaterialID`),
  ADD KEY `TeacherID` (`TeacherID`),
  ADD KEY `SubjectID` (`SubjectID`);

--
-- Indexes for table `material_access_logs`
--
ALTER TABLE `material_access_logs`
  ADD PRIMARY KEY (`LogID`),
  ADD KEY `MaterialID` (`MaterialID`),
  ADD KEY `StudentID` (`StudentID`),
  ADD KEY `TeacherID` (`TeacherID`);

--
-- Indexes for table `qr_attendance_sessions`
--
ALTER TABLE `qr_attendance_sessions`
  ADD PRIMARY KEY (`SessionID`),
  ADD KEY `TeacherID` (`TeacherID`),
  ADD KEY `SubjectID` (`SubjectID`);

--
-- Indexes for table `semesters`
--
ALTER TABLE `semesters`
  ADD PRIMARY KEY (`SemesterID`);

--
-- Indexes for table `students`
--
ALTER TABLE `students`
  ADD PRIMARY KEY (`StudentID`),
  ADD UNIQUE KEY `LoginID` (`LoginID`),
  ADD KEY `DepartmentID` (`DepartmentID`),
  ADD KEY `SemesterID` (`SemesterID`);

--
-- Indexes for table `student_devices`
--
ALTER TABLE `student_devices`
  ADD PRIMARY KEY (`DeviceID`),
  ADD UNIQUE KEY `unique_device_student` (`DeviceFingerprint`,`StudentID`),
  ADD KEY `StudentID` (`StudentID`);

--
-- Indexes for table `subjects`
--
ALTER TABLE `subjects`
  ADD PRIMARY KEY (`SubjectID`),
  ADD KEY `DepartmentID` (`DepartmentID`),
  ADD KEY `SemesterID` (`SemesterID`);

--
-- Indexes for table `teachers`
--
ALTER TABLE `teachers`
  ADD PRIMARY KEY (`TeacherID`),
  ADD UNIQUE KEY `LoginID` (`LoginID`);

--
-- Indexes for table `teacher_department_map`
--
ALTER TABLE `teacher_department_map`
  ADD PRIMARY KEY (`MapID`),
  ADD KEY `TeacherID` (`TeacherID`),
  ADD KEY `DepartmentID` (`DepartmentID`);

--
-- Indexes for table `teacher_subject_map`
--
ALTER TABLE `teacher_subject_map`
  ADD PRIMARY KEY (`MapID`),
  ADD KEY `TeacherID` (`TeacherID`),
  ADD KEY `SubjectID` (`SubjectID`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `admins`
--
ALTER TABLE `admins`
  MODIFY `AdminID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `attendance_records`
--
ALTER TABLE `attendance_records`
  MODIFY `AttendanceID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=19;

--
-- AUTO_INCREMENT for table `departments`
--
ALTER TABLE `departments`
  MODIFY `DepartmentID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `device_registration_tokens`
--
ALTER TABLE `device_registration_tokens`
  MODIFY `TokenID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `login_tbl`
--
ALTER TABLE `login_tbl`
  MODIFY `LoginID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=17;

--
-- AUTO_INCREMENT for table `materials`
--
ALTER TABLE `materials`
  MODIFY `MaterialID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `material_access_logs`
--
ALTER TABLE `material_access_logs`
  MODIFY `LogID` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `qr_attendance_sessions`
--
ALTER TABLE `qr_attendance_sessions`
  MODIFY `SessionID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=20;

--
-- AUTO_INCREMENT for table `semesters`
--
ALTER TABLE `semesters`
  MODIFY `SemesterID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `students`
--
ALTER TABLE `students`
  MODIFY `StudentID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT for table `student_devices`
--
ALTER TABLE `student_devices`
  MODIFY `DeviceID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `subjects`
--
ALTER TABLE `subjects`
  MODIFY `SubjectID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=58;

--
-- AUTO_INCREMENT for table `teachers`
--
ALTER TABLE `teachers`
  MODIFY `TeacherID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `teacher_department_map`
--
ALTER TABLE `teacher_department_map`
  MODIFY `MapID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `teacher_subject_map`
--
ALTER TABLE `teacher_subject_map`
  MODIFY `MapID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=36;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `admins`
--
ALTER TABLE `admins`
  ADD CONSTRAINT `admins_ibfk_1` FOREIGN KEY (`LoginID`) REFERENCES `login_tbl` (`LoginID`);

--
-- Constraints for table `attendance_records`
--
ALTER TABLE `attendance_records`
  ADD CONSTRAINT `attendance_records_ibfk_1` FOREIGN KEY (`StudentID`) REFERENCES `students` (`StudentID`),
  ADD CONSTRAINT `attendance_records_ibfk_2` FOREIGN KEY (`SubjectID`) REFERENCES `subjects` (`SubjectID`),
  ADD CONSTRAINT `attendance_records_ibfk_3` FOREIGN KEY (`TeacherID`) REFERENCES `teachers` (`TeacherID`);

--
-- Constraints for table `device_registration_tokens`
--
ALTER TABLE `device_registration_tokens`
  ADD CONSTRAINT `device_registration_tokens_ibfk_1` FOREIGN KEY (`StudentID`) REFERENCES `students` (`StudentID`) ON DELETE CASCADE;

--
-- Constraints for table `materials`
--
ALTER TABLE `materials`
  ADD CONSTRAINT `materials_ibfk_1` FOREIGN KEY (`TeacherID`) REFERENCES `teachers` (`TeacherID`) ON DELETE CASCADE,
  ADD CONSTRAINT `materials_ibfk_2` FOREIGN KEY (`SubjectID`) REFERENCES `subjects` (`SubjectID`) ON DELETE CASCADE;

--
-- Constraints for table `material_access_logs`
--
ALTER TABLE `material_access_logs`
  ADD CONSTRAINT `material_access_logs_ibfk_1` FOREIGN KEY (`MaterialID`) REFERENCES `materials` (`MaterialID`) ON DELETE CASCADE,
  ADD CONSTRAINT `material_access_logs_ibfk_2` FOREIGN KEY (`StudentID`) REFERENCES `students` (`StudentID`) ON DELETE SET NULL,
  ADD CONSTRAINT `material_access_logs_ibfk_3` FOREIGN KEY (`TeacherID`) REFERENCES `teachers` (`TeacherID`) ON DELETE SET NULL;

--
-- Constraints for table `qr_attendance_sessions`
--
ALTER TABLE `qr_attendance_sessions`
  ADD CONSTRAINT `qr_attendance_sessions_ibfk_1` FOREIGN KEY (`TeacherID`) REFERENCES `teachers` (`TeacherID`),
  ADD CONSTRAINT `qr_attendance_sessions_ibfk_2` FOREIGN KEY (`SubjectID`) REFERENCES `subjects` (`SubjectID`);

--
-- Constraints for table `students`
--
ALTER TABLE `students`
  ADD CONSTRAINT `students_ibfk_1` FOREIGN KEY (`DepartmentID`) REFERENCES `departments` (`DepartmentID`),
  ADD CONSTRAINT `students_ibfk_2` FOREIGN KEY (`SemesterID`) REFERENCES `semesters` (`SemesterID`),
  ADD CONSTRAINT `students_ibfk_3` FOREIGN KEY (`LoginID`) REFERENCES `login_tbl` (`LoginID`);

--
-- Constraints for table `student_devices`
--
ALTER TABLE `student_devices`
  ADD CONSTRAINT `student_devices_ibfk_1` FOREIGN KEY (`StudentID`) REFERENCES `students` (`StudentID`) ON DELETE CASCADE;

--
-- Constraints for table `subjects`
--
ALTER TABLE `subjects`
  ADD CONSTRAINT `subjects_ibfk_1` FOREIGN KEY (`DepartmentID`) REFERENCES `departments` (`DepartmentID`),
  ADD CONSTRAINT `subjects_ibfk_2` FOREIGN KEY (`SemesterID`) REFERENCES `semesters` (`SemesterID`);

--
-- Constraints for table `teachers`
--
ALTER TABLE `teachers`
  ADD CONSTRAINT `teachers_ibfk_2` FOREIGN KEY (`LoginID`) REFERENCES `login_tbl` (`LoginID`);

--
-- Constraints for table `teacher_department_map`
--
ALTER TABLE `teacher_department_map`
  ADD CONSTRAINT `teacher_department_map_ibfk_1` FOREIGN KEY (`TeacherID`) REFERENCES `teachers` (`TeacherID`),
  ADD CONSTRAINT `teacher_department_map_ibfk_2` FOREIGN KEY (`DepartmentID`) REFERENCES `departments` (`DepartmentID`);

--
-- Constraints for table `teacher_subject_map`
--
ALTER TABLE `teacher_subject_map`
  ADD CONSTRAINT `teacher_subject_map_ibfk_1` FOREIGN KEY (`TeacherID`) REFERENCES `teachers` (`TeacherID`),
  ADD CONSTRAINT `teacher_subject_map_ibfk_2` FOREIGN KEY (`SubjectID`) REFERENCES `subjects` (`SubjectID`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
