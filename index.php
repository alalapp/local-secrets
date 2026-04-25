<?php
/**
 * Главная страница — список секретов
 */
require_once __DIR__ . '/bootstrap.php';
Auth::requireAuth();

$categoryService = new CategoryService();
$secretService = new SecretService();
$tagService = new TagService();

$categories = $categoryService->getAll();
$stats = $secretService->getStats();

// Фильтры
$categoryId = isset($_GET['cat']) ? (int)$_GET['cat'] : null;
$search = isset($_GET['q']) ? trim($_GET['q']) : null;
$favoritesOnly = !empty($_GET['fav']);
$tagId = isset($_GET['tag']) ? (int)$_GET['tag'] : null;
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = PER_PAGE;

// Получить доступные теги для текущей категории
$availableTags = $tagService->getByCategory($categoryId);

$result = $secretService->getAll($categoryId, $search, $favoritesOnly, $tagId, $page, $perPage);
$secrets = $result['items'];
$totalPages = $result['totalPages'];
$totalSecrets = $result['total'];

// Заголовок страницы
$pageTitle = 'Все секреты';
$pageEyebrow = 'Каталог';
$currentCat = null;
if ($categoryId) {
    $currentCat = $categoryService->getById($categoryId);
    $pageTitle = $currentCat ? $currentCat['name'] : 'Секреты';
    $pageEyebrow = 'Категория';
} elseif ($favoritesOnly) {
    $pageTitle = 'Избранное';
    $pageEyebrow = 'Каталог';
} elseif ($search) {
    $pageTitle = "Поиск: {$search}";
    $pageEyebrow = 'Поиск';
}

// Обработка удаления
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete' && verify_csrf()) {
    $secretService->delete((int)$_POST['id']);
    header('Location: ' . $_SERVER['REQUEST_URI']);
    exit;
}

// Обработка избранного
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'toggle_favorite' && verify_csrf()) {
    $secretService->toggleFavorite((int)$_POST['id']);
    header('Location: ' . $_SERVER['REQUEST_URI']);
    exit;
}

ob_start();
?>

<div class="am-page-head">
    <div class="am-page-head-text">
        <div class="am-eyebrow"><?= htmlspecialchars($pageEyebrow) ?></div>
        <h1 class="am-h1">
            <?php if ($currentCat): ?>
                <i class="fas <?= htmlspecialchars($currentCat['icon'] ?? 'fa-folder') ?>"
                   style="color: <?= htmlspecialchars($currentCat['color'] ?? 'var(--am-text-3)') ?>"></i>
            <?php elseif ($favoritesOnly): ?>
                <i class="fas fa-star am-text-warning"></i>
            <?php elseif ($search): ?>
                <i class="fas fa-magnifying-glass am-muted"></i>
            <?php else: ?>
                <i class="fas fa-layer-group am-muted"></i>
            <?php endif; ?>
            <?= htmlspecialchars($pageTitle) ?>
            <span class="am-count"><?= (int)$totalSecrets ?></span>
        </h1>
    </div>
    <div class="am-page-head-actions">
        <?php $addQuery = $categoryId ? ('?cat=' . $categoryId) : ''; ?>
        <a href="/local_secrets/pages/smart_add.php<?= $addQuery ?>" class="am-btn am-btn-ghost am-btn-sm">
            <i class="fas fa-wand-magic-sparkles"></i> Умное добавление
        </a>
        <a href="/local_secrets/pages/secret_form.php<?= $addQuery ?>" class="am-btn am-btn-primary am-btn-sm">
            <i class="fas fa-plus"></i> Добавить
        </a>
    </div>
</div>

<?php if (!empty($availableTags)): ?>
    <div class="am-mb-4">
        <div class="am-flex am-flex-wrap am-gap-2 am-items-center">
            <?php if ($tagId): ?>
                <?php
                    $clearParams = [];
                    if ($categoryId) $clearParams['cat'] = $categoryId;
                    if ($favoritesOnly) $clearParams['fav'] = 1;
                    if ($search) $clearParams['q'] = $search;
                    $clearUrl = '/local_secrets/index.php' . ($clearParams ? '?' . http_build_query($clearParams) : '');
                ?>
                <a href="<?= htmlspecialchars($clearUrl) ?>" class="am-chip am-chip-tag">
                    <i class="fas fa-times"></i> Сбросить фильтр
                </a>
            <?php endif; ?>

            <?php foreach ($availableTags as $tag): ?>
                <?php
                    $isActive = $tagId === (int)$tag['id'];
                    $params = [];
                    if ($categoryId) $params['cat'] = $categoryId;
                    if ($favoritesOnly) $params['fav'] = 1;
                    if ($search) $params['q'] = $search;
                    $params['tag'] = $tag['id'];
                    $tagUrl = '/local_secrets/index.php?' . http_build_query($params);
                ?>
                <a href="<?= htmlspecialchars($tagUrl) ?>"
                   class="am-chip am-chip-tag <?= $isActive ? 'is-active' : '' ?>"
                   title="Фильтр по тегу: <?= htmlspecialchars($tag['name']) ?>">
                    <?= htmlspecialchars($tag['name']) ?>
                </a>
            <?php endforeach; ?>
        </div>
    </div>
<?php endif; ?>

<?php if (empty($secrets)): ?>
    <div class="am-empty">
        <div class="am-empty-icon"><i class="fas fa-vault"></i></div>
        <p class="am-empty-title">Нет секретов<?= $categoryId ? ' в этой категории' : '' ?></p>
        <p class="am-empty-text">Создайте первую запись и она появится здесь.</p>
        <a href="/local_secrets/pages/secret_form.php<?= $categoryId ? ('?cat=' . $categoryId) : '' ?>"
           class="am-btn am-btn-primary am-btn-sm">
            <i class="fas fa-plus"></i> Добавить секрет
        </a>
    </div>
