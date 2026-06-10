<?php
session_start();
error_reporting(0);
include('includes/config.php');
include('includes/payments.php');

if(strlen($_SESSION['login'])==0) {
header('location:index.php');
exit;
}

ensure_booking_pricing_columns($dbh);

$bookingId = isset($_GET['booking']) ? intval($_GET['booking']) : 0;
$userEmail = $_SESSION['login'];
$error = '';
$booking = null;
$payment = null;

if($bookingId > 0) {
    $sql = "SELECT b.BookingId as bookid,b.PackageId as pkgid,b.UserEmail as useremail,b.FromDate as fromdate,b.ToDate as todate,b.Adults as adults,b.Children as children,b.Rooms as rooms,b.TravelDays as traveldays,b.EstimatedAmount as estimatedamount,b.PriceBreakdown as pricebreakdown,b.Comment as comment,b.status as status,b.RegDate as regdate,b.CancelledBy as cancelby,b.UpdationDate as upddate,p.PackageName as packagename,p.PackageType as packagetype,p.PackageLocation as packagelocation,p.PackagePrice as packageprice
        FROM tblbooking b
        JOIN tbltourpackages p ON p.PackageId=b.PackageId
        WHERE b.BookingId=:bookingId AND b.UserEmail=:userEmail
        LIMIT 1";
    $query = $dbh->prepare($sql);
    $query->bindParam(':bookingId', $bookingId, PDO::PARAM_INT);
    $query->bindParam(':userEmail', $userEmail, PDO::PARAM_STR);
    $query->execute();
    $booking = $query->fetch(PDO::FETCH_OBJ);
    if($booking) {
        $payment = payment_latest_for_booking($dbh, $bookingId, $userEmail);
        $quote = booking_quote_from_row($booking);
    } else {
        $error = 'Invoice was not found.';
    }
} else {
    $error = 'Booking id is missing.';
}
?>
<!DOCTYPE HTML>
<html>
<head>
<title>TMS | Booking Invoice</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
<link href="css/bootstrap.css" rel='stylesheet' type='text/css' />
<link href="css/style.css" rel='stylesheet' type='text/css' />
<link href="css/font-awesome.css" rel="stylesheet">
<style>
.invoice-box{background:#fff;border:1px solid #ddd;padding:24px;margin-bottom:24px}
.invoice-head{display:flex;justify-content:space-between;gap:20px;border-bottom:1px solid #eee;padding-bottom:12px;margin-bottom:18px}
.invoice-table{width:100%;border-collapse:collapse;margin-top:12px}
.invoice-table th,.invoice-table td{border:1px solid #ddd;padding:9px;text-align:left}
.invoice-table th{background:#f7f7f7}
.invoice-total{font-size:20px;font-weight:bold}
.invoice-actions{margin:18px 0}
@media print{.top-header,.banner-1,.footer-btm,.copy-right,.invoice-actions{display:none!important}.invoice-box{border:0;margin:0;padding:0}}
</style>
</head>
<body>
<?php include('includes/header.php');?>
<div class="banner-1">
	<div class="container"><h1>TMS - Booking Invoice</h1></div>
</div>
<div class="privacy">
	<div class="container">
		<?php if($error) { ?>
			<div class="errorWrap"><strong>ERROR</strong>: <?php echo e($error); ?></div>
			<p><a class="btn btn-primary" href="tour-history.php">Back to Tour History</a></p>
		<?php } else { ?>
			<div class="invoice-actions">
				<button class="btn btn-primary" onclick="window.print()">Print Invoice</button>
				<a class="btn btn-default" href="tour-history.php">Back to Tour History</a>
				<?php if((int)$booking->status !== 2 && !$payment) { ?>
					<a class="btn btn-success" href="payment-start.php?booking=<?php echo e($booking->bookid); ?>">Make Payment</a>
				<?php } ?>
			</div>
			<div class="invoice-box">
				<div class="invoice-head">
					<div>
						<h2>Tourism Management System</h2>
						<p>Estimated booking invoice</p>
					</div>
					<div>
						<p><b>Invoice:</b> TMS-BK-<?php echo e($booking->bookid); ?></p>
						<p><b>Booking Date:</b> <?php echo e($booking->regdate); ?></p>
						<p><b>Status:</b> <?php echo e(booking_status_label($booking->status, $booking->cancelby, $booking->upddate)); ?></p>
					</div>
				</div>

				<table class="invoice-table">
					<tr><th>Package</th><td><?php echo e($booking->packagename); ?></td></tr>
					<tr><th>Location</th><td><?php echo e($booking->packagelocation); ?></td></tr>
					<tr><th>Type</th><td><?php echo e($booking->packagetype); ?></td></tr>
					<tr><th>Travel Dates</th><td><?php echo e($booking->fromdate); ?> to <?php echo e($booking->todate); ?> (<?php echo e($quote['days']); ?> day(s))</td></tr>
					<tr><th>Travelers</th><td><?php echo e($quote['adults']); ?> adult(s), <?php echo e($quote['children']); ?> child(ren), <?php echo e($quote['rooms']); ?> room(s)</td></tr>
					<tr><th>User Email</th><td><?php echo e($booking->useremail); ?></td></tr>
				</table>

				<h3>Price Breakdown</h3>
				<table class="invoice-table">
					<tr><th>Day 1 package price</th><td><?php echo e(pricing_format_inr($quote['base_price'])); ?></td></tr>
					<tr><th>Extra day estimate</th><td><?php echo e($quote['extra_days']); ?> x <?php echo e(pricing_format_inr($quote['extra_day_rate'])); ?> = <?php echo e(pricing_format_inr($quote['extra_days_amount'])); ?></td></tr>
					<tr><th>Adult traveler total</th><td><?php echo e(pricing_format_inr($quote['adult_amount'])); ?></td></tr>
					<tr><th>Child traveler total</th><td><?php echo e(pricing_format_inr($quote['child_amount'])); ?></td></tr>
					<tr><th>Extra room estimate</th><td><?php echo e(pricing_format_inr($quote['room_amount'])); ?></td></tr>
					<tr><th>Hotel/guide dummy estimate</th><td><?php echo e(pricing_format_inr($quote['support_amount'])); ?></td></tr>
					<tr><th>Seasonal buffer</th><td><?php echo e(pricing_format_inr($quote['seasonal_amount'])); ?></td></tr>
					<tr><th>Taxes/service estimate</th><td><?php echo e(pricing_format_inr($quote['tax_amount'])); ?></td></tr>
					<tr><th class="invoice-total">Grand total</th><td class="invoice-total"><?php echo e(pricing_format_inr($quote['total'])); ?></td></tr>
				</table>

				<p><b>Payment:</b>
					<?php if($payment) { ?>
						<?php echo e($payment->Status); ?>, <?php echo e(pricing_format_inr($payment->AmountPaise / 100)); ?>
						<?php if($payment->Status !== 'success' && (int)$booking->status !== 2) { ?>
							<br><a class="btn btn-success" href="payment-start.php?booking=<?php echo e($booking->bookid); ?>">Make Payment</a>
						<?php } ?>
					<?php } elseif((int)$booking->status !== 2) { ?>
						<a class="btn btn-success" href="payment-start.php?booking=<?php echo e($booking->bookid); ?>">Make Payment</a>
					<?php } else { ?>
						Booking cancelled
					<?php } ?>
				</p>
				<p><small>This invoice uses dummy estimated pricing for testing.</small></p>
			</div>
		<?php } ?>
	</div>
</div>
<?php include('includes/footer.php');?>
<?php include('includes/write-us.php');?>
</body>
</html>
