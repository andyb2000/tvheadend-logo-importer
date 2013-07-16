#!/usr/bin/php5

<?php
// php5 UK channel icon importer for TVHeadend by Andy Brown http://github.com/andyb2000
// ---------------------------------------------------------------------

echo "----------- TV Headend XML-lineup import ------------\n";
echo "------------ http://github.com/andyb2000 ------------\n";
echo "-----------------------------------------------------\n";

if (@$argv[1]) {
	$tvh_user_home=$argv[1];
};
auto_config();

// Set channel_permit_update to 1 to permit it to update all existing icons
// or 0 to only add icons where they were missing before
$channel_permit_update=0;

// New function from xmltv.org
//  channel icons in a NAME|icon format
// e.g. BBC One|http://www.lyngsat-logo.com/logo/tv/bb/bbc_one.jpg

$load_icons = fopen("http://supplement.xmltv.org/tv_grab_uk_rt/channel_icons", "r");
if ($load_icons) {
    while (($buffer = fgets($load_icons, 4096)) !== false) {
	@list($ch_name,$ch_icon)=explode("|",$buffer);
		$ch_name=rtrim($ch_name);
		$ch_icon=rtrim($ch_icon);
		// compare ch_name against what we find in the users channels folder
		unset($chan_file);
		$chan_file=chan_search($ch_name);
                if ($chan_file) {
                        chan_update($chan_file,$ch_icon);
                };

    }
    if (!feof($load_icons)) {
        echo "Error: unexpected fgets() fail\n";
    }
    fclose($load_icons);
};

// Not everything is caught by the above, so we'll also parse the xml
echo "\n\nRunning second XMLTV retrieval scripts\n";
$lineup_list=simplexml_load_file("http://supplement.xmltv.org/tv_grab_uk_rt/lineups/lineups.xml");
$lineup_array=array();
$counter=0;
foreach ($lineup_list as $xml_entry) {
//	print_r($xml_entry);
	$lineup_array[$counter]=$xml_entry['id'];
	echo "$counter) ".$xml_entry['id']."\n";
	$counter++;
};
echo "Please enter which lineup you wish to use:\n";
$input_line=readline("Number: ");
rtrim($input_line);
if ($input_line) {
	$load_xml=simplexml_load_file("http://supplement.xmltv.org/tv_grab_uk_rt/lineups/".$lineup_array[$input_line].".xml");
	echo "Loading: http://supplement.xmltv.org/tv_grab_uk_rt/lineups/".$lineup_array[$input_line].".xml\n";
	foreach ($load_xml->{'xmltv-lineup'}->{'lineup-entry'} as $xml_entry) {
		$curr_preset=$xml_entry->preset;
	        $curr_availability=$xml_entry->availability;
	        $curr_station_name=$xml_entry->station->name;
        	$curr_station_logo=$xml_entry->station->logo['url'];
	        $curr_station_stb_preset=$xml_entry->station->{'stb-channel'}['stb-preset'];
		// echo "DEBUG: $curr_station_name and $curr_station_logo\n";
		unset($chan_file);
		$chan_file=chan_search($curr_station_name);
		if ($chan_file) {
			chan_update($chan_file,$curr_station_logo);
		};
	};
} else {
	echo "No input detected, skipping.";
	exit;
};



// Try to load config automagically
function auto_config() {
	global $tvh_user_home;

	exec("pgrep tvheadend", $output, $return);
	if ($return == 0) {
		echo "WARNING: TVHeadend detected as running. Changes are OK to go ahead with, but won't be reflected until you restart tvh\n\n";
		sleep(3);
	};

	$fail=1;
	if (!$tvh_user_home) {
	if (file_exists("/etc/default/tvheadend")) {
		// Debian or ubuntu
		$load_config=fopen("/etc/default/tvheadend","r");
		if ($load_config) {
			while (($config_buffer = fgets($load_config, 4096)) !== false) {
				if (strpos($config_buffer, "TVH_USER=") !== false) {
					$tvhc_user=explode("=",$config_buffer);
					$tvhc_user_value=trim(str_replace('"','',$tvhc_user[1]));

					
				};
			};
		};
	};
	if ($tvhc_user_value) {
		$userhome=`grep tvheadend /etc/passwd`;
		list($username,$na,$id,$gid,$na,$homedir,$shell)=explode(":",$userhome);
		$tvh_user_home=$homedir."/.hts/tvheadend/";
	};
	};
	echo "Found TVH config at $tvh_user_home\n";
	if (file_exists($tvh_user_home."channels/")) {
		echo "Found channels folder\n";
		$fail=0;
	} else {
		$fail=1;
		echo "ERROR: Cannot find channels folder. Cannot continue\n";
		echo "TVHeadend should have been ran and channels created before using this utility\n";
		echo "If your TVHeadend userhome isn't at $tvh_user_home then run this script again passing\n";
		echo "the TVHeadend path as first parameter.. Like this:\n";
		echo "  ./tvheadend-logo-importer.php5 /home/myuser/.hts/tvheadend/\n";
	};
};

