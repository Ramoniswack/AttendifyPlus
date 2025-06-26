-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Jun 21, 2025 at 06:47 PM
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
-- Database: `attendifyplus`
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
(1, 'Admin User', '9800000001', 'Admin Address', NULL, 1),
(2, 'Admin user 2', '981111111', 'Pokhara-21', '../uploads/admins/admin_684ee7d77605e7.88815539.png', 34),
(3, 'Admin user 3', '981111121', 'Pokhara-22', 'uploads/admins/admin_user_3_1750001806.png', 35),
(4, 'Admin user 4', '981111131', 'Pokhara-23', 'uploads/admins/admin_user_4_1750002011.webp', 36),
(5, 'Admin user 5', '981111125', 'Pokhara-25', 'uploads/admins/admin_user_5_1750002286.png', 37),
(6, 'Admin user 5', '981111126', 'Pokhara-26', '../uploads/admins/admin_684eeb59344ab7.48234017.jpg', 38);

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
(1, 1, 1, 17, '2025-06-16 00:00:00', 'present', 'manual'),
(2, 1, 2, 17, '2025-06-16 00:00:00', 'present', 'manual'),
(3, 1, 1, 17, '2025-06-16 00:00:00', 'present', 'manual'),
(4, 3, 8, 17, '2025-06-16 00:00:00', 'present', 'manual'),
(5, 1, 3, 17, '2025-06-16 00:00:00', 'present', 'manual'),
(6, 1, 1, 17, '2025-06-16 00:00:00', 'present', 'manual'),
(7, 3, 7, 17, '2025-06-16 00:00:00', 'present', 'manual'),
(8, 1, 1, 17, '2025-06-17 00:00:00', 'present', 'manual'),
(9, 1, 2, 17, '2025-06-17 00:00:00', 'present', 'manual'),
(10, 7, 3, 17, '2025-06-19 22:55:12', 'present', 'manual'),
(11, 1, 3, 17, '2025-06-19 22:55:12', 'present', 'manual'),
(12, 7, 1, 17, '2025-06-20 00:02:23', 'present', 'manual'),
(13, 1, 1, 17, '2025-06-20 00:02:23', 'present', 'manual'),
(14, 3, 8, 17, '2025-06-20 08:09:50', 'present', 'manual'),
(15, 3, 8, 17, '2025-06-19 08:10:58', 'present', 'manual'),
(16, 7, 3, 17, '0000-00-00 00:00:00', 'present', 'manual'),
(17, 1, 3, 17, '0000-00-00 00:00:00', 'absent', 'manual'),
(18, 7, 3, 17, '0000-00-00 00:00:00', 'present', 'manual'),
(19, 1, 3, 17, '0000-00-00 00:00:00', 'present', 'manual'),
(20, 7, 3, 17, '0000-00-00 00:00:00', 'absent', 'manual'),
(21, 1, 3, 17, '0000-00-00 00:00:00', 'present', 'manual'),
(22, 7, 3, 17, '2025-06-21 18:32:12', 'present', 'manual'),
(23, 1, 3, 17, '2025-06-21 18:32:12', 'present', 'manual'),
(24, 7, 1, 17, '0000-00-00 00:00:00', 'late', 'manual'),
(25, 1, 1, 17, '0000-00-00 00:00:00', 'present', 'manual'),
(26, 7, 1, 17, '2025-06-21 18:45:52', 'present', 'manual'),
(27, 1, 1, 17, '2025-06-21 18:45:52', 'present', 'manual');

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
(3, 'BCA', 'BCA'),
(4, 'BBA', 'BBA');

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
(1, 'admin@college.com', 'admin123', 'admin', 'active', '2025-06-15 20:47:05'),
(2, 'teacher1@bca.com', 'teach123', 'teacher', 'active', '2025-06-15 20:47:05'),
(3, 'teacher2@bba.com', 'teach456', 'teacher', 'active', '2025-06-15 20:47:05'),
(4, 'teacher3@bca.com', 'teach789', 'teacher', 'active', '2025-06-15 20:47:05'),
(5, 'teacher4@bba.com', 'teach321', 'teacher', 'active', '2025-06-15 20:47:05'),
(6, 'student1@bca.com', 'stud123', 'student', 'active', '2025-06-15 20:47:05'),
(7, 'student2@bca.com', 'stud456', 'student', 'active', '2025-06-15 20:47:05'),
(8, 'student3@bca.com', 'stud789', 'student', 'active', '2025-06-15 20:47:05'),
(9, 'student4@bba.com', 'stud321', 'student', 'active', '2025-06-15 20:47:05'),
(10, 'student5@bba.com', 'stud654', 'student', 'active', '2025-06-15 20:47:05'),
(11, 'student6@bba.com', 'stud987', 'student', 'inactive', '2025-06-15 20:47:05'),
(34, 'ndmin@college.com', '$2y$10$fD9bPr3kRpPmAb/PMZlSP.Wn5Q5mDb0C.T4VE4l76x7fZ7WLFp7ri', 'admin', 'active', '2025-06-15 21:18:43'),
(35, 'ndmin1@college.com', '$2y$10$zNw.XXLX5VHvVZAO7S6Ww./.y0j0DQ6rFGYbFGv/AFr6GZEThG2HK', 'admin', 'active', '2025-06-15 21:21:46'),
(36, 'admin4@college.com', '$2y$10$j/nC9ZsnVDRASxDl2Q4/UuPVCFyRD1NvvVCTMaPtYN/jakBJBtQFm', 'admin', 'active', '2025-06-15 21:25:11'),
(37, 'admin5@college.com', '$2y$10$XqMutW4rU/WazZSIQqRTx.6qoHeiuc39WS1MeA62gO1bvWaR7cZOy', 'admin', 'active', '2025-06-15 21:29:46'),
(38, 'admin6@college.com', '$2y$10$049RoUqKVe/ODphlZDf3IeZJ6RYy60Dwfh7b7Tls10o.D/qyF1wFm', 'admin', 'active', '2025-06-15 21:33:41'),
(39, 'tb@mail.com', '$2y$10$yDEbWDyQvz/JcD2OQgkbOe9IMDv3Pzh2.xey/W1B4hPijEecq3i8q', 'teacher', 'active', '2025-06-16 00:56:09'),
(40, 'tc@mail.com', '$2y$10$mcDWkUDx0Ro3Fum4274fxeNTf6c6lZnNU7rr5kT3v8AgOvvs02ekm', 'teacher', 'active', '2025-06-16 07:20:57'),
(47, 't@mail.com', '$2y$10$d3.aIfXhD4Xn7M03aebzqOV/Jgu9ynam7/tzPKyp5sbtIxufShXSq', 'teacher', 'active', '2025-06-16 18:03:33'),
(48, 't2@mail.com', '$2y$10$cl3VJnUrjA9LXWdVnNmo1OnCVHvqtRmMw9KPrxnR8euH2m/5.eBvC', 'teacher', 'active', '2025-06-16 18:11:17'),
(52, 'jpt@mail.com', '$2y$10$om4FbjONzJHwtIt5.65Auu1Qg5MbGzHk0k4IM1WiLKS4jbNkBMeQa', 'student', 'active', '2025-06-17 00:58:43');

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
(1, 17, 3, '2025-06-21', 'f67e09fd0e9064d2c11de5d899683abec925ddae9756f6b9d0ef6ec033f5de06', '2025-06-21 13:35:27', 0, '2025-06-21 17:19:27'),
(2, 17, 3, '2025-06-21', '64bf0b6c92bd81413f56293bd05d52169f52cbc33f89c0486625c27077f1020e', '2025-06-21 13:37:44', 0, '2025-06-21 17:21:44'),
(3, 17, 3, '2025-06-21', '4f33385ff7573b912d98eb52fc74a14b3e095ee866a3b687df23796c38f2cdd1', '2025-06-21 14:12:01', 0, '2025-06-21 17:56:01'),
(4, 17, 7, '2025-06-21', '78e2571d477e2cbb73fad885ded918569e1667d50d18eb1d706d93664573be46', '2025-06-21 14:15:00', 1, '2025-06-21 17:59:00'),
(5, 17, 3, '2025-06-21', '28a6332f7e1e0176389a8e43dcd10cec394df9ec653ad575e4601996b1554f81', '2025-06-21 14:41:59', 0, '2025-06-21 18:25:59'),
(6, 17, 3, '2025-06-21', '4469d7bde4bbc3545eca52446dd50a4a3d0faf877ab83aea1b769aa43cea63be', '2025-06-21 17:54:13', 0, '2025-06-21 21:38:13'),
(7, 17, 3, '2025-06-21', '740ac30470ce533324b8a2398e047f46bf38379252d4a69184d73e9b99934b01', '2025-06-21 18:10:16', 1, '2025-06-21 21:54:16');

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
  `LoginID` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `students`
