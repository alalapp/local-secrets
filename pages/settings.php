<?php
/**
 * Настройки — смена PIN, информация
 */
require_once __DIR__ . '/../bootstrap.php';
Auth::requireAuth();

/**
 * Обновить define'ы прямо в config.php
 * @param array<string, mixed> $defines ['KEY' => value, ...]
 */
function _saveConfig(array $defines): void {
    $configPath = APP_ROOT . '/config.php';
    $content = file_get_contents($configPath);

    foreach ($defines as $key => $value) {
        $exported = is_string($value) ? var_export($value, true) : $value;
        $newLine = "define('{$key}', {$exported});";

        // Заменить существующий define (с if-обёрткой или без)
        $pattern = "/(?:if\s*\(!defined\('{$key}'\)\)\s*)?define\('{$key}',.+?\);/";
        if (preg_match($pattern, $content)) {
            $content = preg_replace($pattern, $newLine, $content);
        }
    }

    file_put_contents($configPath, $content);
}

$categoryService = new CategoryService();
$secretService = new SecretService();

$categories = $categoryService->getAll();
$stats = $secretService->getStats();

$error = '';
$success = '';

// Смена PIN
if ($_SERVER['REQUEST_METHOD'] === 'POST' && verify_csrf()) {
    $action = $_POST['action'] ?? '';

    if ($action === 'change_pin') {
        $currentPin = $_POST['current_pin'] ?? '';
        $newPin = $_POST['new_pin'] ?? '';
        $confirmPin = $_POST['confirm_pin'] ?? '';

        $verify = Auth::verifyPin($currentPin);
        if (!$verify['success']) {
            $error = 'Неверный текущий PIN';
        } elseif ($newPin !== $confirmPin) {
            $error = 'Новые PIN-коды не совпадают';
        } else {
            try {
                Auth::setPin($newPin);
                $success = 'PIN успешно изменён';
            } catch (InvalidArgumentException $e) {
                $error = $e->getMessage();
            }
        }
        $_SESSION['authenticated'] = true;
        $_SESSION['last_activity'] = time();

    } elseif ($action === 'save_general') {
        $perPage = max(5, min(100, (int)($_POST['per_page'] ?? 10)));
        _saveConfig(['PER_PAGE' => $perPage]);
        $success = 'Настройки сохранены. Перезагрузите страницу для применения.';
        Logger::log('update', 'settings', 0, 'Обновлены общие настройки');

    } elseif ($action === 'save_llm') {
        $llmUrl = trim($_POST['llm_url'] ?? '');
        $llmModel = trim($_POST['llm_model'] ?? '');
        $llmTimeout = (int)($_POST['llm_timeout'] ?? 180);
        $llmMaxTokens = (int)($_POST['llm_max_tokens'] ?? 4000);
        $llmTemp = (float)($_POST['llm_temperature'] ?? 0.1);

        if (empty($llmUrl)) {
            $error = 'URL не может быть пустым';
        } else {
            _saveConfig([
                'LLM_API_URL'     => $llmUrl,
                'LLM_MODEL'       => $llmModel,
                'LLM_TIMEOUT'     => $llmTimeout,
                'LLM_MAX_TOKENS'  => $llmMaxTokens,
                'LLM_TEMPERATURE' => $llmTemp,
            ]);
            $success = 'Настройки LLM сохранены. Перезагрузите страницу для применения.';
            Logger::log('update', 'settings', 0, 'Обновлены настройки LLM');
        }
    }
}

$llm = new LlmParser();
$llmInfo = $llm->getServerInfo();

$pageTitle = 'Настройки';
ob_start();
?>

<h4 class="mb-3"><i class="fas fa-cog me-2"></i> Настройки</h4>

<?php if ($error): ?>
    <div class="alert alert-danger py-2"><?= htmlspecialchars($error) ?></div>
<?php endif; ?>
<?php if ($success): ?>
    <div class="alert alert-success py-2"><?= htmlspecialchars($success) ?></div>
<?php endif; ?>

