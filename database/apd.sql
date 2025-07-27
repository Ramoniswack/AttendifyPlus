
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




-- Add these tables to your database
CREATE TABLE device_registration_tokens (
    TokenID INT AUTO_INCREMENT PRIMARY KEY,
    StudentID INT NOT NULL,
    Token VARCHAR(64) NOT NULL,
    ExpiresAt DATETIME NOT NULL,
    Used BOOLEAN DEFAULT FALSE,
    CreatedAt DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (StudentID) REFERENCES students(StudentID) ON DELETE CASCADE,
    INDEX idx_token (Token),
    INDEX idx_expires (ExpiresAt)
);

CREATE TABLE student_devices (
    DeviceID INT AUTO_INCREMENT PRIMARY KEY,
    StudentID INT NOT NULL,
    DeviceFingerprint VARCHAR(255) NOT NULL,
    DeviceName VARCHAR(100),
    DeviceInfo TEXT,
    IsActive BOOLEAN DEFAULT TRUE,
    RegisteredAt DATETIME DEFAULT CURRENT_TIMESTAMP,
    LastUsed DATETIME,
    FOREIGN KEY (StudentID) REFERENCES students(StudentID) ON DELETE CASCADE,
    UNIQUE KEY unique_device_student (DeviceFingerprint, StudentID)
);

-- Add this column to students table
ALTER TABLE students ADD COLUMN DeviceRegistered BOOLEAN DEFAULT FALSE AFTER ProgramCode;


-- Create table for pending QR attendance (before teacher approval)
CREATE TABLE IF NOT EXISTS qr_attendance_pending (
    PendingID INT AUTO_INCREMENT PRIMARY KEY,
    StudentID INT NOT NULL,
    TeacherID INT NOT NULL,
    SubjectID INT NOT NULL,
    SessionID INT NOT NULL,
    CreatedAt DATETIME NOT NULL,
    Status VARCHAR(20) NOT NULL DEFAULT 'present',
    INDEX idx_student_date (StudentID, CreatedAt),
    INDEX idx_teacher_subject_date (TeacherID, SubjectID, CreatedAt),
    INDEX idx_session (SessionID),
    FOREIGN KEY (StudentID) REFERENCES students(StudentID) ON DELETE CASCADE,
    FOREIGN KEY (TeacherID) REFERENCES teachers(TeacherID) ON DELETE CASCADE,
    FOREIGN KEY (SubjectID) REFERENCES subjects(SubjectID) ON DELETE CASCADE,
    FOREIGN KEY (SessionID) REFERENCES qr_attendance_sessions(SessionID) ON DELETE CASCADE
);


