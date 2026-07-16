<?php 
require_once 'db.php';

// тянем статьи для слайдера
$st = $pdo->query("SELECT * FROM posts WHERE status = 'published' OR status IS NULL ORDER BY created_at DESC LIMIT 10");
$arts = $st->fetchAll();

// тянем спецов
$stSpecs = $pdo->query("SELECT * FROM specialists ORDER BY id ASC");
$specs = $stSpecs->fetchAll();

$pageTitle = "Главная | J.O.Y.";
require_once 'header.php';
?>

<!-- секц 1 главн -->
<section id="section-1" class="section section-1">
    <h1 class="main-title hero-white-text">Пространство<br>для открытия</h1>
    <button class="main-button" onclick="location.href='#section-2'">Узнать больше</button>
    <div class="services hero-white-text">Сессии | Медитации | Закрытый клуб</div>
    <div class="scroll-down" onclick="document.getElementById('section-2').scrollIntoView({behavior: 'smooth'})"></div>
</section>

<!-- секц 2 про нас -->
<section id="section-2" class="section section-2">
    <div class="container">
        <div class="row">
            <div class="col-lg-6 col-md-12">
                <h2 class="section-title">Просто открой себя</h2>
                <h3 class="subsection-title">О центре</h3>
                <p class="section-text">Цель психологического центра J.O.Y. - помочь вам раскрыть таланты и исцелить душевные раны, чтобы обрести радость, благодарность и принятие.</p>
                <p class="section-text">В команде - психологи, бизнес-консультанты, трансформационные терапевты со строгим отбором и большим практическим опытом.</p>
                <div class="expand-link" onclick="toggleHiddenText()"><i class="fa fa-angle-down"></i> Развернуть</div>
                <div class="hidden-text section-text-expanded" id="hiddenText">Наши специалисты помогут вам не только разобраться в себе, но и научат применять полученные знания в повседневной жизни. Мы используем комплексный подход, сочетающий современные психологические методики и бережные практики.</div>
            </div>
        </div>
    </div>
</section>

<!-- секц 3 подход -->
<section id="section-3" class="section section-3">
   <div class="mandala-wrapper-1">
        <img src="img/Group 186.png" class="rotating-mandala">
    </div>
    <div class="container">
        <div class="quote-section quote-section-spacing">
            <p class="dissolve-quote">Жизнь – это непрерывное исследование, развитие и радость.</p>
            <p class="dissolve-quote">Правило двух "Р" - развитие и радость - уже сделало сотни людей осознаннее и счастливее.</p>
            <p class="dissolve-quote">И поверьте - радость откроется вам, как только вы сами откроете себя.</p>
        </div>
    </div>
    <div class="container">
        <div class="text-center mb-4">
            <div class="name-title welcome-title-top">Добро пожаловать в</div>
            <div class="name-subtitle">Центр J.O.Y.</div>
        </div>
        <div class="row justify-content-center">
            <div class="col-lg-10">
                <div class="about-panel">
                    <h3 class="about-title">Наш подход</h3>
                    <p class="about-text">Вместе с нашими специалистами вы сможете найти ответы на свои вопросы, исцелить душевные травмы и прийти к себе быстрее, чем с классическими методами.</p>
                    <p class="about-text">Наш метод работы направлен на то, чтобы помочь вам раскрыть свой потенциал, разжечь искру внутри и решить все вопросы в сфере карьеры, личной жизни, финансов и самоопределения.</p>
                    <p class="about-text">На наших сессиях мы решаем проблему на глубинном уровне, находя ваши точки опоры и внутренние ресурсы.</p>
                    <button class="detail-button" onclick="location.href='#section-4'">ПОДРОБНЕЕ</button>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- секц 4 услуги -->
<section id="section-4" class="section services-section">
    <div class="services-title"><h2>НАЧНИТЕ СЕГОДНЯ</h2><h3>Услуги</h3></div>
    <div class="services-grid">
        <div class="service-card">
            <div class="service-icon"><img src="img/session.png"></div>
            <div class="service-content">
                <h4>Сессия</h4>
                <p>В нашем центре или онлайн<br><br>Получите ответы на свои вопросы и разберите сложные жизненные ситуации.</p>
                <button class="service-button" onclick="document.getElementById('section-5').scrollIntoView({behavior: 'smooth'}); event.preventDefault();">подробнее</button>            
            </div>
        </div>
        <div class="service-card">
            <div class="service-icon"><img src="img/meditation.png"></div>
            <div class="service-content">
                <h4>Медитации</h4>
                <p>Доступны в записи<br><br>Получите готовые медитации, направленные на то, чтобы восстановить внутреннее равновесие.</p>
                <button class="service-button" onclick="location.href='catalog.php'">подробнее</button>
            </div>
        </div>
        <div class="service-card">
            <div class="service-icon"><img src="img/club.png"></div>
            <div class="service-content">
                <h4>Закрытый клуб J.O.Y</h4>
                <p>Онлайн сообщество<br><br>Окружение единомышленников, постоянная поддержка и закрытые материалы.</p>
                <button class="service-button" onclick="location.href='catalog.php'">подробнее</button>
            </div>
        </div>
    </div>
