<?php
// CRYPT AND DECRYPT FUNCTION
function encrypt($string, $key=5) {
	$result = '';
	for($i=0, $k= strlen($string); $i<$k; $i++) {
		$char = substr($string, $i, 1);
		$keychar = substr($key, ($i % strlen($key))-1, 1);
		$char = chr(ord($char)+ord($keychar));
		$result .= $char;
	}
	return base64_encode($result);
}

function decrypt($string, $key=5) {
	$result = '';
	$string = base64_decode($string);
	for($i=0,$k=strlen($string); $i< $k ; $i++) {
		$char = substr($string, $i, 1);
		$keychar = substr($key, ($i % strlen($key))-1, 1);
		$char = chr(ord($char)-ord($keychar));
		$result.=$char;
	}
	return $result;
}

// SERVER FUNCTIONS
function set_cpu_usage(){
	$usage = shell_exec('top -b -n2 | grep "Cpu(s)"|tail -n 1 | awk \'{print $2 + $4}\'');
	return $usage;
}

function set_ram_usage(){
	foreach(file('/proc/meminfo') as $ri){
		$m[strtok($ri, ':')] = strtok('');
	}

	return round(100 - ((int)$m['MemFree'] + (int)$m['Buffers'] + (int)$m['Cached']) / (int)$m['MemTotal'] * 100);
}

function set_uptime(){
    $ut = strtok(@exec("cat /proc/uptime"), ".");
    $days = sprintf("%2d", ($ut / (3600 * 24)));
    $hours = sprintf("%2d", (($ut % (3600 * 24))) / 3600);
    $min = sprintf("%2d", ($ut % (3600 * 24) % 3600) / 60);
    $sec = sprintf("%2d", ($ut % (3600 * 24) % 3600) % 60);
    $uptime = array($days, $hours, $min, $sec);

    if ($uptime[0] == 0) {
        if ($uptime[1] == 0) {
            if ($uptime[2] == 0) {
                $result = ($uptime[3] . " second(s)");
            } else {
                $result = ($uptime[2] . " minute(s)");
            }
        } else {
            $result = ($uptime[1] . " hour(s)");
        }
    } else {
        $result = ($uptime[0] . " day(s)");
    }
	return $result;
}

// STREAM FUNCTIONS
function set_transcoding_profile($profile_id, $stream_width, $stream_height){
	global $db;

	$set_transcoding_array = array($profile_id);
	$set_transcoding = $db->query('SELECT * FROM cms_transcoding WHERE transcoding_id = ?', $set_transcoding_array);

	$transcoding_options = '';
	if($set_transcoding[0]['transcoding_method'] != 'own'){
		if($set_transcoding[0]['transcoding_logo'] != ''){

			$img_data = getimagesize(DOCROOT.'image/'.$set_transcoding[0]['transcoding_logo']);
			$img_width = $img_data[0];
			$img_height = $img_data[1];

			$logo_resolution = explode('x', $set_transcoding[0]['transcoding_logo_resolution']);
			$logo_margin = explode('x', $set_transcoding[0]['transcoding_logo_margin']);
			if(trim($set_transcoding[0]['transcoding_resolution']) != ''){
				$video_scale = explode('x', $set_transcoding[0]['transcoding_resolution']);
				$video_transcode_width = $video_scale[0];
				$video_transcode_height = $video_scale[1];

				$logo_xx = (int) ($img_width * ($video_transcode_width/1920) * ($logo_resolution[0]/$img_width));
				$logo_yy = (int) ($img_height * ($video_transcode_height/1080) * ($logo_resolution[1]/$img_height));

			} else {
				$video_transcode_width = $stream_width;
				$video_transcode_height = $stream_height;

				$logo_xx = (int) ($img_width * ($video_transcode_width/1920) * ($logo_resolution[0]/$img_width));
				$logo_yy = (int) ($img_height * ($video_transcode_height/1080) * ($logo_resolution[1]/$img_height));
			}

			switch($set_transcoding[0]['transcoding_logo_position']){
				// TOP LEFT WITH OUR WITHOUT MARGIN
				case 1:
					if(trim($set_transcoding[0]['transcoding_logo_margin']) != ''){
						$overlay = $logo_margin[0].':'.$logo_margin[1];
					} else {
						$overlay = '15:15';
					}

					break;

				// TOP RIGHT WITH OR WITHOUT MARGIN
				case 2:
					if(trim($set_transcoding[0]['transcoding_logo_margin']) != ''){
						$overlay = 'main_w-overlay_w-'.$logo_margin[1].':'.$logo_margin[0];
					} else {
						$overlay = 'main_w-overlay_w-15:15';
					}

					break;

				// BOTTOM LEFT WITH OR WITHOUT MARGIN
				case 3:
					if(trim($set_transcoding[0]['transcoding_logo_margin']) != ''){
						$overlay = $logo_margin[1].':main_h-overlay_h-'.$logo_margin[0];
					} else {
						$overlay = '15:main_h-overlay_h-15';
					}

					break;

				// BOTTOM RIGHT WITH OR WITHOUT MARGIN
				case 4:
					if(trim($set_transcoding[0]['transcoding_logo_margin']) != ''){
						$overlay = 'main_w-overlay_w-'.$logo_margin[1].':main_h-overlay_h-'.$logo_margin[0];
					} else {
						$overlay = 'main_w-overlay_w-15:main_h-overlay_h-15';
					}

					break;
			}

			$transcoding_options .= '-i '.DOCROOT.'image/'.$set_transcoding[0]['transcoding_logo'];
			$transcoding_options .= ' -filter_complex \'[1:v]scale=w='.$logo_xx.':h='.$logo_yy.'[logo];';
			$transcoding_options .= '[0:v][logo]overlay='.$overlay.',scale=w='.$video_transcode_width.':h='.$video_transcode_height.'[v]\' -map \'[v]\' ';
		}

		$transcoding_options .= ($set_transcoding[0]['transcoding_mapping'] ? $set_transcoding[0]['transcoding_mapping'] : '');
		$transcoding_options .= ' -c:v '.($set_transcoding[0]['transcoding_vcodec'] != 'none' ? $set_transcoding[0]['transcoding_vcodec'] : 'copy').' -preset '.($set_transcoding[0]['transcoding_preset'] ? $set_transcoding[0]['transcoding_preset'] : 'medium');
		$transcoding_options .= ' -b:v '.$set_transcoding[0]['transcoding_avbitrate'].'k '.($set_transcoding[0]['transcoding_minvbitrate'] ? ' -minrate '.$set_transcoding[0]['transcoding_minvbitrate'].'k' : '').($set_transcoding[0]['transcoding_maxvbitrate'] ? ' -maxrate '.$set_transcoding[0]['transcoding_maxvbitrate'].'k' : '');
		$transcoding_options .= ' -bufsize '.$set_transcoding[0]['transcoding_buffsize'].'k '.($set_transcoding[0]['transcoding_resolution'] != '' ? '-s '.$set_transcoding[0]['transcoding_resolution'] : '');
		$transcoding_options .= ' -crf '.$set_transcoding[0]['transcoding_crf'].($set_transcoding[0]['transcoding_vframerate'] ? ' -r '.$set_transcoding[0]['transcoding_vframerate'] : '');
		$transcoding_options .= ' -c:a '.($set_transcoding[0]['transcoding_acodec'] != 'none' ? $set_transcoding[0]['transcoding_acodec'] : 'copy').' -b:a '.($set_transcoding[0]['transcoding_abitrate'] ? $set_transcoding[0]['transcoding_abitrate'] : '128').'k -ac 2';

	} else {
		$transcoding_options .= $set_transcoding[0]['transcoding_own_command'];
	}
	return $transcoding_options;
}

function set_transcoding_method($transcode_id){
	global $db;

	$set_transcoding_array = array($transcode_id);
	$set_transcoding = $db->query('SELECT transcoding_method FROM cms_transcoding WHERE transcoding_id = ?', $set_transcoding_array);

	return $set_transcoding[0]['transcoding_method'];
}

function set_stream_transcoding_cuvid($transcode_id){
	global $db;

	$set_transcoding_array = array($transcode_id);
	$set_transcoding = $db->query('SELECT transcoding_cuvid FROM cms_transcoding WHERE transcoding_id = ?', $set_transcoding_array);

	return $set_transcoding[0]['transcoding_cuvid'];
}

