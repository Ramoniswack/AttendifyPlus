<?php

session_start();
if (!isset($_SESSION['UserID']) || strtolower($_SESSION['Role']) !== 'admin') {
  header("Location: ../auth/login.php");
  exit();
}

include '../../config/db_config.php';
include '../../helpers/helpers.php';
include '../../helpers/notification_helpers.php';

$successMsg = '';
$errorMsg = '';
$errors = [];             //declare array

// 1. Move validation functions to top-level scope
function isValidFormattedName($Fullname)
{
  $Fullname = trim($Fullname);
  if (!preg_match('/^[A-Za-z. ]+$/', $Fullname)) return false;
  if (preg_match('/[.]{2,}|[ ]{2,}/', $Fullname)) return false;
  if (!preg_match('/^[A-Z]/', $Fullname)) return false;
  $words = explode(' ', $Fullname);
  foreach ($words as $word) {
    if ($word === '') continue;
    $parts = explode('.', $word);
    foreach ($parts as $part) {
      if ($part === '') continue;
      if (!preg_match('/^[A-Z][a-z]*$/', $part)) return false;
    }
  }
  return true;
}
function validateEmail($Email)
{
  $Email = trim($Email);
  if (!preg_match('/^[a-zA-Z0-9._%+-]+@lagrandee\.com$/', $Email)) return false;
  return true;
}


// Handle form submission for adding teacher
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_teacher') {
  // Collect form data
  $FullName = trim($_POST['FullName'] ?? '');
  $Email = trim($_POST['Email'] ?? '');
  $Contact = trim($_POST['Contact'] ?? '');
  $Type = $_POST['Type'] ?? '';
  $Password = $_POST['Password'] ?? '';
  $ConfirmPassword = $_POST['ConfirmPassword'] ?? '';
  $DepartmentID = $_POST['DepartmentID'] ?? '';
  $SemesterID = $_POST['SemesterID'] ?? '';
  $SubjectID = $_POST['SubjectID'] ?? '';
  $Address = trim($_POST['Address'] ?? '');
  $PhotoURL = '';

  // Assigned subjects should be treated as an array
  $SubjectIDs = is_array($SubjectID) ? $SubjectID : [$SubjectID];

  // VALIDATION
  if (empty($FullName)) {
    $errors['FullName'] = "Full name is required.";
  } elseif (!isValidFormattedName($FullName)) {
    $errors['FullName'] = "Only letters, spaces, and dots allowed. Each part must start with a capital letter.";
  }

  if (empty($Email)) {
    $errors['Email'] = "Email is required.";
  } elseif (!validateEmail($Email)) {
    $errors['Email'] = "Invalid email format. Example: example1@lagrandee.com";
  }

  if (empty($Contact)) {
    $errors['Contact'] = "Contact number is required.";
  } elseif (!preg_match('/^\d{10}$/', $Contact)) {
    $errors['Contact'] = "Contact number must be exactly 10 digits.";
  }

  if (empty($Type)) {
    $errors['Type'] = "Please select employment type.";
  }

  if (empty($DepartmentID)) {
    $errors['DepartmentID'] = "Please select a department.";
  }

  if (empty($SemesterID)) {
    $errors['SemesterID'] = "Please select a semester.";
  }

  if (empty($SubjectIDs) || !is_array($SubjectIDs) || empty($SubjectIDs[0])) {
    $errors['SubjectID'] = "Please assign at least one subject.";
  }

  if (empty($Address)) {
    $errors['Address'] = "Address is required.";
  }

  if (empty($Password)) {
    $errors['Password'] = "Password is required.";
  } elseif (!preg_match('/^(?=.*[0-9])(?=.*[!@#\$%\^&\*\-_])[A-Za-z0-9!@#\$%\^&\*\-_]{6,}$/', $Password)) {
    $errors['Password'] = "Password must be at least 6 characters long, with a number and a special character.";
  }

  if (empty($ConfirmPassword)) {
    $errors['ConfirmPassword'] = "Please confirm your password.";
  } elseif ($Password !== $ConfirmPassword) {
    $errors['ConfirmPassword'] = "Passwords do not match.";
  }

  // Photo upload
  if (isset($_FILES['PhotoFile']) && $_FILES['PhotoFile']['error'] === UPLOAD_ERR_OK) {
    $uploadDir = '../../uploads/teachers/';
    $allowedTypes = ['image/jpeg', 'image/png', 'image/gif'];
    $fileType = $_FILES['PhotoFile']['type'];

    if (!in_array($fileType, $allowedTypes)) {
      $errors['Photo'] = "Only JPEG, PNG, and GIF images are allowed.";
    } elseif ($_FILES['PhotoFile']['size'] > 5 * 1024 * 1024) {
      $errors['Photo'] = "Image size must be less than 5MB.";
    } else {
      $ext = pathinfo($_FILES['PhotoFile']['name'], PATHINFO_EXTENSION);
      $filename = uniqid('teacher_', true) . '.' . $ext;
      $targetPath = $uploadDir . $filename;

      if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
      }

      if (move_uploaded_file($_FILES['PhotoFile']['tmp_name'], $targetPath)) {
        $PhotoURL = $targetPath;
      } else {
        $errorMsg = "Failed to upload photo.";
      }
    }
  }

  // Only proceed if no error so far
  if (empty($errors)) {
    // Check if email already exists
    $emailCheck = $conn->prepare("SELECT LoginID FROM login_tbl WHERE Email = ?");
    $emailCheck->bind_param("s", $Email);
    $emailCheck->execute();
    $emailCheck->store_result();

    if ($emailCheck->num_rows > 0) {
      $errorMsg = "This email is already registered.";
    } else {
      // Begin transaction
      $conn->begin_transaction();

      try {
        // Insert login
        $stmt1 = $conn->prepare("INSERT INTO login_tbl (Email, Password, Role, Status, CreatedDate) VALUES (?, ?, 'teacher', 'active', NOW())");
        $hashedPass = password_hash($Password, PASSWORD_BCRYPT);
        $stmt1->bind_param("ss", $Email, $hashedPass);
        $stmt1->execute();
        $loginID = $conn->insert_id;

        // Insert teacher
        $stmt2 = $conn->prepare("INSERT INTO teachers (LoginID, FullName, Contact, Address, PhotoURL) VALUES (?, ?, ?, ?, ?)");
        $stmt2->bind_param("issss", $loginID, $FullName, $Contact, $Address, $PhotoURL);
        $stmt2->execute();
        $teacherID = $conn->insert_id;

        // Insert subject mapping if provided
        if (!empty($SubjectIDs) && is_array($SubjectIDs)) {
          $stmt3 = $conn->prepare("INSERT INTO teacher_subject_map (TeacherID, SubjectID) VALUES (?, ?)");
          foreach ($SubjectIDs as $subjId) {
            $stmt3->bind_param("ii", $teacherID, $subjId);
            $stmt3->execute();
          }
          $stmt3->close();
        }

        $conn->commit();

        // Create notification for new teacher registration
        // Create notification for new teacher registration using enhanced notification system
        notifyAllAdmins($conn, $_SESSION['UserID'], 'added', 'teacher', $FullName);

        $_SESSION['success_message'] = "Teacher added successfully.";
        header("Location: " . $_SERVER['PHP_SELF']);
        exit();
      } catch (Exception $e) {
        $conn->rollback();
        $errorMsg = "Error adding teacher: " . $e->getMessage();
      }

      if (isset($stmt1)) $stmt1->close();
      if (isset($stmt2)) $stmt2->close();
    }
    $emailCheck->close();
  } else {
  }
}

