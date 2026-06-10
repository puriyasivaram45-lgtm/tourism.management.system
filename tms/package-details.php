<?php
session_start();
error_reporting(0);
include('includes/config.php');
include('includes/payments.php');
if(isset($_POST['submit2']))
{
$pid=intval($_GET['pkgid']);
$useremail=$_SESSION['login'];
if(strlen($useremail)==0) {
$error="Please sign in before booking.";
} elseif(!csrf_verify()) {
$error="Invalid form token. Please try again.";
	} else {
	$fromdate=$_POST['fromdate'];
	$todate=$_POST['todate'];
	$adults=pricing_int_range($_POST['adults'] ?? 1, 1, 20);
	$children=pricing_int_range($_POST['children'] ?? 0, 0, 20);
	$rooms=pricing_int_range($_POST['rooms'] ?? 1, 1, 10);
	$comment=trim($_POST['comment']);
	$status=0;
if(!valid_iso_date($fromdate) || !valid_iso_date($todate)) {
$error="Please select valid travel dates.";
} elseif($fromdate < date('Y-m-d')) {
$error="From date cannot be in the past.";
} elseif($todate < $fromdate) {
$error="To date cannot be before from date.";
} elseif($comment === '') {
$error="Please enter a booking comment.";
} else {
	$packageSql="SELECT PackageId,PackagePrice FROM tbltourpackages WHERE PackageId=:pid";
	$packageQuery=$dbh->prepare($packageSql);
	$packageQuery->bindParam(':pid',$pid,PDO::PARAM_INT);
	$packageQuery->execute();
	$package=$packageQuery->fetch(PDO::FETCH_OBJ);
	if(!$package) {
	$error="Selected package was not found.";
	} else {
	ensure_booking_pricing_columns($dbh);
	$quote=package_price_quote($package->PackagePrice,$fromdate,$todate,$adults,$children,$rooms);
	$travelDays=(int)$quote['days'];
	$estimatedAmount=(int)$quote['total'];
	$priceBreakdown=json_encode($quote);
	$sql="INSERT INTO tblbooking(PackageId,UserEmail,FromDate,ToDate,Adults,Children,Rooms,TravelDays,EstimatedAmount,PriceBreakdown,Comment,status) VALUES(:pid,:useremail,:fromdate,:todate,:adults,:children,:rooms,:traveldays,:estimatedamount,:pricebreakdown,:comment,:status)";
	$query = $dbh->prepare($sql);
	$query->bindParam(':pid',$pid,PDO::PARAM_INT);
	$query->bindParam(':useremail',$useremail,PDO::PARAM_STR);
	$query->bindParam(':fromdate',$fromdate,PDO::PARAM_STR);
	$query->bindParam(':todate',$todate,PDO::PARAM_STR);
	$query->bindParam(':adults',$adults,PDO::PARAM_INT);
	$query->bindParam(':children',$children,PDO::PARAM_INT);
	$query->bindParam(':rooms',$rooms,PDO::PARAM_INT);
	$query->bindParam(':traveldays',$travelDays,PDO::PARAM_INT);
	$query->bindParam(':estimatedamount',$estimatedAmount,PDO::PARAM_INT);
	$query->bindParam(':pricebreakdown',$priceBreakdown,PDO::PARAM_STR);
	$query->bindParam(':comment',$comment,PDO::PARAM_STR);
	$query->bindParam(':status',$status,PDO::PARAM_INT);
$query->execute();
$lastInsertId = $dbh->lastInsertId();
if($lastInsertId)
{
$msg="Booked Successfully";
if(phonepe_enabled()) {
	header('location:payment-start.php?booking='.intval($lastInsertId));
	exit;
	}
	header('location:invoice.php?booking='.intval($lastInsertId));
	exit;
	}
else 
{
$error="Something went wrong. Please try again";
}

}
}
}
}
?>
<!DOCTYPE HTML>
<html>
<head>
<title>TMS | Package Details</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
<script type="applijewelleryion/x-javascript"> addEventListener("load", function() { setTimeout(hideURLbar, 0); }, false); function hideURLbar(){ window.scrollTo(0,1); } </script>
<link href="css/bootstrap.css" rel='stylesheet' type='text/css' />
<link href="css/style.css" rel='stylesheet' type='text/css' />
<link href='//fonts.googleapis.com/css?family=Open+Sans:400,700,600' rel='stylesheet' type='text/css'>
<link href='//fonts.googleapis.com/css?family=Roboto+Condensed:400,700,300' rel='stylesheet' type='text/css'>
<link href='//fonts.googleapis.com/css?family=Oswald' rel='stylesheet' type='text/css'>
<link href="css/font-awesome.css" rel="stylesheet">
<!-- Custom Theme files -->
<script src="js/jquery-1.12.0.min.js"></script>
<script src="js/bootstrap.min.js"></script>
<!--animate-->
<link href="css/animate.css" rel="stylesheet" type="text/css" media="all">
<script src="js/wow.min.js"></script>
<link rel="stylesheet" href="css/jquery-ui.css" />
	<script>
		 new WOW().init();
	</script>
