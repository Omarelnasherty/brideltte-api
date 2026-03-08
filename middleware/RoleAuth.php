<?php
require_once __DIR__ . '/Auth.php';
require_once __DIR__ . '/../utils/Response.php';

class RoleAuth {
    public static function requireRole(string ...$roles): array {
        $user = Auth::authenticate();
        
        if (!in_array($user['role'], $roles)) {
            Response::forbidden('You do not have permission to access this resource');
        }

        return $user;
    }

    public static function requireAdmin(): array {
        return self::requireRole('admin');
    }

    public static function requireVendor(): array {
        return self::requireRole('vendor');
    }

    public static function requireVendorOrAdmin(): array {
        return self::requireRole('vendor', 'admin');
    }
}
