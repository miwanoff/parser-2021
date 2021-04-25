<?php
$phone = array(001, 949, 555, 0112);
$save = serialize($phone); // сериализируем $phone
print_r($save); // выводим сериализованное представление a:4:{i:0;i:1;i:1;i:949;i:2;i:555;i:3;i:74;}
echo "\n";
$phone = "111" . "\n"; // изменяем $phone
print_r($phone); // выводим 111
$phone = unserialize($save); // восстанавливаем $phone
print_r($phone); // выводим восстановленный массив