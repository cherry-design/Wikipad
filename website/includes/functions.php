<?php

///////////////////////////////////////////////////////////////////////////////
//                                                                           //
//   Простой онлайн блокнот "Wikipad" // Общие функции                       //
//   ----------------------------------------------------------------------  //
//   Copyright (C) 1998-2021 Studio "Cherry-Design"                          //
//   URL: https://www.cherry-design.ru/                                      //
//   E-mail: mike@cherry-design.ru                                           //
//                                                                           //
///////////////////////////////////////////////////////////////////////////////

// Регулярное выражение для определения записи блога по идентификатору страницы
$globals["regexp_blog_id"] = "/^[0-9]{2}-[0-9]{2}-[0-9]{4}$/iu";

// Список команд, разрешенных в меню сайта
$globals["menu_actions"] = array(
    "search:"       => "search.php",
    "blog:"         => "blog.php",
    "blog:calendar" => "blog.php?action=calendar",
    "blog:tags"     => "blog.php?action=tags"
);

// Массив с названиями месяцев
$globals["months"] = array(
    "1"  => "Январь",
    "2"  => "Февраль",
    "3"  => "Март",
    "4"  => "Апрель",
    "5"  => "Май",
    "6"  => "Июнь",
    "7"  => "Июль",
    "8"  => "Август",
    "9"  => "Сентябрь",
    "10" => "Октябрь",
    "11" => "Ноябрь",
    "12" => "Декабрь"
);

// Таблица преобразования русского, белорусского и украинского текста в транслитерацию
$globals["transliteration"] = array (
    "а"=>"a","б"=>"b","в"=>"v","г"=>"g","д"=>"d","е"=>"e","ё"=>"yo","ж"=>"j","з"=>"z",
    "и"=>"i","й"=>"i","к"=>"k","л"=>"l","м"=>"m","н"=>"n","о"=>"o","п"=>"p","р"=>"r",
    "с"=>"s","т"=>"t","у"=>"u","ф"=>"f","х"=>"h","ц"=>"ts","ч"=>"ch","ш"=>"sh","щ"=>"sch",
    "ъ"=>"","ы"=>"y","ь"=>"","э"=>"e","ю"=>"yu","я"=>"ya","і"=>"i","ў"=>"y","ґ"=>"g","ї"=>"i","є"=>"ye"
);

///////////////////////////////////////////////////////////////////////////////
//                                                                           //
//               Функция вывода содержимого страницы в браузер               //
//                                                                           //
///////////////////////////////////////////////////////////////////////////////

function print_page() {

    global $globals;

    // Посылаем заголовки, запрещающие кэширование
    header("Expires: Mon, 26 Jul 1997 05:00:00 GMT");
    header("Last-Modified: ".gmdate("D, d M Y H:i:s")." GMT");
    header("Cache-Control: no-cache, must-revalidate");
    header("Pragma: no-cache");

    // Печатаем дополнительные заголовки
    print_website_headers();

    // Печатаем основное меню
    print_main_menu();

    // Печатаем меню пользователя
    print_user_menu();

    // Формируем общие параметры страницы
    $globals["page"]["website_title"] = $globals["website_title"];
    $globals["page"]["website_words"] = $globals["website_words"];
    $globals["page"]["version"] = $globals["version"];
    
    // Производим парсинг переменных в шаблон
    $string = parse_template("main", $globals["page"]);

    // Выводим страницу в браузер
    echo $string;
}

///////////////////////////////////////////////////////////////////////////////
//                                                                           //
//                    Функция печати дополнительных заголовков               //
//                                                                           //
///////////////////////////////////////////////////////////////////////////////

function print_website_headers() {

    global $globals;

    $string = "";

    // Если определены ключевые слова, то добавляем соответствующий мета-тег
    if (!empty($globals["page"]["keywords"]) || !empty($globals["website_keywords"])) {

        if (!empty($globals["page"]["keywords"])) {
            $keywords = $globals["page"]["keywords"];
            unset($globals["page"]["keywords"]);
        } else {
            $keywords = $globals["website_keywords"];
        }

        $string .= "\n<meta name=\"keywords\" content=\"".htmlspecialchars($keywords)."\" />";
    }

    // Если определено описание странички, то добавляем соответствующий мета-тег
    if (!empty($globals["page"]["description"]) || !empty($globals["website_description"])) {

        if (!empty($globals["page"]["description"])) {
            $description = $globals["page"]["description"];
            unset($globals["page"]["description"]);
        } else {
            $description = $globals["website_description"];
        }

        $string .= "\n<meta name=\"description\" content=\"".htmlspecialchars($description)."\" />";
    }

    // Если включен режим трансляции RSS-канала, то добавляем ссылку на канал в заголовке страницы
    if ($globals["rss_flag"]) {
        $string .= "\n\n<link rel=\"alternate\" type=\"application/rss+xml\" title=\"".htmlspecialchars($globals["website_title"])."\" href=\"rss.php\" />";
    }

    // Сохраняем дополнительные заголовки в переменной
    $globals["page"]["website_headers"] = $string;
}

