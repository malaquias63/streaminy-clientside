<?php

function shutdown_callback(){
	
	global $db;
	global $stream_id;
	global $line_user;
	global $last_con_id;
		
	// CHECK FIRST STREAM IS FROM THESE SERVER
	$set_stream_sys_array = array($stream_id, SERVER);
	$set_stream_sys = $db->query('SELECT * FROM cms_stream_sys WHERE stream_id = ? AND server_id = ?', $set_stream_sys_array);
	if(count($set_stream_sys) > 0){
		
		// SET ACTIVITY ID
		$set_stream_activity_array = array($last_con_id);
		$set_stream_activity = $db->query('SELECT * FROM cms_stream_activity WHERE stream_activity_id = ?', $set_stream_activity_array);
		if(count($set_stream_activity) > 0){
		
			$last_activity_connected_time = $set_stream_activity[0]['stream_activity_connected_time'];
		
			// DELETE CONNECTION FROM DATABASE
			$delete_stream_activity_array = array('stream_activity_id' => $last_con_id);
			$delete_stream_activity = $db->query('DELETE FROM cms_stream_activity WHERE stream_activity_id = :stream_activity_id', $delete_stream_activity_array);						
					
			// WRITE CONNECTION TO LAST ACTIVITY		
			if(time() - $last_activity_connected_time > 20){
				
				// WRITE INTO LAST ACTIVITY
				$insert_last_activity_array = array(
					'last_activity_date' => time(),
					'last_activity_stream_id' => $stream_id,
					'last_activity_line_id' => get_line_id_by_name($line_user),
					'last_activity_ip' => $_SERVER['REMOTE_ADDR'],
					'last_activity_connected_time' => $last_activity_connected_time,
					'last_activity_user_agent' => $_SERVER['HTTP_USER_AGENT']
				);
						
				$insert_last_activity = $db->query('INSERT INTO cms_last_activity (last_activity_date, last_activity_stream_id, last_activity_line_id, last_activity_ip, last_activity_connected_time, last_activity_user_agent) VALUES (:last_activity_date, :last_activity_stream_id, :last_activity_line_id, :last_activity_ip, :last_activity_connected_time, :last_activity_user_agent)', $insert_last_activity_array);
			}		
					
			$pid = file_get_contents(DOCROOT.'streams/'.$last_con_id.'.con');
	
			// DELETE CONNECTION FILE
			unlink(DOCROOT.'streams/'.$last_con_id.'.con');			
			shell_exec('kill -9 '.$pid);
		}
	}	
}

// CALL THE SHUTDOWN CALLBACK ON EXIT THIS PHP
register_shutdown_function('shutdown_callback');

// SET TIME LIMIT
set_time_limit(0);

// INCLUDE THE WHOLE FUNCTIONS
require_once('/home/xapicode/iptv_xapicode/wwwdir/_system/config/config.main.php');
require_once('/home/xapicode/iptv_xapicode/wwwdir/_system/class/class.pdo.php');

// GET HEADERS
header('X-Accel-Buffering: no');
header("Access-Control-Allow-Origin: *");

// CONNECT TO MAIN DB
$DBPASS = decrypt(PASSWORD);
$db = new Db(HOST, DATABASE, USER, $DBPASS);

// GET ALL REQUEST STRINGS
$remote_ip = $_SERVER['REMOTE_ADDR'];
$user_agent = $_SERVER['HTTP_USER_AGENT'];
$query_string = $_SERVER['QUERY_STRING'];
$line_user = $_GET['username'];
$line_pass = $_GET['password'];
$stream_id = $_GET['stream'];

if($line_user != 'loop'){
	// CHECK FIRST IF LINE IS ACTIVE
	$set_line_array = array($line_user, $line_pass, 4, 3, 2);
	$set_line = $db->query('SELECT * FROM cms_lines WHERE line_user = ? AND line_pass = ? AND line_status != ? AND line_status != ? AND line_status != ?', $set_line_array);
	if(count($set_line) < 1){
		exit("unable to connect to stream. reason: issue on line status");
	}

	// CHECK FIRST IF REQUEST STRINGS ARE NOT EMPTY
	if (!isset($line_user) || !isset($line_pass) || !isset($stream_id)) {
		exit("unable to connect to stream. reason: not all parameter is given");
	}

	// CHECK IF IP IS ALLOWED
	if(!check_allowed_ip($line_user)){
		exit("unable to connect to stream. reason: ip is not allowed.");
	} 

	// CHECK IF USER AGENT IS ALLOWED
	if(!check_allowed_ua($line_user, $user_agent)){
		exit("unable to connect to stream. reason: useragent not allowed.");
	}

	// CHECK IF BOUQUET HAS THE STREAM ID
	if(!check_allowed_bouquet_stream($line_user, $stream_id)){
		exit("unable to connect to stream. reason: stream is not in bouquet");
	}
}

