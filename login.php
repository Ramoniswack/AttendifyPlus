<?php
session_start();

require_once "config/db_config.php"; // path based on folder structure
require_once "helpers/helpers.php";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $email = sanitize($_POST['email']); // sanitize($email)  using helper
    $password = sanitize($_POST['password']);

    // echo "Sanitized Email: $email <br>";
    // echo "Sanitized password: $password<br>";

    $stmt = $conn->prepare("SELECT UserID, Username, Password, Role FROM login_tbl WHERE Email = ? AND Status = 'active'");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $stmt->store_result();

    if ($stmt->num_rows === 1) {
        $stmt->bind_result($userID, $username, $dbPassword, $role);
        $stmt->fetch();

        if ($password === $dbPassword) {
            $_SESSION['UserID'] = $userID;
            $_SESSION['Username'] = $username;
            $_SESSION['Role'] = $role;
            header("Location: views/dashboard.php");
            exit;
            echo " Login success!";
          
        } else {
            echo " Incorrect password.";
        }
    } else {
        echo " Invalid email or inactive account.";
    }

    $stmt->close();
}
$conn->close();
