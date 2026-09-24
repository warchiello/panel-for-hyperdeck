<?php require_once('config.php');

//---------------------------------------------------------------------------
// SHARED HELPERS - reading & formatting HyperDeck responses for the info panel
//---------------------------------------------------------------------------

//Sends a command and parses its response into an associative array of
//"key: value" fields. HyperDeck multi-line responses end their status
//line with a colon and are terminated by a blank line; single-line
//acknowledgements (e.g. "200 ok") have nothing further to read.
if ( ! function_exists('hdcp_drain_connection_banner') ) {
	//The deck sends an unsolicited "500 connection info:" message the
	//instant a client connects, before any command is sent. If it isn't
	//drained here, it gets mistaken for the response to whichever command
	//is issued first later on, throwing every subsequent read off by one.
	//Returns true when the deck actually greeted us (i.e. it's online),
	//false otherwise, so callers don't need to re-read the banner themselves
	//just to find out whether the connection is live.
	function hdcp_drain_connection_banner($bin){
		if ( ! is_resource($bin) ) { return false; }
		$header = fgets($bin);
		if ($header === false){ return false; }
		$isBanner = (strpos(trim($header), '500') === 0);
		if ($isBanner){
			while ( ($line = fgets($bin)) !== false ){
				if (trim($line) === ''){ break; }
			}
		}
		return $isBanner;
	}
}

if ( ! function_exists('hdcp_drain_acks') ) {
	//Every command sent to the deck (play, stop, rec, a slot select, ...)
	//gets back exactly one single-line "200 ok" per sub-command. If this
	//page already sent one or more commands to a deck earlier in the same
	//request, those acks are still sitting unread in the socket ahead of
	//whatever we ask for next - drain exactly that many lines first so a
	//later query (like "transport info") reads its own response instead
	//of someone else's leftover ack.
	function hdcp_drain_acks($bin, $count){
		if ( ! is_resource($bin) || $count <= 0 ) { return; }
		for ($i = 0; $i < $count; $i++){
			if (fgets($bin) === false){ break; }
		}
	}
}

if ( ! function_exists('hdcp_query') ) {
	function hdcp_query($bin, $command){
		$data = array();
		if ( ! is_resource($bin) ) { return $data; }
		fwrite($bin, $command."\r\n");
		$header = fgets($bin);
		if ($header === false){ return $data; }
		$header = trim($header);
		$data['__status'] = $header;
		if (substr($header, -1) !== ':'){ return $data; } //single-line ack, nothing more to read
		while ( ($line = fgets($bin)) !== false ){
			$line = trim($line);
			if ($line === ''){ break; }
			$parts = explode(':', $line, 2);
			if (count($parts) == 2){
				$data[trim($parts[0])] = trim($parts[1]);
			}
		}
		return $data;
	}
}

