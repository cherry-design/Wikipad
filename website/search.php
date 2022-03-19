<?php

///////////////////////////////////////////////////////////////////////////////
//                                                                           //
//   Простой онлайн блокнот "Wikipad" // Простой поиск                       //
//   ----------------------------------------------------------------------  //
//   Copyright (C) 1998-2022 Studio "Cherry-Design"                          //
//   URL: https://www.cherry-design.com/                                     //
//   E-mail: mike@cherry-design.com                                          //
//                                                                           //
///////////////////////////////////////////////////////////////////////////////

// Имя данного скрипта
$this_script = "search.php"; 

// Производим инициализацию
require("includes/initialization.php"); 

// Если включен режим обязательной авторизации для просмотра сайта и пользователь
// не авторизован или не включен режим поиска по сайту, то делаем редирект на первую страницу
if ($globals["hidden_flag"] && !$globals["user_entry_flag"] || !$globals["search_flag"]) {
    header("Location: ./");
}

// Количество записей на страницу в результатах поиска
$globals["records_per_page"] = 10; 

// Флаг кэширования поискового индекса
$globals["cache_flag"] = 1;

// Актуальное время жизни поискового индекса в кэше в секундах
$globals["cache_time"] = 3600;

// Имя файла, хранящего кэшированное значение индекса
$globals["cache_filename"] = "search_index.dat";

// Способ трактовки пробелов между словами в поисковом запросе
$globals["space_boolean"] = "and";

// Веса ключевых слов, встретившихся при поиске в заголовке, тегах и тексте страницы
$globals["relevance"] = array(
    "title" => 5,
    "tags"  => 3,
    "text"  => 1
);

///////////////////////////////////////////////////////////////////////////////
//                                                                           //
//     Функция предварительной обработки и получения списка ключевых слов    //
//                                                                           //
///////////////////////////////////////////////////////////////////////////////

function get_keywords_list($keywords) {

    global $globals;

    // Преобразуем ключевые слова в нижний регистр
    $keywords = strtolower(trim(stripslashes($keywords)));

    // Заменяем все двойные пробелы на одинарные
    $keywords = str_replace("  ", " ", $keywords);

    // Получаем массив ключевых слов
    $keywords_list = explode(" ", $keywords);

    // Формируем логические операции между словами и удаляем повторяющиеся слова
    $temp = "";
    $boolean = $globals["space_boolean"];
    reset($keywords_list);
    while (list($key, $value) = each($keywords_list)) {

        // Если слово является логической операцией, то меняем тип операции между словами
        if ($value == "and" || $value == "or" || $value == "not") {
            $boolean = $value;
        } else {
            $temp[$value] = $boolean;
            $boolean = $globals["space_boolean"];
        }
    }
    $keywords_list = $temp;

    if (!empty($keywords_list)) {

        // Удаляем ключевые слова короче 3-х букв
        reset($keywords_list);
        while (list($keyword, $value) = each($keywords_list)) {
            if (strlen($keyword) < 3) {
                unset($keywords_list[$keyword]);
            }
        }
    }

    return $keywords_list;
}

///////////////////////////////////////////////////////////////////////////////
//                                                                           //
//            Функция формирующая поисковый индекс из файлов страничек       //
//                                                                           //
///////////////////////////////////////////////////////////////////////////////

