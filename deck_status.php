<?php require('scripts.php'); if($enable_login == "true"){require('_login.php');}else{}
//Read-only JSON snapshot of every enabled deck's transport/slot status,
//polled from the client side by status.php and index.php so both pages
//can update in place without a full page reload. Never sends a deck
//command itself (no $_GET['cmd'] handling here) - it only reads back
//whatever scripts.php's per-deck connection loop already opened above.
header('Content-Type: application/json');

$hdcpOut = array();
foreach($config as $hd => $deck){
	if( $hd === 'global' ){ continue; }
	if( ! isset($deck['enable']) || $deck['enable'] != "true" ){ continue; }

	$bin = ${"hd".$deck['number']};
	$snap = hdcp_deck_snapshot($bin);
	if (is_resource($bin)){ fclose($bin); }

	$hdcpOut[$deck['number']] = array(
		'number'        => $deck['number'],
		'name'          => $deck['name'],
		'ip'            => $deck['ip'],
		'online'        => $snap['online'],
		'output'        => $snap['output'],
		'outputLabel'   => $snap['output'] == 'record' ? 'REC' : $snap['output'],
		'transportClass'=> hdcp_transport_class($snap['output']),
		'tc'            => $snap['tc'],
		'tcDir'         => $snap['tcDir'],
		'fps'           => $snap['fps'],
		'slotId'        => $snap['slotId'],
		'slotRemain'    => $snap['slotRemain'],
		'slotRemainTc'  => hdcp_seconds_to_tc($snap['slotRemain']),
		'slotTimeClass' => hdcp_time_class($snap['slotRemain']),
		'slotTotalGb'   => $snap['slotTotal'] !== null ? hdcp_bytes_to_gb($snap['slotTotal']) : null,
		'recording'     => ($snap['output'] == 'record'),
	);
}

echo json_encode($hdcpOut);
