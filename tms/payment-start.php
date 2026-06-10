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
$userEmail = $_SESSION['login'];
$error = '';
$paymentUrl = '';
$merchantOrderId = '';
$amountPaise = 0;

try {
    if(!$bookingId) {
        throw new RuntimeException('Booking id is missing.');
    }
    if(!phonepe_enabled()) {
        throw new RuntimeException('Online payment is not enabled. Set PHONEPE_ENABLED=true in ' . app_env_file_path() . '. Current PHONEPE_ENABLED value: ' . app_env('PHONEPE_ENABLED', 'not set'));
    }

    ensure_payment_table($dbh);
    ensure_booking_pricing_columns($dbh);

    $sql = "SELECT b.BookingId,b.UserEmail,b.FromDate,b.ToDate,b.Adults,b.Children,b.Rooms,b.TravelDays,b.EstimatedAmount,b.status,p.PackageName,p.PackagePrice
        FROM tblbooking b
        JOIN tbltourpackages p ON p.PackageId=b.PackageId
        WHERE b.BookingId=:bookingId AND b.UserEmail=:userEmail
        LIMIT 1";
    $query = $dbh->prepare($sql);
    $query->bindParam(':bookingId', $bookingId, PDO::PARAM_INT);
    $query->bindParam(':userEmail', $userEmail, PDO::PARAM_STR);
    $query->execute();
    $booking = $query->fetch(PDO::FETCH_OBJ);

    if(!$booking) {
        throw new RuntimeException('Booking was not found.');
    }
    if((int) $booking->status === 2) {
        throw new RuntimeException('Cancelled bookings cannot be paid.');
    }

    $reuseExisting = empty($_GET['refresh']);
    if($reuseExisting) {
        $existing = $dbh->prepare("SELECT MerchantOrderId,AmountPaise,RedirectUrl FROM tblpayments
            WHERE BookingId=:bookingId AND UserEmail=:userEmail AND Status IN ('redirect_pending','pending','created')
                AND RedirectUrl IS NOT NULL AND RedirectUrl <> ''
                AND CreatedAt >= DATE_SUB(NOW(), INTERVAL 20 MINUTE)
            ORDER BY id DESC LIMIT 1");
        $existing->bindParam(':bookingId', $bookingId, PDO::PARAM_INT);
        $existing->bindParam(':userEmail', $userEmail, PDO::PARAM_STR);
        $existing->execute();
        $existingPayment = $existing->fetch(PDO::FETCH_OBJ);
        if($existingPayment) {
            $merchantOrderId = $existingPayment->MerchantOrderId;
            $amountPaise = (int) $existingPayment->AmountPaise;
            $paymentUrl = $existingPayment->RedirectUrl;
        }
    }

    if($paymentUrl === '') {
        $quote = booking_quote_from_row($booking);
        $amountPaise = max(1, (int) $quote['total']) * 100;
        $merchantOrderId = 'TMSBK' . $bookingId . time();
        $redirectUrl = app_url('payment-return.php?merchantOrderId=' . rawurlencode($merchantOrderId));

        list($paymentUrl, $requestPayload, $responsePayload) = phonepe_create_payment_url(
            $merchantOrderId,
            $amountPaise,
            $redirectUrl,
            array(
                'booking_id' => $bookingId,
                'package_name' => $booking->PackageName,
                'user_email' => $booking->UserEmail,
                'travel_days' => $quote['days'],
                'adults' => $quote['adults'],
                'children' => $quote['children'],
                'rooms' => $quote['rooms'],
                'estimated_total' => $quote['total'],
            )
        );

        $insert = $dbh->prepare("INSERT INTO tblpayments
            (BookingId,UserEmail,MerchantOrderId,Provider,AmountPaise,Currency,Status,RedirectUrl,RequestPayload,ResponsePayload)
            VALUES(:bookingId,:userEmail,:merchantOrderId,'phonepe',:amountPaise,'INR','redirect_pending',:redirectUrl,:requestPayload,:responsePayload)");
        $insert->bindParam(':bookingId', $bookingId, PDO::PARAM_INT);
        $insert->bindParam(':userEmail', $booking->UserEmail, PDO::PARAM_STR);
        $insert->bindParam(':merchantOrderId', $merchantOrderId, PDO::PARAM_STR);
        $insert->bindParam(':amountPaise', $amountPaise, PDO::PARAM_INT);
        $insert->bindParam(':redirectUrl', $paymentUrl, PDO::PARAM_STR);
        $requestJson = json_encode($requestPayload);
        $responseJson = json_encode($responsePayload);
        $insert->bindParam(':requestPayload', $requestJson, PDO::PARAM_STR);
        $insert->bindParam(':responsePayload', $responseJson, PDO::PARAM_STR);
        $insert->execute();
    }
} catch (Exception $ex) {
    $error = $ex->getMessage();
}
?>
<!DOCTYPE HTML>
<html>
<head>
<title>TMS | Payment</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
<link href="css/bootstrap.css" rel='stylesheet' type='text/css' />
<link href="css/style.css" rel='stylesheet' type='text/css' />
<link href="css/font-awesome.css" rel="stylesheet">
<script src="js/jquery-1.12.0.min.js"></script>
<script src="js/bootstrap.min.js"></script>
<style>
.errorWrap{padding:10px;margin:20px 0;background:#fff;border-left:4px solid #dd3d36;box-shadow:0 1px 1px 0 rgba(0,0,0,.1)}
.paymentWrap{padding:18px;margin:20px 0;background:#fff;border-left:4px solid #5cb85c;box-shadow:0 1px 1px 0 rgba(0,0,0,.1)}
.paymentWrap h3{margin-top:0}
.payment-actions{margin-top:16px}
</style>
</head>
<body>
<?php include('includes/header.php');?>
<div class="banner-1">
	<div class="container"><h1>TMS - Payment</h1></div>
</div>
<div class="privacy">
	<div class="container">
		<?php if($error) { ?>
			<div class="errorWrap"><strong>PAYMENT ERROR</strong>: <?php echo e($error); ?></div>
			<p><a class="btn btn-primary" href="tour-history.php">Back to Tour History</a></p>
		<?php } else { ?>
			<div class="paymentWrap">
				<h3>PhonePe payment link is ready</h3>
				<p><b>Reference:</b> <?php echo e($merchantOrderId); ?></p>
				<p><b>Amount:</b> <?php echo e(pricing_format_inr($amountPaise / 100)); ?></p>
				<p>If the PhonePe page does not open automatically, click the button below.</p>
				<div class="payment-actions">
					<a class="btn btn-success" href="<?php echo e($paymentUrl); ?>">Continue to PhonePe</a>
					<a class="btn btn-default" href="payment-start.php?booking=<?php echo e($bookingId); ?>&refresh=1">Create New Payment Link</a>
					<a class="btn btn-primary" href="invoice.php?booking=<?php echo e($bookingId); ?>">Back to Invoice</a>
				</div>
			</div>
			<script>
				setTimeout(function () {
					window.location.href = <?php echo json_encode($paymentUrl); ?>;
				}, 1200);
			</script>
		<?php } ?>
	</div>
</div>
<?php include('includes/footer.php');?>
<?php include('includes/write-us.php');?>
</body>
</html>
