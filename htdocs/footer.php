<?php

if (isset($starttime))
{
   $endtime = microtime(true);
   $duration = ((int)(($endtime-$starttime) * 1000)) / 1000.000;
   echo "<br><p>Page load completed in " . $duration . " seconds</p>";
}

echo "</body>";
echo "</html>";

?>
