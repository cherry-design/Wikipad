<?php

///////////////////////////////////////////////////////////////////////////////
//                                                                           //
//   Простой онлайн блокнот "Wikipad" // Работа с файлами                    //
//   ----------------------------------------------------------------------  //
//   Copyright (C) 1998-2021 Studio "Cherry-Design"                          //
//   URL: https://www.cherry-design.ru/                                      //
//   E-mail: mike@cherry-design.ru                                           //
//                                                                           //
///////////////////////////////////////////////////////////////////////////////

// Имя данного скрипта
$this_script = "files.php"; 

// Производим инициализацию
require("includes/initialization.php"); 

// Если включен режим обязательной авторизации для просмотра сайта и
// пользователь не авторизован, то делаем редирект на первую страницу
if ($globals["hidden_flag"] && !$globals["user_entry_flag"]) {
    header("Location: ./");
}

// Имя файла, в случае, если он еще не загружен
if (!empty($_REQUEST["filename"])) {
    $filename = $_REQUEST["filename"];
} else {
    $filename = "";
}

///////////////////////////////////////////////////////////////////////////////
//                                                                           //
//                      Функция загрузки файла в систему                     //
//                                                                           //
///////////////////////////////////////////////////////////////////////////////

function upload_file($record, $upload_filename) {

    global $this_script, $globals;

    // Определяем с какой странички пришел пользователь
    $referer = get_referer_link();

    // Буферизируем вывод
    ob_start();

    // Флаг отрисовки формы
    $form_flag = 1;

    // Делаем предварительную обработку загруженного файла
    if (!empty($_FILES["upload"]["name"]["file"])) {

        $record["file"] = array(
            "name"     => $_FILES["upload"]["name"]["file"],
            "type"     => $_FILES["upload"]["type"]["file"],
            "size"     => $_FILES["upload"]["size"]["file"],
            "tmp_name" => $_FILES["upload"]["tmp_name"]["file"],
            "error"    => $_FILES["upload"]["error"]["file"]
        );

    } else {
        $record["file"] = "";
    }

    // Печатаем форму загрузки файла
    if (empty($record["file"])) {

        // Если не указано имя нового файла, то формируем параметры по умолчанию
        if (empty($record["filename"])) {

            // Формируем сообщение для пользователя
            echo "<p>Выберите файл для загрузки в систему и укажите желаемое имя файла, под которым оно будет доступно на сайте. Если имя файла не указано, то оно будет получено путем преобразования исходного имени. Если Вы желаете удалить ранее загруженный файл, то просто напишите его имя, не загружая никакого файла.</p>\n";

            // Формируем параметры записи по умолчанию
            $record = array(
                "filename"  => $upload_filename,
                "referer"   => $referer
            );

        // Если имя нового файла указано, но сам файл не загружен, то удаляем файл
        } else {

            if (file_exists($globals["path_files"].$record["filename"])) {

                // Удаляем файл
                @unlink($globals["path_files"].$record["filename"]);

                // Печатаем сообщение для пользователя
                echo "<p>Ранее загруженный файл успешно удален из системы.</p>\n";

            } else {

                // Печатаем сообщение для пользователя
                echo "<p>Указанный файл отсутствует в системе и, соответственно, не может быть удален.</p>\n";
            }

            // Сбрасываем флаг отрисовки формы
            $form_flag = 0;
        }

    // Загружаем файл в систему
    } else {

        // Рассчитываем расширение исходного файла
        $extension = substr(strrchr($record["file"]["name"], "."), 1);

        // Рассчитываем новое имя файла
        $name = substr($record["file"]["name"], 0, strrpos($record["file"]["name"], "."));
        if (!empty($record["filename"])) {

            // Удаляем расширение из нового имени файла
            if (strrchr($record["filename"], ".")) {
                $name = substr($record["filename"], 0, strrpos($record["filename"], "."));
            } else {
                $name = $record["filename"];
            }
        }
        $name = calculate_ascii_string($name);
        $filename = $name.".".$extension;

        // Копируем загруженный файл в систему
        $result = move_uploaded_file($record["file"]["tmp_name"], $globals["path_files"].$filename);

        // Печатаем сообщение для пользователя
        if ($result) {
            echo "<p>Файл успешно загружен в систему под именем &quot;".$filename."&quot;.<br />Вы можете перейти к  <a href=\"".$this_script."?filename=".$filename."\">просмотру</a> загруженного файла, <a href=\"".$this_script."?action=upload\">загрузить</a> в систему еще один файл или <a href=\"".$record["referer"]."\">вернуться</a> к просмотру текущей редактируемой странички.</p>\n";
        } else {
            echo "<p>Не удалось загрузить файл в систему. Попробуйте чуть позже.</p>\n";
        }

        // Сбрасываем флаг отрисовки формы
        $form_flag = 0;
    }

    // Отрисовываем форму
    if ($form_flag) {
?>
<form action="<?php echo $this_script; ?>?action=upload" method="post" enctype="multipart/form-data" onsubmit="return confirm_delete('file')";>
<input type="hidden" name="upload[referer]" value="<?php echo htmlspecialchars(stripslashes($record["referer"])); ?>" />
<dl>
<dt><label>Файл для загрузки</label></dt>
<dd><input type="file" size="30" name="upload[file]" id="f_file" value="" /></dd> 
<dt><label>Новое имя файла*</label></dt>
<dd><input type="text" size="42" name="upload[filename]" id="f_filename" value="<?php echo htmlspecialchars(stripslashes($record["filename"])); ?>" /></dd>
</dl>
<p class="button"><input type="submit" value=" Загрузить " /></p>
</form>

<p><em>* При указании нового имени файла, будет использоваться расширение исходного файла.</em></p>
<?php
    }

    // Читаем буферизированный вывод в строку
    $content = ob_get_contents();
    ob_end_clean();

    // Формируем параметры страницы
    $globals["page"]["title"] = "Загрузка файла";
    $globals["page"]["content"] = $content;
}