function prettyPrint( $json )
{
    $result = '';
    $level = 0;
    $prev_char = '';
    $in_quotes = false;
    $ends_line_level = NULL;
    $json_length = strlen( $json );

    for( $i = 0; $i < $json_length; $i++ ) {
        $char = $json[$i];
        $new_line_level = NULL;
        $post = "";
        if( $ends_line_level !== NULL ) {
            $new_line_level = $ends_line_level;
            $ends_line_level = NULL;
        }
        if( $char === '"' && $prev_char != '\\' ) {
            $in_quotes = !$in_quotes;
        } else if( ! $in_quotes ) {
            switch( $char ) {
                case '}': case ']':
                    $level--;
                    $ends_line_level = NULL;
                    $new_line_level = $level;
                    break;

                case '{': case '[':
                    $level++;
                case ',':
                    $ends_line_level = $level;
                    break;

                case ':':
                    $post = " ";
                    break;

                case " ": case "\t": case "\n": case "\r":
                    $char = "";
                    $ends_line_level = $new_line_level;
                    $new_line_level = NULL;
                    break;
            }
        }
        if( $new_line_level !== NULL ) {
            $result .= "\n".str_repeat( "\t", $new_line_level );
        }
        $result .= $char.$post;
        $prev_char = $char;
    }

    return $result;
}

function chan_search($chan_name) {
	global $tvh_user_home,$channel_permit_update;
	$ch_directory=opendir($tvh_user_home."channels/");
		while (($dir_entry = readdir($ch_directory)) !== false) {
			// check entry of dir_entry
			// these are in JSON like structures
			$chk_chanfile=file_get_contents($tvh_user_home."channels/".$dir_entry);
			$chk_json=json_decode($chk_chanfile,true);
			$found=0;
			$icon_found=0;
			if ($chk_json) {
				//echo "COMPARE: '".$chk_json['name']."' and '".$chan_name."'\n";
				if (stripos($chk_json['name'], $chan_name) !== false) {
					// check for $channel_permit_update
					// If set to 0 then dont return the chan if it has an icon already
					if(@$chk_json['icon']) {
						if ($channel_permit_update == 1) {
							echo "FOUND: $chan_name in file $dir_entry\n";
							$found=1;
						} else {
							echo "SKIP: $chan_name already has icon set, and set to ignore\n";
							$found=0;
						};
					} else {
						echo "FOUND: $chan_name in file $dir_entry\n";
                                                $found=1;
					};
				};
				if ($chk_json['name'] == $chan_name) {
					if(@$chk_json['icon']) {
						if ($channel_permit_update == 1) {
							echo "FOUND EXACT MATCH: $chan_name in file $dir_entry\n";
							$found=1;
						} else {
							echo "SKIP EXACT MATCH: $chan_name already has icon set, and set to ignore\n";
							$found=0;
						};
					} else {
						echo "FOUND EXACT MATCH: $chan_name in file $dir_entry\n";
						$found=1;
					};
				};
			};
			if ($found) {
				return $dir_entry;
			};
		};
};
function chan_update($chan_filename,$ch_icon) {
	global $tvh_user_home;
	if ($ch_icon) {
	echo "Updating icon ($chan_filename) and ($ch_icon)\n";
        $chk_chanfile=file_get_contents($tvh_user_home."channels/".$chan_filename);
        $chk_json=json_decode($chk_chanfile,true);
//	$chk_json['icon']=$ch_icon;
	unset($chk_json['icon']);
	$chk_json['icon']="$ch_icon";
	$json_new=prettyPrint(json_encode($chk_json));
	$json_new=str_replace("\/","/",$json_new);
	$out_chan=fopen($tvh_user_home."channels/".$chan_filename, "w");
	fwrite($out_chan, $json_new);
	fclose($out_chan);
	} else {
		echo "WARN: ch_icon is blank/empty so I'm skipping updating\n";
	};
};

echo "Done\n";
?>
