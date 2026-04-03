<?php
/**
 * Умное добавление — вставить неструктурированный текст, LLM распарсит
 */
require_once __DIR__ . '/../bootstrap.php';
Auth::requireAuth();

$categoryService = new CategoryService();
$secretService = new SecretService();

$categories = $categoryService->getAll();
$stats = $secretService->getStats();

$llm = new LlmParser();
$llmAvailable = $llm->isAvailable();

$pageTitle = 'Умное добавление';

// Обработка массового сохранения
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'save_entries' && verify_csrf()) {
    $entries = json_decode($_POST['entries_json'] ?? '[]', true);
    $saved = 0;
    foreach ($entries as $entry) {
        if (empty($entry['service_name'])) continue;

        // Найти category_id по имени
        $catId = null;
        if (!empty($entry['category'])) {
            foreach ($categories as $cat) {
                if (mb_strtolower($cat['name']) === mb_strtolower($entry['category'])) {
                    $catId = $cat['id'];
                    break;
                }
            }
        }

        $data = [
            'service_name' => $entry['service_name'],
            'category_id'  => $catId,
            'description'  => $entry['description'] ?? '',
            'is_favorite'  => 0,
            'fields'       => $entry['fields'] ?? [],
            'tags'         => $entry['tags'] ?? [],
        ];

        try {
            $secretService->create($data);
            $saved++;
        } catch (Throwable $e) {
            Logger::error("Ошибка сохранения entry '{$entry['service_name']}': " . $e->getMessage());
        }
    }

    header("Location: /local_secrets/index.php?saved={$saved}");
    exit;
}

ob_start();
?>

<div class="d-flex align-items-center mb-3">
    <a href="/local_secrets/index.php" class="btn btn-outline-secondary btn-sm me-3">
        <i class="fas fa-arrow-left"></i>
    </a>
    <h4 class="mb-0"><i class="fas fa-robot me-2 text-info"></i> Умное добавление</h4>
</div>

<?php if (!$llmAvailable): ?>
    <div class="alert alert-warning">
        <i class="fas fa-exclamation-triangle me-2"></i>
        LM Studio не доступен (<?= htmlspecialchars(LLM_API_URL) ?>). Запустите LM Studio и загрузите модель.
    </div>
<?php endif; ?>

<!-- Шаг 1: Вставить текст -->
<div id="step1" class="card mb-3">
    <div class="card-header">
        <i class="fas fa-paste me-2"></i> Шаг 1: Вставьте неструктурированный текст
    </div>
    <div class="card-body">
        <textarea id="rawText" class="form-control" rows="12"
                  placeholder="Вставьте сюда текст с логинами, паролями, API-ключами...&#10;&#10;Пример:&#10;OpenAI API Key: sk-proj-abc123...&#10;Login: admin&#10;Password: secret123"></textarea>
        <div class="mt-2 d-flex justify-content-between align-items-center">
            <small class="text-muted">LLM или быстрый парсинг разберут текст на сервисы и поля</small>
            <div class="d-flex gap-2">
                <button id="fallbackBtn" class="btn btn-outline-warning" title="Мгновенный парсинг по паттернам, без LLM">
                    <i class="fas fa-bolt me-1"></i> Быстрый парсинг
                </button>
                <button id="analyzeBtn" class="btn btn-info" <?= !$llmAvailable ? 'disabled' : '' ?>
                        title="Парсинг через LLM (медленнее, но точнее)">
                    <i class="fas fa-wand-magic-sparkles me-1"></i> LLM-анализ
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Шаг 2: Результат — скрыт до анализа -->
<div id="step2" style="display:none;">
    <div class="card mb-3">
        <div class="card-header d-flex justify-content-between align-items-center">
            <span><i class="fas fa-check-double me-2"></i> Шаг 2: Проверьте результат</span>
            <span class="badge bg-info" id="entriesCount"></span>
        </div>
        <div class="card-body" id="entriesPreview">
            <!-- Заполняется через JS -->
        </div>
    </div>

    <form method="POST">
        <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
        <input type="hidden" name="action" value="save_entries">
        <input type="hidden" name="entries_json" id="entriesJson">
        <div class="d-flex gap-2">
            <button type="submit" class="btn btn-success" id="saveAllBtn">
                <i class="fas fa-save me-1"></i> Сохранить все
            </button>
            <button type="button" class="btn btn-secondary" id="backBtn">
                <i class="fas fa-arrow-left me-1"></i> Назад к тексту
            </button>
        </div>
    </form>
