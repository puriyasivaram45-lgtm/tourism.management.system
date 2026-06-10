<?php
session_start();
error_reporting(0);
include('includes/config.php');
include('includes/payments.php');

if(strlen($_SESSION['login'])==0) {
header('location:index.php');
exit;
}

$bookingId = isset($_GET['booking']) ? intval($_GET['booking']) : 0;
$payment = null;
if($bookingId > 0) {
    $payment = payment_latest_for_booking($dbh, $bookingId, $_SESSION['login']);
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
</head>
<body>
<?php include('includes/header.php');?>
<div class="banner-1">
	<div class="container"><h1>TMS - Payment Status</h1></div>
</div>
<div class="privacy">
	<div class="container">
		<?php if($payment) { ?>
			<p><b>Payment Reference:</b> <?php echo e($payment->MerchantOrderId); ?></p>
			<p><b>Status:</b> <?php echo e($payment->Status); ?></p>
			<p><b>Amount:</b> Rs. <?php echo e(number_format($payment->AmountPaise / 100, 2)); ?></p>
			<?php if($payment->Status !== 'success' && phonepe_enabled()) { ?>
				<p><a class="btn btn-primary" href="payment-return.php?merchantOrderId=<?php echo e($payment->MerchantOrderId); ?>">Refresh Payment Status</a></p>
			<?php } ?>
			<p><a class="btn btn-default" href="invoice.php?booking=<?php echo e($payment->BookingId); ?>">View Invoice</a></p>
		<?php } else { ?>
			<p>No payment record found for this booking.</p>
			<?php if($bookingId > 0) { ?>
				<p><a class="btn btn-success" href="payment-start.php?booking=<?php echo e($bookingId); ?>">Make Payment</a></p>
				<p><a class="btn btn-default" href="invoice.php?booking=<?php echo e($bookingId); ?>">View Invoice</a></p>
			<?php } ?>
		<?php } ?>
		<p><a class="btn btn-primary" href="tour-history.php">Back to Tour History</a></p>
	</div>
</div>
<?php include('includes/footer.php');?>
<?php include('includes/write-us.php');?>
</body>
</html>
