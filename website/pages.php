<?php

///////////////////////////////////////////////////////////////////////////////
//                                                                           //
//   Простой онлайн блокнот "Wikipad" // Показ и редактирования страниц      //
//   ----------------------------------------------------------------------  //
//   Copyright (C) 1998-2022 Studio "Cherry-Design"                          //
//   URL: https://www.cherry-design.com/                                     //
//   E-mail: mike@cherry-design.com                                          //
//                                                                           //
///////////////////////////////////////////////////////////////////////////////

// Имя данного скрипта
$this_script = "pages.php";

// Производим инициализацию
require("includes/initialization.php"); 

// Если включен режим обязательной авторизации для просмотра сайта и
// пользователь не авторизован, то делаем редирект на первую страницу
if ($globals["hidden_flag"] && !$globals["user_entry_flag"]) {
    header("Location: ./");
}

// Проверяем не запрашивается ли запись из блога и делаем редирект
if ($globals["blog_flag"] && empty($action) && preg_match($globals["regexp_blog_id"], $id)) {
    header("Location: ".get_rewrite_link($id));
}

// Название странички, в случае, если она еще не существует
if (!empty($_REQUEST["title"])) {

    // Формируем таблицу соответствия &-подстановок спецсимволам
    $html_specialchars_table = array_flip(get_html_translation_table(HTML_SPECIALCHARS));

    // Преобразуем &-подстановки в заголовке в исходные спецсимволы
    $title = stripslashes(strtr($_REQUEST["title"], $html_specialchars_table));

} else {

    // Для новой записи блога, используем в названии текущую дату
    if ($globals["blog_flag"] && preg_match($globals["regexp_blog_id"], $id)) {
        $title = "Запись в блоге от ".date("d/m/Y");
    } else {
        $title = "Новая страница";
    }
}

// Имя файла, хранящего кэшированные данные записей блога
$globals["blog_cache_filename"] = "blog_records.dat";

///////////////////////////////////////////////////////////////////////////////
//                                                                           //
//                         Функция сохранения файла                          //
//                                                                           //
///////////////////////////////////////////////////////////////////////////////

function save_file($string, $filename) {

    global $globals;

    // Формируем временный файл для того, чтобы избежать ситуации
    // обнуления файла при одновременной работе нескольких скриптов
    $tempfile = tempnam($globals["path_temp"], "chr");

    // Сохраняем строку во временный файл
    $fp = fopen($tempfile,"w+");
    fwrite($fp, $string);
    fclose($fp);

    // Копируем временный файл на место нужного
    if (copy($tempfile, $filename)) {

        unlink($tempfile);
        return 1;
    
    } else {
        unlink($tempfile);
        return 0;
    }
}

///////////////////////////////////////////////////////////////////////////////
//                                                                           //
//                      Функция редактирования страницы                      //
//                                                                           //
///////////////////////////////////////////////////////////////////////////////

