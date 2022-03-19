<?php

///////////////////////////////////////////////////////////////////////////////
//                                                                           //
//   Простой онлайн блокнот "Wikipad" // Экспорт содержимого сайта           //
//   ----------------------------------------------------------------------  //
//   Copyright (C) 1998-2022 Studio "Cherry-Design"                          //
//   URL: https://www.cherry-design.com/                                     //
//   E-mail: mike@cherry-design.com                                          //
//                                                                           //
///////////////////////////////////////////////////////////////////////////////

// Имя данного скрипта
$this_script = "export.php";

// Производим инициализацию
require("includes/initialization.php");

// Если включен режим обязательной авторизации для просмотра сайта и
// пользователь не авторизован или не имеет права экспортировать сайт,
// то делаем редирект на первую страницу
if ($globals["hidden_flag"] && !$globals["user_entry_flag"] || !$globals["export_flag"]) {
    header("Location: ./");
}

// Список папок, составляющих файловую структуру статического сайта
$globals["directories"] = array(
    "pages"   => "website",
    "css"     => "website/css",
    "pic"     => "website/pic",
    "files"   => "website/files"
);

///////////////////////////////////////////////////////////////////////////////
//                                                                           //
//            Функция очистки предыдущего состояния экспорта сайта           //
//                                                                           //
///////////////////////////////////////////////////////////////////////////////

function export_clear() {

    global $globals;

    // Количество ошибок
    $errors = 0;

    // Удаляем содержимое ранее экспортированного сайта
    if (file_exists($globals["path_export"])) {

        // Удаляем каталоги и их содержимое в обратном порядке
        $directories = array_reverse($globals["directories"], 1);

        // По очереди удаляем все каталоги ранее экспортированного сайта
        reset($directories);
        while (list($directory_id, $directory) = each($directories)) {

            // Удаляем все файлы файлы в каталоге
            $directory = $globals["path_export"].$directory;
            if (file_exists($directory) && is_dir($directory)) {

                if ($dp = opendir($directory)) {
                    while (false !== ($filename = readdir($dp))) {
                        if ($filename != "." && $filename != "..") {
                            $result = @unlink($directory."/".$filename);
                            if (!$result) {
                                $errors++;
                            }
                        }
                    }
                    closedir($dp);
                }

                // Удаляем каталог
                $result = @rmdir($directory);
                if (!$result) {
                    $errors++;
                }
            }
        }

        // Удаляем файл автозапуска
        if (file_exists($globals["path_export"]."autorun.inf")) {
            $result = @unlink($globals["path_export"]."autorun.inf");
            if (!$result) {
                $errors++;
            }
        }
    }

    return $errors;
}

///////////////////////////////////////////////////////////////////////////////
//                                                                           //
//           Функция создания файловой структуры статического сайта          //
//                                                                           //
///////////////////////////////////////////////////////////////////////////////

function export_structure() {

    global $globals;

    // Количество ошибок
    $errors = 0;

    // Создаем по очереди все необходимые каталоги
    $directories = $globals["directories"];

    reset($directories);
    while (list($directory_id, $directory) = each($directories)) {

        if (!file_exists($globals["path_export"].$directory)) {
            $result = @mkdir($globals["path_export"].$directory, 0777);
            if (!$result) {
                $errors++;
            }
        }
    }

    return $errors;
}

///////////////////////////////////////////////////////////////////////////////
//                                                                           //
//         Функция копирования обновленных файлов в статический сайт         //
//                                                                           //
///////////////////////////////////////////////////////////////////////////////

