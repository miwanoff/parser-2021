<?php
echo date("l d M Y h:i:s A") . "\n";
echo date("d.m.Y") . "\n";

$nextWeek = time() + (7 * 24 * 60 * 60);
echo date("l d F Y h:i:s A", $nextWeek) . "\n";

$loc = setlocale(LC_ALL, '') . "\n";
echo "На этой системе локаль по умолчанию: $loc" . "\n";
$loc_de = setlocale(LC_ALL, 'de_DE', 'de', 'ge');
echo "На этой системе немецкая локаль имеет имя: $loc_de" . "\n";
echo strftime("%A %d %B %Y %X");
$loc_ua = setlocale(LC_ALL, 'Ukrainian_Ukraine', 'Ukrainian_Ukraine', 'Ukrainian_Ukraine');
echo "На этой системе немецкая локаль имеет имя: $loc_ua" . "\n";

echo iconv("windows-1251", "utf-8", strftime("%A %d %B %Y %X", strtotime("12/23/2018")));