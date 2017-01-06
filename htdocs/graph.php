<?php

require("include.php");

//***************************************************************************
// Main
//***************************************************************************

$db = ConnectDb();

// Get parameters

if (isset($_GET['width']) && is_numeric($_GET['width']))
    $width = $_GET['width'];
else
	$width = DFLT_WIDTH;

if (isset($_GET['height']) && is_numeric($_GET['height']))
    $height = $_GET['height'];
else
	$height = DFLT_HEIGHT;

if (isset($_GET['interval']) && is_numeric($_GET['interval']))
    $int = $_GET['interval'];
else
	$int = DFLT_INTERVAL;

if (isset($_GET['ip']))
{
   $ip = $_GET['ip'];
	$ip = str_replace("_", "/", $ip);   # using underscore instead of slash to seperate subnet from bits
   $ip = pg_escape_string($ip);
}

if (isset($_GET['sensor_id']) && is_numeric($_GET['sensor_id']))
	$sensor_id = $_GET['sensor_id'];
else
   $sensor_id = 1;

if (isset($_GET['from']))
   $from = $_GET['from'];

if (isset($_GET['to']))
   $to = $_GET['to'];

// interval

switch ($int)
{
   case INT_TODAY:
   case INT_YESTERDAY:
      $int = INT_TODAY;
      $interval = 60*60*24;
      break;
   case INT_THIS_WEEK:
   case INT_LAST_WEEK:
      $int = INT_THIS_WEEK;
      $interval = 60*60*24*7;
      break;
   case INT_THIS_MONTH:
   case INT_LAST_MONTH:
      $int = INT_THIS_MONTH;
      $interval = 60*60*24*31;
      break;
   case INT_THIS_YEAR:
      $int = INT_THIS_YEAR;
      $interval = 60*60*24*366;
      break;

	default:
      $int = INT_TODAY;
      $interval = 60*60*24;
      break;
}

$timestamp = $from; // time() - $interval + (0.05*$interval);
$table = $_GET['table'];

// if (isset($_GET['yscale']))
//    $yscale = $_GET['yscale'];

$count = array();

$SentPeak = 0;
$TotalSent = 0;
$TotalPackets = 0;

$total = array();
$icmp = array();
$udp = array();
$tcp = array();
$ftp = array();
$http = array();
$mail = array();
$p2p = array();

// Accumulator

$a_total = array();
$a_icmp = array();
$a_udp = array();
$a_tcp = array();
$a_ftp = array();
$a_http = array();
$a_http = array();
$a_mail = array();
$a_p2p = array();

$sql = "select *, extract(epoch from timestamp) as ts from $table" .
       "  where ip <<= '$ip'" .
       "    and sensor_id = '$sensor_id'" .
       "    and timestamp > $from::abstime and timestamp < $to::abstime" .
       "  order by ip;";

$result = pg_query($sql);

// The SQL statement pulls the data out of the database ordered by IP address, that way we can average each
// datapoint for each IP address to provide smoothing and then toss the smoothed value into the accumulator
// to provide accurate total traffic rate.

$last_ip = "";
$YMax = 0;

while ($row = pg_fetch_array($result))
{
	if ($row['ip'] != $last_ip)
   {
		AverageAndAccumulate();
		$last_ip = $row['ip'];
   }

	$x = ($row['ts'] - $timestamp) * (($width-XOFFSET) / $interval) + XOFFSET;
	$xint = (int)$x;

	incArryElementBy($count, $xint, 1);

	if ($row['total'] / $row['sample_duration'] > $SentPeak)
		$SentPeak = $row['total'] / $row['sample_duration'];

	$TotalSent    += $row['total'];
	$TotalPackets += $row['packet_count'];

   incArryElementBy($total, $xint, $row['total'] / $row['sample_duration']);
	incArryElementBy($total, $xint, $row['total'] / $row['sample_duration']);
	incArryElementBy($icmp , $xint, $row['icmp']  / $row['sample_duration']);
	incArryElementBy($udp  , $xint, $row['udp']   / $row['sample_duration']);
	incArryElementBy($tcp  , $xint, $row['tcp']   / $row['sample_duration']);
	incArryElementBy($ftp  , $xint, $row['ftp']   / $row['sample_duration']);
	incArryElementBy($http , $xint, $row['http']  / $row['sample_duration']);
	incArryElementBy($mail , $xint, $row['mail']  / $row['sample_duration']);
	incArryElementBy($p2p  , $xint, $row['p2p']   / $row['sample_duration']);
}