function export_files($source, $destination) {

    // Количество ошибок
    $errors = 0;

    // Очищаем кэш с информацией о файлах
    clearstatcache();

    // Читаем список исходных файлов
    $source_files = "";
    if ($dp = opendir($source)) {
        while (false !== ($filename = readdir($dp))) {
            if ($filename != "." && $filename != "..") {

                // Находим размер и дату последней модификации исходного файла
                $source_files[$filename] = array(
                    "size"  => filesize($source.$filename),
                    "mtime" => filemtime($source.$filename)
                );
            }
        }
        closedir($dp);
    }

    // Читаем список ранее скопированных файлов
    $destination_files = "";
    if ($dp = opendir($destination)) {
        while (false !== ($filename = readdir($dp))) {
            if ($filename != "." && $filename != "..") {

                // Находим размер и дату последней модификации ранее скопированного файла
                $destination_files[$filename] = array(
                    "size"  => filesize($destination.$filename),
                    "mtime" => filemtime($destination.$filename)
                );
            }
        }
        closedir($dp);
    }

    // Определяем список ранее скопированных файлов,
    // которые больше не существуют и удаляем их
    if (!empty($destination_files)) {

        reset($destination_files);
        while (list($filename, $fileinfo) = each($destination_files)) {
            if (!isset($source_files[$filename])) {

                // Удаляем файл из каталога
                $result = @unlink($destination."/".$filename);
                if (!$result) {
                    $errors++;
                }

                // Удаляем файл из списка ранее скопированных файлов
                unset($destination_files[$filename]);
            }
        }
    }

    // Определяем список файлов, которые изменились
    // с последней операции экспортирования и обновляем их
    if (!empty($source_files)) {

        reset($source_files);
        while (list($filename, $fileinfo) = each($source_files)) {

            // Проверяем, что данный файл ранее не создавался, не совпадает его размер,
            // либо дата модификации результирующего файла меньше исходного
            if (!isset($destination_files[$filename]) ||
                $destination_files[$filename]["size"] != $source_files[$filename]["size"] ||
                $destination_files[$filename]["mtime"] < $source_files[$filename]["mtime"]) {

                // Копируем исходный файл в результирующий
                $result = @copy($source.$filename, $destination.$filename);
                if (!$result) {
                    $errors++;
                }
            }
        }
    }

    return $errors;
}

///////////////////////////////////////////////////////////////////////////////
//                                                                           //
//                 Функция формирования дополнительных заголовков            //
//                                                                           //
///////////////////////////////////////////////////////////////////////////////

function get_website_headers($meta="") {

    global $globals;

    $string = "";

    // Добавляем строку идентифицирующую версию движка сгенерировавшего статическую версию сайта
    $string .= "\n<meta name=\"generator\" content=\"Wikipad ".$globals["version"]."\" />";

    // Если определены ключевые слова, то добавляем соответствующий мета-тег
    if (!empty($meta["keywords"]) || !empty($globals["website_keywords"])) {

        if (!empty($meta["keywords"])) {
            $keywords = $meta["keywords"];
        } else {
            $keywords = $globals["website_keywords"];
        }

        $string .= "\n<meta name=\"keywords\" content=\"".htmlspecialchars($keywords)."\" />";
    }

    // Если определено описание странички, то добавляем соответствующий мета-тег
    if (!empty($meta["description"]) || !empty($globals["website_description"])) {

        if (!empty($meta["description"])) {
            $description = $meta["description"];
        } else {
            $description = $globals["website_description"];
        }

        $string .= "\n<meta name=\"description\" content=\"".htmlspecialchars($description)."\" />";
    }

    $string .= "\n";

    return $string;
}

///////////////////////////////////////////////////////////////////////////////
//                                                                           //
//                 Функция формирования календаря блога                      //
//                                                                           //
///////////////////////////////////////////////////////////////////////////////

