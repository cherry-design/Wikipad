///////////////////////////////////////////////////////////////////////////////
//                                                                           //
//   Простой онлайн блокнот "Wikipad" // Функции на JavaScript               //
//   ----------------------------------------------------------------------  //
//   Copyright (C) 1998-2022 Studio "Cherry-Design"                          //
//   URL: https://www.cherry-design.com/                                     //
//   E-mail: mike@cherry-design.com                                          //
//                                                                           //
///////////////////////////////////////////////////////////////////////////////

///////////////////////////////////////////////////////////////////////////////
//                                                                           //
//        Функция запроса подтверждения на удаление странички или файла      //
//                                                                           //
///////////////////////////////////////////////////////////////////////////////

function confirm_delete(type) {

    // Обрабатываем запрос на удаление страницы
    if (type == "page") {

        // Если в форме редактирования удален весь текст
        if (document.getElementById("f_text").value == "") {
    
            // Запрашиваем у пользователя подтверждение и возвращаем результат
            return confirm("Вы действительно хотите удалить данную страничку?");
        }

    // Обрабатываем запрос на удаление файла
    } else {

        // Если новое имя файла указано, а сам файл для загрузки не выбран
        if (document.getElementById("f_file").value == "" && document.getElementById("f_filename").value != "") {
    
            // Запрашиваем у пользователя подтверждение и возвращаем результат
            return confirm("Вы действительно хотите удалить этот файл?");
        }
    }

    return true;
}

///////////////////////////////////////////////////////////////////////////////
//                                                                           //
//                    Функция печати панели инструментов                     //
//                                                                           //
///////////////////////////////////////////////////////////////////////////////

function print_toolbar() {

    // Печатаем панель инструментов в виде HTML-кода
    document.write("<p class=\"toolbar\">");
    document.write("<a href=\"javascript:edit_action('strong');\" title=\"Полужирный шрифт\"><span>Полужирный шрифт</span></a> ");
    document.write("<a href=\"javascript:edit_action('em');\" title=\"Наклонный шрифт\" class=\"separator\"><span>Наклонный шрифт</span></a> ");
    document.write("<a href=\"javascript:edit_action('header');\" title=\"Заголовок 2-го уровня\" class=\"separator\"><span>Заголовок 2-го уровня</span></a> ");
    document.write("<a href=\"javascript:edit_action('link');\" title=\"Внутренняя ссылка\"><span>Внутренняя ссылка</span></a> ");
    document.write("<a href=\"javascript:edit_action('external');\" title=\"Внешняя ссылка\" class=\"separator\"><span>Внешняя ссылка</span></a> ");
    document.write("<a href=\"javascript:edit_action('image');\" title=\"Ссылка на изображение\"><span>Ссылка на изображение</span></a> ");
    document.write("<a href=\"javascript:edit_action('file');\" title=\"Ссылка на загружаемый файл\" class=\"separator\"><span>Ссылка на загружаемый файл</span></a> ");
    document.write("<a href=\"javascript:edit_action('ordered');\" title=\"Нумерованный список\"><span>Нумерованный список</span></a> ");
    document.write("<a href=\"javascript:edit_action('unordered');\" title=\"Маркированный список\" class=\"separator\"><span>Маркированный список</span></a> ");
    document.write("<a href=\"javascript:edit_action('indent');\" title=\"Текст с отступом\"><span>Текст с отступом</span></a> ");
    document.write("<a href=\"javascript:edit_action('definition');\" title=\"Список терминов\"><span>Список терминов</span></a> ");
    document.write("<a href=\"javascript:edit_action('pre');\" title=\"Неформатированный текст\" class=\"separator\"><span>Неформатированный текст</span></a> ");
    document.write("<a href=\"javascript:edit_action('table');\" title=\"Таблица\"><span>Таблица</span></a> ");
    document.write("<a href=\"javascript:edit_action('line');\" title=\"Разделительная линия\"><span>Разделительная линия</span></a>");
    document.write("</p>");
}

///////////////////////////////////////////////////////////////////////////////
//                                                                           //
//               Функция удаления начальных и конечных пробелов              //
//                                                                           //
///////////////////////////////////////////////////////////////////////////////

