<?php
require_once __DIR__ . '/../config/database.php';

class Review {
    private PDO $db;

    public function __construct() {
        $this->db = Database::getInstance();
    }

    public function findById(int $id): ?array {
        $stmt = $this->db->prepare("SELECT * FROM reviews WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->fetch() ?: null;
    }

    public function findByVendor(int $vendorId): array {
        $stmt = $this->db->prepare(
            "SELECT r.*, u.name as user_name, u.email as user_email, u.avatar as user_avatar
             FROM reviews r
             JOIN users u ON r.user_id = u.id
             WHERE r.vendor_id = ?
             ORDER BY r.created_at DESC"
        );
        $stmt->execute([$vendorId]);
        $reviews = $stmt->fetchAll();
        foreach ($reviews as &$r) {
            if (isset($r['images']) && is_string($r['images'])) {
                $r['images'] = json_decode($r['images'], true) ?: [];
            }
        }
        return $reviews;
    }

    public function create(array $data): int {
        $stmt = $this->db->prepare(
            "INSERT INTO reviews (user_id, vendor_id, booking_id, rating, comment, images) VALUES (?, ?, ?, ?, ?, ?)"
        );
        $stmt->execute([
            $data['user_id'],
            $data['vendor_id'],
            $data['booking_id'],
            $data['rating'],
            $data['comment'],
            json_encode($data['images'] ?? []),
        ]);
        return (int) $this->db->lastInsertId();
    }

    public function delete(int $id): bool {
        $stmt = $this->db->prepare("DELETE FROM reviews WHERE id = ?");
        return $stmt->execute([$id]);
    }

    public function existsForBooking(int $bookingId): bool {
        $stmt = $this->db->prepare("SELECT COUNT(*) FROM reviews WHERE booking_id = ?");
        $stmt->execute([$bookingId]);
        return (int)$stmt->fetchColumn() > 0;
    }
}