function get_search_index() {

    global $globals;

    // Рассчитываем полный путь к файлу, хранящему кэш поискового индекса
    $cache_filename = $globals["path_temp"]."/".$globals["cache_filename"];

    // Сначала пробуем прочитать поисковый индекс из кэша
    if ($globals["cache_flag"] && file_exists($cache_filename)) {

        // Рассчитываем время в секундах прошедшее с последнего обновления кэша
        $cache_time = time() - filemtime($cache_filename);

        // Проверяем актуальность кэша
        if ($cache_time < $globals["cache_time"]) {

            // Читаем из кэша строку с описанием поискового индекса
            $search_index_serial = @file_get_contents($cache_filename);

            // Преобразуем строку в массив с поисковым индексом
            $search_index = unserialize($search_index_serial);

            // Возвращаем кэшированное значение поискового индекса
            return $search_index;
        }
    }

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

    // Начинаем рассчитывать значение поискового индекса
    $search_index = "";

    if (!empty($files)) {

        // Сортируем странички по дате создания
        arsort($files);

        // Обрабатываем список файлов, формируя поисковый индекс
        reset($files);
        while (list($filename, $modification_time) = each($files)) {

            // Находим идентификатор страницы
            $id = substr($filename, 0, -strlen(strrchr($filename, ".")));

            // Читаем текст страницы
            $text = @file($globals["path_pages"].$filename);

            // Находим название страницы
            $title = implode("", array_slice($text,0,1));
            $title = trim(substr(trim($title), 1, -1));

            // Удаляем заголовок из текста страницы
            $text = array_slice($text, 2);
            $text = implode("", $text);

            // Читаем список тегов
            $meta = parse_meta($text);
            if (!empty($meta["tags"])) {
                $tags = $meta["tags"];
            } else {
                $tags = "";
            }

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

            // Добавляем информацию в поисковый индекс
            $search_index[$id] = array(
                "id"    => $id,
                "title" => $title,
                "tags"  => $tags,
                "text"  => $text
            );
        }

        // Сохраняем поисковый индекс в кэше
        if ($globals["cache_flag"] && !empty($search_index)) {

            // Преобразуем массив в строку для сохранения в кэше
            $search_index_serial = serialize($search_index);

            // Сохраняем строку с поисковым индексом в файле
            $fp = fopen($cache_filename,"w+");
            fwrite($fp, $search_index_serial);
            fclose($fp);
        }
    }

    // Возвращаем рассчитанное значение поискового индекса
    return $search_index;
}

///////////////////////////////////////////////////////////////////////////////
//                                                                           //
//      Функция осуществляющая поиск страниц, удовлетворяющих запросу        //
//                                                                           //
///////////////////////////////////////////////////////////////////////////////

function get_search_results($keywords_list) {

    global $globals;

    // Формируем поисковый индекс
    $search_index = get_search_index();

    // Осуществляем поиск в индексе
    if ($search_index && $keywords_list) {

        $results = "";

        // Ищем документы, удовлетворяющие ключевым словам
        reset($search_index);
        while (list($id, $record) = each($search_index)) {

            // Инициализируем флаг удовлетворяющего запроса
            if ($globals["space_boolean"] == "and") {
                $boolean_flag = 1;
            } else {
                $boolean_flag = 0;
            }

            // Устанавливаем исходную релевантность страницы
            $record["relevance"] = 0;

            reset($keywords_list);
            while (list($keyword, $boolean) = each($keywords_list)) {

                // Обнуляем флаг наличия ключевого слова
                $check_flag = 0;

                // Устанавливаем исходную релевантность ключевого слова
                $relevance = 0;

                // Проверяем наличие ключевого слова в заголовке и устанавливаем релевантность
                if (stristr($record["title"], $keyword)) { 
                    $check_flag = 1;
                    $relevance += $globals["relevance"]["title"];
                }

                // Проверяем наличие ключевого слова в тегах
                if ($num_matches = preg_match("/".$keyword."/iu", $record["tags"])) { 
                    $check_flag = 1;
                    $relevance += $globals["relevance"]["tags"];
                }

                // Проверяем наличие ключевого слова в тексте статьи и устанавливаем релевантность
                if ($num_matches = preg_match("/".$keyword."/iu", $record["text"])) { 
                    $check_flag = 1;
                    $relevance += $num_matches * $globals["relevance"]["text"];
                }

                // Формируем результирующий флаг удовлетворяющего запроса
                if ($boolean == "not") {
                    $boolean_flag = $boolean_flag && !$check_flag;
                } elseif ($boolean == "and") {
                    $boolean_flag = $boolean_flag && $check_flag;
                } else {
                    $boolean_flag = $boolean_flag || $check_flag;
                }

                // Добавляем результирующую релевантность в описание страницы
                if ($boolean != "not") {
                    $record["relevance"] += $relevance;
                }
            }

            // Если документ удовлетворяет запросу, то добавляем его в результаты
            if ($boolean_flag) {
                $results[] = $record;
            }
        }

        // Сортируем результаты поиска по релевантности
        if (!empty($results) && count($results) > 1) {

            // Вначале формируем промежуточный массив с релевантностью
            $relevances = "";
            reset($results);
            while (list($id, $record) = each($results)) {
                $relevances[$id] = $record["relevance"];
            }

            // Сортируем массив релевантностей по убыванию с сохранением ключей
            arsort($relevances);

            // Перестраиваем основной массив по результатам сортировки
            $temp = "";
            reset($relevances);
            while (list($id, $relevance) = each($relevances)) {
                $temp[] = $results[$id];
            }
            $results = $temp;

        }

        return $results;

    } else {

        return "";
    }
}