<div class="row g-3">
    <!-- Смена PIN -->
    <div class="col-md-6">
        <div class="card">
            <div class="card-header"><i class="fas fa-lock me-2"></i> Смена PIN-кода</div>
            <div class="card-body">
                <form method="POST">
                    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                    <input type="hidden" name="action" value="change_pin">
                    <div class="mb-3">
                        <label class="form-label">Текущий PIN</label>
                        <input type="password" name="current_pin" class="form-control"
                               inputmode="numeric" pattern="[0-9]*" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Новый PIN (<?= PIN_MIN_LENGTH ?>-<?= PIN_MAX_LENGTH ?> цифр)</label>
                        <input type="password" name="new_pin" class="form-control"
                               inputmode="numeric" pattern="[0-9]*"
                               minlength="<?= PIN_MIN_LENGTH ?>" maxlength="<?= PIN_MAX_LENGTH ?>" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Подтвердите новый PIN</label>
                        <input type="password" name="confirm_pin" class="form-control"
                               inputmode="numeric" pattern="[0-9]*"
                               minlength="<?= PIN_MIN_LENGTH ?>" maxlength="<?= PIN_MAX_LENGTH ?>" required>
                    </div>
                    <button type="submit" class="btn btn-primary btn-sm">
                        <i class="fas fa-save me-1"></i> Сменить PIN
                    </button>
                </form>
            </div>
        </div>
    </div>

    <!-- Статистика -->
    <div class="col-md-6">
        <div class="card mb-3">
            <div class="card-header"><i class="fas fa-chart-bar me-2"></i> Статистика</div>
            <div class="card-body">
                <table class="table table-sm mb-0">
                    <tr><td>Всего секретов</td><td class="fw-bold"><?= $stats['total'] ?></td></tr>
                    <tr><td>В избранном</td><td class="fw-bold"><?= $stats['favorites'] ?></td></tr>
                    <tr><td>Полей (зашифровано)</td><td class="fw-bold"><?= $stats['fields'] ?></td></tr>
                    <tr><td>Категорий использовано</td><td class="fw-bold"><?= $stats['categories_used'] ?></td></tr>
                </table>
            </div>
        </div>

        <div class="card">
            <div class="card-header"><i class="fas fa-sliders me-2"></i> Отображение</div>
            <div class="card-body">
                <form method="POST">
                    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                    <input type="hidden" name="action" value="save_general">
                    <div class="row align-items-end g-2">
                        <div class="col">
                            <label class="form-label">Записей на странице</label>
                            <input type="number" name="per_page" class="form-control form-control-sm"
                                   value="<?= PER_PAGE ?>" min="5" max="100" step="5">
                        </div>
                        <div class="col-auto">
                            <button type="submit" class="btn btn-primary btn-sm">
                                <i class="fas fa-save me-1"></i> Сохранить
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>

    </div>
</div>

<!-- LM Studio -->
<div class="card mt-3">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span><i class="fas fa-robot me-2"></i> LM Studio</span>
        <?php if ($llmInfo['available']): ?>
            <span class="badge bg-success"><i class="fas fa-check me-1"></i> Доступен</span>
        <?php else: ?>
            <span class="badge bg-danger"><i class="fas fa-times me-1"></i> Недоступен</span>
        <?php endif; ?>
    </div>
    <div class="card-body">
        <?php if ($llmInfo['available'] && !empty($llmInfo['models'])): ?>
            <div class="mb-3">
                <span class="text-muted small">Загруженные модели:</span>
                <?php foreach ($llmInfo['models'] as $model): ?>
                    <span class="badge bg-info text-dark ms-1" role="button"
                          onclick="document.querySelector('input[name=llm_model]').value='<?= htmlspecialchars($model, ENT_QUOTES) ?>'"
                          title="Подставить в поле модели"><?= htmlspecialchars($model) ?></span>
                <?php endforeach; ?>
            </div>
        <?php elseif (!$llmInfo['available'] && !empty($llmInfo['error'])): ?>
            <div class="alert alert-warning py-2 mb-3">
                <i class="fas fa-exclamation-triangle me-1"></i>
                <?= htmlspecialchars($llmInfo['error']) ?>
            </div>
        <?php endif; ?>

        <form method="POST">
            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
            <input type="hidden" name="action" value="save_llm">
            <div class="row g-3">
                <div class="col-md-8">
                    <label class="form-label">API Endpoint (URL)</label>
                    <input type="url" name="llm_url" class="form-control form-control-sm"
                           value="<?= htmlspecialchars(LLM_API_URL) ?>"
                           placeholder="http://192.168.30.10:1234/v1/chat/completions">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Модель</label>
                    <input type="text" name="llm_model" class="form-control form-control-sm"
                           value="<?= htmlspecialchars(LLM_MODEL) ?>"
                           placeholder="qwen/qwen3-vl-4b">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Таймаут (сек)</label>
                    <input type="number" name="llm_timeout" class="form-control form-control-sm"
                           value="<?= LLM_TIMEOUT ?>" min="10" max="600">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Max tokens</label>
                    <input type="number" name="llm_max_tokens" class="form-control form-control-sm"
                           value="<?= LLM_MAX_TOKENS ?>" min="500" max="16000">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Temperature</label>
                    <input type="number" name="llm_temperature" class="form-control form-control-sm"
                           value="<?= LLM_TEMPERATURE ?>" min="0" max="2" step="0.05">
                </div>
            </div>
            <button type="submit" class="btn btn-primary btn-sm mt-3">
                <i class="fas fa-save me-1"></i> Сохранить настройки LLM
            </button>
        </form>
    </div>
</div>

<div class="card mt-3">
    <div class="card-header"><i class="fas fa-info-circle me-2"></i> О приложении</div>
    <div class="card-body">
        <p class="mb-1"><strong><?= APP_NAME ?></strong> v<?= APP_VERSION ?></p>
        <p class="text-muted small mb-0">
            Локальное хранилище учётных данных с AES-256 шифрованием.
            Данные хранятся только на этом компьютере. Таймаут сессии: <?= SESSION_TIMEOUT / 60 ?> мин.
        </p>
    </div>
</div>

<?php
$content = ob_get_clean();
include __DIR__ . '/../templates/layout.php';