// 2. Add backend logic for teacher update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_teacher') {
  $teacherID = $_POST['teacher_id'] ?? '';
  $FullName = trim($_POST['FullName'] ?? '');
  $Email = trim($_POST['Email'] ?? '');
  $Contact = trim($_POST['Contact'] ?? '');
  $Address = trim($_POST['Address'] ?? '');
  $SubjectIDs = isset($_POST['SubjectID']) ? (array)$_POST['SubjectID'] : null;
  $errors = [];
  // Validate
  if (!isValidFormattedName($FullName)) {
    $errors['FullName'] = "Only letters, spaces, and dots allowed. Each part must start with a capital letter.";
  }
  if (!validateEmail($Email)) {
    $errors['Email'] = "Invalid email format. Example: example1@lagrandee.com";
  }
  if (!preg_match('/^\d{10}$/', $Contact)) {
    $errors['Contact'] = "Contact number must be exactly 10 digits.";
  }
  if (empty($Address)) {
    $errors['Address'] = "Address is required.";
  }
  // Only validate subjects if they are being explicitly updated
  $subjectsUpdated = isset($_POST['subjects_updated']) && $_POST['subjects_updated'] == '1';
  if ($subjectsUpdated && (empty($SubjectIDs) || !is_array($SubjectIDs) || (count($SubjectIDs) === 1 && empty($SubjectIDs[0])))) {
    $errors['SubjectID'] = "Please assign at least one subject.";
  }
  // Photo upload
  $PhotoURL = '';
  if (isset($_FILES['PhotoFile']) && $_FILES['PhotoFile']['error'] === UPLOAD_ERR_OK) {
    $uploadDir = '../../uploads/teachers/';
    $allowedTypes = ['image/jpeg', 'image/png', 'image/gif'];
    $fileType = $_FILES['PhotoFile']['type'];
    if (!in_array($fileType, $allowedTypes)) {
      $errors['Photo'] = "Only JPEG, PNG, and GIF images are allowed.";
    } elseif ($_FILES['PhotoFile']['size'] > 5 * 1024 * 1024) {
      $errors['Photo'] = "Image size must be less than 5MB.";
    } else {
      $ext = pathinfo($_FILES['PhotoFile']['name'], PATHINFO_EXTENSION);
      $filename = uniqid('teacher_', true) . '.' . $ext;
      $targetPath = $uploadDir . $filename;
      if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
      }
      if (move_uploaded_file($_FILES['PhotoFile']['tmp_name'], $targetPath)) {
        $PhotoURL = $targetPath;
      } else {
        $errors['Photo'] = "Failed to upload photo.";
      }
    }
  }
  if (empty($errors)) {
    $conn->begin_transaction();
    try {
      // Get current data
      $stmt = $conn->prepare("SELECT t.*, l.Email as CurrentEmail, l.LoginID, l.Status as CurrentStatus, t.PhotoURL as CurrentPhoto FROM teachers t JOIN login_tbl l ON t.LoginID = l.LoginID WHERE t.TeacherID = ?");
      $stmt->bind_param("i", $teacherID);
      $stmt->execute();
      $currentData = $stmt->get_result()->fetch_assoc();
      $stmt->close();
      $loginID = $currentData['LoginID'];
      $currentStatus = $currentData['CurrentStatus'];
      // Check for duplicate email
      if ($Email !== $currentData['CurrentEmail']) {
        $emailCheck = $conn->prepare("SELECT LoginID FROM login_tbl WHERE Email = ? AND LoginID != ?");
        $emailCheck->bind_param("si", $Email, $loginID);
        $emailCheck->execute();
        $emailCheck->store_result();
        if ($emailCheck->num_rows > 0) {
          throw new Exception("This email is already registered by another user.");
        }
        $emailCheck->close();
      }
      // Update teachers table
      $updateTeacher = $conn->prepare("UPDATE teachers SET FullName=?, Contact=?, Address=?, PhotoURL=? WHERE TeacherID=?");
      $photoToSave = $PhotoURL ? $PhotoURL : $currentData['CurrentPhoto'];
      $updateTeacher->bind_param("ssssi", $FullName, $Contact, $Address, $photoToSave, $teacherID);
      $updateTeacher->execute();
      $updateTeacher->close();
      // Update login_tbl
      $updateLogin = $conn->prepare("UPDATE login_tbl SET Email=?, Status=? WHERE LoginID=?");
      $updateLogin->bind_param("ssi", $Email, $currentStatus, $loginID);
      $updateLogin->execute();
      $updateLogin->close();
      // Update subject assignment only if subjects were actually updated
      if ($subjectsUpdated && $SubjectIDs !== null && is_array($SubjectIDs)) {
        $conn->query("DELETE FROM teacher_subject_map WHERE TeacherID = " . intval($teacherID));
        $stmtSub = $conn->prepare("INSERT INTO teacher_subject_map (TeacherID, SubjectID) VALUES (?, ?)");
        foreach ($SubjectIDs as $subjId) {
          if (!empty($subjId)) {
            $stmtSub->bind_param("ii", $teacherID, $subjId);
            $stmtSub->execute();
          }
        }
        $stmtSub->close();
      }
      $conn->commit();

      // Create notification for teacher update
      notifyAllAdmins($conn, $_SESSION['UserID'], 'edited', 'teacher', $FullName);

      $_SESSION['success_message'] = "Teacher details updated successfully.";
    } catch (Exception $e) {
      $conn->rollback();
      $_SESSION['error_message'] = "Error updating teacher: " . $e->getMessage();
    }
    header("Location: " . $_SERVER['PHP_SELF']);
    exit();
  } else {
    $_SESSION['error_message'] = "Please correct the following errors: " . implode(", ", $errors);
    header("Location: " . $_SERVER['PHP_SELF']);
    exit();
  }
}

// 1. Backend logic for updating teacher subjects only
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_subjects') {
  $teacherID = $_POST['teacher_id'] ?? '';
  $SubjectIDs = isset($_POST['SubjectID']) ? (array)$_POST['SubjectID'] : [];
  if (!empty($teacherID)) {
    $conn->begin_transaction();
    try {
      $conn->query("DELETE FROM teacher_subject_map WHERE TeacherID = " . intval($teacherID));
      if (!empty($SubjectIDs)) {
        $stmtSub = $conn->prepare("INSERT INTO teacher_subject_map (TeacherID, SubjectID) VALUES (?, ?)");
        foreach ($SubjectIDs as $subjId) {
          $stmtSub->bind_param("ii", $teacherID, $subjId);
          $stmtSub->execute();
        }
        $stmtSub->close();
      }
      $conn->commit();

      // Get teacher name for notification
      $teacherNameStmt = $conn->prepare("SELECT t.FullName FROM teachers t WHERE t.TeacherID = ?");
      $teacherNameStmt->bind_param("i", $teacherID);
      $teacherNameStmt->execute();
      $teacherName = $teacherNameStmt->get_result()->fetch_assoc()['FullName'];
      $teacherNameStmt->close();

      // Create notification for subject update
      notifyAllAdmins($conn, $_SESSION['UserID'], 'edited', 'teacher', $teacherName);

      $_SESSION['success_message'] = "Subjects updated successfully.";
    } catch (Exception $e) {
      $conn->rollback();
      $_SESSION['error_message'] = "Error updating subjects: " . $e->getMessage();
    }
    header("Location: " . $_SERVER['PHP_SELF']);
    exit();
  }
}

// Handle subject removal
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'remove_subjects') {
  $teacherID = $_POST['teacher_id'] ?? '';
  $subjectsToRemove = isset($_POST['SubjectsToRemove']) ? (array)$_POST['SubjectsToRemove'] : [];

  if (!empty($teacherID) && !empty($subjectsToRemove)) {
    $conn->begin_transaction();
    try {
      // Remove selected subjects from teacher
      $stmt = $conn->prepare("DELETE FROM teacher_subject_map WHERE TeacherID = ? AND SubjectID = ?");
      foreach ($subjectsToRemove as $subjectId) {
        $stmt->bind_param("ii", $teacherID, $subjectId);
        $stmt->execute();
      }
      $stmt->close();

      $conn->commit();

      // Get teacher name for notification
      $teacherNameStmt = $conn->prepare("SELECT t.FullName FROM teachers t WHERE t.TeacherID = ?");
      $teacherNameStmt->bind_param("i", $teacherID);
      $teacherNameStmt->execute();
      $teacherName = $teacherNameStmt->get_result()->fetch_assoc()['FullName'];
      $teacherNameStmt->close();

      // Create notification for subject removal
      notifyAllAdmins($conn, $_SESSION['UserID'], 'edited', 'teacher', $teacherName);

      $_SESSION['success_message'] = "Subjects removed successfully.";
    } catch (Exception $e) {
      $conn->rollback();
      $_SESSION['error_message'] = "Error removing subjects: " . $e->getMessage();
    }
    header("Location: " . $_SERVER['PHP_SELF']);
    exit();
  }
}

// Handle teacher status update (instead of deletion)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_status') {
  $teacherID = $_POST['teacher_id'] ?? '';
  $newStatus = $_POST['new_status'] ?? '';

  if (!empty($teacherID) && !empty($newStatus)) {
    // Update status in login_tbl
    $updateStmt = $conn->prepare("UPDATE login_tbl l 
                                 JOIN teachers t ON l.LoginID = t.LoginID 
                                 SET l.Status = ? 
                                 WHERE t.TeacherID = ?");
    $updateStmt->bind_param("si", $newStatus, $teacherID);

    if ($updateStmt->execute()) {
      // Get teacher name for notification
      $teacherNameStmt = $conn->prepare("SELECT t.FullName FROM teachers t WHERE t.TeacherID = ?");
      $teacherNameStmt->bind_param("i", $teacherID);
      $teacherNameStmt->execute();
      $teacherName = $teacherNameStmt->get_result()->fetch_assoc()['FullName'];
      $teacherNameStmt->close();

      // Create notification for teacher status change
      $statusAction = $newStatus === 'active' ? 'activated' : 'deactivated';
      notifyAllAdmins($conn, $_SESSION['UserID'], $statusAction, 'teacher', $teacherName);

      $_SESSION['success_message'] = "Teacher status updated successfully.";
    } else {
      $_SESSION['error_message'] = "Failed to update teacher status.";
    }
    $updateStmt->close();
  }

  header("Location: " . $_SERVER['PHP_SELF']);
  exit();
}

// Check for messages from session (after redirect)
if (isset($_SESSION['success_message'])) {
  $successMsg = $_SESSION['success_message'];
  unset($_SESSION['success_message']);
}
if (isset($_SESSION['error_message'])) {
  $errorMsg = $_SESSION['error_message'];
  unset($_SESSION['error_message']);
}

// Fetch teachers with enhanced data (including inactive ones)
$teachers = [];
$sql = "SELECT t.TeacherID, t.FullName, t.Contact, t.Address, l.Email, l.CreatedDate, l.Status,
               t.PhotoURL, COUNT(ts.SubjectID) as SubjectCount
        FROM teachers t
        JOIN login_tbl l ON t.LoginID = l.LoginID
        LEFT JOIN teacher_subject_map ts ON t.TeacherID = ts.TeacherID
        GROUP BY t.TeacherID, t.FullName, t.Contact, t.Address, l.Email, l.CreatedDate, l.Status, t.PhotoURL
        ORDER BY l.Status ASC, t.FullName";
