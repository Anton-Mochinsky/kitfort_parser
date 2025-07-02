<?php
// parser.php

// Подключаем файл с настройками базы данных
require_once 'database.php';

// --- Вспомогательная функция: Получение HTML-содержимого страницы ---
function getPageContent($url) {
    // Инициализируем сессию cURL
    $ch = curl_init();

    // Устанавливаем URL, который нужно загрузить
    curl_setopt($ch, CURLOPT_URL, $url);
    // Возвращаем содержимое страницы в виде строки, а не выводим напрямую
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    // Разрешаем cURL следовать за HTTP-перенаправлениями (например, с HTTP на HTTPS)
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    // Устанавливаем User-Agent, чтобы имитировать запрос из обычного браузера.
    // Это помогает избежать блокировок со стороны некоторых сайтов.
    curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/100.0.4896.75 Safari/537.36');
    // Отключаем проверку SSL-сертификатов. В реальных проектах это небезопасно,
    // но для парсинга некоторых сайтов может быть необходимо. Используйте с осторожностью.
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    // Добавляем небольшой таймаут для запроса
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);

    // Выполняем запрос и получаем HTML-содержимое
    $html = curl_exec($ch);
    // Получаем код ошибки cURL, если она есть
    $error = curl_error($ch);
    // Закрываем сессию cURL
    curl_close($ch);

    // Если произошла ошибка cURL, выводим ее и возвращаем false
    if ($error) {
        echo "Ошибка cURL при загрузке {$url}: " . $error . "\n";
        return false;
    }

    // Если страница не загрузилась или пуста
    if (empty($html)) {
        echo "Предупреждение: Пустое содержимое страницы {$url}\n";
        return false;
    }

    return $html;
}