//Human readable helpers used by the info panel
if ( ! function_exists('hdcp_bytes_to_gb') ) {
	function hdcp_bytes_to_gb($bytes){
		if ( ! is_numeric($bytes) ){ return '—'; }
		return number_format($bytes / 1073741824, 1)." GB";
	}
}
if ( ! function_exists('hdcp_seconds_to_tc') ) {
	function hdcp_seconds_to_tc($seconds){
		if ( ! is_numeric($seconds) ){ return '00:00:00'; }
		return gmdate("H:i:s", intval($seconds));
	}
}
if ( ! function_exists('hdcp_time_class') ) {
	//colour coding for remaining record time, same 10min/5min thresholds the panel already used
	function hdcp_time_class($seconds){
		if ( ! is_numeric($seconds) ){ return 'gray'; }
		if ($seconds >= 600){ return 'green'; }
		if ($seconds > 300){ return 'yellow'; }
		return 'red';
	}
}
if ( ! function_exists('hdcp_slot_class') ) {
	function hdcp_slot_class($status){
		switch(strtolower((string)$status)){
			case 'mounted': return 'green';
			case 'mounting': return 'yellow';
			case 'error': return 'red';
			default: return 'gray'; //empty
		}
	}
}
if ( ! function_exists('hdcp_transport_class') ) {
	function hdcp_transport_class($status){
		switch(strtolower((string)$status)){
			case 'record': return 'red';
			case 'play': case 'forward': case 'rewind': case 'jog': case 'shuttle': return 'green';
			default: return 'gray'; //stopped / preview / unknown
		}
	}
}
if ( ! function_exists('hdcp_fps_from_format') ) {
	//Best-effort frame rate guess from a HyperDeck video-format string like
	//"1080p2997" or "720p50", used only to keep the main panel's live
	//timecode ticker (pure client-side, between real page loads) rolling
	//over at roughly the right rate. It's cosmetic - every button press or
	//refresh re-syncs to the deck's actual timecode - so an approximation
	//for an unrecognised suffix is fine.
	function hdcp_fps_from_format($format){
		if ( ! preg_match('/(\d+)$/', (string)$format, $m) ) { return 30; }
		$known = array(
			2398 => 23.98, 2400 => 24, 2500 => 25, 2997 => 29.97, 3000 => 30,
			5000 => 50, 5994 => 59.94, 6000 => 60,
		);
		$suffix = intval($m[1]);
		if (isset($known[$suffix])){ return $known[$suffix]; }
		if ($suffix >= 1 && $suffix <= 120){ return $suffix; } //plain integer rate, e.g. the "50" in "720p50"
		return 30;
	}
}
if ( ! function_exists('hdcp_deck_snapshot') ) {
	//Read-only status query (transport + active slot), no commands sent -
	//safe to call on an already-established connection at any point, since
	//it never writes anything that would need an ack drained later. Shared
	//by the status board and the live-status polling endpoint so both
	//report the exact same fields the same way.
	function hdcp_deck_snapshot($bin){
		$data = array(
			'online' => false, 'output' => '', 'tc' => null, 'tcDir' => '', 'fps' => 30,
			'slotId' => null, 'slotRemain' => null, 'slotTotal' => null,
		);
		if ( ! is_resource($bin) ) { return $data; }
		$data['online'] = true;
		$transport = hdcp_query($bin, "transport info");
		$data['output'] = isset($transport['status']) ? $transport['status'] : '';
		$data['tc'] = isset($transport['display timecode']) ? $transport['display timecode'] : null;
		$data['fps'] = isset($transport['video format']) ? hdcp_fps_from_format($transport['video format']) : 30;
		if ($data['output'] == 'record' || $data['output'] == 'play' || $data['output'] == 'forward'){
			$data['tcDir'] = 'fwd';
		}elseif ($data['output'] == 'rewind'){
			$data['tcDir'] = 'rev';
		}
		$activeSlot = isset($transport['slot id']) ? $transport['slot id'] : '';
		if ($activeSlot !== '' && $activeSlot !== 'none'){
			$slotInfo = hdcp_query($bin, "slot info: slot id: ".$activeSlot);
			if (isset($slotInfo['status']) && $slotInfo['status'] !== 'empty'){
				$data['slotId'] = $activeSlot;
				$data['slotRemain'] = isset($slotInfo['recording time']) ? intval($slotInfo['recording time']) : null;
				$data['slotTotal'] = isset($slotInfo['total size']) ? floatval($slotInfo['total size']) : null;
			}
		}
		return $data;
	}
}

//DECK COMMANDS
$play = "remote: enable: true\r\n play\r\n";					//sends command to play deck
$stop = "remote: enable: true\r\n stop\r\n";					//sends comand to stop deck
$ff = "remote: enable: true\r\n play: speed: ".$ffspeed."\r\n"; 		//sends command to fast forward
$rw = "remote: enable: true\r\n play: speed: ".$rwspeed."\r\n";  		//sends command to rewind
$record = "record\r\n";  								//sends command to record a deck, replaced by record naming per deck
$rem = "remote: enable: true\r\n";  						//sends command to enable deck to be controlled by above functions
$trkbk = "goto: clip id: -1\r\n";  							//sends command to jump to the previous clip
$trkfw = "goto: clip id: +1\r\n"; 							//sends command to jum to the next clip
if($loopKind == "all"){
	$loop = "remote: enable: true\r\n play: loop: true\r\n";
}else{
	$loop = "remote: enable: true\r\n play: loop: true single clip: true\r\n";
}
$slot = "remote: enable: true\r\n slot select: slot id: ". ( isset($_GET['slotid']) ? $_GET['slotid'] : '' ) ."\r\n";
$range = "playrange set: in: ". ( isset($_GET['prin']) ? urldecode($_GET['prin']) : '' ) ." out: ". ( isset($_GET['prout']) ? urldecode($_GET['prout']) : '' ) ."\r\n";
$input = "configuration: video input: ". ( isset($_GET['vidstd']) ? $_GET['vidstd'] : '' ) ."\r\n";
$audio = "configuration: audio input: ". ( isset($_GET['audstd']) ? $_GET['audstd'] : '' ) ."\r\n";
$format = "configuration: file format: ". ( isset($_GET['fmtstd']) ? $_GET['fmtstd'] : '' ) ."\r\n";
$preview = "preview: enable: true\r\n";
$status = "transport info\r\n";
$clip = "stop\r\n goto: clip id: ". ( isset($_GET['clip']) ? $_GET['clip'] : '' ) ."\r\n";
$clipAndPlay = "goto: clip id: ". ( isset($_GET['clip']) ? $_GET['clip'] : '' ) ."\r\n play\r\n";
$clipsCount = "clips count\r\n";
$clipsList = "clips get\r\n";
$blink = "identify: enable: true\r\n";
$blinkOff = "identify: enable: false\r\n";
$uptime = "uptime\r\n";
$posOn = "play on startup: enable: true\r\n";
$posOff = "play on startup: enable: false\r\n";

