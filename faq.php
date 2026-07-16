<?php 
require_once 'db.php';
$pageTitle = "Вопрос-ответ | J.O.Y.";

// миграция если че вдруг колонки нет
try { 
    $pdo->exec("ALTER TABLE faq ADD COLUMN status VARCHAR(20) DEFAULT 'published'"); 
} catch(Exception $e) {}

$u = getCurrentUser($pdo);

// если юзер шлет вопрос
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_question'])) {
    if (!$u) {
        echo "<script>alert('Пожалуйста, авторизуйтесь для отправки вопроса.');</script>";
    } else {
        $txt = trim($_POST['user_question']);
        if (!empty($txt)) {
            // сохраняем как пендинг каб админ одобрил
            $pdo->prepare("INSERT INTO faq (question, answer, status) VALUES (?, 'Ожидает ответа администратора...', 'pending')")->execute([$txt]);
            echo "<script>localStorage.setItem('flashToast', JSON.stringify({msg: 'Ваш вопрос отправлен модератору!', isError: false})); window.location.href='faq.php';</script>"; 
            exit;
        }
    }
}

require_once 'header.php';

// тянем только то что опубликовано
$items = $pdo->query("SELECT * FROM faq WHERE status = 'published' ORDER BY id DESC")->fetchAll();
?>

<section class="section faq-page-section">
    <div class="mandala-wrapper-header">
        <img src="img/Group 186.png" alt="Фон" class="rotating-mandala">
    </div>   
    <div class="container">
        <div class="row justify-content-center mb-5">
            <div class="col-lg-8 text-center">
                <h2 class="section-title">FAQ</h2>
                <h3 class="subsection-title">Часто задаваемые вопросы</h3>
                <p class="section-text">Мы собрали ответы на самые популярные вопросы наших клиентов, чтобы вам было спокойнее перед первой сессией.</p>
            </div>
        </div>

        <div class="row justify-content-center">
            <div class="col-lg-8">
                <div class="accordion mb-5" id="faqAccordion">
                    <?php if(count($items) > 0): ?>
                        <?php foreach($items as $idx => $f): 
                            $active = ($idx === 0);
                        ?>
                        <div class="faq-card shadow-sm">
                            <div class="faq-header <?= $active ? '' : 'collapsed' ?>" 
                                 id="heading-<?= $f['id'] ?>" 
                                 data-toggle="collapse" 
                                 data-target="#collapse-<?= $f['id'] ?>">
                                <h5 class="faq-question-title">
                                    <?= htmlspecialchars($f['question']) ?>
                                    <i class="fas fa-chevron-down faq-icon"></i>
                                </h5>
                            </div>
                            <div id="collapse-<?= $f['id'] ?>" class="collapse <?= $active ? 'show' : '' ?>" data-parent="#faqAccordion">
                                <div class="faq-answer-content">
                                    <?= nl2br(htmlspecialchars($f['answer'])) ?>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <p class="text-center text-muted py-4">Пока нет опубликованных ответов. Вы можете задать свой вопрос ниже.</p>
                    <?php endif; ?>
                </div>

                <!-- форма для вопроса -->
                <div class="faq-form-box shadow-sm">
                    <h5 class="font-tenor mb-3">Не нашли ответ на свой вопрос?</h5>
                    <?php if($u): ?>
                        <form method="POST">
                            <textarea name="user_question" class="form-control joy-input mb-3" rows="3" placeholder="Задайте ваш вопрос куратору..." required></textarea>
                            <button type="submit" name="submit_question" class="main-button small-btn">Отправить вопрос</button>
                        </form>
                    <?php else: ?>
                        <p class="text-muted small">Чтобы задать вопрос специалисту, пожалуйста, <a href="#" class="color-accent-joy text-underline" onclick="openAuthModal(event)">войдите в свой аккаунт</a>.</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</section>

<?php require_once 'footer.php'; ?>