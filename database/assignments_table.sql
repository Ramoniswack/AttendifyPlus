-- Create assignments table for AttendifyPlus
-- This should be run after the main apd.sql database setup

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

-- Display summary
SELECT 'Assignment Tables Created Successfully!' as Message;
