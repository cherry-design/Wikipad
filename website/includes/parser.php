<?php

///////////////////////////////////////////////////////////////////////////////
//                                                                           //
//   Простой онлайн блокнот "Wikipad" // Парсер Wiki-разметки                //
//   ----------------------------------------------------------------------  //
//   Copyright (C) 1998-2022 Studio "Cherry-Design"                          //
//   URL: https://www.cherry-design.com/                                     //
//   E-mail: mike@cherry-design.com                                          //
//                                                                           //
///////////////////////////////////////////////////////////////////////////////

// Массив хранящий блоки Wiki-разметки
$blocks = "";

// Массив хранящий информацию о страничке
$meta = "";

///////////////////////////////////////////////////////////////////////////////
//                                                                           //
//        Функция разбора исходной Wiki-разметки на блоки (заголовок)        //
//                                                                           //
///////////////////////////////////////////////////////////////////////////////

function get_wiki_header($text) {

    // Находим составляющие заголовка
    $regexp = "/^(={2,6})(.*)\\1$/iu";
    preg_match($regexp, trim($text), $result);

    if (isset($result[1])) {  
        $level = strlen($result[1]) - 1;
    } else { 
        $level = 0;
    }

    // Формируем текст заголовка
    if (isset($result[2])) {
        $text = trim($result[2]);
    } else {
        $text = "";
    }

    // Формируем окончательные параметры заголовка
    $data = array (
        "level"   => $level,
        "text"    => $text
    );

    return $data;
}

///////////////////////////////////////////////////////////////////////////////
//                                                                           //
//        Функция разбора исходной Wiki-разметки на блоки (абзац)            //
//                                                                           //
///////////////////////////////////////////////////////////////////////////////

function get_wiki_text($text) {

    // Регулярное выражение для проверки переноса строк внутри абзаца
    $regexp = "/(\\\\{2,})$/u";

    // Разбираем текст на строки
    $rows = explode("\n", $text);
    
    $data = ""; 
    $text = ""; 

    reset($rows);
    while (list($row_id, $row) = each($rows)) {

        // Проверяем, есть ли переносы в конце строки
        if (preg_match($regexp, trim($row), $result)) {
            $text .= " ".substr(trim($row), 0, -strlen($result[1]));
            $num_breaks = strlen($result[1])-1;
        } else {
            $text .= " ".$row;
            $num_breaks = 0;
        }

        // Если перенос есть, то создаем новую запись в массиве абзаца
        if ($num_breaks) {

            // Заменяем двойные пробелы на одинарные
            $text = str_replace("  ", " ", $text);

            // Добавляем в массив абзаца очередную строку
            $data[] = array(
                "text"   => trim($text),
                "breaks" => $num_breaks
            );

            $text = "";
        }
    }

    // Добавляем в массив абзаца последнюю строку
    if (!empty($text)) {
        $data[] = array(
            "text"   => trim($text),
            "breaks" => 0
        );
    }

    return $data;
}

///////////////////////////////////////////////////////////////////////////////
//                                                                           //
//    Рекурсивная функция разбора исходной Wiki-разметки на блоки (список)   //
//                                                                           //
///////////////////////////////////////////////////////////////////////////////

function get_wiki_list($text, $counter=0) {

    // Увеличиваем счетчик вложенности
    $counter++;

    // Добавляем к списку фиктивную пустую строку
    // чтобы избежать ненужных проверок
    $text .= "\n";

    // Разбираем список на отдельные пункты
    $items = explode("\n", $text);

    $i = 0;
    $data = "";
    $string = "";
    
    $prev_list = "";
    $prev_type = "item";
    $prev_item = "";

    // Формируем массив списка
    reset($items);
    while (list($item_id, $item) = each($items)) {

        // Анализируем первый символ и выясняем является ли элемент вложенным списком
        if (substr($item, 0, 1) == "*") {
            $type = "unordered";
        } elseif (substr($item, 0, 1) == "#") {
            $type = "ordered";
        } else {
            $type = "item";
        }

        // Удаляем символ "*" или "#", если элемент является списком
        if ($type == "unordered" || $type == "ordered") {
            $item = substr($item, 1);
        }

        // Корректируем текущий тип обрабатываемого списка
        if ($prev_type != "item" && (empty($prev_list) || $prev_list != $prev_type)) {
            $prev_list = $prev_type;
        }

        // Если найдены все элементы списка то добавляем информацию о нем в массив списка
        if ($prev_type == "item" || $prev_type != $type) {

            // Убираем лишний перевод каретки справа
            $string = rtrim($string);

            // Рекурсивно вызываем функцию для учета вложенных списков,
            // ограничивая уровень вложенности десятью уровнями
            if ($prev_type != "item" && $counter <= 10) {

                // Рекурсивно вызываем функцию
                $list = get_wiki_list($string, $counter);

                if (empty($data[$i-1]["list"])) {

                    // Cохраняем вложенный список в предыдущем пункте списка
                    $data[$i-1]["list"] = $list;
    
                    // Меняем тип предыдущего пункта
                    $data[$i-1]["type"] = $prev_list;

                } else {

                    $i++;

                    // Формируем новую запись в массиве списков
                    $data[$i] = array (
                        "type" => $prev_type,
                        "item" => trim($prev_item),
                        "list" => $list
                    );
                }

            } else {

                // Формируем новую запись в массиве списков
                $data[$i] = array (
                    "type" => $prev_type,
                    "item" => trim($prev_item)
                );

                $i++;
            }

            // Очищаем строку, накапливающую информацию для вложенных списков
            $string = "";
        }

        // Добавляем очередной элемент в строку, для последующего анализа вложенного списка
        $string .= $item."\n";

        // Сохраняем предыдущий тип элемента и его содержание
        $prev_type = $type;
        $prev_item = $item;
    }

    // Удаляем все пустые элементы списка (включая фиктивный пункт)
    $temp_data = "";
    if (!empty($data)) {
        reset($data);
        while(list($item_id, $item) = each($data)) {
            if (!($item["type"] == "item" && empty($item["item"]))) {
                $temp_data[] = $item;
            }
        }
    }

    return $temp_data;
}

///////////////////////////////////////////////////////////////////////////////
//                                                                           //
//   Рекурсивная функция разбора исходной Wiki-разметки на блоки (отступы)   //
//                                                                           //
///////////////////////////////////////////////////////////////////////////////

