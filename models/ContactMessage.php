<?php
require_once __DIR__ . '/../config/database.php';

class ContactMessage {
    private PDO $db;

    public function __construct() {
        $this->db = Database::getInstance();
    }

    public function create(array $data): int {
        $stmt = $this->db->prepare(
            "INSERT INTO contact_messages (name, email, subject, message) VALUES (?, ?, ?, ?)"
        );
        $stmt->execute([
            $data['name'],
            $data['email'],
            $data['subject'],
            $data['message'],
        ]);
        return (int) $this->db->lastInsertId();
    }

    public function listAll(int $limit, int $offset, ?string $status = null): array {
        $where = "WHERE 1=1";
        $params = [];
        if ($status) {
            $where .= " AND status = ?";
            $params[] = $status;
        }

        $countStmt = $this->db->prepare("SELECT COUNT(*) FROM contact_messages $where");
        $countStmt->execute($params);
        $total = (int) $countStmt->fetchColumn();

        $params[] = $limit;
        $params[] = $offset;
        $stmt = $this->db->prepare(
            "SELECT * FROM contact_messages $where ORDER BY created_at DESC LIMIT ? OFFSET ?"
        );
        $stmt->execute($params);
        return ['data' => $stmt->fetchAll(), 'total' => $total];
    }

    public function updateStatus(int $id, string $status): bool {
        $stmt = $this->db->prepare("UPDATE contact_messages SET status = ? WHERE id = ?");
        return $stmt->execute([$status, $id]);
    }
}