// --- Вспомогательная функция: Сохранение данных о товаре в базу данных ---
function saveProductToDB($productData) {
    global $conn; // Используем глобальное подключение PDO из database.php

    // Подготавливаем SQL-запрос для вставки или обновления данных.
    // ON DUPLICATE KEY UPDATE позволяет избежать ошибок, если product_url уже существует.
    // В этом случае запись будет обновлена, а не вставлена повторно.
    $stmt = $conn->prepare("
        INSERT INTO products (title, price, product_url, image_url, description_short)
        VALUES (:title, :price, :product_url, :image_url, :description_short)
        ON DUPLICATE KEY UPDATE
            title = VALUES(title),
            price = VALUES(price),
            image_url = VALUES(image_url),
            description_short = VALUES(description_short),
            parsed_at = CURRENT_TIMESTAMP
    ");

    try {
        // Выполняем подготовленный запрос, передавая значения через массив
        $stmt->execute([
            ':title' => $productData['title'] ?? null,
            ':price' => $productData['price'] ?? null,
            ':product_url' => $productData['product_url'] ?? null,
            ':image_url' => $productData['image_url'] ?? null,
            ':description_short' => $productData['description_short'] ?? null
        ]);
        echo "✔ Сохранен/обновлен товар: " . ($productData['title'] ?? 'Неизвестный товар') . "\n";
        return true;
    } catch (PDOException $e) {
        // Логируем ошибку, но не прерываем выполнение скрипта, чтобы продолжить парсинг
        echo "✘ Ошибка сохранения товара '" . ($productData['title'] ?? 'Неизвестный товар') . "': " . $e->getMessage() . "\n";
        return false;
    }
}

// --- Основная функция парсинга страницы категории ---
function parseCategoryPage($url) {
    echo "Начинаем парсинг категории: {$url}\n";
    $html = getPageContent($url);
    if (!$html) {
        return []; // Возвращаем пустой массив, если не удалось загрузить страницу
    }

    $dom = new DOMDocument();
    // @: Подавляем предупреждения HTML5, которые DOMDocument может генерировать
    // при парсинге неидеального HTML. Это полезно для "грязных" сайтов.
    @$dom->loadHTML($html);
    $xpath = new DOMXPath($dom);

    $productLinks = [];

    // XPath-запрос для поиска ссылок на товары.
    // Изучаем HTML-код страницы категории на Kitfort.ru (например, https://kitfort.ru/catalog/televizory/)
    // Видно, что каждый товар находится внутри <div class="bx_catalog_item_container">
    // А ссылка на товар находится внутри <a class="bx_catalog_item_title" href="...">
    // Ищем все элементы <a>, у которых есть класс "item__gallery-link"
// и извлекаем значение их атрибута "href".
    $nodes = $xpath->query('//a[contains(@class, "item__gallery-link")]');

    foreach ($nodes as $node) {
        $href = $node->getAttribute('href');
        if ($href) {
            // Убедимся, что получаем полный URL, так как Kitfort.ru использует относительные пути
            $fullUrl = 'https://kitfort.ru' . $href;
            $productLinks[] = $fullUrl;
        }
    }
    echo "Найдено " . count($productLinks) . " ссылок на товары в категории.\n";
    return $productLinks;
}

function parseProductPage($url) {
    echo "Парсинг страницы товара: {$url}\n";
    $html = getPageContent($url);
    if (!$html) {
        return null;
    }

    $dom = new DOMDocument();
    @$dom->loadHTML($html);
    $xpath = new DOMXPath($dom);

    $productData = [
        'title' => null,
        'price' => null,
        'product_url' => $url,
        'image_url' => null,
        'description_short' => null
    ];

    // --- Извлечение названия товара ---
    // Используем h1 с itemprop="name" и классом product-aside__header
    $titleNode = $xpath->query('//h1[contains(@class, "product-aside__header") and @itemprop="name"]')->item(0);
    if ($titleNode) {
        $productData['title'] = trim($titleNode->textContent);
    } else {
        // Если H1 не найден, можно вывести предупреждение или попробовать другие XPath,
        // но с таким точным запросом это маловероятно, если структура не меняется
        echo "Предупреждение: Название товара не найдено по XPath для {$url}\n";
    }

    // --- Извлечение цены товара ---
    // Ищем span с itemprop="price" и берем значение из атрибута 'content'
    $priceNode = $xpath->query('//span[@itemprop="price"]/@content')->item(0);
    if ($priceNode) {
        $productData['price'] = (float) $priceNode->nodeValue; // NodeValue для атрибутов
    } else {
        // Если priceNode не найден, попробуем взять цену из div с классом new_price (акционная цена)
        $newPriceNode = $xpath->query('//div[@class="new_price"]')->item(0);
        if ($newPriceNode) {
            $priceText = $newPriceNode->textContent;
            $priceClean = str_replace([' ', '₽'], '', $priceText); // Удаляем пробелы и символ рубля
            $productData['price'] = (float) filter_var($priceClean, FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION);
        } else {
            echo "Предупреждение: Цена товара не найдена по XPath для {$url}\n";
        }
    }

    // --- Извлечение ссылки на изображение ---
    // Используем XPath, который был из предыдущего фрагмента HTML
    // На странице товара может быть другой селектор, проверьте его через F12, если изображение не парсится
    $imageNode = $xpath->query('//div[contains(@class, "item__image")]/img/@src')->item(0);
    if ($imageNode) {
        $imageUrl = $imageNode->nodeValue;
        if (strpos($imageUrl, 'http') === 0) {
            $productData['image_url'] = $imageUrl;
        } else {
            $productData['image_url'] = 'https://kitfort.ru' . $imageUrl;
        }
    } else {
        // Запасной вариант для детального изображения, если первый не сработал
        $detailImageNode = $xpath->query('//img[contains(@class, "detail_picture")]/@src')->item(0);
        if ($detailImageNode) {
            $imageUrl = $detailImageNode->nodeValue;
            if (strpos($imageUrl, 'http') === 0) {
                $productData['image_url'] = $imageUrl;
            } else {
                $productData['image_url'] = 'https://kitfort.ru' . $imageUrl;
            }
        } else {
             echo "Предупреждение: Изображение товара не найдено для {$url}\n";
        }
    }

    // --- Извлечение краткого описания ---
    // Этот XPath, вероятно, требует обновления, так как его не было в вашем фрагменте.
    // Обязательно проверьте его на полной странице товара через F12.
    $descriptionNode = $xpath->query('//div[contains(@class, "item-block__content")]/p[1]')->item(0);
    if ($descriptionNode) {
        $productData['description_short'] = trim($descriptionNode->textContent);
    } else {
        echo "Предупреждение: Краткое описание товара не найдено для {$url}\n";
    }


    // Проверяем, удалось ли извлечь основные данные (название и цену)
    if ($productData['title'] && $productData['price']) {
        echo "✓ Спарсен товар: " . $productData['title'] . " - " . $productData['price'] . "\n";
        return $productData;
    } else {
        echo "✗ Не удалось спарсить все данные для: {$url}\n";
        // Выводим, чего именно не хватает для отладки
        if (is_null($productData['title'])) echo "   - Отсутствует название.\n";
        if (is_null($productData['price'])) echo "   - Отсутствует цена.\n";
        return null;
    }
}

// --- Запуск парсера ---
$startUrl = "https://kitfort.ru/catalog/aerogrili/"; // Начальная категория для парсинга

echo "=== Запуск парсинга Kitfort.ru ===\n";

$allProductUrls = [];

// Парсим первую страницу категории для получения ссылок на товары
$categoryProductUrls = parseCategoryPage($startUrl);
$allProductUrls = array_merge($allProductUrls, $categoryProductUrls);

// --- Обработка пагинации (переход по страницам) ---
// Kitfort.ru использует пагинацию вида ?PAGEN_1=X
// Нам нужно найти ссылки на следующие страницы пагинации.
// Ищем ссылки с классом "nav-page-item" или "nav-page-item--next"
$html = getPageContent($startUrl);
if ($html) {
    $dom = new DOMDocument();
    @$dom->loadHTML($html);
    $xpath = new DOMXPath($dom);

    // Ищем ссылки на страницы пагинации, которые не являются текущей
    $paginationNodes = $xpath->query('//div[@class="bx_pagination_page"]/ul/li/a[not(contains(@class, "active"))]');
    $pagesToParse = [];

    foreach ($paginationNodes as $node) {
        $href = $node->getAttribute('href');
        if ($href && strpos($href, '?PAGEN_') !== false) { // Убеждаемся, что это ссылка пагинации
            $fullUrl = 'https://kitfort.ru' . $href;
            if (!in_array($fullUrl, $pagesToParse) && $fullUrl != $startUrl) {
                 $pagesToParse[] = $fullUrl;
            }
        }
    }

    foreach ($pagesToParse as $pageUrl) {
        echo "--- Парсинг страницы пагинации: {$pageUrl} ---\n";
        // Задержка перед запросом новой страницы, чтобы не перегружать сервер
        sleep(rand(1, 3)); // Случайная задержка от 1 до 3 секунд
        $moreProductUrls = parseCategoryPage($pageUrl);
        $allProductUrls = array_merge($allProductUrls, $moreProductUrls);
    }
}


echo "\n--- Начинаем парсинг отдельных страниц товаров (всего: " . count(array_unique($allProductUrls)) . ") ---\n";
// Используем array_unique, чтобы избежать дублирования URL, если они были найдены на разных страницах пагинации
$uniqueProductUrls = array_unique($allProductUrls);

foreach ($uniqueProductUrls as $productUrl) {
    // Очень важно добавить задержку между запросами к страницам товаров!
    // Это предотвратит блокировку вашего IP-адреса сайтом.
    sleep(rand(2, 5)); // Случайная задержка от 2 до 5 секунд

    $productData = parseProductPage($productUrl);
    if ($productData) {
        saveProductToDB($productData);
    }
}

echo "\n=== Парсинг завершен! ===\n";
?>