function get_wiki_indent($text, $counter=0) {

    // Увеличиваем счетчик вложенности
    $counter++;

    // Добавляем к списку отступов фиктивную пустую строку
    // чтобы избежать ненужных проверок
    $text .= "\n";

    // Разбираем список отступов на отдельные пункты
    $items = explode("\n", $text);

    $i = 0;
    $data = "";
    $string = "";
    
    $prev_type = "item";
    $prev_item = "";

    // Формируем массив списка отступов
    reset($items);
    while (list($item_id, $item) = each($items)) {

        // Анализируем первый символ и выясняем является ли элемент вложенным отступом
        if (substr($item, 0, 1) == ":") {
            $type = "indent";
        } else {
            $type = "item";
        }

        // Удаляем символ ":", если элемент является отступом
        if ($type == "indent") {
            $item = substr($item, 1);
        }

        // Если найдены все элементы списка отступов, то добавляем информацию о нем в массив
        if ($prev_type == "item" || $prev_type != $type) {

            // Убираем лишний перевод каретки справа
            $string = rtrim($string);

            // Рекурсивно вызываем функцию для учета вложенных оступов,
            // ограничивая уровень вложенности десятью уровнями
            if ($prev_type != "item" && $counter <= 10) {

                // Рекурсивно вызываем функцию и сохраняем вложенный 
                // отступ в предыдущем пункте списка отступов
                $data[$i-1]["indent"] = get_wiki_indent($string, $counter);

                // Меняем тип предыдущего пункта
                $data[$i-1]["type"] = "indent";

            } else {

                // Формируем новую запись в массиве отступов
                $data[$i] = array (
                    "type"   => $prev_type,
                    "item"   => trim($prev_item)
                );

                $i++;
            }

            // Очищаем строку, накапливающую информацию для вложенных списков
            $string = "";
        }

        // Добавляем очередной элемент в строку, для последующего анализа вложенного отступа
        $string .= $item."\n";

        // Сохраняем предыдущий тип элемента и его содержание
        $prev_type = $type;
        $prev_item = $item;
    }

    // Удаляем все пустые элементы списка (включая фиктивный пункт)
    $temp_data = "";
    if (!empty($data)) {
        reset($data);
        while(list($item_id, $item) = each($data)) {
            if (!($item["type"] == "item" && empty($item["item"]))) {
                $temp_data[] = $item;
            }
        }
    }

    return $temp_data;
}

///////////////////////////////////////////////////////////////////////////////
//                                                                           //
//       Функция разбора исходной Wiki-разметки на блоки (определения)       //
//                                                                           //
///////////////////////////////////////////////////////////////////////////////

function get_wiki_definition($text) {

    // Регулярное выражение для нахождения параметров определения
    $regexp = "/^;([^:]*)(:(.*))?$/iu";

    // Разбираем список на отдельные пункты
    $rows = explode("\n", $text);

    $data = "";

    // Формируем массив определений
    reset($rows);
    while (list($row_id, $row) = each($rows)) {

        // Находим составляющие определения
        preg_match($regexp, $row, $result);
        
        if (isset($result[1])) {  
            $term = trim($result[1]);  
        } else { 
            $term = ""; 
        }
        
        if (isset($result[3])) {
            $definition = trim($result[3]);
        } else {
            $definition = "";
        }

        // Добавляем в массив очередное определение
        $data[] = array(
            "term"       => $term,
            "definition" => $definition
        );
    }

    return $data;
}

///////////////////////////////////////////////////////////////////////////////
//                                                                           //
//        Функция разбора исходной Wiki-разметки на блоки (таблица)          //
//                                                                           //
///////////////////////////////////////////////////////////////////////////////

function get_wiki_table($text) {

    // Разбиваем описание таблицы на строки
    $rows = explode("\n", $text);
    
    // Присваиваем таблице характеристики по умолчанию
    $table_caption = "";
    $table_width = "";
    $table_align = "";
    $table_highlight = 0;

    // Анализируем первую строку, проверяя есть ли там дополнительная информация о таблице
    reset($rows);
    list($row_id, $first_row) = each($rows);
    if (substr(trim($first_row),-1,1) != "|" && substr(trim($first_row),-1,1) != "!") {

        $table_properties = preg_split("/\||!/u", trim($first_row));
        unset($table_properties[0]);

        // Формируем характеристики таблицы
        reset($table_properties);
        while (list($property_id, $property) = each($table_properties)) {

            // Отслеживаем ширину таблицы
            if (preg_match("/^([0-9]+(px|%))$/iu", trim($property), $property_result)) {
                $table_width = $property_result[1];
                unset($table_properties[$property_id]);
            }

            // Отслеживаем выравнивание таблицы
            if (preg_match("/^left|center|right$/iu", trim($property))) {
                $table_align = trim($property);
                unset($table_properties[$property_id]);
            }

            // Отслеживаем необходимость подсветки строк
            if (preg_match("/^highlight$/iu", trim($property))) {
                $table_highlight = trim($property);
                unset($table_properties[$property_id]);
            }
        }

        // Находим заголовок таблицы
        if (!empty($table_properties)) {

            // Переходим к последнему элементу в списке
            end($table_properties);
            list($property_id, $property) = each($table_properties);

            // Присваиваем заголовку таблицы значение последнего элемента в списке
            $table_caption = trim($property);
        }

        // Удаляем первую строку из таблицы
        unset($rows[$row_id]);
    }

    // Определяем максимальное количество разделителей в строке
    $max_num_delimiters = 0;
    reset($rows);
    while (list($row_id, $row) = each($rows)) {
        
        // Добавляем закрывающий символ, если он отсутствует
        if (substr(trim($row), -1, 1) != "|" && substr(trim($row), -1, 1) != "!") {
            $row .= "|";
        }
        
        // Находим число разделителей, как сумму количества знаков "|" и "!"
        $num_delimiters = substr_count($row, "|") + substr_count($row, "!");
        if ($num_delimiters > $max_num_delimiters) {
            $max_num_delimiters = $num_delimiters;
        }
    }

    // Корректируем данные таблицы, добавляя недостающие разделители 
    // для правильного отображения таблицы
    reset($rows);
    while (list($row_id, $row) = each($rows)) {

        $num_delimiters = substr_count($row, "|") + substr_count($row, "!");
        $rows[$row_id] = $row." ".str_repeat("|", $max_num_delimiters - $num_delimiters);
    }

    // Формируем данные таблицы
    $row_counter = 1;
    $table = "";
    reset($rows);
    while (list($row_id, $row) = each($rows)) {

        $cells = "";

        // Разбираем строку на составляющие
        $row = preg_split("/([\|!]+)/u", trim($row), -1, PREG_SPLIT_DELIM_CAPTURE);

        // Рассчитываем число ячеек в текущей строке
        $num_cells = count($row) - 2;

        // Формируем ячейки таблицы
        for ($i=2; $i<$num_cells; $i+=2) {

            // Определяем тип ячейки
            if (substr($row[$i-1], -1, 1) == "!") {
                $type = "header";
            } else {
                $type = "normal";
            }

            // Определяем количество ячеек для объединения
            $span = strlen($row[$i+1]);

            // Определяем выравнивание в ячейке
            if (substr($row[$i], 0, 1) == " ") {
                if (substr($row[$i], -1, 1) == " ") {
                    $align = "center";
                } else {
                    $align = "right";
                }
            } else {
                if (substr($row[$i], -1, 1) == " ") {
                    $align = "left";
                } else {
                    $align = "";
                }
            }

            // Формируем данные по ячейке
            $data_cell = array(
                "type"    => $type,
                "span"    => $span,
                "align"   => $align,
                "content" => trim($row[$i])
            );
            
            $cells[] = $data_cell;
        }

        if ($table_highlight && ($row_counter/2) != floor($row_counter/2)) {
            $highlight = 1;
        } else {
            $highlight = 0;
        }

        // Формируем данные по строке
        if (!empty($cells)) {
            $data_row = array(
                "highlight" => $highlight,
                "row"       => $cells
            );

            $table[] = $data_row;

            $row_counter++;
        }
    }

    // Формируем окончательные данные таблицы
    $data_table = array(
        "caption"    => $table_caption,
        "width"      => $table_width,
        "align"      => $table_align,
        "table"      => $table
    );

    return $data_table;
}