$res = $conn->query($sql);

while ($row = $res->fetch_assoc()) {
  $teachers[] = $row;
}

// Fetch teacher subjects for detail view
$teacherSubjects = [];
$subjectSql = "SELECT ts.TeacherID, ts.SubjectID, s.SubjectName, s.SubjectCode, d.DepartmentName, sem.SemesterNumber
               FROM teacher_subject_map ts
               JOIN subjects s ON ts.SubjectID = s.SubjectID
               JOIN departments d ON s.DepartmentID = d.DepartmentID
               JOIN semesters sem ON s.SemesterID = sem.SemesterID
               ORDER BY ts.TeacherID, s.SubjectName";
$subjectRes = $conn->query($subjectSql);

while ($row = $subjectRes->fetch_assoc()) {
  $teacherSubjects[$row['TeacherID']][] = $row;
}

// Fetch reference data
$departments = [];
$deptRes = $conn->query("SELECT DepartmentID, DepartmentName FROM departments ORDER BY DepartmentName");
while ($row = $deptRes->fetch_assoc()) {
  $departments[] = $row;
}

$semesters = [];
$semRes = $conn->query("SELECT SemesterID, SemesterNumber FROM semesters ORDER BY SemesterNumber");
while ($row = $semRes->fetch_assoc()) {
  $semesters[] = $row;
}

// Fetch all subjects with department and semester info for subject assignment
$subjects = [];
$subRes = $conn->query("SELECT s.SubjectID, s.SubjectName, s.SubjectCode, s.DepartmentID, s.SemesterID, d.DepartmentName, sem.SemesterNumber FROM subjects s JOIN departments d ON s.DepartmentID = d.DepartmentID JOIN semesters sem ON s.SemesterID = sem.SemesterID ORDER BY s.SubjectName");
while ($row = $subRes->fetch_assoc()) {
  $subjects[] = $row;
}

// Get statistics - updated to match manage_admin structure
$stats = [];
$statsQueries = [
  'total_teachers' => "SELECT COUNT(*) as count FROM teachers t JOIN login_tbl l ON t.LoginID = l.LoginID",
  'active_teachers' => "SELECT COUNT(*) as count FROM teachers t JOIN login_tbl l ON t.LoginID = l.LoginID WHERE l.Status = 'active'",
  'inactive_teachers' => "SELECT COUNT(*) as count FROM teachers t JOIN login_tbl l ON t.LoginID = l.LoginID WHERE l.Status = 'inactive'"
];

foreach ($statsQueries as $key => $query) {
  $result = $conn->query($query);
  $stats[$key] = $result->fetch_assoc()['count'];
}

