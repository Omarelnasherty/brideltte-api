<?php
require_once __DIR__ . '/../utils/JwtHandler.php';
require_once __DIR__ . '/../utils/Response.php';
require_once __DIR__ . '/../config/database.php';

class Auth {
    private static ?array $currentUser = null;

    public static function authenticate(): array {
        $token = self::getBearerToken();
        if (!$token) {
            Response::unauthorized('No authentication token provided');
        }

        $payload = JwtHandler::decode($token);
        if (!$payload || !isset($payload['user_id'])) {
            Response::unauthorized('Invalid or expired token');
        }

        $db = Database::getInstance();
        $stmt = $db->prepare("SELECT id, name, email, phone, avatar, role, is_active FROM users WHERE id = ? AND is_active = 1");
        $stmt->execute([$payload['user_id']]);
        $user = $stmt->fetch();

        if (!$user) {
            Response::unauthorized('User not found or deactivated');
        }

        self::$currentUser = $user;
        return $user;
    }

    public static function optionalAuth(): ?array {
        $token = self::getBearerToken();
        if (!$token) {
            return null;
        }

        $payload = JwtHandler::decode($token);
        if (!$payload || !isset($payload['user_id'])) {
            return null;
        }

        $db = Database::getInstance();
        $stmt = $db->prepare("SELECT id, name, email, phone, avatar, role, is_active FROM users WHERE id = ? AND is_active = 1");
        $stmt->execute([$payload['user_id']]);
        $user = $stmt->fetch();

        self::$currentUser = $user ?: null;
        return self::$currentUser;
    }

    public static function getCurrentUser(): ?array {
        return self::$currentUser;
    }

    private static function getBearerToken(): ?string {
        $headers = '';

        if (isset($_SERVER['HTTP_AUTHORIZATION'])) {
            $headers = $_SERVER['HTTP_AUTHORIZATION'];
        } elseif (isset($_SERVER['REDIRECT_HTTP_AUTHORIZATION'])) {
            $headers = $_SERVER['REDIRECT_HTTP_AUTHORIZATION'];
        } elseif (function_exists('apache_request_headers')) {
            $requestHeaders = apache_request_headers();
            if (isset($requestHeaders['Authorization'])) {
                $headers = $requestHeaders['Authorization'];
            }
        }

        if (preg_match('/Bearer\s(\S+)/', $headers, $matches)) {
            return $matches[1];
        }

        return null;
    }
}