///////////////////////////////////////////////////////////////////////////////
//                                                                           //
//              Функция разбора исходной Wiki-разметки на блоки              //
//                                                                           //
///////////////////////////////////////////////////////////////////////////////

function get_wiki_blocks($text) {

    global $blocks, $meta;

    $blocks = "";
    $meta = "";

    // Разбиваем текст на строки
    $strings = str_replace("\r\n", "\n", $text);
    $strings = explode ("\n", $strings);

    $i = 0;
    $type = "";
    $prev_type = "";

    // Анализируем текст и разбиваем его по блокам
    reset($strings);
    while (list($id, $string) = each($strings)) {

        if ($type != "nowiki") {

            // Анализируем первый символ в строке
            $first_letter = substr($string, 0, 1);

            if ($first_letter == "") { 
                $type = "space";
            } elseif ($first_letter == "<" && strtolower(trim($string)) == "<nowiki>") { 
                $type = "nowiki";
            } elseif ($first_letter == "-" && substr($string, 0, 4) == "----") { 
                $type = "line";
            } elseif ($first_letter == " ") { 
                $type = "code";
            } elseif ($first_letter == "=" && substr(trim($string), 0, 2) == "==" && substr(trim($string), -2) == "==") {
                $type = "header";
            } elseif ($first_letter == "*" || $first_letter == "#") {
                $type = "list";
            } elseif ($first_letter == ":") {
                $type = "indent";
            } elseif ($first_letter == ";") {
                $type = "definition";
            } elseif ($first_letter == "|" || $first_letter == "!") {
                $type = "table";
            } else {
                $type = "text";
            }
        }

        // Формируем массив блоков
        if ($type == $prev_type && ($type == "code" || $type == "nowiki" || $type == "list" || $type == "indent" || $type == "text" || $type == "table" || $type == "definition")) {

            // Проверяем, не достигли ли мы конца блока NOWIKI и заодно удаляем закрывающий тег
            if ($type == "nowiki" && strtolower(trim($string)) == "</nowiki>") {
                $type = "";
            } else {

                // Добавляем строку к существующему содержимому блока
                $blocks[$i]["content"] .= "\n".$string;
            }

        } else {

            $i++;

            // Удаляем из блока NOWIKI открывающий тег
            if ($type == "nowiki" && strtolower(trim($string)) == "<nowiki>") {
                $string = "\n";
            }

            // Создаем новый блок 
            $blocks[$i]["type"] = $type;
            $blocks[$i]["content"] = $string;
            $prev_type = $type;
        }
    }

    // Производим логическую обработку блоков
    reset($blocks);
    while (list($id, $block) = each($blocks)) {

        if ($block["type"] == "space") {

            // Удаляем пустые блоки
            unset($blocks[$id]);

        } elseif ($block["type"] == "header") {

            // Производим разбор заголовков в формате Wiki
            $blocks[$id]["content"] = get_wiki_header($block["content"]);

        } elseif ($block["type"] == "text") {

            // Производим разбор абзацев в формате Wiki
            $blocks[$id]["content"] = get_wiki_text($block["content"]);

        } elseif ($block["type"] == "list") {

            // Производим разбор списка в формате Wiki
            $blocks[$id]["content"] = get_wiki_list($block["content"]);

        } elseif ($block["type"] == "indent") {

            // Производим разбор списка в формате Wiki
            $blocks[$id]["content"] = get_wiki_indent($block["content"]);

        } elseif ($block["type"] == "definition") {

            // Производим разбор определений в формате Wiki
            $blocks[$id]["content"] = get_wiki_definition($block["content"]);

        } elseif ($block["type"] == "table") {

            // Производим разбор таблицы в формате Wiki
            $blocks[$id]["content"] = get_wiki_table($block["content"]);
        }
    }
}

// ............................................................................

///////////////////////////////////////////////////////////////////////////////
//                                                                           //
//     Рекурсивная функция парсинга Wiki-разметки списка в формат XHTML      //
//                                                                           //
///////////////////////////////////////////////////////////////////////////////

function parse_wiki_list_recursive($data) {

    $string = "";

    if (!empty($data)) {

        reset($data);
        while(list($item_id, $item) = each($data)) {

            $string .= "<li>".htmlspecialchars($item["item"]);

            if ($item["type"] == "unordered") {

                $string .= "\n<ul>\n";
                $string .= parse_wiki_list_recursive($item["list"]);
                $string .= "</ul>\n";

            } elseif ($item["type"] == "ordered") {
            
                $string .= "\n<ol>\n";
                $string .= parse_wiki_list_recursive($item["list"]);
                $string .= "</ol>\n";
            }

            $string .= "</li>\n";
        }
    }

    return $string;
}

