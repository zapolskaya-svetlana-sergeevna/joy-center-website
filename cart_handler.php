<?php
session_start();
require_once 'db.php';

// проверка залогинен или нет
$u = getCurrentUser($pdo);
if (!$u) {
    echo json_encode(['success' => false, 'message' => 'User not logged in']);
    exit;
}

$act = $_GET['action'] ?? '';

try {
    // ищем корзину юзера или создаем новую
    $st = $pdo->prepare("SELECT id FROM cart WHERE user_id = ?");
    $st->execute([$u['id']]);
    $cid = $st->fetchColumn();

    if (!$cid) {
        $pdo->prepare("INSERT INTO cart (user_id) VALUES (?)")->execute([$u['id']]);
        $cid = $pdo->lastInsertId();
    }

    // добавить в корзину
    if ($act === 'add') {
        if (!isset($_POST['product_id'])) {
            echo json_encode(['success' => false, 'message' => 'No product ID']);
            exit;
        }
        $pid = (int)$_POST['product_id'];
        $sql = "INSERT INTO cart_items (cart_id, product_id) VALUES (?, ?)";
        $pdo->prepare($sql)->execute([$cid, $pid]);
        echo json_encode(['success' => true]);
    } 
    
    // удалить из корзины
    elseif ($act === 'remove') {
        $iid = (int)$_POST['item_id'];
        $pdo->prepare("DELETE FROM cart_items WHERE id = ? AND cart_id = ?")->execute([$iid, $cid]);
        echo json_encode(['success' => true]);
    } 
    
    // отдать список товаров в корзине
    elseif ($act === 'get') {
        $st = $pdo->prepare("SELECT ci.id as item_id, p.id as product_id, p.title, p.price, p.image 
                               FROM cart_items ci 
                               JOIN products p ON ci.product_id = p.id 
                               WHERE ci.cart_id = ?");
        $st->execute([$cid]);
        $items = $st->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode(['success' => true, 'items' => $items]);
    }

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}