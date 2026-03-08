<?php
require_once __DIR__ . '/../models/Booking.php';
require_once __DIR__ . '/../models/Service.php';
require_once __DIR__ . '/../models/Vendor.php';
require_once __DIR__ . '/../utils/Response.php';
require_once __DIR__ . '/../utils/Validator.php';
require_once __DIR__ . '/../middleware/Auth.php';
require_once __DIR__ . '/../middleware/RoleAuth.php';

class BookingController {
    private Booking $bookingModel;
    private Service $serviceModel;
    private Vendor $vendorModel;

    public function __construct() {
        $this->bookingModel = new Booking();
        $this->serviceModel = new Service();
        $this->vendorModel = new Vendor();
    }

    public function myBookings(): void {
        $user = Auth::authenticate();
        $pagination = Validator::getPagination();

        $result = $this->bookingModel->findByUser($user['id'], $pagination['limit'], $pagination['offset']);
        Response::paginated($result['data'], $result['total'], $pagination['page'], $pagination['limit']);
    }

    public function vendorBookings(): void {
        $user = RoleAuth::requireVendor();
        $vendor = $this->vendorModel->findByUserId($user['id']);
        if (!$vendor) {
            Response::notFound('Vendor profile not found');
        }

        $pagination = Validator::getPagination();
        $status = $_GET['status'] ?? null;

        $result = $this->bookingModel->findByVendor($vendor['id'], $pagination['limit'], $pagination['offset'], $status);
        Response::paginated($result['data'], $result['total'], $pagination['page'], $pagination['limit']);
    }

    public function create(): void {
        $user = Auth::authenticate();
        $body = Validator::getBody();

        $v = new Validator($body);
        $v->required('vendor_id', 'Vendor')
          ->required('service_id', 'Service')
          ->required('event_date', 'Event date')
          ->date('event_date', 'Event date')
          ->futureDate('event_date', 'Event date')
          ->required('event_time', 'Event time')
          ->required('location', 'Location')
          ->required('guest_count', 'Guest count')
          ->numeric('guest_count', 'Guest count')
          ->min('guest_count', 1, 'Guest count')
          ->validate();

        // Verify service exists and belongs to vendor
        $service = $this->serviceModel->findById((int)$body['service_id']);
        if (!$service) {
            Response::notFound('Service not found');
        }
        if ((int)$service['vendor_id'] !== (int)$body['vendor_id']) {
            Response::error('Service does not belong to this vendor', 400);
        }
        if (!$service['available']) {
            Response::error('This service is currently unavailable', 400);
        }

        // Verify vendor exists and is active
        $vendor = $this->vendorModel->findById((int)$body['vendor_id']);
        if (!$vendor || !$vendor['is_active'] || !$vendor['verified']) {
            Response::error('Vendor is not available for bookings', 400);
        }

        $bookingId = $this->bookingModel->create([
            'user_id' => $user['id'],
            'vendor_id' => (int)$body['vendor_id'],
            'service_id' => (int)$body['service_id'],
            'event_date' => $body['event_date'],
            'event_time' => Validator::sanitize($body['event_time']),
            'location' => Validator::sanitize($body['location']),
            'guest_count' => (int)$body['guest_count'],
            'total_price' => (float)$service['price'],
            'notes' => isset($body['notes']) ? Validator::sanitize($body['notes']) : null,
        ]);

        $booking = $this->bookingModel->findById($bookingId);
        Response::success($booking, 'Booking request sent successfully', 201);
    }

    public function updateStatus(array $params): void {
        $user = RoleAuth::requireVendor();
        $vendor = $this->vendorModel->findByUserId($user['id']);
        if (!$vendor) {
            Response::notFound('Vendor profile not found');
        }

        $bookingId = (int)($params['id'] ?? 0);
        $booking = $this->bookingModel->findById($bookingId);
        if (!$booking) {
            Response::notFound('Booking not found');
        }

        if ((int)$booking['vendor_id'] !== (int)$vendor['id']) {
            Response::forbidden('You can only manage bookings for your own venue');
        }

        $body = Validator::getBody();
        $v = new Validator($body);
        $v->required('status', 'Status')
          ->in('status', ['confirmed', 'cancelled'], 'Status')
          ->validate();

        // Only pending bookings can be confirmed/cancelled by vendor
        if ($booking['status'] !== 'pending') {
            Response::error('Only pending bookings can be updated', 400);
        }

        $cancelReason = ($body['status'] === 'cancelled' && isset($body['cancel_reason']))
            ? Validator::sanitize($body['cancel_reason'])
            : null;

        $this->bookingModel->updateStatus($bookingId, $body['status'], $cancelReason);

        $updated = $this->bookingModel->findById($bookingId);
        Response::success($updated, 'Booking status updated successfully');
    }

    public function cancel(array $params): void {
        $user = Auth::authenticate();

        $bookingId = (int)($params['id'] ?? 0);
        $booking = $this->bookingModel->findById($bookingId);
        if (!$booking) {
            Response::notFound('Booking not found');
        }

        if ((int)$booking['user_id'] !== (int)$user['id']) {
            Response::forbidden('You can only cancel your own bookings');
        }

        if (!in_array($booking['status'], ['pending', 'confirmed'])) {
            Response::error('This booking cannot be cancelled', 400);
        }

        $body = Validator::getBody();
        $cancelReason = isset($body['cancel_reason']) ? Validator::sanitize($body['cancel_reason']) : 'Cancelled by user';

        $this->bookingModel->updateStatus($bookingId, 'cancelled', $cancelReason);

        $updated = $this->bookingModel->findById($bookingId);
        Response::success($updated, 'Booking cancelled successfully');
    }

    public function complete(array $params): void {
        $user = RoleAuth::requireVendor();
        $vendor = $this->vendorModel->findByUserId($user['id']);
        if (!$vendor) {
            Response::notFound('Vendor profile not found');
        }

        $bookingId = (int)($params['id'] ?? 0);
        $booking = $this->bookingModel->findById($bookingId);
        if (!$booking) {
            Response::notFound('Booking not found');
        }

        if ((int)$booking['vendor_id'] !== (int)$vendor['id']) {
            Response::forbidden('You can only manage bookings for your own venue');
        }

        if ($booking['status'] !== 'confirmed') {
            Response::error('Only confirmed bookings can be marked as completed', 400);
        }

        $this->bookingModel->updateStatus($bookingId, 'completed');

        $updated = $this->bookingModel->findById($bookingId);
        Response::success($updated, 'Booking marked as completed');
    }
}
