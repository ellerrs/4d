<?php defined('SYSPATH') OR die('No direct access allowed.'); ?>
<!DOCTYPE html>
<html lang="en">
	<head>

		<meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
		<title><?php echo '4Divisions'.(isset($title) ? ' : '.html::specialchars($title) : '') ?></title>

		<script type="text/javascript" src="/_public/js/jquery-1.3.2.min.js"></script>
		<script type="text/javascript" src="/_public/js/jquery.plugins.min.js"></script>
		<script type="text/javascript" src="/_public/js/4d.js"></script>

		<link rel="stylesheet" type="text/css" media="all" href="/_public/css/4d.css" />
		<link rel="stylesheet" type="text/css" media="all" href="/_public/css/custom-theme/jquery-ui-1.7.2.custom.css" />
		<link rel="stylesheet" type="text/css" media="all" href="/_public/css/jquery.gritter.css" />
		<link rel="stylesheet" type="text/css" media="handheld, only screen and (max-device-width: 480px)" href="/_public/css/mobile.css" />

	</head>
	<body class="<?php echo (isset($title) ? str_replace(' ','_',strtolower(html::specialchars($title))) : '') ?>">

		<?php if(isset($_SESSION['user']) && $_SESSION['user']!='') { ?>
			<div id="user_id" title="<?php echo $_SESSION['user']; ?>"></div>
		<?php } ?>

		<div id="header">
			<h1 class=""><a href="/">Four Divisions</a></h1>

			<?php if(isset($_SESSION['user']) && $_SESSION['user']!='') { ?>
			<div id="navigation" class="">
				<ul>
					<li><a href="/user/logout" class="logoutlink">Log Out<br /><span>Bye for now!</span></a></li>
					<li><a href="/dashboard" class="dashboardlink">Dashboard<br /><span>Options/Games</span></a></li>
					<li><a href="/help" class="helplink">Help<br /><span>&amp; other oddities</span></a></li>
					<li class="info"></li>
				</ul>
			</div>
			<?php } ?>
		</div>
		
		<div id="messages">
			<div id="message_queue">
				<?php if(isset($messages)) echo $messages; ?>
			</div>
		</div>

		<div id="view">
			<?php if(isset($content)) echo $content; ?>
		</div>

		<div id="footer">
			Rendered in {execution_time} seconds, using {memory_usage} of memory
		</div>

	</body>
</html>
