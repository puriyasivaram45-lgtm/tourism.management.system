<?php
session_start();
if(!defined('PUBLIC_ACTIONS_HANDLED') && isset($_POST['signin']))
{
$email=$_POST['email'];
if(!csrf_verify()) {
echo "<script>alert('Invalid form token. Please try again.');</script>";
} else {
$password=$_POST['password'];
$sql ="SELECT EmailId,Password FROM tblusers WHERE EmailId=:email LIMIT 1";
$query= $dbh -> prepare($sql);
$query-> bindParam(':email', $email, PDO::PARAM_STR);
$query-> execute();
$result=$query->fetch(PDO::FETCH_OBJ);
if($result && app_password_verify($password, $result->Password))
{
if(app_password_needs_rehash($result->Password)) {
$newHash=app_password_hash($password);
$update=$dbh->prepare("UPDATE tblusers SET Password=:password WHERE EmailId=:email");
$update->bindParam(':password',$newHash,PDO::PARAM_STR);
$update->bindParam(':email',$email,PDO::PARAM_STR);
$update->execute();
}
$_SESSION['login']=$result->EmailId;
echo "<script type='text/javascript'> document.location = 'package-list.php'; </script>";
} else{
	
		echo "<script>alert('Invalid Details');</script>";

}
}

}

?>

<div class="modal fade" id="myModal4" tabindex="-1" role="dialog" aria-labelledby="myModalLabel">
				<div class="modal-dialog" role="document">
					<div class="modal-content modal-info">
						<div class="modal-header">
							<button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">×</span></button>						
						</div>
						<div class="modal-body modal-spa">
							<div class="login-grids">
								<div class="login">
										<div class="login-right">
											<?php if(!empty($_SESSION['login_error'])) { ?><p style="color:red;"><?php echo e($_SESSION['login_error']); unset($_SESSION['login_error']); ?></p><?php } ?>
											<form method="post">
												<?php echo csrf_field(); ?>
												<h3>Signin with your account </h3>
	<input type="text" name="email" id="email" placeholder="Enter your Email"  required="">	
	<input type="password" name="password" id="password" placeholder="Password" value="" required="">	
											<h4><a href="forgot-password.php">Forgot password</a></h4>
											
											<input type="submit" name="signin" value="SIGNIN">
										</form>
									</div>
									<div class="clearfix"></div>								
								</div>
								<p>By logging in you agree to our <a href="page.php?type=terms">Terms and Conditions</a> and <a href="page.php?type=privacy">Privacy Policy</a></p>
							</div>
						</div>
					</div>
				</div>
			</div>
