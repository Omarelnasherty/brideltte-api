<?php
require_once __DIR__ . '/../config/database.php';

class Booking {
    private PDO $db;

    public function __construct() {
        $this->db = Database::getInstance();
    }

    public function findById(int $id): ?array {
        $stmt = $this->db->prepare(
            "SELECT b.*, v.business_name as vendor_name, v.category as vendor_category,
                    s.name as service_name, s.price as service_price,
                    u.name as user_name, u.email as user_email
             FROM bookings b
             JOIN vendors v ON b.vendor_id = v.id
             JOIN services s ON b.service_id = s.id
             JOIN users u ON b.user_id = u.id
             WHERE b.id = ?"
        );
        $stmt->execute([$id]);
        return $stmt->fetch() ?: null;
    }

    public function findByUser(int $userId, int $limit, int $offset): array {
        $countStmt = $this->db->prepare("SELECT COUNT(*) FROM bookings WHERE user_id = ?");
        $countStmt->execute([$userId]);
        $total = (int) $countStmt->fetchColumn();

        $stmt = $this->db->prepare(
            "SELECT b.*, v.business_name as vendor_name, v.category as vendor_category, v.images as vendor_images,
                    s.name as service_name, s.price as service_price
             FROM bookings b
             JOIN vendors v ON b.vendor_id = v.id
             JOIN services s ON b.service_id = s.id
             WHERE b.user_id = ?
             ORDER BY b.created_at DESC LIMIT ? OFFSET ?"
        );
        $stmt->execute([$userId, $limit, $offset]);
        $bookings = $stmt->fetchAll();

        foreach ($bookings as &$b) {
            if (isset($b['vendor_images']) && is_string($b['vendor_images'])) {
                $b['vendor_images'] = json_decode($b['vendor_images'], true) ?: [];
            }
        }

        return ['data' => $bookings, 'total' => $total];
    }

    public function findByVendor(int $vendorId, int $limit, int $offset, ?string $status = null): array {
        $where = "WHERE b.vendor_id = ?";
        $params = [$vendorId];
        if ($status) {
            $where .= " AND b.status = ?";
            $params[] = $status;
        }

        $countStmt = $this->db->prepare("SELECT COUNT(*) FROM bookings b $where");
        $countStmt->execute($params);
        $total = (int) $countStmt->fetchColumn();

        $params[] = $limit;
        $params[] = $offset;
        $stmt = $this->db->prepare(
            "SELECT b.*, u.name as user_name, u.email as user_email, u.phone as user_phone,
                    s.name as service_name, s.price as service_price
             FROM bookings b
             JOIN users u ON b.user_id = u.id
             JOIN services s ON b.service_id = s.id
             $where
             ORDER BY b.created_at DESC LIMIT ? OFFSET ?"
        );
        $stmt->execute($params);
        $bookings = $stmt->fetchAll();

        return ['data' => $bookings, 'total' => $total];
    }

    public function create(array $data): int {
        $stmt = $this->db->prepare(
            "INSERT INTO bookings (user_id, vendor_id, service_id, event_date, event_time, location, guest_count, total_price, notes)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)"
        );
        $stmt->execute([
            $data['user_id'],
            $data['vendor_id'],
            $data['service_id'],
            $data['event_date'],
            $data['event_time'],
            $data['location'],
            $data['guest_count'],
            $data['total_price'],
            $data['notes'] ?? null,
        ]);
        return (int) $this->db->lastInsertId();
    }

    public function updateStatus(int $id, string $status, ?string $cancelReason = null): bool {
        if ($cancelReason) {
            $stmt = $this->db->prepare("UPDATE bookings SET status = ?, cancel_reason = ? WHERE id = ?");
            return $stmt->execute([$status, $cancelReason, $id]);
        }
        $stmt = $this->db->prepare("UPDATE bookings SET status = ? WHERE id = ?");
        return $stmt->execute([$status, $id]);
    }

    public function getVendorIdForBooking(int $bookingId): ?int {
        $stmt = $this->db->prepare("SELECT vendor_id FROM bookings WHERE id = ?");
        $stmt->execute([$bookingId]);
        $row = $stmt->fetch();
        return $row ? (int)$row['vendor_id'] : null;
    }

    public function getUserIdForBooking(int $bookingId): ?int {
        $stmt = $this->db->prepare("SELECT user_id FROM bookings WHERE id = ?");
        $stmt->execute([$bookingId]);
        $row = $stmt->fetch();
        return $row ? (int)$row['user_id'] : null;
    }

    public function getStatusForBooking(int $bookingId): ?string {
        $stmt = $this->db->prepare("SELECT status FROM bookings WHERE id = ?");
        $stmt->execute([$bookingId]);
        $row = $stmt->fetch();
        return $row ? $row['status'] : null;
    }

    public function hasReview(int $bookingId): bool {
        $stmt = $this->db->prepare("SELECT COUNT(*) FROM reviews WHERE booking_id = ?");
        $stmt->execute([$bookingId]);
        return (int)$stmt->fetchColumn() > 0;
    }

    public function listAll(int $limit, int $offset, ?string $status = null): array {
        $where = "WHERE 1=1";
        $params = [];
        if ($status) {
            $where .= " AND b.status = ?";
            $params[] = $status;
        }

        $countStmt = $this->db->prepare("SELECT COUNT(*) FROM bookings b $where");
        $countStmt->execute($params);
        $total = (int) $countStmt->fetchColumn();

        $params[] = $limit;
        $params[] = $offset;
        $stmt = $this->db->prepare(
            "SELECT b.*, v.business_name as vendor_name, u.name as user_name, u.email as user_email,
                    s.name as service_name
             FROM bookings b
             JOIN vendors v ON b.vendor_id = v.id
             JOIN users u ON b.user_id = u.id
             JOIN services s ON b.service_id = s.id
             $where ORDER BY b.created_at DESC LIMIT ? OFFSET ?"
        );
        $stmt->execute($params);
        return ['data' => $stmt->fetchAll(), 'total' => $total];
    }

    public function getStats(): array {
        $stmt = $this->db->query("SELECT 
            COUNT(*) as total,
            SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
            SUM(CASE WHEN status = 'confirmed' THEN 1 ELSE 0 END) as confirmed,
            SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed,
            SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END) as cancelled,
            COALESCE(SUM(total_price), 0) as total_revenue
            FROM bookings");
        return $stmt->fetch();
    }

    public function getVendorStats(int $vendorId): array {
        $stmt = $this->db->prepare("SELECT 
            COUNT(*) as total,
            SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
            SUM(CASE WHEN status = 'confirmed' THEN 1 ELSE 0 END) as confirmed,
            SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed,
            SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END) as cancelled,
            COALESCE(SUM(CASE WHEN status IN ('confirmed','completed') THEN total_price ELSE 0 END), 0) as total_revenue
            FROM bookings WHERE vendor_id = ?");
        $stmt->execute([$vendorId]);
        return $stmt->fetch();
    }
}
