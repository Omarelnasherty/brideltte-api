<?php
require_once __DIR__ . '/../config/database.php';

class Favorite {
    private PDO $db;

    public function __construct() {
        $this->db = Database::getInstance();
    }

    public function isFavorite(int $userId, int $vendorId): bool {
        $stmt = $this->db->prepare("SELECT COUNT(*) FROM favorites WHERE user_id = ? AND vendor_id = ?");
        $stmt->execute([$userId, $vendorId]);
        return (int)$stmt->fetchColumn() > 0;
    }

    public function toggle(int $userId, int $vendorId): array {
        if ($this->isFavorite($userId, $vendorId)) {
            $stmt = $this->db->prepare("DELETE FROM favorites WHERE user_id = ? AND vendor_id = ?");
            $stmt->execute([$userId, $vendorId]);
            return ['added' => false];
        } else {
            $stmt = $this->db->prepare("INSERT INTO favorites (user_id, vendor_id) VALUES (?, ?)");
            $stmt->execute([$userId, $vendorId]);
            return ['added' => true];
        }
    }

    public function getUserFavorites(int $userId): array {
        $stmt = $this->db->prepare(
            "SELECT f.id, f.created_at, v.id as vendor_id, v.business_name, v.category, v.description,
                    v.location, v.rating, v.review_count, v.images, v.price_range, v.verified
             FROM favorites f
             JOIN vendors v ON f.vendor_id = v.id
             WHERE f.user_id = ? AND v.is_active = 1
             ORDER BY f.created_at DESC"
        );
        $stmt->execute([$userId]);
        $favorites = $stmt->fetchAll();
        foreach ($favorites as &$f) {
            if (isset($f['images']) && is_string($f['images'])) {
                $f['images'] = json_decode($f['images'], true) ?: [];
            }
        }
        return $favorites;
    }
}
