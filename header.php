<?php 
require_once 'db.php'; 
// принимаем куки если наж ок
if (isset($_POST['accept_cookies'])) {
    setcookie('joy_privacy', 'agreed', time() + (86400 * 30), "/");
    header("Location: " . $_SERVER['REQUEST_URI']);
    exit;
}
$user = getCurrentUser($pdo); 
$settings = getSettings($pdo);
$page = basename($_SERVER['PHP_SELF']);
$viber = joyViberUrl($settings['phone'] ?? '');
$tg = joyTelegramUrl($settings['telegram'] ?? '');

// определяем класс для боди каб стили работали
$bClass = '';
if ($page == 'index.php' || $page == '') { $bClass = 'home-page'; } 
elseif ($page == 'services.php') { $bClass = 'services-page'; } 
elseif ($page == 'catalog.php') { $bClass = 'catalog-page'; } 
elseif ($page == 'publications.php') { $bClass = 'publications-page'; } 
elseif ($page == 'article.php') { $bClass = 'article-page'; } 
elseif ($page == 'cabinet.php') { $bClass = 'cabinet-page'; } 
elseif ($page == 'specialists.php') { $bClass = 'specialists-page'; } 
elseif ($page == 'contacts.php') { $bClass = 'contacts-page'; }
elseif ($page == 'profile.php') { $bClass = 'profile-page'; }
elseif ($page == 'privacy.php') { $bClass = 'privacy-page'; }
elseif ($page == 'faq.php') { $bClass = 'faq-page'; }
?>
<!DOCTYPE html>
<html lang="ru">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= isset($pageTitle) ? $pageTitle : 'J.O.Y. Центр психологии' ?></title>
<link rel="icon" type="image/png" href="./img/icon.png">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
<link href="https://fonts.googleapis.com/css2?family=Lato:wght@400;700&family=Tenor+Sans&display=swap" rel="stylesheet">
<link rel="stylesheet" href="joy.css?v=<?= time() ?>">
<script src="https://unpkg.com/imask@7.1.3/dist/imask.min.js"></script>
<script>
    const isLoggedIn = <?= $user ? 'true' : 'false' ?>;
    document.addEventListener('DOMContentLoaded', function() {
        if (typeof updateCartCount === 'function') {
            updateCartCount();
        }
    });
</script>
</head>
<body class="<?= $bClass ?>">

<div class="nav-container">
    <div class="user-corner user-corner--desktop d-none d-lg-flex">
        <?php if($user): ?>
            <a href="cabinet.php" class="user-link-header"><i class="fas fa-user-circle mr-2"></i> <?= htmlspecialchars($user['name']) ?></a>
        <?php else: ?>
            <a href="#" class="user-link-header" onclick="openAuthModal(event)"><i class="fas fa-sign-in-alt mr-2"></i> Войти</a>
        <?php endif; ?>
    </div>

    <div class="nav-centered">
        <div class="nav-address-row">
            <div class="address"><a href="contacts.php"><i class="fa fa-map-marker"></i> <?= htmlspecialchars($settings['address'] ?: 'г. Минск, пр-т Победителей, 11') ?></a></div>
        </div>

        <div class="nav-bar">
            <a href="index.php" class="logo-link"><img src="img/Frame.svg" class="logo"></a>

            <div class="nav-links d-none d-lg-flex">
                <a href="specialists.php" class="<?= $page == 'specialists.php' ? 'active-link' : '' ?>">Специалисты</a>
                <a href="catalog.php" class="<?= $page == 'catalog.php' ? 'active-link' : '' ?>">Онлайн-продукты</a>
                <a href="index.php#section-2">О центре</a>
                <a href="services.php" class="<?= $page == 'services.php' ? 'active-link' : '' ?>">Консультации</a>
                <a href="publications.php" class="<?= ($page == 'publications.php' || $page == 'article.php') ? 'active-link' : '' ?>">Публикации</a>
                <a href="contacts.php" class="<?= $page == 'contacts.php' ? 'active-link' : '' ?>">Контакты</a>
            </div>

            <div class="nav-actions">
                <div class="nav-actions-desktop d-none d-lg-flex align-items-center">
                    <div class="social-icons">
                        <a href="<?= htmlspecialchars($viber) ?>"><i class="fab fa-viber"></i></a>
                        <a href="<?= htmlspecialchars($tg) ?>" target="_blank"><i class="fab fa-telegram-plane"></i></a>
                        <a href="cabinet.php?tab=cart">
                            <i class="fas fa-shopping-basket"></i>
                            <span id="cartCount" class="badge cart-count-badge">0</span>
                        </a>
                    </div>
                    <a href="#" class="appointment-button" onclick="openAppointment(event)">Записаться</a>
                </div>

                <div class="user-corner user-corner--compact d-lg-none">
                    <?php if($user): ?>
                        <a href="cabinet.php" class="user-link-header"><i class="fas fa-user-circle"></i><span class="user-corner-label"><?= htmlspecialchars($user['name']) ?></span></a>
                    <?php else: ?>
                        <a href="#" class="user-link-header" onclick="openAuthModal(event)"><i class="fas fa-sign-in-alt"></i><span class="user-corner-label">Войти</span></a>
                    <?php endif; ?>
                </div>

                <button type="button" class="nav-hamburger d-lg-none" id="hamburger">
                    <span></span><span></span><span></span>
                </button>
            </div>
        </div>
    </div>

    <!-- моб меню -->
    <div class="mobile-nav d-lg-none" id="mobileNav">
        <div class="mobile-nav-backdrop" data-close-menu></div>
        <div class="mobile-nav-panel">
            <nav class="mobile-nav-links">
                <div class="mobile-nav-social d-flex justify-content-center mb-3">
                    <a href="<?= htmlspecialchars($viber) ?>" class="social-icon viber mr-2"><i class="fab fa-viber"></i></a>
                    <a href="<?= htmlspecialchars($tg) ?>" class="social-icon telegram" target="_blank"><i class="fab fa-telegram-plane"></i></a>
                </div>
                <a href="specialists.php">Специалисты</a>
                <a href="catalog.php">Онлайн-продукты</a>
                <a href="index.php#section-2">О центре</a>
                <a href="services.php">Консультации</a>
                <a href="publications.php">Публикации</a>
                <a href="contacts.php">Контакты</a>
                <a href="#" class="mobile-nav-cta" onclick="openAppointment(event)">Записаться</a>
                <?php if($user): ?>
                    <a href="cabinet.php" class="mobile-nav-account"><i class="fas fa-user-circle mr-2"></i>Личный кабинет</a>
                <?php else: ?>
                    <a href="#" class="mobile-nav-account" onclick="openAuthModal(event)"><i class="fas fa-sign-in-alt mr-2"></i>Войти / Регистрация</a>
                <?php endif; ?>
            </nav>
        </div>
    </div>
</div>