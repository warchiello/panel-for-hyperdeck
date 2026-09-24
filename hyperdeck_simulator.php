#!/usr/bin/env php
<?php
/**
 * HyperDeck protocol simulator - for testing panel-for-hyperdeck without real hardware.
 *
 * Run:
 *   php hyperdeck_simulator.php [port]
 *
 * Then in settings.php, point a deck's IP at wherever this is running (127.0.0.1 if it's
 * on the same machine as the web app) with port 9993 (the default - pass a different
 * number on the command line, and update that deck's port expectations accordingly, if
 * you need to run more than one instance for multiple simulated decks).
 *
 * This speaks enough of the Blackmagic HyperDeck Ethernet protocol to drive every screen
 * in the app: the connection banner, transport control (play/stop/ff/rw/record/loop),
 * clip navigation and timecode jump, identify, the clip bin (clips count/get), slot info,
 * configuration, and SSD formatting (the format "ready"/"confirm" handshake is faked -
 * this app never actually validates the token it round-trips, so a fixed placeholder
 * drives the same UI flow end-to-end). It is a testing aid for exercising the app's UI
 * and logic, not a byte-exact replica of real hardware - treat any hardware-specific
 * edge case (actual card capacities, exact firmware response quirks, real FTP clip
 * downloads) as unverified until you test against a real deck.
 *
 * Press Ctrl+C to stop.
 */

if (php_sapi_name() !== 'cli') {
	die("Run this from the command line: php hyperdeck_simulator.php\n");
}

$port = isset($argv[1]) ? intval($argv[1]) : 9993;
$fps  = 30; //simulated frame rate for timecode math

//---------------------------------------------------------------------
//Simulated deck state - shared across every connection, because it's
//meant to represent one physical deck that different page loads (and
//different browser tabs) are all looking at.
//---------------------------------------------------------------------
$state = array(
	'status'         => 'preview', //preview | stopped | play | forward | rewind | record
	'loop'           => false,
	'activeSlot'     => 1,
	'clipId'         => 1,
	'timecodeFrames' => 0,
	'lastTick'       => microtime(true),
	'cfg'            => array(
		'video input' => 'SDI',
		'audio input' => 'embedded',
		'file format' => 'QuickTimeProResHQ',
		'audio codec' => 'PCM/24bit48kHz',
	),
	'slots' => array(
		1 => array('status' => 'mounted', 'volume name' => 'SSD1', 'recording time' => 5400, 'total size' => 1000204886016, 'remaining size' => 800163909632, 'video format' => '1080p2997'),
		2 => array('status' => 'empty',   'volume name' => '',     'recording time' => 0,    'total size' => 0,             'remaining size' => 0,            'video format' => ''),
	),
	'clips' => array(
		1 => array('name' => 'A001_C001_0101AB', 'start' => '00:00:00:00', 'dur' => '00:00:10:00'),
		2 => array('name' => 'A001_C002_0101AB', 'start' => '00:00:10:00', 'dur' => '00:00:08:00'),
		3 => array('name' => 'A001_C003_0101AB', 'start' => '00:00:18:00', 'dur' => '00:00:15:00'),
	),
	'nextClipNum' => 4,
);