</section>

<!-- секц 5 как проходит -->
<section id="section-5" class="section section-5">
    <div class="container">
        <div class="row justify-content-center text-center mb-5">
            <div class="col-lg-10">
                <h2 class="section-title">КАК ПРОХОДИТ СЕССИЯ</h2>
                <h3 class="subsection-title">Процесс</h3>
            </div>
        </div>

        <div class="timeline-area">
            
            <div class="row position-relative process-step align-items-center">
                <div class="center-dot d-none d-lg-block"></div>
                <div class="col-lg-6 col-text-left">
                    <div class="text-box">
                        <h3 class="process-title">РАССЛАБЛЕНИЕ ЧЕРЕЗ<br>МЕДИТАЦИЮ</h3>
                        <p class="section-text">В этом состоянии мозг замедляется, а подсознание бодрствует и выдает истинные ответы, прежние и наилучшие решения для вашей ситуации.</p>
                        <p class="section-text">Вы не успеваете что-то обдумать или придумать и начинаете находить ответы, соответствующие вашей истинной сути.</p>
                    </div>
                </div>
                <div class="col-lg-6 col-img-right mt-4 mt-lg-0">
                    <!-- Будда -->
                    <img src="img/Untitled-21.png" alt="Медитация" class="process-img img-step-1">
                </div>
            </div>

            <div class="row position-relative process-step flex-lg-row-reverse align-items-center">
                <div class="center-dot d-none d-lg-block"></div>
                <div class="col-lg-6 col-text-right">
                    <div class="text-box">
                        <h3 class="process-title">РАБОТА С ЗАПРОСОМ</h3>
                        <p class="section-text">На этом этапе мы раскрываем ваши истинные желания, находящиеся в гармонии с организмом, исцеления и спектра возможностей.</p>
                        <p class="section-text">Мы прорабатываем вопросы процветания, проблемы в отношениях, постоянную усталость.</p>
                    </div>
                </div>
                <div class="col-lg-6 col-img-left mt-4 mt-lg-0">
                    <img src="img/recommendations.png" alt="Работа с запросом" class="process-img img-step-2">
                </div>
            </div>

            <div class="row position-relative process-step align-items-center">
                <div class="center-dot d-none d-lg-block"></div>
                <div class="col-lg-6 col-text-left">
                    <div class="text-box">
                        <h3 class="process-title">ИНДИВИДУАЛЬНЫЕ<br>РЕКОМЕНДАЦИИ</h3>
                        <p class="section-text">Наш специалист объясняет, что вам нужно делать, чтобы разрешить вашу ситуацию быстро и экологично для вас.</p>
                        <p class="section-text">После сессии вы находите решение вашей ситуации, приходите к пониманию настоящего себя и осознаете, что нет ничего невозможного.</p>
                    </div>
                </div>
                <div class="col-lg-6 col-img-right mt-4 mt-lg-0">
                    <img src="img/meditation-work.png" alt="Рекомендации" class="process-img img-step-3">
                </div>
            </div>

        </div>
    </div>
</section>

