# Kitfort.ru Parser (PHP)

Простой PHP-парсер для сбора данных о товарах с сайта Kitfort.ru. Скрипт позволяет извлекать информацию о названии, цене, URL продукта, URL изображения и кратком описании из указанной категории и сохранять ее в базу данных MySQL.

---

## Функциональность

* Парсинг страниц категорий для получения ссылок на товары.
* Обработка пагинации для перехода по всем страницам категории.
* Парсинг данных (название, цена, URL, изображение, краткое описание) с каждой страницы товара.
* Сохранение спарсенных данных в базу данных MySQL.
* Запуск в фоновом режиме через веб-интерфейс (Apache/Nginx + PHP).

---

## Требования

* **PHP 7.4+** (или выше)
* **Расширение PHP cURL** (обычно включено по умолчанию в XAMPP/WAMP/MAMP)
* **Расширение PHP DOM** (для работы с DOMDocument и DOMXPath, обычно включено)
* **База данных MySQL**
* **Веб-сервер** (Apache, Nginx и т.д.) с поддержкой PHP (например, **XAMPP**, WAMP, MAMP)

---

## Установка

1.  **Клонируйте репозиторий:**
    ```bash
    git clone [https://github.com/ВАШ_ЛОГИН_GITHUB/kitfort_parser.git](https://github.com/Anton-Mochinsky/kitfort_parser.git)
    ```
    Или скачайте ZIP-архив репозитория и распакуйте его.

2.  **Разместите файлы:**
    Переместите содержимое клонированной папки (или распакованного архива) в директорию вашего веб-сервера.
    Например, для XAMPP это будет: `D:\xampp\htdocs\kitfort_parser`

3.  **Настройка базы данных:**
    * Создайте новую базу данных MySQL. Например, `kitfort_parser`.
    * Выполните следующий SQL-скрипт для создания таблицы `products`:

    ```sql
    CREATE TABLE IF NOT EXISTS `products` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `title` VARCHAR(255) NOT NULL,
        `price` DECIMAL(10, 2),
        `product_url` VARCHAR(255) NOT NULL UNIQUE,
        `image_url` VARCHAR(255),
        `description_short` TEXT,
        `parsed_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ```

4.  **Настройте `database.php`:**
    Откройте файл `database.php` в корневой директории проекта и обновите учетные данные для доступа к вашей базе данных MySQL:

    ```php
    <?php
    // database.php

    $host = 'localhost'; // Или IP-адрес вашей БД
    $db = 'kitfort_parser'; // Имя вашей базы данных
    $user = 'root'; // Ваш пользователь БД
    $pass = ''; // Ваш пароль БД (для XAMPP часто пустой)
    $charset = 'utf8mb4';

    $dsn = "mysql:host=$host;dbname=$db;charset=$charset";
    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ];

    try {
        $conn = new PDO($dsn, $user, $pass, $options);
        // echo "Подключение к базе данных успешно установлено!\n"; // Можно закомментировать после отладки
    } catch (\PDOException $e) {
        throw new \PDOException($e->getMessage(), (int)$e->getCode());
    }
    ?>
    ```

5.  **Проверьте пути в `index.php`:**
    Убедитесь, что в файле `index.php` указан полный абсолютный путь к `php.exe` и `parser.php` для фонового запуска.
    Например, если XAMPP установлен на `D:\xampp`:
    ```php
    pclose(popen("start /B D:\\xampp\\php\\php.exe D:\\xampp\\htdocs\\kitfort_parser\\parser.php", "r"));
    ```
    **Важно:** Этот путь может отличаться на другом устройстве! Пользователю потребуется обновить его в `index.php` в соответствии со своим расположением XAMPP/PHP.

---

## Запуск парсера

Парсер можно запустить двумя способами:

### 1. Через веб-интерфейс (рекомендуется для фонового запуска)

* Откройте ваш веб-браузер.
* Перейдите по адресу: `http://localhost/kitfort_parser/`
* Нажмите зеленую кнопку **"Запустить парсер сейчас"**.
* Парсер будет запущен в фоновом режиме, и вы можете продолжить работу с браузером.

### 2. Из командной строки (для отладки или ручного запуска)

* Откройте командную строку (CMD) или Git Bash.
* Перейдите в директорию проекта:
    ```bash
    cd D:\xampp\htdocs\kitfort_parser
    ```
    (Замените `D:\xampp\htdocs\kitfort_parser` на фактический путь к вашему проекту).
* Запустите скрипт PHP, указав полный путь к `php.exe`:
    ```bash
    D:\xampp\php\php.exe parser.php
    ```
    (Замените `D:\xampp\php\php.exe` на фактический путь к `php.exe` на вашем устройстве).
* Вы увидите весь процесс парсинга прямо в консоли.

---

## Важные замечания

* **Задержки (sleep()):** В скрипте используются функции `sleep()` для создания задержек между запросами. Это сделано для того, чтобы не перегружать целевой сайт и избежать блокировки вашего IP-адреса. Не удаляйте их.
* **Обработка ошибок cURL:** Скрипт включает базовую обработку ошибок cURL. Если возникают проблемы с загрузкой страниц, проверьте вывод консоли.
* **Изменения на сайте:** Веб-сайты постоянно обновляются. Если Kitfort.ru изменит свою HTML-структуру, XPath-запросы в `parser.php` потребуется обновить. Используйте инструменты разработчика браузера (F12) для анализа новой структуры.

---

## Лицензия

[Укажите здесь вашу лицензию, например, MIT License]

---