</div>

<!-- Спиннер -->
<div id="spinner" style="display:none;" class="text-center py-5">
    <div class="spinner-border text-info" role="status" style="width:3rem;height:3rem;">
        <span class="visually-hidden">Анализ...</span>
    </div>
    <p class="text-muted mt-3">LLM анализирует текст...</p>
</div>

<script>
const categoriesList = <?= json_encode(array_column($categories, 'name'), JSON_UNESCAPED_UNICODE) ?>;
let parsedEntries = [];

document.addEventListener('DOMContentLoaded', function() {

// Общая функция парсинга
function runParse(url, spinnerText) {
    const text = $('#rawText').val().trim();
    if (!text) return;

    $('#step1').hide();
    $('#step2').hide();
    $('#spinner').show();
    $('#spinner p').text(spinnerText);

    $.ajax({
        url: url,
        method: 'POST',
        contentType: 'application/json',
        headers: { 'X-CSRF-TOKEN': CSRF_TOKEN },
        data: JSON.stringify({ text: text }),
        timeout: 200000, // 200 сек для LLM
        success: function(resp) {
            $('#spinner').hide();
            if (resp.success && resp.data.entries && resp.data.entries.length > 0) {
                parsedEntries = resp.data.entries;
                renderEntries(parsedEntries);
                $('#step2').show();
            } else {
                // LLM не справился — автоматически пробуем fallback
                if (url.includes('llm_parse')) {
                    showToast('LLM не справился, пробую быстрый парсинг...', 'warning');
                    runParse('/local_secrets/api/fallback_parse.php', 'Быстрый парсинг...');
                } else {
                    showToast(resp.error || 'Не удалось распознать данные', 'warning');
                    $('#step1').show();
                }
            }
        },
        error: function(xhr) {
            $('#spinner').hide();
            // LLM ошибка — автоматически fallback
            if (url.includes('llm_parse')) {
                showToast('LLM ошибка, переключаюсь на быстрый парсинг...', 'warning');
                runParse('/local_secrets/api/fallback_parse.php', 'Быстрый парсинг...');
            } else {
                $('#step1').show();
                showToast('Ошибка: ' + (xhr.responseJSON?.error || xhr.statusText), 'danger');
            }
        }
    });
}

// LLM-анализ
$('#analyzeBtn').on('click', function() {
    runParse('/local_secrets/api/llm_parse.php', 'LLM анализирует текст...');
});

// Быстрый парсинг (без LLM)
$('#fallbackBtn').on('click', function() {
    runParse('/local_secrets/api/fallback_parse.php', 'Быстрый парсинг...');
});

$('#backBtn').on('click', function() {
    $('#step2').hide();
    $('#step1').show();
});

}); // end DOMContentLoaded

function renderEntries(entries) {
    $('#entriesCount').text(entries.length + ' сервис(ов)');
    let html = '';
    entries.forEach((entry, idx) => {
        html += `<div class="border rounded p-3 mb-3">
            <div class="d-flex justify-content-between align-items-start mb-2">
                <h5 class="mb-0">${escapeHtml(entry.service_name)}</h5>
                <span class="badge bg-secondary">${escapeHtml(entry.category || 'Другое')}</span>
            </div>`;
        if (entry.description) {
            html += `<p class="text-muted small mb-2">${escapeHtml(entry.description)}</p>`;
        }
        html += '<table class="table table-sm mb-1">';
        (entry.fields || []).forEach(f => {
            const masked = f.type === 'password' || f.type === 'token'
                ? '********' : escapeHtml(f.value);
            html += `<tr>
                <td class="fw-semibold" style="width:200px">${escapeHtml(f.name)}</td>
                <td class="text-break">${masked}</td>
                <td style="width:80px"><span class="badge bg-dark">${f.type}</span></td>
            </tr>`;
        });
        html += '</table>';
        if (entry.tags && entry.tags.length) {
            html += '<div>';
            entry.tags.forEach(t => {
                html += `<span class="badge border border-secondary text-secondary me-1">${escapeHtml(t)}</span>`;
            });
            html += '</div>';
        }
        html += '</div>';
    });
    $('#entriesPreview').html(html);
    $('#entriesJson').val(JSON.stringify(entries));
}

function escapeHtml(text) {
    if (!text) return '';
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}
</script>

<?php
$content = ob_get_clean();
include __DIR__ . '/../templates/layout.php';