<!-- секц 6 результаты -->
<section id="section-6" class="section section-6">
    <div class="container">
        <div class="row justify-content-center result-title-margin">
            <div class="col-lg-10 text-center"><h2 class="section-title">В РЕЗУЛЬТАТЕ ВЫ</h2></div>
        </div>
        <div class="row justify-content-center">
            <div class="col-md-4 col-sm-6 text-center mb-3">
                <div class="result-circle mx-auto" onclick="flipResult(this)">
                    <div class="front"><img src="img/Frame.png"></div>
                    <div class="back">Осознаете свои истинные желания и потребности</div>
                </div>
                <h3 class="mt-3">Понимаете себя</h3>
            </div>
            <div class="col-md-4 col-sm-6 text-center mb-3">
                <div class="result-circle mx-auto" onclick="flipResult(this)">
                    <div class="front"><img src="img/Frame(1).png"></div>
                    <div class="back">Улучшаете коммуникацию и глубину отношений</div>
                </div>
                <h3 class="mt-3">Отношения на новый уровень</h3>
            </div>
            <div class="col-md-4 col-sm-6 text-center mb-3">
                <div class="result-circle mx-auto" onclick="flipResult(this)">
                    <div class="front"><img src="img/Frame(2) .png"></div>
                    <div class="back">Находите корень проблемы и пути решения</div>
                </div>
                <h3 class="mt-3">Проблемы решаются раз и навсегда</h3>
            </div>
            <div class="col-md-4 col-sm-6 text-center mb-3">
                <div class="result-circle mx-auto" onclick="flipResult(this)">
                    <div class="front"><img src="img/Frame(4).png"></div>
                    <div class="back">Привлекаете новые финансовые возможности</div>
                </div>
                <h3 class="mt-3">Финансовые потоки</h3>
            </div>
            <div class="col-md-4 col-sm-6 text-center mb-3">
                <div class="result-circle mx-auto" onclick="flipResult(this)">
                    <div class="front"><img src="img/Frame(3).png"></div>
                    <div class="back">Обретаете внутренний баланс и гармонию</div>
                </div>
                <h3 class="mt-3">Внутреннее состояние</h3>
            </div>
            <div class="col-md-4 col-sm-6 text-center mb-3">
                <div class="result-circle mx-auto" onclick="flipResult(this)">
                    <div class="front"><img src="img/Frame(5).png"></div>
                    <div class="back">Открываете в себе скрытые ресурсы</div>
                </div>
                <h3 class="mt-3">Ваша супер сила</h3>
            </div>
        </div>
        <div class="row justify-content-center mt-2">
            <div class="col-12 text-center"><button class="main-button appointment-button-white" onclick="openAppointment(event)">Записаться на сессию</button></div>
        </div>
    </div>
</section>

<!-- спецы -->
<section id="section-specialists" class="section section-specialists">
    <div class="mandala-wrapper-2">
        <img src="img/Group 186.png" class="rotating-mandala">
    </div>
    <div class="container">
        <div class="row justify-content-center mb-5">
            <div class="col-lg-10 text-center">
                <h2 class="section-title">КОМАНДА ЦЕНТРА</h2>
                <h3 class="subsection-title">Наши специалисты</h3>
            </div>
        </div>
        
        <div class="row justify-content-center">
            <div class="col-lg-12">
                <div class="slider-wrapper">
                    <div class="arrow left" onclick="slideSpecLeft()"><svg viewBox="0 0 24 24"><path d="M15.41 7.41L14 6l-6 6 6 6 1.41-1.41L10.83 12z"/></svg></div>
                    <div class="specialists-slider-container">
                        <div class="specialists-grid" id="spec-slider">
                            <?php if (count($specs) > 0): ?>
                                <?php foreach($specs as $s): ?>
                                <div class="specialist-card">
                                    <img src="<?= htmlspecialchars($s['photo']) ?>" class="specialist-photo" onerror="this.src='img/Frame.png'">
                                    <div class="specialist-name"><?= htmlspecialchars($s['first_name'] . ' ' . $s['last_name']) ?></div>
                                    <div class="specialist-role"><?= htmlspecialchars($s['specialization']) ?></div>
                                    <button class="main-button small-btn" onclick="location.href='profile.php?id=<?= $s['id'] ?>'">Подробнее</button>
                                </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <p class="empty-slider-msg">Список специалистов формируется...</p>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="arrow right" onclick="slideSpecRight()"><svg viewBox="0 0 24 24"><path d="M8.59 16.59L10 18l6-6-6-6-1.41 1.41L13.17 12z"/></svg></div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- секц 7 оффис -->
<section id="section-7" class="section section-7">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-lg-10">
                <div class="panel-container">
                    <div class="text-panel">
                        <div class="text-content">
                            <h1>ВСТРЕЧАЕМСЯ<br>ЗДЕСЬ И СЕЙЧАС</h1>
                            <p>Все очные сессии проходят в центре Минска, в стильном и уютном офисе в 5 минутах от ст.м. Немига. Во дворе есть удобная парковка. Мы создали идеальную атмосферу для того, чтобы вам было максимально комфортно</p>
                            <button class="detail-button testimonial-button" onclick="openAppointment(event)">Записаться</button>
                        </div>
                    </div>
                    <div class="carousel-overlay">
                        <div class="carousel">
                            <div class="carousel-item active slide-bg-1"></div>
                            <div class="carousel-item slide-bg-2"></div>
                            <div class="carousel-item slide-bg-3"></div>
                            <div class="carousel-dots"><span class="carousel-dot active"></span><span class="carousel-dot"></span><span class="carousel-dot"></span></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- секц 8 статьи -->
