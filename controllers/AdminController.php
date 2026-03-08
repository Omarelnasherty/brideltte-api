<?php
require_once __DIR__ . '/../models/User.php';
require_once __DIR__ . '/../models/Vendor.php';
require_once __DIR__ . '/../models/Booking.php';
require_once __DIR__ . '/../models/ContactMessage.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../utils/Response.php';
require_once __DIR__ . '/../utils/Validator.php';
require_once __DIR__ . '/../middleware/RoleAuth.php';

class AdminController {
    private User $userModel;
    private Vendor $vendorModel;
    private Booking $bookingModel;
    private ContactMessage $contactModel;

    public function __construct() {
        $this->userModel = new User();
        $this->vendorModel = new Vendor();
        $this->bookingModel = new Booking();
        $this->contactModel = new ContactMessage();
    }

    public function stats(): void {
        RoleAuth::requireAdmin();
        $db = Database::getInstance();

        $totalUsers = (int)$db->query("SELECT COUNT(*) FROM users")->fetchColumn();
        $totalVendors = (int)$db->query("SELECT COUNT(*) FROM vendors")->fetchColumn();
        $pendingVendors = (int)$db->query("SELECT COUNT(*) FROM vendors WHERE verified = 0")->fetchColumn();
        $verifiedVendors = (int)$db->query("SELECT COUNT(*) FROM vendors WHERE verified = 1")->fetchColumn();
        $newContacts = (int)$db->query("SELECT COUNT(*) FROM contact_messages WHERE status = 'new'")->fetchColumn();
        
        $bookingStats = $this->bookingModel->getStats();

        Response::success([
            'users' => $totalUsers,
            'vendors' => [
                'total' => $totalVendors,
                'pending' => $pendingVendors,
                'verified' => $verifiedVendors,
            ],
            'bookings' => $bookingStats,
            'contacts' => [
                'new' => $newContacts,
            ],
        ]);
    }

    public function vendors(): void {
        RoleAuth::requireAdmin();
        $pagination = Validator::getPagination();
        $category = $_GET['category'] ?? null;
        $verified = isset($_GET['verified']) ? ($_GET['verified'] === '1' || $_GET['verified'] === 'true') : null;

        $result = $this->vendorModel->listAll($pagination['limit'], $pagination['offset'], $category, $verified);
        Response::paginated($result['data'], $result['total'], $pagination['page'], $pagination['limit']);
    }

    public function verifyVendor(array $params): void {
        RoleAuth::requireAdmin();
        $vendorId = (int)($params['id'] ?? 0);

        $vendor = $this->vendorModel->findById($vendorId);
        if (!$vendor) {
            Response::notFound('Vendor not found');
        }

        $this->vendorModel->setVerified($vendorId, true);
        Response::success(null, 'Vendor verified successfully');
    }

    public function rejectVendor(array $params): void {
        RoleAuth::requireAdmin();
        $vendorId = (int)($params['id'] ?? 0);

        $vendor = $this->vendorModel->findById($vendorId);
        if (!$vendor) {
            Response::notFound('Vendor not found');
        }

        $this->vendorModel->setVerified($vendorId, false);
        Response::success(null, 'Vendor rejected');
    }

    public function bookings(): void {
        RoleAuth::requireAdmin();
        $pagination = Validator::getPagination();
        $status = $_GET['status'] ?? null;

        $result = $this->bookingModel->listAll($pagination['limit'], $pagination['offset'], $status);
        Response::paginated($result['data'], $result['total'], $pagination['page'], $pagination['limit']);
    }

    public function users(): void {
        RoleAuth::requireAdmin();
        $pagination = Validator::getPagination();
        $role = $_GET['role'] ?? null;

        $result = $this->userModel->listAll($pagination['limit'], $pagination['offset'], $role);
        Response::paginated($result['data'], $result['total'], $pagination['page'], $pagination['limit']);
    }

    public function updateUserRole(array $params): void {
        RoleAuth::requireAdmin();
        $userId = (int)($params['id'] ?? 0);
        $body = Validator::getBody();

        $v = new Validator($body);
        $v->required('role', 'Role')
          ->in('role', ['user', 'vendor', 'admin'], 'Role')
          ->validate();

        $user = $this->userModel->findById($userId);
        if (!$user) {
            Response::notFound('User not found');
        }

        $this->userModel->updateRole($userId, $body['role']);
        Response::success(null, 'User role updated successfully');
    }

    public function updateUserStatus(array $params): void {
        RoleAuth::requireAdmin();
        $userId = (int)($params['id'] ?? 0);
        $body = Validator::getBody();

        $user = $this->userModel->findById($userId);
        if (!$user) {
            Response::notFound('User not found');
        }

        $active = isset($body['is_active']) ? (bool)$body['is_active'] : true;
        $this->userModel->updateStatus($userId, $active);
        Response::success(null, $active ? 'User activated' : 'User deactivated');
    }

    public function contacts(): void {
        RoleAuth::requireAdmin();
        $pagination = Validator::getPagination();
        $status = $_GET['status'] ?? null;

        $result = $this->contactModel->listAll($pagination['limit'], $pagination['offset'], $status);
        Response::paginated($result['data'], $result['total'], $pagination['page'], $pagination['limit']);
    }

    public function updateContactStatus(array $params): void {
        RoleAuth::requireAdmin();
        $contactId = (int)($params['id'] ?? 0);
        $body = Validator::getBody();

        $v = new Validator($body);
        $v->required('status', 'Status')
          ->in('status', ['new', 'read', 'replied'], 'Status')
          ->validate();

        $this->contactModel->updateStatus($contactId, $body['status']);
        Response::success(null, 'Contact status updated');
    }
}
