<?php
$file = fopen("poem.txt", "r");

$arr = [];
$arr = file("poem.txt");
//print_r($arr);

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

$p = fopen("poem.html", "w");
fwrite($p, $str_b);
fwrite($p, "\n");
fwrite($p, "<h1>" . $arr[0] . "</h1>");

for ($i = 1; $i < count($arr); $i++) {
    fwrite($p, $arr[$i]);
}
//echo realpath("file.txt");
$name = "my_path";
if (!is_dir($name)) {
    mkdir($name); // создание каталога по указанному пути
    echo realpath($name); // реальный путь к созданному каталогу
}
//mkdir("../../my_path11");
// echo getcwd(); // текущий каталог
// chdir($name); // переход на существующий каталог cvs, находящийся внутри текущего
// echo getcwd(); //теперь новый текущий каталог - cvs
// $dir = '..';
// $files1 = scandir($dir, 1);
// print_r($files1);
//echo count(glob('*'));
function rscandir($base = '', &$data = array()) {
    $array = array_diff(scandir($base), array('.', '..'));
    foreach ($array as $value) {
        if (is_dir($base.$value)) {
            $data[] = $base.$value . '/';
            $data = rscandir($base.$value . '/', $data);
        } elseif (is_file($base.$value)) {
            $data[] = $base.$value;
        }
    }
    return $data;
}
print_r(rscandir(dirname(__FILE__) . '/'));