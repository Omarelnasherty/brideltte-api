<?php
require_once __DIR__ . '/../models/Review.php';
require_once __DIR__ . '/../models/Booking.php';
require_once __DIR__ . '/../models/Vendor.php';
require_once __DIR__ . '/../utils/Response.php';
require_once __DIR__ . '/../utils/Validator.php';
require_once __DIR__ . '/../middleware/Auth.php';

class ReviewController {
    private Review $reviewModel;
    private Booking $bookingModel;
    private Vendor $vendorModel;

    public function __construct() {
        $this->reviewModel = new Review();
        $this->bookingModel = new Booking();
        $this->vendorModel = new Vendor();
    }

    public function listByVendor(array $params): void {
        $vendorId = (int)($params['vendorId'] ?? 0);
        $reviews = $this->reviewModel->findByVendor($vendorId);
        Response::success($reviews);
    }

    public function create(): void {
        $user = Auth::authenticate();
        $body = Validator::getBody();

        $v = new Validator($body);
        $v->required('vendor_id', 'Vendor')
          ->required('booking_id', 'Booking')
          ->required('rating', 'Rating')
          ->numeric('rating', 'Rating')
          ->min('rating', 1, 'Rating')
          ->max('rating', 5, 'Rating')
          ->required('comment', 'Comment')
          ->minLength('comment', 5, 'Comment')
          ->validate();

        $bookingId = (int)$body['booking_id'];
        $vendorId = (int)$body['vendor_id'];

        // Verify booking exists and belongs to user
        $booking = $this->bookingModel->findById($bookingId);
        if (!$booking) {
            Response::notFound('Booking not found');
        }
        if ((int)$booking['user_id'] !== (int)$user['id']) {
            Response::forbidden('You can only review your own bookings');
        }
        if ($booking['status'] !== 'completed') {
            Response::error('You can only review completed bookings', 400);
        }
        if ((int)$booking['vendor_id'] !== $vendorId) {
            Response::error('Booking does not match vendor', 400);
        }

        // Check if already reviewed
        if ($this->reviewModel->existsForBooking($bookingId)) {
            Response::error('You have already reviewed this booking', 409);
        }

        $reviewId = $this->reviewModel->create([
            'user_id' => $user['id'],
            'vendor_id' => $vendorId,
            'booking_id' => $bookingId,
            'rating' => (int)$body['rating'],
            'comment' => Validator::sanitize($body['comment']),
            'images' => $body['images'] ?? [],
        ]);

        // Update vendor rating
        $this->vendorModel->updateRating($vendorId);

        $review = $this->reviewModel->findById($reviewId);
        Response::success($review, 'Review submitted successfully', 201);
    }

    public function delete(array $params): void {
        $user = Auth::authenticate();
        $reviewId = (int)($params['id'] ?? 0);

        $review = $this->reviewModel->findById($reviewId);
        if (!$review) {
            Response::notFound('Review not found');
        }

        // Only owner or admin can delete
        if ((int)$review['user_id'] !== (int)$user['id'] && $user['role'] !== 'admin') {
            Response::forbidden('You can only delete your own reviews');
        }

        $vendorId = (int)$review['vendor_id'];
        $this->reviewModel->delete($reviewId);

        // Update vendor rating
        $this->vendorModel->updateRating($vendorId);

        Response::success(null, 'Review deleted successfully');
    }
}
