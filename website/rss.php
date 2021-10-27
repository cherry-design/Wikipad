<?php

///////////////////////////////////////////////////////////////////////////////
//                                                                           //
//   Простой онлайн блокнот "Wikipad" // Трансляция RSS-каналов              //
//   ----------------------------------------------------------------------  //
//   Copyright (C) 1998-2021 Studio "Cherry-Design"                          //
//   URL: https://www.cherry-design.ru/                                      //
//   E-mail: mike@cherry-design.ru                                           //
//                                                                           //
///////////////////////////////////////////////////////////////////////////////

// Имя данного скрипта
$this_script = "rss.php"; 

// Производим инициализацию
require("includes/initialization.php"); 

// Если включен режим обязательной авторизации для просмотра сайта
// и пользователь не авторизован или не включен режим трансляции RSS-потока,
// то делаем редирект на первую страницу
if ($globals["hidden_flag"] && !$globals["user_entry_flag"] || !$globals["rss_flag"]) {
    header("Location: ./");
}

// Количестве записей, транслируемых в RSS-канале
$globals["num_items"] = 5;

// Флаг кэширования RSS-потока
$globals["cache_flag"] = 1;

// Актуальное время жизни RSS-потока в кэше в секундах
$globals["cache_time"] = 600;

// Имя файла, хранящего кэшированное значение RSS-потока
$globals["cache_filename"] = "rss_last.dat";

///////////////////////////////////////////////////////////////////////////////
//                                                                           //
//                 Функция получения данных для RSS-канала                   //
//                                                                           //
///////////////////////////////////////////////////////////////////////////////

function get_rss_data() {

    global $globals;

    // Рассчитываем полный путь к файлу, хранящему кэш RSS-канала
    $cache_filename = $globals["path_temp"]."/".$globals["cache_filename"];

    // Сначала пробуем прочитать RSS-канал из кэша
    if ($globals["cache_flag"] && file_exists($cache_filename)) {

        // Рассчитываем время в секундах прошедшее с последнего обновления кэша
        $cache_time = time() - filemtime($cache_filename);

        // Проверяем актуальность кэша
        if ($cache_time < $globals["cache_time"]) {

            // Читаем из кэша строку с описанием RSS-канала
            $rss_data_serial = @file_get_contents($cache_filename);

            // Преобразуем строку в массив с данными RSS-канала
            $rss_data = unserialize($rss_data_serial);

            // Возвращаем кэшированное значение RSS-канала
            return $rss_data;
        }
    }

    // Рассчитываем полный URL сайта
    $website_url = "http://".$_SERVER["HTTP_HOST"].substr($_SERVER["REQUEST_URI"], 0, strrpos($_SERVER["REQUEST_URI"], "/")+1);

    // Формируем общие параметры канала
    $rss_data = array(
        "title"       => $globals["website_title"],
        "link"        => $website_url,
        "description" => $globals["website_words"],
        "items"       => ""
    );

    // Загружаем в массив список всех доступных страничек
    $files = "";
    if ($dp = opendir($globals["path_pages"])) {
        while (false !== ($filename = readdir($dp))) {
            if (preg_match("/^[0-9a-z_-]+\.txt$/iu", $filename)) {

                // Определяем время последнего изменения файла
                $modification_time = filemtime($globals["path_pages"].$filename);
                $files[$filename] = $modification_time;
            }
        }
    }

    // Формируем описание новостей для RSS-канала
    if (!empty($files)) {

        // Сортируем странички по дате создания
        arsort($files);

        // Выбираем последние записи
        $files = array_slice($files, 0, $globals["num_items"]);

        reset($files);
        while (list($filename, $modification_time) = each($files)) {

            // Находим идентификатор страницы
            $id = substr($filename, 0, strrpos($filename, "."));

            // Читаем текст страницы
            $text = @file($globals["path_pages"].$filename);

            // Находим название страницы
            $title = implode("", array_slice($text,0,1));
            $title = trim(substr(trim($title), 1, -1));

            // Удаляем заголовок из текста страницы
            $text = array_slice($text, 2);
            $text = implode("", $text);

            // Удаляем из текста переносы строк
            $text = str_replace("\r\n", "\n", $text);
            $text = str_replace("\n", " ", $text);

            // Удаляем из текста html-теги
            $text = strip_tags($text);

            // Обрабатываем в тексте элементы wiki-разметки (удаляем мета-теги)
            $text = preg_replace("/\[\[meta:[^]]+\]\]/iu", " ", $text);

            // Обрабатываем в тексте элементы wiki-разметки (изображения и загружаемые файлы)
            $text = preg_replace("/\[\[(image|file):[a-z0-9_.-]+(\|(frame|left|center|right))*(\|([^]]+))?\]\]/iu", "\\5", $text);

            // Обрабатываем в тексте элементы wiki-разметки (заголовки, wiki-ссылки, обычные ссылки)
            $text = preg_replace("/==+([^=]+)==+/iu", "\\1", $text);
            $text = preg_replace("/\[\[[^|]+\|([^]|]+)\]\]/iu", "\\1", $text);
            $text = preg_replace("/\[[^ ]+ +([^]]+)\]/iu", "\\1", $text);

            // Рассчитываем адрес ссылки на текст страницы
            $link = get_rewrite_link($id, $website_url);

            // Определяем дату модификации
            $date = date("Y-m-d H:i:s", $modification_time);

            // Формируем описание новости, используя несколько первых предложений
            if (strlen($text) > 450) {
                $end_position = strpos($text, ". ", 450) + 1;
                if ($end_position == 1) {                    
                    $description = substr($text, 0, 450)."...";
                } else {
                    $description = substr($text, 0, $end_position);
                }
            } else {
                $description = $text;
            }

            // Добавляем информацию в описание новости
            $items[$id] = array(
                "id"          => $id,
                "title"       => $title,
                "link"        => $link,
                "description" => $description,
                "pubDate"     => $date
            );
        }
        closedir($dp);

        // Добавляем описание новостей
        if (!empty($items)) {
            $rss_data["items"] = $items;
        }

        // Сохраняем RSS-канал в кэше
        if ($globals["cache_flag"] && !empty($rss_data)) {

            // Преобразуем массив в строку для сохранения в кэше
            $rss_data_serial = serialize($rss_data);

            // Сохраняем строку с данными RSS-канала в файле
            $fp = fopen($cache_filename,"w+");
            fwrite($fp, $rss_data_serial);
            fclose($fp);
        }
    }

    // Возвращаем рассчитанное значение RSS-канала
    return $rss_data;
}