function parse_calendar($year, $calendar) {

    global $globals;

    // Формируем сообщение для пользователя
    $text = "<p>На этой страничке собраны ссылки на все созданные в блоге записи, оформленные в виде простого календаря. Используйте данную страничку, чтобы быстро перейти к нужной записи.</p>\n";

    // Корректируем переданный год для отрисовки календаря
    if (!isset($calendar[$year])) {
        reset($calendar);
        list($year, $months) = each($calendar);
    }

    // Формируем календарь на запрашиваемый год
    $text .= "<ul class=\"calendar\">\n";

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

        // Формируем календарь на текущий месяц
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
                        $day = "<a href=\"".$calendar[$year][$month][$column].".htm\">".$column."</a>";
                    } else {
                        $day = $column;
                    }

                    // Печатаем дату в календаре
                    $text .= "<td".$weekday_class.">".$day."</td>\n";

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
    if (!empty($prev_year)) {
        $text .= "<li><a href=\"".$prev_year.".htm\">Предыдущий год</a></li>\n";
    }
    if (!empty($next_year)) {
        $text .= "<li><a href=\"".$next_year.".htm\">Следующий год</a></li>\n";
    }
    $text .= "<li><a href=\"index.htm\">Первая страница</a></li>\n";
    $text .= "</ul>\n";

    // Возвращаем сгенерированный календарь
    return $text;
}

///////////////////////////////////////////////////////////////////////////////
//                                                                           //
//            Функция генерации xhtml-страничек из Wiki-разметки             //
//                                                                           //
///////////////////////////////////////////////////////////////////////////////

