<?php
$currentScript = basename($_SERVER['SCRIPT_NAME']);
$currentCatId  = isset($_GET['cat']) ? (int)$_GET['cat'] : null;
$isFavView     = !empty($_GET['fav']);
$isAllSecrets  = $currentScript === 'index.php' && !$currentCatId && !$isFavView;
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle ?? 'Секреты') ?> — <?= APP_NAME ?></title>
    <link rel="icon" type="image/svg+xml" href="/local_secrets/assets/favicon.svg">
    <script>
        // Применить тему до рендера (избежать мигания)
        (function(){var t=localStorage.getItem('theme')||'light';document.documentElement.setAttribute('data-am-theme',t);})();
    </script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css" rel="stylesheet">
    <link href="/local_secrets/assets/css/app.css?v=<?= APP_VERSION ?>" rel="stylesheet">
</head>
<body>
    <div class="am-shell">
        <!-- Sidebar -->
        <aside class="am-sidebar" id="amSidebar">
            <a href="/local_secrets/pages/dashboard.php" class="am-brand">
                <img class="am-brand-mark" src="/local_secrets/assets/favicon.svg" alt="<?= APP_NAME ?>">
                <div>
                    <div class="am-brand-name"><?= APP_NAME ?></div>
                    <div class="am-brand-sub">v<?= APP_VERSION ?></div>
                </div>
            </a>

            <nav class="am-nav">
                <a class="am-nav-item <?= $currentScript === 'dashboard.php' ? 'is-active' : '' ?>"
                   href="/local_secrets/pages/dashboard.php">
                    <i class="fas fa-house"></i>
                    <span class="am-nav-label">Главная</span>
                </a>
                <a class="am-nav-item <?= $isAllSecrets ? 'is-active' : '' ?>"
                   href="/local_secrets/index.php">
                    <i class="fas fa-layer-group"></i>
                    <span class="am-nav-label">Все секреты</span>
                    <?php if (isset($stats['total'])): ?>
                        <span class="am-nav-badge"><?= (int)$stats['total'] ?></span>
                    <?php endif; ?>
                </a>
                <a class="am-nav-item <?= $isFavView ? 'is-active' : '' ?>"
                   href="/local_secrets/index.php?fav=1">
                    <i class="fas fa-star"></i>
                    <span class="am-nav-label">Избранное</span>
                    <?php if (isset($stats['favorites'])): ?>
                        <span class="am-nav-badge"><?= (int)$stats['favorites'] ?></span>
                    <?php endif; ?>
                </a>

                <div class="am-nav-section">
                    <span>Категории</span>
                    <a href="/local_secrets/pages/categories.php" title="Управление категориями">
                        <i class="fas fa-gear"></i>
                    </a>
                </div>

                <?php if (!empty($categories)): ?>
                    <?php foreach ($categories as $cat): ?>
                        <a class="am-nav-item <?= $currentCatId === (int)$cat['id'] ? 'is-active' : '' ?>"
                           href="/local_secrets/index.php?cat=<?= (int)$cat['id'] ?>">
                            <i class="fas <?= htmlspecialchars($cat['icon'] ?? 'fa-folder') ?>"
                               style="color: <?= htmlspecialchars($cat['color'] ?? 'var(--am-text-3)') ?>"></i>
                            <span class="am-nav-label"><?= htmlspecialchars($cat['name']) ?></span>
                            <?php if (!empty($cat['secret_count'])): ?>
                                <span class="am-nav-badge"><?= (int)$cat['secret_count'] ?></span>
                            <?php endif; ?>
                        </a>
                    <?php endforeach; ?>
                <?php endif; ?>

                <div class="am-nav-section"><span>Действия</span></div>

                <a class="am-nav-item <?= $currentScript === 'secret_form.php' ? 'is-active' : '' ?>"
                   href="/local_secrets/pages/secret_form.php">
                    <i class="fas fa-plus"></i>
                    <span class="am-nav-label">Добавить вручную</span>
                </a>
                <a class="am-nav-item <?= $currentScript === 'smart_add.php' ? 'is-active' : '' ?>"
                   href="/local_secrets/pages/smart_add.php">
                    <i class="fas fa-wand-magic-sparkles"></i>
                    <span class="am-nav-label">Умное добавление</span>
                </a>
                <a class="am-nav-item <?= $currentScript === 'tags.php' ? 'is-active' : '' ?>"
                   href="/local_secrets/pages/tags.php">
                    <i class="fas fa-tags"></i>
                    <span class="am-nav-label">Теги</span>
                </a>

                <div class="am-nav-section"><span>Администрирование</span></div>

                <a class="am-nav-item <?= $currentScript === 'settings.php' ? 'is-active' : '' ?>"
                   href="/local_secrets/pages/settings.php">
                    <i class="fas fa-sliders"></i>
                    <span class="am-nav-label">Настройки</span>
                </a>
                <a class="am-nav-item <?= $currentScript === 'backup.php' ? 'is-active' : '' ?>"
                   href="/local_secrets/pages/backup.php">
                    <i class="fas fa-database"></i>
                    <span class="am-nav-label">Резервное копирование</span>
                </a>
            </nav>

            <div class="am-sidebar-foot">
                PHP <?= phpversion() ?> · AES-256
            </div>
        </aside>

        <!-- Main column -->
        <div class="am-main">
            <?php include __DIR__ . '/header.php'; ?>

            <div class="am-content">
                <?= $content ?? '' ?>
            </div>
        </div>
    </div>

    <!-- Скрытая форма удаления (для list-view) -->
    <form id="deleteForm" method="POST" style="display:none;">
        <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?? '' ?>">
        <input type="hidden" name="action" value="delete">
        <input type="hidden" name="id" id="deleteId">
    </form>

    <script>
        window.CSRF_TOKEN = '<?= $_SESSION['csrf_token'] ?? '' ?>';
        window.SESSION_TIMEOUT = <?= defined('SESSION_TIMEOUT') ? (int)SESSION_TIMEOUT : 1800 ?>;
    </script>
    <script src="/local_secrets/assets/js/app.js?v=<?= APP_VERSION ?>"></script>
</body>
</html>