$tcall = "00:00:00:00";

//USER FILE NAMING - fn=file name
	//$fnUser called in config.php
		//get values from post and update cookies or set default
	if(isset($_GET['reel'])){
		setcookie("reel",$_GET['reel'],time() + (86400 * 7));
		$reel = $_GET['reel'];
	}elseif(isset($_COOKIE["reel"])){
		$reel = $_COOKIE["reel"];
	}else{
		$reel = "001";
		setcookie("reel", "001",time() + (86400 * 7));
	}
	if(isset($_GET['take'])){
		setcookie("take",$_GET['take'],time() + (86400 * 7));
		$take = $_GET['take'];
	}elseif(isset($_COOKIE["take"])){
		$take = $_COOKIE["take"];
	}else{
		$take = "001";
		setcookie("take", "001",time() + (86400 * 7));
	}
	if(isset($_GET['scene'])){
		setcookie("scene",$_GET['scene'],time() + (86400 * 7));
		$scene = $_GET['scene'];
	}elseif(isset($_COOKIE["scene"])){
		$scene = $_COOKIE["scene"];
	}else{
		$scene = "001";
		setcookie("scene", "001",time() + (86400 * 7));
	}
	if(isset($_GET['customfn'])){
		setcookie("customfn",$_GET['customfn'],time() + (86400 * 7));
		$customFn = $_GET['customfn'];
	}elseif(isset($_COOKIE["customfn"])){
		$customFn = $_COOKIE["customfn"];
	}else{
		$customFn = "";
		setcookie("customfn", "",time() + (86400 * 7));
	}

