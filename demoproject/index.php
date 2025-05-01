<!-- <?php
session_start();

if($_SERVER["REQUEST_METHOD"]=="POST"){
  
  $con=mysqli_connect("localhost","root","", "attendifyplus_db");

  if(!$con){
      die("Connection Failed:".mysqli_connect_error());
  }


  

  $Name=$_POST['Name'];
  $Email=$_POST['email'];
  $Password=$_POST['password'];
  $Role=$_POST['role'];
  $Status=$_POST['status'];


  $query = "SELECT * FROM login_tbl WHERE Email='$Email' AND Status='active'";

  $result = mysqli_query($con, $query);
  // sn name     email    password   role  status
  // 1 Namrata a@mail.com pass      admin active
  $user = mysqli_fetch_assoc($result);


  if( $user && $Password === $user['Password'])
  {
      $_SESSION['user_id'] = $user['UserID'];
      $_SESSION['role']= $user['role'];

     
      if($user['role'] == 'student')
      {
          header("Location: student_dashboard.php");
      }
      else{
          header("Location: teacher_dashboard.php");
      }
      exit;

  }
  else{
      echo "Invalid login credentials.";
  }


}


?>
















 -->
