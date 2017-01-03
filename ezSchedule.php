<?php

require('vendor/autoload.php');

use Abraham\TwitterOAuth\TwitterOAuth;

$user = '????????';
$pass = '';
$MSU_PID = 1;
$TWITTER_HANDLE = "";

date_default_timezone_set("EST");

function ilog($s1, $s2 = '') {
    $time = date("H:i:s");
    echo '['.$time.'] '.$s1;
    if($s2 !== '') { echo ' => '.$s2; }
    echo "\n";
}

function DM($screen_name, $class) {
    $consumerKey = "";
    $consumerSecret = "";
    $accessToken = "";
    $accessTokenSecret = "";

    $twitter = new TwitterOAuth($consumerKey, $consumerSecret, $accessToken, $accessTokenSecret);

    $text = "Open seat in ".$class.". Visit https://login.msu.edu/?App=RO_Schedule";
    $options = array("screen_name" => $screen_name, "text" => $text);
    $twitter->post("direct_messages/new", $options);

    ilog("DM to ".$screen_name." sent", $text);
}

function customDM($screen_name, $msg) {
    $consumerKey = "";
    $consumerSecret = "";
    $accessToken = "";
    $accessTokenSecret = "";

    $twitter = new TwitterOAuth($consumerKey, $consumerSecret, $accessToken, $accessTokenSecret);

    $options = array("screen_name" => $screen_name, "text" => $msg);
    $twitter->post("direct_messages/new", $options);

    ilog("DM to ".$screen_name." sent", $msg);
}

function element($s, $tag, $endtag = '', $inclucive = false) {
    $start = stripos($s, $tag);
    $start += $inclusive ? 0 : strlen($tag);
    $s = substr($s, $start);
    
    if($endtag == '') { $endtag = '</'.substr($tag, 1); }
    $end = stripos($s, $endtag);
    $end += $inclusive ? strlen($endtag) : 0;
    $s = substr($s, 0, $end);
    
    return $s;
}

function getHead($s, $tag = "<head>") {
    return element($s, $tag);
}

function getBody($s, $tag = "<body>") {
    return element($s, $tag);
}

function findStamp($body) {
    $begin = "EncryptedStamp\" value=\"";

    return substr($body,strpos($body,$begin)+strlen($begin),12);
}

function between($x, $min, $max) {
    return ($min <= $x && $x <= $max);
}

function findPWfield($body) {
    $begin = "<input name=\"";
    $pwSub = substr($body,strpos($body,"<td id=\"D6501LoginBoxPwInput\">"));
    $start = strpos($pwSub,$begin) + strlen($begin);

    return substr($pwSub,$start,5);
}

function sleepIfEnrollingOffline() {
    if(intval(date('H')) > '21' && intval(date('w')) != 6) {
        $hoursToWait = 33-intval(date('H'));
        ilog("Good night, scheduling offline.");
        ilog("Sleeping for ".$hoursToWait." hours.");
        for($i = 0; $i < $hoursToWait; $i++) {
            Sleep(3600);
        }
    }

    if(intval(date('H')) < '9' && intval(date('w')) != 0) {
        $hoursToWait = 9-intval(date('H'));
        ilog("Good morning, scheduling offline.");
        ilog("Sleeping for ".$hoursToWait." hours.");
        for($i = 0; $i < $hoursToWait; $i++) {
            Sleep(3600);
        }
    }

}

function sleepUntilNextNotification() {
    $minute = intval(date('i'));
    $validMinutes = array(15, 35, 55);
    while( !in_array($minute, $validMinutes)) {
        Sleep(43);
        $minute = intval(date('i'));
    }
}

ilog("START");
$tries = 0;
$retries = 0;
session_write_close();
////////////////////////////////////////////////////////////
// load login page for stamp and pwField name
////////////////////////////////////////////////////////////
retry:
$tries++;
$ch = curl_init();

$ph = array(
    'POST /Login HTTP/1.1',
    'Host: login.msu.edu',
    'Connection: keep-alive',
    'Cache-Control: max-age=0',
    'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,*/*;q=0.8',
    'Origin: https://login.msu.edu',
    'User-Agent: Mozilla/5.0 (Macintosh; Intel Mac OS X 10_11_5) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/54.0.2840.98 Safari/537.36',
    'Content-Type: application/x-www-form-urlencoded',
    'Referer: https://login.msu.edu/?App=RO_Schedule',
    'Accept-Encoding: gzip, deflate',
    'Accept-Language: en-US,en;q=0.8',
);

