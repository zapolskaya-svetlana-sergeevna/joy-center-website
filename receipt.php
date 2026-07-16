<?php 
require_once 'db.php';

$u = getCurrentUser($pdo);
if (!$u) { header("Location: index.php"); exit; }

$oid = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// тянем инфу по заказу для чека
$sql = "SELECT o.*, u.surname, u.name, u.patronymic, u.email 
        FROM orders o 
        JOIN users u ON o.user_id = u.id 
        WHERE o.id = ? AND (o.user_id = ? OR ? = 'admin')"; 

$st = $pdo->prepare($sql);
$st->execute([$oid, $u['id'], $u['role']]);
$ord = $st->fetch();

if (!$ord) { die("Заказ не найден"); }

$stI = $pdo->prepare("SELECT * FROM order_items WHERE order_id = ?");
$stI->execute([$oid]);
$itms = $stI->fetchAll();
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>Чек №<?= $oid ?></title>
    <style>
        body { font-family: 'Courier New', monospace; background: #fdfbf9; padding: 40px; }
        .receipt { max-width: 400px; margin: 0 auto; background: #fff; padding: 30px; box-shadow: 0 10px 30px rgba(224, 198, 173, 0.3); border-radius: 10px; border-top: 5px solid #E0C6AD; }
        .header { text-align: center; margin-bottom: 20px; border-bottom: 1px dashed #E0C6AD; padding-bottom: 10px; }
        .info { margin-bottom: 20px; font-size: 14px; }
        .items-table { width: 100%; font-size: 14px; margin-bottom: 20px; }
        .items-table th { text-align: left; border-bottom: 1px solid #E0C6AD; }
        .total { text-align: right; font-size: 18px; font-weight: bold; border-top: 1px solid #E0C6AD; padding-top: 15px; }
        .btn-print { display: block; width: 80%; padding: 12px; margin: 30px auto; background: #E0C6AD; text-align: center; text-decoration: none; color: #3D3935; font-weight: bold; border-radius: 25px; }
        @media print { .btn-print { display: none; } }
    </style>
</head>
<body>
<div class="receipt">
    <div class="header">
        <h2 style="margin:0;">J.O.Y. Center</h2>
        <p>Электронный чек</p>
    </div>
    <div class="info">
        <p><strong>Заказ №:</strong> <?= $ord['id'] ?></p>
        <p><strong>Дата:</strong> <?= date('d.m.Y H:i', strtotime($ord['created_at'])) ?></p>
        <p><strong>Покупатель:</strong> <?= htmlspecialchars($ord['surname'] . ' ' . $ord['name']) ?></p>
    </div>
    <table class="items-table">
        <thead><tr><th>Товар</th><th style="text-align: right;">Цена</th></tr></thead>
        <tbody>
            <?php foreach ($itms as $i): ?>
            <tr>
                <td><?= htmlspecialchars($i['product_title']) ?></td>
                <td style="text-align: right;"><?= number_format($i['price'], 0) ?> BYN</td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <div class="total">ИТОГО: <?= number_format($ord['total_price'], 0) ?> BYN</div>
    <div style="text-align: center; margin-top: 20px; font-size: 12px; color: #888;">г. Минск, пр-т Победителей, 11</div>
    <a href="javascript:window.print()" class="btn-print">Печать</a>
</div>
</body>
</html>