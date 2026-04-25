<?php
/**
 * Главная страница (Дашборд) — карточки часто используемых и недавно открытых секретов.
 */
require_once __DIR__ . '/../bootstrap.php';
Auth::requireAuth();

$categoryService = new CategoryService();
$secretService = new SecretService();

$categories = $categoryService->getAll();
$stats = $secretService->getStats();

$limit = max(1, (int)DASHBOARD_LIMIT);
$mostUsed = $secretService->getMostUsed($limit);
$recent   = $secretService->getRecentlyViewed($limit);

$pageTitle = 'Главная';

/**
 * Рендер карточки секрета (общий для обеих секций).
 */
function render_secret_card(array $secret, bool $showLastViewed = false): void {
    $id = (int)$secret['id'];
    ?>
    <a href="/local_secrets/pages/secret_view.php?id=<?= $id ?>" class="am-secret-card">
        <div class="am-secret-card-head">
            <span class="am-secret-card-title">
                <?php if (!empty($secret['is_favorite'])): ?>
                    <i class="fas fa-star am-text-warning"></i>
                <?php endif; ?>
                <?= htmlspecialchars($secret['service_name']) ?>
            </span>
        </div>

        <?php if (!empty($secret['category_name']) || !empty($secret['tags_list'])): ?>
            <div class="am-secret-card-meta">
                <?php if (!empty($secret['category_name'])): ?>
                    <?php $cc = $secret['category_color'] ?? '#888'; ?>
                    <span class="am-chip am-chip-cat"
                          style="background:<?= htmlspecialchars($cc) ?>22;color:<?= htmlspecialchars($cc) ?>;border-color:<?= htmlspecialchars($cc) ?>33;">
                        <i class="fas <?= htmlspecialchars($secret['category_icon'] ?? 'fa-folder') ?>"></i>
                        <?= htmlspecialchars($secret['category_name']) ?>
                    </span>
                <?php endif; ?>
                <?php if (!empty($secret['tags_list'])): ?>
                    <?php foreach (explode(', ', $secret['tags_list']) as $tag): ?>
                        <span class="am-chip am-chip-tag"><?= htmlspecialchars($tag) ?></span>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <?php if ($showLastViewed && !empty($secret['last_viewed_at'])): ?>
            <div class="am-text-xs am-muted">
                <i class="fas fa-clock"></i>
                <?= date('d.m H:i', strtotime($secret['last_viewed_at'])) ?>
            </div>
        <?php endif; ?>
    </a>
    <?php
}

ob_start();
?>

<div class="am-page-head">
    <div class="am-page-head-text">
        <div class="am-eyebrow">Обзор</div>
        <h1 class="am-h1"><i class="fas fa-house am-muted"></i> Главная</h1>
    </div>
    <div class="am-page-head-actions">
        <a href="/local_secrets/pages/smart_add.php" class="am-btn am-btn-ghost am-btn-sm">
            <i class="fas fa-wand-magic-sparkles"></i> Умное добавление
        </a>
        <a href="/local_secrets/pages/secret_form.php" class="am-btn am-btn-primary am-btn-sm">
            <i class="fas fa-plus"></i> Добавить
        </a>
    </div>
</div>

<!-- Плитки статистики -->
<div class="am-grid am-grid-cols-4 am-block">
    <div class="am-stat am-stat-blue">
        <div class="am-stat-label"><i class="fas fa-vault"></i> Всего секретов</div>
        <div class="am-stat-value"><?= (int)$stats['total'] ?></div>
    </div>
    <div class="am-stat am-stat-amber">
        <div class="am-stat-label"><i class="fas fa-star"></i> В избранном</div>
        <div class="am-stat-value"><?= (int)$stats['favorites'] ?></div>
    </div>
    <div class="am-stat am-stat-green">
        <div class="am-stat-label"><i class="fas fa-eye"></i> Всего открытий</div>
        <div class="am-stat-value"><?= (int)$stats['total_views'] ?></div>
    </div>
    <div class="am-stat am-stat-purple">
        <div class="am-stat-label"><i class="fas fa-folder"></i> Категорий</div>
        <div class="am-stat-value"><?= (int)$stats['categories_used'] ?></div>
    </div>
</div>

<!-- Часто используемые -->
<div class="am-block">
    <div class="am-section-head">
        <h2 class="am-h2">
            <i class="fas fa-fire am-text-danger"></i> Часто используемые
            <span class="am-count"><?= count($mostUsed) ?></span>
        </h2>
    </div>

    <?php if (empty($mostUsed)): ?>
        <div class="am-empty">
            <div class="am-empty-icon"><i class="fas fa-vault"></i></div>
            <p class="am-empty-title">Пока нет секретов</p>
            <p class="am-empty-text">Создайте первую запись — она появится здесь.</p>
            <a href="/local_secrets/pages/secret_form.php" class="am-btn am-btn-primary am-btn-sm">
                <i class="fas fa-plus"></i> Добавить секрет
            </a>
        </div>
    <?php else: ?>
        <div class="am-grid am-grid-cols-4">
            <?php foreach ($mostUsed as $secret): ?>
                <?php render_secret_card($secret, false); ?>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<!-- Недавно открытые -->
<?php if (!empty($recent)): ?>
    <div class="am-block">
        <div class="am-section-head">
            <h2 class="am-h2">
                <i class="fas fa-clock-rotate-left am-text-info"></i> Недавно открытые
                <span class="am-count"><?= count($recent) ?></span>
            </h2>
        </div>
        <div class="am-grid am-grid-cols-4">
            <?php foreach ($recent as $secret): ?>
                <?php render_secret_card($secret, true); ?>
            <?php endforeach; ?>
        </div>
    </div>
<?php else: ?>
    <p class="am-text-sm am-muted am-text-center">
        <i class="fas fa-info-circle"></i>
        Откройте любой секрет — он появится в блоке «Недавно открытые».
    </p>
<?php endif; ?>

<?php
$content = ob_get_clean();
include __DIR__ . '/../templates/layout.php';
