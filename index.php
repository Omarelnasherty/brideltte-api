<?php
/**
 * Brideltte API - Single Entry Point Router
 */

// Error reporting
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

// Load config and CORS
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/cors.php';

// Handle CORS
handleCors();

// Get request method and URI
$method = $_SERVER['REQUEST_METHOD'];

// Try to get URI from different sources (for compatibility with different servers)
if (isset($_SERVER['PATH_INFO']) && !empty($_SERVER['PATH_INFO'])) {
    $uri = $_SERVER['PATH_INFO'];
} else {
    $uri = $_SERVER['REQUEST_URI'];
    // Remove query string
    $uri = parse_url($uri, PHP_URL_PATH);
    
    // Remove /api prefix if present
    $basePath = '/api';
    if (strpos($uri, $basePath) === 0) {
        $uri = substr($uri, strlen($basePath));
    }
    
    // Remove /index.php if present
    if (strpos($uri, '/index.php') === 0) {
        $uri = substr($uri, strlen('/index.php'));
    }
}

// Ensure URI starts with /
if (empty($uri) || $uri[0] !== '/') {
    $uri = '/' . $uri;
}

// Remove trailing slash (except for root)
if ($uri !== '/' && substr($uri, -1) === '/') {
    $uri = rtrim($uri, '/');
}

// Load utils
require_once __DIR__ . '/utils/Response.php';
require_once __DIR__ . '/utils/Validator.php';

// Simple router
$routes = [];

function route(string $method, string $pattern, callable $handler): void {
    global $routes;
    $routes[] = [
        'method' => $method,
        'pattern' => $pattern,
        'handler' => $handler,
    ];
}

function matchRoute(string $method, string $uri): void {
    global $routes;
    
    foreach ($routes as $route) {
        if ($route['method'] !== $method && $route['method'] !== 'ANY') {
            continue;
        }

        // Convert route pattern to regex
        $pattern = preg_replace('/\/:([a-zA-Z_]+)/', '/(?P<$1>[^/]+)', $route['pattern']);
        $pattern = '#^' . $pattern . '$#';

        if (preg_match($pattern, $uri, $matches)) {
            // Extract named parameters
            $params = array_filter($matches, 'is_string', ARRAY_FILTER_USE_KEY);
            call_user_func($route['handler'], $params);
            return;
        }
    }

    Response::notFound('Endpoint not found: ' . $method . ' ' . $uri);
}

// ==========================================
// REGISTER ROUTES
// ==========================================

// --- Auth Routes ---
require_once __DIR__ . '/controllers/AuthController.php';
$authController = new AuthController();

route('POST', '/auth/register', fn($p) => $authController->register());
route('POST', '/auth/login', fn($p) => $authController->login());
route('GET', '/auth/me', fn($p) => $authController->me());
route('PUT', '/auth/me', fn($p) => $authController->updateMe());
route('PUT', '/auth/me/password', fn($p) => $authController->changePassword());

// --- Vendor Routes ---
require_once __DIR__ . '/controllers/VendorController.php';
$vendorController = new VendorController();

route('GET', '/vendors', fn($p) => $vendorController->list());
route('GET', '/vendors/me', fn($p) => $vendorController->me());
route('GET', '/vendors/:id', fn($p) => $vendorController->get($p));
route('POST', '/vendors', fn($p) => $vendorController->create());
route('PUT', '/vendors/me', fn($p) => $vendorController->updateMe());
route('POST', '/vendors/me/images', fn($p) => $vendorController->uploadImages());
route('DELETE', '/vendors/me/images/:index', fn($p) => $vendorController->deleteImage($p));

// --- Service Routes ---
require_once __DIR__ . '/controllers/ServiceController.php';
$serviceController = new ServiceController();

route('GET', '/services/vendor/:vendorId', fn($p) => $serviceController->listByVendor($p));
route('POST', '/services', fn($p) => $serviceController->create());
route('PUT', '/services/:id', fn($p) => $serviceController->update($p));
route('DELETE', '/services/:id', fn($p) => $serviceController->delete($p));

// --- Booking Routes ---
require_once __DIR__ . '/controllers/BookingController.php';
$bookingController = new BookingController();

route('GET', '/bookings/my', fn($p) => $bookingController->myBookings());
route('GET', '/bookings/vendor', fn($p) => $bookingController->vendorBookings());
route('POST', '/bookings', fn($p) => $bookingController->create());
route('PUT', '/bookings/:id/status', fn($p) => $bookingController->updateStatus($p));
route('PUT', '/bookings/:id/cancel', fn($p) => $bookingController->cancel($p));
route('PUT', '/bookings/:id/complete', fn($p) => $bookingController->complete($p));

// --- Review Routes ---
require_once __DIR__ . '/controllers/ReviewController.php';
$reviewController = new ReviewController();

route('GET', '/reviews/vendor/:vendorId', fn($p) => $reviewController->listByVendor($p));
route('POST', '/reviews', fn($p) => $reviewController->create());
route('DELETE', '/reviews/:id', fn($p) => $reviewController->delete($p));

// --- Favorite Routes ---
require_once __DIR__ . '/controllers/FavoriteController.php';
$favoriteController = new FavoriteController();

route('GET', '/favorites/my', fn($p) => $favoriteController->myFavorites());
route('POST', '/favorites/toggle', fn($p) => $favoriteController->toggle());
route('GET', '/favorites/check/:vendorId', fn($p) => $favoriteController->check($p));

// --- Contact Routes ---
require_once __DIR__ . '/controllers/ContactController.php';
$contactController = new ContactController();

route('POST', '/contact', fn($p) => $contactController->send());

// --- Upload Routes ---
require_once __DIR__ . '/controllers/UploadController.php';
$uploadController = new UploadController();

route('POST', '/upload/image', fn($p) => $uploadController->uploadImage());

// --- Admin Routes ---
require_once __DIR__ . '/controllers/AdminController.php';
$adminController = new AdminController();

route('GET', '/admin/stats', fn($p) => $adminController->stats());
route('GET', '/admin/vendors', fn($p) => $adminController->vendors());
route('PUT', '/admin/vendors/:id/verify', fn($p) => $adminController->verifyVendor($p));
route('PUT', '/admin/vendors/:id/reject', fn($p) => $adminController->rejectVendor($p));
route('GET', '/admin/bookings', fn($p) => $adminController->bookings());
route('GET', '/admin/users', fn($p) => $adminController->users());
route('PUT', '/admin/users/:id/role', fn($p) => $adminController->updateUserRole($p));
route('PUT', '/admin/users/:id/status', fn($p) => $adminController->updateUserStatus($p));
route('GET', '/admin/contacts', fn($p) => $adminController->contacts());
route('PUT', '/admin/contacts/:id/status', fn($p) => $adminController->updateContactStatus($p));

// Health check
route('GET', '/', fn($p) => Response::success(['version' => APP_VERSION], APP_NAME . ' is running'));
route('GET', '/health', fn($p) => Response::success(['status' => 'ok', 'version' => APP_VERSION], 'Healthy'));

// ==========================================
// DISPATCH REQUEST
// ==========================================
matchRoute($method, $uri);
