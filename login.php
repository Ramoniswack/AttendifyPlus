<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Login Page</title>

    <!-- Bootstrap 5 CDN -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">

    <!-- Font Awesome CDN for icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">

    <!-- Custom CSS -->
    <link rel="stylesheet" href="assets/css/login.css">
</head>
<body class="bg-main d-flex justify-content-center align-items-center vh-100">

    <div class="card login-card p-4">
        <h2 class="text-center text-white mb-4">Login</h2>
        <form action="login_handler.php" method="post">
            <div class="mb-3 input-group">
                <span class="input-group-text"><i class="fa fa-user"></i></span>
                <input type="text" name="username" class="form-control input-style" placeholder="Username" required>
            </div>
            <div class="mb-3 input-group">
                <span class="input-group-text"><i class="fa fa-lock"></i></span>
                <input type="password" name="password" class="form-control input-style" placeholder="Password" required>
            </div>
            <div class="mb-3 text-end">
                <a href="#" class="text-decoration-none forgot-link">Forgot Password?</a>
            </div>
            <div class="d-grid">
                <button type="submit" class="btn btn-login">Login</button>
            </div>
        </form>
    </div>

</body>
</html>
