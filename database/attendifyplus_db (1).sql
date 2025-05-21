-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: May 21, 2025 at 08:03 AM
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
-- Database: `attendifyplus_db`
--

-- --------------------------------------------------------

--
-- Table structure for table `admin_tbl`
--

CREATE TABLE `admin_tbl` (
  `LoginID` int(11) NOT NULL,
  `FullName` varchar(100) DEFAULT NULL,
  `Designation` varchar(100) DEFAULT NULL,
  `Phone` varchar(15) DEFAULT NULL,
  `Status` enum('active','inactive') DEFAULT 'active'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `admin_tbl`
--

INSERT INTO `admin_tbl` (`LoginID`, `FullName`, `Designation`, `Phone`, `Status`) VALUES
(17, 'AdminFullname', 'Principal', '9800000000', 'active');

-- --------------------------------------------------------

--
-- Table structure for table `attendance_tbl`
--

CREATE TABLE `attendance_tbl` (
  `AttendanceID` int(11) NOT NULL,
  `StudentID` int(11) NOT NULL,
  `TeacherID` int(11) NOT NULL,
  `SubjectID` int(11) NOT NULL,
  `Date` date NOT NULL,
  `Status` enum('present','absent','late') NOT NULL,
  `Timestamp` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `attendance_tbl`
--

INSERT INTO `attendance_tbl` (`AttendanceID`, `StudentID`, `TeacherID`, `SubjectID`, `Date`, `Status`, `Timestamp`) VALUES
(1, 33, 32, 31, '2025-05-17', 'late', '2025-05-17 17:15:25'),
(2, 33, 32, 31, '2025-05-17', 'present', '2025-05-17 19:35:11'),
(3, 33, 32, 31, '2025-05-19', 'present', '2025-05-19 07:25:49');

-- --------------------------------------------------------

--
-- Table structure for table `batches_tbl`
--

CREATE TABLE `batches_tbl` (
  `BatchID` int(11) NOT NULL,
  `BatchName` varchar(50) NOT NULL,
  `DepartmentID` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `batches_tbl`
--

INSERT INTO `batches_tbl` (`BatchID`, `BatchName`, `DepartmentID`) VALUES
(24, '2022F', 5),
(25, '2022F', 6),
(26, '2022S', 5),
(27, '2022S', 6),
(28, '2023F', 5),
(29, '2023F', 6),
(30, '2023S', 5),
(31, '2023S', 6);

-- --------------------------------------------------------

--
-- Table structure for table `departments_tbl`
--

CREATE TABLE `departments_tbl` (
  `DepartmentID` int(11) NOT NULL,
  `DepartmentName` varchar(100) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `departments_tbl`
--

INSERT INTO `departments_tbl` (`DepartmentID`, `DepartmentName`) VALUES
(6, 'BBA'),
(5, 'BCA');

-- --------------------------------------------------------

--
-- Table structure for table `login_tbl`
--

CREATE TABLE `login_tbl` (
  `UserID` int(11) NOT NULL,
  `Username` varchar(50) NOT NULL,
  `Password` varchar(255) NOT NULL,
  `Email` varchar(100) NOT NULL,
  `Role` enum('admin','teacher','student') NOT NULL,
  `Status` enum('active','inactive') DEFAULT 'active',
  `CreatedAt` datetime DEFAULT current_timestamp(),
  `LastLogin` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `login_tbl`
--

INSERT INTO `login_tbl` (`UserID`, `Username`, `Password`, `Email`, `Role`, `Status`, `CreatedAt`, `LastLogin`) VALUES
(17, 'admin', 'admin123', 'admin@mail.com', 'admin', 'active', '2025-05-17 20:40:26', NULL),
(18, 't1', 'pass123', 't1@mail.com', 'teacher', 'active', '2025-05-17 20:40:26', NULL),
(19, 't2', 'pass123', 't2@mail.com', 'teacher', 'active', '2025-05-17 20:40:26', NULL),
(20, 's1', 'pass123', 's1@mail.com', 'student', 'active', '2025-05-17 20:40:26', NULL),
(21, 's2', 'pass123', 's2@mail.com', 'student', 'active', '2025-05-17 20:40:26', NULL),
(22, 's3', 'pass123', 's3@mail.com', 'student', 'active', '2025-05-17 20:40:26', NULL),
(23, 's4', 'pass123', 's4@mail.com', 'student', 'active', '2025-05-17 20:40:26', NULL),
(24, 's5', 'pass123', 's5@mail.com', 'student', 'active', '2025-05-17 20:40:26', NULL),
(30, 't3', 'pass123', 't3@mail.com', 'teacher', 'active', '2025-05-17 22:24:59', NULL),
(31, 's6', 'pass123', 'rasmina@mail.com', 'student', 'active', '2025-05-17 22:32:09', NULL),
(32, 'a3', 'pass123', 'ashwin@mail.com', 'teacher', 'active', '2025-05-17 22:49:15', NULL),
(33, 's8', 'pass123', 's8@mail.com', 'student', 'active', '2025-05-17 22:51:36', NULL),
(34, 'KrishnaHari', 'pass123', 'k@mail.com', 'teacher', 'active', '2025-05-19 13:02:35', NULL),
(35, 'Dipen', 'pass123', 'd@mail.com', 'student', 'active', '2025-05-19 13:06:52', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `students_tbl`
--

CREATE TABLE `students_tbl` (
  `LoginID` int(11) NOT NULL,
  `FullName` varchar(100) DEFAULT NULL,
  `DepartmentID` int(11) NOT NULL,
  `BatchID` int(11) NOT NULL,
  `RollNo` varchar(50) NOT NULL,
  `JoinYear` year(4) NOT NULL,
  `Status` enum('active','inactive') DEFAULT 'active'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `students_tbl`
--

INSERT INTO `students_tbl` (`LoginID`, `FullName`, `DepartmentID`, `BatchID`, `RollNo`, `JoinYear`, `Status`) VALUES
(20, 'Ramon', 5, 24, '2022F_BCA_001', '2022', 'active'),
(21, 'Namrata', 5, 26, '2022S_BCA_002', '2022', 'active'),
(22, 'Binod', 5, 28, '2023F_BCA_003', '2023', 'active'),
(23, 'Subash', 6, 25, '2022F_BBA_001', '2022', 'active'),
(24, 'Rikita', 6, 27, '2022S_BBA_002', '2022', 'active'),
(31, 'Rasmina', 5, 24, '22530021', '2022', 'active'),
(33, 'Susant', 6, 31, '22530021bsa', '2022', 'active'),
(35, 'Dipen Pun', 5, 25, '22530007', '2022', 'active');

-- --------------------------------------------------------

--
-- Table structure for table `subjects_tbl`
--

CREATE TABLE `subjects_tbl` (
  `SubjectID` int(11) NOT NULL,
  `SubjectName` varchar(100) NOT NULL,
  `DepartmentID` int(11) NOT NULL,
  `BatchID` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `subjects_tbl`
--

INSERT INTO `subjects_tbl` (`SubjectID`, `SubjectName`, `DepartmentID`, `BatchID`) VALUES
(24, 'Data Structures', 5, 24),
(25, 'Web Development', 5, 26),
(26, 'DBMS', 5, 28),
(27, 'Operating Systems', 5, 30),
(28, 'Accounting Basics', 6, 25),
(29, 'Marketing', 6, 27),
(30, 'Finance', 6, 29),
(31, 'HR Management', 6, 31);

-- --------------------------------------------------------

--
-- Table structure for table `teachers_tbl`
--

CREATE TABLE `teachers_tbl` (
  `LoginID` int(11) NOT NULL,
  `FullName` varchar(100) DEFAULT NULL,
  `Phone` varchar(15) DEFAULT NULL,
  `DepartmentID` int(11) NOT NULL,
  `Status` enum('active','inactive') DEFAULT 'active'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `teachers_tbl`
--

INSERT INTO `teachers_tbl` (`LoginID`, `FullName`, `Phone`, `DepartmentID`, `Status`) VALUES
(18, 'Ramesh', '9800000001', 5, 'active'),
(19, 'Rishi', '9800000002', 6, 'active'),
(30, 'Ashish', '9812390192', 5, 'active'),
(32, 'Ashwin', '8390280', 6, 'active'),
(34, 'Krishna Hari Paudel', '7889798732897', 5, 'active');

-- --------------------------------------------------------

--
-- Table structure for table `teacher_subject_tbl`
--

CREATE TABLE `teacher_subject_tbl` (
  `ID` int(11) NOT NULL,
  `TeacherID` int(11) NOT NULL,
  `SubjectID` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `teacher_subject_tbl`
--

INSERT INTO `teacher_subject_tbl` (`ID`, `TeacherID`, `SubjectID`) VALUES
(8, 18, 24),
(9, 18, 25),
(10, 18, 26),
(11, 19, 28),
(12, 19, 29),
(13, 19, 31),
(15, 32, 31),
(16, 34, 28);

--
-- Indexes for dumped tables
--

--
-- Indexes for table `admin_tbl`
--
ALTER TABLE `admin_tbl`
  ADD PRIMARY KEY (`LoginID`);

--
-- Indexes for table `attendance_tbl`
--
ALTER TABLE `attendance_tbl`
  ADD PRIMARY KEY (`AttendanceID`),
  ADD KEY `StudentID` (`StudentID`),
  ADD KEY `TeacherID` (`TeacherID`),
  ADD KEY `SubjectID` (`SubjectID`);

--
-- Indexes for table `batches_tbl`
--
ALTER TABLE `batches_tbl`
  ADD PRIMARY KEY (`BatchID`),
  ADD KEY `DepartmentID` (`DepartmentID`);

--
-- Indexes for table `departments_tbl`
--
ALTER TABLE `departments_tbl`
  ADD PRIMARY KEY (`DepartmentID`),
  ADD UNIQUE KEY `DepartmentName` (`DepartmentName`);

--
-- Indexes for table `login_tbl`
--
ALTER TABLE `login_tbl`
  ADD PRIMARY KEY (`UserID`),
  ADD UNIQUE KEY `Username` (`Username`),
  ADD UNIQUE KEY `Email` (`Email`);

--
-- Indexes for table `students_tbl`
--
ALTER TABLE `students_tbl`
  ADD PRIMARY KEY (`LoginID`),
  ADD UNIQUE KEY `RollNo` (`RollNo`),
  ADD KEY `DepartmentID` (`DepartmentID`),
  ADD KEY `BatchID` (`BatchID`);

--
-- Indexes for table `subjects_tbl`
--
ALTER TABLE `subjects_tbl`
  ADD PRIMARY KEY (`SubjectID`),
  ADD KEY `DepartmentID` (`DepartmentID`),
  ADD KEY `BatchID` (`BatchID`);

--
-- Indexes for table `teachers_tbl`
--
ALTER TABLE `teachers_tbl`
  ADD PRIMARY KEY (`LoginID`),
  ADD KEY `DepartmentID` (`DepartmentID`);

--
-- Indexes for table `teacher_subject_tbl`
--
ALTER TABLE `teacher_subject_tbl`
  ADD PRIMARY KEY (`ID`),
  ADD KEY `TeacherID` (`TeacherID`),
  ADD KEY `SubjectID` (`SubjectID`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `attendance_tbl`
--
ALTER TABLE `attendance_tbl`
  MODIFY `AttendanceID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `batches_tbl`
--
ALTER TABLE `batches_tbl`
  MODIFY `BatchID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=32;

--
-- AUTO_INCREMENT for table `departments_tbl`
--
ALTER TABLE `departments_tbl`
  MODIFY `DepartmentID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `login_tbl`
--
ALTER TABLE `login_tbl`
  MODIFY `UserID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=36;

--
-- AUTO_INCREMENT for table `subjects_tbl`
--
ALTER TABLE `subjects_tbl`
  MODIFY `SubjectID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=32;

--
-- AUTO_INCREMENT for table `teacher_subject_tbl`
--
ALTER TABLE `teacher_subject_tbl`
  MODIFY `ID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=17;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `admin_tbl`
--
ALTER TABLE `admin_tbl`
  ADD CONSTRAINT `admin_tbl_ibfk_1` FOREIGN KEY (`LoginID`) REFERENCES `login_tbl` (`UserID`) ON DELETE CASCADE;

--
-- Constraints for table `attendance_tbl`
--
ALTER TABLE `attendance_tbl`
  ADD CONSTRAINT `attendance_tbl_ibfk_1` FOREIGN KEY (`StudentID`) REFERENCES `students_tbl` (`LoginID`) ON DELETE CASCADE,
  ADD CONSTRAINT `attendance_tbl_ibfk_2` FOREIGN KEY (`TeacherID`) REFERENCES `teachers_tbl` (`LoginID`) ON DELETE CASCADE,
  ADD CONSTRAINT `attendance_tbl_ibfk_3` FOREIGN KEY (`SubjectID`) REFERENCES `subjects_tbl` (`SubjectID`) ON DELETE CASCADE;

--
-- Constraints for table `batches_tbl`
--
ALTER TABLE `batches_tbl`
  ADD CONSTRAINT `batches_tbl_ibfk_1` FOREIGN KEY (`DepartmentID`) REFERENCES `departments_tbl` (`DepartmentID`) ON DELETE CASCADE;

--
-- Constraints for table `students_tbl`
--
ALTER TABLE `students_tbl`
  ADD CONSTRAINT `students_tbl_ibfk_1` FOREIGN KEY (`LoginID`) REFERENCES `login_tbl` (`UserID`) ON DELETE CASCADE,
  ADD CONSTRAINT `students_tbl_ibfk_2` FOREIGN KEY (`DepartmentID`) REFERENCES `departments_tbl` (`DepartmentID`) ON DELETE CASCADE,
  ADD CONSTRAINT `students_tbl_ibfk_3` FOREIGN KEY (`BatchID`) REFERENCES `batches_tbl` (`BatchID`) ON DELETE CASCADE;

--
-- Constraints for table `subjects_tbl`
--
ALTER TABLE `subjects_tbl`
  ADD CONSTRAINT `subjects_tbl_ibfk_1` FOREIGN KEY (`DepartmentID`) REFERENCES `departments_tbl` (`DepartmentID`) ON DELETE CASCADE,
  ADD CONSTRAINT `subjects_tbl_ibfk_2` FOREIGN KEY (`BatchID`) REFERENCES `batches_tbl` (`BatchID`) ON DELETE CASCADE;

--
-- Constraints for table `teachers_tbl`
--
ALTER TABLE `teachers_tbl`
  ADD CONSTRAINT `teachers_tbl_ibfk_1` FOREIGN KEY (`LoginID`) REFERENCES `login_tbl` (`UserID`) ON DELETE CASCADE,
  ADD CONSTRAINT `teachers_tbl_ibfk_2` FOREIGN KEY (`DepartmentID`) REFERENCES `departments_tbl` (`DepartmentID`) ON DELETE CASCADE;

--
-- Constraints for table `teacher_subject_tbl`
--
ALTER TABLE `teacher_subject_tbl`
  ADD CONSTRAINT `teacher_subject_tbl_ibfk_1` FOREIGN KEY (`TeacherID`) REFERENCES `teachers_tbl` (`LoginID`) ON DELETE CASCADE,
  ADD CONSTRAINT `teacher_subject_tbl_ibfk_2` FOREIGN KEY (`SubjectID`) REFERENCES `subjects_tbl` (`SubjectID`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
