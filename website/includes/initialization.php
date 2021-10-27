<?php

///////////////////////////////////////////////////////////////////////////////
//                                                                           //
//   Простой онлайн блокнот "Wikipad" // Инициализация                       //
//   ----------------------------------------------------------------------  //
//   Copyright (C) 1998-2021 Studio "Cherry-Design"                          //
//   URL: https://www.cherry-design.ru/                                      //
//   E-mail: mike@cherry-design.ru                                           //
//                                                                           //
///////////////////////////////////////////////////////////////////////////////

// Устанавливаем поддержку локализации русского языка в PHP
setlocale(LC_ALL, array ('ru_RU.UTF-8'));

// Подключаем конфигурационный файл
require("includes/configuration.php");

// Подключаем общие функции
require("includes/functions.php");
require("includes/parser.php");

//............................................ Обрабатываем "волшебные кавычки"

// Включаем "волшебные кавычки" для данных переданных методом GET, POST или COOKIE 
if (!get_magic_quotes_gpc()) {

    if (!empty($_GET)) add_magic_quotes($_GET);
    if (!empty($_POST)) add_magic_quotes($_POST);
    if (!empty($_COOKIE)) add_magic_quotes($_COOKIE);
    if (!empty($_REQUEST)) add_magic_quotes($_REQUEST);

    @ini_set("magic_quotes_gpc", 1); 
}

// Отключаем "волшебные кавычки" для данных прочитанных из файлов
@set_magic_quotes_runtime(0);

//................................... Проверяем вошел ли пользователь в систему

if (!empty($_COOKIE["user_login"])) {
    $user_login = $_COOKIE["user_login"];
} else {
    $user_login = "";
}

if (!empty($_COOKIE["user_password"])) {
    $user_password = $_COOKIE["user_password"];
} else {
    $user_password = "";
}

if (md5($globals["login"]) == $user_login && md5($globals["password"]) == $user_password) {
    $globals["user_entry_flag"] = 1;
} else {
    $globals["user_entry_flag"] = 0;
}

//........................................ Инициализируем глобальные переменные

// Текущая команда
if (!empty($_REQUEST["action"])) {
    $action = $_REQUEST["action"];
} else {
    $action = "";
}

// Текущий идентификатор страницы
if (!empty($_REQUEST["id"])) {
    $id = $_REQUEST["id"];
} else {
    $id = "index";
}

// Текущий номер страницы просмотра
if (!empty($_REQUEST["page"])) {
    $page = $_REQUEST["page"];
} else {
    $page = 1;
}

?>