-- Create assignments table
CREATE TABLE IF NOT EXISTS `assignments` (
  `AssignmentID` int(11) NOT NULL AUTO_INCREMENT,
  `TeacherID` int(11) NOT NULL,
  `SubjectID` int(11) NOT NULL,
  `Title` varchar(255) NOT NULL,
  `Description` text,
  `Instructions` text,
  `DueDate` datetime DEFAULT NULL,
  `MaxPoints` int(11) DEFAULT 100,
  `Status` enum('draft','active','graded','archived') DEFAULT 'draft',
  `CreatedAt` datetime DEFAULT CURRENT_TIMESTAMP,
  `UpdatedAt` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `IsActive` tinyint(1) DEFAULT 1,
  `AttachmentPath` varchar(500) DEFAULT NULL,
  `AttachmentFileName` varchar(255) DEFAULT NULL,
  `AttachmentFileSize` bigint(20) DEFAULT NULL,
  `AttachmentFileType` varchar(50) DEFAULT NULL,
  `SubmissionType` enum('file','text','both') DEFAULT 'both',
  `AllowLateSubmissions` tinyint(1) DEFAULT 0,
  `GradingRubric` text,
  PRIMARY KEY (`AssignmentID`),
  KEY `TeacherID` (`TeacherID`),
  KEY `SubjectID` (`SubjectID`),
  KEY `Status` (`Status`),
  KEY `DueDate` (`DueDate`),
  FOREIGN KEY (`TeacherID`) REFERENCES `teachers` (`TeacherID`) ON DELETE CASCADE,
  FOREIGN KEY (`SubjectID`) REFERENCES `subjects` (`SubjectID`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Create assignment submissions table
CREATE TABLE IF NOT EXISTS `assignment_submissions` (
  `SubmissionID` int(11) NOT NULL AUTO_INCREMENT,
  `AssignmentID` int(11) NOT NULL,
  `StudentID` int(11) NOT NULL,
  `SubmissionText` text,
  `FilePath` varchar(500) DEFAULT NULL,
  `OriginalFileName` varchar(255) DEFAULT NULL,
  `FileSize` bigint(20) DEFAULT NULL,
  `SubmittedAt` datetime DEFAULT CURRENT_TIMESTAMP,
  `IsLate` tinyint(1) DEFAULT 0,
  `Status` enum('submitted','graded','returned') DEFAULT 'submitted',
  `Grade` decimal(5,2) DEFAULT NULL,
  `MaxGrade` decimal(5,2) DEFAULT NULL,
  `Feedback` text,
  `GradedAt` datetime DEFAULT NULL,
  `GradedBy` int(11) DEFAULT NULL,
  PRIMARY KEY (`SubmissionID`),
  UNIQUE KEY `unique_assignment_student` (`AssignmentID`,`StudentID`),
  KEY `AssignmentID` (`AssignmentID`),
  KEY `StudentID` (`StudentID`),
  KEY `Status` (`Status`),
  KEY `GradedBy` (`GradedBy`),
  FOREIGN KEY (`AssignmentID`) REFERENCES `assignments` (`AssignmentID`) ON DELETE CASCADE,
  FOREIGN KEY (`StudentID`) REFERENCES `students` (`StudentID`) ON DELETE CASCADE,
  FOREIGN KEY (`GradedBy`) REFERENCES `teachers` (`TeacherID`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Create assignment views table to track when students view assignments
CREATE TABLE IF NOT EXISTS `assignment_views` (
  `ViewID` int(11) NOT NULL AUTO_INCREMENT,
  `AssignmentID` int(11) NOT NULL,
  `StudentID` int(11) NOT NULL,
  `ViewedAt` datetime DEFAULT CURRENT_TIMESTAMP,
  `ViewCount` int(11) DEFAULT 1,
  `LastViewedAt` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`ViewID`),
  UNIQUE KEY `unique_assignment_student_view` (`AssignmentID`,`StudentID`),
  KEY `AssignmentID` (`AssignmentID`),
  KEY `StudentID` (`StudentID`),
  FOREIGN KEY (`AssignmentID`) REFERENCES `assignments` (`AssignmentID`) ON DELETE CASCADE,
  FOREIGN KEY (`StudentID`) REFERENCES `students` (`StudentID`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


-- Create indexes for better performance
CREATE INDEX idx_assignments_teacher_status ON assignments(TeacherID, Status);
CREATE INDEX idx_assignments_subject_status ON assignments(SubjectID, Status);
CREATE INDEX idx_assignments_due_date ON assignments(DueDate);
CREATE INDEX idx_submissions_assignment_student ON assignment_submissions(AssignmentID, StudentID);
CREATE INDEX idx_submissions_status ON assignment_submissions(Status);

CREATE TABLE IF NOT EXISTS `assignment_submission_files` (
  `FileID` int(11) NOT NULL AUTO_INCREMENT,
  `SubmissionID` int(11) NOT NULL,
  `FileName` varchar(255) NOT NULL,
  `FilePath` varchar(500) NOT NULL,
  `FileSize` bigint(20) NOT NULL,
  `FileType` varchar(50) DEFAULT NULL,
  `UploadedAt` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`FileID`),
  KEY `SubmissionID` (`SubmissionID`),
  FOREIGN KEY (`SubmissionID`) REFERENCES `assignment_submissions` (`SubmissionID`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;




-- Enhanced notifications table with better targeting
CREATE TABLE notifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NULL,                    -- For user-specific notifications
    role VARCHAR(20) NULL,               -- For role-based notifications ('student', 'teacher', 'admin')
    department_id INT NULL,              -- For department-specific notifications
    subject_id INT NULL,                 -- For subject-specific notifications
    teacher_id INT NULL,                 -- For teacher-specific notifications
    student_id INT NULL,                 -- For student-specific notifications
    title VARCHAR(255) NOT NULL,
    message TEXT,
    icon VARCHAR(32) DEFAULT 'bell',     -- e.g., 'alert-triangle', 'check-circle', 'download', 'upload'
    type VARCHAR(32) DEFAULT 'info',     -- e.g., 'info', 'warning', 'success', 'error'
    action_type VARCHAR(50) NULL,        -- e.g., 'assignment_submitted', 'material_uploaded', 'attendance_taken', 'qr_scanned'
    action_data JSON NULL,               -- Additional data for the action (e.g., assignment_id, material_id)
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_user_role (user_id, role),
    INDEX idx_department (department_id),
    INDEX idx_subject (subject_id),
    INDEX idx_teacher (teacher_id),
    INDEX idx_student (student_id),
    INDEX idx_action_type (action_type),
    INDEX idx_created_at (created_at),
    FOREIGN KEY (department_id) REFERENCES departments(DepartmentID) ON DELETE CASCADE,
    FOREIGN KEY (subject_id) REFERENCES subjects(SubjectID) ON DELETE CASCADE,
    FOREIGN KEY (teacher_id) REFERENCES teachers(TeacherID) ON DELETE CASCADE,
    FOREIGN KEY (student_id) REFERENCES students(StudentID) ON DELETE CASCADE
);

-- Notification reads table (unchanged)
CREATE TABLE notification_reads (
    id INT AUTO_INCREMENT PRIMARY KEY,
    notification_id INT NOT NULL,
    user_id INT NOT NULL,
    read_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY (notification_id, user_id),
    FOREIGN KEY (notification_id) REFERENCES notifications(id) ON DELETE CASCADE
);


-- Create table for graduated and dropout students
CREATE TABLE student_academic_history (
    HistoryID INT AUTO_INCREMENT PRIMARY KEY,
    StudentID INT NOT NULL,
    FullName VARCHAR(100) NOT NULL,
    Contact VARCHAR(20),
    Address VARCHAR(200),
    PhotoURL VARCHAR(255),
    DepartmentID INT NOT NULL,
    SemesterID INT NOT NULL,
    JoinYear YEAR,
    ProgramCode VARCHAR(50),
    LoginID INT NOT NULL,
    Status ENUM('graduated', 'dropout') NOT NULL,
    Reason TEXT,
    ActionDate DATETIME DEFAULT CURRENT_TIMESTAMP,
    ActionBy INT NOT NULL,
    FOREIGN KEY (DepartmentID) REFERENCES departments(DepartmentID),
    FOREIGN KEY (SemesterID) REFERENCES semesters(SemesterID),
    FOREIGN KEY (ActionBy) REFERENCES admins(AdminID)
);

-- ========== Dummy Data Starts Here ==========
-- Complete database reset with BCA and BBA departments only

-- Disable foreign key checks
SET FOREIGN_KEY_CHECKS = 0;

-- Clear all data
DELETE FROM qr_attendance_sessions;
DELETE FROM device_registration_tokens;
DELETE FROM student_devices;
DELETE FROM material_access_logs;
DELETE FROM materials;
DELETE FROM attendance_records;
DELETE FROM teacher_subject_map;
DELETE FROM teacher_department_map;
DELETE FROM students;
DELETE FROM teachers;
DELETE FROM admins;
DELETE FROM subjects;
DELETE FROM semesters;
DELETE FROM departments;
DELETE FROM login_tbl;

-- Reset auto-increment counters
ALTER TABLE qr_attendance_sessions AUTO_INCREMENT = 1;
ALTER TABLE device_registration_tokens AUTO_INCREMENT = 1;
ALTER TABLE student_devices AUTO_INCREMENT = 1;
ALTER TABLE material_access_logs AUTO_INCREMENT = 1;
ALTER TABLE materials AUTO_INCREMENT = 1;
ALTER TABLE attendance_records AUTO_INCREMENT = 1;
ALTER TABLE teacher_subject_map AUTO_INCREMENT = 1;
ALTER TABLE teacher_department_map AUTO_INCREMENT = 1;
ALTER TABLE students AUTO_INCREMENT = 1;
ALTER TABLE teachers AUTO_INCREMENT = 1;
ALTER TABLE admins AUTO_INCREMENT = 1;
ALTER TABLE subjects AUTO_INCREMENT = 1;
ALTER TABLE semesters AUTO_INCREMENT = 1;
ALTER TABLE departments AUTO_INCREMENT = 1;
ALTER TABLE login_tbl AUTO_INCREMENT = 1;

-- Re-enable foreign key checks
SET FOREIGN_KEY_CHECKS = 1;

-- ========== INSERT FRESH DATA ==========

-- 1. Insert Departments - ONLY BCA and BBA
INSERT INTO departments (DepartmentName, DepartmentCode) VALUES
('Bachelor of Computer Application', 'BCA'),
('Bachelor of Business Administration', 'BBA');

-- 2. Insert Semesters (1 to 8)
INSERT INTO semesters (SemesterNumber) VALUES
(1), (2), (3), (4), (5), (6), (7), (8);

-- 3. Insert Login Data with unique emails (password = "password")
INSERT INTO login_tbl (Email, Password, Role, Status, CreatedDate) VALUES
('admin@lagrandee.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin', 'active', NOW()),
('teacher.bca1@lagrandee.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'teacher', 'active', NOW()),
('teacher.bca2@lagrandee.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'teacher', 'active', NOW()),
('teacher.bba1@lagrandee.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'teacher', 'active', NOW()),
('teacher.bba2@lagrandee.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'teacher', 'active', NOW()),
('student.ram@lagrandee.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'student', 'active', NOW()),
('student.sita@lagrandee.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'student', 'active', NOW()),
('student.hari@lagrandee.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'student', 'active', NOW()),
('student.gita@lagrandee.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'student', 'active', NOW()),
('student.rita@lagrandee.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'student', 'active', NOW()),
('student.maya@lagrandee.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'student', 'active', NOW()),
('student.kiran@lagrandee.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'student', 'active', NOW()),
('student.deepak@lagrandee.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'student', 'active', NOW()),
('student.binita@lagrandee.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'student', 'active', NOW()),
('student.suresh@lagrandee.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'student', 'active', NOW());

-- 4. Insert Admin
INSERT INTO admins (FullName, Contact, Address, PhotoURL, LoginID) VALUES
('System Administrator', '9800000001', 'Pokhara, Nepal', NULL, 1);

-- 5. Insert Teachers - BCA and BBA Teachers only
INSERT INTO teachers (FullName, Contact, Address, PhotoURL, LoginID) VALUES
('Dr. Rajesh Kumar Sharma', '9801234567', 'Pokhara-8, Kaski', NULL, 2),  -- BCA Teacher 1
('Prof. Anjali Thapa', '9802345678', 'Kathmandu-7, Bagmati', NULL, 3),   -- BCA Teacher 2
('Er. Bikash Adhikari', '9803456789', 'Butwal-11, Rupandehi', NULL, 4),  -- BBA Teacher 1
('Dr. Sunita Poudel', '9804567890', 'Chitwan-5, Bharatpur', NULL, 5);    -- BBA Teacher 2

-- 6. Insert Subjects - BCA and BBA subjects only (based on apd.sql pattern)
INSERT INTO subjects (SubjectCode, SubjectName, CreditHour, LectureHour, IsElective, DepartmentID, SemesterID) VALUES
-- BCA Semester 1
('BCA101', 'Fundamentals of Information Technology', 3, 48, FALSE, 1, 1),
('BCA102', 'Mathematics I (Calculus & Algebra)', 3, 48, FALSE, 1, 1),
('BCA103', 'Digital Logic and Computer Organization', 3, 48, FALSE, 1, 1),
('BCA104', 'English Communication', 3, 48, FALSE, 1, 1),
('BCA105', 'Physics for Computing', 3, 48, FALSE, 1, 1),

-- BCA Semester 2
('BCA201', 'C Programming Language', 4, 64, FALSE, 1, 2),
('BCA202', 'Mathematics II (Statistics & Probability)', 3, 48, FALSE, 1, 2),
('BCA203', 'Microprocessor and Assembly Language', 3, 48, FALSE, 1, 2),
('BCA204', 'Discrete Mathematical Structures', 3, 48, FALSE, 1, 2),
('BCA205', 'Financial Accounting', 3, 48, FALSE, 1, 2),

-- BCA Semester 3
('BCA301', 'Data Structures and Algorithms', 4, 64, FALSE, 1, 3),
('BCA302', 'Object Oriented Programming (Java)', 4, 64, FALSE, 1, 3),
('BCA303', 'Computer Graphics and Animation', 3, 48, FALSE, 1, 3),
('BCA304', 'Web Technology I (HTML, CSS, JS)', 3, 48, FALSE, 1, 3),
('BCA305', 'Mathematics III (Numerical Methods)', 3, 48, FALSE, 1, 3),

-- BCA Semester 4
('BCA401', 'Database Management System', 4, 64, FALSE, 1, 4),
('BCA402', 'Operating Systems', 3, 48, FALSE, 1, 4),
('BCA403', 'Web Technology II (PHP, MySQL)', 4, 64, FALSE, 1, 4),
('BCA404', 'Software Engineering', 3, 48, FALSE, 1, 4),
('BCA405', 'Computer Networks', 3, 48, FALSE, 1, 4),

-- BCA Semester 5
('BCA501', 'Mobile Application Development', 4, 64, FALSE, 1, 5),
('BCA502', 'System Analysis and Design', 3, 48, FALSE, 1, 5),
('BCA503', 'Advanced Java Programming', 4, 64, FALSE, 1, 5),
('BCA504', 'E-commerce and Digital Marketing', 3, 48, TRUE, 1, 5),
('BCA505', 'Project Management', 3, 48, FALSE, 1, 5),

-- BCA Semester 6
('BCA601', 'Artificial Intelligence', 3, 48, FALSE, 1, 6),
('BCA602', 'Cyber Security and Ethical Hacking', 3, 48, FALSE, 1, 6),
('BCA603', 'Cloud Computing', 3, 48, FALSE, 1, 6),
('BCA604', 'Final Year Project I', 6, 96, FALSE, 1, 6),

-- BBA Semester 1
('BBA101', 'Principles of Management', 3, 48, FALSE, 2, 1),
('BBA102', 'Business Mathematics', 3, 48, FALSE, 2, 1),
('BBA103', 'Microeconomics', 3, 48, FALSE, 2, 1),
('BBA104', 'Business English', 3, 48, FALSE, 2, 1),
('BBA105', 'Computer Applications in Business', 3, 48, FALSE, 2, 1),

-- BBA Semester 2
('BBA201', 'Macroeconomics', 3, 48, FALSE, 2, 2),
('BBA202', 'Financial Accounting', 3, 48, FALSE, 2, 2),
('BBA203', 'Business Statistics', 3, 48, FALSE, 2, 2),
('BBA204', 'Organizational Behavior', 3, 48, FALSE, 2, 2),
('BBA205', 'Business Communication', 3, 48, FALSE, 2, 2),

-- BBA Semester 3
('BBA301', 'Marketing Management', 3, 48, FALSE, 2, 3),
('BBA302', 'Human Resource Management', 3, 48, FALSE, 2, 3),
('BBA303', 'Business Law', 3, 48, FALSE, 2, 3),
('BBA304', 'Cost and Management Accounting', 3, 48, FALSE, 2, 3),
('BBA305', 'Research Methodology', 3, 48, FALSE, 2, 3),

-- BBA Semester 4
('BBA401', 'Financial Management', 3, 48, FALSE, 2, 4),
('BBA402', 'Operations Management', 3, 48, FALSE, 2, 4),
('BBA403', 'International Business', 3, 48, FALSE, 2, 4),
('BBA404', 'Entrepreneurship Development', 3, 48, FALSE, 2, 4),
('BBA405', 'Business Ethics', 3, 48, FALSE, 2, 4),

-- BBA Semester 5
('BBA501', 'Strategic Management', 3, 48, FALSE, 2, 5),
('BBA502', 'Investment and Portfolio Management', 3, 48, FALSE, 2, 5),
('BBA503', 'Digital Marketing', 3, 48, FALSE, 2, 5),
('BBA504', 'Supply Chain Management', 3, 48, FALSE, 2, 5),
('BBA505', 'Business Analytics', 3, 48, TRUE, 2, 5),

-- BBA Semester 6
('BBA601', 'Corporate Governance', 3, 48, FALSE, 2, 6),
('BBA602', 'Risk Management', 3, 48, FALSE, 2, 6),
('BBA603', 'Final Year Project I', 6, 96, FALSE, 2, 6);

-- 7. Insert Students from BCA and BBA departments (based on apd.sql style)
INSERT INTO students (FullName, Contact, Address, PhotoURL, DepartmentID, SemesterID, JoinYear, ProgramCode, LoginID, DeviceRegistered) VALUES
-- BCA Students
('Ram Bahadur Thapa', '9801111111', 'Pokhara-15, Kaski', NULL, 1, 1, 2024, 'BCA-2024', 6, TRUE),
('Sita Kumari Poudel', '9802222222', 'Kathmandu-10, Bagmati', NULL, 1, 2, 2024, 'BCA-2024', 7, FALSE),
('Gita Devi Acharya', '9804444444', 'Chitwan-2, Bharatpur', NULL, 1, 3, 2024, 'BCA-2024', 9, TRUE),
('Maya Laxmi Shrestha', '9807777777', 'Lalitpur-3, Bagmati', NULL, 1, 4, 2023, 'BCA-2023', 11, FALSE),
('Kiran Bahadur Magar', '9808888888', 'Pokhara-17, Kaski', NULL, 1, 5, 2023, 'BCA-2023', 12, TRUE),
('Deepak Gurung', '9809999999', 'Butwal-5, Rupandehi', NULL, 1, 6, 2022, 'BCA-2022', 13, FALSE),

-- BBA Students  
('Hari Prasad Sharma', '9803333333', 'Butwal-8, Rupandehi', NULL, 2, 1, 2024, 'BBA-2024', 8, FALSE),
('Rita Kumari Gurung', '9805555555', 'Pokhara-12, Kaski', NULL, 2, 2, 2024, 'BBA-2024', 10, FALSE),
('Binita Rai', '9806666666', 'Dharan-5, Sunsari', NULL, 2, 3, 2024, 'BBA-2024', 14, TRUE),
('Suresh Tamang', '9800111222', 'Kathmandu-5, Bagmati', NULL, 2, 4, 2023, 'BBA-2023', 15, TRUE);

-- 8. Insert Teacher-Subject Mappings (based on apd.sql pattern)
INSERT INTO teacher_subject_map (TeacherID, SubjectID) VALUES
-- Dr. Rajesh Kumar Sharma (BCA Teacher 1) - handles BCA Sem 1, 2, 3
(1, 1), (1, 2), (1, 3), (1, 6), (1, 7), (1, 11), (1, 12), (1, 15),
-- Prof. Anjali Thapa (BCA Teacher 2) - handles BCA Sem 4, 5, 6  
(2, 16), (2, 17), (2, 18), (2, 21), (2, 22), (2, 23), (2, 26), (2, 27), (2, 28),
-- Er. Bikash Adhikari (BBA Teacher 1) - handles BBA Sem 1, 2, 3
(3, 29), (3, 30), (3, 31), (3, 34), (3, 35), (3, 36), (3, 39), (3, 40), (3, 41),
-- Dr. Sunita Poudel (BBA Teacher 2) - handles BBA Sem 4, 5, 6
(4, 44), (4, 45), (4, 46), (4, 49), (4, 50), (4, 51), (4, 54), (4, 55), (4, 56);

-- 9. Insert Teacher-Department Mappings
INSERT INTO teacher_department_map (TeacherID, DepartmentID) VALUES
(1, 1), -- Dr. Rajesh -> BCA Department
(2, 1), -- Prof. Anjali -> BCA Department
(3, 2), -- Er. Bikash -> BBA Department
(4, 2); -- Dr. Sunita -> BBA Department

-- 10. Insert sample attendance records (based on apd.sql style)
INSERT INTO attendance_records (StudentID, SubjectID, TeacherID, DateTime, Status, Method) VALUES
-- BCA Students attendance
(1, 1, 1, '2024-12-20 09:00:00', 'present', 'manual'),
(1, 2, 1, '2024-12-20 10:00:00', 'present', 'qr'),
(1, 3, 1, '2024-12-20 11:00:00', 'late', 'manual'),
(2, 6, 1, '2024-12-20 09:00:00', 'present', 'qr'),
(2, 7, 1, '2024-12-20 10:00:00', 'absent', 'manual'),
(3, 11, 1, '2024-12-20 09:00:00', 'present', 'qr'),
(4, 16, 2, '2024-12-20 10:00:00', 'present', 'manual'),
(5, 21, 2, '2024-12-20 11:00:00', 'late', 'qr'),
-- BBA Students attendance
(7, 29, 3, '2024-12-20 09:00:00', 'present', 'qr'),
(7, 30, 3, '2024-12-20 10:00:00', 'present', 'manual'),
(8, 34, 3, '2024-12-20 11:00:00', 'late', 'manual'),
(9, 39, 3, '2024-12-20 09:00:00', 'present', 'qr'),
(10, 44, 4, '2024-12-20 10:00:00', 'present', 'qr');

-- 11. Insert device registration tokens for testing
INSERT INTO device_registration_tokens (StudentID, Token, ExpiresAt, Used) VALUES
(2, 'abc123def456token789', DATE_ADD(NOW(), INTERVAL 10 MINUTE), FALSE),
(7, 'xyz987uvw654token321', DATE_ADD(NOW(), INTERVAL 15 MINUTE), FALSE),
(8, 'token456def789abc123', DATE_ADD(NOW(), INTERVAL 5 MINUTE), FALSE),
(4, 'used123token456def789', DATE_SUB(NOW(), INTERVAL 5 MINUTE), TRUE);

-- 12. Insert sample materials
INSERT INTO materials (TeacherID, SubjectID, Title, Description, FileName, OriginalFileName, FileSize, FileType, FilePath, Tags) VALUES
(1, 1, 'Introduction to IT - Chapter 1', 'Basic concepts of Information Technology', 'it_chapter1_20241220.pdf', 'IT_Chapter1.pdf', 2048576, 'application/pdf', '/uploads/materials/it_chapter1_20241220.pdf', 'IT,Fundamentals,Chapter1'),
(1, 6, 'C Programming Basics', 'Getting started with C programming language', 'c_programming_basics.pdf', 'C_Programming_Basics.pdf', 1536000, 'application/pdf', '/uploads/materials/c_programming_basics.pdf', 'C,Programming,Basics'),
(3, 39, 'Marketing Mix - 4Ps', 'Understanding Product, Price, Place, Promotion', 'marketing_mix_4ps.pptx', 'Marketing_Mix_4Ps.pptx', 3072000, 'application/vnd.openxmlformats-officedocument.presentationml.presentation', '/uploads/materials/marketing_mix_4ps.pptx', 'Marketing,4Ps,Mix'),
(4, 44, 'Financial Management Notes', 'Introduction to Financial Management principles', 'financial_mgmt_notes.pdf', 'Financial_Management_Notes.pdf', 1024000, 'application/pdf', '/uploads/materials/financial_mgmt_notes.pdf', 'Finance,Management,BBA');

-- Check the final state
SELECT 'Departments' as TableName, COUNT(*) as RecordCount FROM departments
UNION ALL
SELECT 'Semesters', COUNT(*) FROM semesters
UNION ALL
SELECT 'Login Accounts', COUNT(*) FROM login_tbl
UNION ALL
SELECT 'Admins', COUNT(*) FROM admins
UNION ALL
SELECT 'Teachers', COUNT(*) FROM teachers
UNION ALL
SELECT 'Students', COUNT(*) FROM students
UNION ALL
SELECT 'Subjects', COUNT(*) FROM subjects
UNION ALL
SELECT 'Teacher-Subject Maps', COUNT(*) FROM teacher_subject_map
UNION ALL
SELECT 'Teacher-Department Maps', COUNT(*) FROM teacher_department_map
UNION ALL
SELECT 'Attendance Records', COUNT(*) FROM attendance_records
UNION ALL
SELECT 'Device Tokens', COUNT(*) FROM device_registration_tokens
UNION ALL
SELECT 'Materials', COUNT(*) FROM materials;