function ffmpeg_live_command($stream_id, $time = 10, $stream_loop = 0){
	global $db;

	echo $stream_loop;

	// SET VARIABLES
	$hashcode = false;
	$delogohashcode = '';
	$i = 0;

	// GET FIRST STREAM
	$set_stream_array = array($stream_id);
	$set_stream = $db->query('SELECT * FROM cms_streams WHERE stream_id = ?', $set_stream_array);

	if(count($set_stream) > 0){

		// KILL FIRST ALL GIVEN PIDS FROM STREAM
		shell_exec('ps aux | grep \'/home/xapicode/iptv_xapicode/streams/'.$stream_id.'_.m3u8\' | grep -v grep | awk \'{print $2}\' | xargs kill -9 > /dev/null 2>/dev/null &');

		// SET THE STREAM STATUS TO TEMP
		$temp_status = json_decode($set_stream[0]['stream_status'], true);
		if($temp_status[0][SERVER] == 3 || $temp_status[0][SERVER] == 4){

			// KILL FIRST ALL GIVEN PIDS FROM STREAM
			shell_exec('ps aux | grep \'/home/xapicode/iptv_xapicode/streams/'.$stream_id.'_.m3u8\' | grep -v grep | awk \'{print $2}\' | xargs kill -9 > /dev/null 2>/dev/null &');

			$temp_status[0][SERVER] = 6;
			delete_stream_data($stream_id);

		} elseif($temp_status[0][SERVER] == 0){

			// KILL FIRST ALL GIVEN PIDS FROM STREAM
			shell_exec('ps aux | grep \'/home/xapicode/iptv_xapicode/streams/'.$stream_id.'_.m3u8\' | grep -v grep | awk \'{print $2}\' | xargs kill -9 > /dev/null 2>/dev/null &');

			$temp_status[0][SERVER] = 7;
			delete_stream_data($stream_id);
		}

		if($stream_loop != 1){
			$update_stream_array = array('stream_status' => json_encode($temp_status), 'stream_id' => $stream_id);
			$update_stream = $db->query('UPDATE cms_streams SET stream_status = :stream_status WHERE stream_id = :stream_id', $update_stream_array);
		} else {
			$update_stream_array = array('stream_loop_from_status' => 6, 'stream_id' => $stream_id);
			$update_stream = $db->query('UPDATE cms_streams SET stream_loop_from_status = :stream_loop_from_status WHERE stream_id = :stream_id', $update_stream_array);
		}

		// IF STREAM HAS HASHCODE ID GET THE HASHCODE DB
		if($set_stream[0]['stream_hashcode_id'] != NULL){
			$set_hashcode_array = array($set_stream[0]['stream_hashcode_id']);
			$set_hashcode = $db->query('SELECT * FROM cms_hashcode WHERE hashcode_id = ?', $set_hashcode_array);

			$hashcode = true;
		}

		// GET THE SOURCE OF STREAM
		$stream_source = json_decode($set_stream[0]['stream_play_pool'], true);
		$stream_source = $stream_source[$set_stream[0]['stream_play_pool_id']];

		// GET SETTINGS FOR FFMPEG
		$set_setting = $db->query('SELECT setting_stream_analyze, setting_stream_probesize FROM cms_settings');
		$stream_probesize = $set_setting[0]['setting_stream_probesize'];
		$stream_analyze_duration = $set_setting[0]['setting_stream_analyze'];

		// IF STREAM IS NOT HASHCODED THEN START THE STREAM NORMALY
		if(!$hashcode){

			if($set_stream[0]['stream_transcode_id'] == 0){
				if(parse_url($stream_source, PHP_URL_HOST) == 'youtube.com' || parse_url($stream_source, PHP_URL_HOST) == 'https://www.youtube.com' || parse_url($stream_source, PHP_URL_HOST) == 'www.youtube.com' || parse_url($stream_source, PHP_URL_HOST) == 'mobil.youtube.com' || parse_url($stream_source, PHP_URL_HOST) == 'youtu.be'){
					$youtube_url = trim(shell_exec(DOCROOT.'bin/youtube-dl --geo-bypass -f best --get-url '.$stream_source));
					$start_command = DOCROOT.'bin/ffmpeg -y -nostdin -hide_banner -loglevel warning -err_detect ignore_err -nofix_dts -start_at_zero -copyts -vsync 0 -correct_ts_overflow 0 -avoid_negative_ts disabled -max_interleave_delta 0 '.($set_stream[0]['stream_http_proxy'] != NULL ? '-http_proxy '.$set_stream[0]['stream_http_proxy'] : '').' -user_agent "'.$set_stream[0]['stream_user_agent'].'" -probesize '.$stream_probesize.' -analyzeduration '.$stream_analyze_duration.' -i "'.$youtube_url.'" -vcodec copy -scodec copy -acodec copy -individual_header_trailer 0 -f segment -segment_format mpegts -segment_time '.$time.' -segment_list_size 6 -segment_format_options mpegts_flags=+initial_discontinuity:mpegts_copyts=1 -segment_list_type m3u8 -segment_list_flags +live+delete -segment_list '.DOCROOT.'streams/'.$stream_id.'_.m3u8 '.DOCROOT.'streams/'.$stream_id.'_%d.ts';
				} else {
					if(parse_url($stream_source)['scheme'] != 'rtmp'){
						$start_command = DOCROOT.'bin/ffmpeg -y -nostdin -hide_banner -loglevel warning -err_detect ignore_err -nofix_dts -start_at_zero -copyts -vsync 0 -correct_ts_overflow 0 -avoid_negative_ts disabled -max_interleave_delta 0 '.($set_stream[0]['stream_http_proxy'] != NULL ? '-http_proxy '.$set_stream[0]['stream_http_proxy'] : '').' -user_agent "'.$set_stream[0]['stream_user_agent'].'" -probesize '.$stream_probesize.' -analyzeduration '.$stream_analyze_duration.' -i "'.$stream_source.'" -vcodec copy -scodec copy -acodec copy -individual_header_trailer 0 -f segment -segment_format mpegts -segment_time '.$time.' -segment_list_size 6 -segment_format_options mpegts_flags=+initial_discontinuity:mpegts_copyts=1 -segment_list_type m3u8 -segment_list_flags +live+delete -segment_list '.DOCROOT.'streams/'.$stream_id.'_.m3u8 '.DOCROOT.'streams/'.$stream_id.'_%d.ts';
					} else {
						$start_command = DOCROOT.'bin/ffmpeg -y -nostdin -hide_banner -loglevel warning -err_detect ignore_err -nofix_dts -start_at_zero -copyts -vsync 0 -correct_ts_overflow 0 -avoid_negative_ts disabled -max_interleave_delta 0 '.($set_stream[0]['stream_http_proxy'] != NULL ? '-http_proxy '.$set_stream[0]['stream_http_proxy'] : '').' -probesize '.$stream_probesize.' -analyzeduration '.$stream_analyze_duration.' -i "'.$stream_source.'" -vcodec copy -scodec copy -acodec copy -individual_header_trailer 0 -f segment -segment_format mpegts -segment_time '.$time.' -segment_list_size 6 -segment_format_options mpegts_flags=+initial_discontinuity:mpegts_copyts=1 -segment_list_type m3u8 -segment_list_flags +live+delete -segment_list '.DOCROOT.'streams/'.$stream_id.'_.m3u8 '.DOCROOT.'streams/'.$stream_id.'_%d.ts';
					}
				}

			} else {

				// SET THE PROBE COMMAND
				if(parse_url($stream_source, PHP_URL_HOST) == 'youtube.com' || parse_url($stream_source, PHP_URL_HOST) == 'https://www.youtube.com' || parse_url($stream_source, PHP_URL_HOST) == 'www.youtube.com' || parse_url($stream_source, PHP_URL_HOST) == 'mobil.youtube.com' || parse_url($stream_source, PHP_URL_HOST) == 'youtu.be'){
					$youtube_url = trim(shell_exec(DOCROOT.'bin/youtube-dl --geo-bypass -f best --get-url '.$stream_source));
					$probe_command = '/usr/bin/timeout 15s '.DOCROOT.'bin/ffprobe '.($set_stream[0]['stream_http_proxy'] != NULL ? '-http_proxy '.$set_stream[0]['stream_http_proxy'] : '').' -user_agent "'.$set_stream[0]['stream_user_agent'].'" -i "'.$youtube_url.'" -v quiet -print_format json -show_streams 2>&1';
				} else {
					if(parse_url($stream_source)['scheme'] != 'rtmp'){
						$probe_command = '/usr/bin/timeout 15s '.DOCROOT.'bin/ffprobe '.($set_stream[0]['stream_http_proxy'] != NULL ? '-http_proxy '.$set_stream[0]['stream_http_proxy'] : '').' -user_agent "'.$set_stream[0]['stream_user_agent'].'" -i "'.$stream_source.'" -v quiet -print_format json -show_streams 2>&1';
					} else {
						$probe_command = '/usr/bin/timeout 15s '.DOCROOT.'bin/ffprobe '.($set_stream[0]['stream_http_proxy'] != NULL ? '-http_proxy '.$set_stream[0]['stream_http_proxy'] : '').' -i "'.$stream_source.'" -v quiet -print_format json -show_streams 2>&1';
					}
				}

				$probe_result = json_decode(shell_exec($probe_command), true);
				$transcoding = set_transcoding_profile($set_stream[0]['stream_transcode_id'], $probe_result['streams'][0]['width'], $probe_result['streams'][0]['height']);
				$transcoding_method = set_transcoding_method($set_stream[0]['stream_transcode_id']);

				if($transcoding_method == 'cpu'){
					if(parse_url($stream_source, PHP_URL_HOST) == 'youtube.com' || parse_url($stream_source, PHP_URL_HOST) == 'https://www.youtube.com' || parse_url($stream_source, PHP_URL_HOST) == 'www.youtube.com' || parse_url($stream_source, PHP_URL_HOST) == 'mobil.youtube.com' || parse_url($stream_source, PHP_URL_HOST) == 'youtu.be'){
						$youtube_url = trim(shell_exec(DOCROOT.'bin/youtube-dl --geo-bypass -f best --get-url '.$stream_source));
						$start_command = DOCROOT.'bin/ffmpeg -y -nostdin '.($set_stream[0]['stream_http_proxy'] != NULL ? '-http_proxy '.$set_stream[0]['stream_http_proxy'] : '').' -user_agent "'.$set_stream[0]['stream_user_agent'].'" -hide_banner -loglevel warning -err_detect ignore_err -nofix_dts -start_at_zero -copyts -correct_ts_overflow 0 -avoid_negative_ts disabled -max_interleave_delta 0 -probesize '.$stream_probesize.' -analyzeduration '.$stream_analyze_duration.' -i "'.$youtube_url.'" '.$transcoding.' -individual_header_trailer 0 -f segment -segment_format mpegts -segment_time 10 -segment_list_size 6 -segment_format_options mpegts_flags=+initial_discontinuity:mpegts_copyts=1 -segment_list_type m3u8 -segment_list_flags +live+delete -segment_list '.DOCROOT.'streams/'.$stream_id.'_.m3u8 '.DOCROOT.'streams/'.$stream_id.'_%d.ts';
					} else {
						if(parse_url($stream_source)['scheme'] != 'rtmp'){
							$start_command = DOCROOT.'bin/ffmpeg -y -nostdin '.($set_stream[0]['stream_http_proxy'] != NULL ? '-http_proxy '.$set_stream[0]['stream_http_proxy'] : '').' -user_agent "'.$set_stream[0]['stream_user_agent'].'" -hide_banner -loglevel warning -err_detect ignore_err -nofix_dts -start_at_zero -copyts -correct_ts_overflow 0 -avoid_negative_ts disabled -max_interleave_delta 0 -probesize '.$stream_probesize.' -analyzeduration '.$stream_analyze_duration.' -i "'.$stream_source.'" '.$transcoding.' -individual_header_trailer 0 -f segment -segment_format mpegts -segment_time 10 -segment_list_size 6 -segment_format_options mpegts_flags=+initial_discontinuity:mpegts_copyts=1 -segment_list_type m3u8 -segment_list_flags +live+delete -segment_list '.DOCROOT.'streams/'.$stream_id.'_.m3u8 '.DOCROOT.'streams/'.$stream_id.'_%d.ts';
						} else {
							$start_command = DOCROOT.'bin/ffmpeg -y -nostdin '.($set_stream[0]['stream_http_proxy'] != NULL ? '-http_proxy '.$set_stream[0]['stream_http_proxy'] : '').' -hide_banner -loglevel warning -err_detect ignore_err -nofix_dts -start_at_zero -copyts -correct_ts_overflow 0 -avoid_negative_ts disabled -max_interleave_delta 0 -probesize '.$stream_probesize.' -analyzeduration '.$stream_analyze_duration.' -i "'.$stream_source.'" '.$transcoding.' -individual_header_trailer 0 -f segment -segment_format mpegts -segment_time 10 -segment_list_size 6 -segment_format_options mpegts_flags=+initial_discontinuity:mpegts_copyts=1 -segment_list_type m3u8 -segment_list_flags +live+delete -segment_list '.DOCROOT.'streams/'.$stream_id.'_.m3u8 '.DOCROOT.'streams/'.$stream_id.'_%d.ts';
						}
					}

				} elseif($transcoding_method == 'gpu') {
					if(parse_url($stream_source, PHP_URL_HOST) == 'youtube.com' || parse_url($stream_source, PHP_URL_HOST) == 'https://www.youtube.com' || parse_url($stream_source, PHP_URL_HOST) == 'www.youtube.com' || parse_url($stream_source, PHP_URL_HOST) == 'mobil.youtube.com' || parse_url($stream_source, PHP_URL_HOST) == 'youtu.be'){
						$youtube_url = trim(shell_exec(DOCROOT.'bin/youtube-dl --geo-bypass -f best --get-url '.$stream_source));
						$start_command = DOCROOT.'bin/nvenc/ffmpeg -y -loglevel debug '.($set_stream[0]['stream_http_proxy'] != NULL ? '-http_proxy '.$set_stream[0]['stream_http_proxy'] : '').' -hwaccel cuvid -hwaccel_device 0 -c:v '.set_stream_transcoding_cuvid($set_stream[0]['stream_transcode_id']).' -gpu 0 -probesize '.$stream_probesize.' -analyzeduration '.$stream_analyze_duration.' -i "'.$youtube_url.'" '.$transcoding.' -hls_flags delete_segments -hls_time 5 -hls_list_size 10 -f hls '.DOCROOT.'streams/'.$stream_id.'_.m3u8';
					} else {
						if(parse_url($stream_source)['scheme'] != 'rtmp'){
							$start_command = DOCROOT.'bin/nvenc/ffmpeg -y -loglevel debug '.($set_stream[0]['stream_http_proxy'] != NULL ? '-http_proxy '.$set_stream[0]['stream_http_proxy'] : '').' -user_agent "'.$set_stream[0]['stream_user_agent'].'" -hwaccel cuvid -hwaccel_device 0 -c:v '.set_stream_transcoding_cuvid($set_stream[0]['stream_transcode_id']).' -gpu 0 -probesize '.$stream_probesize.' -analyzeduration '.$stream_analyze_duration.' -i "'.$stream_source.'" '.$transcoding.' -hls_flags delete_segments -hls_time 5 -hls_list_size 10 -f hls '.DOCROOT.'streams/'.$stream_id.'_.m3u8';
						} else {
							$start_command = DOCROOT.'bin/nvenc/ffmpeg -y -loglevel debug '.($set_stream[0]['stream_http_proxy'] != NULL ? '-http_proxy '.$set_stream[0]['stream_http_proxy'] : '').' -hwaccel cuvid -hwaccel_device 0 -c:v '.set_stream_transcoding_cuvid($set_stream[0]['stream_transcode_id']).' -gpu 0 -probesize '.$stream_probesize.' -analyzeduration '.$stream_analyze_duration.' -i "'.$stream_source.'" '.$transcoding.' -hls_flags delete_segments -hls_time 5 -hls_list_size 10 -f hls '.DOCROOT.'streams/'.$stream_id.'_.m3u8';
						}
					}

				} elseif($transcoding_method == 'own') {

					if(parse_url($stream_source, PHP_URL_HOST) == 'youtube.com' || parse_url($stream_source, PHP_URL_HOST) == 'https://www.youtube.com' || parse_url($stream_source, PHP_URL_HOST) == 'www.youtube.com' || parse_url($stream_source, PHP_URL_HOST) == 'mobil.youtube.com' || parse_url($stream_source, PHP_URL_HOST) == 'youtu.be'){
						$youtube_url = trim(shell_exec(DOCROOT.'bin/youtube-dl --geo-bypass -f best --get-url '.$stream_source));
						$transcoding_command = str_replace('-i {INPUT}', '-i '.$youtube_url, $transcoding);
						$start_command = DOCROOT.'bin/nvenc/ffmpeg -y -loglevel debug '.($set_stream[0]['stream_http_proxy'] != NULL ? '-http_proxy '.$set_stream[0]['stream_http_proxy'] : '').' '.$transcoding_command.' -hls_flags delete_segments -hls_time 5 -hls_list_size 10 -f hls '.DOCROOT.'streams/'.$stream_id.'_.m3u8';
					} else {
						if(parse_url($stream_source)['scheme'] != 'rtmp'){
							$transcoding_command = str_replace('-i {INPUT}', '-i '.$stream_source, $transcoding);
							$start_command = DOCROOT.'bin/nvenc/ffmpeg -y -loglevel debug '.($set_stream[0]['stream_http_proxy'] != NULL ? '-http_proxy '.$set_stream[0]['stream_http_proxy'] : '').' -user_agent "'.$set_stream[0]['stream_user_agent'].'" '.$transcoding_command.' -hls_flags delete_segments -hls_time 5 -hls_list_size 10 -f hls '.DOCROOT.'streams/'.$stream_id.'_.m3u8';
						} else {
							$transcoding_command = str_replace('-i {INPUT}', '-i '.$stream_source, $transcoding);
							$start_command = DOCROOT.'bin/nvenc/ffmpeg -y -loglevel debug '.($set_stream[0]['stream_http_proxy'] != NULL ? '-http_proxy '.$set_stream[0]['stream_http_proxy'] : '').' '.$transcoding_command.' -hls_flags delete_segments -hls_time 5 -hls_list_size 10 -f hls '.DOCROOT.'streams/'.$stream_id.'_.m3u8';
						}
					}
				}
			}

		} else {

			$hashcode_images = explode(',', $set_hashcode[0]['hashcode_image']);
			foreach($hashcode_images as $hashcode_image){
				if($i == 0){
					$delogohashcode = 'logo@'.DOCROOT.'image/'.$hashcode_image.'&similarity@'.$set_hashcode[0]['hashcode_similarity'].'&scale@'.$set_hashcode[0]['hashcode_scale'].'&border@'.$set_hashcode[0]['hashcode_border'];
				} else {
					$delogohashcode .= '##logo@'.DOCROOT.'image/'.$hashcode_image.'&similarity@'.$set_hashcode[0]['hashcode_similarity'].'&scale@'.$set_hashcode[0]['hashcode_scale'].'&border@'.$set_hashcode[0]['hashcode_border'];
				}
				$i++;
			}

			if($set_hashcode[0]['hashcode_vbitrate'] != NULL){
				$bitrate_option = ' -b:v '.$set_hashcode[0]['hashcode_vbitrate'].'k -maxrate '.$set_hashcode[0]['hashcode_vbitrate'].'k -bufsize '.$set_hashcode[0]['hashcode_vbitrate'].'k ';
			} else {
				$bitrate_option = ' ';
			}

			if(parse_url($stream_source)['scheme'] != 'rtmp'){
				$start_command = 'ffmpeg2 -y -nostats '.($set_stream[0]['stream_http_proxy'] != NULL ? '-http_proxy '.$set_stream[0]['stream_http_proxy'] : '').' -user_agent "'.$set_stream[0]['stream_user_agent'].'" -i "'.$stream_source.'" -filter_complex "dyndelogo='.$delogohashcode.':preprocess_frames='.$set_hashcode[0]['hashcode_preprocess_frame'].':frame_interval='.$set_hashcode[0]['hashcode_frameinterval'].':algorithm='.$set_hashcode[0]['hashcode_algorithm'].':continue_frames=150" -c:v libx264 '.$bitrate_option.' -preset '.$set_hashcode[0]['hashcode_preset'].' -crf '.$set_hashcode[0]['hashcode_crf'].' -c:a aac -movflags +faststart -strict -2 -dn -f hls -hls_flags delete_segments -hls_time 10 -hls_list_size 6 '.DOCROOT.'streams/'.$stream_id.'_.m3u8';
			} else {
				$start_command = 'ffmpeg2 -y -nostats '.($set_stream[0]['stream_http_proxy'] != NULL ? '-http_proxy '.$set_stream[0]['stream_http_proxy'] : '').' -i "'.$stream_source.'" -filter_complex "dyndelogo='.$delogohashcode.':preprocess_frames='.$set_hashcode[0]['hashcode_preprocess_frame'].':frame_interval='.$set_hashcode[0]['hashcode_frameinterval'].':algorithm='.$set_hashcode[0]['hashcode_algorithm'].':continue_frames=150" -c:v libx264 '.$bitrate_option.' -preset '.$set_hashcode[0]['hashcode_preset'].' -crf '.$set_hashcode[0]['hashcode_crf'].' -c:a aac -movflags +faststart -strict -2 -dn -f hls -hls_flags delete_segments -hls_time 10 -hls_list_size 6 '.DOCROOT.'streams/'.$stream_id.'_.m3u8';
			}
		}

		$stream_pid = shell_exec($start_command . ' > /dev/null 2>&1 & echo $!');
		if(!empty($stream_pid)){
			file_put_contents(DOCROOT.'streams/'.$stream_id.'_checker', $stream_pid);
			return true;
		}
	}
}

