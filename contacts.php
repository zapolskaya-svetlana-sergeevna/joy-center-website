<?php 
require_once 'db.php';
// тянем настройки
$s = getSettings($pdo); 
$tit = "Контакты | J.O.Y.";
require_once 'header.php';
?>

<section class="section" style="padding-top: 150px; padding-bottom: 100px; background: transparent;">
    <div class="mandala-wrapper-header">
        <img src="img/Group 186.png" alt="Фон" class="rotating-mandala">
    </div>
    <div class="container">
        
        <div class="row mb-5">
            <!-- колонка с контактами -->
            <div class="col-lg-6 mb-5 mb-lg-0 pr-lg-5">
                <h2 class="mb-4 pb-3" style="font-family: 'Tenor Sans'; color: #3D3935; font-size: 2.2rem; border-bottom: 2px solid #E0C6AD; display: inline-block;"> КОНТАКТЫ </h2>
                
                <div class="mt-3">
                    <p style="font-size: 1.8rem; font-family: 'Tenor Sans'; color: #3D3935; margin-bottom: 15px;">
                        <?= htmlspecialchars($s['phone']) ?>
                    </p>

                    <div class="d-flex align-items-center mb-4" style="gap: 20px;">
                        <a href="<?= htmlspecialchars(joyTelegramUrl($s['telegram'] ?? '')) ?>" class="text-muted" target="_blank">Telegram</a>
                        <a href="<?= htmlspecialchars(joyViberUrl($s['phone'] ?? '')) ?>" class="text-muted">Viber</a>
                        <a href="mailto:<?= htmlspecialchars($s['email']) ?>" class="text-muted">Email</a>
                    </div>

                    <div class="d-flex align-items-center mb-4">
                        <a href="<?= htmlspecialchars($s['instagram']) ?>" class="contact-icon-circle"><i class="fab fa-instagram"></i></a>
                        <a href="<?= htmlspecialchars(joyViberUrl($s['phone'] ?? '')) ?>" class="contact-icon-circle"><i class="fab fa-viber"></i></a>
                        <a href="<?= htmlspecialchars(joyTelegramUrl($s['telegram'] ?? '')) ?>" class="contact-icon-circle" target="_blank"><i class="fab fa-telegram-plane"></i></a>
                    </div>

                    <p style="margin-bottom: 10px;"> <?= htmlspecialchars($s['address']) ?> </p>
                    <p class="text-muted"> Время работы: <?= htmlspecialchars($s['work_hours']) ?> </p>
                </div>
            </div>

            <!-- форма обратки -->
            <div class="col-lg-6">
                <h2 class="mb-4 pb-3" style="font-family: 'Tenor Sans'; color: #3D3935; font-size: 2.2rem; border-bottom: 2px solid #E0C6AD; display: inline-block;"> ОБРАТНАЯ СВЯЗЬ </h2>
                <div class="mt-3">
                    <p class="text-muted mb-4"> Остались вопросы? Наш куратор готов помочь вам разобраться во всех деталях. </p>
                    <button type="button" class="main-button contacts-callback-btn" onclick="document.getElementById('callbackModal').style.display='flex'; event.preventDefault();"> ОБРАТНАЯ СВЯЗЬ </button>
                    <p class="small text-muted mt-4"> Нажимая на кнопку вы соглашаетесь с <a href="privacy.php">политикой конфиденциальности</a> </p>
                </div>
            </div>
        </div>

        <!-- карта -->
        <div class="row">
            <div class="col-12">
                <div class="map-container" style="border-radius: 20px; overflow: hidden; box-shadow: 0 10px 30px rgba(224, 198, 173, 0.4); height: 500px; border: 2px solid #E0C6AD;">
                    <iframe src="https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d2350.29777931326!2d27.54807491117172!3d53.90871143242205!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x46dbcfec9920cc83%3A0xc4f95d85dc410978!2z0L_RgC3Rgi4g0J_QvtCx0LXQtNC40YLQtdC70LXQuSAxMSwg0JzQuNC90YHGsQ!5e0!3m2!1sru!2sby!4v1700000000000!5m2!1sru!2sby" width="100%" height="100%" style="border:0;" allowfullscreen="" loading="lazy"></iframe>
                </div>
            </div>
        </div>

    </div>
</section>

<?php require_once 'footer.php'; ?>