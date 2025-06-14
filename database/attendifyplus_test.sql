-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Jun 14, 2025 at 06:20 PM
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
-- Database: `attendifyplus_test`
--

-- --------------------------------------------------------

--
-- Table structure for table `admins`
--

CREATE TABLE `admins` (
  `AdminID` int(11) NOT NULL,
  `LoginID` int(11) DEFAULT NULL,
  `FullName` varchar(100) NOT NULL,
  `Contact` varchar(20) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `admins`
--

INSERT INTO `admins` (`AdminID`, `LoginID`, `FullName`, `Contact`) VALUES
(1, 1, 'Dr. Ramesh Neupane', '9800000001'),
(2, 2, 'Prof. Apsara Thapa', '9800000002');

-- --------------------------------------------------------

--
-- Table structure for table `attendance_records`
--

CREATE TABLE `attendance_records` (
  `AttendanceID` int(11) NOT NULL,
  `StudentID` int(11) DEFAULT NULL,
  `SubjectID` int(11) DEFAULT NULL,
  `TeacherID` int(11) DEFAULT NULL,
  `DateTime` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `batches`
--

CREATE TABLE `batches` (
  `BatchID` int(11) NOT NULL,
  `Year` int(11) NOT NULL,
  `Session` enum('Spring','Fall') NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `batches`
--

INSERT INTO `batches` (`BatchID`, `Year`, `Session`) VALUES
(1, 2022, 'Fall'),
(2, 2023, 'Spring'),
(3, 2024, 'Fall');

-- --------------------------------------------------------

--
-- Table structure for table `departments`
--

CREATE TABLE `departments` (
  `DepartmentID` int(11) NOT NULL,
  `DepartmentName` varchar(100) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `departments`
--

INSERT INTO `departments` (`DepartmentID`, `DepartmentName`) VALUES
(2, 'BBA'),
(1, 'BCA');

-- --------------------------------------------------------

--
-- Table structure for table `login_tbl`
--

CREATE TABLE `login_tbl` (
  `LoginID` int(11) NOT NULL,
  `Email` varchar(100) NOT NULL,
  `Password` varchar(255) NOT NULL,
  `Role` enum('admin','teacher','student') NOT NULL,
  `Status` enum('active','inactive') DEFAULT 'active'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `login_tbl`
--

INSERT INTO `login_tbl` (`LoginID`, `Email`, `Password`, `Role`, `Status`) VALUES
(1, 'admin1@college.edu', 'admin123', 'admin', 'active'),
(2, 'admin2@college.edu', 'admin456', 'admin', 'active'),
(3, 'anuja.sharma@college.edu', 'teach123', 'teacher', 'active'),
(4, 'bipin.gurung@college.edu', 'teach234', 'teacher', 'active'),
(5, 'maya.kc@college.edu', 'teach345', 'teacher', 'active'),
(6, 'ramesh.thapa@college.edu', 'teach456', 'teacher', 'active'),
(7, 'sushant.kc@college.edu', 'stud123', 'student', 'active'),
(8, 'rikita.gharti@college.edu', 'stud234', 'student', 'active'),
(9, 'namrata.bastola@college.edu', 'stud345', 'student', 'active');

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
-- Table structure for table `seminars`
--

CREATE TABLE `seminars` (
  `SeminarID` int(11) NOT NULL,
  `Title` varchar(150) DEFAULT NULL,
  `DepartmentID` int(11) DEFAULT NULL,
  `Date` date DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `seminars`
--

INSERT INTO `seminars` (`SeminarID`, `Title`, `DepartmentID`, `Date`) VALUES
(1, 'Cyber Security Awareness', 1, '2025-08-01'),
(2, 'Startup Pitching Workshop', 2, '2025-08-10');

-- --------------------------------------------------------

--
-- Table structure for table `seminar_attendance`
--

CREATE TABLE `seminar_attendance` (
  `ID` int(11) NOT NULL,
  `StudentID` int(11) DEFAULT NULL,
  `SeminarID` int(11) DEFAULT NULL,
  `EntryTime` datetime DEFAULT NULL,
  `ExitTime` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `seminar_attendance`
--

INSERT INTO `seminar_attendance` (`ID`, `StudentID`, `SeminarID`, `EntryTime`, `ExitTime`) VALUES
(1, 1, 1, '2025-08-01 09:00:00', '2025-08-01 11:00:00'),
(2, 3, 2, '2025-08-10 10:00:00', '2025-08-10 12:30:00');

-- --------------------------------------------------------

--
-- Table structure for table `students`
--

CREATE TABLE `students` (
  `StudentID` int(11) NOT NULL,
  `LoginID` int(11) DEFAULT NULL,
  `FullName` varchar(100) NOT NULL,
  `ExamRoll` varchar(20) DEFAULT NULL,
  `BatchID` int(11) DEFAULT NULL,
  `DepartmentID` int(11) DEFAULT NULL,
  `SemesterID` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `students`
--

INSERT INTO `students` (`StudentID`, `LoginID`, `FullName`, `ExamRoll`, `BatchID`, `DepartmentID`, `SemesterID`) VALUES
(1, 7, 'Sushant KC', '22530021', 1, 1, 3),
(2, 8, 'Rikita Gharti', '22530022', 1, 1, 3),
(3, 9, 'Namrata Bastola', '22530023', 2, 2, 2);

-- --------------------------------------------------------

--
-- Table structure for table `student_enrollments`
--

CREATE TABLE `student_enrollments` (
  `EnrollmentID` int(11) NOT NULL,
  `StudentID` int(11) DEFAULT NULL,
  `SubjectID` int(11) DEFAULT NULL,
  `SemesterID` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `student_enrollments`
--

INSERT INTO `student_enrollments` (`EnrollmentID`, `StudentID`, `SubjectID`, `SemesterID`) VALUES
(1, 1, 1, 3),
(2, 1, 2, 3),
(3, 2, 1, 3),
(4, 2, 2, 3),
(5, 3, 3, 2),
(6, 3, 4, 2);

-- --------------------------------------------------------

--
-- Table structure for table `subjects`
--

CREATE TABLE `subjects` (
  `SubjectID` int(11) NOT NULL,
  `SubjectName` varchar(100) NOT NULL,
  `SubjectCode` varchar(10) NOT NULL,
  `CreditHour` int(11) NOT NULL,
  `LectureHour` int(11) NOT NULL DEFAULT 48,
  `DepartmentID` int(11) DEFAULT NULL,
  `SemesterID` int(11) DEFAULT NULL,
  `IsElective` tinyint(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `subjects`
--

INSERT INTO `subjects` (`SubjectID`, `SubjectName`, `SubjectCode`, `CreditHour`, `LectureHour`, `DepartmentID`, `SemesterID`, `IsElective`) VALUES
(1, 'Database Management System', '', 4, 48, 1, 3, 0),
(2, 'Web Programming', '', 3, 48, 1, 3, 0),
(3, 'Accounting Basics', '', 3, 48, 2, 2, 0),
(4, 'Business Communication', '', 2, 48, 2, 2, 0),
(5, 'Software Engineering', '', 4, 48, 1, 5, 0),
(6, 'Organizational Behavior', '', 3, 48, 2, 5, 1),
(7, 'Php', 'CMP 231', 3, 48, 1, 1, 0),
(8, 'AI', 'CMP 112', 3, 48, 1, 7, 1);

-- --------------------------------------------------------

--
-- Table structure for table `teachers`
--

CREATE TABLE `teachers` (
  `TeacherID` int(11) NOT NULL,
  `LoginID` int(11) DEFAULT NULL,
  `FullName` varchar(100) NOT NULL,
  `Contact` varchar(20) DEFAULT NULL,
  `Type` enum('full-time','part-time') DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `teachers`
--

INSERT INTO `teachers` (`TeacherID`, `LoginID`, `FullName`, `Contact`, `Type`) VALUES
(1, 3, 'Ms. Anuja Sharma', '9800000003', 'full-time'),
(2, 4, 'Mr. Bipin Gurung', '9800000004', 'part-time'),
(3, 5, 'Mrs. Maya KC', '9800000005', 'full-time'),
(4, 6, 'Mr. Ramesh Thapa', '9800000006', 'part-time');

-- --------------------------------------------------------

--
-- Table structure for table `teacher_subject_map`
--

CREATE TABLE `teacher_subject_map` (
  `MapID` int(11) NOT NULL,
  `TeacherID` int(11) DEFAULT NULL,
  `SubjectID` int(11) DEFAULT NULL,
  `SemesterID` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `teacher_subject_map`
--

INSERT INTO `teacher_subject_map` (`MapID`, `TeacherID`, `SubjectID`, `SemesterID`) VALUES
(1, 1, 1, 3),
(2, 1, 2, 3),
(3, 2, 3, 2),
(4, 2, 4, 2),
(5, 3, 5, 5),
(6, 4, 6, 5);

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
-- Indexes for table `batches`
--
ALTER TABLE `batches`
  ADD PRIMARY KEY (`BatchID`);

--
-- Indexes for table `departments`
--
ALTER TABLE `departments`
  ADD PRIMARY KEY (`DepartmentID`),
  ADD UNIQUE KEY `DepartmentName` (`DepartmentName`);

--
-- Indexes for table `login_tbl`
--
ALTER TABLE `login_tbl`
  ADD PRIMARY KEY (`LoginID`),
  ADD UNIQUE KEY `Email` (`Email`);

--
-- Indexes for table `semesters`
--
ALTER TABLE `semesters`
  ADD PRIMARY KEY (`SemesterID`);

--
-- Indexes for table `seminars`
--
ALTER TABLE `seminars`
  ADD PRIMARY KEY (`SeminarID`),
  ADD KEY `DepartmentID` (`DepartmentID`);

--
-- Indexes for table `seminar_attendance`
--
ALTER TABLE `seminar_attendance`
  ADD PRIMARY KEY (`ID`),
  ADD KEY `StudentID` (`StudentID`),
  ADD KEY `SeminarID` (`SeminarID`);

--
-- Indexes for table `students`
--
ALTER TABLE `students`
  ADD PRIMARY KEY (`StudentID`),
  ADD UNIQUE KEY `LoginID` (`LoginID`),
  ADD KEY `BatchID` (`BatchID`),
  ADD KEY `DepartmentID` (`DepartmentID`),
  ADD KEY `SemesterID` (`SemesterID`);

--
-- Indexes for table `student_enrollments`
--
ALTER TABLE `student_enrollments`
  ADD PRIMARY KEY (`EnrollmentID`),
  ADD KEY `StudentID` (`StudentID`),
  ADD KEY `SubjectID` (`SubjectID`),
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
-- Indexes for table `teacher_subject_map`
--
ALTER TABLE `teacher_subject_map`
  ADD PRIMARY KEY (`MapID`),
  ADD KEY `TeacherID` (`TeacherID`),
  ADD KEY `SubjectID` (`SubjectID`),
  ADD KEY `SemesterID` (`SemesterID`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `admins`
--
ALTER TABLE `admins`
  MODIFY `AdminID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `attendance_records`
--
ALTER TABLE `attendance_records`
  MODIFY `AttendanceID` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `batches`
--
ALTER TABLE `batches`
  MODIFY `BatchID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `departments`
--
ALTER TABLE `departments`
  MODIFY `DepartmentID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `login_tbl`
--
ALTER TABLE `login_tbl`
  MODIFY `LoginID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT for table `semesters`
--
ALTER TABLE `semesters`
  MODIFY `SemesterID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `seminars`
--
ALTER TABLE `seminars`
  MODIFY `SeminarID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `seminar_attendance`
--
ALTER TABLE `seminar_attendance`
  MODIFY `ID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `students`
--
ALTER TABLE `students`
  MODIFY `StudentID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `student_enrollments`
--
ALTER TABLE `student_enrollments`
  MODIFY `EnrollmentID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `subjects`
--
ALTER TABLE `subjects`
  MODIFY `SubjectID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `teachers`
--
ALTER TABLE `teachers`
  MODIFY `TeacherID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `teacher_subject_map`
--
ALTER TABLE `teacher_subject_map`
  MODIFY `MapID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `admins`
--
ALTER TABLE `admins`
  ADD CONSTRAINT `admins_ibfk_1` FOREIGN KEY (`LoginID`) REFERENCES `login_tbl` (`LoginID`) ON DELETE CASCADE;

--
-- Constraints for table `attendance_records`
--
ALTER TABLE `attendance_records`
  ADD CONSTRAINT `attendance_records_ibfk_1` FOREIGN KEY (`StudentID`) REFERENCES `students` (`StudentID`),
  ADD CONSTRAINT `attendance_records_ibfk_2` FOREIGN KEY (`SubjectID`) REFERENCES `subjects` (`SubjectID`),
  ADD CONSTRAINT `attendance_records_ibfk_3` FOREIGN KEY (`TeacherID`) REFERENCES `teachers` (`TeacherID`);

--
-- Constraints for table `seminars`
--
ALTER TABLE `seminars`
  ADD CONSTRAINT `seminars_ibfk_1` FOREIGN KEY (`DepartmentID`) REFERENCES `departments` (`DepartmentID`);

--
-- Constraints for table `seminar_attendance`
--
ALTER TABLE `seminar_attendance`
  ADD CONSTRAINT `seminar_attendance_ibfk_1` FOREIGN KEY (`StudentID`) REFERENCES `students` (`StudentID`),
  ADD CONSTRAINT `seminar_attendance_ibfk_2` FOREIGN KEY (`SeminarID`) REFERENCES `seminars` (`SeminarID`);

--
-- Constraints for table `students`
--
ALTER TABLE `students`
  ADD CONSTRAINT `students_ibfk_1` FOREIGN KEY (`LoginID`) REFERENCES `login_tbl` (`LoginID`) ON DELETE CASCADE,
  ADD CONSTRAINT `students_ibfk_2` FOREIGN KEY (`BatchID`) REFERENCES `batches` (`BatchID`),
  ADD CONSTRAINT `students_ibfk_3` FOREIGN KEY (`DepartmentID`) REFERENCES `departments` (`DepartmentID`),
  ADD CONSTRAINT `students_ibfk_4` FOREIGN KEY (`SemesterID`) REFERENCES `semesters` (`SemesterID`);

--
-- Constraints for table `student_enrollments`
--
ALTER TABLE `student_enrollments`
  ADD CONSTRAINT `student_enrollments_ibfk_1` FOREIGN KEY (`StudentID`) REFERENCES `students` (`StudentID`),
  ADD CONSTRAINT `student_enrollments_ibfk_2` FOREIGN KEY (`SubjectID`) REFERENCES `subjects` (`SubjectID`),
  ADD CONSTRAINT `student_enrollments_ibfk_3` FOREIGN KEY (`SemesterID`) REFERENCES `semesters` (`SemesterID`);

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
  ADD CONSTRAINT `teachers_ibfk_1` FOREIGN KEY (`LoginID`) REFERENCES `login_tbl` (`LoginID`) ON DELETE CASCADE;

--
-- Constraints for table `teacher_subject_map`
--
ALTER TABLE `teacher_subject_map`
  ADD CONSTRAINT `teacher_subject_map_ibfk_1` FOREIGN KEY (`TeacherID`) REFERENCES `teachers` (`TeacherID`),
  ADD CONSTRAINT `teacher_subject_map_ibfk_2` FOREIGN KEY (`SubjectID`) REFERENCES `subjects` (`SubjectID`),
  ADD CONSTRAINT `teacher_subject_map_ibfk_3` FOREIGN KEY (`SemesterID`) REFERENCES `semesters` (`SemesterID`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