function ffmpeg_local_command($stream_id){
	global $db;

	// GET FIRST STREAM
	$set_stream_array = array($stream_id);
	$set_stream = $db->query('SELECT * FROM cms_streams WHERE stream_id = ?', $set_stream_array);

	if(count($set_stream) > 0){

		// OPEN THE DIRECTORY
		if ($handle = opendir($set_stream[0]['stream_local_source'])) {
			$directory_source = array();
			while (false !== ($entry = readdir($handle))) {
				if ($entry != "." && $entry != ".." && $entry != $stream_id.'.txt') {
					array_push($directory_source, $entry);
				}
			}

			// SHUFFLE THE DIRECTORY
			shuffle($directory_source);
			$play_source = '';
			foreach($directory_source as $source_to_play){
				$play_source = $source_to_play;
			}

			$stream_source = $set_stream[0]['stream_local_source'].'/'.$play_source;

			// SET THE PROBE COMMAND
			$probe_command = '/usr/bin/timeout 15s '.DOCROOT.'bin/ffprobe -i "'.$stream_source.'" -v quiet -print_format json -show_streams 2>&1';
			$probe_result = json_decode(shell_exec($probe_command), true);

			// CHECK STREAM IF IS WORK
			if(is_array($probe_result) && count($probe_result) > 0){

				if($set_stream[0]['stream_transcode_id'] == 0){

					if($set_stream[0]['stream_concat'] == 1){
						$start_command = DOCROOT.'bin/concat -auto_convert 1 -f concat -safe 0 -re -i '.$set_stream[0]['stream_local_source'].'/'.$stream_id.'.txt -c:v copy -hls_flags delete_segments -hls_time 8 -hls_list_size 6 -hls_allow_cache 0 -f hls '.DOCROOT.'streams/'.$stream_id.'_.m3u8';
					} else {
						$start_command = DOCROOT.'bin/ffmpeg -re -fflags +genpts -y -nostdin -hide_banner -loglevel warning -err_detect ignore_err -nofix_dts -start_at_zero -copyts -vsync 0 -correct_ts_overflow 0 -avoid_negative_ts disabled -i "'.$stream_source.'" -vcodec copy  -scodec copy -acodec copy -individual_header_trailer 0 -f segment -segment_format mpegts -segment_time 10 -segment_list_size 6 -segment_format_options mpegts_flags=+initial_discontinuity:mpegts_copyts=1 -segment_list_type m3u8 -segment_list_flags +live+delete -segment_list '.DOCROOT.'streams/'.$stream_id.'_.m3u8 '.DOCROOT.'streams/'.$stream_id.'_%d.ts';
					}

				} else {
					$transcoding = set_transcoding_profile($set_stream[0]['stream_transcode_id'], $probe_result['streams'][0]['width'], $probe_result['streams'][0]['height']);
					$transcoding_method = set_transcoding_method($set_stream[0]['stream_transcode_id']);

					if($transcoding_method == 'cpu'){
						$start_command = DOCROOT.'bin/ffmpeg -re -fflags +genpts -y -nostdin -hide_banner -loglevel warning -err_detect ignore_err -nofix_dts -start_at_zero -copyts -vsync 0 -correct_ts_overflow 0 -avoid_negative_ts disabled -i "'.$directory_source.'" '.$transcoding.' -individual_header_trailer 0 -f segment -segment_format mpegts -segment_time 10 -segment_list_size 6 -segment_format_options mpegts_flags=+initial_discontinuity:mpegts_copyts=1 -segment_list_type m3u8 -segment_list_flags +live+delete -segment_list '.DOCROOT.'streams/'.$stream_id.'_.m3u8 '.DOCROOT.'streams/'.$stream_id.'_%d.ts';
					} else {
						$start_command = DOCROOT.'bin/nvenc/ffmpeg -y -loglevel debug -user_agent "'.$set_stream[0]['stream_user_agent'].'" -hwaccel cuvid -hwaccel_device 0 -c:v '.set_stream_transcoding_cuvid($set_stream[0]['stream_transcode_id']).' -gpu 0 -i "'.$directory_source.'" '.$transcoding.' -hls_flags delete_segments -hls_time 5 -hls_list_size 10 -f hls '.DOCROOT.'streams/'.$stream_id.'_.m3u8';
					}
				}

				// GET THE PID OF STREAM
				$stream_pid = shell_exec($start_command . ' > /dev/null 2>&1 & echo $!');
				if(!empty($stream_pid)){

					// SET THE STREAM STATUS
					$stream_status = json_decode($set_stream[0]['stream_status'], true);
					$stream_status[0][SERVER] = 1;

					$update_stream_array = array('stream_status' => json_encode($stream_status), 'stream_id' => $stream_id);
					$update_stream = $db->query('UPDATE cms_streams SET stream_status = :stream_status WHERE stream_id = :stream_id', $update_stream_array);

					// SET STREAM PID AND STARTED TIME
					$insert_stream_sys_array = array('stream_pid' => $stream_pid, 'stream_start_time' => time(), 'stream_id' => $stream_id, 'server_id' => SERVER);
					$insert_stream_sys = $db->query('INSERT INTO cms_stream_sys (stream_pid, stream_start_time, stream_id, server_id) VALUES(:stream_pid, :stream_start_time, :stream_id, :server_id)', $insert_stream_sys_array);

					return true;

				} else {

					// SET THE STREAM STATUS
					$stream_status = json_decode($set_stream[0]['stream_status'], true);
					$stream_status[0][SERVER] = 0;

					$update_stream_array = array('stream_status' => json_encode($stream_status), 'stream_id' => $stream_id);
					$update_stream = $db->query('UPDATE cms_streams SET stream_status = :stream_status WHERE stream_id = :stream_id', $update_stream_array);

					return false;
				}

			} else {
				// SET THE STREAM STATUS
				$stream_status = json_decode($set_stream[0]['stream_status'], true);
				$stream_status[0][SERVER] = 0;

				$update_stream_array = array('stream_status' => json_encode($stream_status), 'stream_play_pool_id' => $stream_play_pool_id, 'stream_id' => $stream_id);
				$update_stream = $db->query('UPDATE cms_streams SET stream_status = :stream_status, stream_play_pool_id = :stream_play_pool_id WHERE stream_id = :stream_id', $update_stream_array);

				return false;
			}
		}
	}
}

