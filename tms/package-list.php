<?php
session_start();
error_reporting(0);
include('includes/config.php');
?>
<!DOCTYPE HTML>
<html>
<head>
<title>TMS  | Package List</title>
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
	<script>
		 new WOW().init();
	</script>
<!--//end-animate-->
</head>
<body>
<?php include('includes/header.php');?>
<!--- banner ---->
<div class="banner-3">
	<div class="container">
		<h1 class="wow zoomIn animated animated" data-wow-delay=".5s" style="visibility: visible; animation-delay: 0.5s; animation-name: zoomIn;"> TMS- Package List</h1>
	</div>
</div>
<!--- /banner ---->
<!--- rooms ---->
<div class="rooms">
	<div class="container">
		
		<div class="room-bottom">
			<h3>Package List</h3>
			<form method="get" class="form-inline" style="margin-bottom:20px;">
				<input type="text" name="q" class="form-control" placeholder="Search packages" value="<?php echo e($_GET['q'] ?? ''); ?>" style="margin-bottom:8px;">
				<input type="text" name="location" class="form-control" placeholder="Location" value="<?php echo e($_GET['location'] ?? ''); ?>" style="margin-bottom:8px;">
				<input type="text" name="type" class="form-control" placeholder="Package type" value="<?php echo e($_GET['type'] ?? ''); ?>" style="margin-bottom:8px;">
				<input type="number" min="0" name="min_price" class="form-control" placeholder="Min price" value="<?php echo e($_GET['min_price'] ?? ''); ?>" style="margin-bottom:8px;">
				<input type="number" min="0" name="max_price" class="form-control" placeholder="Max price" value="<?php echo e($_GET['max_price'] ?? ''); ?>" style="margin-bottom:8px;">
				<button type="submit" class="btn btn-primary" style="margin-bottom:8px;">Search</button>
				<a href="package-list.php" class="btn btn-default" style="margin-bottom:8px;">Reset</a>
			</form>

					
<?php
$where = array();
$params = array();
if(!empty($_GET['q'])) {
	$where[] = "(PackageName LIKE :q OR PackageDetails LIKE :q OR PackageFetures LIKE :q)";
	$params[':q'] = '%' . $_GET['q'] . '%';
}
if(!empty($_GET['location'])) {
	$where[] = "PackageLocation LIKE :location";
	$params[':location'] = '%' . $_GET['location'] . '%';
}
if(!empty($_GET['type'])) {
	$where[] = "PackageType LIKE :type";
	$params[':type'] = '%' . $_GET['type'] . '%';
}
if(isset($_GET['min_price']) && $_GET['min_price'] !== '' && is_numeric($_GET['min_price'])) {
	$where[] = "PackagePrice >= :min_price";
	$params[':min_price'] = (int) $_GET['min_price'];
}
if(isset($_GET['max_price']) && $_GET['max_price'] !== '' && is_numeric($_GET['max_price'])) {
	$where[] = "PackagePrice <= :max_price";
	$params[':max_price'] = (int) $_GET['max_price'];
}
$sql = "SELECT * from tbltourpackages";
if($where) {
	$sql .= " WHERE " . implode(" AND ", $where);
}
	$sql .= " ORDER BY PackageId DESC";
$query = $dbh->prepare($sql);
foreach($params as $key=>$value) {
	if($key === ':min_price' || $key === ':max_price') {
		$query->bindValue($key, $value, PDO::PARAM_INT);
	} else {
		$query->bindValue($key, $value, PDO::PARAM_STR);
	}
}
$query->execute();
$results=$query->fetchAll(PDO::FETCH_OBJ);
$cnt=1;
if($query->rowCount() > 0)
{
foreach($results as $result)
{	?>
			<div class="rom-btm">
				<div class="col-md-3 room-left wow fadeInLeft animated" data-wow-delay=".5s">
					<img src="admin/pacakgeimages/<?php echo htmlentities($result->PackageImage);?>" class="img-responsive" alt="">
				</div>
				<div class="col-md-6 room-midle wow fadeInUp animated" data-wow-delay=".5s">
					<h4>Package Name: <?php echo htmlentities($result->PackageName);?></h4>
					<h6>Package Type : <?php echo htmlentities($result->PackageType);?></h6>
					<p><b>Package Location :</b> <?php echo htmlentities($result->PackageLocation);?></p>
					<p><b>Features</b> <?php echo htmlentities($result->PackageFetures);?></p>
				</div>
				<div class="col-md-3 room-right wow fadeInRight animated" data-wow-delay=".5s">
					<h5><?php echo e(pricing_format_inr($result->PackagePrice));?></h5>
					<p><small>Estimated day 1 price</small></p>
					<a href="package-details.php?pkgid=<?php echo htmlentities($result->PackageId);?>" class="view">Details</a>
				</div>
				<div class="clearfix"></div>
			</div>

<?php }} else { ?>
			<p>No packages matched your search.</p>
<?php } ?>
			
		
		
		</div>
	</div>
</div>
<!--- /rooms ---->

<!--- /footer-top ---->
<?php include('includes/footer.php');?>
<!-- signup -->
<?php include('includes/signup.php');?>			
<!-- //signu -->
<!-- signin -->
<?php include('includes/signin.php');?>			
<!-- //signin -->
<!-- write us -->
<?php include('includes/write-us.php');?>			
<!-- //write us -->
</body>
</html>