// One more time for the last IP

AverageAndAccumulate();

// Pull the data out of Accumulator

$total = $a_total;
$icmp  = $a_icmp;
$udp   = $a_udp;
$tcp   = $a_tcp;
$ftp   = $a_ftp;
$http  = $a_http;
$mail  = $a_mail;
$p2p   = $a_p2p;

$YMax += $YMax * 0.05;    // Add an extra 5%

// if a y scale was specified override YMax

// if (isset($yscale))
//     $YMax = $yscale / 8;

// Plot the data

header("Content-type: image/png");

// Not enough data to graph

if ($YMax <= 1.1)
{
	$im = imagecreate($width, 20);
	$white = imagecolorallocate($im, 255, 255, 255);
	$black  = ImageColorAllocate($im, 0, 0, 0);
	ImageString($im, 2, $width/2,  0, "No Data", $black);
	imagepng($im);
	imagedestroy($im);

	exit(0);
}

$im = imagecreate($width, $height);
$white  = imagecolorallocate($im, 255, 255, 255);
$yellow = ImageColorAllocate($im, 255, 255, 0);
$purple = ImageColorAllocate($im, 255, 0, 255);
$green  = ImageColorAllocate($im, 0, 255, 0);
$blue   = ImageColorAllocate($im, 0, 0, 255);
$orange = ImageColorAllocate($im, 255, 128, 0);
$lblue  = ImageColorAllocate($im, 128, 128, 255);
$brown  = ImageColorAllocate($im, 128, 0, 0);
$red    = ImageColorAllocate($im, 255, 0, 0);
$black  = ImageColorAllocate($im, 0, 0, 0);

for ($Counter = XOFFSET+1; $Counter < $width; $Counter++)
{
	if (isset($total[$Counter]))
   {
      // Convert the bytes/sec to y coords

      $total[$Counter] = ($total[$Counter] * ($height-YOFFSET)) / $YMax;
		$tcp[$Counter]   = ($tcp[$Counter]   * ($height-YOFFSET)) / $YMax;
      $ftp[$Counter]   = ($ftp[$Counter]   * ($height-YOFFSET)) / $YMax;
		$http[$Counter]  = ($http[$Counter]  * ($height-YOFFSET)) / $YMax;
		$mail[$Counter]  = ($mail[$Counter]  * ($height-YOFFSET)) / $YMax;
		$p2p[$Counter]   = ($p2p[$Counter]   * ($height-YOFFSET)) / $YMax;
      $udp[$Counter]   = ($udp[$Counter]   * ($height-YOFFSET)) / $YMax;
		$icmp[$Counter]  = ($icmp[$Counter]  * ($height-YOFFSET)) / $YMax;

		// Stack 'em up!
		// Total is stacked from the bottom
		// Icmp is on the bottom too

		$udp[$Counter] += $icmp[$Counter];   // Udp is stacked on top of icmp
		$tcp[$Counter] += $udp[$Counter];    // TCP and p2p are stacked on top of Udp
		$p2p[$Counter] += $udp[$Counter];    //  "    "     "    "    "
		$http[$Counter] += $p2p[$Counter];   // Http is stacked on top of p2p
		$mail[$Counter] += $http[$Counter];  // Mail is stacked on top of http
      $ftp[$Counter] += $mail[$Counter];   // Ftp is stacked on top of http

		// Plot them!
		//echo "$Counter:".$Counter." (h-y)-t:".($height-YOFFSET) - $total[$Counter]." h-YO-1:".$height-YOFFSET-1;

      // ImageLine($im, $Counter, ($height-YOFFSET) - $total[$Counter], $Counter, $height-YOFFSET-1, $yellow);
      ImageLine($im, $Counter, ($height-YOFFSET) - $icmp[$Counter], $Counter, ($height-YOFFSET) - 1, $red);
      ImageLine($im, $Counter, ($height-YOFFSET) - $udp[$Counter],  $Counter, ($height-YOFFSET) - $icmp[$Counter] - 1, $brown);
      ImageLine($im, $Counter, ($height-YOFFSET) - $tcp[$Counter],  $Counter, ($height-YOFFSET) - $udp[$Counter] - 1, $green);
      ImageLine($im, $Counter, ($height-YOFFSET) - $p2p[$Counter],  $Counter, ($height-YOFFSET) - $udp[$Counter] - 1, $purple);
      ImageLine($im, $Counter, ($height-YOFFSET) - $http[$Counter], $Counter, ($height-YOFFSET) - $p2p[$Counter] - 1, $blue);
      ImageLine($im, $Counter, ($height-YOFFSET) - $mail[$Counter], $Counter, ($height-YOFFSET) - $http[$Counter] - 1, $orange);
      ImageLine($im, $Counter, ($height-YOFFSET) - $ftp[$Counter],  $Counter, ($height-YOFFSET) - $mail[$Counter] - 1, $lblue);
   }
}

