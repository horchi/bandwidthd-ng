<?php

include("include.php");
$subtitle = "Sensors";
include("header.php");

// init

$sensor_id = "";
$interval = INT_TODAY;
$subnet = "0.0.0.0/0";
$limit = "all";

//***************************************************************************
// Get variables from url
//***************************************************************************

if (isset($_GET['sensor_id']) && is_numeric($_GET['sensor_id']))
   $sensor_id = $_GET['sensor_id'];

if (isset($_GET['interval']) && is_numeric($_GET['interval']))
   $interval = $_GET['interval'];

if (isset($_GET['subnet']) && $_GET['subnet'] != "none" && $_GET['subnet'] != "" )
   $subnet = pg_escape_string($_GET['subnet']);

if (isset($_GET['limit']) && ($_GET['limit'] == "all" || is_numeric($_GET['limit'])))
	$limit = $_GET['limit'];

$db = ConnectDb();

//***************************************************************************
//
//***************************************************************************

$sql = "SELECT sensor_name, interface, sensor_id from sensors order by sensor_name, interface;";
$result = @pg_query($sql);

if (!$result)
{
	echo "<center>No data</center>";
	include('footer.php');
	exit();
}

// Display Checkboxes

echo "<form name=\"navigation\" method=get action=\"" . $_SERVER['PHP_SELF'] . "\">\n";
echo "   <br>\n";
echo "   <table width=\"75%\" cellspacing=0 cellpadding=5 border=1 rules=none>\n";
echo "   <tr>\n";

echo "   <td>\n";
echo "      <select name=\"sensor_id\">\n";
echo "         <option value=\"none\">--Select A Sensor--</option>\n";

while ($r = pg_fetch_array($result))
{
   echo '         <option value="' . $r['sensor_id'] .'" '
   . ($sensor_id==$r['sensor_id']?"SELECTED":"") . '>'
   . $r['sensor_name'] . ' - ' . $r['interface'] . "</option>\n";
}

echo "         </select>\n";
echo "      </td>\n";
echo "      <td>\n";
echo "       <select name=\"interval\">\n";
echo "         <option value=\"none\">--Select An Interval--</option>\n";
echo "         <option value=" . INT_TODAY      . ($interval == INT_TODAY      ? " SELECTED" : "") . ">Heute</option>\n";
echo "         <option value=" . INT_YESTERDAY  . ($interval == INT_YESTERDAY  ? " SELECTED" : "") . ">Gestern</option>\n";
echo "         <option value=" . INT_THIS_HOUR  . ($interval == INT_THIS_HOUR  ? " SELECTED" : "") . ">Diese Stunde</option>\n";
echo "         <option value=" . INT_LAST_HOUR  . ($interval == INT_LAST_HOUR  ? " SELECTED" : "") . ">Letzte Stunde</option>\n";
echo "         <option value=" . INT_THIS_WEEK  . ($interval == INT_THIS_WEEK  ? " SELECTED" : "") . ">Diese Woche</option>\n";
echo "         <option value=" . INT_LAST_WEEK  . ($interval == INT_LAST_WEEK  ? " SELECTED" : "") . ">Letzte Woche</option>\n";
echo "         <option value=" . INT_THIS_MONTH . ($interval == INT_THIS_MONTH ? " SELECTED" : "") . ">Dieser Monat</option>\n";
echo "         <option value=" . INT_LAST_MONTH . ($interval == INT_LAST_MONTH ? " SELECTED" : "") . ">Letzter Monat</option>\n";
echo "         <option value=" . INT_THIS_YEAR  . ($interval == INT_THIS_YEAR  ? " SELECTED" : "") . ">Dieses Jahr</option>\n";
echo "       </select>\n";
echo "     </td>\n";
echo "     <td>\n";
echo "       <select name=\"limit\">\n";
echo "         <option value=\"none\">--How Many Results--</option>\n";
echo "         <option value=20"  . ($limit == 20    ? " SELECTED" : "") . ">20</option>\n";
echo "         <option value=50"  . ($limit == 50    ? " SELECTED" : "") . ">50</option>\n";
echo "         <option value=100" . ($limit == 100   ? " SELECTED" : "") . ">100</option>\n";
echo "         <option value=all" . ($limit == "all" ? " SELECTED" : "") . ">All</option>\n";
echo "       </select>\n";
echo "     </td>\n";
echo "     <td>\n";
echo "      Subnet Filter:<input name=subnet value=\"" . isset($subnet) ? $subnet : "0.0.0.0/0" . "\">\n";
echo "     </td>\n";
echo "     <td>\n";
echo "      <input type=submit value=\"  Go  \">\n";
echo "     </td>\n";
echo "   </tr>\n";
echo "  </table>\n";
echo "</form>\n";