<script src="js/jquery-ui.js"></script>
					<script>
						$(function() {
						$( "#datepicker,#datepicker1" ).datepicker();
						});
					</script>
	  <style>
		.errorWrap {
    padding: 10px;
    margin: 0 0 20px 0;
    background: #fff;
    border-left: 4px solid #dd3d36;
    -webkit-box-shadow: 0 1px 1px 0 rgba(0,0,0,.1);
    box-shadow: 0 1px 1px 0 rgba(0,0,0,.1);
}
.succWrap{
    padding: 10px;
    margin: 0 0 20px 0;
    background: #fff;
    border-left: 4px solid #5cb85c;
    -webkit-box-shadow: 0 1px 1px 0 rgba(0,0,0,.1);
    box-shadow: 0 1px 1px 0 rgba(0,0,0,.1);
}
.estimated-price-box {
    background: #fff;
    border: 1px solid #e4e4e4;
    padding: 14px 16px;
    margin-top: 14px;
}
.estimated-price-box h3 {
    margin: 4px 0 10px;
}
.estimated-price-box ul {
    list-style: none;
    padding: 0;
    margin: 0;
}
.estimated-price-box li {
    display: flex;
    justify-content: space-between;
    gap: 12px;
    border-top: 1px solid #efefef;
    padding: 6px 0;
}
.estimated-price-box small {
    display: block;
    color: #777;
    margin-top: 8px;
}
		</style>				
</head>
<body>
<!-- top-header -->
<?php include('includes/header.php');?>
<div class="banner-3">
	<div class="container">
		<h1 class="wow zoomIn animated animated" data-wow-delay=".5s" style="visibility: visible; animation-delay: 0.5s; animation-name: zoomIn;"> TMS -Package Details</h1>
	</div>
</div>
<!--- /banner ---->
<!--- selectroom ---->
<div class="selectroom">
	<div class="container">	
		  <?php if($error){?><div class="errorWrap"><strong>ERROR</strong>:<?php echo htmlentities($error); ?> </div><?php } 
				else if($msg){?><div class="succWrap"><strong>SUCCESS</strong>:<?php echo htmlentities($msg); ?> </div><?php }?>
