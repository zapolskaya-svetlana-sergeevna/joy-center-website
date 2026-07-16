<?php 
require_once 'db.php';
require_once 'PHPMailer/Exception.php';
require_once 'PHPMailer/PHPMailer.php';
require_once 'PHPMailer/SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

$u = getCurrentUser($pdo);
if (!$u) { header("Location: index.php"); exit; }

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['cart_data'])) {
    
    $items = json_decode($_POST['cart_data'], true);
    if (empty($items)) { header("Location: cabinet.php"); exit; }

    $sum = 0;
    foreach ($items as $it) $sum += (int)$it['price'];

    try {
        $pdo->beginTransaction();
 
        // созд заказ
        $st = $pdo->prepare("INSERT INTO orders (user_id, total_price) VALUES (?, ?)");
        $st->execute([$u['id'], $sum]);
        $oid = $pdo->lastInsertId();
        
        $stI = $pdo->prepare("INSERT INTO order_items (order_id, product_id, product_title, price) VALUES (?, ?, ?, ?)");
        $hList = ''; 
        
        foreach ($items as $it) {
            $stI->execute([$oid, $it['id'], $it['title'], (int)$it['price']]);
            $hList .= "<li style='margin-bottom: 5px;'>" . htmlspecialchars($it['title']) . " — <b>" . (int)$it['price'] . " BYN</b></li>";
        }

        $pdo->commit();

        // чистим корзину в бд после покупк
        $pdo->prepare("DELETE FROM cart_items WHERE cart_id = (SELECT id FROM cart WHERE user_id = ?)")
        ->execute([$u['id']]);

        // галеп для чека на почт
        if (isset($_POST['send_to_email']) && $_POST['send_to_email'] == '1') {
            $m = "Здравствуйте, {$u['name']}!<br>Ваш заказ <b>№{$oid}</b> успешно оформлен.<br><br>Состав заказа:<br>{$hList}<br><b>Итого: {$sum} BYN</b><br><br>Доступ будет открыт в течение 24 часов.";
            joySendMail($u['email'], 'Ваш заказ №' . $oid . ' оформлен | J.O.Y.', $m);
        }

        header("Location: cabinet.php?order_success=1");
        exit;

    } catch (Exception $e) {
        $pdo->rollBack();
        die("ошибка: " . $e->getMessage());
    }
} else {
    header("Location: cabinet.php");
}
?>