// Margin Text

if ($SentPeak < 1024/8)
	$txtPeakSendRate = sprintf("Peak Rate: %.1f KBits/sec", $SentPeak*8);
else if ($SentPeak < (1024*1024)/8)
   $txtPeakSendRate = sprintf("Peak Rate: %.1f MBits/sec", ($SentPeak*8.0)/1024.0);
else
	$txtPeakSendRate = sprintf("Peak Rate: %.1f GBits/sec", ($SentPeak*8.0)/(1024.0*1024.0));

if ($TotalSent < 1024)
	$txtTotalSent = sprintf("Total %.1f KBytes", $TotalSent);
else if ($TotalSent < 1024*1024)
	$txtTotalSent = sprintf("Total %.1f MBytes", $TotalSent/1024.0);
else
	$txtTotalSent = sprintf("Total %.1f GBytes", $TotalSent/(1024.0*1024.0));

$txtPacketsPerMtu = sprintf("%dk Pkts, Avg Size: %.1f bytes", $TotalPackets/1000, ($TotalSent*1024.0)/$TotalPackets);

ImageString($im, 2, XOFFSET+5,  $height-20, $txtTotalSent, $black);
ImageString($im, 2, ($width-XOFFSET)/3+XOFFSET,  $height-20, $txtPeakSendRate, $black);
ImageString($im, 2, 2*(($width-XOFFSET)/3)+XOFFSET,  $height-20, $txtPacketsPerMtu, $black);


//***************************************************************************
// Draw X Axis
//***************************************************************************

ImageLine($im, 0, $height-YOFFSET, $width, $height-YOFFSET, $black);

// Day/Month Seperator bars

if ((24*60*60*($width-XOFFSET))/$interval > ($width-XOFFSET)/10)
{
	$ts = getdate($timestamp);
	$MarkTime = mktime(0, 0, 0, $ts['mon'], $ts['mday'], $ts['year']);
   $x = ts2x($MarkTime);

   while ($x < XOFFSET)
   {
      $MarkTime += 24*60*60;
      $x = ts2x($MarkTime);
   }

   while ($x < ($width-10))
   {
      // Day Lines

      // syslog(LOG_DEBUG, "bandwidth: dayline at x=".($x)."");

      ImageLine($im, $x, 0, $x, $height-YOFFSET, $black);
      // ImageLine($im, $x+1, 0, $x+1, $height-YOFFSET, $black);  // linie dicker machen :o warum ??

      $txtDate = strftime("%a, %b %d", $MarkTime);
      ImageString($im, 2, $x-30, $height-YOFFSET+10, $txtDate, $black);

      // Calculate Next x

      $MarkTime += (24*60*60);  // skip to next day
      $x = ts2x($MarkTime);
   }
}

else if ((24*60*60*30*($width-XOFFSET))/$interval > ($width-XOFFSET)/10)
{
	// Monthly Bars

	$ts = getdate($timestamp);
	$month = $ts['mon'];
	$MarkTime = mktime(0, 0, 0, $month, 1, $ts['year']);
   $x = ts2x($MarkTime);

   while ($x < XOFFSET)
   {
		$month++;
      $MarkTime = mktime(0, 0, 0, $month, 1, $ts['year']);
      $x = ts2x($MarkTime);
   }

   while ($x < ($width-10))
   {
      // Day Lines
      ImageLine($im, $x, 0, $x, $height-YOFFSET, $black);
      //ImageLine($im, $x+1, 0, $x+1, $height-YOFFSET, $black);

      $txtDate = strftime("%b, %Y", $MarkTime);
      ImageString($im, 2, $x-25,  $height-YOFFSET+10, $txtDate, $black);

      // Calculate Next x

		$month++;
      $MarkTime = mktime(0, 0, 0, $month, 1, $ts['year']);
      $x = ts2x($MarkTime);
   }
}
else
{
	// Year Bars

   $ts = getdate($timestamp);
   $year = $ts['year'];
   $MarkTime = mktime(0, 0, 0, 1, 1, $year);
   $x = ts2x($MarkTime);

   while ($x < XOFFSET)
   {
      $year++;
      $MarkTime = mktime(0, 0, 0, 1, 1, $year);
      $x = ts2x($MarkTime);
   }

   while ($x < ($width-10))
   {
      // Day Lines
      ImageLine($im, $x, 0, $x, $height-YOFFSET, $black);
      // ImageLine($im, $x+1, 0, $x+1, $height-YOFFSET, $black);

      $txtDate = strftime("%b, %Y", $MarkTime);
      ImageString($im, 2, $x-25,  $height-YOFFSET+10, $txtDate, $black);

      // Calculate Next x

      $year++;
      $MarkTime = mktime(0, 0, 0, 1, 1, $year);
      $x = ts2x($MarkTime);
   }
}