curl_setopt($ch, CURLOPT_COOKIESESSION, false);
curl_setopt($ch, CURLOPT_COOKIEJAR, 'cookies.txt');
curl_setopt($ch, CURLOPT_COOKIEFILE, 'cookies.txt');
curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_11_5) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/54.0.2840.98 Safari/537.36'); 
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
curl_setopt($ch, CURLOPT_HEADER, TRUE);
curl_setopt($ch, CURLOPT_NOBODY, FALSE);
curl_setopt($ch, CURLOPT_POST, TRUE); 
curl_setopt($ch, CURLOPT_HTTPHEADER, $ph);
curl_setopt($ch, CURLOPT_HTTPGET, FALSE);
curl_setopt($ch, CURLOPT_URL, 'https://login.msu.edu/?App=RO_Schedule');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

$r = curl_exec($ch);
curl_close($ch);
unset($ch);

$h = getHead($r);
$b = getBody($r);

if(stripos($b, $MSU_PID)) {
    goto checkpoint0;
}
ilog('Attempting Login (Current Cookie Invalid)');

////////////////////////////////////////////////////////////
// if not already logged in, log in
////////////////////////////////////////////////////////////
$stamp = findStamp($b);
ilog("stamp", $stamp);
$pwField = findPWfield($b);
ilog("pwField", $pwField);

$q = 'AlternateID='.$user.
     '&'.$pwField."=".$pass.
     '&EncryptedStamp='.$stamp.
     '&App=RO_Schedule&submit=Login&AuthenticationMethod=MSUNet';

$ch = curl_init();

curl_setopt($ch, CURLOPT_COOKIESESSION, false);
curl_setopt($ch, CURLOPT_COOKIEJAR, 'cookies.txt');
curl_setopt($ch, CURLOPT_COOKIEFILE, 'cookies.txt');
curl_setopt($ch, CURLOPT_REFERER, 'https://login.msu.edu/?App=RO_Schedule');
curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 6.1; WOW64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/42.0.2311.135 Safari/537.36'); 
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
curl_setopt($ch, CURLOPT_HEADER, TRUE);
curl_setopt($ch, CURLOPT_NOBODY, FALSE);
curl_setopt($ch, CURLOPT_POST, TRUE); 
curl_setopt($ch, CURLOPT_HTTPHEADER, $ph);
curl_setopt($ch, CURLOPT_POSTFIELDS, $q);
curl_setopt($ch, CURLOPT_HTTPGET, FALSE);
curl_setopt($ch, CURLOPT_URL, 'https://login.msu.edu/Login');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

$r = curl_exec($ch);
curl_close($ch);
unset($ch);

$h = getHead($r);
$b = getBody($r);

if(stripos($b, $MSU_PID)) {
    ilog('Login Success');
} elseif(stripos($b, "Not Authenticated")) {
    ilog('Not Authenticated');
} elseif(stripos($b, "Login Screen has expired, please try again.")) {
    ilog('Login Screen Expired');
    $retries += 1;
    if($retries > 2) {
        ilog('Exceeded allowed number of retries.');
        goto end;
    }
    ilog('Retrying Login');
    goto retry;
} elseif(stripos($b, "You have exceeded the number of enrollment actions allowed per day.")) {
    ilog('Exceeded allowed number of enrollment actions.');
    customDM($TWITTER_HANDLE, "too many actions today. terminated :(");
    goto end;
}else {
    ilog('Login Failed');
    echo "\n\n LOGIN RESPONSE BODY: \n".$b;
    echo "\n\n END LOGIN RESPONSE BODY \n\n\n\n\n\n\n\n\n\n";
}
$retries = 0;

checkpoint0:

////////////////////////////////////////////////////////////
// check planned courses for an opening
////////////////////////////////////////////////////////////

$pC = 0;
$enrollable = 0;
$tagpos = strpos($b, '<a id="MainContent_UCPlanned_rptPlanner_imgEnroll_'.$pC.'" title="Enroll in ');
$taglen = 70;

while($tagpos) {
    $b = substr($b, $tagpos + $taglen);
    $classcode = substr($b, 0, 3);
    $b = substr($b, 4);
    $classnum = substr($b, 0, 3);
    $b = substr($b, 12);
    $section = substr($b, 0, 3);
    
    $tagpos = strpos($b, '<a id="MainContent_UCPlanned_rptPlanner_imgEnroll_'.($pC+1).'" title="Enroll in ');

    if(strpos($b, 'Section Full') < $tagpos || (strpos($b, 'Section Full') && !$tagpos)) {  }
    elseif(strpos($b, 'Restricted') < $tagpos || (strpos($b, 'Restricted') && !$tagpos)) {  }
    else { 
        $enrollable++;
        $message = $classcode.$classnum." sec".$section." is open! visit https://login.msu.edu/?App=RO_Schedule";
        DM($TWITTER_HANDLE, $message);
    }
    $pC++;
}

ilog($user.":".$tries, $enrollable);
Sleep(60);
sleepIfEnrollingOffline();
sleepUntilNextNotification();
if($tries < 7000) { goto retry; }

////////////////////////////////////////////////////////////
// end of the script
////////////////////////////////////////////////////////////

end:

ilog('terminated...');
echo "\r\n\r\n\r\n";

?>