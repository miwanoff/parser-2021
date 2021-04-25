<?php
// $h = fopen("file.txt", "a+"); // Будет открыт файл нулевой длины "my_file.html" для записи.
// $text = "Hello";
// if (fwrite($h, $text)) {
//     echo "Запись прошла успешно";
// } else {
//     echo "Произошла ошибка при записи данных";
// }

// $file = fopen("file.txt", "r");
// $content = fread($file, filesize("file.txt")); //читает 10 байтов из файла
// echo $content;
// fclose($file);
// $fp = fopen('data.txt', 'w');
// fwrite($fp, 'a');
// fwrite($fp, 'bc'); // содержимое 'data.txt' - abc
// fclose($fp);
// $file = fopen("file.txt", "r");
// //Выводит строки файла до тех пор, пока не будет достигнут конец файла
// while (!feof($file)) {
//     echo fgets($file);
// }

// $list = array(
//     array('aaa', 'bbb', 'ccc', 'dddd'),
//     array('123', '456', '789'),
//     array('aaa', 'bbb'),
// );

// $fp = fopen('file.csv', 'w');

// foreach ($list as $fields) {
//     fputcsv($fp, $fields);
// }
// fclose($fp);

// $row = 1;
// if (($handle = fopen("file.csv", "r")) !== false) {
//     while (($data = fgetcsv($handle, 1000)) !== false) {
//         $num = count($data); // количество полей в строке
//         for ($c = 0; $c < $num; $c++) {
//             echo $data[$c] . "\n";
//         }
//     }
//     fclose($handle);
// }

// $arr = file("file.txt");
// print_r($arr);
// foreach ($arr as $i => $a) {
//     echo $i, ": ", $a;
// }

//$n = readfile("my_file.html");
// if (!$n) {
//     echo "Ошибка чтения из файла";
// }
// // если ошибка была, то выводим сообщение
// else {
//     //echo $n;
// }
// если ошибки не было, то выводим число считанных символов

$homepage = file_get_contents('http://www.example.com/');
echo $homepage;