function tc_to_frames($tc, $fps){
	if ( ! preg_match('/^(\d+):(\d+):(\d+):(\d+)$/', trim($tc), $m) ){ return 0; }
	return ((intval($m[1]) * 3600 + intval($m[2]) * 60 + intval($m[3])) * $fps) + intval($m[4]);
}
function frames_to_tc($frames, $fps){
	$frames = max(0, intval($frames));
	$ff = $frames % $fps;
	$totalSec = intval($frames / $fps);
	$ss = $totalSec % 60;
	$mm = intval($totalSec / 60) % 60;
	$hh = intval($totalSec / 3600);
	return sprintf('%02d:%02d:%02d:%02d', $hh, $mm, $ss, $ff);
}
function advance_timecode(&$state, $fps){
	$now = microtime(true);
	$elapsed = $now - $state['lastTick'];
	$state['lastTick'] = $now;
	if ($state['status'] === 'play' || $state['status'] === 'record'){
		$state['timecodeFrames'] += intval($elapsed * $fps);
	}elseif ($state['status'] === 'forward'){
		$state['timecodeFrames'] += intval($elapsed * $fps * 5);
	}elseif ($state['status'] === 'rewind'){
		$state['timecodeFrames'] = max(0, $state['timecodeFrames'] - intval($elapsed * $fps * 5));
	}
}
function block_response($header, $fields){
	$out = $header.":\r\n";
	foreach ($fields as $k => $v){
		$out .= $k.": ".$v."\r\n";
	}
	$out .= "\r\n";
	return $out;
}

