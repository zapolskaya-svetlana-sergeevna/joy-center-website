<?php 
session_start();
// если пустая корзина кидаем обратно
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || empty($_POST['cart_data'])) { 
header("Location: cabinet.php"); 
exit; 
}
$data = $_POST['cart_data']; 
$items = json_decode($data, true); 
$summ = 0;
// считаем итого
foreach($items as $i) $summ += (int)$i['price'];
?>
<!DOCTYPE html>
<html lang="ru">
<head>
<meta charset="UTF-8">
<title>Оформление заказа | J.O.Y.</title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
<link href="https://fonts.googleapis.com/css2?family=Lato:wght@400;700&family=Tenor+Sans&display=swap" rel="stylesheet">
<link rel="stylesheet" href="joy.css?v=<?= time() ?>">
</head>
<body class="checkout-page">

<div class="checkout-box position-relative">
    <div class="loader-overlay" id="loader">
        <div class="spinner-border mb-3" role="status"></div>
        <h5>Обработка платежа...</h5>
        <p class="text-muted small">Пожалуйста, не закрывайте окно</p>
    </div>

    <div class="bank-header">
        <h4 class="mb-1" style="color: #3D3935; font-family: 'Tenor Sans';">Оплата заказа</h4>
        <div class="text-muted small">Введите реквизиты для оплаты материалов</div>
    </div>

    <div class="mb-4 text-center">
        <span class="text-muted d-block">Итого к оплате:</span>
        <h2 class="font-weight-bold" style="color: #3D3935;"><?= $summ ?>.00 BYN</h2>
    </div>

    <form id="paymentForm" action="submit_order.php" method="POST" novalidate>
        <input type="hidden" name="cart_data" value="<?= htmlspecialchars($data) ?>">
        
        <div class="form-group">
            <label class="small font-weight-bold text-muted">Номер карты</label>
            <input type="text" class="form-control" id="card-number" placeholder="0000 0000 0000 0000">
        </div>
        <div class="row">
            <div class="col-6 form-group">
                <label class="small font-weight-bold text-muted">Срок действия</label>
                <input type="text" class="form-control text-center" id="card-date" placeholder="ММ/ГГ">
            </div>
            <div class="col-6 form-group">
                <label class="small font-weight-bold text-muted">CVC / CVV</label>
                <input type="password" class="form-control text-center" id="card-cvc" placeholder="•••">
            </div>
        </div>
        <div class="form-group mb-4">
            <label class="small font-weight-bold text-muted">Имя владельца (латиницей)</label>
            <input type="text" class="form-control text-uppercase" id="card-name">
        </div>
        
        <div class="form-group mb-4">
            <div class="custom-control custom-checkbox mb-2">
                <input type="checkbox" class="custom-control-input" id="privacyCheck" checked>
                <label class="custom-control-label small text-muted" for="privacyCheck" style="line-height: 1.5;">Я согласен с <a href="privacy.php" style="color: #E0C6AD;">политикой конфиденциальности</a></label>
            </div>
            <div class="custom-control custom-checkbox">
                <input type="checkbox" class="custom-control-input" id="emailCheck" name="send_to_email" value="1" checked>
                <label class="custom-control-label small text-muted" for="emailCheck" style="line-height: 1.5;">Отправить информацию о заказе на Email</label>
            </div>
        </div>
        
        <button type="submit" class="pay-btn mt-2">Оплатить</button>
        <div class="text-center mt-4">
            <a href="catalog.php" class="text-muted small" style="text-decoration: underline;">Отменить и вернуться в каталог</a>
        </div>
    </form>
</div>

<script src="https://unpkg.com/imask@7.1.3/dist/imask.min.js"></script>
<script>
    // маски для полей карты
    IMask(document.getElementById('card-number'), { mask: '0000 0000 0000 0000' });
    IMask(document.getElementById('card-date'), { mask: '00/00' });
    IMask(document.getElementById('card-cvc'), { mask: '000' });
    
    document.getElementById('card-name').addEventListener('input', function() {
        this.value = this.value.replace(/[^A-Za-z\s]/g, '');
    });

    document.getElementById('paymentForm').addEventListener('submit', function(e) {
        e.preventDefault();
        document.getElementById('loader').style.display = 'flex';
        // типа имитация банка
        setTimeout(() => { this.submit(); }, 2000);
    });
</script>
</body>
</html>