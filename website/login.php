<?php

///////////////////////////////////////////////////////////////////////////////
//                                                                           //
//   Простой онлайн блокнот "Wikipad" // Вход в систему                      //
//   ----------------------------------------------------------------------  //
//   Copyright (C) 1998-2021 Studio "Cherry-Design"                          //
//   URL: https://www.cherry-design.ru/                                      //
//   E-mail: mike@cherry-design.ru                                           //
//                                                                           //
///////////////////////////////////////////////////////////////////////////////

// Имя данного скрипта
$this_script = "login.php";

// Производим инициализацию
require("includes/initialization.php"); 

// Если пользователь авторизован, то делаем редирект на первую страницу
if ($globals["user_entry_flag"]) {
    header("Location: ./");
}

///////////////////////////////////////////////////////////////////////////////
//                                                                           //
//                       Функция авторизации пользователя                    //
//                                                                           //
///////////////////////////////////////////////////////////////////////////////

function login_user($record) {

    global $this_script, $globals;

    // Определяем с какой странички пришел пользователь
    $referer = get_referer_link();

    // Буферизируем вывод
    ob_start();

    if (empty($record)) {

        // Печатаем сообщение для пользователя
        echo "<p>Для получения возможности добавления, изменения и, в ряде случаев, просмотра информации на сайте, Вам необходимо авторизоваться. Пожалуйста, введите свой логин и пароль, чтобы войти в систему.</p>";

        // Формируем значения переменных по умолчанию
        $record = array(
            "login"    => "",
            "password" => "",
            "referer"  => $referer
        );

    } else {

        // Проверяем, что указаны все необходимые данные
        if (!empty($record["login"]) && !empty($record["password"])) {

            // Проверяем логин и пароль пользователя
            if ($globals["login"] == $record["login"] && $globals["password"] == $record["password"]) {

                // Сохраняем хеши логина и пароля пользователя в Cookies
                setcookie("user_login", md5($record["login"]), 0);
                setcookie("user_password", md5($record["password"]), 0);

                // Перенаправляем пользователя на страницу, с которой он зашел
                header("Location: ".$record["referer"]);
                exit();

            } else {

                // Печатаем сообщение об ошибке
                echo "<p>Введен неверный логин или пароль. Внимательно проверьте, что у Вас включена нужная раскладка клавиатуры и проверьте состояние клавиши Caps Lock.</p>";
            }
        } else {

            // Печатаем сообщение об ошибке
            echo "<p>Вы не заполнили, как минимум, одно из обязательных полей формы.</p>";
        }
    }

?>
<form action="<?php echo $this_script; ?>" method="post">
<input type="hidden" name="user[referer]" value="<?php echo htmlspecialchars(stripslashes($record["referer"])); ?>" />
<dl>
<dt>Логин</dt>
<dd><input type="text" name="user[login]" value="<?php echo htmlspecialchars(stripslashes($record["login"])); ?>" /></dd>
<dt>Пароль</dt>
<dd><input type="password" name="user[password]" value="<?php echo htmlspecialchars(stripslashes($record["password"])); ?>" /></dd>
</dl>
<p class="button"><input type="submit" value="  Войти  " /></p>
</form>
<?php

    // Читаем буферизированный вывод в строку
    $content = ob_get_contents();
    ob_end_clean();

    // Формируем параметры страницы
    $globals["page"]["title"] = "Вход в систему";
    $globals["page"]["content"] = $content;

}

///////////////////////////////////////////////////////////////////////////////

if (!empty($_REQUEST["user"])) {
    $user = $_REQUEST["user"];
} else {
    $user = "";
}

// Осуществляем вход в систему
login_user($user);
print_page();

?>