<?php 
$pid=intval($_GET['pkgid']);
$sql = "SELECT * from tbltourpackages where PackageId=:pid";
$query = $dbh->prepare($sql);
$query -> bindParam(':pid', $pid, PDO::PARAM_STR);
$query->execute();
$results=$query->fetchAll(PDO::FETCH_OBJ);
$cnt=1;
if($query->rowCount() > 0)
{
foreach($results as $result)
{	
$priceConfig = pricing_quote_config($result->PackagePrice);
$initialQuote = package_price_quote($result->PackagePrice);
?>
	
<form name="book" method="post"
	data-base-price="<?php echo e($priceConfig['base_price']); ?>"
	data-extra-day-rate="<?php echo e($priceConfig['extra_day_rate']); ?>"
	data-daily-support-fee="<?php echo e($priceConfig['daily_support_fee']); ?>"
	data-child-rate="<?php echo e($priceConfig['child_rate']); ?>"
	data-room-day-rate="<?php echo e($priceConfig['room_day_rate']); ?>"
	data-seasonal-rate="<?php echo e($priceConfig['seasonal_rate']); ?>"
	data-tax-rate="<?php echo e($priceConfig['tax_rate']); ?>">
		<?php echo csrf_field(); ?>
		<div class="selectroom_top">
			<div class="col-md-4 selectroom_left wow fadeInLeft animated" data-wow-delay=".5s">
				<img src="admin/pacakgeimages/<?php echo htmlentities($result->PackageImage);?>" class="img-responsive" alt="">
			</div>
			<div class="col-md-8 selectroom_right wow fadeInRight animated" data-wow-delay=".5s">
				<h2><?php echo htmlentities($result->PackageName);?></h2>
				<p class="dow">#PKG-<?php echo htmlentities($result->PackageId);?></p>
				<p><b>Package Type :</b> <?php echo htmlentities($result->PackageType);?></p>
				<p><b>Package Location :</b> <?php echo htmlentities($result->PackageLocation);?></p>
					<p><b>Features</b> <?php echo htmlentities($result->PackageFetures);?></p>
					<div class="ban-bottom">
				<div class="bnr-right">
				<label class="inputLabel">From</label>
				<input class="date" id="datepicker2" type="date" placeholder="dd-mm-yyyy"  name="fromdate" required="">
			</div>
				<div class="bnr-right">
					<label class="inputLabel">To</label>
					<input class="date" id="datepicker3" type="date" placeholder="dd-mm-yyyy" name="todate" required="">
				</div>
				<div class="bnr-right">
					<label class="inputLabel">Adults</label>
					<input class="date" id="adults" type="number" name="adults" min="1" max="20" value="1" required="">
				</div>
				<div class="bnr-right">
					<label class="inputLabel">Children</label>
					<input class="date" id="children" type="number" name="children" min="0" max="20" value="0" required="">
				</div>
				<div class="bnr-right">
					<label class="inputLabel">Rooms</label>
					<input class="date" id="rooms" type="number" name="rooms" min="1" max="10" value="1" required="">
				</div>
				</div>
						<div class="clearfix"></div>
				<div class="grand estimated-price-box">
					<p>Estimated Total</p>
					<h3 id="estimateTotal"><?php echo e(pricing_format_inr($initialQuote['total']));?></h3>
					<ul>
						<li><span>Travel days</span><strong id="estimateDays"><?php echo e($initialQuote['days']);?></strong></li>
						<li><span>Travelers / rooms</span><strong id="estimateTravelers"><?php echo e($initialQuote['adults']);?> adult, <?php echo e($initialQuote['children']);?> children, <?php echo e($initialQuote['rooms']);?> room</strong></li>
						<li><span>Day 1 package price</span><strong id="estimateBase"><?php echo e(pricing_format_inr($initialQuote['base_price']));?></strong></li>
						<li><span>Extra day estimate</span><strong id="estimateExtra"><?php echo e($initialQuote['extra_days']);?> x <?php echo e(pricing_format_inr($initialQuote['extra_day_rate']));?></strong></li>
						<li><span>Adult traveler total</span><strong id="estimateAdult"><?php echo e(pricing_format_inr($initialQuote['adult_amount']));?></strong></li>
						<li><span>Child traveler total</span><strong id="estimateChild"><?php echo e(pricing_format_inr($initialQuote['child_amount']));?></strong></li>
						<li><span>Extra room estimate</span><strong id="estimateRooms"><?php echo e(pricing_format_inr($initialQuote['room_amount']));?></strong></li>
						<li><span>Hotel/guide dummy estimate</span><strong id="estimateSupport"><?php echo e(pricing_format_inr($initialQuote['support_amount']));?></strong></li>
						<li><span>Seasonal buffer</span><strong id="estimateSeasonal"><?php echo e(pricing_format_inr($initialQuote['seasonal_amount']));?></strong></li>
						<li><span>Taxes/service estimate</span><strong id="estimateTax"><?php echo e(pricing_format_inr($initialQuote['tax_amount']));?></strong></li>
					</ul>
					<small>Testing estimate only. Amount increases as travel days increase.</small>
				</div>
			</div>
		<h3>Package Details</h3>
				<p style="padding-top: 1%"><?php echo htmlentities($result->PackageDetails);?> </p>	
				<div class="clearfix"></div>
		</div>
		<div class="selectroom_top">
			<h2>Travels</h2>
			<div class="selectroom-info animated wow fadeInUp animated" data-wow-duration="1200ms" data-wow-delay="500ms" style="visibility: visible; animation-duration: 1200ms; animation-delay: 500ms; animation-name: fadeInUp; margin-top: -70px">
				<ul>
				
					<li class="spe">
						<label class="inputLabel">Comment</label>
						<input class="special" type="text" name="comment" required="">
					</li>
					<?php if($_SESSION['login'])
					{?>
						<li class="spe" align="center">
					<button type="submit" name="submit2" class="btn-primary btn">Book</button>
						</li>
						<?php } else {?>
							<li class="sigi" align="center" style="margin-top: 1%">
							<a href="#" data-toggle="modal" data-target="#myModal4" class="btn-primary btn" > Book</a></li>
							<?php } ?>
					<div class="clearfix"></div>
				</ul>
			</div>
			
		</div>
		</form>
<?php }} ?>


	</div>
</div>
<!--- /selectroom ---->
<<!--- /footer-top ---->
<?php include('includes/footer.php');?>
<!-- signup -->
<?php include('includes/signup.php');?>			
<!-- //signu -->
<!-- signin -->
<?php include('includes/signin.php');?>			
<!-- //signin -->
<!-- write us -->
<?php include('includes/write-us.php');?>


