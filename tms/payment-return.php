<?php
session_start();
error_reporting(0);
include('includes/config.php');
include('includes/payments.php');

if(strlen($_SESSION['login'])==0) {
header('location:index.php');
exit;
}

$merchantOrderId = isset($_GET['merchantOrderId']) ? $_GET['merchantOrderId'] : '';
$userEmail = $_SESSION['login'];
$status = 'unknown';
$message = '';
$payment = null;

try {
    if($merchantOrderId === '') {
        throw new RuntimeException('Payment reference is missing.');
    }

    ensure_payment_table($dbh);
    $paymentQuery = $dbh->prepare("SELECT * FROM tblpayments WHERE MerchantOrderId=:merchantOrderId AND UserEmail=:userEmail LIMIT 1");
    $paymentQuery->bindParam(':merchantOrderId', $merchantOrderId, PDO::PARAM_STR);
    $paymentQuery->bindParam(':userEmail', $userEmail, PDO::PARAM_STR);
    $paymentQuery->execute();
    $payment = $paymentQuery->fetch(PDO::FETCH_OBJ);

    if(!$payment) {
        throw new RuntimeException('Payment record was not found.');
    }

    $gatewayStatus = phonepe_order_status($merchantOrderId);
    $status = normalize_payment_status($gatewayStatus);
    $statusJson = json_encode($gatewayStatus);

    $update = $dbh->prepare("UPDATE tblpayments SET Status=:status,StatusPayload=:payload WHERE MerchantOrderId=:merchantOrderId");
    $update->bindParam(':status', $status, PDO::PARAM_STR);
    $update->bindParam(':payload', $statusJson, PDO::PARAM_STR);
    $update->bindParam(':merchantOrderId', $merchantOrderId, PDO::PARAM_STR);
    $update->execute();

    if($status === 'success') {
        $confirmed = 1;
        $bookingUpdate = $dbh->prepare("UPDATE tblbooking SET status=:status WHERE BookingId=:bookingId AND UserEmail=:userEmail AND status<>2");
        $bookingUpdate->bindParam(':status', $confirmed, PDO::PARAM_INT);
        $bookingUpdate->bindParam(':bookingId', $payment->BookingId, PDO::PARAM_INT);
        $bookingUpdate->bindParam(':userEmail', $userEmail, PDO::PARAM_STR);
        $bookingUpdate->execute();
        $message = 'Payment successful. Your booking is confirmed.';
    } elseif($status === 'pending') {
        $message = 'Payment is still pending. Please check again after a few minutes.';
    } else {
        $message = 'Payment was not completed. Current status: ' . $status;
    }
} catch (Exception $ex) {
    $message = $ex->getMessage();
}
?>
<!DOCTYPE HTML>
<html>
<head>
<title>TMS | Payment Status</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
<link href="css/bootstrap.css" rel='stylesheet' type='text/css' />
<link href="css/style.css" rel='stylesheet' type='text/css' />
<link href="css/font-awesome.css" rel="stylesheet">
<script src="js/jquery-1.12.0.min.js"></script>
<script src="js/bootstrap.min.js"></script>
<style>
.statusWrap{padding:10px;margin:20px 0;background:#fff;border-left:4px solid #5cb85c;box-shadow:0 1px 1px 0 rgba(0,0,0,.1)}
</style>
</head>
<body>
<?php include('includes/header.php');?>
<div class="banner-1">
	<div class="container"><h1>TMS - Payment Status</h1></div>
</div>
<div class="privacy">
	<div class="container">
		<div class="statusWrap"><?php echo e($message); ?></div>
		<p><a class="btn btn-primary" href="tour-history.php">View Tour History</a></p>
		<?php if($payment) { ?>
			<p><a class="btn btn-default" href="invoice.php?booking=<?php echo e($payment->BookingId); ?>">View Invoice</a></p>
		<?php } ?>
	</div>
</div>
<?php include('includes/footer.php');?>
<?php include('includes/write-us.php');?>
</body>
</html>
