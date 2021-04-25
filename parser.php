<?php
header("Content-Type: text/html; charset=utf-8");
require 'simplehtmldom/simple_html_dom.php';
$cinemas = [];
$cinemas_title = [];
$cinem = file_get_html("https://mykharkov.info/catalog/kinoteatry/");
//print_r($cinemas);
echo count($cinem->find('.title a'));
if (count($cinem->find('.title a')) > 0) {
    foreach ($cinem->find('.title a') as $a) {
        $cinemas[] = $a->href;
        $cinemas_title[] = $a->innertext;
    }
}

print_r($cinemas);
print_r($cinemas_title);

$description = [];
foreach ($cinemas as $cinema) {
    $data = file_get_html("$cinema");
    // echo $cinema . "\n";
    //echo count($data->find('.news-datails-text p'));
    if (count($data->find('.news-datails-text p')) > 0) {
        $temp = $data->find('.news-datails-text p', 0)->innertext;
        $description[] = $temp;

    }

}

print_r($description);