///////////////////////////////////////////////////////////////////////////////
//                                                                           //
//            Функция парсинга Wiki-разметки списка в формат XHTML           //
//                                                                           //
///////////////////////////////////////////////////////////////////////////////

function parse_wiki_list($data) {

    $string = "";

    if (!empty($data)) {

        reset($data);
        while(list($item_id, $item) = each($data)) {

            if ($item["type"] == "unordered") {

                $string .= "<ul>\n";
                $string .= parse_wiki_list_recursive($item["list"]);
                $string .= "</ul>\n";

            } elseif ($item["type"] == "ordered") {

                $string .= "<ol>\n";
                $string .= parse_wiki_list_recursive($item["list"]);
                $string .= "</ol>\n";
            }
        }
    }

    return $string;
}

///////////////////////////////////////////////////////////////////////////////
//                                                                           //
//     Рекурсивная функция парсинга Wiki-разметки отступов в формат XHTML    //
//                                                                           //
///////////////////////////////////////////////////////////////////////////////

function parse_wiki_indent_recursive($data) {

    $string = "";

    reset($data);
    while(list($item_id, $item) = each($data)) {

        // Печатаем текст
        $string .= "<dd>".htmlspecialchars($item["item"]);

        // Печатаем вложенный отступ
        if ($item["type"] == "indent") {

            $string .= "\n<dl>\n";
            $string .= parse_wiki_indent_recursive($item["indent"]);
            $string .= "</dl>\n";
        }

        $string .= "</dd>\n";
    }

    return $string;
}

///////////////////////////////////////////////////////////////////////////////
//                                                                           //
//          Функция парсинга Wiki-разметки отступов в формат XHTML           //
//                                                                           //
///////////////////////////////////////////////////////////////////////////////

function parse_wiki_indent($data) {

    $string = "";

    if (!empty($data)) {

        reset($data);
        while(list($item_id, $item) = each($data)) {

            // Печатаем внешний отступ
            if ($item["type"] == "indent") {

                $string .= "<dl>\n";
                $string .= parse_wiki_indent_recursive($item["indent"]);
                $string .= "</dl>\n";
            }
        }
    }

    return $string;
}

///////////////////////////////////////////////////////////////////////////////
//                                                                           //
//           Функция парсинга Wiki-разметки определений в формат HTML        //
//                                                                           //
///////////////////////////////////////////////////////////////////////////////

function parse_wiki_definition($data) {

    $string = "";

    if (!empty($data)) {

        $string .= "<dl>\n";

        // Формируем список определений
        reset($data);
        while (list($id, $record) = each($data)) {
        
            $string .= "<dt>".htmlspecialchars($record["term"])."</dt>\n";
            $string .= "<dd>".htmlspecialchars($record["definition"])."</dd>\n";
        }
        
        $string .= "</dl>\n";
    }

    return $string;
}

///////////////////////////////////////////////////////////////////////////////
//                                                                           //
//      Функция парсинга базовой Wiki-разметки в формат HTML (таблица)       //
//                                                                           //
///////////////////////////////////////////////////////////////////////////////

function parse_wiki_table($data) {

    $string = "";

    // Формируем таблицу только в том случае, если есть данные
    if (!empty($data["table"])) {

        // Находим ширину таблицы
        if (!empty($data["width"])) {
            $width = " style=\"width: ".$data["width"].";\"";
        } else {
            $width = "";
        }

        // Находим выравнивание таблицы
        if (!empty($data["align"])) {
            $align = " class=\"".$data["align"]."\"";
        } else {
            $align = "";
        }

        $string = "<table".$width.$align.">\n";

        // Формируем заголовок таблицы, если он указан
        if (!empty($data["caption"])) {
            $string .= "<caption".$align.">".htmlspecialchars($data["caption"])."</caption>\n";
        }

        // Начинаем формирование таблицы
        reset($data["table"]);
        while (list($row_id, $row) = each($data["table"])) {

            // Формируем строку
            if ($row["highlight"]) {
                $string .= "<tr class=\"highlight\">\n";
            } else {
                $string .= "<tr>\n";
            }

            reset($row["row"]);
            while(list($cell_id, $cell) = each($row["row"])) {

                // Начинаем формирование ячейки
                if ($cell["type"] == "header") {
                    $string .= "<th ";
                } else {
                    $string .= "<td ";
                }
                
                // Объединяем ячейки, в случае необходимости
                if ($cell["span"] > 1) {
                    $string .= "colspan=\"".$cell["span"]."\" ";
                }

                // Указываем выравнивание ячейки
                if (!empty($cell["align"])) {
                    $string .= "style=\"text-align: ".$cell["align"].";\" ";
                }

                $string = substr($string, 0, -1).">";

                // Добавляем содержимое ячейки
                if ($cell["content"] != "") {
                    $string .= htmlspecialchars($cell["content"]);
                } else {
                    $string .= "&nbsp;";
                }

                // Заканчиваем формирование ячейки
                if ($cell["type"] == "header") {
                    $string .= "</th>\n";
                } else {
                    $string .= "</td>\n";
                }
            }

            $string .= "</tr>\n";
        }

        $string .= "</table>\n";
    }

    return $string;
}

///////////////////////////////////////////////////////////////////////////////
//                                                                           //
//        Функция парсинга базовой Wiki-разметки в формат HTML (абзацы)      //
//                                                                           //
///////////////////////////////////////////////////////////////////////////////

function parse_wiki_text($data) {

    $string = "";

    if (!empty($data)) {

        $string .= "<p>";

        reset($data);
        while (list($id, $record) = each($data)) {

            // Добавляем содержимое строки
            $string .= htmlspecialchars($record["text"]);

            // Проверяем, нужно ли делать переносы после этой строки
            if ($record["breaks"]) {
                $string .= str_repeat("<br />", $record["breaks"])."\n";
            }
        }

        $string .= "</p>\n";
    }

    return $string;
}

///////////////////////////////////////////////////////////////////////////////
//                                                                           //
//            Функция парсинга базовой Wiki-разметки в формат HTML           //
//                                                                           //
///////////////////////////////////////////////////////////////////////////////

