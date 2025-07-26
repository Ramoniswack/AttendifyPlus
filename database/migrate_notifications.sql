-- Migration script to enhance the notifications table
-- Run this after the main database setup

-- First, backup existing notifications data
CREATE TABLE notifications_backup AS SELECT * FROM notifications;
CREATE TABLE notification_reads_backup AS SELECT * FROM notification_reads;

-- Drop existing tables
DROP TABLE IF EXISTS notification_reads;
DROP TABLE IF EXISTS notifications;

-- Create enhanced notifications table
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

-- Create notification reads table
CREATE TABLE notification_reads (
    id INT AUTO_INCREMENT PRIMARY KEY,
    notification_id INT NOT NULL,
    user_id INT NOT NULL,
    read_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY (notification_id, user_id),
    FOREIGN KEY (notification_id) REFERENCES notifications(id) ON DELETE CASCADE
);

-- Migrate existing data (if any)
INSERT INTO notifications (id, user_id, role, title, message, icon, type, created_at)
SELECT id, user_id, role, title, message, icon, type, created_at
FROM notifications_backup;

-- Insert sample notifications for testing
INSERT INTO notifications (role, title, message, icon, type, action_type) VALUES
('student', 'Welcome to AttendifyPlus!', 'Your student portal is now active. You can view materials, submit assignments, and track your attendance.', 'graduation-cap', 'success', 'welcome'),
('teacher', 'Welcome to AttendifyPlus!', 'Your teacher portal is now active. You can upload materials, create assignments, and manage attendance.', 'user-check', 'success', 'welcome'),
('admin', 'System Ready', 'AttendifyPlus system is fully operational. All modules are active.', 'shield-check', 'success', 'system_ready');

-- Clean up backup tables (optional - uncomment if you want to remove them)
-- DROP TABLE notifications_backup;
-- DROP TABLE notification_reads_backup;

-- Verify migration
SELECT 'Migration completed successfully' as status;
SELECT COUNT(*) as total_notifications FROM notifications; 