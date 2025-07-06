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
