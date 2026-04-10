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
}
