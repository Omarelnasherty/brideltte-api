<?php
require_once __DIR__ . '/../config/database.php';

class Service {
    private PDO $db;

    public function __construct() {
        $this->db = Database::getInstance();
    }

    public function findById(int $id): ?array {
        $stmt = $this->db->prepare("SELECT * FROM services WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->fetch() ?: null;
    }

    public function findByVendor(int $vendorId): array {
        $stmt = $this->db->prepare("SELECT * FROM services WHERE vendor_id = ? ORDER BY created_at DESC");
        $stmt->execute([$vendorId]);
        return $stmt->fetchAll();
    }

    public function create(array $data): int {
        $stmt = $this->db->prepare(
            "INSERT INTO services (vendor_id, name, description, price, duration, category) VALUES (?, ?, ?, ?, ?, ?)"
        );
        $stmt->execute([
            $data['vendor_id'],
            $data['name'],
            $data['description'],
            $data['price'],
            $data['duration'],
            $data['category'],
        ]);
        return (int) $this->db->lastInsertId();
    }

    public function update(int $id, array $data): bool {
        $fields = [];
        $values = [];
        $allowed = ['name', 'description', 'price', 'duration', 'category', 'available'];
        foreach ($allowed as $field) {
            if (array_key_exists($field, $data)) {
                $fields[] = "$field = ?";
                $values[] = $data[$field];
            }
        }
        if (empty($fields)) return false;
        $values[] = $id;
        $stmt = $this->db->prepare("UPDATE services SET " . implode(', ', $fields) . " WHERE id = ?");
        return $stmt->execute($values);
    }

    public function delete(int $id): bool {
        $stmt = $this->db->prepare("DELETE FROM services WHERE id = ?");
        return $stmt->execute([$id]);
    }

    public function getVendorIdForService(int $serviceId): ?int {
        $stmt = $this->db->prepare("SELECT vendor_id FROM services WHERE id = ?");
        $stmt->execute([$serviceId]);
        $row = $stmt->fetch();
        return $row ? (int)$row['vendor_id'] : null;
    }
}
