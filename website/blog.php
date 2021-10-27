<?php

///////////////////////////////////////////////////////////////////////////////
//                                                                           //
//   Простой онлайн блокнот "Wikipad" // Блог                                //
//   ----------------------------------------------------------------------  //
//   Copyright (C) 1998-2021 Studio "Cherry-Design"                          //
//   URL: https://www.cherry-design.ru/                                      //
//   E-mail: mike@cherry-design.ru                                           //
//                                                                           //
///////////////////////////////////////////////////////////////////////////////

// Имя данного скрипта
$this_script = "blog.php";

// Производим инициализацию
require("includes/initialization.php");

// Если включен режим обязательной авторизации для просмотра сайта и пользователь
// не авторизован или не включен режим ведения блога, то делаем редирект на первую страницу
if ($globals["hidden_flag"] && !$globals["user_entry_flag"] || !$globals["blog_flag"]) {
    header("Location: ./");
}

// Проверяем не запрашивается ли обычная страничка и делаем редирект
if ($globals["blog_flag"] && $id != "index" && !preg_match($globals["regexp_blog_id"], $id)) {
    header("Location: ".get_rewrite_link($id));
}

// Количество записей на страницу в блоге
$globals["records_per_page"] = 10;

// Флаг кэширования блога
$globals["cache_flag"] = 1;

// Актуальное время жизни блога в кэше в секундах
$globals["cache_time"] = 600;

// Имя файла, хранящего кэшированные данные записей блога
$globals["cache_filename"] = "blog_records.dat";

///////////////////////////////////////////////////////////////////////////////
//                                                                           //
//             Функция получения данных для показа записей в блоге           //
//                                                                           //
///////////////////////////////////////////////////////////////////////////////

function get_blog_data() {

    global $globals;

    // Рассчитываем полный путь к файлу, хранящему кэш записей блога
    $cache_filename = $globals["path_temp"]."/".$globals["cache_filename"];

    // Сначала пробуем прочитать записи блога из кэша
    if ($globals["cache_flag"] && file_exists($cache_filename)) {

        // Рассчитываем время в секундах прошедшее с последнего обновления кэша
        $cache_time = time() - filemtime($cache_filename);

        // Проверяем актуальность кэша
        if ($cache_time < $globals["cache_time"]) {

            // Читаем из кэша строку с записями блога
            $blog_data_serial = @file_get_contents($cache_filename);

            // Преобразуем строку в массив с записями блога
            $blog_data = unserialize($blog_data_serial);

            // Возвращаем кэшированное значение записей блога
            return $blog_data;
        }
    }

    // Читаем список файлов, содержащий записи блога
    $files = "";
    if ($dp = opendir($globals["path_pages"])) {
        while (false !== ($filename = readdir($dp))) {
            if (preg_match("/^[0-9a-z_-]+\.txt$/iu", $filename)) {

                // Находим идентификатор записи из имени файла
                $id = substr($filename, 0, strrpos($filename, "."));

                // Обрабатываем только текстовые файлы содержащие записи блога
                if (preg_match($globals["regexp_blog_id"], $id)) {

                    // Извлекаем составляющие даты
                    $day   = substr($id,0,2);
                    $month = substr($id,3,2);
                    $year  = substr($id,6,4);

                    // Формируем дату создания записи в блоге для последующей сортировки
                    $creation_date =  $year."-".$month."-".$day;
                    $files[$filename] = $creation_date;
                }
            }
        }
        closedir($dp);
    }

    // Начинаем формировать записи блога
    $blog_data = "";

    if (!empty($files)) {

        // Сортируем записи блога по дате создания
        arsort($files);

        // Обрабатываем список файлов, формируя записи в блоге
        reset($files);
        while (list($filename, $creation_date) = each($files)) {

            // Находим идентификатор записи из имени файла
            $id = substr($filename, 0, strrpos($filename, "."));

            // Извлекаем составляющие даты для проверки
            $day   = substr($id,0,2);
            $month = substr($id,3,2);
            $year  = substr($id,6,4);

            // Некорректные даты не включаем в список
            if ($month > 12 || $day > 31) {
                continue;
            }

            // Формируем дату для печати в блоге
            $date = str_replace("-", "/", $id);

            // Читаем текст странички блога
            $text = @file($globals["path_pages"].$filename);

            // Находим название странички блога
            $title = implode("", array_slice($text,0,1));
            $title = trim(substr(trim($title), 1, -1));

            // Удаляем заголовок из текста заметки блога
            $text = array_slice($text, 2);

            // Читаем список тегов
            $meta = parse_meta(implode("", $text));
            if (!empty($meta["tags"])) {
                $tags = $meta["tags"];
            } else {
                $tags = "";
            }

            // Рассчитываем общее количество символов в тексте заметки
            $length = strlen(implode("", $text));

            // Отслеживаем горизонтальную черту и скрываем все, что идет после нее
            $temp = "";
            reset($text);
            while (list($num, $string) = each($text)) {
                if (substr($string, 0, 4) == "----") {
                    break;
                }
                $temp[] = $string;
            }
            $text = $temp;
            unset($temp);

            // Формируем содержание записи блога
            $text = implode("", $text);

            // Добавляем запись блога в общий массив
            $blog_data[$id] = array(
                "id"     => $id,
                "date"   => $date,
                "title"  => $title,
                "text"   => $text,
                "tags"   => $tags,
                "length" => $length
            );
        }

        // Сохраняем записи блога в кэше
        if ($globals["cache_flag"] && !empty($blog_data)) {

            // Преобразуем массив в строку для сохранения в кэше
            $blog_data_serial = serialize($blog_data);

            // Сохраняем строку с записями блога в файле
            $fp = fopen($cache_filename,"w+");
            fwrite($fp, $blog_data_serial);
            fclose($fp);
        }
    }

    // Возвращаем массив с записями блога
    return $blog_data;
}