function parse_wiki_blocks() {

    global $globals, $blocks;

    // Производим обработку блоков
    reset($blocks);
    while (list($id, $block) = each($blocks)) {

        if ($block["type"] == "code") {

            $block["parsed_content"]  = "<pre>";
            $block["parsed_content"] .= htmlspecialchars($block["content"]);
            $block["parsed_content"] .= "</pre>\n";

        } elseif ($block["type"] == "nowiki") {

            $block["parsed_content"] = $block["content"];

        } elseif ($block["type"] == "line") {

            $block["parsed_content"] = "<hr />\n";

        } elseif ($block["type"] == "list") {
        
            // Вызываем внешнюю функции парсинга списка
            $block["parsed_content"] = parse_wiki_list($block["content"]);

        } elseif ($block["type"] == "indent") {

            // Вызываем внешнюю функции парсинга отступов
            $block["parsed_content"] = parse_wiki_indent($block["content"]);

        } elseif ($block["type"] == "definition") {

            // Вызываем внешнюю функции парсинга определений
            $block["parsed_content"] = parse_wiki_definition($block["content"]);

        } elseif ($block["type"] == "table") {

            // Вызываем внешнюю функции парсинга таблицы
            $block["parsed_content"] = parse_wiki_table($block["content"]);

        } elseif ($block["type"] == "header") {
        
            if ($block["content"]["level"] > 0) {

                $block["parsed_content"]  = "<h".($block["content"]["level"] + 1).">";
                $block["parsed_content"] .= htmlspecialchars($block["content"]["text"]);
                $block["parsed_content"] .= "</h".($block["content"]["level"] + 1).">\n";

            } else {
                
                // Вызываем внешнюю функции парсинга абзацев
                $block["parsed_content"]  = parse_wiki_text($block["content"]);
            }

        } else {
        
            // Вызываем внешнюю функцию парсинга абзацев
            $block["parsed_content"]  = parse_wiki_text($block["content"]);
        }

        $blocks[$id] = $block;
    }
}

// ............................................................................

///////////////////////////////////////////////////////////////////////////////
//                                                                           //
//                         Функция парсинга мета-тега                        //
//                                                                           //
///////////////////////////////////////////////////////////////////////////////

function parse_wiki_meta($meta_tag) {

    global $meta;

    // Формируем регулярное выражение для нахождения параметров мета-тега
    $regexp = "/^meta:(tags|keywords|description)\|(.+)$/iu";

    // Добавляем мета-тег в общий массив
    if (preg_match($regexp, $meta_tag, $result)) {

        // Находим имя мета-тега и его значение
        $name = $result[1];
        $value = $result[2];

        // Производим раскодирование спецсимволов
        $html_translation_table = array_flip(get_html_translation_table(HTML_SPECIALCHARS));
        $value = strtr($value, $html_translation_table);

        // Сохраняем мета-тег в общем массиве
        $meta[$name] = $value;

	    // Удаляем мета-тег из исходной страницы
    	$replace_text = "";

    } else {

        // Формируем сообщение об ошибке
        $replace_text = "<span class=\"error\">".$meta_tag."</span>\n";
    }

    return $replace_text;
}

///////////////////////////////////////////////////////////////////////////////
//                                                                           //
//                     Функция парсинга тега изображения                     //
//                                                                           //
///////////////////////////////////////////////////////////////////////////////

function parse_wiki_image($image_tag) {

    global $globals;

    $filename = "";

    // Присваиваем изображению атрибуты по умолчанию
    $image_alt = "";
    $image_align = "";
    $image_frame = 0;

    // Разбиваем псевдотег изображения на составляющие
    $image_properties = preg_split("/\|/u", $image_tag, -1, PREG_SPLIT_NO_EMPTY);

    // Формируем характеристики изображения
    reset($image_properties);
    while (list($property_id, $property) = each($image_properties)) {

        // Находим имя файла
        if (preg_match("/^image:([a-z0-9_-]+\.(gif|jpg|png|swf))$/iu", trim($property), $property_result)) {
            if (isset($property_result[1])) {
                $filename = trim($property_result[1]);
            }
            unset($image_properties[$property_id]);
        }

        // Отслеживаем выравнивание изображения
        if (preg_match("/^left|center|right$/iu", trim($property))) {
            $image_align = trim($property);
            unset($image_properties[$property_id]);
        }

        // Отслеживаем рамку изображения
        if (preg_match("/^frame$/iu", trim($property))) {
            $image_frame = 1;
            unset($image_properties[$property_id]);
        }
    }

    // Находим альтернативный текст к иллюстрации
    if (!empty($image_properties)) {

        // Переходим к последнему элементу в списке
        end($image_properties);
        list($property_id, $property) = each($image_properties);

        // Присваиваем альтернативному тексту значение последнего элемента в списке
        $image_alt = trim($property);
        $image_description = $image_alt;
        $image_alt_string = preg_replace("/''+/u", "", $image_alt);

    } else {
        $image_alt_string = "Image:".$filename;
    }

    // Проверяем, что такой файл существует
    if (!empty($filename) && file_exists($globals["path_files"].$filename)) {

        // Находим размеры исходного изображения
        $image_src = $globals["path_files"].$filename;
        $image_size = getimagesize($image_src); 
        $image_width = $image_size[0];
        $image_height = $image_size[1];
        $image_mime_type = $image_size["mime"];

        // Формируем текст для замены
        if ($image_frame) {

            // Формируем рамку вокруг изображения
            $replace_text = "<span class=\"image_frame\" style=\"width:".$image_width."px\">";

            // Формируем HTML-код изображения
            if ($image_mime_type == "application/x-shockwave-flash") {
                $replace_text .= "<object type=\"application/x-shockwave-flash\" style=\"width: ".$image_width."px; height: ".$image_height."px;\" data=\"".$image_src."\"><param name=\"movie\" value=\"".$image_src."\" /></object>";
            } else {
                $replace_text .= "<img src=\"".$image_src."\" style=\"width: ".$image_width."px; height: ".$image_height."px;\" alt=\"".htmlspecialchars($image_alt_string)."\" title=\"".htmlspecialchars($image_alt_string)."\" />";
            }

            // Формируем подпись к фотографии
            if (!empty($image_description)) {
                $replace_text .= "<span class=\"image_frame_text\">".$image_description."</span>";
            }
            $replace_text .= "</span>";

        } else {

            // Формируем HTML-код изображения
            if ($image_mime_type == "application/x-shockwave-flash") {
                $replace_text = "<object type=\"application/x-shockwave-flash\" style=\"width: ".$image_width."px; height: ".$image_height."px;\" data=\"".$image_src."\"><param name=\"movie\" value=\"".$image_src."\" /></object>";
            } else {
                $replace_text = "<img src=\"".$image_src."\" style=\"width: ".$image_width."px; height: ".$image_height."px;\" alt=\"".htmlspecialchars($image_alt_string)."\" title=\"".htmlspecialchars($image_alt_string)."\" />";
            }
        }

        // Формируем строку значения класса выравнивания
        if ($image_align == "left") {
            $image_align_string = " class=\"float_left\"";
        } elseif ($image_align == "right") {
            $image_align_string = " class=\"float_right\"";
        } elseif ($image_align == "center") {
            $image_align_string = " class=\"center\"";
        } else {
            $image_align_string = "";
        }

        // Отслеживаем выравнивание изображения
        if (!empty($image_align_string)) {
            $replace_text = "<span".$image_align_string.">".$replace_text."</span>";
        }

    } else {

        // Формируем ссылку для загрузки файла
        if ($globals["user_entry_flag"]) {
            $replace_text = "<a class=\"nopage\" href=\"files.php?action=upload&amp;filename=".$filename."\">".htmlspecialchars($image_alt_string)."</a>";
        } else {
            $replace_text = "<a class=\"nopage\" href=\"files.php?filename=".$filename."\">".htmlspecialchars($image_alt_string)."</a>";
        }
    }

    return $replace_text;
}

