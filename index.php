<?php
// index.php

require_once 'database.php';

if (isset($_POST['run_parser'])) {
    set_time_limit(0);
    ignore_user_abort(true);

    // Этот код для запуска парсера в фоновом режиме на Windows
    // Если у вас Linux/macOS, используйте: exec('php parser.php > /dev/null &');
    pclose(popen("start /B D:\\xampp\\php\\php.exe D:\\xampp\\htdocs\\kitfort_parser\\parser.php", "r"));

    echo "<script>alert('Парсер запущен в фоновом режиме! Обновите страницу через некоторое время, чтобы увидеть результаты.');</script>";
    header("Location: index.php");
    exit();
}

$stmt = $conn->query("SELECT * FROM products ORDER BY parsed_at DESC LIMIT 50");
$products = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Парсер Kitfort.ru</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; background-color: #f0f2f5; color: #333; }
        .container { max-width: 960px; margin: 30px auto; background-color: #ffffff; padding: 30px; border-radius: 10px; box-shadow: 0 4px 15px rgba(0,0,0,0.1); }
        h1, h2 { color: #2c3e50; border-bottom: 2px solid #e0e0e0; padding-bottom: 10px; margin-bottom: 25px; }
        .parser-actions { margin-bottom: 30px; text-align: center; }
        .parser-actions button { padding: 12px 25px; background-color: #28a745; color: white; border: none; border-radius: 6px; cursor: pointer; font-size: 18px; transition: background-color 0.3s ease; }
        .parser-actions button:hover { background-color: #218838; }
        .product-list { margin-top: 20px; }
        .product-item { display: flex; align-items: center; border: 1px solid #e0e0e0; border-radius: 8px; padding: 15px; margin-bottom: 15px; background-color: #fdfdfd; box-shadow: 0 2px 5px rgba(0,0,0,0.05); transition: transform 0.2s ease; }
        .product-item:hover { transform: translateY(-3px); }
        .product-item img { width: 100px; height: 100px; object-fit: contain; margin-right: 20px; border-radius: 4px; border: 1px solid #ddd; padding: 5px; background-color: #fff; }
        .product-details { flex-grow: 1; }
        .product-details h3 { margin: 0 0 8px 0; font-size: 1.4em; }
        .product-details h3 a { color: #007bff; text-decoration: none; transition: color 0.2s ease; }
        .product-details h3 a:hover { color: #0056b3; text-decoration: underline; }
        .product-details .price { font-weight: bold; color: #e67e22; font-size: 1.2em; margin-bottom: 5px; }
        .product-details p { margin: 0; color: #666; font-size: 0.9em; line-height: 1.4; }
        .product-details .parsed-at { font-size: 0.8em; color: #888; margin-top: 5px; }
        .no-products { text-align: center; color: #777; padding: 30px; border: 1px dashed #ccc; border-radius: 8px; }
    </style>
</head>
<body>
    <div class="container">
        <h1>Панель управления парсером Kitfort.ru</h1>

        <div class="parser-actions">
            <form method="post">
                <button type="submit" name="run_parser">Запустить парсер сейчас</button>
            </form>
            <p><small>Нажмите, чтобы начать процесс сбора данных. Это может занять некоторое время.</small></p>
        </div>

        <h2>Последние спарсенные продукты</h2>
        <div class="product-list">
            <?php if (!empty($products)): ?>
                <?php foreach ($products as $product): ?>
                    <div class="product-item">
                        <img src="<?= htmlspecialchars($product['image_url'] ?? 'https://via.placeholder.com/100?text=Нет+фото') ?>" alt="<?= htmlspecialchars($product['title'] ?? 'Название не указано') ?>">
                        <div class="product-details">
                            <h3><a href="<?= htmlspecialchars($product['product_url'] ?? '#') ?>" target="_blank"><?= htmlspecialchars($product['title'] ?? 'Название не указано') ?></a></h3>
                            <p class="price"><?= htmlspecialchars(number_format($product['price'], 2, ',', ' ') ?? 'Нет цены') ?> ₽</p>
                            <?php if (!empty($product['description_short'])): ?>
                                <p><?= nl2br(htmlspecialchars($product['description_short'])) ?></p>
                            <?php endif; ?>
                            <p class="parsed-at">Обновлено: <?= htmlspecialchars($product['parsed_at']) ?></p>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="no-products">
                    <p>Нет спарсенных продуктов. Нажмите "Запустить парсер", чтобы начать сбор данных.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>