<section id="section-8" class="section section-8">
    <div class="container">
        <div class="row justify-content-center mb-5">
            <div class="col-lg-10 text-center">
                <h2 class="section-title">ПРОСТРАНСТВО ЗНАНИЙ</h2>
                <h3 class="subsection-title">Наши публикации</h3>
            </div>
        </div>
        <div class="row justify-content-center">
            <div class="col-lg-12">
                <div class="slider-wrapper">
                    <div class="arrow left" onclick="slideLeft()"><svg viewBox="0 0 24 24"><path d="M15.41 7.41L14 6l-6 6 6 6 1.41-1.41L10.83 12z"/></svg></div>
                    <div class="slider-container">
                        <div class="slider" id="slider">
                            <?php if(count($arts) > 0): ?>
                                <?php foreach ($arts as $a): ?>
                                    <a href="article.php?id=<?= $a['id'] ?>" class="pub-slide-card">
                                        <div class="pub-slide-card__image">
                                            <img src="<?= htmlspecialchars($a['image']) ?>" onerror="this.src='img/Frame.png'">
                                        </div>
                                        <div class="pub-slide-card__body">
                                            <h4 class="pub-slide-card__title"><?= htmlspecialchars($a['title']) ?></h4>
                                            <?php if (!empty($a['short_desc'])): ?>
                                            <p class="pub-slide-card__desc"><?= htmlspecialchars($a['short_desc']) ?></p>
                                            <?php endif; ?>
                                        </div>
                                    </a>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <p class="empty-slider-msg">Скоро здесь будут статьи...</p>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="arrow right" onclick="slideRight()"><svg viewBox="0 0 24 24"><path d="M8.59 16.59L10 18l6-6-6-6-1.41 1.41L13.17 12z"/></svg></div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- секц 9 бесплатное -->
<section id="section-9" class="section-9">
    <div class="mandala-wrapper-3">
        <img src="img/Group 186.png" alt="Фон" class="rotating-mandala">
    </div>
    <div class="section-9__content">
        <div class="container">
            <div class="row text-center">
                <div class="col-12">
                    <h2 class="section-title">НАЧАТЬ — ПРОСТО</h2>
                    <h3 class="subsection-title">Начни с бесплатной медитации</h3>
                </div>
            </div>
            <div class="row align-items-center">
                <div class="col-lg-6 section-9__text">
                    <p class="section-text">Если вы не уверены, что готовы начать с полноценной сессией — начните с медитации, которую мы предлагаем прямо сейчас.</p>
                    <p class="section-text">Получите 6-минутную медитацию на активацию сердца, которая поможет вам расслабиться и восполнить свою энергию за считанные минуты.</p>
                    <button class="main-button" onclick="document.getElementById('meditationModal').style.display='flex'">ПОЛУЧИТЬ МЕДИТАЦИЮ</button>
                </div>
                <div class="col-lg-6 section-9__image">
                    <img src="img/tel.png" alt="Медитация" class="img-fluid">
                </div>
            </div>
        </div>
    </div>
</section>
<!-- секц 10 конткты -->
<section id="section-10" class="section section-10">
    <div class="container">
        <div class="row align-items-center">
            <div class="col-lg-6 mb-4 mb-lg-0 section-10__img-col d-none d-md-block">
                <img src="img/Maskgroup.png" alt="Контакты" class="img-fluid">
            </div>
            <div class="col-lg-6 contact-right-side contact-block-padding">
                <div class="contact-text">
                    <p class="section-text mb-3">Кураторы J.O.Y. готовы ответить на все вопросы, которые вас волнуют. Они подробно расскажут, как проходит сессия и чего стоит от неё ожидать.</p>
                    <p class="section-text mb-3">Нажмите на кнопку "Нужна консультация" чтобы оформить обратный звонок, с вами быстро свяжутся.</p>
                </div>
                <div class="contact-buttons-row">
                    <a href="#" class="consultation-button contact-btn-override" onclick="document.getElementById('callbackModal').style.display='flex'; event.preventDefault();">НУЖНА КОНСУЛЬТАЦИЯ</a>
                    <a href="#" class="appointment-button-white contact-btn-override" onclick="openAppointment(event)">ЗАПИСАТЬСЯ НА СЕССИЮ</a>
                </div>
                <div class="telegram-link">
                    <div class="d-flex align-items-center mb-2"><span class="telegram-text">Также вы можете написать нам в Телеграм:</span></div>
                    <?php $sIndex = getSettings($pdo); $tgUrl = joyTelegramUrl($sIndex['telegram'] ?? ''); $tgLabel = $sIndex['telegram'] ?: '@joy_center'; ?>
                    <div class="d-flex align-items-center"><i class="fab fa-telegram-plane telegram-icon mr-2"></i><a href="<?= htmlspecialchars($tgUrl) ?>" class="telegram-username" target="_blank"><?= htmlspecialchars($tgLabel) ?> →</a></div>
                </div>
            </div>
        </div>
    </div>
</section>

<?php require_once 'footer.php'; ?>