
-- =======================
-- Attendify+ SQL Schema (Extended Version with More Dummy Data)
-- =======================

-- Table: login_tbl
CREATE TABLE login_tbl (
    LoginID INT AUTO_INCREMENT PRIMARY KEY,
    Email VARCHAR(100) UNIQUE NOT NULL,
    Password VARCHAR(255) NOT NULL,
    Role ENUM('admin', 'teacher', 'student') NOT NULL,
    Status ENUM('active', 'inactive') DEFAULT 'active'
);

-- Table: admins
CREATE TABLE admins (
    AdminID INT AUTO_INCREMENT PRIMARY KEY,
    LoginID INT UNIQUE,
    FullName VARCHAR(100) NOT NULL,
    Contact VARCHAR(20),
    FOREIGN KEY (LoginID) REFERENCES login_tbl(LoginID) ON DELETE CASCADE
);

-- Table: departments
CREATE TABLE departments (
    DepartmentID INT AUTO_INCREMENT PRIMARY KEY,
    DepartmentName VARCHAR(100) NOT NULL UNIQUE
);

-- Table: batches
CREATE TABLE batches (
    BatchID INT AUTO_INCREMENT PRIMARY KEY,
    Year INT NOT NULL,
    Session ENUM('Spring', 'Fall') NOT NULL
);

-- Table: semesters
CREATE TABLE semesters (
    SemesterID INT AUTO_INCREMENT PRIMARY KEY,
    SemesterNumber INT NOT NULL
);

-- Table: teachers
CREATE TABLE teachers (
    TeacherID INT AUTO_INCREMENT PRIMARY KEY,
    LoginID INT UNIQUE,
    FullName VARCHAR(100) NOT NULL,
    Contact VARCHAR(20),
    Type ENUM('full-time', 'part-time'),
    FOREIGN KEY (LoginID) REFERENCES login_tbl(LoginID) ON DELETE CASCADE
);

-- Table: students
CREATE TABLE students (
    StudentID INT AUTO_INCREMENT PRIMARY KEY,
    LoginID INT UNIQUE,
    FullName VARCHAR(100) NOT NULL,
    ExamRoll VARCHAR(20),
    BatchID INT,
    DepartmentID INT,
    SemesterID INT,
    FOREIGN KEY (LoginID) REFERENCES login_tbl(LoginID) ON DELETE CASCADE,
    FOREIGN KEY (BatchID) REFERENCES batches(BatchID),
    FOREIGN KEY (DepartmentID) REFERENCES departments(DepartmentID),
    FOREIGN KEY (SemesterID) REFERENCES semesters(SemesterID)
);

-- Table: subjects
CREATE TABLE subjects (
    SubjectID INT AUTO_INCREMENT PRIMARY KEY,
    SubjectName VARCHAR(100) NOT NULL,
    CreditHour INT NOT NULL,
    DepartmentID INT,
    SemesterID INT,
    IsElective BOOLEAN DEFAULT FALSE,
    FOREIGN KEY (DepartmentID) REFERENCES departments(DepartmentID),
    FOREIGN KEY (SemesterID) REFERENCES semesters(SemesterID)
);

-- Table: teacher_subject_assignments
CREATE TABLE teacher_subject_assignments (
    AssignmentID INT AUTO_INCREMENT PRIMARY KEY,
    TeacherID INT,
    SubjectID INT,
    SemesterID INT,
    FOREIGN KEY (TeacherID) REFERENCES teachers(TeacherID),
    FOREIGN KEY (SubjectID) REFERENCES subjects(SubjectID),
    FOREIGN KEY (SemesterID) REFERENCES semesters(SemesterID)
);

-- Table: student_enrollments
CREATE TABLE student_enrollments (
    EnrollmentID INT AUTO_INCREMENT PRIMARY KEY,
    StudentID INT,
    SubjectID INT,
    SemesterID INT,
    FOREIGN KEY (StudentID) REFERENCES students(StudentID),
    FOREIGN KEY (SubjectID) REFERENCES subjects(SubjectID),
    FOREIGN KEY (SemesterID) REFERENCES semesters(SemesterID)
);

-- Table: attendance_records
CREATE TABLE attendance_records (
    AttendanceID INT AUTO_INCREMENT PRIMARY KEY,
    StudentID INT,
    SubjectID INT,
    TeacherID INT,
    DateTime DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (StudentID) REFERENCES students(StudentID),
    FOREIGN KEY (SubjectID) REFERENCES subjects(SubjectID),
    FOREIGN KEY (TeacherID) REFERENCES teachers(TeacherID)
);

