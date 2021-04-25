<?php
header("Content-Type: text/html; charset=utf-8");
require 'simplehtmldom/simple_html_dom.php';
$cinemas = [];
$cinemas_title = [];
$images = [];
$cinem = file_get_html("https://mykharkov.info/catalog/kinoteatry/");
//print_r($cinemas);
echo count($cinem->find('.title a'));
if (count($cinem->find('.title a')) > 0) {
    foreach ($cinem->find('.title a') as $a) {
        $cinemas[] = $a->href;
        $cinemas_title[] = $a->innertext;
    }
}

if (count($cinem->find('.image img')) > 0) {
    foreach ($cinem->find('.image img') as $img) {
        $images[] = $img->getAttribute('data-src');
    }
}

print_r($cinemas);
print_r($cinemas_title);
print_r($images);

$description = [];
foreach ($cinemas as $cinema) {
    $data = file_get_html("$cinema");
    // echo $cinema . "\n";
    //echo count($data->find('.news-datails-text p'));
    if (count($data->find('.news-datails-text p')) > 0) {
        $temp = $data->find('.news-datails-text p', 0)->innertext;
        $description[] = preg_replace('/<img\s+[^>]*src="([^"]*)"[^>]*>/', '', $temp);

    }

}

print_r($description);

$str_b = '<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Document</title>
</head>
<body>';

$str_e = '</body>
</html>';

$name = "my_path";
if (!is_dir($name)) {
    mkdir($name); // создание каталога по указанному пути
    echo realpath($name); // реальный путь к созданному каталогу
}

$p = fopen("$name/cinemas.html", "w");
fwrite($p, $str_b);
fwrite($p, "\n");
fwrite($p, "<h1>Кинотеатры</h1>");

for ($i = 1; $i < count($cinemas); $i++) {
    fwrite($p, "\n<h3><a href=\"$cinemas[$i]\"> $cinemas_title[$i]</a></h3>");
    fwrite($p, "<p>$description[$i]</p>");
    fwrite($p, "<img src=\"\">");
}

fwrite($p, $str_e);
fclose($p);