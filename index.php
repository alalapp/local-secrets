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
if ($categoryId) {
    $currentCat = $categoryService->getById($categoryId);
    $pageTitle = $currentCat ? $currentCat['name'] : 'Секреты';
} elseif ($favoritesOnly) {
    $pageTitle = 'Избранное';
} elseif ($search) {
    $pageTitle = "Поиск: {$search}";
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

<div class="d-flex justify-content-between align-items-center mb-3">
    <h4 class="mb-0">
        <?php if ($categoryId && isset($currentCat)): ?>
            <i class="fas <?= htmlspecialchars($currentCat['icon'] ?? 'fa-folder') ?> me-2"
               style="color: <?= htmlspecialchars($currentCat['color'] ?? '#888') ?>"></i>
        <?php endif; ?>
        <?= htmlspecialchars($pageTitle) ?>
        <span class="badge bg-secondary fs-6 ms-2"><?= $totalSecrets ?></span>
    </h4>
    <div>
        <?php $addQuery = $categoryId ? ('?cat=' . $categoryId) : ''; ?>
        <a href="/local_secrets/pages/smart_add.php<?= $addQuery ?>" class="btn btn-info btn-sm me-1 text-white">
            <i class="fas fa-robot me-1"></i> Умное добавление
        </a>
        <a href="/local_secrets/pages/secret_form.php<?= $addQuery ?>" class="btn btn-success btn-sm">
            <i class="fas fa-plus me-1"></i> Добавить
        </a>
    </div>
</div>

<?php if (!empty($availableTags)): ?>
    <div class="mb-3">
        <!-- Кнопка очистки фильтра тегов -->
        <?php if ($tagId): ?>
            <div class="mb-3">
                <a href="<?php
                    $clearParams = [];
                    if ($categoryId) $clearParams['cat'] = $categoryId;
                    if ($favoritesOnly) $clearParams['fav'] = 1;
                    if ($search) $clearParams['q'] = $search;
                    echo '/local_secrets/index.php?' . http_build_query($clearParams);
                ?>" class="btn btn-outline-secondary btn-sm">
                    <i class="fas fa-times me-1"></i> Очистить фильтр по тегам
                </a>
            </div>
        <?php endif; ?>

        <!-- Теги -->
        <div class="d-flex flex-wrap gap-2">
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
                   class="badge <?= $isActive ? 'bg-primary' : 'bg-light text-dark border' ?> text-decoration-none"
                   title="Фильтр по тегу: <?= htmlspecialchars($tag['name']) ?>">
                    <?= htmlspecialchars($tag['name']) ?>
                </a>
            <?php endforeach; ?>
        </div>
    </div>
<?php endif; ?>

<?php if (empty($secrets)): ?>
    <div class="text-center text-muted py-5">
        <i class="fas fa-vault fa-3x mb-3"></i>
        <p>Нет секретов<?= $categoryId ? ' в этой категории' : '' ?></p>
        <a href="/local_secrets/pages/secret_form.php<?= $categoryId ? ('?cat=' . $categoryId) : '' ?>" class="btn btn-outline-success btn-sm">
            <i class="fas fa-plus me-1"></i> Добавить первый секрет
        </a>
    </div>
<?php else: ?>
    <div class="table-responsive">
        <table class="table table-hover align-middle">
            <thead>
                <tr>
                    <th style="width:30px"></th>
                    <th>Сервис</th>
                    <th>Категория</th>
                    <th>Теги</th>
                    <th>Обновлён</th>
                    <th style="width:160px">Действия</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($secrets as $secret): ?>
                    <tr>
                        <td>
                            <form method="POST" class="d-inline">
                                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                                <input type="hidden" name="action" value="toggle_favorite">
                                <input type="hidden" name="id" value="<?= $secret['id'] ?>">
                                <button type="submit" class="btn btn-link p-0 border-0" title="Избранное">
                                    <i class="fas fa-star <?= $secret['is_favorite'] ? 'text-warning' : 'text-secondary' ?>"></i>
                                </button>
                            </form>
                        </td>
                        <td>
                            <a href="/local_secrets/pages/secret_view.php?id=<?= $secret['id'] ?>"
                               class="text-decoration-none" style="font-size: 0.95rem;">
                                <?= htmlspecialchars($secret['service_name']) ?>
                            </a>
                        </td>
                        <td>
                            <?php if ($secret['category_name']): ?>
                                <span class="badge" style="background-color: <?= htmlspecialchars($secret['category_color'] ?? '#666') ?>">
                                    <i class="fas <?= htmlspecialchars($secret['category_icon'] ?? 'fa-folder') ?> me-1"></i>
                                    <?= htmlspecialchars($secret['category_name']) ?>
                                </span>
                            <?php else: ?>
                                <span class="text-muted">—</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($secret['tags_list']): ?>
                                <?php foreach (explode(', ', $secret['tags_list']) as $tag): ?>
                                    <span class="badge border border-secondary text-secondary me-1"><?= htmlspecialchars($tag) ?></span>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </td>
                        <td class="text-muted small"><?= date('d.m.Y H:i', strtotime($secret['updated_at'])) ?></td>
                        <td>
                            <a href="/local_secrets/pages/secret_view.php?id=<?= $secret['id'] ?>"
                               class="btn btn-outline-info btn-sm me-1" title="Просмотр">
                                <i class="fas fa-eye"></i>
                            </a>
                            <a href="/local_secrets/pages/secret_form.php?id=<?= $secret['id'] ?>"
                               class="btn btn-outline-warning btn-sm me-1" title="Редактировать">
                                <i class="fas fa-pen"></i>
                            </a>
                            <button class="btn btn-outline-danger btn-sm btn-delete"
                                    data-id="<?= $secret['id'] ?>"
                                    data-name="<?= htmlspecialchars($secret['service_name']) ?>"
                                    title="Удалить">
                                <i class="fas fa-trash"></i>
                            </button>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <?php if ($totalPages > 1): ?>
        <nav class="mt-3">
            <ul class="pagination pagination-sm justify-content-center mb-0">
                <?php
                // Сохранить текущие фильтры в URL
                $queryParams = [];
                if ($categoryId) $queryParams['cat'] = $categoryId;
                if ($favoritesOnly) $queryParams['fav'] = 1;
                if ($search) $queryParams['q'] = $search;
                if ($tagId) $queryParams['tag'] = $tagId;

                $buildUrl = function(int $p) use ($queryParams): string {
                    $queryParams['page'] = $p;
                    return '/local_secrets/index.php?' . http_build_query($queryParams);
                };
                ?>
                <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
                    <a class="page-link" href="<?= $buildUrl($page - 1) ?>"><i class="fas fa-chevron-left"></i></a>
                </li>
                <?php
                // Показать диапазон страниц вокруг текущей
                $range = 2;
                $start = max(1, $page - $range);
                $end = min($totalPages, $page + $range);

                if ($start > 1): ?>
                    <li class="page-item"><a class="page-link" href="<?= $buildUrl(1) ?>">1</a></li>
                    <?php if ($start > 2): ?><li class="page-item disabled"><span class="page-link">...</span></li><?php endif; ?>
                <?php endif;

                for ($p = $start; $p <= $end; $p++): ?>
                    <li class="page-item <?= $p === $page ? 'active' : '' ?>">
                        <a class="page-link" href="<?= $buildUrl($p) ?>"><?= $p ?></a>
                    </li>
                <?php endfor;

                if ($end < $totalPages): ?>
                    <?php if ($end < $totalPages - 1): ?><li class="page-item disabled"><span class="page-link">...</span></li><?php endif; ?>
                    <li class="page-item"><a class="page-link" href="<?= $buildUrl($totalPages) ?>"><?= $totalPages ?></a></li>
                <?php endif; ?>
                <li class="page-item <?= $page >= $totalPages ? 'disabled' : '' ?>">
                    <a class="page-link" href="<?= $buildUrl($page + 1) ?>"><i class="fas fa-chevron-right"></i></a>
                </li>
            </ul>
        </nav>
    <?php endif; ?>

    <!-- Скрытая форма удаления -->
    <form id="deleteForm" method="POST" style="display:none;">
        <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
        <input type="hidden" name="action" value="delete">
        <input type="hidden" name="id" id="deleteId">
    </form>
<?php endif; ?>

<?php
$content = ob_get_clean();
include __DIR__ . '/templates/layout.php';