///////////////////////////////////////////////////////////////////////////////
//                                                                           //
//                       Функция печати основного меню                       //
//                                                                           //
///////////////////////////////////////////////////////////////////////////////

function print_main_menu() {

    global $this_script, $action, $globals, $id;

    $string = "";

    // Печатаем основное меню, только в случае, если пользователь авторизован
    // и система не работает в режиме обязательной регистрации
    if (!$globals["hidden_flag"] || $globals["user_entry_flag"]) {

        // Формируем меню сайта
        $string .= "<ul>\n";

        // В случае, если пользователь авторизовался
        if ($globals["user_entry_flag"]) {

            $string .= "<li><a href=\"".get_rewrite_link("index")."\">Первая страница</a></li>\n";

             // Печатаем ссылку на блог
             if ($globals["blog_flag"]) {
                 $string .= "<li><a href=\"blog.php\">Блог</a></li>\n";
             }

             // Печатаем ссылку на команду поиска по сайту
             if ($globals["search_flag"]) {
                 $string .= "<li><a href=\"search.php\">Поиск</a></li>\n";
             }

            // Печатаем команду редактировать только на основных страничках
            if ($this_script != "files.php" && $this_script != "export.php") {

                // В режиме изменения странички заменяем команду "редактировать" на "посмотреть"
                if ($this_script == "pages.php" && $action == "edit") {
                    $string .= "<li><a href=\"".get_rewrite_link($id)."\">Просмотр</a></li>\n";
                } else {

                    // Если мы находимся в режиме просмотра блога, то при редактировании изменяем идентификатор страницы на текущую дату
                    if ($globals["blog_flag"] && $id == "index" && $this_script == "blog.php") {
                        $string .= "<li><a href=\"pages.php?action=edit&amp;id=".date("d-m-Y")."\">Редактирование</a></li>\n";
                    } else {
                        $string .= "<li><a href=\"pages.php?action=edit&amp;id=".$id."\">Редактирование</a></li>\n";
                    }
                }
            }

            // Печатаем команду "Загрузить файл" только в режиме просмотра странички
            if (!($this_script == "pages.php" && $action == "edit")) {
                $string .= "<li><a href=\"files.php?action=upload\">Загрузка файла</a></li>\n";
            }

            // Печатаем ссылку на команду экспорта содержимого сайта
            if ($globals["export_flag"]) {
                $string .= "<li><a href=\"export.php\">Экспорт</a></li>\n";
            }

            $string .= "<li><a href=\"".get_rewrite_link("sitemap")."\">Карта сайта</a></li>\n";
            $string .= "<li><a href=\"".get_rewrite_link("help")."\">Помощь</a></li>\n";

        // В случае, если пользователь еще не был авторизован
        } else {

             // Удаляем ссылку на блог, если он отключен
             if (!$globals["blog_flag"] && isset($globals["menu"]["blog:"])) {
                 unset($globals["menu"]["blog:"]);
             }
             if (!$globals["blog_flag"] && isset($globals["menu"]["blog:calendar"])) {
                 unset($globals["menu"]["blog:calendar"]);
             }
             if (!$globals["blog_flag"] && isset($globals["menu"]["blog:tags"])) {
                 unset($globals["menu"]["blog:tags"]);
             }

             // Удаляем ссылку на команду поиска по сайту, если поиск отключен
             if (!$globals["search_flag"] && isset($globals["menu"]["search:"])) {
                 unset($globals["menu"]["search:"]);
             }

            // Формируем меню согласно конфигурационному файлу
            reset($globals["menu"]);
            while (list($id, $title) = each($globals["menu"])) {
                if (isset($globals["menu_actions"][$id])) {
                    $string .= "<li><a href=\"".$globals["menu_actions"][$id]."\">".$title."</a></li>\n";
                } else {
                    $string .= "<li><a href=\"".get_rewrite_link($id)."\">".$title."</a></li>\n";
                }
            }
        }

        $string .= "</ul>";
    }

    // Сохраняем основное меню в переменной
    $globals["page"]["main_menu"] = $string;
}

