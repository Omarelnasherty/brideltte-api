<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../utils/Response.php';

class RateLimit {
    private static string $storageDir = '';

    private static function getStorageDir(): string {
        if (empty(self::$storageDir)) {
            self::$storageDir = sys_get_temp_dir() . '/brideltte_rate_limit/';
            if (!is_dir(self::$storageDir)) {
                @mkdir(self::$storageDir, 0755, true);
            }
        }
        return self::$storageDir;
    }

    public static function check(int $maxRequests = 0, int $window = 0): void {
        $maxRequests = $maxRequests ?: RATE_LIMIT_MAX_REQUESTS;
        $window = $window ?: RATE_LIMIT_WINDOW;
        
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $key = md5($ip . $_SERVER['REQUEST_URI']);
        $file = self::getStorageDir() . $key . '.json';

        $now = time();
        $data = ['count' => 0, 'reset' => $now + $window];

        if (file_exists($file)) {
            $content = @file_get_contents($file);
            $stored = $content ? json_decode($content, true) : null;
            if ($stored && isset($stored['reset']) && $stored['reset'] > $now) {
                $data = $stored;
            }
        }

        $data['count']++;

        if ($data['count'] > $maxRequests) {
            header('Retry-After: ' . ($data['reset'] - $now));
            Response::error('Too many requests. Please try again later.', 429);
        }

        @file_put_contents($file, json_encode($data));
    }

    public static function checkAuth(): void {
        self::check(RATE_LIMIT_AUTH_MAX, RATE_LIMIT_WINDOW);
    }
}