function export_wiki($source, $destination) {

    global $globals;

    // Количество ошибок
    $errors = 0;

    // Очищаем кэш с информацией о файлах
    clearstatcache();

    // Читаем список исходных файлов
    $blog_files = "";
    $source_files = "";
    if ($dp = opendir($source)) {
        while (false !== ($filename = readdir($dp))) {
            if ($filename != "." && $filename != ".." && $filename != ".htaccess") {

                // Если включен режим блога, то отдельно формируем список записей блога
                if ($globals["blog_flag"]) {

                    // Находим идентификатор страницы из имени файла
                    $id = substr($filename, 0, strrpos($filename, "."));

                    // Обрабатываем только текстовые файлы содержащие записи блога
                    if (preg_match($globals["regexp_blog_id"], $id)) {

                        // Извлекаем составляющие даты
                        $day   = substr($id,0,2);
                        $month = substr($id,3,2);
                        $year  = substr($id,6,4);

                        // Некорректные даты не включаем в список
                        if ($month <= 12 && $day < 31) {

                            // Формируем дату создания записи в блоге для последующей сортировки
                            $creation_date =  $year."-".$month."-".$day;
                            $blog_files[$id] = $creation_date;
                        }
                    }
                }

                // Корректируем имя файла для последующего сравнения
                $html_filename = str_replace(".txt", ".htm", $filename);

                // Находим дату последней модификации исходного файла
                $source_files[$html_filename] = array(
                    "mtime" => filemtime($source.$filename)
                );
            }
        }
        closedir($dp);
    }

    // Обрабатываем список записей блога, формируя календарь записей в виде дерева
    $blog_calendar = "";
    if ($globals["blog_flag"] && !empty($blog_files)) {

            // Сортируем записи блога по дате создания
            arsort($blog_files);

            // Формируем календарь записей в виде дерева
            reset($blog_files);
            while (list($id, $record) = each($blog_files)) {

                // Извлекаем составляющие даты
                $blog_day   = (int) substr($id,0,2);
                $blog_month = (int) substr($id,3,2);
                $blog_year  = (int) substr($id,6,4);

                // Добавляем в обший массив календаря
                $blog_calendar[$blog_year][$blog_month][$blog_day] = $id;
            }

            // Сортируем года по убыванию
            krsort($blog_calendar);
    }

    // Читаем список ранее скопированных файлов
    $destination_files = "";
    if ($dp = opendir($destination)) {
        while (false !== ($filename = readdir($dp))) {
            if ($filename != "." && $filename != ".." && !is_dir($destination.$filename)) {

                // Находим дату последней модификации ранее сгенерированных страничек
                $destination_files[$filename] = array(
                    "mtime" => filemtime($destination.$filename)
                );
            }
        }
        closedir($dp);
    }

    // Определяем список ранее сгенерированных страничек,
    // которые больше не существуют и удаляем их
    if (!empty($destination_files)) {

        reset($destination_files);
        while (list($filename, $fileinfo) = each($destination_files)) {
            if (!isset($source_files[$filename])) {

                // Удаляем файл из каталога
                $result = @unlink($destination."/".$filename);
                if (!$result) {
                    $errors++;
                }
                
                // Удаляем файл из списка ранее скопированных файлов
                unset($destination_files[$filename]);
            }
        }
    }

    // Определяем список файлов, которые изменились с последней операции экспортирования
    $update_pages = "";
    reset($source_files);
    while (list($filename, $fileinfo) = each($source_files)) {
    
        // Проверяем, что данный файл ранее не создавался,
        // что дата создания xhtml-файла меньше файла с исходной wiki-разметкой
        if (!isset($destination_files[$filename]) || 
            $destination_files[$filename]["mtime"] < $source_files[$filename]["mtime"]) {

            // Рассчитываем оригинальное название файла
            $wiki_filename = str_replace(".htm", ".txt", $filename);

            // Добавляем название исходного файла в массив обновленных страничек
            $update_pages[$wiki_filename] = $filename;
        }
    }

    // Формируем шаблон для последующей генерации статически страничек
    if(!empty($blog_calendar) || !empty($update_pages)) {

        // Читаем шаблон оформления страниц в массив строк
        $template = file($globals["path_templates"]."main.tpl");

        // Удаляем из шаблона ссылку на подключаемые JavaScript-функции 
        reset($template);
        while(list($template_id, $template_string) = each($template)) {
            if (trim($template_string) == "<script type=\"text/javascript\" src=\"js/functions.js\"></script>") {
                unset($template[$template_id]);
            }
        }

        // Объединяем строки в общий шаблон
        $template = implode("", $template);

        // Удаляем из меню сайта ссылку на поиск
        if (isset($globals["menu"]["search:"])) {
            unset($globals["menu"]["search:"]);
        }

        // Удаляем из меню сайта ссылку на блог, если он отключен
        if (!$globals["blog_flag"] && isset($globals["menu"]["blog:"])) {
            unset($globals["menu"]["blog:"]);
        }
        if (!$globals["blog_flag"] && isset($globals["menu"]["blog:calendar"])) {
            unset($globals["menu"]["blog:calendar"]);
        }
        if (!$globals["blog_flag"] && isset($globals["menu"]["blog:tags"])) {
            unset($globals["menu"]["blog:tags"]);
        }

        // Удаляем из меню повторные ссылки на блог
        if (isset($globals["menu"]["blog:"]) && isset($globals["menu"]["blog:calendar"])) {
            unset($globals["menu"]["blog:calendar"]);
        }
        if (isset($globals["menu"]["blog:"]) && isset($globals["menu"]["blog:tags"])) {
            unset($globals["menu"]["blog:tags"]);
        }

        // Формируем основное меню сайта
        $main_menu = "<ul>\n";
        reset($globals["menu"]);
        while (list($id, $title) = each($globals["menu"])) {
            if (isset($globals["menu_actions"][$id])) {

                // Из доступных команд меню обрабатываем только блог
                if ($id == "blog:" || $id == "blog:calendar" || $id == "blog:tags") {

                    // Формируем ссылку на календарь блога за последний год
                    reset($blog_calendar);
                    list($year, $months) = each($blog_calendar);
                    $main_menu .= "<li><a href=\"".$year.".htm\">".$title."</a></li>\n";
                }

            } else {
                $main_menu .= "<li><a href=\"".$id.".htm\">".$title."</a></li>\n";
            }
        }
        $main_menu .= "</ul>";

        // Формируем меню пользователя
        if (!empty($globals["website_email"])) {
            $user_menu = "<ul>\n";
            $user_menu .= "<li><a href=\"mailto:".$globals["website_email"]."\">Написать письмо</a></li>\n";
            $user_menu .= "</ul>";
        } else {
            $user_menu = "";
        }

        // Добавляем в шаблон переменные общие для всех страниц
        $template = str_replace("{WEBSITE_TITLE}", $globals["website_title"], $template);
        $template = str_replace("{WEBSITE_WORDS}", $globals["website_words"], $template);
        $template = str_replace("{VERSION}", $globals["version"], $template);
        $template = str_replace("{MAIN_MENU}", $main_menu, $template);
        $template = str_replace("{USER_MENU}", $user_menu, $template);
    }

    // Формируем странички календаря блога
    if ($globals["blog_flag"] && !empty($blog_calendar)) {

        // Формируем отдельную страницу календаря для каждого года
        reset($blog_calendar);
        while(list($year, $months) = each($blog_calendar)) {

            // Формируем название страницы
            $title = "Календарь блога за ".$year." год";

            // Производим парсинг календаря
            $text = parse_calendar($year, $blog_calendar);

            // Формируем имя файла календаря
            $filename = $year.".htm";

            // Формируем описание и список ключевых слов для страницы
            $website_headers = get_website_headers();

            // Формируем страничку в формате HTML
            $string = $template;
            $string = str_replace("{WEBSITE_HEADERS}", $website_headers, $string);
            $string = str_replace("{TITLE}", $title, $string);
            $string = str_replace("{TITLE}", $title, $string);
            $string = str_replace("{CONTENT}", $text, $string);

            // Корректируем концы строк
            $string = str_replace("\r\n", "\n", $string);
            $string = str_replace("\n", "\r\n", $string);

            // Сохраняем созданный HTML-файл
            $fp = fopen($destination."/".$filename,"w+");
            $result = fwrite($fp, $string);
            if (!$result) {
                $errors++;
            }
            fclose($fp);
        }
    }

    // Обновляем странички, изменившиеся с последней операции импортирования
    if (!empty($update_pages)) {

        // Генерируем статические странички на основе шаблонов сайта
        reset($update_pages);
        while (list($wiki_filename, $html_filename) = each($update_pages)) {

            // Находим идентификатор страницы из имени файла
            $id = substr($wiki_filename, 0, strrpos($wiki_filename, "."));

            // Читаем содержание файла
            $text = file("pages/".$wiki_filename);

            // Находим название страницы
            $title = implode("", array_slice($text,0,1));
            $title = trim(substr(trim($title), 1, -1));

            // Находим текст страницы
            $text = array_slice($text,2);
            $text = implode("", $text);

            // Производим парсинг Wiki-разметки
            $meta = parse_meta($text);
            $text = parse_wiki($text, 1);

            // Добавляем дату последнего изменения странички
            if ($globals["date_flag"]) {
                if ($globals["blog_flag"] && isset($blog_files[$id])) {

                    // Извлекаем составляющие даты
                    $day   = substr($id,0,2);
                    $month = substr($id,3,2);
                    $year  = substr($id,6,4);

                    // Формируем дату публикации записи в блоге
                    $text = "<address>Дата публикации: ".$day."/".$month."/".$year."</address>\n".$text;

                } else {
                    $text = "<address>Последнее изменение: ".date("d/m/Y H:i:s", $source_files[$html_filename]["mtime"])."</address>\n".$text;
                }
            }

            // Производим дополнительную обработку для записей блога
            if ($globals["blog_flag"] && isset($blog_files[$id])) {

                // Извлекаем составляющие даты
                $day   = substr($id,0,2);
                $month = substr($id,3,2);
                $year  = substr($id,6,4);

                // Рассчитываем ссылку на предыдущую запись в блоге
                $prev_id = "";
                $keys = array_keys($blog_files);
                reset($keys);
                while (list($key_id, $key) = each($keys)) {
                    if ($key == $id) {
                        $prev_id = current($keys);
                        break;
                    }
                }

                // Рассчитываем ссылку на следующую запись в блоге
                $next_id = "";
                $keys = array_reverse(array_keys($blog_files));
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
                $text .= "<li><a href=\"".$year.".htm\">Календарь блога</a></li>\n";
                if (!empty($prev_id)) {
                    $text .= "<li><a href=\"".$prev_id.".htm\">Предыдущая запись</a></li>\n";
                }
                if (!empty($next_id)) {
                    $text .= "<li><a href=\"".$next_id.".htm\">Следующая запись</a></li>\n";
                }
                $text .= "</ul>\n";
            }

            // Формируем описание и список ключевых слов для страницы
            $website_headers = get_website_headers($meta);

            // Формируем страничку в формате HTML
            $string = $template;
            $string = str_replace("{WEBSITE_HEADERS}", $website_headers, $string);
            $string = str_replace("{TITLE}", $title, $string);
            $string = str_replace("{CONTENT}", $text, $string);

            // Корректируем концы строк
            $string = str_replace("\r\n", "\n", $string);
            $string = str_replace("\n", "\r\n", $string);

            // Сохраняем созданный HTML-файл
            $fp = fopen($destination."/".$html_filename,"w+");
            $result = fwrite($fp, $string);
            if (!$result) {
                $errors++;
            }
            fclose($fp);
        }
    }

    return $errors;
}

