<?PHP

$hosts_to_check    = array('localhost', 'not_checked');
$nagios_status_url = "http://localhost/cgi-bin/nagios3/status.cgi?host=all";
$nagios_user       = "nagiosadmin";
$nagios_pwd        = "";

//get status html
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $nagios_status_url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_USERPWD, "$nagios_user:$nagios_pwd");
curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
$html = curl_exec($ch);
curl_close($ch);

//filter links to checks and hosts from html
$regexp = "<a\s[^>]*href=(\"??)([^\" >]*?)\\1[^>]*>(.*)<\/a>"; 
preg_match_all("/$regexp/siU", $html, $matches);
$link = array();
$text = array();
for ($i = 0; $i < count($matches[2]); $i++) {
	if (strpos($matches[2][$i], 'extinfo') !== false && strpos($matches[3][$i], "<IMG") === false) {
		$link[] = $matches[2][$i];
		$text[] = $matches[3][$i];
	}
}
unset($matches);

//run through all service problems
$checked = array();
for ($i = 0; $i < count($link); $i++) {
	
	// a new host
	if (strpos($link[$i], "&service=") === false) {
		$host = $text[$i];
		$checked[$host] = 0;	// state ok initially
		continue;
	}
	
	// host is already erroring
	if ($checked[$host] > 1) continue;
	
	// check if this service is warn or err (shortest distance of Warn, Err from link position in text)
	$linkpos = strpos($html, $link[$i]);
	$warnpos = strpos($html, "WARNING", $linkpos); if ($warnpos === false) $warnpos = 10000000;
	$erropos = strpos($html, "ERROR"  , $linkpos); if ($erropos === false) $erropos = 10000000;
	$okpos   = strpos($html, "OK"  , $linkpos);    if ($okpos   === false) $okpos = 10000000;

	if ($okpos < $warnpos && $okpos < $erropos) {
		continue;
	}
	if ($warnpos < $erropos) {
		$checked[$host] = 1;	// stat warn
	}
	else {
		$checked[$host] = 2;	// state err
	}
}

// filter checked by hosts
$output = 0;
foreach ($hosts_to_check as $host) {
	$output = $output << 2;
	if (isset($checked[$host])) {
		$output += $checked[$host];
	}
	else {
		$output += 4;		// state unknown
	}
}
echo $output;

echo decbin($output);