// FLOOD DEDECTION
if(check_flood_dedection()){
	
	if($line_user != 'loop'){
	
		// CHECK IF LINE IS EXISTS
		if(!check_line_user($line_user)){	
			
			// IF LINE NOT EXISTS THEN CHECK FIRST BANNED LIST OF REMOTE ID
			$set_bann_array = array($remote_ip);
			$set_bann = $db->query('SELECT bann_id FROM cms_bannlist WHERE bann_ip = ?', $set_bann_array);
			
			// IF REMOTE IP NOT IN BANNLIST
			if(count($set_bann) == 0){
				
				// INSERT FIRST INTO THE LOG
				insert_into_loglist($remote_ip, $user_agent, $query_string);
				
				// GET THEN THE LOGS
				$set_log_array = array($remote_ip, SERVER);
				$set_log = $db->query('SELECT log_ip FROM cms_log WHERE log_ip = ? AND log_server = ?', $set_log_array);			
				
				// IF COUNT OF LOGS MORE THEN 5 THEN BANN USER
				if(count($set_log) >= 5){
					$bann_title = 'Flood Protection';
					$bann_note = 'line not exists ('.$query_string.')';
					
					insert_into_bannlist(0, $set_log[0]['log_ip'], $bann_title, $bann_note);
					iptables_add($set_log[0]['log_ip']);
				}
			}
			
			exit();
		}
	}
}

// GET STREAM DATA
$set_stream_array = array($stream_id);
$set_stream = $db->query('SELECT * FROM cms_streams WHERE stream_id = ?', $set_stream_array);

$set_server_array = array($set_stream[0]['stream_loop_to_server_id']);
$set_server = $db->query('SELECT * FROM cms_server WHERE server_id = ?', $set_server_array);

$stream_server = json_decode($set_stream[0]['stream_server_id'], true);
$stream_is_demand = $set_stream[0]['stream_is_demand'];
$stream_status = json_decode($set_stream[0]['stream_status'], true);

// FORWARD LOOP STREAM
if($set_stream[0]['stream_method'] == 3 && $line_user == 'loop'){
	if(base64_decode($_SERVER['REMOTE_ADDR']) != base64_decode($set_server[0]['server_ip'])){
		// LOOP SERVER
		header('location: http://'.$set_server[0]['server_ip'].':'.$set_server[0]['server_broadcast_port'].'/live/loop/loop/'.$stream_id.'.ts');
		exit();
	}
}

// FORWARD STREAM TO SOURCE
if($set_stream[0]['stream_direct_source'] != ''){
	if($set_stream[0]['stream_status'] == 1){		
		header('location: '.$set_stream[0]['stream_direct_source']);
		exit();
	} else {
		exit("Stream is not set to playing...");
	}
}

// CHECK STREAM IS ON LOAD BALANCER TO REDIRECT IT
if(!in_array(SERVER, $stream_server) && $set_stream[0]['stream_method'] != 3){
	shuffle($stream_server);
	$set_server_array = array($stream_server[0]);
	$set_server = $db->query('SELECT server_ip, server_broadcast_port FROM cms_server WHERE server_id = ?', $set_server_array);
	
	header('location: http://' . $set_server[0]['server_ip'] . ':' . $set_server[0]['server_broadcast_port'] . '/live/' . $line_user . '/' . $line_pass . '/' . $stream_id . '.ts');
}

// CHECK FIRST IF STREAM IS ON DEMAND MODE AND STATUS NOT ONLINE
if($stream_is_demand == 1 && $stream_status[0][SERVER] == 2){			
	$stream_status = json_decode($set_stream[0]['stream_status'], true);
	$stream_status[0][SERVER] = 3;	
	
	// SET STREAM STATUS TO STARTING
	$update_stream_array = array('stream_status' => json_encode($stream_status), 'stream_id' => $stream_id);
	$update_stream = $db->query('UPDATE cms_streams SET stream_status = :stream_status WHERE stream_id = :stream_id', $update_stream_array);
	
	ffmpeg_live_command($stream_id);		
		
	// GO TO WHILE LOOP IF FOUND M3U BREAK IT AND OPEN STREAM	
	while(!file_exists(DOCROOT.'streams/'.$stream_id.'_.m3u8')) {						
		sleep(1);
	}
}


// GET STREAM FOLDER
$stream_folder = DOCROOT. 'streams/';
$segment = DOCROOT . 'streams/'. $stream_id.'_.m3u8';