//DECK GLOBALS
	foreach($config as $hd => $deck){
		if( $hd !== 'global' ){
			if($deck['enable'] == "true"){
				${"hd".$deck['number']} = @fsockopen("tcp://".$deck['ip'], 9993, $errno, $errstr, 1); //Establish Connection (short connect timeout - fail fast on unreachable/non-existent decks)
				if ( is_resource(${"hd".$deck['number']}) ) {
					stream_set_timeout(${"hd".$deck['number']}, 1); //cap every read on this socket, so something that accepts the TCP connection but never speaks the HyperDeck protocol can't hang the page waiting on fgets()
				}
				${"online_d".$deck['number']} = hdcp_drain_connection_banner(${"hd".$deck['number']}); //discard the deck's unsolicited "connection info" message, remembering whether it actually greeted us
				if(isset($_COOKIE["deck".$deck['number']."sync"])){
					
				}else{
					if ( ! headers_sent() ) { setcookie("deck".$deck['number']."sync", "false",time() + (86400 * 7)); }
				}
					//Set up the file naming string   
					$fnKey = array("%deckname","%month","%day","%year","%hour","%min","%sec","%reel","%take","%scene","%custom"," ");
					$fnReplace = array($deck['name'],date('m'),date('d'),date('Y'),date('H'),date('i'),date('s'),"R".$reel,"T".$take,"S".$scene,$customFn,"");
					$fnStructure = str_replace($fnKey, $fnReplace, $fnUser);
					//Make sure all spaces are stripped
					$fnOutput = str_replace(" ","",$fnStructure);
				${"recDeck".$deck['number']} = "remote: enable: true\r\n record: name: ".$fnOutput."_\r\n"; //Set Record Filename
				if( isset( $_GET['deck'] ) && $_GET['deck'] == $deck['number']){
					$go = ${"hd".$deck['number']};
				}
				//Specific Deck Commands
					//Related to clipbin & devinfo
					if( isset( $_GET['deck'] ) && $_GET['deck'] == $deck['number']){
						$currentDeck = $deck['name'];
						$currentIp = $deck['ip'];
						$bin = ${"hd".$deck['number']};
						if($deck['model'] == "mini"){
							$value = 38;
							$value2 = 1;
							$value3 = 7;
						}else{
							$value = 45;
							$value2 = 0;
							$value3 = 10;
						}
						$dname = $deck['name'];
					}
					//Timer related
					if( isset( $_GET['timeDeck'] ) && $_GET['timeDeck'] == $deck['number']){
						$name = $deck['name'];
					}else{
						$name = "Sync";
					}
					//Sync related
						//Timecode
						$tcjall = "goto: timecode: ". ( isset( $_POST["timecodeall"] ) ? $_POST["timecodeall"] : '' ) ."\r\n";
						if(isset($_POST["timecodeall"])){
							$tcall = "Timecode Set!";
						}else{
							$tcall = "00:00:00:00";
						}
						//Track Back
						if(@$_GET['cmd'] == "trkbkall"){
							if($_COOKIE["deck".$deck['number']."sync"] == "true"){
								if ( gettype( ${"hd".$deck['number']} ) == 'resource' ) { fwrite(${"hd".$deck['number']}, $trkbk); }
							}
						}
						//Track Forward
						if(@$_GET['cmd'] == "trkfwall"){
							if($_COOKIE["deck".$deck['number']."sync"] == "true"){
								if ( gettype( ${"hd".$deck['number']} ) == 'resource' ) { fwrite(${"hd".$deck['number']}, $trkfw); }
							}
						}
						//Record
						if(@$_GET['cmd'] == "recall"){
							if($_COOKIE["deck".$deck['number']."sync"] == "true"){
								if ( gettype( ${"hd".$deck['number']} ) == 'resource' ) { fwrite(${"hd".$deck['number']}, ${"recDeck".$deck['number']}); }
							}
						}
						//Play
						if(@$_GET['cmd'] == "playall"){
							if($_COOKIE["deck".$deck['number']."sync"] == "true"){
								if ( gettype( ${"hd".$deck['number']} ) == 'resource' ) { fwrite(${"hd".$deck['number']}, $play); }
							}
						}
						//Fast Forward
						if(@$_GET['cmd'] == "ffall"){
							if($_COOKIE["deck".$deck['number']."sync"] == "true"){
								if ( gettype( ${"hd".$deck['number']} ) == 'resource' ) { fwrite(${"hd".$deck['number']}, $ff); }
							}
						}
						//Rewind
						if(@$_GET['cmd'] == "rwall"){
							if($_COOKIE["deck".$deck['number']."sync"] == "true"){
								if ( gettype( ${"hd".$deck['number']} ) == 'resource' ) { fwrite(${"hd".$deck['number']}, $rw); }
							}
						}
						//Stop
						if(@$_GET['cmd'] == "stopall"){
							if($_COOKIE["deck".$deck['number']."sync"] == "true"){
								if ( gettype( ${"hd".$deck['number']} ) == 'resource' ) { fwrite(${"hd".$deck['number']}, $stop); }
							}
						}
						//Timecode Jump
						if(@$_GET['cmd'] == "tcjall"){
							if($_COOKIE["deck".$deck['number']."sync"] == "true"){
								if ( gettype( ${"hd".$deck['number']} ) == 'resource' ) { fwrite(${"hd".$deck['number']}, $tcjall); }
								${"deck".$deck['number']."tc"} = "Timecode Set!";
							}
						}
						//Play With Loop
						if(@$_GET['cmd'] == "loopall"){
							if($_COOKIE["deck".$deck['number']."sync"] == "true"){
								if ( gettype( ${"hd".$deck['number']} ) == 'resource' ) { fwrite(${"hd".$deck['number']}, $loop); }
							}
						}
						//Decktimecode
						${"deck".$deck['number']."tcj"} = "goto: timecode: ". ( isset( $_POST["timecode".$deck['number']] ) ? $_POST["timecode".$deck['number']] : '' ) ."\r\n";
						if(isset($_POST["timecode".$deck['number']])){
							${"deck".$deck['number']."tc"} = "Timecode Set!";
						}else{
							${"deck".$deck['number']."tc"} = "00:00:00:00";
						}
						if( isset( $_GET['deck'] ) && $_GET['deck'] == $deck['number'] && @$_GET['cmd'] == "syncfalse") {
							setcookie("deck".$deck['number']."sync", "false",time() + (86400 * 7));
						}elseif( isset( $_GET['deck'] ) && $_GET['deck'] == $deck['number'] && @$_GET['cmd'] == "synctrue") {
							setcookie("deck".$deck['number']."sync", "true",time() + (86400 * 7));
						}
						if(@$_GET['cmd'] == "tcj" && $_GET['deck'] == $deck['number']) {
							if ( gettype( $go ) == 'resource' ) { fwrite($go,${"deck".$deck['number']."tcj"}); }
						}elseif(@$_GET['cmd'] == "rec" && $_GET['deck'] == $deck['number']) {
							if ( gettype( $go ) == 'resource' ) { fwrite($go, ${"recDeck".$deck['number']}); }
						}
			}
	
		}
	}


