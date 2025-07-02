<?php
// database.php

$servername = "localhost"; // Адрес сервера базы данных
$username = "root";      // Имя пользователя базы данных (по умолчанию для XAMPP/WAMP)
$password = "";          // Пароль пользователя базы данных (по умолчанию пустой для XAMPP/WAMP)
$dbname = "kitfort_parser"; // Имя нашей базы данных

try {
    // Создаем новый объект PDO для подключения к базе данных
    $conn = new PDO("mysql:host=$servername;dbname=$dbname;charset=utf8", $username, $password);
    // Устанавливаем режим ошибок PDO на исключения, что упрощает отладку
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    // echo "Подключение к базе данных успешно установлено!"; // Можно раскомментировать для проверки
} catch(PDOException $e) {
    // Если произошла ошибка подключения, выводим сообщение и прерываем выполнение скрипта
    die("Ошибка подключения к базе данных: " . $e->getMessage());
}
?>