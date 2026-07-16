<?php 
require_once 'db.php';

// все спецы
$st = $pdo->query("SELECT * FROM specialists ORDER BY id ASC");
$specs = $st->fetchAll();

$pageTitle = "Наши специалисты | J.O.Y.";
require_once 'header.php';
?>

<section class="section" style="padding-top: 150px; padding-bottom: 100px;">
    <div class="mandala-wrapper-quiz">
        <img src="img/Group 186.png" class="rotating-mandala">
    </div>
    <div class="container">
        
        <div class="row align-items-center mb-5 pb-4">
            <div class="col-lg-5 mb-4">
                <h3 class="section-title text-left" style="font-size: 2.4rem;">КОМАНДА ЦЕНТРА</h3>
                <h2 class="subsection-title text-left" style="font-size: 1.4rem; color: #E0C6AD;">Выберите своего психолога</h2>
            </div>
            <div class="col-lg-7">
                <div class="specialists-header-desc">
                    <p class="mb-4">
                        Каждый специалист нашего центра — это не просто дипломированный психолог, а бережный проводник, прошедший строгий профессиональный отбор. Мы объединили экспертов с разными терапевтическими подходами, чтобы вы смогли найти именно того человека, с которым почувствуете абсолютную безопасность, тепло и поддержку.
                    </p>
                    <p class="mb-0">
                        <strong>Прислушайтесь к себе:</strong> выберите того, чей опыт откликается вам больше всего, и сделайте первый, самый важный шаг навстречу внутренней гармонии.
                    </p>
                </div>
            </div>
        </div>

        <!-- фильтры -->
        <div class="row justify-content-center mb-5">
            <div class="col-12 text-center">
                <div class="filters-group">
                    <button class="filter-btn active" data-filter="all">Все специалисты</button>
                    <button class="filter-btn" data-filter="семейн">Семейные</button>
                    <button class="filter-btn" data-filter="тревог">Тревожность</button>
                    <button class="filter-btn" data-filter="бизнес">Бизнес</button>
                </div>
            </div>
        </div>

        <!-- сетка спецов -->
        <div class="row justify-content-center" id="specialists-grid">
            <?php foreach($specs as $s): ?>
                <div class="col-lg-4 col-md-6 mb-5 spec-card-wrapper" data-specs="<?= mb_strtolower($s['specialization'] . ' ' . $s['description']) ?>">
                    <div class="specialist-card w-100 h-100 d-flex flex-column">
                        <img src="<?= htmlspecialchars($s['photo']) ?>" class="specialist-photo" onerror="this.src='img/Frame.png'">
                        <div class="specialist-name"><?= htmlspecialchars($s['first_name'] . ' ' . $s['last_name']) ?></div>
                        <div class="specialist-role mb-2"><?= htmlspecialchars($s['specialization']) ?></div>
                        <div class="text-muted small mb-3">Опыт: <?= $s['experience_years'] ?> лет</div>
                        <a href="profile.php?id=<?= $s['id'] ?>" class="main-button small-btn mt-auto">Подробнее</a>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <!-- баннер теста -->
        <div class="row justify-content-center mt-5">
            <div class="col-lg-8">
                <div class="quiz-banner">
                    <h3 class="mb-3" style="font-family: 'Tenor Sans';">Не знаете, кого выбрать?</h3>
                    <p class="mb-4">Пройдите тест, алгоритм подберет психолога под ваш запрос.</p>
                    <button class="consultation-button mx-auto" onclick="document.getElementById('quizModal').style.display='flex'">ПОДОБРАТЬ ПСИХОЛОГА</button>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- модалка теста -->
<div class="modal-form" id="quizModal">
    <div class="form-container quiz-modal-container">
        <span class="close-btn" onclick="document.getElementById('quizModal').style.display='none'; document.body.style.overflow='';">&times;</span>
        
        <div class="quiz-step" id="q-step-1">
            <span class="quiz-step-badge">Шаг 1 из 5</span>
            <h4 class="mb-3 font-tenor">Для кого ищем психолога?</h4>
            <div class="d-flex flex-column">
                <button class="quiz-btn" onclick="nextQuizStep(2, 'individual')">Для себя</button>
                <button class="quiz-btn" onclick="nextQuizStep(2, 'couples')">Для пары</button>
                <button class="quiz-btn" onclick="nextQuizStep(2, 'child')">Для ребенка</button>
                <button class="quiz-btn" onclick="nextQuizStep(2, 'family')">Для семьи</button>
            </div>
        </div>

        <div class="quiz-step" id="q-step-2" style="display: none;">
            <span class="quiz-step-badge">Шаг 2 из 5</span>
            <h4 class="mb-3 font-tenor">Что беспокоит сейчас?</h4>
            <div class="d-flex flex-column">
                <button class="quiz-btn" onclick="nextQuizStep(3, 'emotional')">Эмоции (Страхи)</button>
                <button class="quiz-btn" onclick="nextQuizStep(3, 'relationship')">Отношения</button>
                <button class="quiz-btn" onclick="nextQuizStep(3, 'self')">Самооценка</button>
                <button class="quiz-btn" onclick="nextQuizStep(3, 'family_child')">Дети</button>
            </div>
        </div>

        <div class="quiz-step" id="q-step-3" style="display: none;">
            <span class="quiz-step-badge">Шаг 3 из 5</span>
            <h4 class="mb-3 font-tenor">Какой формат?</h4>
            <div class="d-flex flex-column">
                <button class="quiz-btn" onclick="nextQuizStep(4, 'offline')">Лично в Минске</button>
                <button class="quiz-btn" onclick="nextQuizStep(4, 'online')">Онлайн</button>
                <button class="quiz-btn" onclick="nextQuizStep(4, 'any')">Любой</button>
            </div>
        </div>

        <div class="quiz-step" id="q-step-4" style="display: none;">
            <span class="quiz-step-badge">Шаг 4 из 5</span>
            <h4 class="mb-3 font-tenor">Какой стиль общения?</h4>
            <div class="d-flex flex-column">
                <button class="quiz-btn" onclick="nextQuizStep(5, 'soft')">Мягкий</button>
                <button class="quiz-btn" onclick="nextQuizStep(5, 'structured')">Структурный</button>
                <button class="quiz-btn" onclick="nextQuizStep(5, 'active')">Активный</button>
            </div>
        </div>

        <div class="quiz-step" id="q-step-5" style="display: none;">
            <span class="quiz-step-badge">Шаг 5 из 5</span>
            <h4 class="mb-3 font-tenor">Пол специалиста</h4>
            <div class="d-flex flex-column">
                <button class="quiz-btn" onclick="finishQuiz('female')">Женщина</button>
                <button class="quiz-btn" onclick="finishQuiz('male')">Мужчина</button>
                <button class="quiz-btn" onclick="finishQuiz('any')">Любой</button>
            </div>
        </div>

        <div class="quiz-step" id="q-step-result" style="display: none;">
            <h3 class="mb-2 font-tenor">Мы рекомендуем</h3>
            <div id="quiz-result-card" class="quiz-result-card"> </div>
            <button class="main-button small-btn w-100" id="quiz-book-btn" onclick="bookFromQuiz()">ЗАПИСАТЬСЯ</button>
            <a href="#" class="back-link" onclick="resetQuiz()">Заново</a>
        </div>
    </div>
</div>
<?php require_once 'footer.php'; ?>