///////////////////////////////////////////////////////////////////////////////
//                                                                           //
//                     Функция печати меню пользователя                      //
//                                                                           //
///////////////////////////////////////////////////////////////////////////////

function print_user_menu() {

    global $globals;

    // Формируем меню сайта
    $string  = "<ul>\n";

    if ($globals["user_entry_flag"]) {

        // В случае, если пользователь авторизовался
        $string .= "<li><a href=\"logout.php\">Выйти из системы</a></li>\n";

    } else {

        // В случае, если пользователь еще не был авторизован
        $string .= "<li><a href=\"login.php\">Войти в систему</a></li>\n";
    }

    $string .= "</ul>";

    // Сохраняем меню пользователя в переменной
    $globals["page"]["user_menu"] = $string;
}

///////////////////////////////////////////////////////////////////////////////
//                                                                           //
//                   Функция добавления "волшебных кавычек"                  //
//                                                                           //
///////////////////////////////////////////////////////////////////////////////

function add_magic_quotes(&$data) {

    reset($data);
    while(list($key, $value) = each($data)) {

        // Если переданные данные являются массивом, 
        // то вызываем функцию рекурсивно
        if (is_array($value)) {
            $data[$key] = add_magic_quotes($value);
        } else {
            $data[$key] = addslashes($value);
        }
    }

    return $data;
}

///////////////////////////////////////////////////////////////////////////////
//                                                                           //
//             Функция расчета сетки календаря на заданный месяц             //
//                                                                           //
///////////////////////////////////////////////////////////////////////////////

function get_calendar_grid($month, $year) {

    // Определяем с какого дня недели начинается месяц
    $first_date = mktime(0,0,0, $month, 1, $year);
    $first_day = date("w", $first_date);

    // Находим количество дней в месяце
    $month_days = date("t", $first_date);

    // Рассчитываем сколько пустышек нужно напечатать в начале
    $start_spaces = $first_day - 1;

    if ($start_spaces < 0) {
        $start_spaces += 7;
    }

    // Порядковый счетчик дня с учетом поправки на пустышки
    $day_counter = 1 - $start_spaces;

    // Формируем строки календарика
    $calendar_grid = "";
    for ($j=0; $j<6; $j++) {

        // Формируем дни в строке
        for ($i=0; $i<7; $i++) {

            // Печатаем дни с учетом начальных пустышек и количества дней в месяце
            if ($day_counter > 0 && $day_counter <= $month_days) {
                $row[$i] = $day_counter;
            } else {
                $row[$i] = "";
            }
            $day_counter++;
        }

        // Добавляем очередную строку в общую сетку календаря
        $calendar_grid[] = $row;
        unset ($row);
    }

    return $calendar_grid;
}

///////////////////////////////////////////////////////////////////////////////
//                                                                           //
//                      Функция печати ссылок навигаций                      //
//                                                                           //
///////////////////////////////////////////////////////////////////////////////

function print_navigation($page, $records_per_page, $records_number, $query_string="") {

    $string = "<p class=\"navigation\">";

    // Количество страниц на секцию
    $pages_per_section = 20;

    // Находим число получившихся страниц
    $pages_number = ceil($records_number / $records_per_page);

    // Корректируем номер странички
    if ($page > $pages_number) {
        $page = $pages_number;
    }

    // Находим число получившихся секций
    $sections_number = ceil($pages_number / $pages_per_section);

    // Находим номер текущей секции
    $section = ceil($page / $pages_per_section);

    // Формируем навигацию в секции слева
    $prev_section_page = ($section-1) * $pages_per_section;
    if ($section > 1) {

        if ($query_string == "") {
            $string .= "<a href=\"?page=".$prev_section_page."\">[&lt;&lt;]</a>";
        } else {
            $string .= "<a href=\"?".$query_string."&amp;page=".$prev_section_page."\">[&lt;&lt;]</a>";
        }

        $string .= " &nbsp;";
    }

    // Рассчитываем начало и конец диапазона печатаемых страничек
    $start = ($section-1) * $pages_per_section + 1;
    $end = $start + $pages_per_section;
    if ($end > $pages_number + 1) {
        $end = $pages_number + 1;
    }

    // Печатаем странички со ссылками
	for ($i = $start; $i < $end; $i++) {

        if ($i == $page) {
            $string .= "[".$i."]";
        } else {

            if ($query_string == "") {
                $string .= "<a href=\"?page=".$i."\">".$i."</a>";
            } else {
                $string .= "<a href=\"?".$query_string."&amp;page=".$i."\">".$i."</a>";
            }
        }
        $string .= " &nbsp;";
	}

    // Формируем навигацию в секции справа
    if ($section < $sections_number) {
        $next_section_page = $section * $pages_per_section + 1;
        if ($query_string == "") {
            $string .= "<a href=\"?page=".$next_section_page."\">[&gt;&gt;]</a>";
        } else {
            $string .= "<a href=\"?".$query_string."&amp;page=".$next_section_page."\">[&gt;&gt;]</a>";
        }
        $string .= " &nbsp;";
    }

    $string .= "// всего записей: ".$records_number."</p>\n";

    // Печатаем общее число записей
    echo $string;
}

