
-- Attendify+ Full Schema - Generated on 2025-06-15

-- Drop existing tables if they exist
DROP TABLE IF EXISTS attendance_records, teacher_subject_map, subjects, semesters, students, teachers, admins, departments, login_tbl;

-- 1. Login Table
CREATE TABLE login_tbl (
  LoginID INT AUTO_INCREMENT PRIMARY KEY,
  Email VARCHAR(100) UNIQUE NOT NULL,
  Password VARCHAR(255) NOT NULL,
  Role ENUM('admin', 'teacher', 'student') NOT NULL,
  Status ENUM('active', 'inactive') DEFAULT 'active',
  CreatedDate DATETIME DEFAULT CURRENT_TIMESTAMP
);

-- 2. Departments
CREATE TABLE departments (
  DepartmentID INT AUTO_INCREMENT PRIMARY KEY,
  DepartmentName VARCHAR(100) NOT NULL UNIQUE,
  DepartmentCode VARCHAR(10) NOT NULL UNIQUE
);

-- 3. Semesters
CREATE TABLE semesters (
  SemesterID INT AUTO_INCREMENT PRIMARY KEY,
  SemesterNumber INT NOT NULL
);

-- 4. Admins
CREATE TABLE admins (
  AdminID INT AUTO_INCREMENT PRIMARY KEY,
  FullName VARCHAR(100) NOT NULL,
  Contact VARCHAR(20),
  Address VARCHAR(200),
  PhotoURL VARCHAR(255),
  LoginID INT NOT NULL UNIQUE,
  FOREIGN KEY (LoginID) REFERENCES login_tbl(LoginID)
);

-- 5. Teachers
CREATE TABLE teachers (
  TeacherID INT AUTO_INCREMENT PRIMARY KEY,
  FullName VARCHAR(100) NOT NULL,
  Contact VARCHAR(20),
  Address VARCHAR(200),
  PhotoURL VARCHAR(255),
  LoginID INT NOT NULL UNIQUE,
  FOREIGN KEY (LoginID) REFERENCES login_tbl(LoginID)
);

-- 6. Students
CREATE TABLE students (
  StudentID INT AUTO_INCREMENT PRIMARY KEY,
  FullName VARCHAR(100) NOT NULL,
  Contact VARCHAR(20),
  Address VARCHAR(200),
  PhotoURL VARCHAR(255),
  DepartmentID INT NOT NULL,
  SemesterID INT NOT NULL,
  JoinYear YEAR DEFAULT (YEAR(CURDATE())),
  ProgramCode VARCHAR(50),
  LoginID INT NOT NULL UNIQUE,
  FOREIGN KEY (DepartmentID) REFERENCES departments(DepartmentID),
  FOREIGN KEY (SemesterID) REFERENCES semesters(SemesterID),
  FOREIGN KEY (LoginID) REFERENCES login_tbl(LoginID)
);

-- 7. Subjects
CREATE TABLE subjects (
  SubjectID INT AUTO_INCREMENT PRIMARY KEY,
  SubjectCode VARCHAR(20) NOT NULL,
  SubjectName VARCHAR(100) NOT NULL,
  CreditHour INT NOT NULL,
  LectureHour INT DEFAULT 48,
  IsElective BOOLEAN DEFAULT FALSE,
  DepartmentID INT NOT NULL,
  SemesterID INT NOT NULL,
  FOREIGN KEY (DepartmentID) REFERENCES departments(DepartmentID),
  FOREIGN KEY (SemesterID) REFERENCES semesters(SemesterID)
);

-- 8. Teacher-Subject Mapping
CREATE TABLE teacher_subject_map (
  MapID INT AUTO_INCREMENT PRIMARY KEY,
  TeacherID INT NOT NULL,
  SubjectID INT NOT NULL,
  FOREIGN KEY (TeacherID) REFERENCES teachers(TeacherID),
  FOREIGN KEY (SubjectID) REFERENCES subjects(SubjectID)
);


