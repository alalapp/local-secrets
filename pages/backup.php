<?php
/**
 * Резервное копирование — экспорт/импорт БД и настроек
 */
require_once __DIR__ . '/../bootstrap.php';
Auth::requireAuth();

$categoryService = new CategoryService();
$categories      = $categoryService->getAll();
$stats           = (new SecretService())->getStats();

$importSuccess = htmlspecialchars($_GET['import_success'] ?? '');
$importError   = htmlspecialchars($_GET['import_error']   ?? '');

// Размер папки БД не доступен напрямую, покажем кол-во записей
$db         = Database::getInstance();
$dbSizeMb   = $db->fetchColumn(
    "SELECT ROUND(SUM(data_length + index_length) / 1024 / 1024, 2)
     FROM information_schema.tables
     WHERE table_schema = DATABASE()"
);

$pageTitle = 'Резервное копирование';
ob_start();
?>

<h4 class="mb-3"><i class="fas fa-database me-2"></i> Резервное копирование</h4>

<?php if ($importSuccess): ?>
    <div class="alert alert-success alert-dismissible py-2 fade show">
        <i class="fas fa-check-circle me-1"></i> <?= $importSuccess ?>
        <button type="button" class="btn-close btn-sm" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>
<?php if ($importError): ?>
    <div class="alert alert-danger alert-dismissible py-2 fade show">
        <i class="fas fa-times-circle me-1"></i> <?= $importError ?>
        <button type="button" class="btn-close btn-sm" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<!-- Статус -->
<div class="row g-2 mb-3">
    <div class="col-auto">
        <span class="badge bg-secondary fs-6">
            <i class="fas fa-lock me-1"></i> <?= $stats['fields'] ?> зашифрованных полей
        </span>
    </div>
    <div class="col-auto">
        <span class="badge bg-secondary fs-6">
            <i class="fas fa-key me-1"></i> <?= $stats['total'] ?> секретов
        </span>
    </div>
    <?php if ($dbSizeMb !== null): ?>
    <div class="col-auto">
        <span class="badge bg-secondary fs-6">
            <i class="fas fa-hdd me-1"></i> ~<?= $dbSizeMb ?> МБ в БД
        </span>
    </div>
    <?php endif; ?>
</div>

