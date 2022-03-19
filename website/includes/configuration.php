<?php

///////////////////////////////////////////////////////////////////////////////
//                                                                           //
//   Простой онлайн блокнот "Wikipad" // Конфигурация                        //
//   ----------------------------------------------------------------------  //
//   Copyright (C) 1998-2022 Studio "Cherry-Design"                          //
//   URL: https://www.cherry-design.com/                                     //
//   E-mail: mike@cherry-design.com                                          //
//                                                                           //
///////////////////////////////////////////////////////////////////////////////

// Текущая версия движка
$globals["version"] = "v2.2.0";

// Флаг работы системы в режиме обязательной авторизации
$globals["hidden_flag"] = 0;

// Флаг работы системы в режиме возможности экспорта содержимого сайта
$globals["export_flag"] = 0;

// Флаг показа даты последнего изменения страницы
$globals["date_flag"] = 1;

// Флаг использования простой системы поиска на сайте
$globals["search_flag"] = 1;

// Флаг использования движка в режиме ведения блога
$globals["blog_flag"] = 0; 

// Флаг трансляции RSS-каналов
$globals["rss_flag"] = 1;

// Флаг преобразования ссылок для режима "mod_rewrite"
$globals["rewrite_flag"] = 0;

// Название, слоган и электронный адрес сайта
$globals["website_title"] = "Wikipad";
$globals["website_words"] = "Простой онлайн блокнот";
$globals["website_email"] = "email@domain.com";

// Ключевые слова и описание сайта
$globals["website_keywords"] = "Список ключевых слов";
$globals["website_description"] = "Краткое описание сайта";

// Логин и пароль для доступа к системе
$globals["login"] = "admin";
$globals["password"] = "admin";

// Меню сайта для неавторизованных пользователей
$globals["menu"] = array(
    "index"    => "Первая страница",
    "blog:"    => "Блог",
    "search:"  => "Поиск",
    "sitemap"  => "Карта сайта",
    "about"    => "О проекте"
);

// Путь к каталогу, где хранятся странички
$globals["path_pages"] = "pages/";

// Путь к каталогу, где хранятся загружаемые файлы
$globals["path_files"] = "files/";

// Путь к каталогу, где хранятся шаблоны
$globals["path_templates"] = "templates/";

// Путь к каталогу, куда будет происходить экспорт содержимого сайта
$globals["path_export"] = "../export/";

// Путь к каталогу, где хранятся временные файлы (без завершающего слеша)
$globals["path_temp"] = "temp";

?>