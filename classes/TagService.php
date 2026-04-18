<?php
/**
 * CRUD для тегов
 */
class TagService {
    private Database $db;

    public function __construct() {
        $this->db = Database::getInstance();
    }

    /**
     * Все теги с количеством секретов
     */
    public function getAll(): array {
        return $this->db->fetchAll("
            SELECT t.*,
                   (SELECT COUNT(*) FROM secret_tags st WHERE st.tag_id = t.id) AS secret_count
            FROM tags t
            ORDER BY t.name
        ");
    }

    /**
     * Популярные теги (для автодополнения)
     */
    public function getPopular(int $limit = 20): array {
        return $this->db->fetchAll("
            SELECT t.name, COUNT(st.secret_id) AS cnt
            FROM tags t
            JOIN secret_tags st ON st.tag_id = t.id
            GROUP BY t.id
            ORDER BY cnt DESC
            LIMIT ?
        ", [$limit]);
    }

    /**
     * Поиск тегов по подстроке
     */
    public function search(string $query): array {
        return $this->db->fetchAll(
            "SELECT name FROM tags WHERE name LIKE ? ORDER BY name LIMIT 10",
            ['%' . $query . '%']
        );
    }

    /**
     * Получить теги по категории
     */
    public function getByCategory(?int $categoryId = null): array {
        if ($categoryId === null) {
            // Все теги без фильтра по категории
            return $this->db->fetchAll("
                SELECT DISTINCT t.* FROM tags t
                JOIN secret_tags st ON st.tag_id = t.id
                ORDER BY t.name
            ");
        }

        // Теги для конкретной категории
        return $this->db->fetchAll("
            SELECT DISTINCT t.* FROM tags t
            JOIN secret_tags st ON st.tag_id = t.id
            JOIN secrets s ON s.id = st.secret_id
            WHERE s.category_id = ?
            ORDER BY t.name
        ", [$categoryId]);
    }

    /**
     * Удалить неиспользуемые теги
     */
    public function cleanup(): int {
        return $this->db->execute("DELETE FROM tags WHERE id NOT IN (SELECT DISTINCT tag_id FROM secret_tags)");
    }

    public function getById(int $id): ?array {
        $row = $this->db->fetchOne("SELECT * FROM tags WHERE id = ?", [$id]);
        return $row ?: null;
    }

    public function getByName(string $name): ?array {
        $row = $this->db->fetchOne("SELECT * FROM tags WHERE name = ?", [$name]);
        return $row ?: null;
    }

    public function create(string $name): int {
        $name = trim($name);
        if ($name === '') {
            throw new RuntimeException('Название тега не может быть пустым');
        }
        if ($this->getByName($name)) {
            throw new RuntimeException("Тег «{$name}» уже существует");
        }
        $this->db->execute("INSERT INTO tags (name) VALUES (?)", [$name]);
        $id = (int)$this->db->lastInsertId();
        Logger::log('create', 'tag', $id, "Создан тег: {$name}");
        return $id;
    }

    /**
     * Переименовать тег. Если тег с таким именем уже есть — выполняется слияние.
     */
    public function rename(int $id, string $newName): void {
        $newName = trim($newName);
        if ($newName === '') {
            throw new RuntimeException('Название тега не может быть пустым');
        }
        $current = $this->getById($id);
        if (!$current) {
            throw new RuntimeException('Тег не найден');
        }
        if ($current['name'] === $newName) {
            return;
        }
        $existing = $this->getByName($newName);
        if ($existing && (int)$existing['id'] !== $id) {
            $this->merge($id, (int)$existing['id']);
            return;
        }
        $this->db->execute("UPDATE tags SET name = ? WHERE id = ?", [$newName, $id]);
        Logger::log('update', 'tag', $id, "Переименован тег: {$current['name']} → {$newName}");
    }

    public function delete(int $id): void {
        $tag = $this->getById($id);
        if (!$tag) return;
        $this->db->execute("DELETE FROM tags WHERE id = ?", [$id]);
        Logger::log('delete', 'tag', $id, "Удалён тег: {$tag['name']}");
    }

    /**
     * Слить тег $sourceId в $targetId: все связи переносятся, исходный удаляется.
     */
    public function merge(int $sourceId, int $targetId): void {
        if ($sourceId === $targetId) return;
        $src = $this->getById($sourceId);
        $dst = $this->getById($targetId);
        if (!$src || !$dst) {
            throw new RuntimeException('Один из тегов не найден');
        }

        $this->db->beginTransaction();
        try {
            $this->db->execute(
                "UPDATE IGNORE secret_tags SET tag_id = ? WHERE tag_id = ?",
                [$targetId, $sourceId]
            );
            $this->db->execute("DELETE FROM secret_tags WHERE tag_id = ?", [$sourceId]);
            $this->db->execute("DELETE FROM tags WHERE id = ?", [$sourceId]);
            $this->db->commit();
        } catch (Throwable $e) {
            $this->db->rollback();
            throw $e;
        }
        Logger::log('update', 'tag', $targetId, "Слияние тега «{$src['name']}» → «{$dst['name']}»");
    }
}