///////////////////////////////////////////////////////////////////////////////
//                                                                           //
//                 Функция формирования списка тегов из строки               //
//                                                                           //
///////////////////////////////////////////////////////////////////////////////

function get_tags($string) {

    $tags = array();
    $tags_sort = array();
    $tags_title = array();
    $tags_frequency = array();

    // Разбиваем строку на отдельные теги
    $tags_array = explode(",", $string);

    // Формируем список тегов с идентификаторами
    reset($tags_array);
    while (list($id, $title) = each($tags_array)) {

        // Формируем название и идентификатор тега
        $tag_title = trim($title);
        $tag_id = calculate_ascii_string($tag_title);

        // Добавляем только уникальные теги
        if (!isset($tags_title[$tag_id])) {
            $tags_sort[$tag_id] = strtolower($tag_title);
            $tags_title[$tag_id] = $tag_title;
            $tags_frequency[$tag_id] = 1;
        } else {
            $tags_frequency[$tag_id]++;
        }
    }

    // Сортируем список тегов
    if (!empty($tags_sort)) {

        // Сортируем список тегов по алфавиту без учета регистра
        asort($tags_sort);

        // Формируем обобщенный массив тегов с учетом популярности
        reset($tags_sort);
        while (list($tag_id, $tag_title) = each($tags_sort)) {
            $tags[$tag_id]["title"] = $tags_title[$tag_id];
            $tags[$tag_id]["frequency"] = $tags_frequency[$tag_id];
        }
    }

    return $tags;
}

///////////////////////////////////////////////////////////////////////////////
//                                                                           //
//                 Функция формирования строки тегов к заметке               //
//                                                                           //
///////////////////////////////////////////////////////////////////////////////