--

INSERT INTO `students` (`StudentID`, `FullName`, `Contact`, `Address`, `PhotoURL`, `DepartmentID`, `SemesterID`, `JoinYear`, `ProgramCode`, `LoginID`) VALUES
(1, 'Ram BC', '9801111111', 'Butwal', NULL, 3, 1, '2025', 'BCA-BATCH-2025', 6),
(2, 'Sita KC', '9802222222', 'Pokhara', NULL, 3, 2, '2025', 'BCA-BATCH-2025', 7),
(3, 'Hari Gurung', '9803333333', 'Gorkha', NULL, 3, 3, '2025', 'BCA-BATCH-2025', 8),
(4, 'Priya Sharma', '9804444444', 'Kathmandu', NULL, 4, 1, '2025', 'BBA-BATCH-2025', 9),
(5, 'Ravi Lamichhane', '9805555555', 'Pokhara', NULL, 4, 2, '2025', 'BBA-BATCH-2025', 10),
(6, 'Nisha Rai', '9806666666', 'Itahari', NULL, 4, 3, '2025', 'BBA-BATCH-2025', 11),
(7, 'Jpt Boy', '9819291928', 'Pokhara-21', '../uploads/students/student_68506ce7a08731.82014403.jpg', 3, 1, '2025', 'BCA-2025', 52);

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
(1, 'BCA101', 'Fundamentals of IT', 3, 48, 0, 3, 1),
(2, 'BCA102', 'Mathematics I', 3, 48, 0, 3, 1),
(3, 'BCA103', 'Digital Logic', 3, 48, 0, 3, 1),
(4, 'BCA201', 'C Programming', 3, 48, 0, 3, 2),
(5, 'BCA202', 'Discrete Structure', 3, 48, 0, 3, 2),
(6, 'BCA203', 'Microprocessor', 3, 48, 0, 3, 2),
(7, 'BCA301', 'Data Structures', 3, 48, 0, 3, 3),
(8, 'BCA302', 'OOP in Java', 3, 48, 0, 3, 3),
(9, 'BCA303', 'Web Technology', 3, 48, 0, 3, 3),
(10, 'BBA101', 'Principles of Management', 3, 48, 0, 4, 1),
(11, 'BBA102', 'Business Mathematics', 3, 48, 0, 4, 1),
(12, 'BBA103', 'English Composition', 3, 48, 0, 4, 1),
(13, 'BBA201', 'Financial Accounting', 3, 48, 0, 4, 2),
(14, 'BBA202', 'Business Communication', 3, 48, 0, 4, 2),
(15, 'BBA203', 'Business Statistics', 3, 48, 0, 4, 2),
(16, 'BBA301', 'Marketing Management', 3, 48, 0, 4, 3),
(17, 'BBA302', 'HRM', 3, 48, 0, 4, 3),
(18, 'BBA303', 'Organizational Behavior', 3, 48, 0, 4, 3);

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
(17, 'BCA Teacher A', '9800000002', 'Pokhara', NULL, 2),
(18, 'BBA Teacher A', '9800000003', 'Kathmandu', NULL, 3),
(19, 'BCA Teacher B', '9800000004', 'Butwal', NULL, 4),
(20, 'BBA Teacher B', '9800000005', 'Chitwan', NULL, 5),
(21, 'BCA Teacher C', '9818281828', '', '../uploads/teachers/teacher_68500b9964cb04.42914858.jpeg', 47),
(22, 'BBA Teacher C', '9828182811', '', '../uploads/teachers/teacher_68500d69459c21.31516549.jpg', 48);

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
(7, 17, 3),
(8, 17, 4),
(9, 18, 3),
(14, 17, 3),
(15, 17, 4);

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
(19, 17, 1),
(20, 17, 2),
(21, 17, 3),
(22, 19, 4),
(23, 19, 5),
(24, 19, 6),
(25, 17, 7),
(26, 17, 8),
(27, 17, 9),
(28, 18, 10),
(29, 18, 11),
(30, 18, 12),
(31, 18, 13),
(32, 18, 14),
(33, 18, 15),
(34, 20, 16),
(35, 20, 17),
(36, 20, 18);

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
-- Indexes for table `login_tbl`
--
ALTER TABLE `login_tbl`
  ADD PRIMARY KEY (`LoginID`),
  ADD UNIQUE KEY `Email` (`Email`);

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
  MODIFY `AdminID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `attendance_records`
--
ALTER TABLE `attendance_records`
  MODIFY `AttendanceID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=28;

--
-- AUTO_INCREMENT for table `departments`
--
ALTER TABLE `departments`
  MODIFY `DepartmentID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `login_tbl`
--
ALTER TABLE `login_tbl`
  MODIFY `LoginID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=53;

--
-- AUTO_INCREMENT for table `qr_attendance_sessions`
--
ALTER TABLE `qr_attendance_sessions`
  MODIFY `SessionID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `semesters`
--
ALTER TABLE `semesters`
  MODIFY `SemesterID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `students`
--
ALTER TABLE `students`
  MODIFY `StudentID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `subjects`
--
ALTER TABLE `subjects`
  MODIFY `SubjectID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=19;

--
-- AUTO_INCREMENT for table `teachers`
--
ALTER TABLE `teachers`
  MODIFY `TeacherID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=23;

--
-- AUTO_INCREMENT for table `teacher_department_map`
--
ALTER TABLE `teacher_department_map`
  MODIFY `MapID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=16;

--
-- AUTO_INCREMENT for table `teacher_subject_map`
--
ALTER TABLE `teacher_subject_map`
  MODIFY `MapID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=37;

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
