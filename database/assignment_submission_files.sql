-- Create assignment submission files table for AttendifyPlus
-- This table stores file attachments for assignment submissions

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

-- Create indexes for better performance
CREATE INDEX idx_submission_files_submission ON assignment_submission_files(SubmissionID);
CREATE INDEX idx_submission_files_uploaded ON assignment_submission_files(UploadedAt);

-- Display summary
SELECT 'Assignment Submission Files Table Created Successfully!' as Message; 