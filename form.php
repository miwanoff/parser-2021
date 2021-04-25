<?php
if (isset($_POST['submitB'])) {
    $uploaddir = 'my_path/'; // загружаемые файлы будут сохраняться в эту директории
    $destination = $uploaddir . $_FILES['myfile']['name']; // имя файла остается неизменным
    if (move_uploaded_file($_FILES['myfile']['tmp_name'], $destination)) { //файл перемещается из временной папки в выбранную директорию для хранения
        print "Файл успешно загружен";
    } else {
        echo "Произошла ошибка при загрузке файла.
    Некоторая отладочная информация:";
        print_r($_FILES);
    }
}
?>

<!DOCTYPE HTML>
<html>

<head>
    <meta charset="utf-8">
    <title>Основы PHP</title>
</head>

<body>
    <form enctype="multipart/form-data" action="form.php" method="post">
        <input type="hidden" name="MAX_FILE_SIZE" value="30000" />
        Загрузить файл:
        <input type="file" name="myfile" />
        <input type="submit" name="submitB" value="Отправить файл" />
    </form>
</body>

</html>