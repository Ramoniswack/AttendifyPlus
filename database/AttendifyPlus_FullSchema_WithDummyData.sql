
-- =======================
-- Attendify+ SQL Schema
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
    StudentID VARCHAR(20) PRIMARY KEY,
    LoginID INT UNIQUE,
    FullName VARCHAR(100) NOT NULL,
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
    StudentID VARCHAR(20),
    SubjectID INT,
    SemesterID INT,
    FOREIGN KEY (StudentID) REFERENCES students(StudentID),
    FOREIGN KEY (SubjectID) REFERENCES subjects(SubjectID),
    FOREIGN KEY (SemesterID) REFERENCES semesters(SemesterID)
);

-- Table: attendance_records
CREATE TABLE attendance_records (
    AttendanceID INT AUTO_INCREMENT PRIMARY KEY,
    StudentID VARCHAR(20),
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
    StudentID VARCHAR(20),
    SeminarID INT,
    EntryTime DATETIME,
    ExitTime DATETIME,
    FOREIGN KEY (StudentID) REFERENCES students(StudentID),
    FOREIGN KEY (SeminarID) REFERENCES seminars(SeminarID)
);

-- ========================
-- Dummy Data Insertions
-- ========================

-- Login entries
INSERT INTO login_tbl (Email, Password, Role) VALUES 
('admin@college.edu', 'admin123', 'admin'),
('teacher@college.edu', 'teach123', 'teacher'),
('student@college.edu', 'stud123', 'student');

-- Admins
INSERT INTO admins (LoginID, FullName, Contact) VALUES (1, 'Dr. Ramesh Neupane', '9800000001');

-- Departments
INSERT INTO departments (DepartmentName) VALUES ('BCA'), ('BBA');

-- Batches
INSERT INTO batches (Year, Session) VALUES (2022, 'Fall'), (2023, 'Spring');

-- Semesters
INSERT INTO semesters (SemesterNumber) VALUES (1), (2), (3), (4);

-- Teachers
INSERT INTO teachers (LoginID, FullName, Contact, Type) VALUES (2, 'Ms. Anuja Sharma', '9800000002', 'full-time');

-- Students
INSERT INTO students (StudentID, LoginID, FullName, BatchID, DepartmentID, SemesterID) VALUES 
('22530021', 3, 'Sushant KC', 1, 1, 2);

-- Subjects
INSERT INTO subjects (SubjectName, CreditHour, DepartmentID, SemesterID, IsElective) VALUES 
('Database Management System', 4, 1, 2, FALSE),
('Web Programming', 3, 1, 2, FALSE);

-- Teacher-Subject Assignment
INSERT INTO teacher_subject_assignments (TeacherID, SubjectID, SemesterID) VALUES (1, 1, 2), (1, 2, 2);

-- Student Enrollments
INSERT INTO student_enrollments (StudentID, SubjectID, SemesterID) VALUES 
('22530021', 1, 2),
('22530021', 2, 2);

-- Seminar
INSERT INTO seminars (Title, DepartmentID, Date) VALUES ('AI Workshop', 1, '2025-07-01');

-- Seminar Attendance
INSERT INTO seminar_attendance (StudentID, SeminarID, EntryTime, ExitTime) VALUES 
('22530021', 1, '2025-07-01 10:00:00', '2025-07-01 12:00:00');