function start_loop_stream($stream_id, $server_ip, $server_port){
	global $db;

	$start_command = DOCROOT.'bin/ffmpeg -y -nostdin -hide_banner -loglevel warning -err_detect ignore_err -nofix_dts -start_at_zero -copyts -vsync 0 -correct_ts_overflow 0 -avoid_negative_ts disabled -max_interleave_delta 0 -user_agent " looper" -i "http://'.$server_ip.':'.$server_port.'/live/loop/loop/'.$stream_id.'.ts" -vcodec copy -scodec copy -acodec copy -individual_header_trailer 0 -f segment -segment_format mpegts -segment_time 10 -segment_list_size 6 -segment_format_options mpegts_flags=+initial_discontinuity:mpegts_copyts=1 -segment_list_type m3u8 -segment_list_flags +live+delete -segment_list '.DOCROOT.'streams/'.$stream_id.'_.m3u8 '.DOCROOT.'streams/'.$stream_id.'_%d.ts';
	$stream_pid = shell_exec($start_command . ' > /dev/null 2>&1 & echo $!');
	if(!empty($stream_pid)){

		$insert_stream_sys_array = array('stream_pid' => $stream_pid, 'stream_start_time' => time(), 'stream_id' => $stream_id, 'stream_loop' => 0, 'server_id' => SERVER);
		$insert_stream_sys = $db->query('INSERT INTO cms_stream_sys (stream_pid, stream_start_time, stream_loop, stream_id, server_id) VALUES(:stream_pid, :stream_start_time, :stream_loop, :stream_id, :server_id)', $insert_stream_sys_array);

		return true;
	} else {
		return false;
	}
}

function start_live_stream($stream_id, $binary_id, $hashcode_id, $status, $stream_loop = 0){

	// IF STREAM STATUS OFFLINE OR IN START POSITION CHECK FIRST BINARY AND START FFMPEG
	switch($status){
		case 0:
			// IF BINARY 1 (FFMPEG) START FFMPEG COMMAND
			if($binary_id == 1){
				if(ffmpeg_live_command($stream_id, 10, $stream_loop)){
					return true;
				}
			}
		break;

		case 3:
			// IF BINARY 1 (FFMPEG) START FFMPEG COMMAND
			if($binary_id == 1){
				if(ffmpeg_live_command($stream_id, 10, $stream_loop)){
					return true;
				}
			}
		break;

		case 4:

			// STOP FIRST STREAM AND START IT AGAIN
			stop_stream($stream_id, $binary_id, $hashcode_id, 4, $stream_loop);

			// IF BINARY 1 (FFMPEG) START FFMPEG COMMAND
			if($binary_id == 1){
				if(ffmpeg_live_command($stream_id, 10, $stream_loop)){
					return true;
				}
			}
		break;
	}
}

function start_local_stream($stream_id, $binary_id, $hashcode_id, $status){
	global $db;

	delete_sys_if_exists($stream_id);

	// IF STREAM STATUS OFFLINE OR IN START POSITION CHECK FIRST BINARY AND START FFMPEG
	if($status == 0 || $status == 3){
		// IF BINARY 1 (FFMPEG) START FFMPEG COMMAND
		if($binary_id == 1){
			if(ffmpeg_local_command($stream_id)){
				return true;
			}
		}

	}

	// START COMMAND COMES FROM RESTART STATUS
	elseif($status == 4){

		// STOP FIRST STREAM AND START IT AGAIN
		stop_stream($stream_id, $binary_id, $hashcode_id);

		// IF BINARY 1 (FFMPEG) START FFMPEG COMMAND
		if($binary_id == 1){
			if(ffmpeg_local_command($stream_id)){
				return true;
			}
		}
	}
}

function start_transcoding_files($stream_id, $bitrate = 2500, $resolution = '1280:720'){
	global $db;

	// GET FIRST DIRECTORY OF STREAM LOCAL SOURCE
	$set_stream_array = array($stream_id);
	$set_stream = $db->query('SELECT * FROM cms_streams WHERE stream_id = ?', $set_stream_array);

	if ($handle = opendir($set_stream[0]['stream_local_source'])) {
		$directory_source = array();
		while (false !== ($entry = readdir($handle))) {
			if ($entry != "." && $entry != ".." && $entry != $stream_id.'.txt') {
				$directory_source[] = $set_stream[0]['stream_local_source'].'/'.$entry;
			}
		}

		$stream_audio_channels = 0;
		foreach($directory_source as $source){
			$stream_info = reset(json_decode(@shell_exec(DOCROOT.'bin/ffprobe -v quiet -print_format json -show_format -show_streams '.$source), true));
			if($stream_audio_channels < (int) $stream_info[$playlistsound[$key]+1]['channels']){
				$stream_audio_channels = (int) $stream_info[$playlistsound[$key]+1]['channels'];
			}
		}

		$ffmpeg_command = '';
		$outputfile = '';
		foreach($directory_source as $source){
			$check_vod = explode('_', $source);
			if(!file_exists($set_stream[0]['stream_local_source'].'/'.$stream_id.'_'.end($check_vod))){
				$ffmpeg_command .= DOCROOT.'bin/ffmpeg -i '.$source.' -c:v libx264 -b:v '.$bitrate.'k -maxrate '.$bitrate.'k -bufsize '.$bitrate.'k -vf scale='.$resolution.' -c:a aac -b:a 128k -ac 2 -preset fast -profile:v baseline -level 3.0 -movflags +faststart -threads 6 '.$set_stream[0]['stream_local_source'].'/'.$stream_id.'_'.end(explode('/', $source)).PHP_EOL;
				$ffmpeg_command .= 'rm -rf '.$source.PHP_EOL;
				$outputfile .= "file '".$set_stream[0]['stream_local_source'].'/'.$stream_id.'_'.end(explode('/', $source))."'".PHP_EOL;
			} else {
				$outputfile .= "file '".$set_stream[0]['stream_local_source'].'/'.end(explode('/', $source))."'".PHP_EOL;
			}
		}


		file_put_contents('/tmp/'.$stream_id.'.sh', $ffmpeg_command);
		file_put_contents($set_stream[0]['stream_local_source'].'/'.$stream_id.'.txt', $outputfile);

		$transcoding_pid = shell_exec('sh /tmp/'.$stream_id.'.sh > /dev/null 2>&1 & echo $!');

		$transcoding = false;
		if($transcoding_pid != ''){

			file_put_contents('/tmp/'.$stream_id.'.txt', $transcoding_pid);

			$update_stream_array = array('stream_concat_status' => 1, 'stream_id' => $stream_id);
			$update_stream = $db->query('UPDATE cms_streams SET stream_concat_status = :stream_concat_status WHERE stream_id = :stream_id', $update_stream_array);

			$transcoding = true;

		} else {
			$transcoding = false;
		}

		return $transcoding;
	}
}

