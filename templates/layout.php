<!DOCTYPE html>
<html lang="ru" data-bs-theme="dark">
<script>
    // Применить тему до рендера (избежать мигания)
    (function(){var t=localStorage.getItem('theme')||'dark';document.documentElement.setAttribute('data-bs-theme',t);})();
</script>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle ?? 'Секреты') ?> — <?= APP_NAME ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css" rel="stylesheet">
    <link href="/local_secrets/assets/css/app.css" rel="stylesheet">
</head>
<body>
    <?php include __DIR__ . '/header.php'; ?>

    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <nav class="col-md-3 col-lg-2 d-md-block sidebar collapse" id="sidebarMenu">
                <div class="position-sticky pt-3">
                    <h6 class="sidebar-heading d-flex justify-content-between align-items-center px-3 mb-2 text-muted">
                        <span>Категории</span>
                        <a href="/local_secrets/pages/categories.php" class="text-muted" title="Управление">
                            <i class="fas fa-gear fa-sm"></i>
                        </a>
                    </h6>
                    <ul class="nav flex-column">
                        <li class="nav-item">
                            <a class="nav-link <?= empty($_GET['cat']) && empty($_GET['fav']) ? 'active' : '' ?>"
                               href="/local_secrets/index.php">
                                <i class="fas fa-layer-group me-2"></i> Все секреты
                                <?php if (isset($stats)): ?>
                                    <span class="badge bg-secondary ms-auto"><?= $stats['total'] ?></span>
                                <?php endif; ?>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link <?= !empty($_GET['fav']) ? 'active' : '' ?>"
                               href="/local_secrets/index.php?fav=1">
                                <i class="fas fa-star me-2 text-warning"></i> Избранное
                                <?php if (isset($stats)): ?>
                                    <span class="badge bg-secondary ms-auto"><?= $stats['favorites'] ?></span>
                                <?php endif; ?>
                            </a>
                        </li>
                        <li><hr class="dropdown-divider mx-3"></li>
                        <?php if (isset($categories)): ?>
                            <?php foreach ($categories as $cat): ?>
                                <li class="nav-item">
                                    <a class="nav-link <?= ($_GET['cat'] ?? '') == $cat['id'] ? 'active' : '' ?>"
                                       href="/local_secrets/index.php?cat=<?= $cat['id'] ?>">
                                        <i class="fas <?= htmlspecialchars($cat['icon'] ?? 'fa-folder') ?> me-2"
                                           style="color: <?= htmlspecialchars($cat['color'] ?? '#888') ?>"></i>
                                        <?= htmlspecialchars($cat['name']) ?>
                                        <?php if ($cat['secret_count'] > 0): ?>
                                            <span class="badge bg-secondary ms-auto"><?= $cat['secret_count'] ?></span>
                                        <?php endif; ?>
                                    </a>
                                </li>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </ul>

                    <h6 class="sidebar-heading d-flex justify-content-between align-items-center px-3 mt-4 mb-2 text-muted">
                        <span>Действия</span>
                    </h6>
                    <ul class="nav flex-column mb-2">
                        <li class="nav-item">
                            <a class="nav-link" href="/local_secrets/pages/secret_form.php">
                                <i class="fas fa-plus me-2 text-success"></i> Добавить вручную
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="/local_secrets/pages/smart_add.php">
                                <i class="fas fa-robot me-2 text-info"></i> Умное добавление
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="/local_secrets/pages/settings.php">
                                <i class="fas fa-cog me-2"></i> Настройки
                            </a>
                        </li>
                    </ul>
                </div>
            </nav>

            <!-- Main content -->
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 pt-3">
                <?= $content ?? '' ?>
            </main>
        </div>
    </div>

    <!-- Toast для уведомлений -->
    <div class="toast-container position-fixed bottom-0 end-0 p-3">
        <div id="appToast" class="toast" role="alert">
            <div class="toast-body" id="toastBody"></div>
        </div>
    </div>

    <!-- Модалка подтверждения удаления -->
    <div class="modal fade" id="deleteModal" tabindex="-1">
        <div class="modal-dialog modal-sm">
            <div class="modal-content">
                <div class="modal-header">
                    <h6 class="modal-title">Подтвердите удаление</h6>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" id="deleteModalBody">Удалить эту запись?</div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Отмена</button>
                    <button type="button" class="btn btn-sm btn-danger" id="deleteConfirmBtn">Удалить</button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script>const CSRF_TOKEN = '<?= $_SESSION['csrf_token'] ?? '' ?>';</script>
    <script src="/local_secrets/assets/js/app.js"></script>
</body>
</html>
