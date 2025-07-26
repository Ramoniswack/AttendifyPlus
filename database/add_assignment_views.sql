-- Add assignment_views table if it doesn't exist
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

SELECT 'Assignment Views Table Ready!' as Message;