-- 9. Attendance Records
CREATE TABLE attendance_records (
  AttendanceID INT AUTO_INCREMENT PRIMARY KEY,
  StudentID INT NOT NULL,
  SubjectID INT NOT NULL,
  TeacherID INT NOT NULL,
  DateTime DATETIME DEFAULT CURRENT_TIMESTAMP,
  Status ENUM('present', 'absent', 'late') DEFAULT 'present',
  FOREIGN KEY (StudentID) REFERENCES students(StudentID),
  FOREIGN KEY (SubjectID) REFERENCES subjects(SubjectID),
  FOREIGN KEY (TeacherID) REFERENCES teachers(TeacherID)
);


CREATE TABLE teacher_department_map (
  MapID INT AUTO_INCREMENT PRIMARY KEY,
  TeacherID INT NOT NULL,
  DepartmentID INT NOT NULL,
  FOREIGN KEY (TeacherID) REFERENCES teachers(TeacherID),
  FOREIGN KEY (DepartmentID) REFERENCES departments(DepartmentID)
);


-- 1. Add Method column to attendance_records (THIS IS REQUIRED)
ALTER TABLE `attendance_records` 
ADD COLUMN `Method` ENUM('manual', 'qr') DEFAULT 'manual' AFTER `Status`;

-- 2. Create QR sessions table (THIS IS REQUIRED)
CREATE TABLE `qr_attendance_sessions` (
  `SessionID` int(11) NOT NULL AUTO_INCREMENT,
  `TeacherID` int(11) NOT NULL,
  `SubjectID` int(11) NOT NULL,
  `Date` date NOT NULL,
  `QRToken` varchar(255) NOT NULL,
  `ExpiresAt` datetime NOT NULL,
  `IsActive` tinyint(1) DEFAULT 1,
  `CreatedAt` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`SessionID`),
  KEY `TeacherID` (`TeacherID`),
  KEY `SubjectID` (`SubjectID`),
  CONSTRAINT `qr_attendance_sessions_ibfk_1` FOREIGN KEY (`TeacherID`) REFERENCES `teachers` (`TeacherID`),
  CONSTRAINT `qr_attendance_sessions_ibfk_2` FOREIGN KEY (`SubjectID`) REFERENCES `subjects` (`SubjectID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


-- Add these tables to your existing database

-- Materials table
CREATE TABLE `materials` (
  `MaterialID` int(11) NOT NULL AUTO_INCREMENT,
  `TeacherID` int(11) NOT NULL,
  `SubjectID` int(11) NOT NULL,
  `Title` varchar(255) NOT NULL,
  `Description` text,
  `FileName` varchar(255) NOT NULL,
  `OriginalFileName` varchar(255) NOT NULL,
  `FileSize` bigint(20) NOT NULL,
  `FileType` varchar(50) NOT NULL,
  `FilePath` varchar(500) NOT NULL,
  `UploadDateTime` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `IsActive` tinyint(1) NOT NULL DEFAULT 1,
  `DownloadCount` int(11) DEFAULT 0,
  `Tags` varchar(500),
  PRIMARY KEY (`MaterialID`),
  KEY `TeacherID` (`TeacherID`),
  KEY `SubjectID` (`SubjectID`),
  FOREIGN KEY (`TeacherID`) REFERENCES `teachers` (`TeacherID`) ON DELETE CASCADE,
  FOREIGN KEY (`SubjectID`) REFERENCES `subjects` (`SubjectID`) ON DELETE CASCADE
);

-- Material access logs
CREATE TABLE `material_access_logs` (
  `LogID` int(11) NOT NULL AUTO_INCREMENT,
  `MaterialID` int(11) NOT NULL,
  `StudentID` int(11),
  `TeacherID` int(11),
  `AccessDateTime` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `ActionType` enum('view','download') NOT NULL,
  `IPAddress` varchar(45),
  PRIMARY KEY (`LogID`),
  KEY `MaterialID` (`MaterialID`),
  KEY `StudentID` (`StudentID`),
  KEY `TeacherID` (`TeacherID`),
  FOREIGN KEY (`MaterialID`) REFERENCES `materials` (`MaterialID`) ON DELETE CASCADE,
  FOREIGN KEY (`StudentID`) REFERENCES `students` (`StudentID`) ON DELETE SET NULL,
  FOREIGN KEY (`TeacherID`) REFERENCES `teachers` (`TeacherID`) ON DELETE SET NULL
);







-- ========== Dummy Data Starts Here ==========

-- Insert Departments
INSERT INTO departments (DepartmentName) VALUES
('BCA'), ('BBA');

-- Insert Semesters (1 to 8)
INSERT INTO semesters (SemesterNumber) VALUES
(1), (2), (3), (4), (5), (6), (7), (8);

-- Insert Login Data
INSERT INTO login_tbl (Email, Password, Role, Status, CreatedDate) VALUES
('admin@college.com', 'admin123', 'admin', 'active', NOW()),
('teacher1@bca.com', 'teach123', 'teacher', 'active', NOW()),
('teacher2@bba.com', 'teach456', 'teacher', 'active', NOW()),
('student1@bca.com', 'stud123', 'student', 'active', NOW()),
('student2@bca.com', 'stud456', 'student', 'active', NOW()),
('student3@bba.com', 'stud789', 'student', 'active', NOW());

-- Insert Admin
INSERT INTO admins (FullName, Contact, Address, PhotoURL, LoginID) VALUES
('Admin User', '9800000001', 'Admin Address', NULL, 1);

-- Insert Teachers
INSERT INTO teachers (FullName, Contact, Address, PhotoURL, DepartmentID, LoginID) VALUES
('BCA Teacher', '9800000002', 'Pokhara', NULL, 1, 2),
('BBA Teacher', '9800000003', 'Kathmandu', NULL, 2, 3);

-- Insert Students (3 classes from BCA/BBA)
INSERT INTO students (FullName, Contact, Address, PhotoURL, DepartmentID, SemesterID, LoginID, ProgramCode) VALUES
('Ram BC', '9801111111', 'Butwal', NULL, 1, 1, 4, 'BCA-BATCH-2025'),
('Sita KC', '9802222222', 'Pokhara', NULL, 1, 2, 5, 'BCA-BATCH-2025'),
('Hari Lal', '9803333333', 'Lalitpur', NULL, 2, 3, 6, 'BBA-BATCH-2025');

-- Insert Subjects (3 per semester per department = 48)
INSERT INTO subjects (SubjectCode, SubjectName, CreditHour, LectureHour, DepartmentID, SemesterID) VALUES
-- BCA Semester 1
('BCA101', 'Fundamentals of IT', 3, 48, 1, 1),
('BCA102', 'Mathematics I', 3, 48, 1, 1),
('BCA103', 'Digital Logic', 3, 48, 1, 1),
-- BCA Semester 2
('BCA201', 'C Programming', 3, 48, 1, 2),
('BCA202', 'Discrete Structure', 3, 48, 1, 2),
('BCA203', 'Microprocessor', 3, 48, 1, 2),
-- BCA Semester 3
('BCA301', 'Data Structures', 3, 48, 1, 3),
('BCA302', 'OOP in Java', 3, 48, 1, 3),
('BCA303', 'Web Technology', 3, 48, 1, 3),

-- BBA Semester 3
('BBA301', 'Marketing Management', 3, 48, 2, 3),
('BBA302', 'HRM', 3, 48, 2, 3),
('BBA303', 'Organizational Behavior', 3, 48, 2, 3);

-- Insert Teacher-Subject Mappings
INSERT INTO teacher_subject_map (TeacherID, SubjectID) VALUES
-- BCA Teacher handles Sem 1
(1, 1), (1, 2), (1, 3),
-- BBA Teacher handles Sem 3
(2, 10), (2, 11), (2, 12);