<?php else: ?>
    <div class="am-table-wrap">
        <table class="am-table">
            <thead>
                <tr>
                    <th class="am-td-shrink"></th>
                    <th>Сервис</th>
                    <th>Категория</th>
                    <th>Теги</th>
                    <th>Обновлён</th>
                    <th class="am-td-actions">Действия</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($secrets as $secret): ?>
                    <tr>
                        <td class="am-td-shrink">
                            <form method="POST" style="display:inline;">
                                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                                <input type="hidden" name="action" value="toggle_favorite">
                                <input type="hidden" name="id" value="<?= $secret['id'] ?>">
                                <button type="submit" class="am-fav-btn <?= $secret['is_favorite'] ? 'is-fav' : '' ?>"
                                        title="Избранное">
                                    <i class="fas fa-star"></i>
                                </button>
                            </form>
                        </td>
                        <td>
                            <a href="/local_secrets/pages/secret_view.php?id=<?= $secret['id'] ?>"
                               class="am-fw-500" style="color: var(--am-text-1);">
                                <?= htmlspecialchars($secret['service_name']) ?>
                            </a>
                        </td>
                        <td>
                            <?php if ($secret['category_name']): ?>
                                <?php $cc = $secret['category_color'] ?? '#888'; ?>
                                <span class="am-chip am-chip-cat"
                                      style="background:<?= htmlspecialchars($cc) ?>22;color:<?= htmlspecialchars($cc) ?>;border-color:<?= htmlspecialchars($cc) ?>33;">
                                    <i class="fas <?= htmlspecialchars($secret['category_icon'] ?? 'fa-folder') ?>"></i>
                                    <?= htmlspecialchars($secret['category_name']) ?>
                                </span>
                            <?php else: ?>
                                <span class="am-muted">—</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($secret['tags_list']): ?>
                                <span class="am-flex am-flex-wrap am-gap-1">
                                    <?php foreach (explode(', ', $secret['tags_list']) as $tag): ?>
                                        <span class="am-chip am-chip-tag"><?= htmlspecialchars($tag) ?></span>
                                    <?php endforeach; ?>
                                </span>
                            <?php else: ?>
                                <span class="am-muted">—</span>
                            <?php endif; ?>
                        </td>
                        <td class="am-text-sm am-muted"><?= date('d.m.Y H:i', strtotime($secret['updated_at'])) ?></td>
                        <td class="am-td-actions">
                            <span class="am-btn-group">
                                <a href="/local_secrets/pages/secret_view.php?id=<?= $secret['id'] ?>"
                                   class="am-icon-btn is-info" title="Просмотр">
                                    <i class="fas fa-eye"></i>
                                </a>
                                <a href="/local_secrets/pages/secret_form.php?id=<?= $secret['id'] ?>"
                                   class="am-icon-btn is-warning" title="Редактировать">
                                    <i class="fas fa-pen"></i>
                                </a>
                                <button class="am-icon-btn is-danger btn-delete"
                                        type="button"
                                        data-id="<?= $secret['id'] ?>"
                                        data-name="<?= htmlspecialchars($secret['service_name'], ENT_QUOTES) ?>"
                                        title="Удалить">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </span>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <?php if ($totalPages > 1): ?>
        <nav class="am-flex am-justify-center">
            <div class="am-pagination">
                <?php
                $queryParams = [];
                if ($categoryId) $queryParams['cat'] = $categoryId;
                if ($favoritesOnly) $queryParams['fav'] = 1;
                if ($search) $queryParams['q'] = $search;
                if ($tagId) $queryParams['tag'] = $tagId;

                $buildUrl = function (int $p) use ($queryParams): string {
                    $queryParams['page'] = $p;
                    return '/local_secrets/index.php?' . http_build_query($queryParams);
                };
                ?>
                <a class="am-page-link <?= $page <= 1 ? 'is-disabled' : '' ?>"
                   href="<?= $buildUrl(max(1, $page - 1)) ?>" aria-label="Назад">
                    <i class="fas fa-chevron-left"></i>
                </a>
                <?php
                $range = 2;
                $start = max(1, $page - $range);
                $end = min($totalPages, $page + $range);

                if ($start > 1): ?>
                    <a class="am-page-link" href="<?= $buildUrl(1) ?>">1</a>
                    <?php if ($start > 2): ?>
                        <span class="am-page-ellipsis">…</span>
                    <?php endif; ?>
                <?php endif;

                for ($p = $start; $p <= $end; $p++): ?>
                    <a class="am-page-link <?= $p === $page ? 'is-active' : '' ?>"
                       href="<?= $buildUrl($p) ?>"><?= $p ?></a>
                <?php endfor;

                if ($end < $totalPages): ?>
                    <?php if ($end < $totalPages - 1): ?>
                        <span class="am-page-ellipsis">…</span>
                    <?php endif; ?>
                    <a class="am-page-link" href="<?= $buildUrl($totalPages) ?>"><?= $totalPages ?></a>
                <?php endif; ?>
                <a class="am-page-link <?= $page >= $totalPages ? 'is-disabled' : '' ?>"
                   href="<?= $buildUrl(min($totalPages, $page + 1)) ?>" aria-label="Вперёд">
                    <i class="fas fa-chevron-right"></i>
                </a>
            </div>
        </nav>
    <?php endif; ?>
<?php endif; ?>

<?php
$content = ob_get_clean();
include __DIR__ . '/templates/layout.php';