function get_tags_string($tags_string) {

    global $globals, $this_script;

    // Формируем список тегов
    $tags = get_tags($tags_string);

	// Формируем строку со ссылками
	$string = "<p class=\"tags\">Теги: ";
	reset($tags);
	while (list($tag_id, $tag) = each($tags)) {
        $string .= "<a href=\"".$this_script."?tag=".$tag_id."\">".htmlspecialchars($tag["title"])."</a>, ";
	}
	$string  = substr($string, 0, -2);
	$string .= "</p>\n";

    return $string;
}

//////////////////////////////////////////////////////////////////////////////
//                                                                           //
//               Функция показа последних записей в блоге                    //
//                                                                           //
///////////////////////////////////////////////////////////////////////////////

function view_blog($tag="") {

    global $globals, $this_script, $page;

    // Читаем записи блога
    $records = get_blog_data();

    // Буферизируем вывод
    ob_start();

    if (!empty($records)) {

        // Если задан тег, то выбираем только относящиеся к нему записи
        if (!empty($tag)) {
            $records_tag = "";
            reset($records);
            while(list($id, $record) = each($records)) {
                if (!empty($record["tags"])) {
                    $tags_array = get_tags($record["tags"]);
                    if (isset($tags_array[$tag])) {
                        $records_tag[$id] = $record;
                    }
                }
            }
            $records = $records_tag;
            unset($records_tag);
        }

        // Находим общее количество записей
        $records_number = count($records);

        // Корректируем номер страницы
        if ($page < 1) {
            $page = 1;
        }
        if ($page > ceil($records_number / $globals["records_per_page"])) {
            $page = ceil($records_number / $globals["records_per_page"]);
        }

        // Выделяем из списка записи, соответствующие текущей странице
        if ($records_number > $globals["records_per_page"]) {
            $records_offset = ($page - 1) * $globals["records_per_page"];
            $records = array_slice($records, $records_offset, $globals["records_per_page"]);
        }

        // Используем в качестве названия страницы заголовок последней записи в блоге
        reset($records);
        list($id, $record) = each($records);
        $title = $record["title"];

        // Печатаем записи блога
        $i = 0;
        reset($records);
        while(list($id, $record) = each($records)) {

            // Заголовок первой записи в блоге не печатаем,
            // т.к. он будет напечатан в названии страницы автоматически
            if ($i > 0) {
                echo "<h2>".htmlspecialchars($record["title"])."</h2>\n";
            }

            // Печатаем дату публикации записи в блоге
            if ($globals["date_flag"]) {
                echo "<address>Дата публикации: ".$record["date"]."</address>\n";
            }
            echo "\n";

            // Производим парсинг Wiki-разметки
            $text = parse_wiki($record["text"]);

            // Выводим содержимое записи блога в браузер
            echo $text;

            // Формируем текст ссылки перехода
            if (strlen($record["text"]) != $record["length"]) {
                $link_title = "Читать дальше";
            } else {
                $link_title = "Перейти";
            }

            // Печатаем ссылку перехода к просмотру отдельной записи
            echo "<p><a href=\"".get_rewrite_link($record["id"])."\">".$link_title."</a></p>\n\n";

            // Печатаем строку со списком тегов к заметке
            if (!empty($record["tags"])) {
                echo get_tags_string($record["tags"]);
            }

            $i++;
        }

        // Рассчитываем ссылку на предыдущую страницу
        if ($page < ceil($records_number / $globals["records_per_page"])) {
            $prev_page = $page + 1;
        } else {
            $prev_page = "";
        }

        // Рассчитываем ссылку на следующую страницу
        if ($page > 1) {
            $next_page = $page - 1;
        } else {
            $next_page = "";
        }

        // Добавляем элементы управления
        $text = "<h2>Смотри также</h2>\n";
        $text .= "<ul>\n";
        $text .= "<li><a href=\"".$this_script."?action=calendar\">Календарь блога</a></li>\n";
        $text .= "<li><a href=\"".$this_script."?action=tags\">Список тегов</a></li>\n";
        if (!empty($prev_page)) {
            $text .= "<li><a href=\"".$this_script."?page=".$prev_page."\">Предыдущая страница</a></li>\n";
        }
        if (!empty($next_page)) {
            $text .= "<li><a href=\"".$this_script."?page=".$next_page."\">Следующая страница</a></li>\n";
        }
        $text .= "</ul>\n";

        // Выводим содержимое страницы в браузер
        echo $text;

    } else {

        // Формируем название страницы
        $title = "В блоге нет ни одной записи";

        // Печатаем сообщение об отсутствии странички
        echo "<p>Запрашиваемая Вами запись в блоге пока еще не создана. Возможно, она только планируется и появится здесь через некоторое время. Попробуйте зайти на сайт несколько позже.</p>\n";
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
//                 Функция показа запрошенной записи блога                   //
//                                                                           //
///////////////////////////////////////////////////////////////////////////////

function view_record($id) {

    global $globals, $this_script;

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

        // Формируем строку со списком тегов к заметке
        if (!empty($meta["tags"])) {
            $text .= get_tags_string($meta["tags"]);
        }

        // Читаем список записей в блоге
        $records = get_blog_data();

        // Рассчитываем ссылку на предыдущую запись в блоге
        $prev_id = "";
        $keys = array_keys($records);
        reset($keys);
        while (list($key_id, $key) = each($keys)) {
            if ($key == $id) {
                $prev_id = current($keys);
                break;
            }
        }

        // Рассчитываем ссылку на следующую запись в блоге
        $next_id = "";
        $keys = array_reverse(array_keys($records));
        reset($keys);
        while (list($key_id, $key) = each($keys)) {
            if ($key == $id) {
                $next_id = current($keys);
                break;
            }
        }

        // Добавляем элементы управления
        $text .= "<h2>Смотри также</h2>\n";
        $text .= "<ul>\n";
        $text .= "<li><a href=\"".$this_script."?action=calendar\">Календарь блога</a></li>\n";
        if (!empty($prev_id)) {
            $text .= "<li><a href=\"".get_rewrite_link($prev_id)."\">Предыдущая запись</a></li>\n";
        }
        if (!empty($next_id)) {
            $text .= "<li><a href=\"".get_rewrite_link($next_id)."\">Следующая запись</a></li>\n";
        }
        $text .= "</ul>\n";

        // Печатаем дату публикации записи в блоге
        if ($globals["date_flag"]) {
            echo "<address>Дата публикации: ".$records[$id]["date"]."</address>\n";
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
        $title = "Запись в блоге отсутствует";

        // Печатаем сообщение об отсутствии странички
        echo "<p>Запрашиваемая Вами запись в блоге пока еще не создана. Возможно, она только планируется и появится здесь через некоторое время. Попробуйте зайти на сайт несколько позже.</p>\n";
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
//                                                                           //
//                       Функция печати календаря блога                      //
//                                                                           //
///////////////////////////////////////////////////////////////////////////////

function print_calendar($year) {

    global $globals, $this_script;

    // Буферизируем вывод
    ob_start();

    // Читаем записи в блоге
    $records = get_blog_data();

    if (!empty($records)) {

        // Обрабатываем записи блога, формируя календарь записей в виде дерева
        $calendar = "";
        reset($records);
        while (list($id, $record) = each($records)) {

            // Извлекаем составляющие даты
            $blog_day   = (int) substr($record["id"],0,2);
            $blog_month = (int) substr($record["id"],3,2);
            $blog_year  = (int) substr($record["id"],6,4);

            // Добавляем в обший массив календаря
            $calendar[$blog_year][$blog_month][$blog_day] = $record;
        }

        // Расчитываем текущий год, месяц  и день
        $current_month = date("n");
        $current_year = date("Y");
        $current_day = date("j"); 

        // Сортируем года по убыванию
        krsort($calendar);

        // Корректируем переданный год для отрисовки календаря
        if (!isset($calendar[$year])) {
            reset($calendar);
            list($year, $months) = each($calendar);
        }

        // Печатаем календарь на запрашиваемый год
        $text = "<ul class=\"calendar\">\n";

        for ($month=1; $month<=12; $month++) {

            $text .= "<li>\n";

            // Печатаем заголовок таблицы
            $text .= "<table>\n";
            $text .= "<caption>".$globals["months"][$month]."</caption>\n";
            $text .= "<tr>\n";
            $text .= "<th>Пн</th>\n";
            $text .= "<th>Вт</th>\n";
            $text .= "<th>Ср</th>\n";
            $text .= "<th>Чт</th>\n";
            $text .= "<th>Пт</th>\n";
            $text .= "<th>Сб</th>\n";
            $text .= "<th>Вс</th>\n";
            $text .= "</tr>\n";

            // Читаем сетку календаря на текущий месяц
            $records = get_calendar_grid($month, $year);

            // Печатаем календарь на текущий месяц
            reset($records);
            while (list($row_id, $row) = each($records)) {

                $weekday_counter = 1;

                // Формируем очередную строку календаря
                $text .= "<tr>\n";
                while (list($column_id, $column) = each($row)) {

                    // Выделяем в календаре выходные дни
                    if ($weekday_counter == 6 || $weekday_counter == 7) {
                        $weekday_class = " class=\"weekday\"";
                    } else {
                        $weekday_class = "";
                    }

                    if (!empty($column)) {

                        // Проверяем есть ли в блоге запись на данный день
                        if (isset($calendar[$year][$month][$column])) {
                            $day = "<a href=\"".get_rewrite_link($calendar[$year][$month][$column]["id"])."\" title=\"".htmlspecialchars($calendar[$year][$month][$column]["title"])."\">".$column."</a>";
                        } else {
                            $day = $column;
                        }

                        // Выделяем в календаре текущий день
                        if ($column == $current_day && $month == $current_month && $year == $current_year) {
                            $text .= "<th".$weekday_class.">".$day."</th>\n";
                        } else {
                            $text .= "<td".$weekday_class.">".$day."</td>\n";
                        }

                    } else {

                        // Печатаем пустышку
                        $text .= "<td".$weekday_class.">&nbsp;</td>\n";
                    }

                    // Увеличиваем счетчик дня недели
                    $weekday_counter++;
                }
                $text .= "</tr>\n";
            }

            // Заканчиваем формирование таблицы
            $text .= "</table>\n";

            $text .= "</li>\n\n";
        }
        $text .= "</ul>\n";

        // Рассчитываем ссылку на предыдущий год
        $prev_year = "";
        $keys = array_keys($calendar);
        reset($keys);
        while (list($key_id, $key) = each($keys)) {
            if ($key == $year) {
                $prev_year = current($keys);
                break;
            }
        }

        // Рассчитываем ссылку на следующий год
        $next_year = "";
        $keys = array_reverse(array_keys($calendar));
        reset($keys);
        while (list($key_id, $key) = each($keys)) {
            if ($key == $year) {
                $next_year = current($keys);
                break;
            }
        }

        // Добавляем элементы управления
        $text .= "<h2>Смотри также</h2>\n";
        $text .= "<ul>\n";
        $text .= "<li><a href=\"".$this_script."\">Последние записи</a></li>\n";
        $text .= "<li><a href=\"".$this_script."?action=tags\">Список тегов</a></li>\n";
        if (!empty($prev_year)) {
            $text .= "<li><a href=\"".$this_script."?action=calendar&amp;year=".$prev_year."\">Предыдущий год</a></li>\n";
        }
        if (!empty($next_year)) {
            $text .= "<li><a href=\"".$this_script."?action=calendar&amp;year=".$next_year."\">Следующий год</a></li>\n";
        }
        $text .= "</ul>\n";

        // Формируем название страницы
        $title = "Календарь блога за ".$year." год";

        // Печатаем сообщение для пользователя
        echo "<p>На этой страничке собраны ссылки на все созданные в блоге записи, оформленные в виде простого календаря. Используйте данную страничку, чтобы быстро перейти к нужной записи.</p>\n";

        // Выводим содержимое страницы в браузер
        echo $text;

    } else {

        // Формируем название страницы
        $title = "Календарь блога";

        // Печатаем сообщение об отсутствии странички
        echo "<p>На данный момент в блоге не создано пока ни одной записи. Возможно, они только планируются и появятся здесь через некоторое время. Попробуйте зайти на сайт несколько позже.</p>\n";
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
//                       Функция печати списка тегов                         //
//                                                                           //
///////////////////////////////////////////////////////////////////////////////

function print_tags() {

    global $globals, $this_script;

    // Буферизируем вывод
    ob_start();

    // Читаем записи в блоге
    $records = get_blog_data();

    // Обрабатываем записи блога, формируя общий список тегов
    if (!empty($records)) {

        $tags_string = "";
        reset($records);
        while (list($id, $record) = each($records)) {
            if (!empty($record["tags"])) {
                $tags_string .= $record["tags"].", ";
            }
        }
        $tags_string = substr($tags_string, 0, -2);

    } else {
        $tags_string = "";
    }

    if (!empty($tags_string)) {

        // Формируем список тегов из строки
        $tags = get_tags($tags_string);

        // Находим максимальное значение частоты появления тега
        $frequency_max = 1;
        reset($tags);
        while (list($tag_id, $tag) = each($tags)) {
            if ($tag["frequency"] > $frequency_max) {
                $frequency_max = $tag["frequency"];
            }
        }

        // Формируем список тегов
        $text = "<p>\n";
        reset($tags);
        while (list($tag_id, $tag) = each($tags)) {

            // Рассчитываем высоту шрифта
            if ($frequency_max > 1) {
            	$fontsize = 100 + round((($tag["frequency"]-1) / ($frequency_max-1)) * 100);
            } else {
            	$fontsize = 100;
            }

            // Печатаем очередной тег
            $text .= "<a href=\"".$this_script."?tag=".$tag_id."\"><span style=\"font-size: ".$fontsize."%;\">".htmlspecialchars($tag["title"])."</span></a> \n";
        }
        $text .= "</p>\n";

        // Добавляем элементы управления
        $text .= "<h2>Смотри также</h2>\n";
        $text .= "<ul>\n";
        $text .= "<li><a href=\"".$this_script."\">Последние записи</a></li>\n";
        $text .= "<li><a href=\"".$this_script."?action=calendar\">Календарь блога</a></li>\n";
        $text .= "</ul>\n";

        // Формируем название страницы
        $title = "Список тегов";

        // Выводим содержимое страницы в браузер
        echo $text;

    } else {

        // Формируем название страницы
        $title = "Теги не найдены";

        // Печатаем сообщение об отсутствии странички
        echo "<p>На данный момент ни одна из страниц не использует теги.</p>\n";
    }

    // Читаем буферизированный вывод в строку
    $content = ob_get_contents();
    ob_end_clean();

    // Формируем параметры страницы
    $globals["page"]["title"] = $title;
    $globals["page"]["content"] = $content;
}

///////////////////////////////////////////////////////////////////////////////

if (!empty($_REQUEST["year"])) {
    $year = $_REQUEST["year"];
} else {
    $year = date("Y");
}

if (!empty($_REQUEST["tag"])) {
    $tag = $_REQUEST["tag"];
} else {
    $tag = "";
}

if ($action == "calendar") { // Печатаем календарь блога

    print_calendar($year);
    print_page();

} elseif ($action == "tags") { // Печатаем список тегов

    print_tags();
    print_page();

} else { 

    if ($id == "index") {

        // Печатаем последние записи блога
        view_blog($tag);

    } else {

        // Печатаем запрошенную запись блога
        view_record($id);
    }
    print_page();
}

?>