-- Table: seminars
CREATE TABLE seminars (
    SeminarID INT AUTO_INCREMENT PRIMARY KEY,
    Title VARCHAR(150),
    DepartmentID INT,
    Date DATE,
    FOREIGN KEY (DepartmentID) REFERENCES departments(DepartmentID)
);

-- Table: seminar_attendance
CREATE TABLE seminar_attendance (
    ID INT AUTO_INCREMENT PRIMARY KEY,
    StudentID INT,
    SeminarID INT,
    EntryTime DATETIME,
    ExitTime DATETIME,
    FOREIGN KEY (StudentID) REFERENCES students(StudentID),
    FOREIGN KEY (SeminarID) REFERENCES seminars(SeminarID)
);

-- ================
-- Dummy Data Inserts
-- ================

-- Logins
INSERT INTO login_tbl (Email, Password, Role) VALUES
('admin1@college.edu', 'admin123', 'admin'),
('admin2@college.edu', 'admin456', 'admin'),
('anuja.sharma@college.edu', 'teach123', 'teacher'),
('bipin.gurung@college.edu', 'teach234', 'teacher'),
('maya.kc@college.edu', 'teach345', 'teacher'),
('ramesh.thapa@college.edu', 'teach456', 'teacher'),
('sushant.kc@college.edu', 'stud123', 'student'),
('rikita.gharti@college.edu', 'stud234', 'student'),
('namrata.bastola@college.edu', 'stud345', 'student');

-- Admins
INSERT INTO admins (LoginID, FullName, Contact) VALUES
(1, 'Dr. Ramesh Neupane', '9800000001'),
(2, 'Prof. Apsara Thapa', '9800000002');

-- Departments
INSERT INTO departments (DepartmentName) VALUES
('BCA'), ('BBA');

-- Batches
INSERT INTO batches (Year, Session) VALUES
(2022, 'Fall'), (2023, 'Spring'), (2024, 'Fall');

-- Semesters
INSERT INTO semesters (SemesterNumber) VALUES
(1), (2), (3), (4), (5), (6), (7), (8);

-- Teachers
INSERT INTO teachers (LoginID, FullName, Contact, Type) VALUES
(3, 'Ms. Anuja Sharma', '9800000003', 'full-time'),
(4, 'Mr. Bipin Gurung', '9800000004', 'part-time'),
(5, 'Mrs. Maya KC', '9800000005', 'full-time'),
(6, 'Mr. Ramesh Thapa', '9800000006', 'part-time');

-- Students
INSERT INTO students (LoginID, FullName, ExamRoll, BatchID, DepartmentID, SemesterID) VALUES
(7, 'Sushant KC', '22530021', 1, 1, 3),
(8, 'Rikita Gharti', '22530022', 1, 1, 3),
(9, 'Namrata Bastola', '22530023', 2, 2, 2);

-- Subjects
INSERT INTO subjects (SubjectName, CreditHour, DepartmentID, SemesterID, IsElective) VALUES
('Database Management System', 4, 1, 3, FALSE),
('Web Programming', 3, 1, 3, FALSE),
('Accounting Basics', 3, 2, 2, FALSE),
('Business Communication', 2, 2, 2, FALSE),
('Software Engineering', 4, 1, 5, FALSE),
('Organizational Behavior', 3, 2, 5, TRUE);

-- Assignments
INSERT INTO teacher_subject_assignments (TeacherID, SubjectID, SemesterID) VALUES
(1, 1, 3),
(1, 2, 3),
(2, 3, 2),
(2, 4, 2),
(3, 5, 5),
(4, 6, 5);

-- Enrollments
INSERT INTO student_enrollments (StudentID, SubjectID, SemesterID) VALUES
(1, 1, 3),
(1, 2, 3),
(2, 1, 3),
(2, 2, 3),
(3, 3, 2),
(3, 4, 2);

-- Seminar
INSERT INTO seminars (Title, DepartmentID, Date) VALUES
('Cyber Security Awareness', 1, '2025-08-01'),
('Startup Pitching Workshop', 2, '2025-08-10');

-- Seminar Attendance
INSERT INTO seminar_attendance (StudentID, SeminarID, EntryTime, ExitTime) VALUES
(1, 1, '2025-08-01 09:00:00', '2025-08-01 11:00:00'),
(3, 2, '2025-08-10 10:00:00', '2025-08-10 12:30:00');