///////////////////////////////////////////////////////////////////////////////
//                                                                           //
//                  Функция парсинга тега загружаемого файла                 //
//                                                                           //
///////////////////////////////////////////////////////////////////////////////

function parse_wiki_file($file_tag) {

    global $globals;

    // Формируем регулярное выражение для нахождения параметров загружаемого файла
    $regexp = "/^file:([a-z0-9_.-]+)(\|(.+))?$/iu";

    // Находим параметры загружаемого файла
    if (preg_match($regexp, $file_tag, $result)) {

        // Находим имя загружаемого файла
        $filename = $result[1];

        // Находим текстовое описание файла
        if (isset($result[3])) {
            $description = $result[3];
        } else {
            $description = "File:".$filename;
        }

        // Проверяем, что такой файл существует
        if (!empty($filename) && file_exists($globals["path_files"].$filename)) {

            // Формируем ссылку на файл
            $replace_text = "<a href=\"".$globals["path_files"].$filename."\">".htmlspecialchars($description)."</a>";

        } else {

            // Формируем ссылку для загрузки файла
            if ($globals["user_entry_flag"]) {
                $replace_text = "<a class=\"nopage\" href=\"files.php?action=upload&amp;filename=".$filename."\">".htmlspecialchars($description)."</a>";
            } else {
                $replace_text = "<a class=\"nopage\" href=\"files.php?filename=".$filename."\">".htmlspecialchars($description)."</a>";
            }
        }

    } else {

        // Формируем сообщение об ошибке
        $replace_text = "<span class=\"error\">".$file_tag."</span>\n";
    }

    return $replace_text;
}

///////////////////////////////////////////////////////////////////////////////
//                                                                           //
//                 Функция парсинга тега ссылки на страницу                  //
//                                                                           //
///////////////////////////////////////////////////////////////////////////////

function parse_wiki_page($page_tag, $export_flag=0) {

    global $globals;

    // Формируем регулярное выражение для нахождения параметров странички
    $regexp = "/^([A-Z0-9_-]+)\|(.+)$/iu";

    // Находим параметры странички
    if (preg_match($regexp, $page_tag, $result)) {

        // Находим идентификатор страницы
        $id = $result[1];

        // Находим название страницы
        $title = $result[2];

        // Определяем, существует ли страница
        if (file_exists($globals["path_pages"].$id.".txt")) {

            // Формируем ссылку на страницу
            if (!$export_flag) {
                $replace_text = "<a href=\"".get_rewrite_link($id)."\">".htmlspecialchars($title)."</a>";
            } else {
                $replace_text = "<a href=\"".$id.".htm\">".htmlspecialchars($title)."</a>";
            }

        } else {

            // Формируем ссылку для создания новой страницы
            if (!$export_flag) {
                if ($globals["user_entry_flag"]) {
                    $replace_text = "<a class=\"nopage\" href=\"pages.php?action=edit&amp;id=".$id."&amp;title=".urlencode($title)."\">".htmlspecialchars($title)."</a>";
                } else {
                    $replace_text = "<a class=\"nopage\" href=\"".get_rewrite_link($id)."\">".htmlspecialchars($title)."</a>";
                }
            } else {
                $replace_text = "<span class=\"nopage\">".htmlspecialchars($title)."</span>";
            }
        }

    } else {
    
        // Формируем сообщение об ошибке
        $replace_text = "<span class=\"error\">".$page_tag."</span>\n";
    }

    return $replace_text;
}

///////////////////////////////////////////////////////////////////////////////
//                                                                           //
//                Функция парсинга внутренних ссылок Wiki                    //
//                                                                           //
///////////////////////////////////////////////////////////////////////////////

function parse_wiki_links($export_flag=0) {

    global $this_script, $globals, $blocks;

    // Формируем регулярное выражение для нахождения Wiki-ссылок
    $regexp_allowed_characters = "[A-ZА-ЯёЁІіЎўҐґЇїєЄ0-9:;.,!?%'\/\|\"\(\) _=\&#№+-]";
    $regexp = "/\[\[(((meta|file|image):)*([A-Z0-9_.-]+)(\|(".$regexp_allowed_characters."*))?)\]\]/iu";

    reset($blocks);
    while (list($id, $block) = each($blocks)) {

        // Обрабатываем все блоки, за исключением неформатированного текста и NOWIKI
        if ($block["type"] != "code" && $block["type"] != "nowiki") {

            // Находим все Wiki-ссылки
        	preg_match_all($regexp, $block["parsed_content"], $results, PREG_SET_ORDER);

            if (!empty($results)) {

                reset($results);
                while (list($result_id, $result) = each($results)) {

                    // Находим параметры ссылки
                    if (!empty($result[3])) {
                        $type = strtolower($result[3]);
                    } else {
                        $type = "";
                    }

                    // Проверяем, является ли данная ссылка мета-тегом...
                    if ($type == "meta") {

                        $replace_text = parse_wiki_meta($result[1]);

                    // ...или изображением...
                    } elseif ($type == "image") {

                        $replace_text = parse_wiki_image($result[1]);

                    // ...или загружаемым файлом...
                    } elseif ($type == "file") {

                        $replace_text = parse_wiki_file($result[1]);

                    // ...или это просто внутренняя ссылка
                    } else {

                        $replace_text = parse_wiki_page($result[1], $export_flag);
                    }

                    // Производим замену
                    $block["parsed_content"] = str_replace($result[0], $replace_text, $block["parsed_content"]);
                }
            }
        }

        $blocks[$id] = $block;
    }
}

