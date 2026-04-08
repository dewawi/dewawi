<?php

define("_CRONJOB_",true);
require('index.php');

$log_file = BASE_PATH.'/logs/cron.log';
error_log(date("Y-m-d H:i:s")." Cronjob 'dewawi' gestartet.\n", 3, $log_file);

$config = parse_ini_file(BASE_PATH.'/configs/database.ini');

// DB Settings 
define('DB_SERVER', $config['resources.db.params.host']);
define('DB_USER', $config['resources.db.params.username']);
define('DB_PASSWORD', $config['resources.db.params.password']);
define('DB_NAME', $config['resources.db.params.dbname']);

require_once(BASE_PATH.'/library/DEEC/Campaign.php');
require_once(BASE_PATH.'/library/DEEC/User.php');
require_once(BASE_PATH.'/library/DEEC/Email.php');

$Campaign = new DEEC_Campaign(BASE_PATH, DB_SERVER, DB_USER, DB_PASSWORD, DB_NAME);
$User = new DEEC_User(BASE_PATH, DB_SERVER, DB_USER, DB_PASSWORD, DB_NAME);
$Email = new DEEC_Email(BASE_PATH, DB_SERVER, DB_USER, DB_PASSWORD, DB_NAME);

// Use Europe/Berlin by default
date_default_timezone_set('Europe/Berlin');
$now = new DateTime('now');

$campaigns = $Campaign->getCampaigns();

foreach ($campaigns as $campaign) {
    // basic guards
    if (empty($campaign['activated']) || !empty($campaign['deleted'])) {
        continue;
    }

    // timezone override per campaign (optional)
    if (!empty($campaign['timezone'])) {
        try { $tz = new DateTimeZone($campaign['timezone']); $now->setTimezone($tz); } catch (Exception $e) {}
    }

    // schedule window check
    if (!withinDateRange($now, $campaign['startdate'] ?? null, $campaign['duedate'] ?? null)) {
        continue;
    }
    if (!withinDailyWindow($now, $campaign['startwindow'] ?? null, $campaign['endwindow'] ?? null)) {
        continue;
    }

    // interval check
    $intervalMin = (int)($campaign['interval'] ?? 60);
    if ($intervalMin <= 0) $intervalMin = 60;

    $okByInterval = false;
    if (empty($campaign['lastsent'])) {
        $okByInterval = true;
    } else {
        $last = DateTime::createFromFormat('Y-m-d H:i:s', $campaign['lastsent']);
        if ($last) {
            $next = clone $last;
            $next->modify("+{$intervalMin} minutes");
            $okByInterval = ($now >= $next);
        } else {
            $okByInterval = true; // bad stored value → allow
        }
    }

    if (!$okByInterval) continue;

    // responsible must exist
    if (!empty($campaign['responsible'])) {
        $user = $User->getUser($campaign['responsible']);
        try {
            $Email->send($user, 0, 0, $campaign);
            $Campaign->touchLastSent($campaign['id'], $now); // update lastsent
        } catch (Exception $e) {
            error_log(date("Y-m-d H:i:s")." Campaign {$campaign['id']} send error: ".$e->getMessage()."\n", 3, $log_file);
        }
    }
}

// Test cron for campaigns
/*if(date("i") % 3 == 0) {
	//if(isBetween("03:00", "23:00", date("H:i"))) {
		foreach($campaigns as $campaign) {
			//echo $campaign['title'];
			if($campaign['activated'] && !$campaign['deleted']) {
				if($campaign['responsible']) {
					$user = $User->getUser($campaign['responsible']);
					$Email->send($user, 0, 0, $campaign);
					//echo 123;
				}
			}
		}
	//}
}*/

require_once(BASE_PATH.'/library/DEEC/Address.php');
$Address = new DEEC_Address(BASE_PATH, DB_SERVER, DB_USER, DB_PASSWORD, DB_NAME);
$address = $Address->getLatestAddress();

// Test cron for geodata
//print_r($address);
$geodata = array();
if($address['street']) {
	if(strtotime($address['geoupdated']) <= strtotime($address['modified'])) {
		$location = urlencode($address['street'].' '.$address['postcode'].' '.$address['city']);
		$opts = array('http'=>array('header'=>"User-Agent: DEWAWI\r\n"));
		$context = stream_context_create($opts);
		$url = 'https://nominatim.openstreetmap.org/search?addressdetails=1&q='.$location.'&format=jsonv2&limit=1';
		//print_r($url);
		$json = file_get_contents($url, false, $context);
		//print_r($json);
		if($json) {
			$geocode = json_decode($json);
			//print_r($geocode);
			if(isset($geocode[0]->lat) && isset($geocode[0]->lon)) {
				$geodata['latitude'] = $geocode[0]->lat;
				$geodata['longitude'] = $geocode[0]->lon;
				$geodata['geoupdated'] = date('Y-m-d H:i:s');
				$Address->updateAddress($address['id'], $geodata);
			} else {
				$geodata['latitude'] = 'NULL';
				$geodata['longitude'] = 'NULL';
				$geodata['geoupdated'] = date('Y-m-d H:i:s');
				$Address->updateAddress($address['id'], $geodata);
			}
		} else {
			$geodata['latitude'] = 'NULL';
			$geodata['longitude'] = 'NULL';
			$geodata['geoupdated'] = date('Y-m-d H:i:s');
			$Address->updateAddress($address['id'], $geodata);
		}
	} else {
		$geodata['latitude'] = 'NULL';
		$geodata['longitude'] = 'NULL';
		$geodata['geoupdated'] = date('Y-m-d H:i:s');
		$Address->updateAddress($address['id'], $geodata);
	}
} else {
	$geodata['latitude'] = 'NULL';
	$geodata['longitude'] = 'NULL';
	$geodata['geoupdated'] = date('Y-m-d H:i:s');
	$Address->updateAddress($address['id'], $geodata);
}
//print_r($geodata);

function isBetween($from, $till, $input) {
	$f = DateTime::createFromFormat('!H:i', $from);
	$t = DateTime::createFromFormat('!H:i', $till);
	$i = DateTime::createFromFormat('!H:i', $input);
	if ($f > $t) $t->modify('+1 day');
	return ($f <= $i && $i <= $t) || ($f <= $i->modify('+1 day') && $i <= $t);
}

error_log(date("Y-m-d H:i:s")." Cronjob 'dewawi' finished.\n", 3, $log_file);

// helpers
function withinDateRange(DateTime $now, $start, $due): bool {
    if ($start) {
        $sd = DateTime::createFromFormat('Y-m-d H:i:s', $start);
        if ($sd && $now < $sd) return false;
    }
    if ($due) {
        $dd = DateTime::createFromFormat('Y-m-d H:i:s', $due);
        if ($dd && $now > $dd) return false;
    }
    return true;
}

function withinDailyWindow(DateTime $now, $from, $till): bool {
    if (!$from || !$till) return true; // no window → allowed all day
    // Both stored as 'HH:MM:SS'
    $f = DateTime::createFromFormat('H:i:s', $from);
    $t = DateTime::createFromFormat('H:i:s', $till);
    if (!$f || !$t) return true;

    $cur = DateTime::createFromFormat('H:i:s', $now->format('H:i:s'));

    // allow windows that cross midnight
    if ($f <= $t) {
        return $cur >= $f && $cur <= $t;
    } else {
        return ($cur >= $f) || ($cur <= $t);
    }
}
