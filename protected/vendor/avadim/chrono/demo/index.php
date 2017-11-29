<?php

include_once __DIR__ . '/../src/autoload.php';

use avadim\Chrono\Chrono;

$aIntervals = [
    '1 month',
    'P30D',
    'P1M',
];

$aBaseDate = [
    'now',
    '2017-01-01',
    '2017-02-01',
    '2016-02-01',
];

?><!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>avadim/Chrono</title>
    <style>
        td { padding: 10px; border: 1px solid #111;}
    </style>
</head>
<body>
<table>
    <tr>
        <th>Interval</th>
        <th>Base date</th>
        <th>Days</th>
        <th>Seconds</th>
    </tr>
<?php
foreach($aIntervals as $sInterval) {
    echo '<tr><td>', $sInterval, '</td>', '<td>default</td><td>', Chrono::totalDays($sInterval), '</td><td>', Chrono::totalSeconds($sInterval), '</td></tr>';
}
foreach($aBaseDate as $sDate) {
    echo '<tr><td>P1M</td><td>', $sDate, '</td><td>', Chrono::totalDays('P1M', $sDate),'</td><td>', Chrono::totalSeconds('P1M', $sDate), '</td></tr>';
}

echo '1984 year is ', Chrono::createFrom(1984), '<br>';
echo 'Year ago is ', Chrono::today()->subYears(1), '<br>';

echo '<h3>Sequence</h3>';
foreach (Chrono::createPeriod('now', '+1 week')->sequenceOfDays() as $oDate) {
    echo $oDate, '<br>';
}
?>
</table>
</body>
</html>