function handle_command($line, &$state, $fps){
	$line = trim($line);
	if ($line === ''){ return ''; }

	//transport info
	if ($line === 'transport info'){
		advance_timecode($state, $fps);
		$tc = frames_to_tc($state['timecodeFrames'], $fps);
		$speed = '0';
		if ($state['status'] === 'forward'){ $speed = '500'; }
		elseif ($state['status'] === 'rewind'){ $speed = '-500'; }
		elseif ($state['status'] === 'play' || $state['status'] === 'record'){ $speed = '100'; }
		return block_response('208 transport info', array(
			'status'                => $state['status'],
			'speed'                 => $speed,
			'slot id'               => $state['activeSlot'],
			'clip id'               => $state['clipId'],
			'single clip playback'  => $state['loop'] ? 'false' : 'true',
			'display timecode'      => $tc,
			'timecode'              => $tc,
			'video format'          => '1080p2997',
			'loop'                  => $state['loop'] ? 'true' : 'false',
			'input video format'    => '1080p2997',
		));
	}

	//slot info (bare = currently active slot, or an explicit "slot info: slot id: N")
	if (preg_match('/^slot info(?::\s*slot id:\s*(\d+))?$/', $line, $m)){
		$id = (isset($m[1]) && $m[1] !== '') ? intval($m[1]) : $state['activeSlot'];
		$slot = isset($state['slots'][$id]) ? $state['slots'][$id] : $state['slots'][1];
		return block_response('202 slot info', array('slot id' => $id) + $slot);
	}

	//configuration - query
	if ($line === 'configuration'){
		return block_response('210 configuration', $state['cfg']);
	}
	//configuration - set
	if (preg_match('/^configuration:\s*video input:\s*(.+)$/', $line, $m)){ $state['cfg']['video input'] = trim($m[1]); return "200 ok\r\n"; }
	if (preg_match('/^configuration:\s*audio input:\s*(.+)$/', $line, $m)){ $state['cfg']['audio input'] = trim($m[1]); return "200 ok\r\n"; }
	if (preg_match('/^configuration:\s*file format:\s*(.+)$/', $line, $m)){ $state['cfg']['file format'] = trim($m[1]); return "200 ok\r\n"; }

	//clips count
	if ($line === 'clips count'){
		return block_response('214 clips count', array('clip count' => count($state['clips'])));
	}
	//clips get
	if ($line === 'clips get'){
		$fields = array('clip count' => count($state['clips']));
		foreach ($state['clips'] as $id => $clip){
			$fields[$id] = $clip['name'].' '.$clip['start'].' '.$clip['dur'];
		}
		return block_response('206 clips info', $fields);
	}

	//remote enable
	if (preg_match('/^remote:\s*enable:\s*(true|false)$/', $line)){
		return "200 ok\r\n";
	}

	//preview
	if ($line === 'preview: enable: true'){
		$state['status'] = 'preview';
		return "200 ok\r\n";
	}

	//play (bare, with a speed, and/or with loop flags)
	if (preg_match('/^play(?::\s*(.*))?$/', $line, $m)){
		advance_timecode($state, $fps); //account for elapsed time under whatever status we were in before switching
		$opts = isset($m[1]) ? $m[1] : '';
		$speed = null;
		if (preg_match('/speed:\s*(-?\d+)/', $opts, $sm)){ $speed = intval($sm[1]); }
		if ($speed !== null && $speed > 100){ $state['status'] = 'forward'; }
		elseif ($speed !== null && $speed < 0){ $state['status'] = 'rewind'; }
		else { $state['status'] = 'play'; }
		if (preg_match('/loop:\s*true/', $opts)){ $state['loop'] = true; }
		return "200 ok\r\n";
	}

	//stop
	if ($line === 'stop'){
		advance_timecode($state, $fps);
		$state['status'] = 'stopped';
		$state['loop'] = false;
		return "200 ok\r\n";
	}

	//record (bare, or "record: name: X_")
	if (preg_match('/^record(?::\s*name:\s*(.+?)_?)?$/', $line, $m)){
		advance_timecode($state, $fps);
		$state['status'] = 'record';
		$name = (isset($m[1]) && trim($m[1]) !== '') ? trim($m[1]) : ('REC_'.date('His'));
		$id = $state['nextClipNum']++;
		$state['clips'][$id] = array('name' => $name, 'start' => frames_to_tc($state['timecodeFrames'], $fps), 'dur' => '00:00:00:00');
		$state['clipId'] = $id;
		return "200 ok\r\n";
	}

	//goto clip id: -1 (previous) / +1 (next) / an absolute clip number
	if (preg_match('/^goto:\s*clip id:\s*([+\-]?\d+)$/', $line, $m)){
		$ids = array_keys($state['clips']);
		sort($ids);
		$pos = array_search($state['clipId'], $ids);
		if ($m[1] === '-1'){
			$state['clipId'] = ($pos !== false && $pos > 0) ? $ids[$pos - 1] : $ids[0];
		}elseif ($m[1] === '+1'){
			$state['clipId'] = ($pos !== false && $pos < count($ids) - 1) ? $ids[$pos + 1] : $ids[count($ids) - 1];
		}else{
			$target = intval($m[1]);
			if (in_array($target, $ids)){ $state['clipId'] = $target; }
		}
		$state['timecodeFrames'] = 0;
		return "200 ok\r\n";
	}

	//goto timecode
	if (preg_match('/^goto:\s*timecode:\s*([\d:]+)$/', $line, $m)){
		$state['timecodeFrames'] = tc_to_frames($m[1], $fps);
		return "200 ok\r\n";
	}

	//slot select
	if (preg_match('/^slot select:\s*slot id:\s*(\d+)$/', $line, $m)){
		$state['activeSlot'] = intval($m[1]);
		return "200 ok\r\n";
	}

	//playrange set
	if (preg_match('/^playrange set:/', $line)){
		return "200 ok\r\n";
	}

	//identify
	if (preg_match('/^identify:\s*enable:\s*(true|false)$/', $line)){
		return "200 ok\r\n";
	}

	//play on startup
	if (preg_match('/^play on startup:\s*enable:\s*(true|false)$/', $line)){
		return "200 ok\r\n";
	}

	//format: prepare - mirrors the real HyperDeck reply: a "216 format ready"
	//status line, then a separate "ready id: <hex>" line carrying the token
	//the client must echo back on "format: confirm:". The sleep()s below are
	//deliberate: real hardware takes real time to prepare/complete a format,
	//well past the 1-second timeout the app uses for everything else, so a
	//test run against this simulator actually exercises that the client
	//waits long enough instead of always getting an instant reply.
	if (preg_match('/^format:(?:\s*slot id:\s*\d+)?\s*prepare:\s*(.+)$/', $line, $m)){
		sleep(3);
		return "216 format ready\r\nready id: 6f4a2b91\r\n\r\n\r\n\r\n";
	}
	//format: confirm - only accept the exact token we handed out above, so a
	//test run genuinely exercises the client's token parsing instead of
	//passing no matter what it sends.
	if (preg_match('/^format:\s*confirm:\s*([0-9a-fA-F]+)\s*$/', $line, $m)){
		sleep(2);
		if (strcasecmp($m[1], '6f4a2b91') === 0){
			return "200 ok\r\n";
		}
		return "108 internal error\r\n";
	}

	//uptime
	if ($line === 'uptime'){
		return block_response('209 uptime', array('uptime' => '00:12:34:00'));
	}

	//Unknown command - still ack it, so the app never hangs waiting on a reply.
	return "200 ok\r\n";
}