///////////////////////////////////////////////////////////////////////////////
//                                                                           //
//                          Функция экспорта web-сайта                       //
//                                                                           //
///////////////////////////////////////////////////////////////////////////////

function export_website($record) {

    global $this_script, $globals;

    // Буферизируем вывод
    ob_start();

    // Флаг отрисовки формы
    $form_flag = 1;

    if (empty($record)) {

        // Формируем значения переменных по умолчанию
        $record = array(
            "clear"  => "no",
            "files"  => "yes"
        );

    } else {

        echo "<p>Ниже приведены результаты экспорта текущего состояния информации из Wikipad в статический сайт.</p>\n";
        echo "<ol>\n";

        // Делаем предварительную очистку папки экспорта, если установлен соответствующий флаг
        $errors_clear = 0;
        if (isset($record["clear"]) && $record["clear"] == "yes") {
            $errors_clear = export_clear();
            if (!$errors_clear) {
                echo "<li>Очищено предыдущее состояние экспорта сайта</li>\n";
            } else {
                echo "<li><strong>Ошибка!</strong> Не удалось полностью или частично очистить предыдущее состояние экспорта сайта</li>\n";
            }
        }

        // Создаем файловую структуру статического сайта
        $errors_structure = export_structure();
        if (!$errors_structure) {
            echo "<li>Создана структура папок статического сайта</li>\n";
        } else {
            echo "<li><strong>Ошибка!</strong> Не удалось полностью или частично создать структуру папок статического сайта</li>\n";
        }

        // Копируем изображения, используемые в дизайне
        $errors_files_pic = export_files("pic/", $globals["path_export"].$globals["directories"]["pic"]."/");
        if (!$errors_files_pic) {
            echo "<li>Скопированы изображения, используемые в дизайне сайта</li>\n";
        } else {
            echo "<li><strong>Ошибка!</strong> Не удалось скопировать одно или несколько изображений, используемых в дизайне сайта</li>\n";
        }
        
        // Удаляем графический файл с изображением панели инструментов
        if (file_exists($globals["path_export"].$globals["directories"]["pic"]."/toolbar.gif")) {
            @unlink($globals["path_export"].$globals["directories"]["pic"]."/toolbar.gif");
        }

        // Копируем стилевые файлы с дизайном сайта
        $errors_files_css = 0;

        // Ключевые фразы, определяющие начало фрагментов для удаления
        $remove_fragments = array(
            "/* Основное содержание (формы)",
            "/* Основное содержание (панель инструментов редактирования)",
            "/* Основное содержание (теги)"
        );

        // Копируем и обрабатываем таблицу стилей для экрана
        if (file_exists("css/screen.css")) {

            // Читаем стиль для экрана в массив строк
            $style = @file("css/screen.css");

            // Формируем новый стилевой файл, удаляя из него правила относящиеся к панели инструментов, к формам и тегам
            $copy_flag = 1;
            $new_style = "";
            reset($style);
            while(list($style_id, $style_string) = each($style)) {

                // Определяем начало ключевой фразы удаляемого фрагмента
                if ($copy_flag == 1 && in_array(trim($style_string), $remove_fragments)) {
                    $copy_flag = 0;
                    continue;
                }

                // Определяем конец удаляемого фрагмента
                if ($copy_flag == 0 && substr($style_string,0,2) == "/*" && !in_array(trim($style_string), $remove_fragments)) {
                    $copy_flag = 1;
                }

                // Копируем исходные стилевые правила в новый стиль
                if ($copy_flag) {
                    $new_style[] = $style_string;
                }
            }

            // Формируем объединенную строку со стилем для экрана
            $style = implode("", $new_style);

            // Сохраняем скорректированный стиль для экрана
            $fp = fopen($globals["path_export"].$globals["directories"]["css"]."/screen.css","w+");
            $result = fwrite($fp, $style);
            if (!$result) {
                $errors_files_css++;
            }
            fclose($fp);
        }

        // Копируем и обрабатываем таблицу стилей для печати
        if (file_exists("css/print.css")) {

            // Читаем стиль для экрана в массив строк
            $style = @file("css/print.css");

            // Формируем новый стилевой файл, удаляя из него правила относящиеся к панели инструментов и к формам
            $copy_flag = 1;
            $new_style = "";
            reset($style);
            while(list($style_id, $style_string) = each($style)) {

                // Определяем начало ключевой фразы удаляемого фрагмента
                if ($copy_flag == 1 && in_array(trim($style_string), $remove_fragments)) {
                    $copy_flag = 0;
                    continue;
                }

                // Определяем конец удаляемого фрагмента
                if ($copy_flag == 0 && substr($style_string,0,2) == "/*" && !in_array(trim($style_string), $remove_fragments)) {
                    $copy_flag = 1;
                }

                // Копируем исходные стилевые правила в новый стиль
                if ($copy_flag) {
                    $new_style[] = $style_string;
                }
            }

            // Формируем объединенную строку со стилем для печати
            $style = implode("", $new_style);

            // Сохраняем скорректированный стиль для печати
            $fp = fopen($globals["path_export"].$globals["directories"]["css"]."/print.css","w+");
            $result = fwrite($fp, $style);
            if (!$result) {
                $errors_files_css++;
            }
            fclose($fp);
        }

        if (!$errors_files_css) {
            echo "<li>Скопированы таблицы стилей, используемые в дизайне сайта</li>\n";
        } else {
            echo "<li><strong>Ошибка!</strong> Не удалось скопировать одну или несколько таблиц стилей, используемых в дизайне сайта</li>\n";
        }

        // Копируем загруженные файлы и иллюстрации
        $errors_files = 0;
        if (isset($record["files"]) && $record["files"] == "yes") {
            $errors_files = export_files("files/", $globals["path_export"].$globals["directories"]["files"]."/");
            if (!$errors_files) {
                echo "<li>Скопированы загруженные файлы и иллюстрации к страницам</li>\n";
            } else {
                echo "<li><strong>Ошибка!</strong> Не удалось скопировать один или несколько загруженных файлов и иллюстраций к страницам</li>\n";
            }
        }

        // Генерируем xhtml-странички из Wiki-разметки
        $errors_wiki = export_wiki("pages/", $globals["path_export"].$globals["directories"]["pages"]."/");
        if (!$errors_wiki) {
            echo "<li>Сгенерированы статические странички из исходной wiki-разметки</li>\n";
        } else {
            echo "<li><strong>Ошибка!</strong> Не удалось сгенерировать одну или несколько статических страничек из исходной wiki-разметки</li>\n";
        }

        // Создаем файл автозапуска для записи экспортированного сайта на диск
        $errors_autorun = 0;
        $string = "[autorun]\n";
        $string .= "open=explorer website\index.htm\n";
        $fp = fopen($globals["path_export"]."autorun.inf","w+");
        $result = fwrite($fp, $string);
        if (!$result) {
            $errors_autorun = 1;
        }
        fclose($fp);

        if (!$errors_autorun) {
            echo "<li>Сгенерирован файл автозапуска &quot;autorun.inf&quot; для записи сайта на диск</li>\n";
        } else {
            echo "<li><strong>Ошибка!</strong> Не удалось сгенерировать файл автозапуска &quot;autorun.inf&quot; для записи сайта на диск</li>\n";
        }

        echo "</ol>\n";

        // Печатаем обобщенное сообщение о результатах экспорта сайта
        if ($errors_clear || $errors_structure || $errors_files_pic || $errors_files_css || $errors_files || $errors_wiki || $errors_autorun) {
            echo "<p>При экспорте информации из Wikipad в статический сайт произошла одна или несколько ошибок. Это может быть связано либо с недостаточными правами на запись в каталог экспорта, либо с нехваткой места для экспорта статического сайта. Внимательно прочитайте сообщения приведенные выше, исправьте ошибку и повторите процедуру <a href=\"".$this_script."\">экспорта</a>.</p>\n";
        } else {
            echo "<p>Информация из Wikipad успешно экспортирована в статический сайт.</p>\n";
        }

        // Сбрасываем флаг отрисовки формы
        $form_flag = 0;
    }

    // Отрисовываем форму
    if ($form_flag) {
?>
<p>На этой странице Вы можете произвести экспорт всего содержимого Wikipad в обычный статический сайт. Если затем записать экспортированный вариант сайта на компакт-диск, то у Вас получится законченный информационный проект. Вы можете производить экспорт после любого изменения на основном сайте - система будет обновлять только те странички и файлы, которые изменились с предыдущего раза.</p>
<p>Дизайн и навигация экспортированного сайта будут максимально близко повторять дизайн и навигацию исходного сайта. Если Вы желаете изменить дизайн экспортированного статического сайта, то Вам необходимо предварительно изменить шаблон дизайна и таблицы стилей оригинального сайта.</p>

<h2>Параметры экспорта</h2>
<p>Экспорт сайта производится в папку, определенную в файле конфигурации системы. Вы можете указать ряд параметров экспорта в приведенной ниже форме. Сайт всегда экспортируется целиком и это может занять достаточно длительное время, особенно при первом экспорте сайта или генерации страничек с предварительной очисткой.</p>

<form action="<?php echo $this_script; ?>" method="post">
<ul>
<li><input type="checkbox" name="data[clear]" value="yes" /> Предварительно очистить предыдущее состояние экспортирования сайта</li>
<li><input type="checkbox" name="data[files]" value="yes" checked="checked" /> Копировать загруженные файлы и изображения при экспорте статических страничек</li>
</ul>
<p><input type="submit" value=" Экспортировать " /></p>
</form>
<?php
    }

    // Читаем буферизированный вывод в строку
    $content = ob_get_contents();
    ob_end_clean();

    // Формируем параметры страницы
    $globals["page"]["title"] = "Экспорт сайта";
    $globals["page"]["content"] = $content;
}

///////////////////////////////////////////////////////////////////////////////

if (!empty($_REQUEST["data"])) {
    $data = $_REQUEST["data"];
} else {
    $data = "";
}

// Экспортируем содержимое сайта
export_website($data);
print_page();

?>