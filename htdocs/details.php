<?php
include("include.php");
include("header.php");

if (isset($_GET['sensor_id']) && is_numeric($_GET['sensor_id']))
   $sensor_id = $_GET['sensor_id'];

if (isset($_GET['interval']) && is_numeric($_GET['interval']))
   $interval = $_GET['interval'];

if (isset($_GET['ip']))
   $ip = pg_escape_string($_GET['ip']);

if (isset($_GET['from']))
   $from = $_GET['from'];

if (isset($_GET['to']))
   $to = $_GET['to'];

echo "<h3>";

if (strpos($ip, "/") === FALSE)
	echo "$ip - " . gethostbyaddr($ip);
else
	echo "Total - $ip";

echo "</h3>\n";

$db = ConnectDb();

if ($ip == pg_escape_string("0.0.0.0/0"))
{
   $rxtable = "bd_rx_total_log";
	$txtable = "bd_tx_total_log";
}
else
{
   $rxtable = "bd_rx_log";
	$txtable = "bd_tx_log";
}

$sql = "select rx.scale as rxscale, tx.scale as txscale, tx.total+rx.total as total, tx.total as sent,
               rx.total as received, tx.tcp+rx.tcp as tcp, tx.udp+rx.udp as udp,
               tx.icmp+rx.icmp as icmp, tx.http+rx.http as http,
               tx.mail+rx.mail as mail,
               tx.p2p+rx.p2p as p2p, tx.ftp+rx.ftp as ftp
        from
               (SELECT ip, max(total/sample_duration)*8 as scale, sum(total) as total, sum(tcp) as tcp, sum(udp) as udp, sum(icmp) as icmp,
                           sum(mail) as mail,
                           sum(http) as http, sum(p2p) as p2p, sum(ftp) as ftp
                from sensors, $txtable
                where sensors.sensor_id = '$sensor_id'
                      and sensors.sensor_id = ".$txtable.".sensor_id
                      and ip <<= '$ip'
                      and timestamp > $from::abstime and timestamp < $to::abstime
                group by ip) as tx,

                (SELECT ip, max(total/sample_duration)*8 as scale, sum(total) as total, sum(tcp) as tcp, sum(udp) as udp, sum(icmp) as icmp,
                            sum(mail) as mail,
                            sum(http) as http, sum(p2p) as p2p, sum(ftp) as ftp
                 from  sensors, $rxtable
                 where sensors.sensor_id = '$sensor_id'
                       and sensors.sensor_id = ".$rxtable.".sensor_id
                       and ip <<= '$ip'
                       and timestamp > $from::abstime and timestamp < $to::abstime
                 group by ip) as rx
         where tx.ip = rx.ip;";

// echo "<pre>$sql</pre>";

$starttime = microtime(true);
$result = pg_query($sql);

echo "<div>\n";
echo "  <table width=\"100%\" border=0 cellspacing=0 rules=none>";
echo "   <tr style=\"color:white\" bgcolor=\"#000099\">";
echo "      <td align=\"left\" ><b>IP</b></td>";
echo "      <td align=\"left\" ><b>Name</b></td>";
echo "      <td align=\"right\"><b>Total</b></td>";
echo "      <td align=\"right\"><b>Sent</b></td>";
echo "      <td align=\"right\"><b>Received</b></td>";
echo "      <td align=\"right\"><b>tcp</b></td>";
echo "      <td align=\"right\"><b>udp</b></td>";
echo "      <td align=\"right\"><b>icmp</b></td>";
echo "      <td align=\"right\"><b>http</b></td>";
echo "      <td align=\"right\"><b>mail</b></td>";
echo "      <td align=\"right\"><b>p2p</b></td>";
echo "      <td align=\"right\"><b>ftp</b></td>";
echo "   </tr>";

$r = pg_fetch_array($result);

echo "   <tr bgcolor=\"#8EE1FF\">\n";
echo "      <td>$ip</td>\n";
echo "      <td>".gethostbyaddr($ip)."</td>\n";

echo "   ".fmtb($r['total']).
     "   ".fmtb($r['sent']).
     "   ".fmtb($r['received']).
     "   ".fmtb($r['tcp']).
     "   ".fmtb($r['udp']).
     "   ".fmtb($r['icmp']).
     "   ".fmtb($r['http']).
     "   ".fmtb($r['mail']).
     "   ".fmtb($r['p2p']).
     "   ".fmtb($r['ftp']);

echo "   </tr>\n";
echo "  </table>\n\n";
echo "</div>\n";

echo "<div>\n";
echo "  <div>Send:</div>\n";
echo "  <img src='graph.php?interval=" . $interval . "&ip=" . $ip . "&sensor_id=" . $sensor_id . "&from=" . $from . "&to=" . $to . "&table=" . $txtable . "&yscale=" . $r['txscale'] . "'>\n";
echo "</div>\n";

echo "<div>\n";
echo "  <div>Receive:</div>\n";
echo "  <img src='graph.php?interval=" . $interval . "&ip=" . $ip . "&sensor_id=" . $sensor_id . "&from=" . $from . "&to=" . $to . "&table=" . $rxtable . "&yscale=" . $r['rxscale'] . "'>\n";
echo "</div>\n";

echo "<img src=\"legend.gif\">\n";

include('footer.php');
?>