///////////////////////////////////////////////////////////////////////////////
//                                                                           //
//                   Функция показа запрошенного файла                       //
//                                                                           //
///////////////////////////////////////////////////////////////////////////////

function view_file($filename) {

    global $this_script, $globals;

    // Буферизируем вывод
    ob_start();

    // Проверяем, что такой файл существует
    if (!empty($filename) && file_exists($globals["path_files"].$filename)) {

        // Печатаем дату последнего изменения файла
        if ($globals["date_flag"]) {
            echo "<address>Последнее изменение: ".date("d/m/Y H:i:s", filemtime($globals["path_files"].$filename))."</address>\n";
        }

        // Рассчитываем расширение файла
        $extension = substr(strrchr($filename, "."), 1);

        // Выводим изображение в браузер
        if ($extension == "gif" || $extension == "jpg" || $extension == "png" || $extension == "swf") {

            // Формируем название странички
            $title = "Изображение: ".$filename;

            // Находим размеры исходного изображения
            $image_src = $globals["path_files"].$filename;
            $image_size = getimagesize($image_src); 
            $image_width = $image_size[0];
            $image_height = $image_size[1];

            // Печатаем изображение
            if ($extension == "swf") {
                echo "<p><object type=\"application/x-shockwave-flash\" style=\"width:".$image_width."px; height:".$image_height."px;\" data=\"".$image_src."\"><param name=\"movie\" value=\"".$image_src."\" /></object></p>\n";
            } else {
                echo "<p><img src=\"".$image_src."\" style=\"width:".$image_width."px; height:".$image_height."px;\" alt=\"".htmlspecialchars("Изображение: ".$filename)."\" /></p>\n";
            }

            // Печатаем информацию о вставке изображения на страничку
            if ($globals["user_entry_flag"]) {
                echo "<p>Вы можете вставить данное изображение на любую страницу, используя следующий синтаксис:</p>\n";
                echo "<pre>\n";
                echo "[[Image:".$filename."|right|frame|Описание изображения]]\n";
                echo "</pre>\n";
            }

        // Печатаем ссылку на загрузку файла
        } else {

            // Формируем название странички
            $title = "Файл: ".$filename;

            // Печатаем информацию для пользователя
            echo "<p>Ниже представлена ссылка на ранее загруженный файл. Вы можете его скачать, в случае необходимости.</p>\n";

            // Рассчитываем размер файла
            $filesize = filesize($globals["path_files"].$filename);
            if ($filesize > 1024) {
                $filesize = round(($filesize / 1024), 2)." Кб";
            }elseif ($filesize > 1048576) {
                $filesize = round(($filesize / 1048576), 2)." Mб";
            } else {
                $filesize .= " байт";
            }

            // Печатаем ссылку на файл
            echo "<ul>\n";
            echo "<li><a href=\"".$globals["path_files"].$filename."\">Скачать файл &quot;".htmlspecialchars($filename)."&quot;</a> (".$filesize.")</li>\n";
            echo "</ul>\n";

            // Печатаем информацию о вставке файла на страничку
            if ($globals["user_entry_flag"]) {
                echo "<p>Вы можете поставить на любой странице ссылку на скачивание данного файла, используя следующий синтаксис:</p>\n";
                echo "<pre>\n";
                echo "[[File:".$filename."|Текст ссылки на странице]]\n";
                echo "</pre>\n";
            }
        }

    } else {

        // Формируем название страницы
        $title = "Файл отсутствует";

        // Печатаем сообщение об отсутствии файла
        echo "<p>Запрашиваемый Вами файл пока еще не загружен. Возможно, это только планируется и файл появится здесь через некоторое время. Попробуйте зайти на сайт несколько позже.</p>\n";
    }

    // Читаем буферизированный вывод в строку
    $content = ob_get_contents();
    ob_end_clean();

    // Формируем параметры страницы
    $globals["page"]["title"] = $title;
    $globals["page"]["content"] = $content;
}

///////////////////////////////////////////////////////////////////////////////

if (!empty($_REQUEST["upload"])) {
    $data = $_REQUEST["upload"];
} else {
    $data = "";
}

if ($action == "upload" && $globals["user_entry_flag"]) { // Загружаем файл в систему

    upload_file($data, $filename);
    print_page();

} else { // Выводим файл для просмотра

    view_file($filename);
    print_page();

}

?>