function stop_stream($stream_id, $binary_id, $hashcode_id, $status = '', $stream_loop = 0){

	global $db;

	// KILL FIRST ALL GIVEN PIDS FROM STREAM
	shell_exec('ps aux | grep \'/home/xapicode/iptv_xapicode/streams/'.$stream_id.'_.m3u8\' | grep -v grep | awk \'{print $2}\' | xargs kill -9  > /dev/null 2>/dev/null &');

	delete_sys_if_exists($stream_id);
	delete_stream_data($stream_id);

	// IF COMMING FROM RESTART STATUS DONT UPDATE STREAM STATUS
	if($status != 4){
		update_stream_status($stream_id, 2, $stream_loop);
	}

	return true;
}

function offline_stream($stream_id, $stream_loop = 0){
	delete_stream_data($stream_id);
	update_stream_status($stream_id, 0, $stream_loop);
}

function get_pid_of_stream($stream_id){
	global $db;

	// CHECK PID EXISTS ON DB
	$set_stream_sys_array = array($stream_id, SERVER);
	$set_stream_sys = $db->query('SELECT stream_pid FROM cms_stream_sys WHERE stream_id = ? AND server_id = ?', $set_stream_sys_array);

	// IF PID EXISTS ON DB THEN RETURN IT
	if(count($set_stream_sys) > 0){
		$stream_pid = $set_stream_sys[0]['stream_pid'];
	} else {
		$stream_pid = 0;
	}

	return $stream_pid;
}

function get_start_time_of_stream($stream_id){
	global $db;

	// CHECK START TIME IS EXISTS
	$set_stream_sys_array = array($stream_id, SERVER);
	$set_stream_sys = $db->query('SELECT stream_start_time FROM cms_stream_sys WHERE stream_id = ? AND server_id = ?', $set_stream_sys_array);

	// IF START TIME EXISTS RETURN IT
	if(count($set_stream_sys) > 0){
		$stream_start_time = $set_stream_sys[0]['stream_start_time'];
	} else {
		$stream_start_time = 0;
	}

	return $stream_start_time;
}

function delete_stream_data($stream_id){
	global $db;

	// DELETE STREAM DATA
	$delete_stream = "/bin/rm -r ".DOCROOT . "streams/" .$stream_id . "_*";
	shell_exec($delete_stream .' > /dev/null 2>&1');

	// DELETE PROGRESS DATA
	//$delete_progress = "/bin/rm -r ".DOCROOT . "streams/" .$stream_id;
	//shell_exec($delete_progress .' > /dev/null 2>&1');

	// KILL ACTIVITY OF STREAM
	kill_activity_of_stream($stream_id);

	// DELETE SYS DATA FROM DB
	delete_sys_if_exists($stream_id);
}

function delete_sys_if_exists($stream_id){
	global $db;

	// CHECK SYS IS EXISTS
	$set_stream_sys_array = array($stream_id, SERVER);
	$set_stream_sys = $db->query('SELECT stream_sys_id FROM cms_stream_sys WHERE stream_id = ? AND server_id = ?', $set_stream_sys_array);

	if(count($set_stream_sys) > 0){
		// DELETE SYS FROM DB
		$delete_stream_sys_array = array($stream_id, SERVER);
		$delete_stream_sys = $db->query('DELETE FROM cms_stream_sys WHERE stream_id = ? AND server_id = ?', $delete_stream_sys_array);
	}
}

function kill_activity_of_stream($stream_id){
	global $db;

	// GET ACTIVITY OF STREAM
	$set_stream_activity_array = array($stream_id, SERVER);
	$set_stream_activity = $db->query('SELECT stream_activity_id, stream_activity_php_pid FROM cms_stream_activity WHERE stream_activity_stream_id = ? AND stream_activity_server_id = ?', $set_stream_activity_array);

	// IF ACTIVITY FOUND
	if(count($set_stream_activity) > 0){

		foreach($set_stream_activity as $get_stream_activity){

			// KILL ACTIVITY
			$activity_pid = $get_stream_activity['stream_activity_php_pid'];
			if(file_exists('/proc/'.$activity_pid)){
				shell_exec('kill -9 '.$activity_pid . ' > /dev/null');
			}

			// DELETE FIRST CONNECTION FILE FROM STREAMS FOLDER
			$delete_connection = "/bin/rm -r ".DOCROOT . "streams/" .$get_stream_activity['stream_activity_id'] . ".con";
			shell_exec($delete_connection .' > /dev/null 2>&1');

			// DELETE FROM ACTIVITY
			$delete_stream_activity_array = array($stream_id, SERVER);
			$delete_stream_activity = $db->query('DELETE FROM cms_stream_activity WHERE stream_activity_stream_id = ? AND stream_activity_server_id = ?', $delete_stream_activity_array);
		}

	} else {
		shell_exec('ps aux | grep \'/home/xapicode/iptv_xapicode/streams/'.$stream_id.'_.m3u8\' | grep -v grep | awk \'{print $2}\' | xargs kill -9 > /dev/null 2>/dev/null &');
	}
}

function update_stream_status($stream_id, $status, $stream_loop){
	global $db;

	if($stream_loop != 1){

		// UPDATE STREAM STATUS
		$set_stream_array = array($stream_id);
		$set_stream = $db->query('SELECT stream_status, stream_is_demand FROM cms_streams WHERE stream_id = ?', $set_stream_array);

		$stream_status = json_decode($set_stream[0]['stream_status'], true);
		if($set_stream[0]['stream_is_demand'] == 1){
			$stream_status[0][SERVER] = 2;
		} else {
			$stream_status[0][SERVER] = $status;
		}

		$update_stream_array = array('stream_status' => json_encode($stream_status), 'stream_id' => $stream_id);
		$update_stream = $db->query('UPDATE cms_streams SET stream_status = :stream_status WHERE stream_id = :stream_id', $update_stream_array);

	} else {
		$update_stream_array = array('stream_loop_from_status' => $status, 'stream_loop_to_status' => $status, 'stream_id' => $stream_id);
		$update_stream = $db->query('UPDATE cms_streams SET stream_loop_from_status = :stream_loop_from_status, stream_loop_to_status = :stream_loop_to_status WHERE stream_id = :stream_id', $update_stream_array);
	}
}

function update_stream_information($stream_id){

	set_time_limit(1);

	global $db;

	// GET FIRST ALL SEGMENTS OF STREAM
	$stream_segment = stream_segments($stream_id);

	if($stream_segment){

		// IF SEGMENTS FOUND GET THE LAST OF THEM
		$last_segment = DOCROOT.'streams/'.end($stream_segment);

		if(file_exists($last_segment)){

			// PROBE IT WITH FFPROBE
			$probe_command = DOCROOT.'bin/ffprobe -i '.$last_segment.' -v quiet -print_format json -show_format -show_streams &';
			$probe_result = shell_exec($probe_command);

			// GET JSON DATA OF PROBE
			$stream_data = json_decode($probe_result, true);
			if (is_array($stream_data) && count($stream_data) > 0){

				$check_sys_array = array($stream_id, SERVER);
				$check_sys = $db->query('SELECT stream_sys_id, stream_data FROM cms_stream_sys WHERE stream_id = ? AND server_id = ?', $check_sys_array);

				$stream_data_decoded = json_decode($check_sys[0]['stream_data'], true);
				$speed = '0x';
				if($stream_data_decoded['kbps'] != 0){
					$speed = sprintf('%0.2f', (int)($stream_data['format']['size'] / $stream_data['format']['duration'] / 1024) / $stream_data_decoded['kbps']).'x';
				}

				$data_array = array('width' => $stream_data['streams'][0]['width'], 'height' => $stream_data['streams'][0]['height'], 'vcodec' => $stream_data['streams'][0]['codec_name'], 'acodec' => $stream_data['streams'][1]['codec_name'], 'framerate' => $stream_data['streams'][0]['avg_frame_rate'], 'kbps' => (int)($stream_data['format']['size'] / $stream_data['format']['duration'] / 1024), 'speed' => $speed);
				if(count($check_sys_array) > 0){
					$update_sys_array = array('stream_data' => json_encode($data_array), 'stream_bitrate' => $stream_data['format']['bit_rate'] / 1024, 'stream_id' => $stream_id, 'server_id' => SERVER);
					$update_sys = $db->query('UPDATE cms_stream_sys SET stream_data = :stream_data, stream_bitrate = :stream_bitrate WHERE stream_id = :stream_id AND server_id = :server_id', $update_sys_array);
				}

				return true;
			}

		} else {
			return false;
		}

	} else {
		return false;
	}
}

// FINGERPRINT FUNCTIONS
function check_fingerprint($line_id, $server_id){
	global $db;

	// IF LICENSE HAS FINGERPRINT THEN CHECK IF STREAM FINGERPRINT HAS 1
	$set_line_array = array($line_id, 0, time());
	$set_line = $db->query('SELECT line_id, line_fingerprint FROM cms_lines WHERE line_id = ? AND line_fingerprint_start_time != ? AND line_fingerprint_start_time < ?', $set_line_array);
	if(count($set_line) > 0){
		$fingerprint_array = json_decode($set_line[0]['line_fingerprint'], true);
		if($fingerprint_array[0][$server_id] == 1){
			return true;
		} else {
			return false;
		}
	} else {
		return false;
	}
}