<div class="row g-3">

    <!-- ── Экспорт БД ── -->
    <div class="col-md-6">
        <div class="card h-100">
            <div class="card-header fw-semibold">
                <i class="fas fa-file-arrow-down me-2 text-success"></i> Экспорт базы данных
            </div>
            <div class="card-body d-flex flex-column">
                <p class="text-muted small mb-2">
                    Полный SQL-дамп всех таблиц: секреты (зашифрованы AES-256),
                    категории, теги, лог активности.
                </p>
                <div class="alert alert-warning py-2 small mb-3">
                    <i class="fas fa-triangle-exclamation me-1"></i>
                    Дамп содержит зашифрованные данные. Для восстановления потребуется
                    тот же <strong>ключ шифрования</strong> из <code>config.php</code>.
                </div>
                <form method="POST" action="/local_secrets/api/backup.php" class="mt-auto">
                    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                    <input type="hidden" name="action"     value="export_sql">
                    <button type="submit" class="btn btn-success btn-sm">
                        <i class="fas fa-download me-1"></i> Скачать SQL-дамп
                    </button>
                </form>
            </div>
        </div>
    </div>

    <!-- ── Экспорт настроек ── -->
    <div class="col-md-6">
        <div class="card h-100">
            <div class="card-header fw-semibold">
                <i class="fas fa-sliders me-2 text-info"></i> Экспорт настроек
            </div>
            <div class="card-body d-flex flex-column">
                <p class="text-muted small mb-2">
                    Сохраняет параметры приложения в JSON-файл:
                    отображение, сессия, таймауты, конфигурация LLM.
                </p>
                <div class="alert alert-info py-2 small mb-3">
                    <i class="fas fa-circle-info me-1"></i>
                    Ключ шифрования и учётные данные БД
                    <strong>не включены</strong> в этот файл.
                </div>
                <form method="POST" action="/local_secrets/api/backup.php" class="mt-auto">
                    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                    <input type="hidden" name="action"     value="export_settings">
                    <button type="submit" class="btn btn-info btn-sm">
                        <i class="fas fa-download me-1"></i> Скачать настройки (JSON)
                    </button>
                </form>
            </div>
        </div>
    </div>

    <!-- ── Ключ шифрования ── -->
    <div class="col-12">
        <div class="card border-warning">
            <div class="card-header fw-semibold text-warning">
                <i class="fas fa-key me-2"></i> Ключ шифрования (ENCRYPTION_KEY)
            </div>
            <div class="card-body">
                <div class="alert alert-danger py-2 small mb-3">
                    <i class="fas fa-skull-crossbones me-1"></i>
                    <strong>Критически важно!</strong>
                    Без этого ключа все зашифрованные данные будут <strong>безвозвратно утеряны</strong>.
                    Храните ключ отдельно от дампа базы данных — в менеджере паролей или на бумаге.
                </div>
                <div class="input-group input-group-sm">
                    <input type="password" id="encKeyInput"
                           class="form-control font-monospace"
                           value="<?= htmlspecialchars(ENCRYPTION_KEY) ?>"
                           readonly autocomplete="off">
                    <button class="btn btn-outline-secondary" type="button"
                            id="encKeyToggle" onclick="toggleEncKey()" title="Показать/скрыть">
                        <i class="fas fa-eye" id="encKeyEyeIcon"></i>
                    </button>
                    <button class="btn btn-outline-warning" type="button"
                            onclick="copyEncKey()" title="Скопировать ключ">
                        <i class="fas fa-copy me-1"></i> Копировать
                    </button>
                </div>
                <p class="text-muted small mt-1 mb-0">
                    Ключ хранится в <code>config.php</code>. Сохраните резервную копию
                    всего этого файла в надёжном месте.
                </p>
            </div>
        </div>
    </div>

    <!-- ── Восстановление из дампа ── -->
    <div class="col-md-6">
        <div class="card border-danger h-100">
            <div class="card-header fw-semibold text-danger">
                <i class="fas fa-file-arrow-up me-2"></i> Восстановление из дампа
            </div>
            <div class="card-body d-flex flex-column">
                <p class="text-muted small mb-2">
                    Импортирует SQL-дамп в базу данных.
                    <strong>Существующие таблицы будут удалены и перезаписаны!</strong>
                </p>
                <div class="alert alert-danger py-2 small mb-3">
                    <i class="fas fa-triangle-exclamation me-1"></i>
                    После восстановления убедитесь, что ключ шифрования
                    в <code>config.php</code> соответствует тому, который использовался
                    при создании дампа.
                </div>
                <form method="POST" action="/local_secrets/api/backup.php"
                      enctype="multipart/form-data"
                      onsubmit="return confirmSqlImport()"
                      class="mt-auto">
                    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                    <input type="hidden" name="action"     value="import_sql">
                    <div class="mb-2">
                        <label class="form-label small fw-semibold">SQL-файл резервной копии</label>
                        <input type="file" name="sql_file"
                               class="form-control form-control-sm"
                               accept=".sql" required>
                    </div>
                    <div class="form-check mb-3">
                        <input type="checkbox" class="form-check-input"
                               id="confirmSqlCheck" required>
                        <label class="form-check-label small text-warning"
                               for="confirmSqlCheck">
                            Я понимаю, что текущие данные будут перезаписаны
                        </label>
                    </div>
                    <button type="submit" class="btn btn-danger btn-sm">
                        <i class="fas fa-upload me-1"></i> Восстановить базу данных
                    </button>
                </form>
            </div>
        </div>
    </div>

    <!-- ── Импорт настроек ── -->
    <div class="col-md-6">
        <div class="card h-100">
            <div class="card-header fw-semibold">
                <i class="fas fa-file-import me-2 text-primary"></i> Импорт настроек
            </div>
            <div class="card-body d-flex flex-column">
                <p class="text-muted small mb-2">
                    Восстанавливает настройки приложения из ранее сохранённого JSON-файла.
                    Ключ шифрования и данные БД не затрагиваются.
                </p>
                <div class="alert alert-secondary py-2 small mb-3">
                    <i class="fas fa-circle-info me-1"></i>
                    После импорта перезагрузите страницу, чтобы новые настройки вступили в силу.
                </div>
                <form method="POST" action="/local_secrets/api/backup.php"
                      enctype="multipart/form-data"
                      class="mt-auto">
                    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                    <input type="hidden" name="action"     value="import_settings">
                    <div class="mb-2">
                        <label class="form-label small fw-semibold">JSON-файл настроек</label>
                        <input type="file" name="settings_file"
                               class="form-control form-control-sm"
                               accept=".json" required>
                    </div>
                    <button type="submit" class="btn btn-primary btn-sm">
                        <i class="fas fa-upload me-1"></i> Импортировать настройки
                    </button>
                </form>
            </div>
        </div>
    </div>

</div><!-- /row -->

<script>
function toggleEncKey() {
    const input = document.getElementById('encKeyInput');
    const icon  = document.getElementById('encKeyEyeIcon');
    if (input.type === 'password') {
        input.type    = 'text';
        icon.className = 'fas fa-eye-slash';
    } else {
        input.type    = 'password';
        icon.className = 'fas fa-eye';
    }
}

function copyEncKey() {
    const key = document.getElementById('encKeyInput').value;
    if (navigator.clipboard) {
        navigator.clipboard.writeText(key).then(() => {
            showToast('Ключ шифрования скопирован', 'success');
        });
    } else {
        const tmp = document.createElement('textarea');
        tmp.value = key;
        document.body.appendChild(tmp);
        tmp.select();
        document.execCommand('copy');
        document.body.removeChild(tmp);
        showToast('Ключ шифрования скопирован', 'success');
    }
}

function confirmSqlImport() {
    return confirm(
        'ВНИМАНИЕ!\n\n' +
        'Текущие данные базы данных будут полностью перезаписаны данными из файла.\n\n' +
        'Убедитесь, что у вас есть актуальная резервная копия.\n\n' +
        'Продолжить восстановление?'
    );
}
</script>

<?php
$content = ob_get_clean();
include __DIR__ . '/../templates/layout.php';
