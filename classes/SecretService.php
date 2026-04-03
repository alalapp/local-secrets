<?php
/**
 * CRUD и поиск секретов
 */
class SecretService {
    private Database $db;

    public function __construct() {
        $this->db = Database::getInstance();
    }

    /**
     * Получить секреты с пагинацией
     * @return array{items: array, total: int, page: int, perPage: int, totalPages: int}
     */
    public function getAll(?int $categoryId = null, ?string $search = null, bool $favoritesOnly = false, int $page = 1, int $perPage = 0): array {
        if ($perPage <= 0) $perPage = PER_PAGE;
        $where = [];
        $params = [];

        if ($categoryId) {
            $where[] = "s.category_id = ?";
            $params[] = $categoryId;
        }
        if ($favoritesOnly) {
            $where[] = "s.is_favorite = 1";
        }
        if ($search) {
            $where[] = "(MATCH(s.service_name) AGAINST(? IN BOOLEAN MODE) OR s.service_name LIKE ? OR s.id IN (SELECT st2.secret_id FROM secret_tags st2 JOIN tags t2 ON t2.id = st2.tag_id WHERE t2.name LIKE ?))";
            $params[] = $search . '*';
            $params[] = '%' . $search . '%';
            $params[] = '%' . $search . '%';
        }

        $whereClause = $where ? 'WHERE ' . implode(' AND ', $where) : '';

        // Общее количество
        $countSql = "SELECT COUNT(*) FROM secrets s {$whereClause}";
        $total = (int)$this->db->fetchColumn($countSql, $params);

        $page = max(1, $page);
        $totalPages = max(1, (int)ceil($total / $perPage));
        $offset = ($page - 1) * $perPage;

        $sql = "
            SELECT s.*, c.name AS category_name, c.icon AS category_icon, c.color AS category_color,
                   (SELECT COUNT(*) FROM secret_fields sf WHERE sf.secret_id = s.id) AS field_count,
                   (SELECT GROUP_CONCAT(t.name SEPARATOR ', ')
                    FROM secret_tags st JOIN tags t ON t.id = st.tag_id
                    WHERE st.secret_id = s.id) AS tags_list
            FROM secrets s
            LEFT JOIN categories c ON c.id = s.category_id
            {$whereClause}
            ORDER BY s.is_favorite DESC, s.updated_at DESC
            LIMIT {$perPage} OFFSET {$offset}
        ";

        return [
            'items'      => $this->db->fetchAll($sql, $params),
            'total'      => $total,
            'page'       => $page,
            'perPage'    => $perPage,
            'totalPages' => $totalPages,
        ];
    }

    /**
     * Получить секрет по ID со всеми полями (расшифрованными)
     */
    public function getById(int $id): ?array {
        $secret = $this->db->fetchOne(
            "SELECT s.*, c.name AS category_name, c.icon AS category_icon, c.color AS category_color
             FROM secrets s LEFT JOIN categories c ON c.id = s.category_id
             WHERE s.id = ?",
            [$id]
        );

        if (!$secret) {
            return null;
        }

        // Расшифровать описание
        if ($secret['description']) {
            $secret['description'] = Encryption::decrypt($secret['description']);
        }

        // Получить и расшифровать поля
        $fields = $this->db->fetchAll(
            "SELECT * FROM secret_fields WHERE secret_id = ? ORDER BY sort_order, id",
            [$id]
        );
        foreach ($fields as &$field) {
            $field['field_value'] = Encryption::decrypt($field['field_value']);
        }
        $secret['fields'] = $fields;

        // Получить теги
        $secret['tags'] = $this->db->fetchAll(
            "SELECT t.* FROM tags t JOIN secret_tags st ON st.tag_id = t.id WHERE st.secret_id = ?",
            [$id]
        );

        return $secret;
    }

    /**
     * Создать секрет
     * @param array $data {service_name, category_id, description, is_favorite, fields: [{name, value, type}], tags: [string]}
     */
    public function create(array $data): int {
        $this->db->beginTransaction();
        try {
            // Зашифровать описание
            $description = !empty($data['description']) ? Encryption::encrypt($data['description']) : null;

            $this->db->execute(
                "INSERT INTO secrets (service_name, category_id, description, is_favorite) VALUES (?, ?, ?, ?)",
                [
                    $data['service_name'],
                    $data['category_id'] ?: null,
                    $description,
                    $data['is_favorite'] ?? 0
                ]
            );
            $secretId = $this->db->lastInsertId();

            // Поля
            $this->saveFields($secretId, $data['fields'] ?? []);

            // Теги
            $this->saveTags($secretId, $data['tags'] ?? []);

            $this->db->commit();
            Logger::log('create', 'secret', $secretId, "Создан секрет: {$data['service_name']}");
            return $secretId;
        } catch (Throwable $e) {
            $this->db->rollback();
            throw $e;
        }
    }