function start_fingerprint($search_segment_id, $read_segment_id, $stream_id, $line_id, $line_user){
	global $db;

	$delete_fingerprint = '/bin/rm -r '.DOCROOT.'streams/'.$stream_id.'_fingerprint_'.$line_id.'.ts';
	shell_exec($delete_fingerprint);

	$set_line_array = array($line_id, $stream_id);
	$set_line = $db->query('SELECT line_id, line_expire_date, line_fingerprint_typ, line_fingerprint_custom_text FROM cms_lines WHERE line_id = ? AND line_fingerprint_stream_id = ?', $set_line_array);
	if(count($set_line) > 0){

		$read_segment = $stream_id.'_'.$read_segment_id.'.ts';
		$write_segment = $stream_id.'_fingerprint_'.$line_id.'.ts';

		while(!file_exists(DOCROOT.'streams/'.$stream_id.'_'.$search_segment_id.'.ts')){
			usleep(1000);
		}

		// GET STREAM BITRATE OF SEGMENTS
		$set_stream_sys_array = array($stream_id, SERVER);
		$set_stream_sys = $db->query('SELECT stream_bitrate, stream_data from cms_stream_sys WHERE stream_id = ? AND server_id = ?', $set_stream_sys_array);
		$stream_data = json_decode($set_stream_sys[0]['stream_data'], true);

		$xcoordinate = (int)(150 * ($stream_data['width']/1920));
		$ycoordinate = $stream_data['height'] - (int)(250 * ($stream_data['height']/1080)) - rand(0, 20);
		$fontsize = (int) (36 * ($stream_data['width']/1920));

		// FINGERPRINT USERNAME
		if($set_line[0]['line_fingerprint_typ'] == 0){
			$start_command = DOCROOT.'bin/ffmpeg -y -i '.DOCROOT.'streams/'.$read_segment.' -vf drawbox="x=0:y='.($ycoordinate-25).':w=in_w:h=80:color=black@0.5:t=max",drawtext="DejaVu Sans:fontcolor=white:fontsize='.$fontsize.':text=USER '.$line_user.':x=(w-tw)/2:y='.$ycoordinate.'" -c:v libx264 -b:v '.$set_stream_sys[0]['stream_bitrate'].'k -preset ultrafast -c:a copy -muxdelay 0 '.DOCROOT.'streams/'.$write_segment;
			shell_exec($start_command);
		}

		// SEND FINGERPRINT EXPIRE DATE
		if($set_line[0]['line_fingerprint_typ'] == 1){
			if($set_line[0]['line_expire_date'] != NULL || $set_line[0]['line_expire_date'] != '0' || $set_line[0]['line_expire_date'] != ''){
				$days_left = date('Y-m-d', $set_line[0]['line_expire_date']);

				$show_text = 'You line will be expiring in | '.$days_left;
			} else {
				$show_text = 'You line will be expiring | never';
			}

			$start_command = DOCROOT.'bin/ffmpeg -y -i '.DOCROOT.'streams/'.$read_segment.' -vf drawbox="x=0:y='.($ycoordinate-25).':w=in_w:h=80:color=black@0.5:t=max",drawtext="DejaVu Sans:fontcolor=white:fontsize='.$fontsize.':text='.$show_text.':x=(w-tw)/2:y='.$ycoordinate.'" -c:v libx264 -b:v '.$set_stream_sys[0]['stream_bitrate'].'k -preset ultrafast -c:a copy -muxdelay 0 '.DOCROOT.'streams/'.$write_segment;
			shell_exec($start_command);
		}

		// SEND FINDERPRINT CUSTOM TEXT
		if($set_line[0]['line_fingerprint_typ'] == 2){
			$show_text = $set_line[0]['line_fingerprint_custom_text'];
			$start_command = DOCROOT.'bin/ffmpeg -y -i '.DOCROOT.'streams/'.$read_segment.' -vf drawbox="x=0:y='.($ycoordinate-25).':w=in_w:h=80:color=black@0.5:t=max",drawtext="DejaVu Sans:fontcolor=white:fontsize='.$fontsize.':text='.$show_text.':x=(w-tw)/2:y='.$ycoordinate.'" -c:v libx264 -b:v '.$set_stream_sys[0]['stream_bitrate'].'k -preset ultrafast -c:a copy -muxdelay 0 '.DOCROOT.'streams/'.$write_segment;

			shell_exec($start_command);
		}
	}
}

function stop_fingerprint($stream_id, $line_id, $server_id){
	global $db;

	$update_line_array = array('line_fingerprint_typ' => NULL, 'line_fingerprint_custom_text' => NULL, 'line_fingerprint_start_time' => NULL, 'line_fingerprint' => NULL, 'line_fingerprint_target' => NULL, 'line_fingerprint_stream_id' => NULL, 'line_id' => $line_id);
	$update_line = $db->query('
		UPDATE cms_lines SET
			line_fingerprint_typ = :line_fingerprint_typ,
			line_fingerprint_custom_text = :line_fingerprint_custom_text,
			line_fingerprint_start_time = :line_fingerprint_start_time,
			line_fingerprint = :line_fingerprint,
			line_fingerprint_target = :line_fingerprint_target,
			line_fingerprint_stream_id = :line_fingerprint_stream_id
		WHERE line_id = :line_id', $update_line_array
	);
}

// EPISODE FUNCTIONS
function start_episode_download($episode_id){
	global $db;

	// GET FIRST EPISODE
	$set_episode_array = array($episode_id);
	$set_episode = $db->query('SELECT * FROM cms_serie_episodes WHERE episode_id = ?', $set_episode_array);

	// AND CHECK IF IS EXISTS ON FOLDER IF YES DELETE IT FIRST
	if(file_exists(DOCROOT.'/series/serie_finished/'.$episode_id.'.'.$set_episode[0]['serie_episode_extension'])){
		$delete_command = 'rm -rf '.DOCROOT.'series/serie_finished/'.$episode_id.'.'.$set_episode[0]['serie_episode_extension'];
		shell_exec($delete_command);
	}

	$download = false;

	// CHECK IF EPISODE REMOTE SOURCE OR LOCAL SOURCE
	if($set_episode[0]['serie_episode_remote_source'] != ''){

		// CHECK IF SOURCE IS FROM YOUTUBE
		if(parse_url($set_episode[0]['serie_episode_remote_source'], PHP_URL_HOST) == 'youtube.com' || parse_url($set_episode[0]['serie_episode_remote_source'], PHP_URL_HOST) == 'https://www.youtube.com' || parse_url($set_episode[0]['serie_episode_remote_source'], PHP_URL_HOST) == 'www.youtube.com' || parse_url($set_episode[0]['serie_episode_remote_source'], PHP_URL_HOST) == 'mobil.youtube.com' || parse_url($set_episode[0]['serie_episode_remote_source'], PHP_URL_HOST) == 'youtu.be'){
			// EPISODE DOWNLOAD FROM YOUTUBE
			$start_command = '/home/xapicode/iptv_xapicode/bin/youtube-dl -o "/home/xapicode/iptv_xapicode/series/serie_finished/'.$episode_id.'.'.$set_episode[0]['serie_episode_extension'].'" '.$set_episode[0]['serie_episode_remote_source'];
		} else {
			// MOVIE DOWNLOAD FROM REMOTE
			$start_command = 'wget '.$set_episode[0]['serie_episode_remote_source']. ' -O ' .DOCROOT.'series/serie_finished/'.$episode_id.'.'.$set_episode[0]['serie_episode_extension'];
		}

		$episode_download_pid = shell_exec($start_command . ' > /dev/null 2>&1 & echo $!');
		if($episode_download_pid != ''){
			$update_episode_array = array('episode_downloading_pid' => $episode_download_pid, 'episode_id' => $episode_id);
			$update_episode = $db->query('
				UPDATE cms_serie_episodes SET
					episode_downloading_pid = :episode_downloading_pid
				WHERE episode_id = :episode_id', $update_episode_array
			);

			$download = true;

		} else {
			$download = false;
		}

	} else {

		// MOVIE MOVE TO FINISHED FOLDER
		$start_command = 'mv '.$set_episode[0]['serie_episode_local_source'].' '.DOCROOT.'series/serie_finished/'.$episode_id.'.'.$set_episode[0]['serie_episode_extension'];
		$episode_download_pid = shell_exec($start_command . ' > /dev/null 2>&1 & echo $!');

		if($episode_download_pid != ''){
			$update_episode_array = array('serie_episode_downloading_pid' => $episode_download_pid, 'episode_id' => $episode_id);
			$update_episode = $db->query('
				UPDATE cms_movies SET
					serie_episode_downloading_pid = :serie_episode_downloading_pid
				WHERE episode_id = :episode_id', $update_episode_array
			);

			$download = true;

		} else {
			$download = false;
		}
	}

	return $download;
}

function start_episode_transcode($episode_id){
	global $db;

	// GET FIRST MOVIE
	$set_episode_array = array($episode_id);
	$set_episode = $db->query('SELECT * FROM cms_movies WHERE movie_id = ?', $set_episode_array);

	$episode_to_transcode = DOCROOT.'series/serie_finished/'.$episode_id.'.'.$set_episode[0]['serie_episode_extension'];

	// SET THE PROBE COMMAND
	$probe_command = '/usr/bin/timeout 15s '.DOCROOT.'bin/ffprobe -i "'.$episode_to_transcode.'" -v quiet -print_format json -show_streams 2>&1';
	$probe_result = shell_exec($probe_command);
	$probe_array = json_decode($probe_result, true);

	// CHECK STREAM IF IS WORK
	$transcoding = set_transcoding_profile($set_episode[0]['episode_transcoding_id'], $probe_array['streams'][0]['width'], $probe_array['streams'][0]['height']);
	$start_command = DOCROOT.'bin/ffmpeg -y -i '.$episode_to_transcode.' '.$transcoding.' '.DOCROOT.'series/serie_finished/transcoding_'.$episode_id.'.'.$set_episode[0]['serie_episode_extension'];

	// GET THE PID OF STREAM
	$episode_transcode_pid = shell_exec($start_command . ' > /dev/null 2>&1 & echo $!');

	$transcoding = false;

	// IF PID IS GIVEN UPDATE DB
	if($episode_transcode_pid != ''){
		$update_episode_array = array('episode_transcoding_pid' => $episode_transcode_pid, 'episode_id' => $episode_id);
		$update_episode = $db->query('
			UPDATE cms_episode SET
				episode_transcode_pid = :episode_transcode_pid
			WHERE episode_id = :episode_id', $update_episode_array
		);

		$transcoding = true;

	} else {
		$transcoding = false;
	}

	return $transcoding;
}

function check_allowed_bouquet_series($line_user, $serie_id){
	global $db;

	$series_array = array();

	// GET FIRST ALL BOUQUETS FROM DATABASE OF USER
	$set_line_array = array($line_user);
	$set_line = $db->query('SELECT line_bouquet_id FROM cms_lines WHERE line_user = ?', $set_line_array);

	// MAKE ALL BOUQUETS AS ARRAY
	$line_bouquets = json_decode($set_line[0]['line_bouquet_id'], true);
	foreach($line_bouquets as $bouquet_id){
		$set_bouquet_array = array($bouquet_id);
		$set_bouquet = $db->query('SELECT * FROM cms_bouquets WHERE bouquet_id = ?', $set_bouquet_array);
		if(count($set_bouquet) > 0){
			foreach($set_bouquet as $get_bouquet){
				foreach(array_filter($get_bouquet) as $key => $value){
					if($key == 'bouquet_series'){
						$series = json_decode($value, true);
						foreach($series as $serie_value){
							$series_array[] = $serie_value;
						}
					}
				}

			}
		}
	}

	if(in_array($serie_id, $series_array)){
		return true;
	} else {
		return false;
	}
}

