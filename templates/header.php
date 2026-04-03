<nav class="navbar navbar-expand-md border-bottom" data-bs-theme="dark">
    <div class="container-fluid">
        <a class="navbar-brand" href="/local_secrets/index.php">
            <i class="fas fa-shield-halved me-2 text-info"></i><?= APP_NAME ?>
        </a>

        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#sidebarMenu">
            <span class="navbar-toggler-icon"></span>
        </button>

        <div class="d-flex align-items-center">
            <!-- Поиск -->
            <div class="position-relative me-3">
                <input type="text" class="form-control form-control-sm border-secondary"
                       id="globalSearch" placeholder="Поиск..." style="width: 400px;">
                <div id="searchResults" class="dropdown-menu w-100" style="display:none; max-height:300px; overflow-y:auto;"></div>
            </div>

            <button class="btn btn-sm me-2 theme-toggle" id="themeToggle" title="Сменить тему" onclick="toggleTheme()">
                <i class="fas fa-sun" id="themeIcon"></i>
            </button>
            <span class="text-muted small me-3">v<?= APP_VERSION ?></span>
            <a href="/local_secrets/logout.php" class="btn btn-outline-secondary btn-sm" title="Выход">
                <i class="fas fa-right-from-bracket"></i>
            </a>
        </div>
    </div>
</nav>
