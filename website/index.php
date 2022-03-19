<?php

///////////////////////////////////////////////////////////////////////////////
//                                                                           //
//   Простой онлайн блокнот "Wikipad" // Первая страница                     //
//   ----------------------------------------------------------------------  //
//   Copyright (C) 1998-2022 Studio "Cherry-Design"                          //
//   URL: https://www.cherry-design.com/                                     //
//   E-mail: mike@cherry-design.com                                          //
//                                                                           //
///////////////////////////////////////////////////////////////////////////////

// Имя данного скрипта
$this_script = "index.php"; 

// Производим инициализацию
require("includes/initialization.php"); 

// Осуществляем редирект на нужную страницу
if ($globals["hidden_flag"] && !$globals["user_entry_flag"]) {
    header("Location: login.php");
} else {
    if ($globals["blog_flag"]) {
        header("Location: blog.php");
    } else {
        header("Location: ".get_rewrite_link($id));
    }
}

?>