// MOVIE FUNCTIONS
function start_movie_download($movie_id){
	global $db;

	// GET FIRST MOVIE
	$set_movie_array = array($movie_id);
	$set_movie = $db->query('SELECT * FROM cms_movies WHERE movie_id = ?', $set_movie_array);

	// AND CHECK IF IS EXISTS ON FOLDER IF YES DELETE IT FIRST
	if(file_exists(DOCROOT.'movie_finished/'.$movie_id.'.'.$set_movie[0]['movie_extension'])){
		$delete_command = 'rm -rf '.DOCROOT.'movie_finished/'.$movie_id.'.'.$set_movie[0]['movie_extension'];
		shell_exec($delete_command);
	}

	$download = false;

	// CHECK IF MOVIE REMOTE SOURCE OR LOCAL SOURCE
	if($set_movie[0]['movie_remote_source'] != ''){

		// MOVIE DOWNLOAD FROM REMOTE
		$start_command = 'wget '.$set_movie[0]['movie_remote_source']. ' -O ' .DOCROOT.'movies/movie_finished/'.$movie_id.'.'.$set_movie[0]['movie_extension'];
		$movie_download_pid = shell_exec($start_command . ' > /dev/null 2>&1 & echo $!');

		if($movie_download_pid != ''){
			$update_movie_array = array('movie_downloading_pid' => $movie_download_pid, 'movie_id' => $movie_id);
			$update_movie = $db->query('
				UPDATE cms_movies SET
					movie_downloading_pid = :movie_downloading_pid
				WHERE movie_id = :movie_id', $update_movie_array
			);

			$download = true;

		} else {
			$download = false;
		}

	} else {

		// MOVIE MOVE TO FINISHED FOLDER
		$start_command = 'mv '.$set_movie[0]['movie_local_source'].' '.DOCROOT.'movies/movie_finished/'.$movie_id.'.'.$set_movie[0]['movie_extension'];
		$movie_download_pid = shell_exec($start_command . ' > /dev/null 2>&1 & echo $!');

		if($movie_download_pid != ''){
			$update_movie_array = array('movie_downloading_pid' => $movie_download_pid, 'movie_id' => $movie_id);
			$update_movie = $db->query('
				UPDATE cms_movies SET
					movie_downloading_pid = :movie_downloading_pid
				WHERE movie_id = :movie_id', $update_movie_array
			);

			$download = true;

		} else {
			$download = false;
		}
	}

	return $download;
}

function start_movie_transcode($movie_id){
	global $db;

	// GET FIRST MOVIE
	$set_movie_array = array($movie_id);
	$set_movie = $db->query('SELECT * FROM cms_movies WHERE movie_id = ?', $set_movie_array);

	$movie_to_transcode = DOCROOT.'movies/movie_finished/'.$movie_id.'.'.$set_movie[0]['movie_extension'];

	// SET THE PROBE COMMAND
	$probe_command = '/usr/bin/timeout 15s '.DOCROOT.'bin/ffprobe -i "'.$movie_to_transcode.'" -v quiet -print_format json -show_streams 2>&1';
	$probe_result = shell_exec($probe_command);
	$probe_array = json_decode($probe_result, true);

	// CHECK STREAM IF IS WORK
	$transcoding = set_transcoding_profile($set_movie[0]['movie_transcode_id'], $probe_array['streams'][0]['width'], $probe_array['streams'][0]['height']);
	$start_command = DOCROOT.'bin/ffmpeg -y -i '.$movie_to_transcode.' '.$transcoding.' '.DOCROOT.'movies/movie_finished/transcoding_'.$movie_id.'.'.$set_movie[0]['movie_extension'];

	// GET THE PID OF STREAM
	$movie_transcode_pid = shell_exec($start_command . ' > /dev/null 2>&1 & echo $!');

	$transcoding = false;

	// IF PID IS GIVEN UPDATE DB
	if($movie_transcode_pid != ''){
		$update_movie_array = array('movie_transcoding_pid' => $movie_transcode_pid, 'movie_id' => $movie_id);
		$update_movie = $db->query('
			UPDATE cms_movies SET
				movie_transcoding_pid = :movie_transcoding_pid
			WHERE movie_id = :movie_id', $update_movie_array
		);

		$transcoding = true;

	} else {
		$transcoding = false;
	}

	return $transcoding;
}

function check_allowed_bouquet_movie($line_user, $movie_id){
	global $db;

	$movies_array = array();

	// GET FIRST ALL BOUQUETS FROM DATABASE OF USER
	$set_line_array = array($line_user);
	$set_line = $db->query('SELECT line_bouquet_id FROM cms_lines WHERE line_user = ?', $set_line_array);

	// MAKE ALL BOUQUETS AS ARRAY
	$line_bouquets = json_decode($set_line[0]['line_bouquet_id'], true);
	foreach($line_bouquets as $bouquet_id){
		$set_bouquet_array = array($bouquet_id);
		$set_bouquet = $db->query('SELECT * FROM cms_bouquets WHERE bouquet_id = ?', $set_bouquet_array);
		if(count($set_bouquet) > 0){
			foreach($set_bouquet as $get_bouquet){
				foreach(array_filter($get_bouquet) as $key => $value){
					if($key == 'bouquet_movies'){
						$movies = json_decode($value, true);
						foreach($movies as $movies_value){
							$movies_array[] = $movies_value;
						}
					}
				}

			}
		}
	}

	if(in_array($movie_id, $movies_array)){
		return true;
	} else {
		return false;
	}

}

// CLIENT LIVE TS FUNCTIONS
function check_allowed_ip($line_user){
	global $db;

	// GET FIRST ALL IPS FROM DATABASE OF USER
	$set_line_array = array(get_line_id_by_name($line_user));
	$set_line = $db->query('SELECT line_allowed_ip FROM cms_lines WHERE line_id = ?', $set_line_array);

	// IF LINE ALLOWED IPS NOT EMPTY
	if($set_line[0]['line_allowed_ip'] != ''){

		// MAKE IPS AS ARRAY
		$allowed_ips = json_decode($set_line[0]['line_allowed_ip'], true);
		foreach($allowed_ips as $ip){

			// IF IP IS REMOTE IP THEN ALLOW IT OTHERWISE DONT ALLOW IT
			if($ip == $_SERVER['REMOTE_ADDR']){
				$return = true;
			} else {
				$return = false;
			}
		}
	} else {
		$return = true;
	}

	return $return;
}

function check_allowed_ua($line_user, $user_agent){
	global $db;

	// GET FIRST ALL USER AGENTS FROM DATABASE OF USER
	$set_line_array = array(get_line_id_by_name($line_user));
	$set_line = $db->query('SELECT line_allowed_ua FROM cms_lines WHERE line_id = ?', $set_line_array);

	// IF USER AGENT NOT EMPTY AND ALLOWED UA NOT EMPTY
	if($user_agent != ''){
		if($set_line[0]['line_allowed_ua'] != ''){

			// MAKE USERAGENTS AS ARRAY
			$allowed_ua = json_decode($set_line[0]['line_allowed_ua'], true);
            foreach($allowed_ua as $ua){

				// IF PREG MATCH USER AGENT AS HTTP USER AGENT ALLOW IT OTHERWISE DONT ALLOW IT
				if (preg_match('#'.strtolower($ua).'#', strtolower($_SERVER['HTTP_USER_AGENT']), $matches)) {
                    if($matches != ''){
                        $return = true;
                    } else {
                        $return = false;
                    }
                } else {
                    $return = false;
                }
            }
        } else {
			$return = true;
        }
    }

	return $return;
}

function check_allowed_bouquet_stream($line_user, $stream_id){
	global $db;

	$streams_array = array();

	// GET FIRST ALL BOUQUETS FROM DATABASE OF USER
	$set_line_array = array($line_user);
	$set_line = $db->query('SELECT line_bouquet_id FROM cms_lines WHERE line_user = ?', $set_line_array);

	// MAKE ALL BOUQUETS AS ARRAY
	$line_bouquets = json_decode($set_line[0]['line_bouquet_id'], true);

	foreach($line_bouquets as $bouquet_id){
		$set_bouquet_array = array($bouquet_id);
		$set_bouquet = $db->query('SELECT bouquet_streams FROM cms_bouquets WHERE bouquet_id = ?', $set_bouquet_array);
		foreach($set_bouquet as $get_bouquet){
			$bouquet_streams_decode = json_decode($set_bouquet[0]['bouquet_streams'], true);

			foreach(array_filter($bouquet_streams_decode) as $key => $value){
				$streams_array[] = $value;
			}
		}
	}

	if(in_array($stream_id, $streams_array)){
		return true;
	} else {
		return false;
	}
}

function check_flood_dedection(){
	global $db;

	// CHECK IF ON SETTINGS IS FLOOD PROTECTION SET TO 1
	$set_flood_array = array(1);
	$set_flood = $db->query('SELECT setting_flood_protection FROM cms_settings WHERE setting_flood_protection = ?', $set_flood_array);

	// IF IS SET TO 1 THEN CHECK FLOOD OTHERWISE DONT CHECK IT
	if(count($set_flood) > 0){
		return true;
	} else {
		return false;
	}
}

function check_line_user($line_user){
	global $db;

	// GET LINE USER FROM LINES
	$set_line_count_array = array($line_user);
	$set_line_count = $db->query('SELECT line_id FROM cms_lines WHERE line_user = ?', $set_line_count_array);

	// IF LINE FOUND RETURN TRUE OTHERWISE RETURN FALSE
	if(count($set_line_count) > 0){
		return true;
	} else {
		return false;
	}
}

function check_line_is_expired($line_user){
	global $db;

	// GET LINE USER FROM LINES
	$set_line_count_array = array($line_user, 2);
	$set_line_count = $db->query('SELECT line_id FROM cms_lines WHERE line_user = ? AND line_status = ?', $set_line_count_array);

	// IF LINE FOUND RETURN TRUE OTHERWISE RETURN FALSE
	if(count($set_line_count) > 0){
		return true;
	} else {
		return false;
	}
}

function check_security_token($token){
	global $db;

	// GET TOKEN FROM SETTINGS
	$set_token_array = array($token);
	$set_security_token = $db->query('SELECT setting_id FROM cms_settings WHERE setting_security_token = ?', $set_token_array);

	if(count($set_security_token) > 0){
		return true;
	} else {
		return false;
	}
}