    /**
     * Обновить секрет
     */
    public function update(int $id, array $data): void {
        $this->db->beginTransaction();
        try {
            $description = !empty($data['description']) ? Encryption::encrypt($data['description']) : null;

            $this->db->execute(
                "UPDATE secrets SET service_name = ?, category_id = ?, description = ?, is_favorite = ? WHERE id = ?",
                [
                    $data['service_name'],
                    $data['category_id'] ?: null,
                    $description,
                    $data['is_favorite'] ?? 0,
                    $id
                ]
            );

            // Пересоздать поля
            $this->db->execute("DELETE FROM secret_fields WHERE secret_id = ?", [$id]);
            $this->saveFields($id, $data['fields'] ?? []);

            // Пересоздать теги
            $this->db->execute("DELETE FROM secret_tags WHERE secret_id = ?", [$id]);
            $this->saveTags($id, $data['tags'] ?? []);

            $this->db->commit();
            Logger::log('update', 'secret', $id, "Обновлён секрет: {$data['service_name']}");
        } catch (Throwable $e) {
            $this->db->rollback();
            throw $e;
        }
    }

    /**
     * Удалить секрет
     */
    public function delete(int $id): void {
        $secret = $this->db->fetchOne("SELECT service_name FROM secrets WHERE id = ?", [$id]);
        $this->db->execute("DELETE FROM secrets WHERE id = ?", [$id]);
        Logger::log('delete', 'secret', $id, "Удалён секрет: " . ($secret['service_name'] ?? '?'));
    }

    /**
     * Переключить избранное
     */
    public function toggleFavorite(int $id): bool {
        $current = $this->db->fetchColumn("SELECT is_favorite FROM secrets WHERE id = ?", [$id]);
        $new = $current ? 0 : 1;
        $this->db->execute("UPDATE secrets SET is_favorite = ? WHERE id = ?", [$new, $id]);
        return (bool)$new;
    }

    /**
     * Получить расшифрованное значение одного поля (для clipboard)
     */
    public function decryptFieldValue(int $fieldId): ?string {
        $row = $this->db->fetchOne("SELECT field_value, secret_id FROM secret_fields WHERE id = ?", [$fieldId]);
        if (!$row) {
            return null;
        }
        Logger::log('copy', 'secret_field', $fieldId, "Скопировано поле #{$fieldId}");
        return Encryption::decrypt($row['field_value']);
    }

    /**
     * Полнотекстовый поиск
     */
    public function search(string $query): array {
        $sql = "
            SELECT s.*, c.name AS category_name, c.icon AS category_icon, c.color AS category_color,
                   (SELECT COUNT(*) FROM secret_fields sf WHERE sf.secret_id = s.id) AS field_count,
                   (SELECT GROUP_CONCAT(t.name SEPARATOR ', ')
                    FROM secret_tags st JOIN tags t ON t.id = st.tag_id
                    WHERE st.secret_id = s.id) AS tags_list
            FROM secrets s
            LEFT JOIN categories c ON c.id = s.category_id
            LEFT JOIN secret_fields sf2 ON sf2.secret_id = s.id
            LEFT JOIN secret_tags st2 ON st2.secret_id = s.id
            LEFT JOIN tags t2 ON t2.id = st2.tag_id
            WHERE s.service_name LIKE ?
               OR MATCH(s.service_name) AGAINST(? IN BOOLEAN MODE)
               OR sf2.field_name LIKE ?
               OR t2.name LIKE ?
            GROUP BY s.id
            ORDER BY s.is_favorite DESC, s.updated_at DESC
            LIMIT 50
        ";
        $like = '%' . $query . '%';
        Logger::log('search', null, null, "Поиск: {$query}");
        return $this->db->fetchAll($sql, [$like, $query . '*', $like, $like]);
    }

    /**
     * Статистика
     */
    public function getStats(): array {
        return [
            'total' => (int)$this->db->fetchColumn("SELECT COUNT(*) FROM secrets"),
            'favorites' => (int)$this->db->fetchColumn("SELECT COUNT(*) FROM secrets WHERE is_favorite = 1"),
            'fields' => (int)$this->db->fetchColumn("SELECT COUNT(*) FROM secret_fields"),
            'categories_used' => (int)$this->db->fetchColumn("SELECT COUNT(DISTINCT category_id) FROM secrets WHERE category_id IS NOT NULL"),
        ];
    }

    // --- Приватные методы ---

    private function saveFields(int $secretId, array $fields): void {
        foreach ($fields as $i => $field) {
            if (empty($field['name']) || !isset($field['value'])) {
                continue;
            }
            $this->db->execute(
                "INSERT INTO secret_fields (secret_id, field_name, field_value, field_type, sort_order) VALUES (?, ?, ?, ?, ?)",
                [
                    $secretId,
                    $field['name'],
                    Encryption::encrypt($field['value']),
                    $field['type'] ?? 'text',
                    $i
                ]
            );
        }
    }

    private function saveTags(int $secretId, array $tags): void {
        foreach ($tags as $tagName) {
            $tagName = trim($tagName);
            if ($tagName === '') continue;

            // Найти или создать тег
            $tagId = $this->db->fetchColumn("SELECT id FROM tags WHERE name = ?", [$tagName]);
            if (!$tagId) {
                $this->db->execute("INSERT INTO tags (name) VALUES (?)", [$tagName]);
                $tagId = $this->db->lastInsertId();
            }

            $this->db->execute(
                "INSERT IGNORE INTO secret_tags (secret_id, tag_id) VALUES (?, ?)",
                [$secretId, $tagId]
            );
        }
    }
}