//***************************************************************************
//
//***************************************************************************

if (!isset($sensor_id) || $sensor_id == "")
{
	include('footer.php');
	exit();
}

$sql = "SELECT sensor_name, interface, sensor_id FROM sensors WHERE sensor_id = '$sensor_id';";
$result = @pg_query($sql);
$r = pg_fetch_array($result);
$sensor_name = $r['sensor_name'];
$interface = $r['interface'];

// interval

switch ($interval)
{
   case INT_TODAY:
      $from = mktime(0, 0, 0, date("m"), date("d"), date("Y"));
      $to = time();
      break;
   case INT_YESTERDAY:
      $from = mktime(0, 0, 0, date("m"), date("d")-1, date("Y"));
      $to = mktime(0, 0, 0, date("m"), date("d"), date("Y"));
      break;
   case INT_THIS_HOUR:
      $from = mktime(date("H"), 0, 0, date("m"), date("d"), date("Y"));
      $to = time();
      break;
   case INT_LAST_HOUR:
      $from = mktime(date("H")-1, 0, 0, date("m"), date("d"), date("Y"));
      $to = mktime(date("H"), 0, 0, date("m"), date("d"), date("Y"));
      break;
   case INT_THIS_WEEK:
      $from = strtotime("last Monday", mktime(0, 0, 0, date("m"), date("d")+1, date("Y")));
      $to = time();
      break;
   case INT_LAST_WEEK:
      $from = strtotime("last Monday", mktime(0, 0, 0, date("m"), date("d")+1, date("Y"))) - 60*60*24*7;
      $to = $from + 60*60*24*7;
      break;
   case INT_THIS_MONTH:
      $from = mktime(0, 0, 0, date("m"), 1, date("Y"));
      $to = time();
      break;
   case INT_LAST_MONTH:
      $from = mktime(0, 0, 0, date("m")-1, 1, date("Y"));
      $to = mktime(0, 0, 0, date("m"), 1, date("Y"));
      break;
   case INT_THIS_YEAR:
      $from = mktime(0, 0, 0, 1, 1, date("Y"));
      $to = time();
      break;

	default:
      echo("<center>Invalid interval".($interval)."</center><br> ");
      exit(1);
      break;
}

echo("<br><h2>Traffic from ".(date("d. M H:i", $from))."   to   ".(date("d. M H:i", $to))." </h2><br>");

// Sql Statement

$sql = "select tx.ip, rx.scale as rxscale, tx.scale as txscale, tx.total+rx.total as total, tx.total as sent,
               rx.total as received, tx.tcp+rx.tcp as tcp, tx.udp+rx.udp as udp,
               tx.icmp+rx.icmp as icmp, tx.http+rx.http as http,
               tx.mail+rx.mail as mail,
               tx.p2p+rx.p2p as p2p, tx.ftp+rx.ftp as ftp
          from
              (SELECT ip, max(total/sample_duration)*8 as scale, sum(total) as total, sum(tcp) as tcp, sum(udp) as udp, sum(icmp) as icmp,
                      sum(http) as http, sum(mail) as mail, sum(p2p) as p2p, sum(ftp) as ftp
                 from sensors, bd_tx_log
                 where sensors.sensor_id = '$sensor_id'
                       and sensors.sensor_id = bd_tx_log.sensor_id
                       and ip <<= '$subnet'
                       and timestamp > $from::abstime and timestamp < $to::abstime
                 group by ip) as tx,
              (SELECT ip, max(total/sample_duration)*8 as scale, sum(total) as total, sum(tcp) as tcp, sum(udp) as udp, sum(icmp) as icmp,
                      sum(http) as http, sum(mail) as mail, sum(p2p) as p2p, sum(ftp) as ftp
                 from sensors, bd_rx_log
                 where sensors.sensor_id = '$sensor_id'
                       and sensors.sensor_id = bd_rx_log.sensor_id
                       and ip <<= '$subnet'
                       and timestamp > $from::abstime and timestamp < $to::abstime
                       group by ip) as rx
           where tx.ip = rx.ip
           order by total desc;";