function edit_page($id, $record, $title) {

    global $this_script, $globals;

    // Буферизируем вывод
    ob_start();

    // Флаг отрисовки формы
    $form_flag = 1;

    // Читаем нужную страницу
    $text = @file($globals["path_pages"].$id.".txt");

    if (empty($text)) {

        // Если страничка ранее не была создана
        $text = "";

    } else {

        // Находим название страницы
        $title = implode("", array_slice($text,0,1));
        $title = trim(substr(trim($title), 1, -1));

        // Удаляем название из текста страницы
        $text = array_slice($text, 2);
        $text = implode("", $text);
        $text = addslashes($text);
    }

    if (empty($record)) {

        // Печатаем сообщение для пользователя
        echo "<p>Для создания или редактирования страницы, используется Wiki-разметка, описание которой приведено в разделе &quot;Помощь&quot;. Для удаления существующей страницы из системы, достаточно удалить содержимое страницы и нажать кнопку &quot;Сохранить&quot;.</p>\n";

        // Формируем значения переменных по умолчанию
        $record = array(
            "text"  => $text
        );

    } else {

        // Если текст отсутствует, то страничка, либо не существовала, 
        // либо ее необходимо удалить
        if (empty($record["text"])) {

            if (file_exists($globals["path_pages"].$id.".txt")) {

                // Удаляем файл
                @unlink($globals["path_pages"].$id.".txt");

                // Очищаем кэш блога после изменения записи
            	if ($globals["blog_flag"] && preg_match($globals["regexp_blog_id"], $id)) {
                    @unlink($globals["path_temp"]."/".$globals["blog_cache_filename"]);
                }

                // Печатаем сообщение для пользователя
                echo "<p>Страничка успешно удалена из системы.</p>\n";

            } else {

                // Печатаем сообщение для пользователя
                echo "<p>Страничка не была создана, потому что не было введено никакой информации. Вы можете <a href=\"".$this_script."?action=edit&amp;id=".$id."&amp;title=".urlencode($title)."\">вернуться к форме</a> для создания этой страницы или перейти на <a href=\"./\">первую страницу</a> сайта.</p>\n";
            }

            // Сбрасываем флаг отрисовки формы
            $form_flag = 0;

        } else {

            // Читаем текст из формы, удаляя из него экранирующие слеши
            $text = stripslashes($record["text"]);

            // Разбиваем текст на отдельные строки
            $strings = str_replace("\r\n", "\n", $text);
            $strings = explode ("\n", $strings);

            // Проверяем не указано ли в первой строке название страницы
            if (preg_match("/^= (.*) =$/iu", trim($strings[0]), $result) && empty($strings[1])) {

                // Корректируем название страницы
                $title = trim($result[1]);

                // Удаляем строки, содержащие название страницы
                $strings = array_slice($strings, 2);
            }

            // Формируем текст страницы, объединяя строки
            $text = implode ("\n", $strings);

            // Формируем содержимое файла, добавляя к тексту страницы заголовок
            $text = "= ".$title." =\n\n".$text;

            // Сохраняем файл в системе
            save_file($text, $globals["path_pages"].$id.".txt");

            // Очищаем кэш блога после изменения записи
            if ($globals["blog_flag"] && preg_match($globals["regexp_blog_id"], $id)) {
                @unlink($globals["path_temp"]."/".$globals["blog_cache_filename"]);
            }

            // Осуществляем редирект на страничку просмотра
            header("Location: ".get_rewrite_link($id));

            exit();
        }

        // Сбрасываем флаг отрисовки формы
        $form_flag = 0;
    }

    // Отрисовываем форму
    if ($form_flag) {
?>
<form action="<?php echo $this_script; ?>?action=edit&amp;id=<?php echo $id; ?>&amp;title=<?php echo urlencode($title); ?>" method="post" onsubmit="return confirm_delete('page');">
<script type="text/javascript">
<!--
// Печатаем панель инструментов
print_toolbar();
//-->
</script>
<p><textarea name="data[text]" id="f_text" cols="80" rows="20"><?php echo htmlspecialchars(stripslashes($record["text"])); ?></textarea></p>
<p><input type="submit" value=" Сохранить " /></p>
</form>
<?php
    }

    // Читаем буферизированный вывод в строку
    $content = ob_get_contents();
    ob_end_clean();

    // Формируем параметры страницы
    $globals["page"]["title"] = $title;
    $globals["page"]["content"] = $content;
}

///////////////////////////////////////////////////////////////////////////////
//                                                                           //
//                    Функция показа запрошенной страницы                    //
//                                                                           //
///////////////////////////////////////////////////////////////////////////////

function view_page($id) {

    global $globals;

    $keywords = "";
    $description = "";

    // Буферизируем вывод
    ob_start();

    // Рассчитываем имя файла в котором хранится текст
    $filename = $globals["path_pages"].$id.".txt";

    // Читаем нужную страницу
    $text = @file($filename);

    if (!empty($text)) {

        // Находим название страницы
        $title = implode("", array_slice($text, 0, 1));
        $title = trim(substr(trim($title), 1, -1));

        // Находим текст страницы
        $text = array_slice($text, 2);
        $text = implode("", $text);

        // Производим парсинг Wiki-разметки
        $meta = parse_meta($text);
        $text = parse_wiki($text);

        // Печатаем дату последнего изменения странички
        if ($globals["date_flag"]) {
            echo "<address>Последнее изменение: ".date("d/m/Y H:i:s", filemtime($filename))."</address>\n";
        }

        // Формируем ключевые слова и описание к страничке
        if (!empty($meta["keywords"])) {
            $keywords = $meta["keywords"];
        }
        if (!empty($meta["description"])) {
            $description = $meta["description"];
        }

        // Выводим содержимое страницы в браузер
        echo $text;

    } else {

        // Формируем название страницы
        $title = "Страница отсутствует";

        // Печатаем сообщение об отсутствии странички
        echo "<p>Запрашиваемая Вами страница пока еще не создана. Возможно, она только планируется и появится здесь через некоторое время. Попробуйте зайти на сайт несколько позже.</p>\n";
    }

    // Читаем буферизированный вывод в строку
    $content = ob_get_contents();
    ob_end_clean(); 

    // Формируем параметры страницы
    $globals["page"]["title"] = $title;
    $globals["page"]["content"] = $content;
    $globals["page"]["keywords"] = $keywords;
    $globals["page"]["description"] = $description;

}

///////////////////////////////////////////////////////////////////////////////

if (!empty($_REQUEST["data"])) {
    $data = $_REQUEST["data"];
} else {
    $data = "";
}

if ($action == "edit" && $globals["user_entry_flag"]) { // Редактируем выбранную страницу

    edit_page($id, $data, $title);
    print_page();

} else { // Печатаем запрошенную страницу

    view_page($id);
    print_page();
}

?>