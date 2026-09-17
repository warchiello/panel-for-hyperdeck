<?php require('scripts.php'); if($enable_login == "true"){require('_login.php');}else{}
if(isset($_GET['rfr'])){$refresh = $_GET['rfr'];}else{$refresh = "60";}header("Refresh:$refresh");
?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN"
"http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">
<html>
	<head>
		<meta name="apple-mobile-web-app-capable" content="yes">
		<meta name="viewport" content="width=1200, user-scalable=0">
		<meta name="apple-mobile-web-app-status-bar-style" content="default">
		<link rel="apple-touch-icon" href="images/hdcpIcon.png">
		<title>HyperDeck Device Info</title>
		<link rel="stylesheet" type="text/css" href="css/default.css" media="screen" />
		<link rel="icon" type="image/png" href="images/hdcpIcon.png">
		<link rel="stylesheet" href="assets/css/font-awesome.min.css">
	</head>
	<body>
		<div class="hdcpInterface">
		<!--START HEADER -->
			<div class="hdcpHeader">
				<a href="" onclick="location.reload();">
					<?php if($enable_avatar == "true"){ echo '<img class="avatar left" src="'.$avatar.'">';} ?>
					<div class="left large" style="overflow:hidden; width:500px;height:52px;">
						<span class="bold large">HyperDeck </span><?php  echo (isset($dname)&&!empty($dname)) ? $dname : ''; ?> Info
					</div>
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
				<form method="get" action="?">
					<div class="right logout refresh" style="margin-right: 20px;">
						<input type="hidden" name="deck" value="<?php echo $_GET['deck']; ?>">Refresh
						<input name="rfr" type="text" value="<?php echo $refresh; ?>" size="4">
						<input type="submit" value="set">
					</div>
				</form>
			</div>
			<!--END HEADER-->

			<!--INFO PANEL-->
			<?php
				//---------------------------------------------------------------
				//Gather everything up front so the markup below just displays it
				//---------------------------------------------------------------
				$connected = is_resource($bin);
				if ($connected){
					stream_set_timeout($bin, 3);
					$transport = hdcp_query($bin, "transport info");
					$slot1     = hdcp_query($bin, "slot info: slot id: 1");
					$slot2     = hdcp_query($bin, "slot info: slot id: 2");
					$cfg       = hdcp_query($bin, "configuration");

					//Current clip name, if the deck is parked on one
					$clipName = null;
					$clipId = isset($transport['clip id']) ? $transport['clip id'] : 'none';
					if ($clipId !== 'none' && $clipId !== '' ){
						$clips = hdcp_query($bin, "clips get");
						if (isset($clips[$clipId])){
							//"{name} {start tc} {duration tc}" - name is the first token
							$clipFields = explode(' ', trim($clips[$clipId]));
							$clipName = $clipFields[0];
						}
					}
					fclose($bin);
				}
			?>

			<?php if ( ! $connected ){ ?>
				<div class="hdcpDeck hdcpDeckBorderlt">
					<div class="statCard">
						<span class="pill pill-red">Offline</span>
						<span class="statValue" style="margin-left:16px;">Could not connect to <?php echo htmlspecialchars($currentDeck); ?> (<?php echo htmlspecialchars($currentIp); ?>).</span>
					</div>
				</div>
			<?php }else{ ?>

				<!--TRANSPORT-->
				<div class="hdcpDeck hdcpDeckBorderlt">
					<div class="hdcpDblBox hdcpBoxRborder left" style="line-height: 37px;">
						<div name="deckname" class="deckname medium">
							<br>Transport
						</div>
					</div>
					<div class="statCard left">
						<div class="statGroup">
							<div class="statLabel">Status</div>
							<span class="pill pill-<?php echo hdcp_transport_class(isset($transport['status']) ? $transport['status'] : ''); ?>">
								<?php echo isset($transport['status']) ? htmlspecialchars($transport['status']) : 'unknown'; ?>
							</span>
						</div>
						<div class="statGroup">
							<div class="statLabel">Timecode</div>
							<div class="tcDisplay"><?php echo isset($transport['display timecode']) ? htmlspecialchars($transport['display timecode']) : '--:--:--:--'; ?></div>
						</div>
						<div class="statGroup">
							<div class="statLabel">Loop</div>
							<span class="pill pill-<?php echo (isset($transport['loop']) && $transport['loop']=='true') ? 'green' : 'gray'; ?>">
								<?php echo (isset($transport['loop']) && $transport['loop']=='true') ? 'On' : 'Off'; ?>
							</span>
						</div>
						<div class="statGroup">
							<div class="statLabel">Slot</div>
							<div class="statValue"><?php echo isset($transport['slot id']) ? htmlspecialchars($transport['slot id']) : '—'; ?></div>
						</div>
					</div>
				</div>

				<!--CURRENT CLIP-->
				<div class="hdcpDeck hdcpDeckBorderlt">
					<div class="hdcpDblBox hdcpBoxRborder left" style="line-height: 37px;">
						<div name="deckname" class="deckname medium">
							<br>Current Clip
						</div>
					</div>
					<div class="statCard left">
						<div class="statGroup" style="min-width:340px;">
							<div class="statLabel">Clip Name</div>
							<div class="statValue"><?php echo $clipName ? htmlspecialchars($clipName) : 'Not in a playback mode, or no clips on the timeline'; ?></div>
						</div>
						<div class="statGroup">
							<div class="statLabel">Playback Format</div>
							<div class="statValue"><?php echo isset($transport['video format']) ? htmlspecialchars($transport['video format']) : '—'; ?></div>
						</div>
						<div class="statGroup">
							<div class="statLabel">Input Format</div>
							<div class="statValue"><?php echo isset($transport['input video format']) ? htmlspecialchars($transport['input video format']) : '—'; ?></div>
						</div>
					</div>
				</div>

				<!--SLOTS-->
				<?php foreach ( array(1=>$slot1, 2=>$slot2) as $slotNum => $slot ){
					$slotStatus = isset($slot['status']) ? $slot['status'] : 'empty';
					$remaining = isset($slot['recording time']) ? intval($slot['recording time']) : null;
					$total = isset($slot['total size']) ? floatval($slot['total size']) : null;
					$remBytes = isset($slot['remaining size']) ? floatval($slot['remaining size']) : null;
					$pct = ($total && $remBytes !== null) ? max(0, min(100, ($remBytes/$total)*100)) : 0;
				?>
				<div class="hdcpDeck hdcpDeckBorderlt">
					<div class="hdcpDblBox hdcpBoxRborder left" style="line-height: 37px;">
						<div name="deckname" class="deckname medium">
							<br>Slot <?php echo $slotNum; ?>
						</div>
					</div>
					<div class="statCard left">
						<div class="statGroup">
							<div class="statLabel">Status</div>
							<span class="pill pill-<?php echo hdcp_slot_class($slotStatus); ?>"><?php echo htmlspecialchars($slotStatus); ?></span>
						</div>
						<div class="statGroup">
							<div class="statLabel">Volume</div>
							<div class="statValue"><?php echo isset($slot['volume name']) && $slot['volume name'] !== '' ? htmlspecialchars($slot['volume name']) : '—'; ?></div>
						</div>
						<div class="statGroup">
							<div class="statLabel">Remaining Record Time</div>
							<div class="statValue statValue-<?php echo hdcp_time_class($remaining); ?>"><?php echo hdcp_seconds_to_tc($remaining); ?></div>
							<div class="remainBar"><div class="remainBarFill remainBarFill-<?php echo hdcp_time_class($remaining); ?>" style="width:<?php echo $pct; ?>%;"></div></div>
						</div>
						<div class="statGroup">
							<div class="statLabel">Capacity</div>
							<div class="statValue"><?php echo $total !== null ? hdcp_bytes_to_gb($total) : '—'; ?></div>
						</div>
					</div>
				</div>
				<?php } ?>

				<!--CONFIGURATION-->
				<div class="hdcpDeck hdcpDeckBorderlt">
					<div class="hdcpDblBox hdcpBoxRborder left" style="line-height: 37px;">
						<div name="deckname" class="deckname medium">
							<br>Configuration
						</div>
					</div>
					<div class="statCard left">
						<div class="statGroup">
							<div class="statLabel">Video Input</div>
							<div class="statValue"><?php echo isset($cfg['video input']) ? htmlspecialchars($cfg['video input']) : '—'; ?></div>
						</div>
						<div class="statGroup">
							<div class="statLabel">Audio Input</div>
							<div class="statValue"><?php echo isset($cfg['audio input']) ? htmlspecialchars($cfg['audio input']) : '—'; ?></div>
						</div>
						<div class="statGroup">
							<div class="statLabel">File Format</div>
							<div class="statValue"><?php echo isset($cfg['file format']) ? htmlspecialchars($cfg['file format']) : '—'; ?></div>
						</div>
						<div class="statGroup">
							<div class="statLabel">Audio Codec</div>
							<div class="statValue"><?php echo isset($cfg['audio codec']) ? htmlspecialchars($cfg['audio codec']) : '—'; ?></div>
						</div>
					</div>
				</div>

			<?php } ?>
			<!--INFO PANEL END-->

			<!--FOOTER START-->
			<?php include_once('footer.php'); ?>
			<!--FOOTER END-->
		</div>
	</body>
</html>