pg_query("SET sort_mem TO 30000;");
pg_send_query($db, $sql);
$starttime = microtime(true);

while (pg_connection_busy($db))
	usleep(1000);

$result = pg_get_result($db);

pg_query("set sort_mem to default;");

if ($limit == "all")
	$limit = pg_num_rows($result);

?>

<a name="top"></a>
<table width="100%" border=0 cellspacing=0 rules=none>

<tr style="color:white" bgcolor="#000099">
   <td align="left" ><b>IP</b></td>
   <td align="left" ><b>Name</b></td>
   <td align="right"><b>Total</b></td>
   <td align="right"><b>Sent</b></td>
   <td align="right"><b>Received</b></td>
   <td align="right"><b>tcp</b></td>
   <td align="right"><b>udp</b></td>
   <td align="right"><b>icmp</b></td>
   <td align="right"><b>http</b></td>
   <td align="right"><b>mail</b></td>
   <td align="right"><b>p2p</b></td>
   <td align="right"><b>ftp</b></td>
</tr>

<?php

//***************************************************************************
// Output Total Line
//***************************************************************************

$url = "<a href=\"#\" onclick=\"window.open('details.php?sensor_id=$sensor_id&amp;ip=$subnet','_blank','scrollbars=yes,width=930,height=768,resizable=yes,left=20,top=20')\">";

echo "<tr>\n";
echo "   <td>".$url."Total</a></td>\n";
echo "   <td>$subnet</td>\n";

foreach (array("total", "sent", "received", "tcp", "udp", "icmp", "http", "mail", "p2p", "ftp") as $key)
{
	for ($Counter=0, $Total = 0; $Counter < pg_num_rows($result); $Counter++)
   {
		$r = pg_fetch_array($result, $Counter);
		$Total += $r[$key];
   }

	echo fmtb($Total);
}

echo "</tr>\n";

//***************************************************************************
// Output Other Lines
//***************************************************************************

$i = 0;

for ($Counter=0; $Counter < pg_num_rows($result) && $Counter < $limit; $Counter++)
{
	$r = pg_fetch_array($result, $Counter);
   $url = "<a href=\"#\" onclick=\"window.open('details.php?interval=$interval&sensor_id=$sensor_id&from=$from&to=$to&ip=".$r['ip']."','_blank', 'scrollbars=yes,width=1130,height=768,resizable=yes,left=20,top=20')\">";

   if ($i++ % 2)
      echo "<tr>\n   <td>" . $url . $r['ip'] . "</a></td>\n   <td>" . gethostbyaddr($r['ip']) . "</td>\n";
   else
      echo "<tr bgcolor=\"#8EE1FF\">\n   <td>" . $url . $r['ip'] . "</a></td>\n   <td>" . gethostbyaddr($r['ip']) . "</td>\n";

	echo fmtb($r['total']).fmtb($r['sent']).fmtb($r['received']).
		fmtb($r['tcp']).fmtb($r['udp']).fmtb($r['icmp']).fmtb($r['http']).fmtb($r['mail']).
		fmtb($r['p2p']).fmtb($r['ftp'])."</tr>\n";
}

echo "</table>\n\n";

include('footer.php');