// Draw Major Tick Marks

if ($int == INT_TODAY)
{
   // for day - draw major ticks all 6 hours

	$MarkTimeStep = 6*60*60;
}

else if ($int == INT_THIS_WEEK)
{
   // for week - draw major ticks all 24 hours

	$MarkTimeStep = 24*60*60;
}

else if ($int == INT_THIS_MONTH)
{
   // for month - draw major ticks all 24 hours

	$MarkTimeStep = 24*60*60;
}

else if ($int == INT_THIS_YEAR)
{
   // Major tick marks for year

   $MarkTimeStep = 0; // Skip the standard way of drawing major tick marks below

   $ts = getdate($timestamp);
   $month = $ts['mon'];
   $MarkTime = mktime(0, 0, 0, $month, 1, $ts['year']);

   $x = ts2x($MarkTime);

   while ($x < XOFFSET)
   {
      $month++;
      $MarkTime = mktime(0, 0, 0, $month, 1, $ts['year']);
      $x = ts2x($MarkTime);
   }

   while ($x < ($width-10))
   {
      // Day Lines

		$date = getdate($MarkTime);

		if ($date['mon'] != 1)
      {
         ImageLine($im, $x, $height-YOFFSET-5, $x, $height-YOFFSET+5, $black);
         $txtDate = strftime("%b", $MarkTime);
        	ImageString($im, 2, $x-5,  $height-YOFFSET+10, $txtDate, $black);
      }

      // Calculate Next x

      $month++;
      $MarkTime = mktime(0, 0, 0, $month, 1, $ts['year']);
      $x = ts2x($MarkTime);
   }
}
else
	$MarkTimeStep = 0; // Skip Major Tick Marks

if ($MarkTimeStep)
{
   $ts = getdate($timestamp);
	$MarkTime = mktime(0, 0, 0, $ts['mon'], $ts['mday'], $ts['year']);
	$x = ts2x($MarkTime);

	while ($x < ($width-10))
   {
    	if ($x > XOFFSET)
         ImageLine($im, $x, $height-YOFFSET-5, $x, $height-YOFFSET+5, $black);

		$MarkTime += $MarkTimeStep;
      $x = ts2x($MarkTime);
   }
}

// Draw Minor Tick marks

if ((60*60*($width-XOFFSET))/$interval > 4)          // pixels per hour is more than 2
	$MarkTimeStep = 60*60;                            // Minor ticks are 1 hour
else if ((6*60*60*($width-XOFFSET))/$interval > 4)
	$MarkTimeStep = 6*60*60;                          // Minor ticks are 6 hours
else if ((24*60*60*($width-XOFFSET))/$interval > 4)
	$MarkTimeStep = 24*60*60;
else
	$MarkTimeStep = 0;                                // Skip minor tick marks

if ($MarkTimeStep)
{
   $ts = getdate($timestamp);
	$MarkTime = mktime(0, 0, 0, $ts['mon'], $ts['mday'], $ts['year']);
	$x = ts2x($MarkTime);

	while ($x < ($width-10))
   {
    	if ($x > XOFFSET)
         ImageLine($im, $x, $height-YOFFSET, $x, $height-YOFFSET+5, $black);

		$MarkTime += $MarkTimeStep;
      $x = ts2x($MarkTime);
   }
}

//***************************************************************************
// Draw Y Axis
//***************************************************************************

ImageLine($im, XOFFSET, 0, XOFFSET, $height, $black);

$YLegend = 'k';
$Divisor = 1;