///////////////////////////////////////////////////////////////////////////////
//                                                                           //
//                      Функция обработки спецсимволов                       //
//                                                                           //
///////////////////////////////////////////////////////////////////////////////

function parse_special_characters() {

    global $blocks;

    reset($blocks);
    while (list($id, $block) = each($blocks)) {

        // Читаем таблицу обратного преобразования спецсимволов
        $html_translation_table = array_flip(get_html_translation_table(HTML_SPECIALCHARS));

        // Обрабатываем все блоки, за исключением неформатированного текста и NOWIKI
        if ($block["type"] != "code" && $block["type"] != "nowiki") {
            $block["parsed_content"] = strtr($block["parsed_content"], $html_translation_table);
        }

        $blocks[$id] = $block;
    }
}

///////////////////////////////////////////////////////////////////////////////
//                                                                           //
//             Функция парсинга логического выделения текста                 //
//                                                                           //
///////////////////////////////////////////////////////////////////////////////

function parse_emphasizes_characters() {

    global $blocks;

    reset($blocks);
    while (list($id, $block) = each($blocks)) {

        // Обрабатываем все блоки, за исключением неформатированного текста и NOWIKI
        if ($block["type"] != "code" && $block["type"] != "nowiki") {

            // Разбиваем строку на составляющие, включая апострофы-разделители
            $chunks = preg_split("/(''+)/u", $block["parsed_content"], -1, PREG_SPLIT_DELIM_CAPTURE); 

            if (count($chunks) > 1) {
            
                // Рассчитываем количество маркеров наклонного и полужирного текста
                $num_italics = 0;
                $num_bold = 0;
                for ($i=1; $i<count($chunks); $i+=2) {

                    // Если апострофов четыре, то считаем, что 
                    // предпослений относится к тексту
                    if (strlen($chunks[$i]) == 4) {
                        $chunks[$i-1] .= "'";
                        $chunks[$i] = "'''";

                    // Если апострофов больше пяти, то считаем что к тексту 
                    // относятся все, за исключением последних пяти
                    } elseif (strlen($chunks[$i]) > 5) {
                        $chunks[$i-1] .= str_repeat("'", strlen($chunks[$i]) - 5);
                        $chunks[$i] = "'''''";
                    }            

                    // Рассчитываем количество маркеров
                    if (strlen($chunks[$i]) == 2) {
                        $num_italics++;
                    } elseif (strlen($chunks[$i]) == 3) {
                        $num_bold++;
                    } elseif (strlen($chunks[$i]) == 5) {
                        $num_italics++;
                        $num_bold++;
                    }
                }

                // Если количество наклонных и полужирных маркеров нечетно, то 
                // то это означает, что один из полужирных маркеров на самом деле
                // является наклонным. В этом случае находим первый же полужирный тег
                // и заменяем его на наклонный, а апостроф прибавляем к тексту слева
                if (($num_bold % 2) == 1 && ($num_italics % 2) == 1) {
                    $first_bold = -1;
                    for ($i=1; $i<count($chunks); $i+=2) {
                        if (strlen($chunks[$i]) == 3) {
                            $chunks[$i-1] .= "'";
                            $chunks[$i] = "''";
                            break;
                        }
                    }
                }

                // Преобразуем наклонные и полужирные маркеры в HTML-разметку
                $state = "";
                $string = "";
                $string_buffer = "";
                for ($i=0; $i<count($chunks); $i++) {

                    // Обрабатываем фрагменты с содержимым
                    if (($i % 2) == 0) {

                        if ($state == "both") {
                            $string_buffer .= $chunks[$i];
                        } else {
                            $string .= $chunks[$i];
                        }

                    // Обрабатываем фрагменты с маркерами
                    } else {

                        if (strlen($chunks[$i]) == 2) {
                        
                            if ($state == "em") {
                                $string .= "</em>";
                                $state = "";
                            } elseif ($state == "strongem") {
                                $string .= "</em>";
                                $state = "strong";
                            } elseif ($state == "emstrong") {
                                $string .= "</strong></em><strong>";
                                $state = "strong";
                            } elseif ($state == "both") {
                                $string .= "<strong><em>".$string_buffer."</em>";
                                $state = "strong";
                            } else {
                                $string .= "<em>";
                                $state .= "em";
                            }
                        
                        } elseif (strlen($chunks[$i]) == 3) {

                            if ($state == "strong") {
                                $string .= "</strong>";
                                $state = "";
                            } elseif ($state == "strongem") {
                                $string .= "</em></strong><em>";
                                $state = "em";
                            } elseif ($state == "emstrong") {
                                $string .= "</strong>";
                                $state = "em";
                            } elseif ($state == "both") {
                                $string .= "<em><strong>".$string_buffer."</strong>";
                                $state = "em";
                            } else {
                                $string .= "<strong>";
                                $state .= "strong";
                            }
                        
                        } elseif (strlen($chunks[$i]) == 5) {

                            if ($state == "strong") {
                                $string .= "</strong><em>";
                                $state = "em";
                            } elseif ($state == "em") {
                                $string .= "</em><strong>";
                                $state = "strong";
                            } elseif ($state == "strongem") {
                                $string .= "</em></strong>";
                                $state = "";
                            } elseif ($state == "emstrong") {
                                $string .= "</strong></em>";
                                $state = "";
                            } elseif ($state == "both") {
                                $string .= "<em><strong>".$string_buffer."</strong></em>";
                                $state = "";
                            } else {
                                $string_buffer = "";
                                $state = "both";
                            }
                        }
                    }
                }

                // Закрываем оставшиеся незакрытыми теги
                if ($state == "strong" || $state == "emstrong") {
                    $string .= "</strong>";
                } elseif ($state == "em" || $state == "emstrong" || $state == "strongem")  {
                    $string .= "</em>";
                } elseif ($state == "strongem")  {
                    $string .= "</strong>";
                } elseif ($state == "both")  {
                    $string .= "<em><strong>".$string_buffer."</strong></em>";
                }

                // Сохраняем обработанный блок
                $block["parsed_content"] = $string;
            }
        }

        $blocks[$id] = $block;
    }
}

