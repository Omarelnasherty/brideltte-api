<?php
// Application Configuration
define('APP_NAME', 'Brideltte API');
define('APP_VERSION', '1.0.0');
define('APP_ENV', 'production'); // 'development' or 'production'

// JWT Configuration
define('JWT_SECRET', 'brideltte_jwt_secret_key_change_in_production_2024');
define('JWT_EXPIRY', 86400 * 7); // 7 days in seconds
define('JWT_ALGORITHM', 'HS256');

// Database Configuration
define('DB_HOST', getenv('DB_HOST') ?: 'localhost');
define('DB_NAME', getenv('DB_NAME') ?: 'brideltte_db');
define('DB_USER', getenv('DB_USER') ?: 'root');
define('DB_PASS', getenv('DB_PASS') ?: '');
define('DB_CHARSET', 'utf8mb4');

// Upload Configuration
define('UPLOAD_DIR', __DIR__ . '/../uploads/');
define('MAX_FILE_SIZE', 5 * 1024 * 1024); // 5MB
define('ALLOWED_IMAGE_TYPES', ['image/jpeg', 'image/png', 'image/webp', 'image/gif']);

// Pagination
define('DEFAULT_PAGE_SIZE', 12);
define('MAX_PAGE_SIZE', 50);

// Rate Limiting
define('RATE_LIMIT_WINDOW', 60); // seconds
define('RATE_LIMIT_MAX_REQUESTS', 60); // max requests per window
define('RATE_LIMIT_AUTH_MAX', 10); // max auth attempts per window

// CORS
define('ALLOWED_ORIGINS', [
    'http://localhost:5173',
    'http://localhost:3000',
    'https://brideltte-wedding.netlify.app',
]);