///////////////////////////////////////////////////////////////////////////////
//                                                                           //
//                   Функция осуществления поиска по сайту                   //
//                                                                           //
///////////////////////////////////////////////////////////////////////////////

function do_search($query) {

    global $this_script, $globals, $page;

    // Буферизируем вывод
    ob_start();

?>
<p>Введите в форме поиска одно или несколько ключевых слов. В запросе можно использовать служебные слова AND, OR и NOT, выполняющих одноименные логические операции. По умолчанию, пробел между словами, рассматривается как операция AND, т.е. будут найдены только те документы, в которых присутствуют все ключевые слова.</p>
<form action="<?php echo $this_script; ?>" method="get">
<p><input type="text" size="50" name="query" value="<?php echo htmlspecialchars(stripslashes($query)); ?>" />
<input type="submit" value=" Искать " /></p>
</form>
<?php

    // Если запрос не пустой, то начинаем поиск страничек
    if (!empty($query)) {

        // Печатаем сообщение для пользователя
        echo "<p>Ниже приведены результаты поиска по сайту. Если Вы ничего не нашли или в результатах поиска не совсем то, что нужно, то попытайтесь переформулировать свой вопрос. При составлении запроса: используйте синонимы, попробуйте изменить окончание или набрать только часть слова.</p>\n";
        echo "<hr />\n";

        // Получаем список ключевых слов
        $keywords_list = get_keywords_list($query);

        // Осуществляем непосредственный поиск
        $records = get_search_results($keywords_list);

        // Печатаем результаты поиска
        if ($records) {

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

            reset($records);
            while(list($id, $record) = each($records)) {

                // Находим местонахождение ключевого слова в описании
                reset($keywords_list);
                while (list($keyword, $boolean) = each($keywords_list)) {
                    $keyword_position = strpos(strtolower($record["text"]), $keyword);
                    if (!empty($keyword_position)) {
                        $offsets[$keyword] = $keyword_position;
                    }
                }

                if (!empty($offsets)) {
                    $keyword_offset = min($offsets);
                } else {
                    $keyword_offset = 0;
                }

                // Выделяем из текста описание, содержащее ключевые слова
                if ($keyword_offset > 450) {
                    $start_position = strpos($record["text"], " ", $keyword_offset - 150) + 1;
                    $record["text"] = "...".substr($record["text"], $start_position);
                }

                // Рассчитываем описание страницы
                if (strlen($record["text"]) > 450) {
                    $end_position = strpos($record["text"], " ", 450);
                    $description = substr($record["text"], 0, $end_position)."...";
                } else {
                    $description = $record["text"];
                }
                $record["description"] = htmlspecialchars($description);

                // Подсвечиваем в описании ключевые слова
                reset($keywords_list);
                while (list($keyword, $boolean) = each($keywords_list)) {
                    $record["description"] = preg_replace("/(".$keyword.")/iu", "<strong>\\1</strong>", $record["description"]);
                }

                // Печатаем название и краткое описание найденной страницы
                echo "<h2>".htmlspecialchars($record["title"])."</h2>\n";
                echo "<p>".$record["description"]."</p>\n";

                // Печатаем ссылку перехода для просмотра страницы
                echo "<p><a href=\"".get_rewrite_link($record["id"])."\">Перейти</a></p>\n\n";
            }

            // Печатаем навигацию по записям
            $query_string = "query=".$query;
            print_navigation($page, $globals["records_per_page"], $records_number, $query_string);

            // Сбрасываем флаг отрисовки формы
            $form_flag = 0;

        } else {

            // Печатаем сообщения для пользователя
            echo "<p>По Вашему запросу ничего не найдено.</p>\n";
        }
    }

    // Читаем буферизированный вывод в строку
    $content = ob_get_contents();
    ob_end_clean();

    // Формируем параметры страницы
    $globals["page"]["title"] = "Поиск по сайту";
    $globals["page"]["content"] = $content;
}

///////////////////////////////////////////////////////////////////////////////

if (!empty($_REQUEST["query"])) {
    $query = $_REQUEST["query"];
} else {
    $query = "";
}

// Осуществляем поиск на сайте
do_search($query);
print_page();

?>