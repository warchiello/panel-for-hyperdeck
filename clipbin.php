<?php require('scripts.php'); if($enable_login == "true"){require('_login.php');}else{} ?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN"
"http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">
<html>
	<head>
		<meta name="apple-mobile-web-app-capable" content="yes">
		<meta name="viewport" content="width=1200, user-scalable=0">
		<meta name="apple-mobile-web-app-status-bar-style" content="default">
		<link rel="apple-touch-icon" href="images/hdcpIcon.png">
		<title>HyperDeck Clip Bin</title>
		<link rel="stylesheet" type="text/css" href="css/default.css" media="screen" />
		<link rel="icon" type="image/png" href="images/hdcpIcon.png">
		<link rel="stylesheet" href="assets/css/font-awesome.min.css">
	</head>
	<body>
		<div class="hdcpInterface">
		<!--START HEADER -->
			<div class="hdcpHeader">
				<a href="clipbin.php">
					<?php if($enable_avatar == "true"){echo '<img class="avatar left" src="'.$avatar.'">';} ?>
					<div class="left large"><span class="bold large">HyperDeck</span>
					<?php echo (isset($dname)&&!empty($dname)) ? $dname : ''; ?> Clip Bin</div>
				</a>
				
				<?php
					//check for admin usage, if true show logout option
					if($enable_login == "true"){
						echo '<div class="right logout"><a href="logout.php">';
						if(strlen($login->username) <= "8"){
							echo $login->username;
						}else{
							echo substr_replace($login->username, "...", 7);
						}
						echo ' &rarr; Logout</a></div>';
					}
				?>
				<div class="right">
					<?php include_once('nav.php'); ?>
				</div>
				<?php
					if ( ! isset( $_GET['deck'] ) || empty( $_GET['deck'] ) ) {
						die('No deck ID set.');
					}
				?>
			</div>
			<!--END HEADER-->
			<!--BIN GUI-->
			<div class="hdcpDeckBorderlt">
				<div class="right">
					<div class="logout" style="margin-right:20px;">
						<a href="?deck=<?php echo $_GET['deck']; ?>">Refresh clips</a>
					</div>
					<?php
						//cue and clip+play interface
						if( isset( $_GET['cmd'] ) && $_GET['cmd'] == "cpfalse"){
							$cplink = "gtc";
							echo "<div class='right logout' style='margin-right:20px;clear:right;background-color:red;'><a style='background-color:inherit;' href='?deck=".$_GET['deck']."&cmd=cptrue'>Cue & Play</a></div>";
						}elseif( isset( $_GET['cmd'] ) && $_GET['cmd'] == "cptrue"){
							$cplink = "gtcp";
							echo "<div class='right logout' style='margin-right:20px;clear:right;background-color:green;'><a style='background-color:inherit;' href='?deck=".$_GET['deck']."&cmd=cpfalse'>Cue & Play</a></div>";
						}elseif($_COOKIE['clipandplay'] == "true"){
							$cplink = "gtcp";
							echo "<div class='right logout' style='margin-right:20px;clear:right;background-color:green;'><a style='background-color:inherit;' href='?deck=".$_GET['deck']."&cmd=cpfalse'>Cue & Play</a></div>";
						}else{
							$cplink = "gtc";
							echo "<div class='right logout' style='margin-right:20px;clear:right;background-color:red;'><a style='background-color:inherit;' href='?deck=".$_GET['deck']."&cmd=cptrue'>Cue & Play</a></div>";
						}
					?>
				</div>
				<div class="cliplist">
					<ul>
					<?php
						//determine model of deck for download ability
						foreach($config as $hd => $deck){
							if( $hd !== 'global' ){
								if($deck['number'] == $_GET['deck']){
									$model = $deck['model'];
								}
							}
						}
						//get the deck info and print it
						if(isset($noDeck)){
							echo $noDeck;
						}
						//connect to deck
						//---------------------------------------------------------------
						//Read the deck's currently active slot, clip count, and clip
						//list using the same robust "read until the blank line"
						//parsing the info panel uses (hdcp_query), instead of the
						//fixed-line-count reads this used to rely on - those assumed
						//an exact number of response lines per deck model, which is
						//fragile and breaks the moment a real deck's response shape
						//doesn't match those hardcoded counts.
						//---------------------------------------------------------------
						$slotInfo = hdcp_query($bin, "slot info");
						$slotStatus = isset($slotInfo['status']) ? $slotInfo['status'] : '';
						//check if deck has a disk
						if($slotStatus === '' || $slotStatus === 'empty' || (isset($slotInfo['__status']) && strpos($slotInfo['__status'], '105') === 0)){
							echo "<span style='font-size:22px;'>Error: There are currently no disks in ".$currentDeck."</span>";
						}else{
							$clipCountInfo = hdcp_query($bin, trim($clipsCount));
							$output = isset($clipCountInfo['clip count']) ? intval($clipCountInfo['clip count']) : 0;
							if($output == 0){
								echo "<span style='font-size:22px;'>Notice: There are no clips on the selected disk in ".$currentDeck."</span>";
							}else{
								$clipsInfo = hdcp_query($bin, trim($clipsList));
								for ($i = 1; $i <= $output; $i++){
									if ( ! isset($clipsInfo[$i]) ) { continue; }
									$clipName = trim($clipsInfo[$i]); //"{name} {start tc} {duration tc}"
									if($model == "mini"){
										$dl = explode(" ", $clipName);
										echo "<li><a href='clipbin.php?deck=".$_GET['deck']."&cmd=".$cplink."&clip=". $i ."'>". substr($clipName,0,60) ."</a>...<a href='download.php?deck=".$_GET['deck']."&slot=".urlencode(isset($slotInfo['volume name']) ? $slotInfo['volume name'] : '')."&file=".urlencode($dl[0])."'><i class='fa fa-download fa-fw' style='color:#e2e1dd;background:transparent;float:right;line-height:40px;margin-right:10px;'></i></a></li>";
									}else{
										echo "<li><a href='clipbin.php?deck=".$_GET['deck']."&cmd=".$cplink."&clip=". $i ."'>". substr($clipName,0,60) ."</a>...</li>";
									}
								}
							}
						}
						fclose($bin);
					?>
					</ul>
				</div>
			</div>
			<!--BIN GUI END-->
			<!--FOOTER START-->  
			<?php include_once('footer.php'); ?>
			<!--FOOTER END-->
		</div>
	</body>
</html>