// CHECK M3U AND SEGMENT IS EXISTS
if (file_exists($segment) && preg_match_all("/(.*?).ts/", file_get_contents($segment), $data)) {
	$segment_ts = segment_playlist($segment, segment_buffer());
	
	// GET THE FIRST SEGMENT
	$first_segment = current($segment_ts);
	preg_match("/_(.*)\./", $first_segment, $current_segment);
	
	// GET READED SEGMENT
	$current = $current_segment[1];	
	
	// CHECK FOR TS EXISTS ON STREAM FOLDER
	if(file_exists(DOCROOT.'streams/'.$stream_id.'_'.$current.'.ts')){
		
		if($line_user != 'loop'){ 
		
			// GET AVAILABLE ACTIVITY OF LINE
			$available_activity = $set_line[0]['line_connection'];
			
			// INSERT INTO ACTIVITY
			$insert_activity_array = array(
				'stream_activity_line_id' => $set_line[0]['line_id'],
				'stream_activity_stream_id' => $stream_id,
				'stream_activity_useragent' => $user_agent,
				'stream_activity_ip' => $remote_ip,
				'stream_activity_php_pid' => getmypid(),
				'stream_activity_connected_time' => time(),
				'stream_activity_server_id' => SERVER
			);

			$insert_activity = $db->query('
				INSERT INTO cms_stream_activity (
					stream_activity_line_id,
					stream_activity_stream_id,
					stream_activity_useragent,
					stream_activity_ip,
					stream_activity_php_pid,
					stream_activity_connected_time,
					stream_activity_server_id
				) VALUES (
					:stream_activity_line_id,
					:stream_activity_stream_id,
					:stream_activity_useragent,
					:stream_activity_ip,
					:stream_activity_php_pid,
					:stream_activity_connected_time,
					:stream_activity_server_id
				)', $insert_activity_array
			);					
			
			// GET LAST ADDED ACTIVITY AND CREATE CONNECTION FILE
			$last_con_id = $db->lastInsertId();
			$connection_file = file_put_contents(DOCROOT.'streams/'.$last_con_id.'.con', getmypid());		
			
			// GET ALL ACTIVITY OF LINE
			$set_activity_array = array($set_line[0]['line_id']);
			$set_activity = $db->query('SELECT stream_activity_id FROM cms_stream_activity WHERE stream_activity_line_id = ?', $set_activity_array);
			$activity_count = count($set_activity);
			
			// IF ACTIVITY COUNT HIGHER THEN AVAILABLE COUNT KILL FIRST LAST ACTIVITY
			if($activity_count > $available_activity){
				
				// GET LATEST ACTIVITY
				$set_stream_activity_array = array($set_line[0]['line_id']);
				$set_stream_activity = $db->query('SELECT stream_activity_id, stream_activity_php_pid FROM cms_stream_activity WHERE stream_activity_line_id = ? ORDER BY stream_activity_id ASC LIMIT 1', $set_stream_activity_array);
				
				// DELETE IT FROM DB
				$delete_last_activity_array = array($set_stream_activity[0]['stream_activity_id']);
				$delete_last_activity = $db->query('DELETE FROM cms_stream_activity WHERE stream_activity_id = ?', $delete_last_activity_array);
				
				// KILL LAST ACTIVITY
				shell_exec('kill -9 '.$set_stream_activity[0]['stream_activity_php_pid']);
				
				// DELETE CONNECTION
				shell_exec('rm '.DOCROOT.'streams/'.$set_stream_activity[0]['stream_activity_id'].'.con');
			}
		}	
		
		// GET THE HEADER
		header("Content-Type: video/mp2t");

		// FLUSH CONTENT
		ob_end_flush();

		// SET FAILED TRIES
		$total_failed_tries = 10 * 2;
		$fails = 0;
		
		// MAKE A WHILE LOOP AND LOAD THE NEXT SEGMENT
		while ($fails <= $total_failed_tries) {
			$segment_file = sprintf("%d_%d.ts", $stream_id, $current);
			$nextsegment_file = sprintf("%d_%d.ts", $stream_id, $current + 1);
			
			// IF STREAM HAS FINGERPRINT THEN CREATE A FINGERPRINT TS
			if(check_fingerprint($set_line[0]['line_id'], SERVER)){
				$read_segment = $current;						
				$search_segment = $current + 1;							
				
				start_fingerprint($search_segment, $read_segment, $stream_id, $set_line[0]['line_id'], $line_user);
										
				$fp_segment_file = $stream_id.'_fingerprint_'.$set_line[0]['line_id'].'.ts';	
				$segment_file = $fp_segment_file;			

				// UPDATE STREAM FLAG
				stop_fingerprint($stream_id, $set_line[0]['line_id'], SERVER);
			}
			
			// CHECK FOR SEGMENT FILE IF NOT EXISTS COUNT UP FAIL
			if (!file_exists(DOCROOT.'streams/'.$segment_file)) {
				sleep(1);
				$fails++;
				continue;
			}
			
			// SET FAIL TO 0 IF SEGMENT FILE FOUND AGAIN
			$fails = 0;
			
			// OPEN SEGMENT FILE
			$fp = fopen(DOCROOT.'streams/'.$segment_file, "r");
			
			// AND GO TO THE NEXT SEGMENT TO READ IT
			while (($fails <= $total_failed_tries) && !file_exists(DOCROOT.'streams/'.$nextsegment_file)) {
				
				$data = stream_get_line($fp, 4096);
				if (empty($data)) {
					sleep(1);
					++$fails;
					continue;
				}
				
				echo $data;
				$fails = 0;
			}
			
			// UPDATE THE CONNECTION FILE
			$speedfile = file_put_contents(DOCROOT.'streams/'.$last_con_id.'.con', getmypid());			
			$size = filesize(DOCROOT.'streams/'.$segment_file);
			echo stream_get_line($fp, $size - ftell($fp));
			
			fclose($fp);
			$fails = 0;
			$current++;						
		}					
	}			

} else {
	exit("unable to connect. reason: stream is offline");
}

?>