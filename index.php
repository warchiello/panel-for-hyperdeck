<?php require('scripts.php'); if($enable_login == "true"){require('_login.php');}else{} ?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN"
"http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">
<html>
	<head>
		<meta name="apple-mobile-web-app-capable" content="yes">
		<meta name="viewport" content="width=1200, user-scalable=0">
		<meta name="apple-mobile-web-app-status-bar-style" content="default">
		<link rel="apple-touch-icon" href="images/hdcpIcon.png">
		<title>HyperDeck Control Panel</title>
		<link rel="stylesheet" type="text/css" href="css/default.css" media="screen" />
		<link rel="icon" type="image/png" href="images/hdcpIcon.png">
		<link rel="stylesheet" href="assets/css/font-awesome.min.css">
	</head>
	<body>
		<div class="hdcpInterface">
		<!--START HEADER -->
			<div class="hdcpHeader">
				<a href="?">
					<?php if($enable_avatar == "true"){ echo '<img class="avatar left" src="'.$avatar.'">';} ?>
					<div class="left large"><span class="bold large">HyperDeck</span> Control Panel</div>
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
		<!--SYNC START-->   
			<div name="deckall" class="hdcpDeck">
				<div class="hdcpDblBox left" style="line-height: 116px;">
					<div name="deckname" class="deckname large">
						Sync
					</div>
				</div>
				<div class="hdcpDblBox left">
					<div class="hdcpHalfBox hdcpBoxBborder" style="line-height: 94px;">
						Sync timecode:
					</div>
					<div class="hdcpHalfBox" style="line-height: 26px;">
						<form action="?cmd=tcjall" method="post" class="tcform">
							<input type="text" name="timecodeall" value="<?php echo $tcall;?>">
						</form>
					</div>
				</div>
				<div class="hdcpBox left">
					<a href="?cmd=trkbkall">
						<div class="hdcpButton trkbk"></div>
					</a>
				</div>
				<div class="hdcpBox left">
					<a href="?cmd=rwall">
						<div class="hdcpButton rw"></div>
					</a>
				</div>
				<div class="hdcpBox left">
					<a href="?cmd=playall">
						<div class="hdcpButton play"></div>
					</a>
				</div>
				<div class="hdcpBox left">
					<a href="?cmd=ffall">
						<div class="hdcpButton ff"></div>
					</a>
				</div>
				<div class="hdcpBox left">
					<a href="?cmd=trkfwall">
						<div class="hdcpButton trkfw"></div>
					</a>
				</div>
				<div class="hdcpBox left">
					<a href="?cmd=stopall">
						<div class="hdcpButton stop"></div>
					</a>
				</div>
				<div class="hdcpBox left">
					<a href="?cmd=recall">
						<div class="hdcpButtonRec rec"></div>
					</a>
				</div>
				<div class="hdcpBox left">
					<a href="?cmd=loopall">
						<div class="hdcpButton loop"></div>
					</a>
				</div>
		<!--SYNC END-->
		<!--CUSTOM FILE NAMING-->
			<?php
				if(preg_match('/%reel/', $fnUser) || preg_match('/%take/', $fnUser) || preg_match('/%scene/', $fnUser) || preg_match('/%custom/', $fnUser)){
			
				}else{
					echo '<!--';
				}
			?>
			<form method="get" action="?">
				<div name="naming" class="hdcpDeck hdcpDeckBorderlt rtsc" style="height:60px;">
					<div style="float:left;margin:20px auto;" class="bold">
						File Naming
					</div>
					<div style="float:right;margin:12px auto;">
						<?php
						if(preg_match('/%reel/', $fnUser)){
						echo 'Reel<input style="margin-left:8px;text-align:right;width:72px;border: none;background-color: #444;border-radius: 4px;padding: 2px 2px 2px 6px;margin-top: 5px;font-size: 16px;" type="text" name="reel" value="'.$reel.'">';
						}
						if(preg_match('/%take/', $fnUser)){
						echo '<span style="margin-left:30px;">Take<input style="margin-left:8px;text-align:right;width:72px;border: none;background-color: #444;border-radius: 4px;padding: 2px 2px 2px 6px;margin-top: 5px;font-size: 16px;" type="text" name="take" value="'.$take.'"></span>';
						}
						if(preg_match('/%scene/', $fnUser)){
						echo '<span style="margin-left:30px;">Scene<input style="margin-left:8px;text-align:right;width:72px;border: none;background-color: #444;border-radius: 4px;padding: 2px 2px 2px 6px;margin-top: 5px;font-size: 16px;" type="text" name="scene" value="'.$scene.'"></span>';
						}
						if(preg_match('/%custom/', $fnUser)){
						echo '<span style="margin-left:30px;">Custom<input style="margin-left:8px;text-align:left;width:210px;border: none;background-color: #444;border-radius: 4px;padding: 2px 2px 2px 6px;margin-top: 5px;font-size: 16px;" type="text" name="customfn" value="'.$customFn.'"></span>';
						}
						?>
						<input style="margin-left:8px;width:56px;border: none;background-color: #888;border-radius: 4px;padding: 2px 2px 2px 2px;font-size: 10px;" type="submit" value="SET ALL">
					</div>
				</div>
			</form>
			<?php
				if(preg_match('/%reel/', $fnUser) || preg_match('/%take/', $fnUser) || preg_match('/%scene/', $fnUser) || preg_match('/%custom/', $fnUser)){
			
				}else{
					echo '-->';
				}
			?>
			<!--END CUSTOM FILE NAMING-->
			
			<!-- ADD DECK IF NONE PRESENT -->
			<?php
				$x=0;
				foreach($config as $hd => $deck){
					if( isset( $deck['enable'] ) && $deck['enable'] == "true"){
						$x++;
					}
				}
				if($x == 0){
					echo '<div name="deckadd" class="hdcpDeck hdcpDeckBorderlt" style="text-align:center;vertical-align:middle;padding-top:60px;"><a href="settings.php"><img style="width:30px;margin: 11px 30px 0px 0px;" src="images/settings.png"><span class="large">Add a deck</span></a></div>';
				}
			?>
			<!-- END ADD -->
			<?php
				foreach($config as $hd => $deck){
					if( $hd !== 'global' ){
						if( isset( $deck['enable'] ) && $deck['enable'] == "true"){
			?>
							<div name="deck<?php echo $deck['number']; ?>" class="hdcpDeck hdcpDeckBorderlt hdcpDeckTall">
								<div class="hdcpDblBox left" style="line-height: 37px; height:146px;">
									<div name="deckname" class="deckname medium">
										<?php echo $deck['name']; ?>
									</div>
									<div name="ipaddress">
										<span style="<?php if ( empty( ${"hd".$deck['number']} ) ) { echo 'color:red;'; } else { echo 'color:#45D40C;'; } ?>"><?php echo $deck['ip']; ?><?php if ( empty( ${"hd".$deck['number']} ) ) { ?> <i class="fa fa-exclamation-circle fa-fw" style="color:red;" title="Connection error"></i><?php } ?></span>
									</div>
									<div name="status">
									<?php
										if ($deck['enable'] == "true" && gettype( ${"hd".$deck['number']} ) == 'resource' && ${"online_d".$deck['number']}){
											echo '<div class="conntrue" title="Deck is online"></div>';
										}else{
											echo '<div class="connfalse" title="Deck is not connected"></div>';
										}
										if (@$_GET["cmd"] == "syncfalse" && $_GET["deck"] == $deck['number']){
											echo '<a href="?deck='.$deck['number'].'&cmd=synctrue"><div class="syncfalse" title="Deck is set to solo operation"></div></a>';
										}elseif (@$_GET["cmd"] == "synctrue" && $_GET["deck"] == $deck['number']){
											echo '<a href="?deck='.$deck['number'].'&cmd=syncfalse"><div class="synctrue" title="Deck is set to synced operation"></div></a>';
										}elseif (@$_COOKIE["deck".$deck['number']."sync"] == "true"){
											echo '<a href="?deck='.$deck['number'].'&cmd=syncfalse"><div class="synctrue" title="Deck is set to synced operatio"></div></a>';
										}else{
											echo '<a href="?deck='.$deck['number'].'&cmd=synctrue"><div class="syncfalse" title="Deck is set to solo operation"></div></a>';
										}
										//---------------------------------------------------------------
										//Transport status & timecode - figure out how many single-line
										//acks (one per sub-command) this deck already has queued from a
										//command this same page load issued, drain exactly those, then
										//the deck's next response really is the "transport info" we ask
										//for. Command variables ($play, $stop, etc.) come from scripts.php.
										//---------------------------------------------------------------
										$hdcpSoloCmds = array(
											'trkbk' => $trkbk, 'rw' => $rw, 'play' => $play, 'ff' => $ff,
											'trkfw' => $trkfw, 'stop' => $stop, 'rem' => $rem, 'loop' => $loop,
											'gtc' => $clip, 'gtcp' => $clipAndPlay,
											'tcj' => ${"deck".$deck['number']."tcj"}, 'rec' => ${"recDeck".$deck['number']},
										);
										$hdcpSyncCmds = array(
											'trkbkall' => $trkbk, 'trkfwall' => $trkfw, 'recall' => ${"recDeck".$deck['number']},
											'playall' => $play, 'ffall' => $ff, 'rwall' => $rw, 'stopall' => $stop,
											'tcjall' => $tcjall, 'loopall' => $loop,
										);
										$hdcpCmd = isset($_GET['cmd']) ? $_GET['cmd'] : '';
										$hdcpAckCount = 0;
										if ($hdcpCmd == 'id' && @$_GET['deck'] == $deck['number']){
											$hdcpAckCount = substr_count($blink, "\r\n") + substr_count($blinkOff, "\r\n");
										}elseif (isset($hdcpSoloCmds[$hdcpCmd]) && @$_GET['deck'] == $deck['number']){
											$hdcpAckCount = substr_count($hdcpSoloCmds[$hdcpCmd], "\r\n");
										}elseif (isset($hdcpSyncCmds[$hdcpCmd]) && @$_COOKIE["deck".$deck['number']."sync"] == "true"){
											$hdcpAckCount = substr_count($hdcpSyncCmds[$hdcpCmd], "\r\n");
										}
										hdcp_drain_acks(${"hd".$deck['number']}, $hdcpAckCount);
										${"transport_d".$deck['number']} = hdcp_query(${"hd".$deck['number']}, "transport info");
										${"output_d".$deck['number']} = isset(${"transport_d".$deck['number']}['status']) ? ${"transport_d".$deck['number']}['status'] : '';
										${"tc_d".$deck['number']} = isset(${"transport_d".$deck['number']}['display timecode']) ? ${"transport_d".$deck['number']}['display timecode'] : null;
										//fclose(${"hd".$deck['number']});
									?>
									</div>
									<?php if ( ${"tc_d".$deck['number']} ){ ?>
									<div class="deckTc">
										<span class="pill pill-small pill-<?php echo hdcp_transport_class(${"output_d".$deck['number']}); ?>" style="margin-right:8px;"><?php echo ${"output_d".$deck['number']} == 'record' ? 'REC' : htmlspecialchars(${"output_d".$deck['number']}); ?></span>
										<span class="tcDisplay tcDisplaySmall"><?php echo htmlspecialchars(${"tc_d".$deck['number']}); ?></span>
									</div>
									<?php } ?>
								</div>
								<div class="hdcpDblBox left" style="height:146px;">
									<div class="hdcpBoxBborder" style="line-height: 26px;margin-top:2px;">
										Timecode Jump:
									</div>
									<div class="" style="line-height: 26px;">
										<form action="?deck=<?php echo $deck['number']; ?>&cmd=tcj" method="post" class="tcform">
											<input type="text" name="timecode<?php echo $deck['number']; ?>" value="<?php echo ${"deck".$deck['number']."tc"}; ?>">
										</form>
									</div>
									<a href="clipbin.php?deck=<?php echo $deck['number']; ?>" title="Deck clip bin">
										<div class="clipbin"></div>
									</a>
									<a href="devinfo.php?deck=<?php echo $deck['number']; ?>" title="Deck info panel">
										<div class="info"></div>
									</a>
									<a href="?deck=<?php echo $deck['number']; ?>&cmd=id" title="Identify deck">
										<div class="blink"></div>
									</a>
									<a href="?deck=<?php echo $deck['number']; ?>&cmd=rem" title="Deck remote enable">
										<div class="remote"></div>
									</a>
								</div>
								<div class="hdcpBox left" style="height:146px;">
									<a href="?deck=<?php echo $deck['number']; ?>&cmd=trkbk" title="Previous clip">
										<div class="hdcpButton trkbk"></div>
									</a>
								</div>
								<div class="hdcpBox left" style="height:146px;">
									<a href="?deck=<?php echo $deck['number']; ?>&cmd=rw" title="Rewind">
										<div class="hdcpButton rw<?php if(${"output_d".$deck['number']} == "rewind"){ echo "active";} ?>"></div>
									</a>
								</div>
								<div class="hdcpBox left" style="height:146px;">
									<a href="?deck=<?php echo $deck['number']; ?>&cmd=play" title="Play">
										<div class="hdcpButton play<?php if(${"output_d".$deck['number']} == "play"){ echo "active";} ?>"></div>
									</a>
								</div>
								<div class="hdcpBox left" style="height:146px;">
									<a href="?deck=<?php echo $deck['number']; ?>&cmd=ff" title="Fast Forward">
										<div class="hdcpButton ff<?php if(${"output_d".$deck['number']} == "forward"){ echo "active";} ?>"></div>
									</a>
								</div>
								<div class="hdcpBox left" style="height:146px;">
									<a href="?deck=<?php echo $deck['number']; ?>&cmd=trkfw" title="Next clip">
										<div class="hdcpButton trkfw"></div>
									</a>
								</div>
								<div class="hdcpBox left" style="height:146px;">
									<a href="?deck=<?php echo $deck['number']; ?>&cmd=stop" title="Stop">
										<div class="hdcpButton stop<?php if(${"output_d".$deck['number']} == "preview" || ${"output_d".$deck['number']} == "stopped"){ echo "active";} ?>"></div>
									</a>
								</div>
								<div class="hdcpBox left hdcpBoxRborder" style="height:146px;">
									<a href="?deck=<?php echo $deck['number']; ?>&cmd=rec" title="Record">
										<div class="hdcpButtonRec rec<?php if(${"output_d".$deck['number']} == "record"){ echo "active";} ?>"></div>
									</a>
								</div>
								<div class="hdcpBox left" style="height:146px;">
									<a href="?deck=<?php echo $deck['number']; ?>&cmd=loop" title="Loop play">
										<div class="hdcpButton loop"></div>
									</a>
								</div>
							</div>
			<?php
						}
					}
				}
			?>
			<!--FOOTER START-->  
			<?php include_once('footer.php'); ?>
			<!--FOOTER END-->
			</div>
		</div>
	</body>
</html>