///////////////////////////////////////////////////////////////////////////////
//                                                                           //
//                     Функция парсинга переменных в шаблон                  //
//                                                                           //
///////////////////////////////////////////////////////////////////////////////

function parse_template($template, $vars) {

    global $globals;
    
    // Читаем шаблон
    $string = @file_get_contents($globals["path_templates"].$template.".tpl");

    if ($string) {
    
        reset($vars);
        while (list($key, $value) = each($vars)) {

            // Производим замену всех переменных их значениями
            $string = str_replace("{".strtoupper($key)."}", $value, $string);
        }

    } else {
        $string = "<p>Шаблон с именем <em>".$template."</em> не обнаружен.</p>";
    }

    return $string;
}

///////////////////////////////////////////////////////////////////////////////
//                                                                           //
//     Функция расчета и преобразования идентификатора страницы в ссылку     //
//                                                                           //
///////////////////////////////////////////////////////////////////////////////

function get_rewrite_link($id, $base_url="") {

    global $globals;

    // Обрабатываем включенный режим преобразования ссылок
    if ($globals["rewrite_flag"]) {

        // Формируем статическую ссылку
        $link = $base_url.$id.".htm";

    } else {

        // Формируем динамическую ссылку на страничку или на запись в блоге
        if ($globals["blog_flag"] && preg_match($globals["regexp_blog_id"], $id)) {
            $link = $base_url."blog.php?id=".$id;
        } else {
            $link = $base_url."pages.php?id=".$id;
        }
    }

    return $link;
}

///////////////////////////////////////////////////////////////////////////////
//                                                                           //
//            Функция расчета ссылки на страничку с которой пришли           //
//                                                                           //
///////////////////////////////////////////////////////////////////////////////

function get_referer_link() {

    global $globals;

    // Определяем с какой странички пришел пользователь
    if (!empty($_SERVER["HTTP_REFERER"])) {

        // Находим адрес странички для возврата
        $link = substr(strrchr($_SERVER["HTTP_REFERER"], "/"), 1);

        // Обрабатываем включенный режим преобразования ссылок
        if ($globals["rewrite_flag"]) {

            // Обрабатываем статические ссылки
            if (!preg_match("/[A-Z0-9_-]+\.htm$/iu", $link)) {
                $link = "./";
            }

        } else {

            // Обрабатываем динамические ссылки
            if (!preg_match("/^(pages|blog)\.php\?id=([A-Z0-9_-]+)$/iu", $link)) {
                $link = "./";
            }
        }

    } else {
        $link = "./";
    }

    return $link;
}

///////////////////////////////////////////////////////////////////////////////
//                                                                           //
//                  Функция транслитерации строки в ASCII-код                //
//                                                                           //
///////////////////////////////////////////////////////////////////////////////

function calculate_ascii_string($string) {

    global $globals;

    // Преобразуем строку в нижний регистр
    $string = strtolower(trim($string));

    // Преобразуем русские буквы в латинские
    $string = strtr($string, $globals["transliteration"]);

    // Убираем из строки все спецсимволы
    $string = preg_replace("/[^a-z0-9_ -]/u", "", $string);

    // Заменяем все двойные пробелы на одинарные
    $string = str_replace("  ", " ", $string);

    // Заменяем все пробелы на подчеркивание
    $string = str_replace(" ", "_", $string);

    // Ограничиваем длину строки 100 символами
    $string = substr($string, 0, 100);

    return $string;
}

?>