///////////////////////////////////////////////////////////////////////////////
//                                                                           //
//                         Функция печати RSS-канала                         //
//                                                                           //
///////////////////////////////////////////////////////////////////////////////

function print_rss($channel) {

    // Формируем стандартный заголовок RSS-потока 
    $string  = "<?xml version=\"1.0\" encoding=\"utf-8\"?>\n";
    $string .= "<rss version=\"2.0\" xmlns:content=\"http://purl.org/rss/1.0/modules/content/\">\n";
    $string .= "<channel>\n";

    // Формируем общие параметры сайта
    $string .= "<title>".htmlspecialchars($channel["title"])."</title>\n";
    $string .= "<link>".$channel["link"]."</link>\n";
    $string .= "<description>".htmlspecialchars($channel["description"])."</description>\n";
    $string .= "<language>ru</language>\n";
    $string .= "<generator>Cherry-Design RSS-builder</generator>\n\n";

    // Формируем новости RSS-потока
    if (!empty($channel["items"])) {

        reset($channel["items"]);
        while (list($id, $item) = each($channel["items"])) {

            // Рассчитываем дату новости в формате RFC 2822
            $date = date("r", strtotime($item["pubDate"]));

            // Формируем параметры новости
            $string .= "<item>\n";
            $string .= "<title>".htmlspecialchars($item["title"])."</title>\n";
            $string .= "<link>".$item["link"]."</link>\n";
            $string .= "<description>".htmlspecialchars($item["description"])."</description>\n";
            $string .= "<pubDate>".$date."</pubDate>\n";
            $string .= "</item>\n\n";
        }
    }

    $string .= "</channel>\n";
    $string .= "</rss>";

    // Посылаем заголовок, определяющий, что далее пойдут XML-данные
    header("Content-type: text/xml");

    // Печатаем RSS-канал
    echo $string;
}

///////////////////////////////////////////////////////////////////////////////

// Осуществляем трансляцию RSS-канала
$rss = get_rss_data();
print_rss($rss);

?>