<?php
/**
 * CRUD для категорий
 */
class CategoryService {
    private Database $db;

    public function __construct() {
        $this->db = Database::getInstance();
    }

    /**
     * Все категории с количеством секретов
     */
    public function getAll(): array {
        return $this->db->fetchAll("
            SELECT c.*,
                   (SELECT COUNT(*) FROM secrets s WHERE s.category_id = c.id) AS secret_count
            FROM categories c
            ORDER BY c.sort_order, c.name
        ");
    }

    /**
     * Одна категория по ID
     */
    public function getById(int $id): ?array {
        return $this->db->fetchOne("SELECT * FROM categories WHERE id = ?", [$id]);
    }

    /**
     * Создать категорию
     */
    public function create(string $name, ?string $icon = null, ?string $color = null): int {
        $maxSort = (int)$this->db->fetchColumn("SELECT MAX(sort_order) FROM categories WHERE sort_order < 100");
        $this->db->execute(
            "INSERT INTO categories (name, icon, color, sort_order) VALUES (?, ?, ?, ?)",
            [$name, $icon, $color, $maxSort + 1]
        );
        $id = $this->db->lastInsertId();
        Logger::log('create', 'category', $id, "Создана категория: {$name}");
        return $id;
    }

    /**
     * Обновить категорию
     */
    public function update(int $id, string $name, ?string $icon = null, ?string $color = null): void {
        $this->db->execute(
            "UPDATE categories SET name = ?, icon = ?, color = ? WHERE id = ?",
            [$name, $icon, $color, $id]
        );
        Logger::log('update', 'category', $id, "Обновлена категория: {$name}");
    }

    /**
     * Удалить категорию (секреты переходят в NULL)
     */
    public function delete(int $id): void {
        $cat = $this->getById($id);
        $this->db->execute("DELETE FROM categories WHERE id = ?", [$id]);
        Logger::log('delete', 'category', $id, "Удалена категория: " . ($cat['name'] ?? '?'));
    }
}