if ($YMax*8 > 1024*2)
{
   $Divisor = 1024;    // Display in m
   $YLegend = 'm';
}

if ($YMax*8 > 1024*1024*2)
{
   $Divisor = 1024*1024; // Display in g
   $YLegend = 'g';
}

if ($YMax*8 > 1024*1024*1024*2)
{
   $Divisor = 1024*1024*1024; // Display in t
   $YLegend = 't';
}

if ($height/10 > 15)
	$YMarks = 10;
else
	$YMarks = 5;

$YStep = $YMax/$YMarks;

if ($YStep < 1)
	$YStep = 1;

$YTic = $YStep;
$YMarks = $YMax / $YStep;  // recalc marc count with real step
$stepPix = ($height-YOFFSET) / $YMarks;

//while ($YTic <= ($YMax - $YMax/$YMarks))

for ($i = 1; $i <= $YMarks; $i++)
{
   // $y = ($height-YOFFSET) - (($YTic * ($height-YOFFSET)) / $YMax);

   $y = ($height-YOFFSET) - $stepPix * $i;

	ImageLine($im, XOFFSET, $y, $width, $y, $black);
   $txtYLegend = sprintf("%4.1f %sbits/s", (8.0*$YTic)/$Divisor, $YLegend);
   ImageString($im, 2, 3, $y-7, $txtYLegend, $black);
	$YTic += $YStep;
}

imagepng($im);
imagedestroy($im);

//***************************************************************************
// Increment Arry Element By
//***************************************************************************

function incArryElementBy(&$arr, $pos, $value)
{
   if (isset($arr[$pos]))
      $arr[$pos] += $value;
   else
      $arr[$pos] = $value;
}

//***************************************************************************
// Returns x location of any given timestamp
//***************************************************************************

function ts2x($ts)
{
	global $timestamp, $width, $interval;

	return ($ts-$timestamp)*(($width-XOFFSET) / $interval) + XOFFSET;
}

//***************************************************************************
// If we have multiple IP's in a result set we need
//    to total the average of each IP's samples
//***************************************************************************

function AverageAndAccumulate()
{
	global $count, $total, $icmp, $udp, $tcp, $ftp, $http, $mail, $p2p, $YMax;
	global $a_total, $a_icmp, $a_udp, $a_tcp, $a_ftp, $a_http, $a_mail, $a_p2p;

	foreach ($count as $key => $number)
   {
      $total[$key] /= $number;
    	$icmp[$key] /= $number;
    	$udp[$key] /= $number;
    	$tcp[$key] /= $number;
    	$ftp[$key] /= $number;
    	$http[$key] /= $number;
		$mail[$key] /= $number;
    	$p2p[$key] /= $number;
   }

	foreach ($count as $key => $number)
   {
		incArryElementBy($a_total, $key, $total[$key]);
		incArryElementBy($a_icmp, $key, $icmp[$key]);
		incArryElementBy($a_udp , $key, $udp[$key]);
		incArryElementBy($a_tcp , $key, $tcp[$key]);
		incArryElementBy($a_ftp , $key, $ftp[$key]);
		incArryElementBy($a_http, $key, $http[$key]);
		incArryElementBy($a_mail, $key, $mail[$key]);
		incArryElementBy($a_p2p , $key, $p2p[$key]);

// 		if ($a_total[$key] > $YMax)
// 			$YMax = $a_total[$key];

		if ($a_icmp[$key] > $YMax)
			$YMax = $a_icmp[$key];

		if ($a_udp[$key] > $YMax)
			$YMax = $a_udp[$key];

		if ($a_tcp[$key] > $YMax)
			$YMax = $a_tcp[$key];

		if ($a_ftp[$key] > $YMax)
			$YMax = $a_ftp[$key];

		if ($a_http[$key] > $YMax)
			$YMax = $a_http[$key];

		if ($a_mail[$key] > $YMax)
			$YMax = $a_mail[$key];

		if ($a_p2p[$key] > $YMax)
			$YMax = $a_p2p[$key];
   }

	unset($GLOBALS['total'], $GLOBALS['icmp'], $GLOBALS['udp'], $GLOBALS['tcp'], $GLOBALS['ftp'], $GLOBALS['http'], $GLOBALS['mail'], $GLOBALS['p2p'], $GLOBALS['Count']);

	$total = array();
	$icmp = array();
	$udp = array();
	$tcp = array();
	$ftp = array();
	$http = array();
	$mail = array();
	$p2p = array();
	$count = array();
}

?>