function trim(str) {

    // Удаляем начальные пробелы и символы табуляции
    while (str.substr(0,1) == " " || str.substr(0,1) == "\t") {
        str = str.substr(1, str.length-1);
    }

    // Удаляем конечные пробелы и символы табуляции
    while (str.substr(str.length-1,1) == " " || str.substr(str.length-1,1) == "\t") {
        str = str.substr(0, str.length-1);
    }

    return str;
}

///////////////////////////////////////////////////////////////////////////////
//                                                                           //
//                    Функция чтения выделенного фрагмента                   //
//                                                                           //
///////////////////////////////////////////////////////////////////////////////

function get_selection() {

    var selection = "";

    // Создаем объект, содержащий текстовую форму
    var textarea = document.getElementById("f_text"); 

    // Обрабатываем Internet Explorer и Opera
    if (document.selection) {

        // Находим выделенный фрагмент текста
        selection = document.selection.createRange().text;

    // Обрабатываем FireFox и WebKit
    } else if (textarea.selectionStart || textarea.selectionStart == "0") {

        // Находим выделенный фрагмент текста
	    selection = (textarea.value).substring(textarea.selectionStart, textarea.selectionEnd); 
    }

    // Корректируем концы строк, преобразуя "\r\n" в "\n"
    selection = selection.replace(/\r/g, "");

    return selection;
}

///////////////////////////////////////////////////////////////////////////////
//                                                                           //
//            Функция записи строки на место выделенного фрагмента           //
//                                                                           //
///////////////////////////////////////////////////////////////////////////////

function set_selection(selection) {

    // Создаем объект, содержащий текстовую форму
    var textarea = document.getElementById("f_text"); 

    // Обрабатываем Internet Explorer и Opera
    if (document.selection) {

        textarea.focus();

        // Вставляем обработанный фрагмент обратно в форму
        document.selection.createRange().text = selection;

        // Позиционируем курсор
        textarea.caretPos = document.selection.createRange().duplicate(); 

    // Обрабатываем FireFox и WebKit
    } else if (textarea.selectionStart || textarea.selectionStart == "0") {

        textarea.focus();

        // Запоминаем текущую позицию курсора
        var startPos = textarea.selectionStart;
        var endPos = textarea.selectionEnd;
        var scrollTop = textarea.scrollTop;

        // Вставляем обработанный фрагмент обратно в форму
        textarea.value = textarea.value.substring(0, textarea.selectionStart) + selection + textarea.value.substring(textarea.selectionEnd, textarea.value.length);

        // Позиционируем курсор
		var caretPos = startPos + selection.length;
		textarea.selectionStart = caretPos;
		textarea.selectionEnd = caretPos;
		textarea.scrollTop = scrollTop;
    }
}

///////////////////////////////////////////////////////////////////////////////
//                                                                           //
//                            Функция печати списка                          //
//                                                                           //
///////////////////////////////////////////////////////////////////////////////

function print_list(type) {

    // Находим выделенный фрагмент текста
    var selection = get_selection();

    // Если выделение отсутствует
    if (selection == "") {

        // Формируем пример использования списка
        switch (type) {
            case "indent":
                selection = "Первый абзац с отступом\nВторой абзац с отступом";
                break
            case "definition":
                selection = "Первый термин\nОпределение первого термина\nВторой термин\nОпределение второго термина";
                break
            case "pre":
                selection = "Неформатированный текст\n   с сохранением\n      всех отступов";
                break
            default:
                selection = "Первый элемент списка\nВторой элемент списка\nТретий элемент списка";
        }
    }

    // Разбираем выделенный фрагмент на строки
    var str_array = selection.split("\n");

    // Преобразуем каждую строку в массиве
    for (i=0; i<str_array.length; i++) {

        // Удаляем лишние пробелы, но только в случае,
        // если не выбрана команда неформатированного текста
        if (type != "pre") {
            str_array[i] = trim(str_array[i]);
        }

        // Формируем нужный тип списка
        if (str_array[i] != "") {

            switch (type) {
                case "ordered":
                    str_array[i] = "# " + str_array[i];
                    break;
                case "unordered":
                    str_array[i] = "* " + str_array[i];
                    break;
                case "indent":
                    str_array[i] = ": " + str_array[i];
                    break;
                case "definition":
                    if (i%2 == 0) {
                        str_array[i] = "; " + str_array[i];
                    } else {
                        str_array[i-1] += " : " + str_array[i];
                        str_array[i] = "";
                    }
                    break;
                case "pre":
                    str_array[i] = " " + str_array[i];
                    break;
            }
        }
    }
    
    // Объединяем массив в одну строку
    selection = "\n" + str_array.join("\n") + "\n";
    selection = selection.replace(/\n\n/g, "\n")

    // Вставляем обработанный фрагмент обратно в форму
    set_selection(selection);
}