function insert_into_loglist($remote_ip = '', $user_agent = '', $query_string = ''){
	global $db;

	// INSERT TO LOG DB
	$insert_log_array = array('log_ip' => $remote_ip, 'log_ua' => $user_agent, 'log_query' => $query_string, 'log_time' => time(), 'log_server' => SERVER, 'log_proxy' => 0);
	$insert_log = $db->query('INSERT INTO cms_log (log_ip, log_ua, log_query, log_time, log_server, log_proxy) VALUES (:log_ip, :log_ua, :log_query, :log_time, :log_server, :log_proxy)', $insert_log_array);
}

function insert_into_bannlist($line_user = '', $remote_ip = '', $bann_title, $bann_note){
	global $db;

	// INSERT INTO THE BANN LIST
	$insert_bann_array = array('bann_time' => time(), 'bann_ip' => $remote_ip, 'bann_line_id' => ($line_user != 0 ? get_line_id_by_name($line_user) : Null), 'bann_server' => SERVER, 'bann_note' => '<strong>'.$bann_title.': </strong> '.$bann_note);
	$insert_bann_db = $db->query('INSERT INTO cms_bannlist (bann_time, bann_ip, bann_line_id, bann_server, bann_note) VALUES (:bann_time, :bann_ip, :bann_line_id, :bann_server, :bann_note)', $insert_bann_array);
}

function get_line_id_by_name($line_user){
	global $db;

	// GET LINE FROM LINES
	$set_line_array = array($line_user);
	$set_line = $db->query('SELECT line_id FROM cms_lines WHERE line_user = ?', $set_line_array);

	// RETURN LINE ID
	return $set_line[0]['line_id'];
}

function get_broadcast_port($server_id){
	global $db;

	$set_server = array($server_id);
	$set_server = $db->query('SELECT server_broadcast_port FROM cms_server WHERE server_id = ?', $set_server);
	return $set_server[0]['server_broadcast_port'];
}

function iptables_add($remote_ip){
	shell_exec('sudo /sbin/iptables -A INPUT -s '.$remote_ip.' -j DROP');
}

function stream_segments($stream_id) {
	if(file_exists(DOCROOT.'streams/'.$stream_id.'_.m3u8')){
		$stream_source = file_get_contents(DOCROOT.'streams/'.$stream_id.'_.m3u8');
		$segment_explode = explode(',', $stream_source);
		$segment_array = array();

		foreach(preg_split("/((\r?\n)|(\r\n?))/", $stream_source) as $line){
			if(strpos($line,".ts") !== FALSE) {
				preg_match('%([^`]*?)\?%',$line,$line_tmp);
				array_push($segment_array, $line);
			}
		}

		return $segment_array;

	} else {
		return false;
	}
}

function segment_playlist($playlist, $prebuffer = 0){
	if (file_exists($playlist)) {
		$source = file_get_contents($playlist);
		if (preg_match_all("/(.*?).ts/", $source, $matches)) {
			if (0 < $prebuffer) {
				$total_segs = intval($prebuffer / 10);
				return array_slice($matches[0], -$total_segs);
			}
			return $matches[0];
		}
	}
	return false;
}

function segment_buffer(){
	global $db;

	// GET PREBUFFER IN SECONDS FROM SETTINGS DB
	$set_settings = $db->query('SELECT setting_prebuffer_sec, setting_buffersize_reading FROM cms_settings');
	return $set_settings[0]['setting_prebuffer_sec'];
}

// CLIENT LIVE M3U (HLS) FUNCTIONS
function check_line_connection_hls($line_id, $stream_id){
	global $db;

	// GET FIRST ALL CONNECTIONS OF LINE
	$set_activity_array = array($line_id, 'hls');
	$set_activity = $db->query('SELECT stream_activity_id, stream_activity_stream_id FROM cms_stream_activity WHERE stream_activity_line_id = ? AND stream_activity_typ = ?', $set_activity_array);

	if(count($set_activity) > 0){

		if($set_activity[0]['stream_activity_stream_id'] == $stream_id){
			return true;
		} else {
			exit('unable to connection. reason: max allowed channels on hls is reached!');
		}
	} else {
		return true;
	}
}

// LOAD GET DATA FUNCTIONS
function show_all_series_on_bouquet(){
	global $db;

	$set_settings_array = array(1);
	$set_settings = $db->query('SELECT setting_id FROM cms_settings WHERE setting_show_all_episodes = ?', $set_settings_array);

	if(count($set_settings) > 0){
		return true;
	} else {
		return false;
	}
}

function get_movie_by_id($movie_id){
	global $db;

	// GET MOVIE EXTENSION FROM CMS MOVIES
	$set_movie_array = array($movie_id);
	$set_movie = $db->query('SELECT movie_name FROM cms_movies WHERE movie_id = ?', $set_movie_array);

	return $set_movie[0]['movie_name'];
}

function get_movie_extension($movie_id){
	global $db;

	// GET MOVIE EXTENSION FROM CMS MOVIES
	$set_movie_array = array($movie_id);
	$set_movie = $db->query('SELECT movie_extension FROM cms_movies WHERE movie_id = ?', $set_movie_array);

	return $set_movie[0]['movie_extension'];
}

function get_episode_extension($episode_id){
	global $db;

	// GET MOVIE EXTENSION FROM CMS MOVIES
	$set_episode_array = array($episode_id);
	$set_episode = $db->query('SELECT serie_episode_extension FROM cms_serie_episodes WHERE episode_id = ?', $set_episode_array);

	return $set_episode[0]['serie_episode_extension'];
}

function season_number($season_number){
	if($season_number < 10){
		$season_number = '0'.$season_number;
	} else {
		$season_number = $season_number;
	}

	return $season_number;
}

function episode_number($episode_number){
	if($episode_number < 10){
		$episode_number = '0'.$episode_number;
	} else {
		$episode_number = $episode_number;
	}

	return $episode_number;
}

// MAG FUNCTIONS
function get_from_cookie($cookie, $type) {
    if (!empty($cookie)) {
        $explode = explode(";", $cookie);

        foreach ($explode as $data) {
            $data = explode("=", $data);
            $output[trim($data[0]) ] = trim($data[1]);
        }

        switch ($type) {
            case "mac":
                if (array_key_exists("mac", $output)) {
                    return base64_encode(strtoupper(urldecode($output["mac"])));
                }
        }
    }

    return false;
}

function prepair_mag_cols($array) {
    $output = array();

    foreach ($array as $key => $value) {
        if (($key == "mac") || ($key == "ver") || ($key == "hw_version")) {
            $output[$key] = base64_decode($value);
        }

        $output[$key] = $value;
    }

    unset($output["fav_channels"]);
    return $output;
}

function portal_auth($sn, $mac, $ver, $stb_type, $image_version, $device_id, $device_id2, $hw_version, $req_ip) {
	global $db;

	$set_mag_array = array(trim($mac));
    $set_mag = $db->query('SELECT * FROM mag_devices WHERE mac = ?', $set_mag_array);

	if (count($set_mag) > 0){

		$set_line_array = array($set_mag[0]['line_id']);
		$set_line = $db->query('SELECT * FROM cms_lines WHERE line_id = ?', $set_line_array);

		$total_info = array_merge($set_mag[0], $set_line[0]);

		$mag_update_array = array('ip' => $req_ip, 'mag_id' => $total_info['mag_id']);
		$mag_update = $db->query('UPDATE mag_devices SET ip = :ip WHERE mag_id = :mag_id', $mag_update_array);

		if (!empty($stb_type)) {
			$stp_update_array = array('stb_type' => $stb_type, 'mag_id' => $total_info['mag_id']);
			$stp_update = $db->query('UPDATE mag_devices SET stb_type = :stb_type WHERE mag_id = :mag_id', $stp_update_array);

			$total_info["stb_type"] = $stb_type;
		}

		if (!empty($sn)) {
			$sn_update_array = array('sn' => $sn, 'mag_id' => $total_info['mag_id']);
			$sn_update = $db->query('UPDATE mag_devices SET sn = :sn WHERE mag_id = :mag_id', $sn_update_array);

			$total_info["sn"] = $sn;
		}

		if (!empty($ver)) {
			$ver_update_array = array('ver' => $ver, 'mag_id' => $total_info['mag_id']);
			$ver_update = $db->query('UPDATE mag_devices SET ver = :ver WHERE mag_id = :mag_id', $ver_update_array);

			$total_info["ver"] = $ver;
		}

		if (!empty($image_version)) {
			$image_update_array = array('image_version' => $image_version, 'mag_id' => $total_info['mag_id']);
			$image_update = $db->query('UPDATE mag_devices SET image_version = :image_version WHERE mag_id = :mag_id', $image_update_array);

			$total_info["image_version"] = $image_version;
		}

		if (!empty($device_id)) {
			$device_update_array = array('device_id' => $device_id, 'mag_id' => $total_info['mag_id']);
			$device_update = $db->query('UPDATE mag_devices SET device_id = :device_id WHERE mag_id = :mag_id', $device_update_array);

			$total_info["device_id"] = $device_id;
		}

		if (!empty($device_id2)) {
			$device2_update_array = array('device_id2' => $device_id2, 'mag_id' => $total_info['mag_id']);
			$device2_update = $db->query('UPDATE mag_devices SET device_id2 = :device_id2 WHERE mag_id = :mag_id', $device2_update_array);

			$total_info["device_id2"] = $device_id2;
		}

		if (!empty($hw_version)) {
			$hwversion_update_array = array('hw_version' => $hw_version, 'mag_id' => $total_info['mag_id']);
			$hwversion_update = $db->query('UPDATE mag_devices SET hw_version = :hw_version WHERE mag_id = :mag_id', $hwversion_update_array);

			$total_info["hw_version"] = $hw_version;
		}

		return array(
			"total_info" => prepair_mag_cols($total_info) ,
			"mag_info_db" => prepair_mag_cols($set_mag[0]) ,
			"fav_channels" => empty($set_mag[0]["fav_channels"]) ? array() : json_decode($set_mag[0]["fav_channels"], true)
		);

		if (($total_info["sn"] == $sn) && ($total_info["hw_version"] == $hw_version) && ($total_info["device_id2"] == $device_id2) && ($total_info["device_id"] == $device_id) && ($total_info["image_version"] == $image_version) && ($total_info["ver"] == $ver)) {
			return array(
				"total_info" => prepair_mag_cols($total_info) ,
				"mag_info_db" => prepair_mag_cols($set_mag[0]) ,
				"fav_channels" => empty($set_mag[0]["fav_channels"]) ? array() : json_decode($set_mag[0]["fav_channels"], true)
			);
		}

	} else {
		return false;
	}
}
?>