//COMMAND GLOBALS
if ( isset( $_GET['cmd'] ) && isset( $go ) && gettype( $go ) == 'resource' ) { 
	if(@$_GET['cmd'] == "trkbk") {
		fwrite($go, $trkbk);
	}elseif(@$_GET['cmd'] == "rw") {
		fwrite($go, $rw);
	}elseif(@$_GET['cmd'] == "play") {
		fwrite($go, $play);
	}elseif(@$_GET['cmd'] == "ff") {
		fwrite($go, $ff);
	}elseif(@$_GET['cmd'] == "trkfw") {
		fwrite($go, $trkfw);
	}elseif(@$_GET['cmd'] == "stop") {
		fwrite($go, $stop);
	}elseif(@$_GET['cmd'] == "rem") {
		fwrite($go, $rem);
	}elseif(@$_GET['cmd'] == "slot") {
		fwrite($go, $slot);
	}elseif(@$_GET['cmd'] == "loop") {
		fwrite($go,$loop);
	}elseif(@$_GET['cmd'] == "range") {
		fwrite($go,$range);
	}elseif(@$_GET['cmd'] == "vidstd" && $_GET['vidstd'] == "preview") {
		fwrite($go,$preview);
	}elseif(@$_GET['cmd'] == "vidstd") {
		fwrite($go,$input);
	}elseif(@$_GET['cmd'] == "audstd") {
		fwrite($go,$audio);
	}elseif(@$_GET['cmd'] == "fmtstd") {
		fwrite($go,$format);
	}elseif(@$_GET['cmd'] == "startup" && $_GET['startupstate'] == "true") {
		fwrite($go,$posOn);
	}elseif(@$_GET['cmd'] == "startup" && $_GET['startupstate'] == "false") {
		fwrite($go,$posOff);
	}elseif(@$_GET['cmd'] == "id") {
		fwrite($go,$blink);
		sleep(5);
		fwrite($go,$blinkOff);
	}elseif(@$_GET['cmd'] == "gtc") {
		fwrite($go,$clip);
	}elseif(@$_GET['cmd'] == "gtcp") {
		fwrite($go,$clipAndPlay);
	}elseif(@$_GET['cmd'] == "cptrue") {
		setcookie("clipandplay", "true");
	}elseif(@$_GET['cmd'] == "cpfalse") {
		setcookie("clipandplay", "false");
	}
}

