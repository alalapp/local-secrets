<header class="am-header">
    <button class="am-burger" id="amBurger" type="button" aria-label="Меню">
        <i class="fas fa-bars"></i>
    </button>

    <h1 class="am-header-title"><?= htmlspecialchars($pageTitle ?? APP_NAME) ?></h1>

    <div class="am-search-wrap">
        <i class="fas fa-search am-search-icon"></i>
        <input type="text" class="am-search-input" id="globalSearch"
               placeholder="Поиск по сервисам, тегам…"
               autocomplete="off" spellcheck="false">
        <div class="am-search-results" id="searchResults"></div>
    </div>

    <div class="am-header-actions">
        <div class="am-avatar-wrap">
            <button class="am-avatar" type="button"
                    data-am-dropdown-toggle="amUserDropdown"
                    title="Меню пользователя">
                <i class="fas fa-user"></i>
            </button>
            <div class="am-dropdown" id="amUserDropdown">
                <div class="am-dropdown-meta">
                    <?= APP_NAME ?> · v<?= APP_VERSION ?>
                </div>
                <div class="am-dropdown-divider"></div>
                <a class="am-dropdown-item" href="/local_secrets/pages/settings.php">
                    <i class="fas fa-sliders"></i> Настройки
                </a>
                <a class="am-dropdown-item" href="/local_secrets/pages/backup.php">
                    <i class="fas fa-database"></i> Резервное копирование
                </a>
                <div class="am-dropdown-divider"></div>
                <a class="am-dropdown-item" href="/local_secrets/logout.php">
                    <i class="fas fa-right-from-bracket"></i> Выйти
                </a>
            </div>
        </div>
    </div>
</header>
