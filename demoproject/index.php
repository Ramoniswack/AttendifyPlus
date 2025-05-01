<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" type="text/css" href="style.css">
    <title>Attentify+</title>
</head>
<body>
    
    <div class="right-side"></div>
    <div class="container">
    <img src="logo.png" class="logo" alt="Logo">
        <h1>LOGIN</h1>

<form action="#" method="POST" autocomplete="off">
        <div class="form">
           <input type="text" name="username" class="textfield" placeholder="Username">
           <input type="password" name="password" class="textfield" placeholder="Password">  
        <div class="forgetpassword"><a href="#" class="link" onclick="message()"> Forget Password ?</a></div>
        <input type="submit" name="login" value="Login" class="btn">
        <div class="signup">New Member? <a href="#" class="link">SignUp Here</a> </div>
    </div>
    </div>
</form>

<script>
    function message(){
    alert("Remember Password");
    }
    </script>
</body>
</html>


<?php
include("connection.php");
if(isset($_post['login'])){
    $username=$_post['username'];
    $pwd=$_post['password'];
    $query="SELECT * FROM form WHERE email='$username' && password='$pwd'";
    $data=mysqli_query($conn, $query);
    
    $total=mysqli_num_rows($data);
    //echo $total;

    if($total == 1){
      echo "login successful";
    }
    else{
        echo "login failed";
    }
}
?>