<script>
  (function () {
    var fromInput = document.getElementById("datepicker2");
    var toInput = document.getElementById("datepicker3");
    var adultsInput = document.getElementById("adults");
    var childrenInput = document.getElementById("children");
    var roomsInput = document.getElementById("rooms");
    var bookingForm = document.forms.book;
    if (!fromInput || !toInput || !bookingForm) {
      return;
    }

    var today = new Date().toISOString().split("T")[0];
    fromInput.setAttribute("min", today);
    toInput.setAttribute("min", today);

    var basePrice = parseInt(bookingForm.getAttribute("data-base-price"), 10) || 1;
    var extraDayRate = parseInt(bookingForm.getAttribute("data-extra-day-rate"), 10) || 500;
    var dailySupportFee = parseInt(bookingForm.getAttribute("data-daily-support-fee"), 10) || 300;
    var childRate = parseFloat(bookingForm.getAttribute("data-child-rate")) || 0.55;
    var roomDayRate = parseInt(bookingForm.getAttribute("data-room-day-rate"), 10) || 800;
    var seasonalRate = parseFloat(bookingForm.getAttribute("data-seasonal-rate")) || 0.06;
    var taxRate = parseFloat(bookingForm.getAttribute("data-tax-rate")) || 0.05;

    function inr(amount) {
      return "Rs. " + Math.round(amount).toLocaleString("en-IN");
    }

    function parseIsoDate(value) {
      if (!/^\d{4}-\d{2}-\d{2}$/.test(value || "")) {
        return null;
      }
      var parts = value.split("-").map(Number);
      return new Date(Date.UTC(parts[0], parts[1] - 1, parts[2]));
    }

    function getTravelDays() {
      var fromDate = parseIsoDate(fromInput.value);
      var toDate = parseIsoDate(toInput.value);
      if (!fromDate || !toDate || toDate < fromDate) {
        return 1;
      }
      return Math.floor((toDate - fromDate) / 86400000) + 1;
    }

    function intRange(input, min, max) {
      var value = parseInt(input.value, 10);
      if (isNaN(value)) {
        value = min;
      }
      value = Math.max(min, Math.min(max, value));
      input.value = value;
      return value;
    }

    function updateEstimate() {
      if (fromInput.value) {
        toInput.setAttribute("min", fromInput.value);
        if (toInput.value && toInput.value < fromInput.value) {
          toInput.value = fromInput.value;
        }
      }

      var days = getTravelDays();
      var adults = intRange(adultsInput, 1, 20);
      var children = intRange(childrenInput, 0, 20);
      var rooms = intRange(roomsInput, 1, 10);
      var extraDays = Math.max(0, days - 1);
      var extraAmount = extraDays * extraDayRate;
      var travelSubtotal = basePrice + extraAmount;
      var adultAmount = travelSubtotal * adults;
      var childAmount = Math.round(travelSubtotal * childRate * children);
      var roomAmount = Math.max(0, rooms - 1) * days * roomDayRate;
      var supportAmount = days * dailySupportFee * (adults + children);
      var subtotal = adultAmount + childAmount + roomAmount;
      var seasonalAmount = Math.round(subtotal * seasonalRate);
      var taxAmount = Math.round((subtotal + supportAmount + seasonalAmount) * taxRate);
      var total = subtotal + supportAmount + seasonalAmount + taxAmount;

      document.getElementById("estimateDays").textContent = days;
      document.getElementById("estimateTravelers").textContent = adults + " adult, " + children + " children, " + rooms + " room";
      document.getElementById("estimateBase").textContent = inr(basePrice);
      document.getElementById("estimateExtra").textContent = extraDays + " x " + inr(extraDayRate);
      document.getElementById("estimateAdult").textContent = inr(adultAmount);
      document.getElementById("estimateChild").textContent = inr(childAmount);
      document.getElementById("estimateRooms").textContent = inr(roomAmount);
      document.getElementById("estimateSupport").textContent = inr(supportAmount);
      document.getElementById("estimateSeasonal").textContent = inr(seasonalAmount);
      document.getElementById("estimateTax").textContent = inr(taxAmount);
      document.getElementById("estimateTotal").textContent = inr(total);
    }

    fromInput.addEventListener("change", updateEstimate);
    toInput.addEventListener("change", updateEstimate);
    adultsInput.addEventListener("change", updateEstimate);
    childrenInput.addEventListener("change", updateEstimate);
    roomsInput.addEventListener("change", updateEstimate);
    updateEstimate();
  })();
</script>

</body>
</html>
