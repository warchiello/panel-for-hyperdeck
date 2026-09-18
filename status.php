<?php require('scripts.php'); if($enable_login == "true"){require('_login.php');}else{}
//Simple read-only status board: everything on this page is display only,
//no forms or transport buttons. Meant to be left open on a second screen
//or checked from a phone on the same network to see at a glance which
//decks are recording and how much room is left, without needing the
//full control panel (and without being able to accidentally hit a
//transport button from across the room).
if(isset($_GET['rfr'])){$refresh = $_GET['rfr'];}else{$refresh = "30";}
if (is_numeric($refresh) && $refresh > 0){ header("Refresh:$refresh"); }
?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN"
"http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">
<html>
	<head>
		<meta name="apple-mobile-web-app-capable" content="yes">
		<meta name="viewport" content="width=device-width, initial-scale=1">
		<meta name="apple-mobile-web-app-status-bar-style" content="default">
		<link rel="apple-touch-icon" href="images/hdcpIcon.png">
		<title>HyperDeck Status Board</title>
		<link rel="stylesheet" type="text/css" href="css/default.css" media="screen" />
		<link rel="icon" type="image/png" href="images/hdcpIcon.png">
		<link rel="stylesheet" href="assets/css/font-awesome.min.css">
		<style>
			.statusBoard{
				display:flex;
				flex-wrap:wrap;
				gap:16px;
				padding:20px;
				box-sizing:border-box;
			}
			.statusCard{
				background-color:#333;
				border-radius:8px;
				padding:20px 24px;
				width:320px;
				box-sizing:border-box;
			}
			/* The site-wide "*{background-color:rgb(33,33,33);}" rule in
			   default.css would otherwise paint each of these plain divs
			   with its own dark box, showing up as odd stacked rectangles
			   inside the lighter card background - keep just these four
			   transparent (not every element in the card - the pill-*
			   classes below need to keep their own colored backgrounds). */
			.statusCard .deckname,
			.statusCard .deckip,
			.statusCard .statusTc,
			.statusCard .statusSlot,
			.statusCard .statusOffline{
				background-color:transparent;
			}
			.statusCard .deckname{
				font-size:22px;
				font-weight:bold;
				margin-bottom:2px;
			}
			.statusCard .deckip{
				font-size:12px;
				color:#8a8a86;
				margin-bottom:14px;
			}
			.statusCard .statusTc{
				font-family:monospace;
				font-size:34px;
				letter-spacing:1px;
				margin:10px 0 14px 0;
			}
			.statusCard .statusSlot{
				font-size:14px;
				color:#cfcfcc;
			}
			.statusCard .statusOffline{
				font-size:14px;
				color:#8a8a86;
				padding:30px 0 34px 0;
			}
		</style>
	</head>
	<body>
		<div class="hdcpInterface">
		<!--START HEADER -->
			<div class="hdcpHeader">
				<a href="" onclick="location.reload();return false;">
					<?php if($enable_avatar == "true"){ echo '<img class="avatar left" src="'.$avatar.'">';} ?>
					<div class="left large"><span class="bold large">HyperDeck</span> Status Board</div>
				</a>
				<?php
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
			</div>
		<!--END HEADER-->

			<div class="statusBoard">
			<?php
				$hdcpAnyDeck = false;
				foreach($config as $hd => $deck){
					if( $hd === 'global' ){ continue; }
					if( ! isset($deck['enable']) || $deck['enable'] != "true" ){ continue; }
					$hdcpAnyDeck = true;

					$hdcpOnline = isset(${"online_d".$deck['number']}) && ${"online_d".$deck['number']};
					$hdcpOutput = '';
					$hdcpTc = null;
					$hdcpSlotId = null;
					$hdcpSlotRemain = null;
					$hdcpSlotTotal = null;
					$hdcpFps = 30;

					if ($hdcpOnline){
						//Nothing else has talked to this deck yet on this page load
						//(status.php never sends any transport commands), so there
						//are no leftover acks to drain before asking for status.
						$hdcpTransport = hdcp_query(${"hd".$deck['number']}, "transport info");
						$hdcpOutput = isset($hdcpTransport['status']) ? $hdcpTransport['status'] : '';
						$hdcpTc = isset($hdcpTransport['display timecode']) ? $hdcpTransport['display timecode'] : null;
						$hdcpFps = isset($hdcpTransport['video format']) ? hdcp_fps_from_format($hdcpTransport['video format']) : 30;

						$hdcpActiveSlot = isset($hdcpTransport['slot id']) ? $hdcpTransport['slot id'] : '';
						if ($hdcpActiveSlot !== '' && $hdcpActiveSlot !== 'none'){
							$hdcpSlotInfo = hdcp_query(${"hd".$deck['number']}, "slot info: slot id: ".$hdcpActiveSlot);
							if (isset($hdcpSlotInfo['status']) && $hdcpSlotInfo['status'] !== 'empty'){
								$hdcpSlotId = $hdcpActiveSlot;
								$hdcpSlotRemain = isset($hdcpSlotInfo['recording time']) ? intval($hdcpSlotInfo['recording time']) : null;
								$hdcpSlotTotal = isset($hdcpSlotInfo['total size']) ? floatval($hdcpSlotInfo['total size']) : null;
							}
						}
						if (is_resource(${"hd".$deck['number']})){ fclose(${"hd".$deck['number']}); }
					}

					$hdcpTcDir = '';
					if ($hdcpOutput == 'record' || $hdcpOutput == 'play' || $hdcpOutput == 'forward'){
						$hdcpTcDir = 'fwd';
					}elseif ($hdcpOutput == 'rewind'){
						$hdcpTcDir = 'rev';
					}
			?>
				<div class="statusCard">
					<div class="deckname"><?php echo htmlspecialchars($deck['name']); ?></div>
					<div class="deckip"><?php echo htmlspecialchars($deck['ip']); ?></div>
					<?php if ( ! $hdcpOnline ){ ?>
						<div class="statusOffline"><span class="pill pill-red">Offline</span></div>
					<?php }else{ ?>
						<span class="pill pill-<?php echo hdcp_transport_class($hdcpOutput); ?>"><?php echo $hdcpOutput == 'record' ? 'REC' : ($hdcpOutput !== '' ? htmlspecialchars($hdcpOutput) : 'unknown'); ?></span>
						<?php if ($hdcpTc){ ?>
						<div class="statusTc tcDisplay" data-hdcp-live-tc<?php echo $hdcpTcDir ? ' data-hdcp-dir="'.$hdcpTcDir.'" data-hdcp-fps="'.htmlspecialchars($hdcpFps).'"' : ''; ?>><?php echo htmlspecialchars($hdcpTc); ?></div>
						<?php } ?>
						<?php if ($hdcpSlotId !== null){ ?>
						<div class="statusSlot">
							<span class="pill pill-small pill-<?php echo hdcp_time_class($hdcpSlotRemain); ?>">Slot <?php echo htmlspecialchars($hdcpSlotId); ?> &middot; <span data-hdcp-live-remain<?php echo ($hdcpOutput == 'record' && $hdcpSlotRemain !== null) ? ' data-hdcp-seconds="'.intval($hdcpSlotRemain).'"' : ''; ?>><?php echo hdcp_seconds_to_tc($hdcpSlotRemain); ?></span> left<?php echo $hdcpSlotTotal !== null ? ' of '.hdcp_bytes_to_gb($hdcpSlotTotal) : ''; ?></span>
						</div>
						<?php } ?>
					<?php } ?>
				</div>
			<?php
				}
				if ( ! $hdcpAnyDeck ){
					echo '<div class="statusCard" style="width:100%;text-align:center;">No decks configured. <a href="settings.php">Add a deck</a>.</div>';
				}
			?>
			</div>

			<!--FOOTER START-->
			<?php include_once('footer.php'); ?>
			<!--FOOTER END-->
		</div>
		<script>
		// Same purely cosmetic live ticker as the main control panel - see
		// index.php for the full explanation. Kept independent (rather than
		// a shared .js include) since this page's markup is intentionally
		// much simpler and this is the only script either page needs.
		(function(){
			function pad(n, len){
				n = String(Math.max(0, Math.floor(n)));
				while (n.length < len){ n = '0' + n; }
				return n;
			}

			function tickRemaining(){
				var els = document.querySelectorAll('[data-hdcp-live-remain][data-hdcp-seconds]');
				for (var i = 0; i < els.length; i++){
					var el = els[i];
					var secs = parseInt(el.getAttribute('data-hdcp-seconds'), 10);
					if (isNaN(secs)){ continue; }
					secs = Math.max(0, secs - 1);
					el.setAttribute('data-hdcp-seconds', secs);
					var hh = Math.floor(secs / 3600);
					var mm = Math.floor(secs / 60) % 60;
					var ss = secs % 60;
					el.textContent = pad(hh, 2) + ':' + pad(mm, 2) + ':' + pad(ss, 2);
				}
			}

			var tcEls = document.querySelectorAll('[data-hdcp-live-tc][data-hdcp-dir]');
			for (var i = 0; i < tcEls.length; i++){
				(function(el){
					var fps = parseFloat(el.getAttribute('data-hdcp-fps')) || 30;
					var intervalMs = 1000 / fps;
					setInterval(function(){
						var dir = el.getAttribute('data-hdcp-dir');
						var parts = el.textContent.split(':');
						if (parts.length !== 4){ return; }
						var hh = parseInt(parts[0], 10) || 0;
						var mm = parseInt(parts[1], 10) || 0;
						var ss = parseInt(parts[2], 10) || 0;
						var ff = parseInt(parts[3], 10) || 0;
						var wholeFps = Math.max(1, Math.round(fps));
						var totalFrames = ((hh * 3600) + (mm * 60) + ss) * wholeFps + ff;
						totalFrames += (dir === 'rev') ? -1 : 1;
						if (totalFrames < 0){ totalFrames = 0; }
						var framesOnly = totalFrames % wholeFps;
						var totalSeconds = Math.floor(totalFrames / wholeFps);
						var secOnly = totalSeconds % 60;
						var minOnly = Math.floor(totalSeconds / 60) % 60;
						var hrOnly = Math.floor(totalSeconds / 3600);
						el.textContent = pad(hrOnly, 2) + ':' + pad(minOnly, 2) + ':' + pad(secOnly, 2) + ':' + pad(framesOnly, 2);
					}, intervalMs);
				})(tcEls[i]);
			}

			setInterval(tickRemaining, 1000);
		})();
		</script>
	</body>
</html>