///////////////////////////////////////////////////////////////////////////////
//                                                                           //
//                          Функция печати таблицы                           //
//                                                                           //
///////////////////////////////////////////////////////////////////////////////

function print_table() {

    // Находим выделенный фрагмент текста
    var selection = get_selection();

    // Если выделение отсутствует    
    if (selection == "") {

        // Формируем пример использования таблицы
        selection  = "\n!Название столбца  ! Название столбца !  Название столбца!\n";
        selection += "|По левому краю    |    По центру     |   По правому краю|\n";
        selection += "|По левому краю    |    По центру     |   По правому краю|\n";

    } else {
    
        // Разбираем выделенный фрагмент на строки
        var str_array = selection.split("\n");
    
        // Удаляем лишние пробелы
        for (i=0; i<str_array.length; i++) {
            str_array[i] = trim(str_array[i]);
        }
    
        // Преобразуем каждую строку в заголовок таблицы
        selection = "\n" + "! " + str_array.join(" ! ") + " !" + "\n";
    }

    // Вставляем обработанный фрагмент обратно в форму
    set_selection(selection);
}

///////////////////////////////////////////////////////////////////////////////
//                                                                           //
//             Функция обработки команды вставки строчного тега              //
//                                                                           //
///////////////////////////////////////////////////////////////////////////////

function print_inline_tag(start, end, sample) {

    // Находим выделенный фрагмент текста
    var selection = get_selection();

    // Если выделение отсутствует, то формируем шаблон с примером использования
    if (selection == "") {
        selection = sample;
    }

    // Обрабатываем выделенный фрагмент
    if (selection.charAt(selection.length-1) == " ") {
        selection = selection.substring(0, selection.length - 1); 
        selection = start + selection + end + " ";
    } else {
        selection = start + selection + end;
    }
        
    // Вставляем обработанный фрагмент обратно в форму
    set_selection(selection);
}

///////////////////////////////////////////////////////////////////////////////
//                                                                           //
//             Функция обработки команды вставки блочного тега               //
//                                                                           //
///////////////////////////////////////////////////////////////////////////////

function print_block_tag(start, end, sample) {

    // Находим выделенный фрагмент текста
    var selection = get_selection();

    // Если выделение отсутствует, то формируем шаблон с примером использования
    if (selection == "") {
        selection = sample;
    }

    // Обрабатываем выделенный фрагмент
    selection = "\n" + start + trim(selection) + end + "\n";

    // Вставляем обработанный фрагмент обратно в форму
    set_selection(selection);
}

///////////////////////////////////////////////////////////////////////////////
//                                                                           //
//           Функция обработки команд редактирования в редакторе             //
//                                                                           //
///////////////////////////////////////////////////////////////////////////////

function edit_action(action) {

    // По порядку обрабатываем все доступные команды
    switch (action) {
        case "strong":
            print_inline_tag("'''","'''","Полужирный шрифт");
            break;
        case "em":
            print_inline_tag("''","''","Наклонный шрифт");
            break;
        case "header":
            print_block_tag("== "," ==","Текст заголовка");
            break;
        case "link":
            print_inline_tag("[[link|","]]","Текст ссылки");
            break;
        case "external":
            print_inline_tag("[http://www.example.com/ ","]","Текст ссылки");
            break;
        case "image":
            print_inline_tag("[[Image:picture.jpg|","]]","Описание изображения");
            break;
        case "file":
            print_inline_tag("[[File:archive.zip|","]]","Описание файла для скачивания");
            break;
        case "ordered":
            print_list("ordered");
            break;
        case "unordered":
            print_list("unordered");
            break;
        case "indent":
            print_list("indent");
            break;
        case "definition":
            print_list("definition");
            break;
        case "pre":
            print_list("pre");
            break;
        case "table":
            print_table();
            break;
        case "line":
            print_block_tag("----","","");
            break;
    }
}