// Build a map of SubjectID => TeacherID for all assigned subjects
$assignedSubjectToTeacher = [];
$assignedRes = $conn->query("SELECT SubjectID, TeacherID FROM teacher_subject_map");
while ($row = $assignedRes->fetch_assoc()) {
  $assignedSubjectToTeacher[$row['SubjectID']] = $row['TeacherID'];
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Manage Teachers | Attendify+</title>
  <link rel="stylesheet" href="../../assets/css/manage_teacher.css" />
  <link rel="stylesheet" href="../../assets/css/sidebar_admin.css">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet" />
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet" />
  <script src="../../assets/js/lucide.min.js"></script>
  <script src="../../assets/js/manage_teacher.js" defer></script>
  <script src="../../assets/js/navbar_admin.js" defer></script>
  <style>
    /* Subject Assignment Container - Minimal & Dark Mode Responsive */
    .subject-assignment-container {
      border: 1px solid var(--bs-border-color);
      border-radius: 0.5rem;
      padding: 0.75rem;
      background-color: var(--bs-body-bg);
      transition: all 0.2s ease;
      max-height: 300px;
      overflow-y: auto;
    }

    .subject-selection-container {
      border: 1px solid var(--bs-border-color);
      border-radius: 0.5rem;
      padding: 0.75rem;
      background-color: var(--bs-body-bg);
      transition: all 0.2s ease;
    }

    .dark-mode .subject-selection-container {
      border-color: var(--bs-border-color);
      background-color: var(--bs-dark);
    }

    /* Subject Select Styling */
    .readonly-multiselect,
    .subject-select {
      border: 1px solid var(--bs-border-color);
      background-color: var(--bs-body-bg);
      color: var(--bs-body-color);
      transition: all 0.2s ease;
      font-size: 0.875rem;
      line-height: 1.4;
      overflow-y: auto !important;
      scrollbar-width: thin;
      min-height: 120px !important;
      max-height: 200px !important;
    }

    .readonly-multiselect::-webkit-scrollbar,
    .subject-select::-webkit-scrollbar {
      width: 6px;
    }

    .readonly-multiselect::-webkit-scrollbar-track,
    .subject-select::-webkit-scrollbar-track {
      background: var(--bs-light);
      border-radius: 3px;
    }

    .readonly-multiselect::-webkit-scrollbar-thumb,
    .subject-select::-webkit-scrollbar-thumb {
      background: var(--bs-secondary);
      border-radius: 3px;
    }

    .readonly-multiselect::-webkit-scrollbar-thumb:hover,
    .subject-select::-webkit-scrollbar-thumb:hover {
      background: var(--bs-secondary-dark);
    }

    .readonly-multiselect:focus,
    .subject-select:focus {
      border-color: var(--bs-primary);
      box-shadow: 0 0 0 0.2rem rgba(var(--bs-primary-rgb), 0.25);
    }

    .readonly-multiselect option,
    .subject-select option {
      padding: 0.375rem 0.5rem;
      background-color: var(--bs-body-bg);
      color: var(--bs-body-color);
      border-bottom: 1px solid var(--bs-border-color);
      font-size: 0.875rem;
      line-height: 1.3;
      white-space: nowrap;
      overflow: hidden;
      text-overflow: ellipsis;
    }

    .readonly-multiselect option:last-child,
    .subject-select option:last-child {
      border-bottom: none;
    }

    .readonly-multiselect option:hover,
    .subject-select option:hover {
      background-color: var(--bs-primary);
      color: white;
    }

    .readonly-multiselect option:checked,
    .subject-select option:checked {
      background-color: var(--bs-primary);
      color: white;
    }

    /* Subject Assignment Buttons */
    .subject-assignment-buttons {
      display: flex;
      gap: 0.5rem;
      flex-wrap: wrap;
      margin-top: 0.75rem;
    }

    .subject-assignment-buttons .btn {
      font-size: 0.8rem;
      padding: 0.375rem 0.75rem;
      border-radius: 0.375rem;
      transition: all 0.2s ease;
      white-space: nowrap;
    }

    /* Dark Mode Specific Adjustments */
    .dark-mode .subject-assignment-container,
    .dark-mode .subject-selection-container {
      background-color: var(--bs-dark);
      border-color: var(--bs-border-color);
    }

    .dark-mode .readonly-multiselect,
    .dark-mode .subject-select {
      background-color: var(--bs-dark);
      border-color: var(--bs-border-color);
      color: var(--bs-body-color);
    }

    .dark-mode .readonly-multiselect option,
    .dark-mode .subject-select option {
      background-color: var(--bs-dark);
      color: var(--bs-body-color);
      border-color: var(--bs-border-color);
    }

    .dark-mode .readonly-multiselect option:hover,
    .dark-mode .subject-select option:hover {
      background-color: var(--bs-primary);
      color: white;
    }

    /* Subject Status Badge */
    .subject-status {
      display: inline-flex;
      align-items: center;
      padding: 0.25rem 0.5rem;
      font-size: 0.8rem;
      border-radius: 0.375rem;
      background-color: var(--bs-success-bg-subtle);
      color: var(--bs-success-text-emphasis);
      margin-bottom: 0.5rem;
      border: 1px solid var(--bs-success-border-subtle);
    }

    .dark-mode .subject-status {
      background-color: var(--bs-success-bg-subtle);
      color: var(--bs-success-text-emphasis);
      border-color: var(--bs-success-border-subtle);
    }

    /* Empty State */
    .subject-empty-state {
      display: flex;
      align-items: center;
      justify-content: center;
      padding: 1rem;
      color: var(--bs-secondary);
      font-style: italic;
      font-size: 0.875rem;
      background-color: var(--bs-light);
      border: 1px dashed var(--bs-border-color);
      border-radius: 0.375rem;
      margin-bottom: 0.5rem;
    }

    .dark-mode .subject-empty-state {
      background-color: var(--bs-dark);
      border-color: var(--bs-border-color);
      color: var(--bs-secondary);
    }

    /* New Subject List Container */
    .subject-list-container {
      border: 1px solid var(--bs-border-color);
      border-radius: 0.375rem;
      background-color: var(--bs-body-bg);
      max-height: 200px;
      overflow-y: auto;
      padding: 0.5rem;
    }

    .dark-mode .subject-list-container {
      border-color: #4a5568;
      background-color: #1a202c;
    }

    .subject-item {
      display: flex;
      align-items: center;
      padding: 0.5rem;
      margin-bottom: 0.25rem;
      background-color: var(--bs-light);
      border-radius: 0.25rem;
      border: 1px solid var(--bs-border-color);
      transition: all 0.2s ease;
    }

    .dark-mode .subject-item {
      background-color: #2d3748;
      border-color: #4a5568;
      color: #ffffff;
    }

    .dark-mode .subject-item {
      background-color: #2d3748;
      border-color: #4a5568;
      color: #ffffff;
    }

    .subject-item:last-child {
      margin-bottom: 0;
    }

    .subject-text {
      font-size: 0.875rem;
      color: var(--bs-body-color);
      flex: 1;
    }

    .dark-mode .subject-text {
      color: #ffffff !important;
    }

    .subject-list-container::-webkit-scrollbar {
      width: 6px;
    }

    .subject-list-container::-webkit-scrollbar-track {
      background: var(--bs-light);
      border-radius: 3px;
    }

    .subject-list-container::-webkit-scrollbar-thumb {
      background: var(--bs-secondary);
      border-radius: 3px;
    }

    .subject-list-container::-webkit-scrollbar-thumb:hover {
      background: var(--bs-secondary-dark);
    }

    /* Remove Subjects Container */
    .remove-subjects-container {
      border: 1px solid var(--bs-border-color);
      border-radius: 0.5rem;
      padding: 0.75rem;
      background-color: var(--bs-body-bg);
      transition: all 0.2s ease;
    }

    .dark-mode .remove-subjects-container {
      border-color: var(--bs-border-color);
      background-color: var(--bs-dark);
    }

    .remove-subjects-select {
      border: 1px solid var(--bs-border-color);
      background-color: var(--bs-body-bg);
      color: var(--bs-body-color);
      transition: all 0.2s ease;
      font-size: 0.875rem;
      line-height: 1.4;
      overflow-y: auto !important;
      scrollbar-width: thin;
    }

    .dark-mode .remove-subjects-select {
      border-color: var(--bs-border-color);
      background-color: var(--bs-dark);
      color: var(--bs-body-color);
    }

    .remove-subjects-select option {
      padding: 0.375rem 0.5rem;
      background-color: var(--bs-body-bg);
      color: var(--bs-body-color);
      border-bottom: 1px solid var(--bs-border-color);
      font-size: 0.875rem;
      line-height: 1.3;
      white-space: nowrap;
      overflow: hidden;
      text-overflow: ellipsis;
    }

    .dark-mode .remove-subjects-select option {
      background-color: var(--bs-dark);
      color: var(--bs-body-color);
      border-color: var(--bs-border-color);
    }

    .remove-subjects-select option:hover {
      background-color: var(--bs-danger);
      color: white;
    }

    .remove-subjects-select option:checked {
      background-color: var(--bs-danger);
      color: white;
    }

    /* Subject Selection Header */
    .subject-selection-header {
      display: flex;
      justify-content: space-between;
      align-items: center;
      flex-wrap: wrap;
      gap: 0.5rem;
      margin-bottom: 0.5rem;
    }

    .subject-selection-actions {
      display: flex;
      gap: 0.25rem;
    }

    .dark-mode .subject-selection-actions .btn {
      border-color: var(--bs-border-color);
      color: var(--bs-body-color);
    }

    .dark-mode .subject-selection-actions .btn:hover {
      background-color: var(--bs-primary);
      border-color: var(--bs-primary);
      color: white;
    }

    .subject-selection-actions .btn {
      font-size: 0.75rem;
      padding: 0.25rem 0.5rem;
      white-space: nowrap;
    }

    @media (max-width: 576px) {
      .subject-selection-header {
        flex-direction: column;
        align-items: stretch;
      }

      .subject-selection-actions {
        justify-content: center;
      }

      .subject-assignment-container,
      .subject-selection-container {
        padding: 0.5rem;
      }

      .readonly-multiselect,
      .subject-select {
        font-size: 0.8rem;
      }

      .readonly-multiselect option,
      .subject-select option {
        font-size: 0.8rem;
        padding: 0.25rem 0.375rem;
      }
    }

    /* Responsive Design */
    @media (max-width: 768px) {
      .subject-assignment-buttons {
        flex-direction: column;
      }

      .subject-assignment-buttons .btn {
        width: 100%;
      }
    }
  </style>
  <style>
    .readonly-multiselect {
      pointer-events: none;
      background-color: inherit !important;
      color: inherit !important;
      opacity: 1 !important;
    }

    .teacher-details-row.collapse:not(.show) {
      display: none;
    }

    .teacher-details-row.collapse.show {
      display: table-row;
      animation: slideDown 0.3s ease;
    }

    @keyframes slideDown {
      from {
        opacity: 0;
        transform: translateY(-10px);
      }

      to {
        opacity: 1;
        transform: translateY(0);
      }
    }

    @media (max-width: 767.98px) {
      .teacher-details-row.collapse.show {
        position: fixed;
        left: 0;
        bottom: 0;
        width: 100vw;
        z-index: 1050;
        background: var(--card-light);
        box-shadow: 0 -4px 24px rgba(0, 0, 0, 0.15);
        border-radius: 16px 16px 0 0;
        animation: slideUpSticky 0.3s ease;
      }

      @keyframes slideUpSticky {
        from {
          opacity: 0;
          transform: translateY(100%);
        }

        to {
          opacity: 1;
          transform: translateY(0);
        }
      }
    }
  </style>
</head>

<body>
  <!-- Include sidebar and navbar -->
  <?php include '../components/sidebar_admin_dashboard.php'; ?>
  <?php include '../components/navbar_admin.php'; ?>

  <!-- Main content -->
  <div class="container-fluid dashboard-container">
    <!-- Page Header -->
    <div class="page-header d-flex justify-content-between align-items-center flex-wrap">
      <div>
        <h2 class="page-title">
          <i data-lucide="users"></i>
          Teacher Management
        </h2>
        <p class="text-muted mb-0">Manage teacher accounts and subject assignments</p>
      </div>
      <div class="d-flex gap-2 flex-wrap">
        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addTeacherModal">
          <i data-lucide="user-plus"></i> Add Teacher
        </button>
        <a href="manage_admin.php" class="btn btn-outline-primary">
          <i data-lucide="shield"></i> Admin Management
        </a>
      </div>
    </div>

    <!-- Statistics Cards -->
    <div class="row g-4 mb-4">
      <div class="col-md-4">
        <div class="stat-card text-center">
          <div class="d-flex justify-content-between align-items-center">
            <div>
              <div class="stat-number"><?= $stats['total_teachers'] ?></div>
              <div class="stat-label">Total Teachers</div>
              <div class="stat-change">
                <i data-lucide="users"></i>
                <span>Faculty members</span>
              </div>
            </div>
            <div class="stats-icon">
              <i data-lucide="users"></i>
            </div>
          </div>
        </div>
      </div>
      <div class="col-md-4">
        <div class="stat-card teachers text-center">
          <div class="d-flex justify-content-between align-items-center">
            <div>
              <div class="stat-number"><?= $stats['active_teachers'] ?></div>
              <div class="stat-label">Active Teachers</div>
              <div class="stat-change">
                <i data-lucide="user-check"></i>
                <span>Currently teaching</span>
              </div>
            </div>
            <div class="stats-icon">
              <i data-lucide="user-check"></i>
            </div>
          </div>
        </div>
      </div>
      <div class="col-md-4">
        <div class="stat-card admins text-center">
          <div class="d-flex justify-content-between align-items-center">
            <div>
              <div class="stat-number"><?= $stats['inactive_teachers'] ?></div>
              <div class="stat-label">Inactive Teachers</div>
              <div class="stat-change">
                <i data-lucide="user-x"></i>
                <span>Suspended accounts</span>
              </div>
            </div>
            <div class="stats-icon">
              <i data-lucide="user-x"></i>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Success/Error Messages -->
    <?php if ($successMsg): ?>
      <div class="alert alert-success alert-dismissible fade show" role="alert">
        <i data-lucide="check-circle" class="me-2"></i>
        <?= htmlspecialchars($successMsg) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
      </div>
    <?php endif; ?>

    <?php if ($errorMsg): ?>
      <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <i data-lucide="alert-circle" class="me-2"></i>
        <?= htmlspecialchars($errorMsg) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
      </div>
    <?php endif; ?>

    <!-- Search and Filter Section -->
    <div class="card mb-4">
      <div class="card-body">
        <h6 class="card-title">
          <i data-lucide="filter"></i>
          Search & Filter Teachers
        </h6>
        <div class="row g-3">
          <div class="col-md-4">
            <label class="form-label">
              <i data-lucide="search"></i>
              Search Teachers
            </label>
            <input id="teacherSearch" type="text" class="form-control" placeholder="Search by name, email, or contact..." />
          </div>
          <div class="col-md-3">
            <label class="form-label">
              <i data-lucide="layers"></i>
              Account Status
            </label>
            <select id="filterStatus" class="form-select">
              <option value="">All Status</option>
              <option value="active">Active</option>
              <option value="inactive">Inactive</option>
            </select>
          </div>
          <div class="col-md-3">
            <label class="form-label">
              <i data-lucide="book-open"></i>
              Subject Count
            </label>
            <select id="filterSubjectCount" class="form-select">
              <option value="">All Teachers</option>
              <option value="0">No Subjects Assigned</option>
              <option value="1">1 Subject</option>
              <option value="2+">2+ Subjects</option>
            </select>
          </div>
          <div class="col-md-2">
            <label class="form-label">&nbsp;</label>
            <button id="clearFilters" class="btn btn-outline-secondary d-block w-100">
              <i data-lucide="x"></i>
              Clear Filters
            </button>
          </div>
        </div>
        <div class="mt-3">
          <small id="resultsCount" class="text-muted"></small>
        </div>
      </div>
    </div>

    <!-- Teachers Table -->
    <div class="card shadow-sm">
      <div class="card-header">
        <h6 class="card-title mb-0">
          <i data-lucide="users"></i>
          Teacher Directory
        </h6>
      </div>
      <div class="table-responsive">
        <table id="teachersTable" class="table table-hover align-middle mb-0">
          <thead class="table-light">
            <tr>
              <th>Profile</th>
              <th>Teacher</th>
              <th>Contact Information</th>
              <th>Subjects</th>
              <th>Date Joined</th>
              <th>Account Status</th>
              <th class="text-center">Actions</th>
            </tr>
          </thead>
          <tbody id="teachersTableBody">
            <?php foreach ($teachers as $teacher): ?>
              <tr class="teacher-row"
                data-name="<?= strtolower(htmlspecialchars($teacher['FullName'])) ?>"
                data-email="<?= strtolower(htmlspecialchars($teacher['Email'])) ?>"
                data-contact="<?= htmlspecialchars($teacher['Contact']) ?>"
                data-status="<?= htmlspecialchars($teacher['Status']) ?>"
                data-subject-count="<?= $teacher['SubjectCount'] ?>">
                <td>
                  <?php if (!empty($teacher['PhotoURL']) && file_exists($teacher['PhotoURL'])): ?>
                    <img src="<?= htmlspecialchars($teacher['PhotoURL']) ?>"
                      alt="<?= htmlspecialchars($teacher['FullName']) ?>"
                      class="teacher-photo">
                  <?php else: ?>
                    <div class="teacher-placeholder">
                      <i data-lucide="user"></i>
                    </div>
                  <?php endif; ?>
                </td>
                <td>
                  <div>
                    <div class="fw-semibold"><?= htmlspecialchars($teacher['FullName']) ?></div>
                  </div>
                </td>
                <td>
                  <div>
                    <div class="mb-1">
                      <i data-lucide="mail" class="me-1 text-muted" style="width: 14px; height: 14px;"></i>
                      <small><?= htmlspecialchars($teacher['Email']) ?></small>
                    </div>
                    <?php if (!empty($teacher['Contact'])): ?>
                      <div>
                        <i data-lucide="phone" class="me-1 text-muted" style="width: 14px; height: 14px;"></i>
                        <small><?= htmlspecialchars($teacher['Contact']) ?></small>
                      </div>
                    <?php endif; ?>
                  </div>
                </td>
                <td>
                  <div class="d-flex align-items-center">
                    <span class="badge bg-primary me-2"><?= $teacher['SubjectCount'] ?></span>
                    <small class="text-muted">
                      <?= $teacher['SubjectCount'] == 1 ? 'Subject' : 'Subjects' ?>
                    </small>
                  </div>
                </td>
                <td>
                  <small class="text-muted">
                    <?= date('M j, Y', strtotime($teacher['CreatedDate'])) ?>
                  </small>
                </td>
                <td>
                  <span class="badge <?= $teacher['Status'] === 'active' ? 'bg-success' : 'bg-danger' ?> status-badge">
                    <?= ucfirst($teacher['Status']) ?>
                  </span>
                </td>
                <td class="text-center">
                  <div class="btn-group" role="group">
                    <button class="btn btn-sm btn-outline-info"
                      data-bs-toggle="modal"
                      data-bs-target="#viewTeacherModal<?= $teacher['TeacherID'] ?>"
                      title="View Details">
                      <i data-lucide="eye"></i>
                    </button>
                    <div class="btn-group" role="group">
                      <button class="btn btn-sm btn-outline-secondary dropdown-toggle" data-bs-toggle="dropdown" title="Change Status">
                        <i data-lucide="settings"></i>
                      </button>
                      <ul class="dropdown-menu">
                        <li>
                          <form method="POST" class="d-inline">
                            <input type="hidden" name="action" value="update_status">
                            <input type="hidden" name="teacher_id" value="<?= $teacher['TeacherID'] ?>">
                            <input type="hidden" name="new_status" value="<?= $teacher['Status'] === 'active' ? 'inactive' : 'active' ?>">
                            <button type="submit" class="dropdown-item" onclick="return confirm('Are you sure you want to change the status?')">
                              <i data-lucide="<?= $teacher['Status'] === 'active' ? 'user-x' : 'user-check' ?>"></i>
                              <?= $teacher['Status'] === 'active' ? 'Deactivate' : 'Activate' ?>
                            </button>
                          </form>
                        </li>
                      </ul>
                    </div>
                  </div>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>

    <!-- View Teacher Modals -->
    <?php foreach ($teachers as $teacher): ?>
      <div class="modal fade" id="viewTeacherModal<?= $teacher['TeacherID'] ?>" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered">
          <div class="modal-content">
            <div class="modal-header">
              <h5 class="modal-title">
                <i data-lucide="user"></i>
                Edit Teacher - <?= htmlspecialchars($teacher['FullName']) ?>
              </h5>
              <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="" enctype="multipart/form-data" class="update-teacher-form">
              <input type="hidden" name="action" value="update_teacher">
              <input type="hidden" name="teacher_id" value="<?= $teacher['TeacherID'] ?>">
              <input type="hidden" name="subjects_updated" value="0" id="subjectsUpdated<?= $teacher['TeacherID'] ?>">
              <div class="modal-body">
                <div class="row">
                  <div class="col-md-4 text-center">
                    <?php if (!empty($teacher['PhotoURL']) && file_exists($teacher['PhotoURL'])): ?>
                      <img src="<?= htmlspecialchars($teacher['PhotoURL']) ?>"
                        alt="<?= htmlspecialchars($teacher['FullName']) ?>"
                        class="teacher-photo-large mb-3">
                    <?php else: ?>
                      <div class="teacher-placeholder-large mb-3">
                        <i data-lucide="user"></i>
                      </div>
                    <?php endif; ?>
                    <div class="mb-3">
                      <label class="form-label">Update Photo</label>
                      <input type="file" name="PhotoFile" class="form-control" accept="image/*">
                    </div>
                  </div>
                  <div class="col-md-8">
                    <div class="row g-3">
                      <div class="col-md-12">
                        <label class="form-label">Full Name</label>
                        <input type="text" name="FullName" class="form-control"
                          value="<?= htmlspecialchars($teacher['FullName']) ?>" required>
                      </div>
                      <div class="col-md-6">
                        <label class="form-label">Email</label>
                        <input type="email" name="Email" class="form-control"
                          value="<?= htmlspecialchars($teacher['Email']) ?>" required>
                      </div>
                      <div class="col-md-6">
                        <label class="form-label">Contact</label>
                        <input type="text" name="Contact" class="form-control"
                          value="<?= htmlspecialchars($teacher['Contact']) ?>" required>
                      </div>
                      <div class="col-md-12">
                        <label class="form-label">Address</label>
                        <textarea name="Address" class="form-control" rows="2" required><?= htmlspecialchars($teacher['Address']) ?></textarea>
                      </div>
                      <?php
                      // Determine default DepartmentID and SemesterID for the teacher (first assigned subject)
                      $defaultDepartmentID = '';
                      $defaultSemesterID = '';
                      if (isset($teacherSubjects[$teacher['TeacherID']]) && count($teacherSubjects[$teacher['TeacherID']]) > 0) {
                        $defaultDepartmentID = $teacherSubjects[$teacher['TeacherID']][0]['DepartmentID'] ?? '';
                        $defaultSemesterID = $teacherSubjects[$teacher['TeacherID']][0]['SemesterID'] ?? '';
                      }
                      ?>
                      <div class="col-md-6">
                        <label class="form-label">Department</label>
                        <select name="DepartmentID" class="form-select">
                          <option value="">Select Department</option>
                          <?php foreach ($departments as $d): ?>
                            <option value="<?= $d['DepartmentID'] ?>" <?= ($defaultDepartmentID == $d['DepartmentID']) ? 'selected' : '' ?>><?= htmlspecialchars($d['DepartmentName']) ?></option>
                          <?php endforeach; ?>
                        </select>
                      </div>
                      <div class="col-md-6">
                        <label class="form-label">Semester</label>
                        <select name="SemesterID" class="form-select">
                          <option value="">Select Semester</option>
                          <?php foreach ($semesters as $s): ?>
                            <option value="<?= $s['SemesterID'] ?>" <?= ($defaultSemesterID == $s['SemesterID']) ? 'selected' : '' ?>>Semester <?= $s['SemesterNumber'] ?></option>
                          <?php endforeach; ?>
                        </select>
                      </div>
                      <div class="col-12">
                        <label class="form-label">Subject Assignment</label>
                        <div class="subject-assignment-container">
                          <?php if (isset($teacherSubjects[$teacher['TeacherID']]) && count($teacherSubjects[$teacher['TeacherID']]) > 0): ?>
                            <div class="subject-status">
                              <i data-lucide="check-circle" class="me-1"></i>
                              <?= count($teacherSubjects[$teacher['TeacherID']]) ?> subject(s) assigned
                            </div>
                            <div class="subject-list-container">
                              <?php foreach ($teacherSubjects[$teacher['TeacherID']] as $assignedSubj): ?>
                                <?php
                                $subjCode = htmlspecialchars($assignedSubj['SubjectCode'] ?? '');
                                $subjName = htmlspecialchars($assignedSubj['SubjectName'] ?? '');
                                $deptName = htmlspecialchars($assignedSubj['DepartmentName'] ?? '');
                                $semNum = htmlspecialchars($assignedSubj['SemesterNumber'] ?? '');
                                ?>
                                <div class="subject-item">
                                  <span class="subject-text"><?= $subjCode ?> - <?= $subjName ?> (<?= $deptName ?>, Sem <?= $semNum ?>)</span>
                                </div>
                              <?php endforeach; ?>
                            </div>
                          <?php else: ?>
                            <div class="subject-empty-state">
                              <i data-lucide="book-open" class="me-2"></i>
                              No subjects assigned
                            </div>
                            <div class="subject-list-container">
                            </div>
                          <?php endif; ?>

                          <div class="subject-assignment-buttons">
                            <button type="button" class="btn btn-outline-primary btn-sm" data-bs-toggle="modal" data-bs-target="#assignSubjectsModal<?= $teacher['TeacherID'] ?>">
                              <i data-lucide="plus"></i> Manage Subjects
                            </button>
                          </div>
                        </div>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
              <div class="modal-footer d-flex justify-content-between">
                <div>
                  <button type="button"
                    class="btn <?= $teacher['Status'] === 'active' ? 'btn-danger' : 'btn-success' ?>"
                    onclick="changeTeacherStatus(<?= $teacher['TeacherID'] ?>, '<?= $teacher['Status'] === 'active' ? 'inactive' : 'active' ?>')">
                    <i data-lucide="<?= $teacher['Status'] === 'active' ? 'user-x' : 'user-check' ?>" class="me-1"></i>
                    <?= $teacher['Status'] === 'active' ? 'Deactivate Account' : 'Activate Account' ?>
                  </button>
                </div>
                <div>
                  <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                  <button type="submit" class="btn btn-primary update-btn">
                    <i data-lucide="save" class="me-1"></i>
                    Update Changes
                  </button>
                </div>
              </div>
            </form>
          </div>
        </div>
      </div>
      <!-- Add the assign subjects modal for each teacher (at the end of the edit modal): -->
      <div class="modal fade" id="assignSubjectsModal<?= $teacher['TeacherID'] ?>" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg">
          <div class="modal-content">
            <form method="POST" action="" class="assign-subjects-form">
              <input type="hidden" name="action" value="update_subjects">
              <input type="hidden" name="teacher_id" value="<?= $teacher['TeacherID'] ?>">
              <div class="modal-header">
                <h5 class="modal-title">Assign Subjects to <?= htmlspecialchars($teacher['FullName']) ?></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
              </div>
              <div class="modal-body">
                <div class="mb-3">
                  <label class="form-label">Department</label>
                  <select name="DepartmentID" class="form-select department-select" required onchange="filterSemesters<?= $teacher['TeacherID'] ?>()">
                    <option value="">Select Department</option>
                    <?php foreach ($departments as $d): ?>
                      <option value="<?= $d['DepartmentID'] ?>"><?= htmlspecialchars($d['DepartmentName']) ?></option>
                    <?php endforeach; ?>
                  </select>
                </div>
                <div class="mb-3">
                  <label class="form-label">Semester</label>
                  <select name="SemesterID" class="form-select semester-select" required onchange="filterSubjects<?= $teacher['TeacherID'] ?>()">
                    <option value="">Select Semester</option>
                    <?php foreach ($semesters as $s): ?>
                      <option value="<?= $s['SemesterID'] ?>">Semester <?= $s['SemesterNumber'] ?></option>
                    <?php endforeach; ?>
                  </select>
                </div>
                <div class="mb-3">
                  <label class="form-label">Subjects</label>
                  <div class="subject-selection-container">
                    <div class="subject-selection-header mb-2">
                      <small class="form-text text-muted">Hold Ctrl (Windows) or Cmd (Mac) to select multiple subjects.</small>
                      <div class="subject-selection-actions">
                        <button type="button" class="btn btn-outline-secondary btn-sm" onclick="console.log('Select All button clicked'); selectAllSubjects(<?= $teacher['TeacherID'] ?>);">
                          <i data-lucide="check-square"></i> Select All
                        </button>
                        <button type="button" class="btn btn-outline-secondary btn-sm ms-1" onclick="console.log('Clear All button clicked'); deselectAllSubjects(<?= $teacher['TeacherID'] ?>);">
                          <i data-lucide="square"></i> Clear All
                        </button>

                        <?php if (isset($teacherSubjects[$teacher['TeacherID']]) && count($teacherSubjects[$teacher['TeacherID']]) > 0): ?>
                          <button type="button" class="btn btn-outline-danger btn-sm ms-1" onclick="showRemoveSubjectsModal(<?= $teacher['TeacherID'] ?>)">
                            <i data-lucide="trash-2"></i> Remove Subjects
                          </button>
                        <?php endif; ?>
                      </div>
                    </div>
                    <select name="SubjectID[]" class="form-select subject-select" multiple required style="min-height: 200px; max-height: 300px; overflow-y: auto;">
                      <!-- Options will be populated by JS -->
                    </select>
                    <small class="form-text text-muted mt-2 d-block">Selected subjects will be assigned to the teacher.</small>
                  </div>
                </div>
              </div>
              <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-primary">Assign</button>
              </div>
            </form>
          </div>
        </div>
      </div>

      <!-- Remove Subjects Modal -->
      <div class="modal fade" id="removeSubjectsModal<?= $teacher['TeacherID'] ?>" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg">
          <div class="modal-content">
            <form method="POST" action="" class="remove-subjects-form">
              <input type="hidden" name="action" value="remove_subjects">
              <input type="hidden" name="teacher_id" value="<?= $teacher['TeacherID'] ?>">
              <div class="modal-header">
                <h5 class="modal-title">
                  <i data-lucide="trash-2" class="me-2"></i>
                  Remove Subjects from <?= htmlspecialchars($teacher['FullName']) ?>
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
              </div>
              <div class="modal-body">
                <div class="alert alert-warning">
                  <i data-lucide="alert-triangle" class="me-2"></i>
                  Select the subjects you want to remove from this teacher.
                </div>
                <div class="mb-3">
                  <label class="form-label">Currently Assigned Subjects</label>
                  <div class="remove-subjects-container">
                    <select name="SubjectsToRemove[]" class="form-select remove-subjects-select" multiple style="min-height: 200px; max-height: 300px; overflow-y: auto;">
                      <?php if (isset($teacherSubjects[$teacher['TeacherID']]) && count($teacherSubjects[$teacher['TeacherID']]) > 0): ?>
                        <?php foreach ($teacherSubjects[$teacher['TeacherID']] as $assignedSubj): ?>
                          <?php
                          $subjId = $assignedSubj['SubjectID'];
                          $subjCode = htmlspecialchars($assignedSubj['SubjectCode'] ?? '');
                          $subjName = htmlspecialchars($assignedSubj['SubjectName'] ?? '');
                          $deptName = htmlspecialchars($assignedSubj['DepartmentName'] ?? '');
                          $semNum = htmlspecialchars($assignedSubj['SemesterNumber'] ?? '');
                          ?>
                          <option value="<?= $subjId ?>"><?= $subjCode ?> - <?= $subjName ?> (<?= $deptName ?>, Sem <?= $semNum ?>)</option>
                        <?php endforeach; ?>
                      <?php endif; ?>
                    </select>
                    <div class="mt-2">
                      <button type="button" class="btn btn-outline-secondary btn-sm" onclick="selectAllRemoveSubjects(<?= $teacher['TeacherID'] ?>)">
                        <i data-lucide="check-square"></i> Select All
                      </button>
                      <button type="button" class="btn btn-outline-secondary btn-sm ms-1" onclick="deselectAllRemoveSubjects(<?= $teacher['TeacherID'] ?>)">
                        <i data-lucide="square"></i> Clear All
                      </button>
                    </div>
                  </div>
                </div>
              </div>
              <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-danger">
                  <i data-lucide="trash-2" class="me-1"></i> Remove Selected
                </button>
              </div>
            </form>
          </div>
        </div>
      </div>
      <script>
        // JS for filtering semesters and subjects in the assign subjects modal
        const allSubjects<?= $teacher['TeacherID'] ?> = <?php echo json_encode($subjects); ?>;
        const teacherAssignedSubjects<?= $teacher['TeacherID'] ?> = <?php echo json_encode(
                                                                      isset($teacherSubjects[$teacher['TeacherID']]) ? array_column($teacherSubjects[$teacher['TeacherID']], 'SubjectID') : []
                                                                    ); ?>;

        // Build a map of SubjectID => TeacherID for all assigned subjects
        const assignedSubjectToTeacher<?= $teacher['TeacherID'] ?> = <?php echo json_encode($assignedSubjectToTeacher); ?>;

        function filterSemesters<?= $teacher['TeacherID'] ?>() {
          // Optionally, you can filter semesters based on department if needed
          filterSubjects<?= $teacher['TeacherID'] ?>();
        }

        function filterSubjects<?= $teacher['TeacherID'] ?>() {
          const deptSelect = document.querySelector('#assignSubjectsModal<?= $teacher['TeacherID'] ?> .department-select');
          const semSelect = document.querySelector('#assignSubjectsModal<?= $teacher['TeacherID'] ?> .semester-select');
          const subjSelect = document.querySelector('#assignSubjectsModal<?= $teacher['TeacherID'] ?> .subject-select');
          const deptId = deptSelect.value;
          const semId = semSelect.value;
          subjSelect.innerHTML = '';
          if (!deptId || !semId) return;
          allSubjects<?= $teacher['TeacherID'] ?>.forEach(sub => {
            // Only show if not assigned to another teacher, or already assigned to this teacher
            if (sub.DepartmentID == deptId && sub.SemesterID == semId &&
              (!assignedSubjectToTeacher<?= $teacher['TeacherID'] ?>[sub.SubjectID] || assignedSubjectToTeacher<?= $teacher['TeacherID'] ?>[sub.SubjectID] == <?= $teacher['TeacherID'] ?>)) {
              const opt = document.createElement('option');
              opt.value = sub.SubjectID;
              opt.textContent = `${sub.SubjectCode} - ${sub.SubjectName} (${sub.DepartmentName}, Sem ${sub.SemesterNumber})`;
              if (teacherAssignedSubjects<?= $teacher['TeacherID'] ?>.includes(sub.SubjectID)) {
                opt.selected = true;
              }
              subjSelect.appendChild(opt);
            }
          });
        }
        document.addEventListener('DOMContentLoaded', () => {
          // Reset modal fields on open
          const modal = document.getElementById('assignSubjectsModal<?= $teacher['TeacherID'] ?>');
          modal.addEventListener('show.bs.modal', () => {
            console.log('Assign subjects modal opened for teacher <?= $teacher['TeacherID'] ?>');
            modal.querySelector('.department-select').value = '';
            modal.querySelector('.semester-select').value = '';
            modal.querySelector('.subject-select').innerHTML = '';
          });

          // Add event listener to subject select in edit form
          const subjectSelect = document.getElementById('subjectSelect<?= $teacher['TeacherID'] ?>');
          if (subjectSelect) {
            subjectSelect.addEventListener('change', () => {
              markSubjectsUpdated(<?= $teacher['TeacherID'] ?>);
            });
          }
        });

        // Function to select all available subjects
        function selectAllSubjects(teacherId) {
          console.log(`selectAllSubjects called for teacher ${teacherId}`);
          const modal = document.getElementById(`assignSubjectsModal${teacherId}`);
          if (!modal) {
            console.error(`Modal not found for teacher ${teacherId}`);
            return;
          }
          const subjectSelect = modal.querySelector('.subject-select');
          if (subjectSelect && subjectSelect.options.length > 0) {
            console.log(`Found ${subjectSelect.options.length} options to select`);
            for (let i = 0; i < subjectSelect.options.length; i++) {
              subjectSelect.options[i].selected = true;
              console.log(`Selected option ${i}: ${subjectSelect.options[i].textContent}`);
            }
            console.log(`Successfully selected all ${subjectSelect.options.length} subjects for teacher ${teacherId}`);
            // Trigger change event to update any listeners
            const changeEvent = new Event('change', {
              bubbles: true
            });
            subjectSelect.dispatchEvent(changeEvent);
          } else {
            console.error(`Subject select not found or no options available for teacher ${teacherId}`);
          }
        }

        // Function to deselect all subjects
        function deselectAllSubjects(teacherId) {
          console.log(`deselectAllSubjects called for teacher ${teacherId}`);
          const modal = document.getElementById(`assignSubjectsModal${teacherId}`);
          if (!modal) {
            console.error(`Modal not found for teacher ${teacherId}`);
            return;
          }
          const subjectSelect = modal.querySelector('.subject-select');
          if (subjectSelect) {
            console.log(`Found ${subjectSelect.options.length} options to deselect`);
            for (let i = 0; i < subjectSelect.options.length; i++) {
              subjectSelect.options[i].selected = false;
              console.log(`Deselected option ${i}: ${subjectSelect.options[i].textContent}`);
            }
            console.log(`Successfully cleared all subjects for teacher ${teacherId}`);
            // Trigger change event to update any listeners
            const changeEvent = new Event('change', {
              bubbles: true
            });
            subjectSelect.dispatchEvent(changeEvent);
          }
        }



        // Function to mark subjects as updated when changed
        function markSubjectsUpdated(teacherId) {
          document.getElementById(`subjectsUpdated${teacherId}`).value = '1';
        }

        // Function to show remove subjects modal
        function showRemoveSubjectsModal(teacherId) {
          const assignModal = document.getElementById(`assignSubjectsModal${teacherId}`);
          const removeModal = document.getElementById(`removeSubjectsModal${teacherId}`);

          // Close the assign modal first
          const assignModalInstance = bootstrap.Modal.getInstance(assignModal);
          if (assignModalInstance) {
            assignModalInstance.hide();
          }

          // Show the remove modal
          const removeModalInstance = new bootstrap.Modal(removeModal);
          removeModalInstance.show();
        }

        // Function to select all subjects in remove modal
        function selectAllRemoveSubjects(teacherId) {
          const modal = document.getElementById(`removeSubjectsModal${teacherId}`);
          if (!modal) {
            console.error(`Remove modal not found for teacher ${teacherId}`);
            return;
          }
          const subjectSelect = modal.querySelector('.remove-subjects-select');
          if (subjectSelect && subjectSelect.options.length > 0) {
            Array.from(subjectSelect.options).forEach(option => {
              option.selected = true;
            });
            console.log(`Selected all ${subjectSelect.options.length} subjects for removal from teacher ${teacherId}`);
          }
        }

        // Function to deselect all subjects in remove modal
        function deselectAllRemoveSubjects(teacherId) {
          const modal = document.getElementById(`removeSubjectsModal${teacherId}`);
          if (!modal) {
            console.error(`Remove modal not found for teacher ${teacherId}`);
            return;
          }
          const subjectSelect = modal.querySelector('.remove-subjects-select');
          if (subjectSelect) {
            Array.from(subjectSelect.options).forEach(option => {
              option.selected = false;
            });
            console.log(`Cleared all subjects for removal from teacher ${teacherId}`);
          }
        }

        // Test function to verify select all works
        function testSelectAll(teacherId) {
          console.log('=== TESTING SELECT ALL ===');
          const modal = document.getElementById(`assignSubjectsModal${teacherId}`);
          console.log('Modal found:', !!modal);
          if (modal) {
            const subjectSelect = modal.querySelector('.subject-select');
            console.log('Subject select found:', !!subjectSelect);
            console.log('Number of options:', subjectSelect ? subjectSelect.options.length : 0);
            if (subjectSelect && subjectSelect.options.length > 0) {
              console.log('First option:', subjectSelect.options[0].textContent);
              subjectSelect.options[0].selected = true;
              console.log('First option selected:', subjectSelect.options[0].selected);
            }
          }
          console.log('=== END TEST ===');
        }
      </script>
    <?php endforeach; ?>

    <!-- Add Teacher Modal -->
    <div class="modal fade" id="addTeacherModal" tabindex="-1" data-bs-backdrop="static" data-bs-keyboard="false">
      <div class="modal-dialog modal-lg modal-dialog-centered">
        <form class="modal-content" id="teacherform" method="POST" enctype="multipart/form-data">
          <input type="hidden" name="action" value="add_teacher">
          <div class="modal-header">
            <h5 class="modal-title">
              <i data-lucide="user-plus"></i>
              Add New Teacher
            </h5>
            <button type="button" class="btn-close btn-close-red" data-bs-dismiss="modal"></button>
          </div>
          <div class="modal-body">
            <div class="row g-3">
              <!-- Personal Information -->
              <div class="col-12">
                <h6 class="text-primary border-bottom pb-2"><i data-lucide="user"></i> Personal Information</h6>
              </div>

              <div class="col-md-6">
                <label class="form-label">
                  Full Name <span class="required-field">*</span>
                </label>
                <input name="FullName" type="text" class="form-control" required placeholder="Enter full name"
                  value="<?php echo htmlspecialchars($_POST['FullName'] ?? ''); ?>" />
                <span class="error text-danger"><?php echo $errors['FullName'] ?? ''; ?></span>
              </div>

              <div class="col-md-6">
                <label class="form-label">
                  Email Address <span class="required-field">*</span>
                </label>
                <input name="Email" type="email" class="form-control" required placeholder="teacher@example.com"
                  value="<?php echo htmlspecialchars($_POST['Email'] ?? ''); ?>" />
                <span class="error text-danger"><?php echo $errors['Email'] ?? ''; ?></span>
              </div>

              <div class="col-md-6">
                <label class="form-label">Contact Number</label>
                <input name="Contact" type="tel" class="form-control" placeholder="98xxxxxxxx"
                  value="<?php echo htmlspecialchars($_POST['Contact'] ?? ''); ?>" />
                <span class="error text-danger"><?php echo $errors['Contact'] ?? ''; ?></span>

              </div>

              <div class="col-md-6">
                <label class="form-label">Employment Type</label>
                <select name="Type" class="form-select">
                  <option value="full-time">Full-Time</option>
                  <option value="part-time">Part-Time</option>
                  <option value="contract">Contract</option>
                </select>
                <span class="error text-danger"><?php echo $errors['Type'] ?? ''; ?></span>
              </div>

              <div class="col-12">
                <label class="form-label">Address</label>
                <textarea name="Address" class="form-control" rows="2" placeholder="Enter full address"></textarea>
                <span class="error text-danger"><?php echo $errors['Address'] ?? ''; ?></span>
              </div>

              <div class="col-12">
                <label class="form-label">
                  Profile Photo
                  <small class="text-muted">(Optional, max 5MB)</small>
                </label>
                <input name="PhotoFile" type="file" class="form-control" accept="image/*" />
                <small class="form-text text-muted">JPG, PNG, GIF (Max 5MB)</small>
                <span class="error text-danger"><?php echo $errors['Photo'] ?? ''; ?></span>
              </div>

              <div class="col-12 mt-4">
                <h6 class="text-primary border-bottom pb-2"><i data-lucide="graduation-cap"></i> Academic Information</h6>
              </div>
              <div class="col-md-6">
                <label class="form-label">Department</label>
                <select name="DepartmentID" id="departmentSelect" class="form-select">
                  <option value="">Select Department</option>
                  <?php foreach ($departments as $d): ?>
                    <option value="<?= $d['DepartmentID'] ?>"><?= htmlspecialchars($d['DepartmentName']) ?></option>
                  <?php endforeach; ?>
                </select>
                <span class="error text-danger"><?php echo $errors['DepartmentID'] ?? ''; ?></span>
              </div>
              <div class="col-md-6">
                <label class="form-label">Semester</label>
                <select name="SemesterID" id="semesterSelect" class="form-select">
                  <option value="">Select Semester</option>
                  <?php foreach ($semesters as $s): ?>
                    <option value="<?= $s['SemesterID'] ?>">Semester <?= $s['SemesterNumber'] ?></option>
                  <?php endforeach; ?>
                </select>
                <span class="error text-danger"><?php echo $errors['SemesterID'] ?? ''; ?></span>
              </div>
              <div class="col-12">
                <label class="form-label">Subject Assignment</label>
                <select name="SubjectID[]" id="subjectSelect" class="form-select" multiple required>
                  <option value="">Select Department & Semester First</option>
                </select>
                <span class="error text-danger"><?php echo $errors['SubjectID'] ?? ''; ?></span>
                <small class="form-text text-muted"><br>
                  You can assign additional subjects later</small>
              </div>

              <!-- Account Information -->
              <div class="col-12 mt-4">
                <h6 class="text-primary border-bottom pb-2"><i data-lucide="lock"></i> Account Information</h6>
              </div>
              <div class="col-md-6">
                <label class="form-label">
                  Password <span class="required-field">*</span>
                </label>
                <input name="Password" type="password" class="form-control" required />
                <span class="error text-danger"><?php echo $errors['Password'] ?? ''; ?></span>
              </div>
              <div class="col-md-6">
                <label class="form-label">
                  Confirm Password <span class="required-field">*</span>
                </label>
                <input name="ConfirmPassword" type="password" class="form-control" required />
                <span class="error text-danger"><?php echo $errors['ConfirmPassword'] ?? ''; ?></span>
              </div>
            </div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
              <i data-lucide="x" class="me-1"></i>
              Cancel
            </button>
            <button type="submit" class="btn btn-primary">
              <i data-lucide="save"></i>
              Create Teacher
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>

  <!-- Scripts -->
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
  <script>
    // Initialize Lucide icons
    lucide.createIcons();

    // Subject filtering logic for add teacher modal
    const subjectData = <?= json_encode($subjects) ?>;
    const departmentSelect = document.getElementById("departmentSelect");
    const semesterSelect = document.getElementById("semesterSelect");
    const subjectSelect = document.getElementById("subjectSelect");

    function filterSubjects() {
      const dept = departmentSelect.value;
      const sem = semesterSelect.value;
      subjectSelect.innerHTML = '<option value="">Select Subject</option>';

      if (dept && sem) {
        const filteredSubjects = subjectData.filter(sub =>
          parseInt(sub.DepartmentID) === parseInt(dept) &&
          parseInt(sub.SemesterID) === parseInt(sem)
        );

        filteredSubjects.forEach(sub => {
          subjectSelect.innerHTML +=
            `<option value="${sub.SubjectID}">${sub.SubjectCode} - ${sub.SubjectName}</option>`;
        });

        if (filteredSubjects.length === 0) {
          subjectSelect.innerHTML = '<option value="">No subjects available</option>';
        }
      } else {
        subjectSelect.innerHTML = '<option value="">Select Department & Semester First</option>';
      }
    }

    departmentSelect.addEventListener("change", filterSubjects);
    semesterSelect.addEventListener("change", filterSubjects);

    // Search and filter functionality
    const searchInput = document.getElementById('teacherSearch');
    const statusFilter = document.getElementById('filterStatus');
    const subjectCountFilter = document.getElementById('filterSubjectCount');
    const clearFiltersBtn = document.getElementById('clearFilters');
    const resultsCount = document.getElementById('resultsCount');
    const teacherRows = document.querySelectorAll('.teacher-row');

    function updateResultsCount() {
      const visibleRows = document.querySelectorAll('.teacher-row:not([style*="display: none"])').length;
      const totalRows = teacherRows.length;
      resultsCount.textContent = `Showing ${visibleRows} of ${totalRows} teachers`;
    }

    function filterTeachers() {
      const searchTerm = searchInput.value.toLowerCase();
      const statusFilterValue = statusFilter.value.toLowerCase();
      const subjectCountFilterValue = subjectCountFilter.value;

      teacherRows.forEach(row => {
        const name = row.dataset.name || '';
        const email = row.dataset.email || '';
        const contact = row.dataset.contact || '';
        const status = row.dataset.status || '';
        const subjectCount = parseInt(row.dataset.subjectCount) || 0;

        let showRow = true;

        // Search filter
        if (searchTerm) {
          const searchMatch = name.includes(searchTerm) ||
            email.includes(searchTerm) ||
            contact.includes(searchTerm);
          if (!searchMatch) showRow = false;
        }

        // Status filter
        if (statusFilterValue && status !== statusFilterValue) {
          showRow = false;
        }

        // Subject count filter
        if (subjectCountFilterValue) {
          if (subjectCountFilterValue === '0' && subjectCount !== 0) showRow = false;
          if (subjectCountFilterValue === '1' && subjectCount !== 1) showRow = false;
          if (subjectCountFilterValue === '2+' && subjectCount < 2) showRow = false;
        }

        row.style.display = showRow ? '' : 'none';
      });

      updateResultsCount();
    }

    // Event listeners for filters
    searchInput.addEventListener('input', filterTeachers);
    statusFilter.addEventListener('change', filterTeachers);
    subjectCountFilter.addEventListener('change', filterTeachers);

    // Clear filters
    clearFiltersBtn.addEventListener('click', () => {
      searchInput.value = '';
      statusFilter.value = '';
      subjectCountFilter.value = '';
      filterTeachers();
    });

    // Reset add teacher modal on close
    const addTeacherModal = document.getElementById('addTeacherModal');

    addTeacherModal.addEventListener('hidden.bs.modal', () => {
      console.log('Here');
      document.getElementById('teacherform').reset();
      subjectSelect.innerHTML = '<option value="">Select Department & Semester First</option>';
    });

    // Password confirmation validation
    const passwordInput = document.querySelector('input[name="Password"]');
    const confirmPasswordInput = document.querySelector('input[name="ConfirmPassword"]');

    function validatePasswordMatch() {
      if (confirmPasswordInput.value && passwordInput.value !== confirmPasswordInput.value) {
        confirmPasswordInput.setCustomValidity('Passwords do not match');
      } else {
        confirmPasswordInput.setCustomValidity('');
      }
    }

    passwordInput.addEventListener('input', validatePasswordMatch);
    confirmPasswordInput.addEventListener('input', validatePasswordMatch);

    // Initialize results count
    updateResultsCount();

    // Auto-dismiss alerts after 5 seconds
    setTimeout(() => {
      const alerts = document.querySelectorAll('.alert');
      alerts.forEach(alert => {
        const bsAlert = new bootstrap.Alert(alert);
        bsAlert.close();
      });
    }, 5000);
  </script>
  <form id="teacherStatusForm" method="POST" style="display:none;">
    <input type="hidden" name="action" value="update_status">
    <input type="hidden" name="teacher_id" id="statusTeacherId" value="">
    <input type="hidden" name="new_status" id="statusTeacherNewStatus" value="">
  </form>
  <script>
    function changeTeacherStatus(teacherId, newStatus) {
      if (confirm('Are you sure you want to ' + (newStatus === 'active' ? 'activate' : 'deactivate') + ' this teacher?')) {
        document.getElementById('statusTeacherId').value = teacherId;
        document.getElementById('statusTeacherNewStatus').value = newStatus;
        document.getElementById('teacherStatusForm').submit();
      }
    }
  </script>
</body>

</html>

<?php if (!empty($errors)): ?>
  <script>
    var myModal = new bootstrap.Modal(document.getElementById('addTeacherModal'));
    window.addEventListener('load', () => {
      myModal.show();
    });
  </script>
<?php endif; ?>