///////////////////////////////////////////////////////////////////////////////
//                                                                           //
//                       Функция парсинга внешних ссылок                     //
//                                                                           //
///////////////////////////////////////////////////////////////////////////////

function parse_links() {

    global $blocks;

    // Формируем регулярное выражение для нахождения описания ссылки
    $regexp_allowed_characters = "[A-ZА-ЯёЁІіЎўҐґЇїєЄ0-9:;.,!?'\/\|\"\(\) _=\&#№+-]";

    // Формируем все возможные варианты внешних ссылок
    $regexp_email_1 = "[A-Z0-9_-]+(\.[A-Z0-9_-]+)*@([A-Z0-9-]+\.)+[A-Z]{2,5}";
    $regexp_email_2 = "\[mailto:(".$regexp_email_1.") +(".$regexp_allowed_characters."+)\]";
    $regexp_url_1 = "(https?|ftp):\/\/[A-Z0-9-]+(\.[A-Z0-9-]+)*\/([A-Z0-9~\._-]+\/)*[A-Z0-9.+*:;?\/&#%=\(\)_-]*";
    $regexp_url_2 = "\[(".$regexp_url_1.") +(".$regexp_allowed_characters."+)\]";

    // Формируем обобщенное регулярное выражение для нахождения всех типов внешних ссылок
    $regexp = "/(".$regexp_email_1."|".$regexp_email_2."|".$regexp_url_1."|".$regexp_url_2.")/iu";

    reset($blocks);
    while (list($id, $block) = each($blocks)) {

        // Обрабатываем все блоки, за исключением неформатированного текста и NOWIKI
        if ($block["type"] != "code" && $block["type"] != "nowiki") {

            // Находим все Wiki-ссылки
        	preg_match_all($regexp, $block["parsed_content"], $results, PREG_SET_ORDER);

            // Массив уже обработанных ссылок, чтобы избежать двойной замены
            $parsed_links = "";

            if (!empty($results)) {

                reset($results);
                while (list($result_id, $result) = each($results)) {

                    // Если данная ссылка была уже ранее обработана, то пропускаем ее
                    if (!empty($parsed_links) && in_array($result[0], $parsed_links)) {
                        continue;
                    }
                    
                    // Сохраняем ссылку в массиве обработанных ссылок
                    $parsed_links[] = $result[0];

                    // По порядку обрабатываем каждый тип ссылки
                    if (preg_match("/^".$regexp_email_1."$/iu", $result[0], $result_local)) {
                        $replace_text = "<a href=\"mailto:".$result_local[0]."\">".$result_local[0]."</a>";
                    } elseif (preg_match("/^".$regexp_email_2."$/iu", $result[0], $result_local)) {

                        // Проверям наличие поясняющего текста к ссылке
                        if (!empty($result_local[4])) {
                            $replace_text = "<a href=\"mailto:".$result_local[1]."\">".trim($result_local[4])."</a>";
                        } else {
                            $replace_text = "<a href=\"mailto:".$result_local[1]."\">".$result_local[1]."</a>";
                        }

                    } elseif (preg_match("/^".$regexp_url_1."$/iu", $result[0], $result_local)) {
                        $replace_text = "<a href=\"".$result_local[0]."\">".$result_local[0]."</a>";
                    } elseif (preg_match("/^".$regexp_url_2."$/iu", $result[0], $result_local)) {

                        // Проверям наличие поясняющего текста к ссылке
                        if (!empty($result_local[5])) {
                            $replace_text = "<a href=\"".$result_local[1]."\">".trim($result_local[5])."</a>";
                        } else {
                            $replace_text = "<a href=\"".$result_local[1]."\">".$result_local[1]."</a>";
                        }

                    } else {
                        $replace_text = $result[0];
                    }
                    
                    // Производим замену
                    $block["parsed_content"] = str_replace($result[0], $replace_text, $block["parsed_content"]);
                }
            }    
        }

        $blocks[$id] = $block;
    }
}

///////////////////////////////////////////////////////////////////////////////
//                                                                           //
//        Функция формирования окончательного содержимого странички          //
//                                                                           //
///////////////////////////////////////////////////////////////////////////////

function get_parsed_content() {

    global $blocks;

    $text = "";

    // Формируем окончательное содержимое странички, объединяя обработанные блоки
    reset($blocks);
    while (list($id, $block) = each($blocks)) {
        $text .= $block["parsed_content"]."\n";
    }

    return $text;
}

///////////////////////////////////////////////////////////////////////////////
//                                                                           //
//                 Функция парсинга мета-информации о страничке              //
//                                                                           //
///////////////////////////////////////////////////////////////////////////////

function parse_meta($text, $export_flag=0) {

    global $meta;

	// Если страничка не была ранее обработана, то производим ее частичный парсинг
	if (empty($blocks)) {

	    // Разбираем исходный текст на блоки
    	get_wiki_blocks($text);

	    // Обрабатываем базовые блоки разметки
	    parse_wiki_blocks();

	    // Обрабатываем внутренние Wiki-ссылки,
    	// включая мета-теги, изображения и загружаемые файлы
	    parse_wiki_links($export_flag);

	}

    return $meta;
}

///////////////////////////////////////////////////////////////////////////////
//                                                                           //
//                Основная функция парсинга Wiki-разметки                    //
//                                                                           //
///////////////////////////////////////////////////////////////////////////////

function parse_wiki($text, $export_flag=0) {

    // Разбираем исходный текст на блоки
    get_wiki_blocks($text);

    // Обрабатываем базовые блоки разметки
    parse_wiki_blocks();

    // Обрабатываем внутренние Wiki-ссылки,
    // включая мета-теги, изображения и загружаемые файлы
    parse_wiki_links($export_flag);

    // Обрабатываем спецсимволы
    parse_special_characters();

    // Обрабатываем логическое выделение слов
    parse_emphasizes_characters();

    // Обрабатываем внешние ссылки
    parse_links();

    // Формируем окончательное содержимое странички
    $text = get_parsed_content();

    return $text;
}

?>