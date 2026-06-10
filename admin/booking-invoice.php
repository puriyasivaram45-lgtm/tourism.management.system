<?php
session_start();
error_reporting(0);
include('includes/config.php');
include('../includes/payments.php');

if(strlen($_SESSION['alogin'])==0) {
header('location:index.php');
exit;
}

ensure_booking_pricing_columns($dbh);

$bookingId = isset($_GET['booking']) ? intval($_GET['booking']) : 0;
$error = '';
$booking = null;
$payment = null;

if($bookingId > 0) {
    $sql = "SELECT b.BookingId as bookid,b.PackageId as pkgid,b.UserEmail as useremail,b.FromDate as fromdate,b.ToDate as todate,b.Adults as adults,b.Children as children,b.Rooms as rooms,b.TravelDays as traveldays,b.EstimatedAmount as estimatedamount,b.PriceBreakdown as pricebreakdown,b.Comment as comment,b.status as status,b.RegDate as regdate,b.CancelledBy as cancelby,b.UpdationDate as upddate,u.FullName as fullname,u.MobileNumber as mobilenumber,p.PackageName as packagename,p.PackageType as packagetype,p.PackageLocation as packagelocation,p.PackagePrice as packageprice
        FROM tblbooking b
        LEFT JOIN tblusers u ON b.UserEmail=u.EmailId
        JOIN tbltourpackages p ON p.PackageId=b.PackageId
        WHERE b.BookingId=:bookingId
        LIMIT 1";
    $query = $dbh->prepare($sql);
    $query->bindParam(':bookingId', $bookingId, PDO::PARAM_INT);
    $query->execute();
    $booking = $query->fetch(PDO::FETCH_OBJ);
    if($booking) {
        $payment = payment_latest_for_booking($dbh, $bookingId);
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
<title>TMS | Admin Booking Invoice</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
<link href="css/bootstrap.min.css" rel='stylesheet' type='text/css' />
<link href="css/style.css" rel='stylesheet' type='text/css' />
<link href="css/font-awesome.css" rel="stylesheet">
<style>
.invoice-box{background:#fff;border:1px solid #ddd;padding:24px;margin:20px}
.invoice-head{display:flex;justify-content:space-between;gap:20px;border-bottom:1px solid #eee;padding-bottom:12px;margin-bottom:18px}
.invoice-table{width:100%;border-collapse:collapse;margin-top:12px}
.invoice-table th,.invoice-table td{border:1px solid #ddd;padding:9px;text-align:left}
.invoice-table th{background:#f7f7f7}
.invoice-total{font-size:20px;font-weight:bold}
.invoice-actions{margin:20px}
@media print{.sidebar-menu,.header-main,.breadcrumb,.invoice-actions,.footer{display:none!important}.left-content{margin:0!important}.invoice-box{border:0;margin:0;padding:0}}
</style>
</head>
<body>
<div class="page-container">
<div class="left-content">
	<div class="mother-grid-inner">
		<?php include('includes/header.php');?>
		<div class="clearfix"> </div>
	</div>
	<ol class="breadcrumb">
		<li class="breadcrumb-item"><a href="dashboard.php">Home</a><i class="fa fa-angle-right"></i>Booking Invoice</li>
	</ol>
	<?php if($error) { ?>
		<div class="invoice-actions">
			<div class="errorWrap"><strong>ERROR</strong>: <?php echo e($error); ?></div>
			<a class="btn btn-primary" href="manage-bookings.php">Back to Bookings</a>
		</div>
	<?php } else { ?>
		<div class="invoice-actions">
			<button class="btn btn-primary" onclick="window.print()">Print Invoice</button>
			<a class="btn btn-default" href="manage-bookings.php">Back to Bookings</a>
		</div>
		<div class="invoice-box">
			<div class="invoice-head">
				<div>
					<h2>Tourism Management System</h2>
					<p>Admin booking invoice</p>
				</div>
				<div>
					<p><b>Invoice:</b> TMS-BK-<?php echo e($booking->bookid); ?></p>
					<p><b>Booking Date:</b> <?php echo e($booking->regdate); ?></p>
					<p><b>Status:</b> <?php echo e(booking_status_label($booking->status, $booking->cancelby, $booking->upddate, true)); ?></p>
				</div>
			</div>

			<table class="invoice-table">
				<tr><th>Customer</th><td><?php echo e($booking->fullname); ?>, <?php echo e($booking->mobilenumber); ?></td></tr>
				<tr><th>Email</th><td><?php echo e($booking->useremail); ?></td></tr>
				<tr><th>Package</th><td><?php echo e($booking->packagename); ?></td></tr>
				<tr><th>Location</th><td><?php echo e($booking->packagelocation); ?></td></tr>
				<tr><th>Travel Dates</th><td><?php echo e($booking->fromdate); ?> to <?php echo e($booking->todate); ?> (<?php echo e($quote['days']); ?> day(s))</td></tr>
				<tr><th>Travelers</th><td><?php echo e($quote['adults']); ?> adult(s), <?php echo e($quote['children']); ?> child(ren), <?php echo e($quote['rooms']); ?> room(s)</td></tr>
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
				<?php } else { ?>
					Not started
				<?php } ?>
			</p>
		</div>
	<?php } ?>
	<?php include('includes/footer.php');?>
</div>
<?php include('includes/sidebarmenu.php');?>
<div class="clearfix"></div>
</div>
<script src="js/jquery-2.1.4.min.js"></script>
<script src="js/bootstrap.min.js"></script>
</body>
</html>
