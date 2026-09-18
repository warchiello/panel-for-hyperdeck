<?php require('scripts.php'); if($enable_login == "true"){require('_login.php');}else{}
//Simple read-only status board: everything on this page is display only,
//no forms or transport buttons. Meant to be left open on a second screen
//or checked from a phone on the same network to see at a glance which
//decks are recording and how much room is left, without needing the
//full control panel (and without being able to accidentally hit a
//transport button from across the room).
//
//The board is "live" via deck_status.php polling (see the script at the
//bottom) - it patches each card in place every few seconds instead of
//reloading the page. The meta refresh below is now just a long-interval
//safety net in case JavaScript is disabled or the poll loop ever stalls,
//not the primary update mechanism any more.
if(isset($_GET['rfr'])){$refresh = $_GET['rfr'];}else{$refresh = "300";}
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
				display:block;
				text-decoration:none;
				color:inherit;
				cursor:pointer;
				transition:background-color 0.15s ease;
			}
			.statusCard:hover{
				background-color:#3d3d3d;
			}
			/* The site-wide "*{background-color:rgb(33,33,33);}" rule in
			   default.css would otherwise paint each of these plain divs
			   with its own dark box, showing up as odd stacked rectangles
			   inside the lighter card background - keep just these five
			   transparent (not every element in the card - the pill-*
			   classes below need to keep their own colored backgrounds). */
			.statusCard .deckname,
			.statusCard .deckip,
			.statusCard .statusOnline,
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
			/* One "live" dot in the header so it's obvious at a glance that
			   this board updates itself rather than needing a refresh. */
			.liveDot{
				display:inline-block;
				width:8px;
				height:8px;
				border-radius:50%;
				background:#45D40C;
				margin-right:6px;
				vertical-align:middle;
			}
			.liveDot.stale{ background:#ff5c5c; }
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
				<div class="right" style="margin-right:20px;line-height:60px;font-size:12px;color:#8a8a86;" id="hdcpLiveIndicator">
					<span class="liveDot" id="hdcpLiveDot"></span><span id="hdcpLiveLabel">Live</span>
				</div>
			</div>
		<!--END HEADER-->

			<div class="statusBoard" id="hdcpStatusBoard">
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
						$hdcpSnap = hdcp_deck_snapshot(${"hd".$deck['number']});
						if (is_resource(${"hd".$deck['number']})){ fclose(${"hd".$deck['number']}); }
						$hdcpOutput = $hdcpSnap['output'];
						$hdcpTc = $hdcpSnap['tc'];
						$hdcpFps = $hdcpSnap['fps'];
						$hdcpSlotId = $hdcpSnap['slotId'];
						$hdcpSlotRemain = $hdcpSnap['slotRemain'];
						$hdcpSlotTotal = $hdcpSnap['slotTotal'];
					}

					$hdcpTcDir = '';
					if ($hdcpOutput == 'record' || $hdcpOutput == 'play' || $hdcpOutput == 'forward'){
						$hdcpTcDir = 'fwd';
					}elseif ($hdcpOutput == 'rewind'){
						$hdcpTcDir = 'rev';
					}
			?>
				<a class="statusCard" href="devinfo.php?deck=<?php echo htmlspecialchars($deck['number']); ?>" data-hdcp-deck="<?php echo htmlspecialchars($deck['number']); ?>">
					<div class="deckname"><?php echo htmlspecialchars($deck['name']); ?></div>
					<div class="deckip"><?php echo htmlspecialchars($deck['ip']); ?></div>
					<div class="statusOffline" data-hdcp-offline-block style="<?php echo $hdcpOnline ? 'display:none;' : ''; ?>">
						<span class="pill pill-red">Offline</span>
					</div>
					<div class="statusOnline" data-hdcp-online-block style="<?php echo $hdcpOnline ? '' : 'display:none;'; ?>">
						<span class="pill pill-<?php echo hdcp_transport_class($hdcpOutput); ?>" data-hdcp-status-pill><?php echo $hdcpOutput == 'record' ? 'REC' : ($hdcpOutput !== '' ? htmlspecialchars($hdcpOutput) : 'unknown'); ?></span>
						<div class="statusTc tcDisplay" data-hdcp-live-tc data-hdcp-status-tc data-hdcp-dir="<?php echo $hdcpTcDir; ?>" data-hdcp-fps="<?php echo htmlspecialchars($hdcpFps); ?>" style="<?php echo $hdcpTc ? '' : 'display:none;'; ?>"><?php echo $hdcpTc ? htmlspecialchars($hdcpTc) : '--:--:--:--'; ?></div>
						<div class="statusSlot" data-hdcp-status-slot-block style="<?php echo $hdcpSlotId !== null ? '' : 'display:none;'; ?>">
							<span class="pill pill-small pill-<?php echo hdcp_time_class($hdcpSlotRemain); ?>" data-hdcp-status-slot-pill<?php echo $hdcpSlotTotal !== null ? ' title="Total capacity: '.htmlspecialchars(hdcp_bytes_to_gb($hdcpSlotTotal)).'"' : ''; ?>>Slot <span data-hdcp-status-slot-id><?php echo htmlspecialchars($hdcpSlotId); ?></span> &middot; <span data-hdcp-live-remain<?php echo ($hdcpOutput == 'record' && $hdcpSlotRemain !== null) ? ' data-hdcp-seconds="'.intval($hdcpSlotRemain).'"' : ''; ?>><?php echo hdcp_seconds_to_tc($hdcpSlotRemain); ?></span> left</span>
						</div>
					</div>
				</a>
			<?php
				}
				if ( ! $hdcpAnyDeck ){
					echo '<div class="statusCard" style="width:100%;text-align:center;cursor:default;">No decks configured. <a href="settings.php">Add a deck</a>.</div>';
				}
			?>
			</div>

			<!--FOOTER START-->
			<?php include_once('footer.php'); ?>
			<!--FOOTER END-->
		</div>
		<script>
		(function(){
			function pad(n, len){
				n = String(Math.max(0, Math.floor(n)));
				while (n.length < len){ n = '0' + n; }
				return n;
			}

			//---------------------------------------------------------------
			//Purely cosmetic tickers, so the numbers keep moving between
			//polls instead of sitting frozen for the next few seconds. Both
			//read their current state (dir/fps/seconds) fresh every tick,
			//straight off the element's attributes - poll() below only ever
			//needs to update those attributes, it never has to touch these
			//loops directly.
			//---------------------------------------------------------------
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

			var hdcpTcCarry = new WeakMap();
			var hdcpLastTcTick = Date.now();
			function tickTc(){
				var now = Date.now();
				var elapsedMs = now - hdcpLastTcTick;
				hdcpLastTcTick = now;
				var els = document.querySelectorAll('[data-hdcp-live-tc]');
				for (var i = 0; i < els.length; i++){
					var el = els[i];
					var dir = el.getAttribute('data-hdcp-dir');
					if ( ! dir ){ continue; } //stopped/paused/offline - nothing to advance
					var fps = parseFloat(el.getAttribute('data-hdcp-fps')) || 30;
					var wholeFps = Math.max(1, Math.round(fps));
					var carry = (hdcpTcCarry.get(el) || 0) + (elapsedMs / 1000) * fps;
					var framesToAdd = Math.floor(carry);
					if (framesToAdd <= 0){ hdcpTcCarry.set(el, carry); continue; }
					hdcpTcCarry.set(el, carry - framesToAdd);
					var parts = el.textContent.split(':');
					if (parts.length !== 4){ continue; }
					var hh = parseInt(parts[0], 10) || 0;
					var mm = parseInt(parts[1], 10) || 0;
					var ss = parseInt(parts[2], 10) || 0;
					var ff = parseInt(parts[3], 10) || 0;
					var totalFrames = ((hh * 3600) + (mm * 60) + ss) * wholeFps + ff;
					totalFrames += (dir === 'rev') ? -framesToAdd : framesToAdd;
					if (totalFrames < 0){ totalFrames = 0; }
					var framesOnly = totalFrames % wholeFps;
					var totalSeconds = Math.floor(totalFrames / wholeFps);
					var secOnly = totalSeconds % 60;
					var minOnly = Math.floor(totalSeconds / 60) % 60;
					var hrOnly = Math.floor(totalSeconds / 3600);
					el.textContent = pad(hrOnly, 2) + ':' + pad(minOnly, 2) + ':' + pad(secOnly, 2) + ':' + pad(framesOnly, 2);
				}
			}

			setInterval(tickRemaining, 1000);
			setInterval(tickTc, 40);

			//---------------------------------------------------------------
			//The actual "live" part - poll deck_status.php and patch each
			//card in place. Never touches anything the user could be
			//interacting with (this page has no forms or buttons at all),
			//so there's nothing to lose by rewriting freely every cycle.
			//---------------------------------------------------------------
			var HDCP_POLL_MS = 4000;
			var hdcpLiveDot = document.getElementById('hdcpLiveDot');
			var hdcpLiveLabel = document.getElementById('hdcpLiveLabel');
			var hdcpMissedPolls = 0;

			function hdcpMarkLive(ok){
				if (ok){
					hdcpMissedPolls = 0;
					if (hdcpLiveDot){ hdcpLiveDot.classList.remove('stale'); }
					if (hdcpLiveLabel){ hdcpLiveLabel.textContent = 'Live'; }
				}else{
					hdcpMissedPolls++;
					if (hdcpMissedPolls >= 2 && hdcpLiveDot){ hdcpLiveDot.classList.add('stale'); }
					if (hdcpMissedPolls >= 2 && hdcpLiveLabel){ hdcpLiveLabel.textContent = 'Update failed'; }
				}
			}

			function applySnapshot(deckNum, snap){
				var card = document.querySelector('.statusCard[data-hdcp-deck="' + deckNum + '"]');
				if ( ! card ){ return; } //deck added/removed from config.txt since load - full reload picks that up

				var offlineBlock = card.querySelector('[data-hdcp-offline-block]');
				var onlineBlock = card.querySelector('[data-hdcp-online-block]');
				if (offlineBlock){ offlineBlock.style.display = snap.online ? 'none' : ''; }
				if (onlineBlock){ onlineBlock.style.display = snap.online ? '' : 'none'; }
				if ( ! snap.online ){ return; }

				var pill = card.querySelector('[data-hdcp-status-pill]');
				if (pill){
					pill.className = 'pill pill-' + snap.transportClass;
					pill.textContent = snap.outputLabel ? snap.outputLabel : 'unknown';
				}

				var tcEl = card.querySelector('[data-hdcp-status-tc]');
				if (tcEl){
					if (snap.tc){
						tcEl.style.display = '';
						tcEl.textContent = snap.tc;
						tcEl.setAttribute('data-hdcp-dir', snap.tcDir || '');
						tcEl.setAttribute('data-hdcp-fps', snap.fps || 30);
					}else{
						tcEl.style.display = 'none';
					}
				}

				var slotBlock = card.querySelector('[data-hdcp-status-slot-block]');
				if (slotBlock){
					if (snap.slotId !== null && snap.slotId !== undefined){
						slotBlock.style.display = '';
						var slotPill = card.querySelector('[data-hdcp-status-slot-pill]');
						if (slotPill){
							slotPill.className = 'pill pill-small pill-' + snap.slotTimeClass;
							if (snap.slotTotalGb){ slotPill.title = 'Total capacity: ' + snap.slotTotalGb; }
						}
						var slotIdEl = card.querySelector('[data-hdcp-status-slot-id]');
						if (slotIdEl){ slotIdEl.textContent = snap.slotId; }
						var remainEl = card.querySelector('[data-hdcp-live-remain]');
						if (remainEl){
							remainEl.textContent = snap.slotRemainTc || '00:00:00';
							if (snap.recording && snap.slotRemain !== null && snap.slotRemain !== undefined){
								remainEl.setAttribute('data-hdcp-seconds', snap.slotRemain);
							}else{
								remainEl.removeAttribute('data-hdcp-seconds');
							}
						}
					}else{
						slotBlock.style.display = 'none';
					}
				}
			}

			function poll(){
				fetch('deck_status.php', {cache: 'no-store'}).then(function(resp){
					if ( ! resp.ok ){ throw new Error('bad status ' + resp.status); }
					return resp.json();
				}).then(function(data){
					for (var deckNum in data){
						if (Object.prototype.hasOwnProperty.call(data, deckNum)){
							applySnapshot(deckNum, data[deckNum]);
						}
					}
					hdcpMarkLive(true);
				}).catch(function(){
					hdcpMarkLive(false);
				});
			}

			setInterval(poll, HDCP_POLL_MS);
			poll();
		})();
		</script>
	</body>
</html>