//---------------------------------------------------------------------
//TCP server - single-threaded, multiplexed with stream_select() so it
//can happily serve several page loads (several decks, several tabs,
//an auto-refreshing devinfo.php) at once without needing pcntl/forking.
//---------------------------------------------------------------------
$server = @stream_socket_server("tcp://0.0.0.0:".$port, $errno, $errstr);
if ( ! $server ){
	fwrite(STDERR, "Could not start on port $port: $errstr ($errno)\n");
	exit(1);
}
stream_set_blocking($server, false);

echo "HyperDeck simulator listening on port $port. Point a deck's IP at this machine (e.g. 127.0.0.1 if it's the same host as the web app). Ctrl+C to stop.\n";

$clients = array(); //id => array('socket'=>..., 'buffer'=>'', 'lastActive'=>time())
$nextId = 1;

while (true){
	$read = array($server);
	foreach ($clients as $c){ $read[] = $c['socket']; }
	$write = null; $except = null;

	$changed = @stream_select($read, $write, $except, 1);
	if ($changed === false){ continue; }

	//New connection?
	if (in_array($server, $read, true)){
		$conn = @stream_socket_accept($server, 0);
		if ($conn){
			stream_set_blocking($conn, false);
			$id = $nextId++;
			$clients[$id] = array('socket' => $conn, 'buffer' => '', 'lastActive' => time());
			fwrite($conn, "500 connection info:\r\nprotocol version: 1.11\r\nmodel: HyperDeck Studio Mini\r\n\r\n");
			echo "[".date('H:i:s')."] deck connected (#$id)\n";
		}
		//Remove the server socket from further processing this pass
		$key = array_search($server, $read, true);
		if ($key !== false){ unset($read[$key]); }
	}

	foreach ($read as $sock){
		$id = null;
		foreach ($clients as $cid => $c){ if ($c['socket'] === $sock){ $id = $cid; break; } }
		if ($id === null){ continue; }

		$data = @fread($sock, 8192);
		if ($data === '' || $data === false){
			if (feof($sock)){
				echo "[".date('H:i:s')."] deck disconnected (#$id)\n";
				fclose($sock);
				unset($clients[$id]);
			}
			continue;
		}

		$clients[$id]['buffer'] .= $data;
		$clients[$id]['lastActive'] = time();
		$outbound = '';
		while (($pos = strpos($clients[$id]['buffer'], "\r\n")) !== false){
			$line = substr($clients[$id]['buffer'], 0, $pos);
			$clients[$id]['buffer'] = substr($clients[$id]['buffer'], $pos + 2);
			$resp = handle_command($line, $state, $fps);
			if ($resp !== ''){
				echo "[".date('H:i:s')."] #$id > ".$line."\n";
				$outbound .= $resp;
			}
		}
		if ($outbound !== ''){
			@fwrite($sock, $outbound);
		}
	}

	//Clean up anything idle for a long time (a deck slot switched off in Settings,
	//a browser tab left open indefinitely, etc.)
	foreach ($clients as $cid => $c){
		if (time() - $c['lastActive'] > 300){
			@fclose($c['socket']);
			unset($clients[$cid]);
		}
	}
}
