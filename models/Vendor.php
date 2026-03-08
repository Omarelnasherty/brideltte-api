<?php
require_once __DIR__ . '/../config/database.php';

class Vendor {
    private PDO $db;

    public function __construct() {
        $this->db = Database::getInstance();
    }

    public function findById(int $id): ?array {
        $stmt = $this->db->prepare(
            "SELECT v.*, u.name as owner_name, u.email as owner_email 
             FROM vendors v JOIN users u ON v.user_id = u.id WHERE v.id = ?"
        );
        $stmt->execute([$id]);
        $vendor = $stmt->fetch() ?: null;
        if ($vendor && is_string($vendor['images'])) {
            $vendor['images'] = json_decode($vendor['images'], true) ?: [];
        }
        return $vendor;
    }

    public function findByUserId(int $userId): ?array {
        $stmt = $this->db->prepare("SELECT * FROM vendors WHERE user_id = ?");
        $stmt->execute([$userId]);
        $vendor = $stmt->fetch() ?: null;
        if ($vendor && is_string($vendor['images'])) {
            $vendor['images'] = json_decode($vendor['images'], true) ?: [];
        }
        return $vendor;
    }

    public function create(array $data): int {
        $stmt = $this->db->prepare(
            "INSERT INTO vendors (user_id, business_name, category, description, location, phone, email, website, price_range, images)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
        );
        $stmt->execute([
            $data['user_id'],
            $data['business_name'],
            $data['category'],
            $data['description'],
            $data['location'],
            $data['phone'],
            $data['email'],
            $data['website'] ?? null,
            $data['price_range'],
            json_encode($data['images'] ?? []),
        ]);
        return (int) $this->db->lastInsertId();
    }

    public function update(int $id, array $data): bool {
        $fields = [];
        $values = [];
        $allowed = ['business_name', 'category', 'description', 'location', 'phone', 'email', 'website', 'price_range'];
        foreach ($allowed as $field) {
            if (array_key_exists($field, $data)) {
                $fields[] = "$field = ?";
                $values[] = $data[$field];
            }
        }
        if (array_key_exists('images', $data)) {
            $fields[] = "images = ?";
            $values[] = json_encode($data['images']);
        }
        if (empty($fields)) return false;
        $values[] = $id;
        $stmt = $this->db->prepare("UPDATE vendors SET " . implode(', ', $fields) . " WHERE id = ?");
        return $stmt->execute($values);
    }

    public function updateRating(int $vendorId): void {
        $stmt = $this->db->prepare(
            "UPDATE vendors SET 
                rating = COALESCE((SELECT AVG(rating) FROM reviews WHERE vendor_id = ?), 0),
                review_count = (SELECT COUNT(*) FROM reviews WHERE vendor_id = ?)
             WHERE id = ?"
        );
        $stmt->execute([$vendorId, $vendorId, $vendorId]);
    }

    public function setVerified(int $id, bool $verified): bool {
        $stmt = $this->db->prepare("UPDATE vendors SET verified = ? WHERE id = ?");
        return $stmt->execute([$verified ? 1 : 0, $id]);
    }

    public function list(int $limit, int $offset, ?string $category = null, ?string $search = null, bool $onlyVerified = true): array {
        $where = "WHERE v.is_active = 1";
        $params = [];
        
        if ($onlyVerified) {
            $where .= " AND v.verified = 1";
        }
        if ($category) {
            $where .= " AND v.category = ?";
            $params[] = $category;
        }
        if ($search) {
            $where .= " AND (v.business_name LIKE ? OR v.description LIKE ? OR v.location LIKE ?)";
            $searchTerm = "%$search%";
            $params[] = $searchTerm;
            $params[] = $searchTerm;
            $params[] = $searchTerm;
        }

        $countStmt = $this->db->prepare("SELECT COUNT(*) FROM vendors v $where");
        $countStmt->execute($params);
        $total = (int) $countStmt->fetchColumn();

        $params[] = $limit;
        $params[] = $offset;
        $stmt = $this->db->prepare(
            "SELECT v.* FROM vendors v $where ORDER BY v.rating DESC, v.review_count DESC LIMIT ? OFFSET ?"
        );
        $stmt->execute($params);
        $vendors = $stmt->fetchAll();

        foreach ($vendors as &$v) {
            if (is_string($v['images'])) {
                $v['images'] = json_decode($v['images'], true) ?: [];
            }
        }

        return ['data' => $vendors, 'total' => $total];
    }

    public function listAll(int $limit, int $offset, ?string $category = null, ?bool $verified = null): array {
        $where = "WHERE 1=1";
        $params = [];
        if ($category) {
            $where .= " AND v.category = ?";
            $params[] = $category;
        }
        if ($verified !== null) {
            $where .= " AND v.verified = ?";
            $params[] = $verified ? 1 : 0;
        }

        $countStmt = $this->db->prepare("SELECT COUNT(*) FROM vendors v $where");
        $countStmt->execute($params);
        $total = (int) $countStmt->fetchColumn();

        $params[] = $limit;
        $params[] = $offset;
        $stmt = $this->db->prepare(
            "SELECT v.*, u.name as owner_name, u.email as owner_email 
             FROM vendors v JOIN users u ON v.user_id = u.id $where ORDER BY v.created_at DESC LIMIT ? OFFSET ?"
        );
        $stmt->execute($params);
        $vendors = $stmt->fetchAll();

        foreach ($vendors as &$v) {
            if (is_string($v['images'])) {
                $v['images'] = json_decode($v['images'], true) ?: [];
            }
        }

        return ['data' => $vendors, 'total' => $total];
    }
}