//SSD Formatting
	if( isset( $_GET['cmd'] ) && $_GET['cmd'] == 'format' && isset( $go ) && gettype( $go ) == 'resource' ){
		$fmt = $_GET['formatType'];
		//Target a specific card/slot when one was chosen; otherwise fall back
		//to the deck's currently active slot (the original behaviour)
		$fmtSlotId = ( isset($_GET['slotid']) && $_GET['slotid'] !== '' ) ? intval($_GET['slotid']) : null;

		//"format" has to be sent as a multi-line "parameterized" command
		//block - a bare "format:" line, one "key: value" line per parameter,
		//then a blank line - NOT as a single inline line like
		//"format: slot id: 1 prepare: exFAT". The deck was silently
		//rejecting the inline form outright (protocol error code 163 is
		//literally "parameterized single line command not supported"),
		//which is why it never showed any activity at all: it was never
		//actually receiving a command it understood. Confirmed against
		//Blackmagic's own open-source client library
		//(github.com/Sofie-Automation/sofie-hyperdeck-connection), which
		//always builds "format" this way.
		$prepToken = "format:\r\nprepare: ".$fmt."\r\n";
		if ( $fmtSlotId ){ $prepToken .= "slot id: ".$fmtSlotId."\r\n"; }
		$prepToken .= "\r\n";

		//Diagnostic trail shown on the Utility page after this runs, so a
		//failure on real hardware can be debugged from the exact bytes the
		//deck actually sent back, without needing server/log access. Every
		//control character is escaped visibly (\r, \n, \t) so nothing here
		//depends on how the browser renders raw CR/LF.
		$formatDebug = array();
		$formatDebug[] = "sent: ".addcslashes($prepToken, "\r\n\t");

		//Every read on this socket normally has a 1-second timeout (set once,
		//up in DECK GLOBALS) so a deck that never speaks the protocol can't
		//hang the page. That's far too short for formatting: preparing and
		//confirming a format is real work on real hardware and can easily
		//take several seconds. Give this one exchange a much longer timeout,
		//and restore the original one afterwards so nothing else on the
		//socket is affected.
		stream_set_timeout($go, 20);
		$token = '';
		$result = '';
		fwrite($go, $prepToken);

		//The deck's success reply is a genuine protocol oddity (also taken
		//from the reference client above, which special-cases it): a normal
		//single-line ack - "216 format ready", with NO trailing colon -
		//immediately followed by a SECOND raw line that is nothing but the
		//bare confirmation token itself. No "code:"/"ready id:" label, no
		//blank-line terminator; whatever that second line's raw content is,
		//is the token, verbatim. Any other single-line reply (e.g. "100
		//syntax error", "101 unsupported parameter") means the deck refused
		//the command outright, so there's nothing more to read - reading
		//just the one status line and reacting to it (rather than looping on
		//fgets() hoping for more data that a rejection will never send) is
		//also what makes a bad command fail in under a second instead of
		//hanging until the timeout.
		$line1 = fgets($go);
		if ( $line1 === false ){
			$meta1 = stream_get_meta_data($go);
			$formatDebug[] = "line1: (no data - ".( ! empty($meta1['timed_out']) ? "read timed out" : "connection closed/eof" ).")";
		}else{
			$formatDebug[] = "line1: ".addcslashes($line1, "\r\n\t");
		}
		if ( $line1 !== false && preg_match('/^\s*216\b/', $line1) ){
			if ( strpos(trim($line1), ':') !== false ){
				//Defensive fallback only: if a firmware variant ever sends
				//this as an ordinary multi-line block ("216 format ready:"
				//followed by "field: value" lines and a blank-line
				//terminator) instead of the bare token line documented
				//above, handle that shape too rather than failing outright.
				for ($x=0; $x<10; $x++){
					$line = fgets($go);
					if ($line === false || trim($line) === ''){ break; }
					$formatDebug[] = "block line: ".addcslashes($line, "\r\n\t");
					if ( preg_match('/^\s*(?:code|ready\s*id)\s*:\s*(.+)$/i', $line, $tokenMatch) ){
						$token = trim($tokenMatch[1]);
						break;
					}
				}
			}else{
				$line2 = fgets($go);
				if ( $line2 === false ){
					$formatDebug[] = "line2: (no data)";
				}else{
					$formatDebug[] = "line2 (token): ".addcslashes($line2, "\r\n\t");
					$token = trim($line2);
				}
			}
		}
		$formatDebug[] = "token parsed as: ".( $token !== '' ? "'".$token."'" : "(empty - no confirm sent)" );

		if ( $token !== '' ){
			//"format: confirm: <token>" is likewise a multi-line block, not
			//an inline line - see the note above prepare.
			$confirm = "format:\r\nconfirm: ".$token."\r\n\r\n";
			//The confirm's "200 ok" can also lag behind the deck actually
			//starting/finishing the format, so give it the same generous
			//timeout rather than the page-wide 1-second default.
			stream_set_timeout($go, 20);
			fwrite($go, $confirm);
			$formatDebug[] = "confirm sent: ".addcslashes($confirm, "\r\n\t");
			$result = fgets($go);
			if ($result === false){
				$meta2 = stream_get_meta_data($go);
				$formatDebug[] = "confirm reply: (no data - ".( ! empty($meta2['timed_out']) ? "read timed out" : "connection closed/eof" ).")";
				$result = '';
			}else{
				$formatDebug[] = "confirm reply: ".addcslashes($result, "\r\n\t");
			}
		}
		stream_set_timeout($go, 1); //restore the fast-fail timeout for the rest of the page
		$formatDebugText = implode(' | ', $formatDebug);
		if ( $token !== '' && preg_match('/^\s*200\b/', $result) ){
			$complete = "completed";
		}else{
			$complete = "failed";
		}
	}

//Version information
	$installed_version = '4.1.0';
?>
