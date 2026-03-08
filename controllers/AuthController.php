<?php
require_once __DIR__ . '/../models/User.php';
require_once __DIR__ . '/../utils/Response.php';
require_once __DIR__ . '/../utils/Validator.php';
require_once __DIR__ . '/../utils/JwtHandler.php';
require_once __DIR__ . '/../middleware/Auth.php';
require_once __DIR__ . '/../middleware/RateLimit.php';

class AuthController {
    private User $userModel;

    public function __construct() {
        $this->userModel = new User();
    }

    public function register(): void {
        RateLimit::checkAuth();
        $body = Validator::getBody();

        $v = new Validator($body);
        $v->required('name', 'Name')
          ->minLength('name', 2, 'Name')
          ->maxLength('name', 255, 'Name')
          ->required('email', 'Email')
          ->email('email')
          ->required('password', 'Password')
          ->minLength('password', 6, 'Password')
          ->validate();

        // Check if email already exists
        $existing = $this->userModel->findByEmail($body['email']);
        if ($existing) {
            Response::error('Email already registered', 409);
        }

        $userId = $this->userModel->create([
            'name' => Validator::sanitize($body['name']),
            'email' => strtolower(trim($body['email'])),
            'password' => $body['password'],
            'phone' => isset($body['phone']) ? Validator::sanitize($body['phone']) : null,
        ]);

        $user = $this->userModel->findById($userId);
        $token = JwtHandler::encode(['user_id' => $userId, 'role' => $user['role']]);

        Response::success([
            'token' => $token,
            'user' => $user,
        ], 'Registration successful', 201);
    }

    public function login(): void {
        RateLimit::checkAuth();
        $body = Validator::getBody();

        $v = new Validator($body);
        $v->required('email', 'Email')
          ->email('email')
          ->required('password', 'Password')
          ->validate();

        $user = $this->userModel->findByEmail(strtolower(trim($body['email'])));
        if (!$user) {
            Response::error('Invalid email or password', 401);
        }

        if (!$user['is_active']) {
            Response::error('Account has been deactivated', 403);
        }

        if (!password_verify($body['password'], $user['password'])) {
            Response::error('Invalid email or password', 401);
        }

        $token = JwtHandler::encode(['user_id' => $user['id'], 'role' => $user['role']]);

        // Remove password from response
        unset($user['password']);

        Response::success([
            'token' => $token,
            'user' => $user,
        ], 'Login successful');
    }

    public function me(): void {
        $user = Auth::authenticate();
        Response::success($user);
    }

    public function updateMe(): void {
        $user = Auth::authenticate();
        $body = Validator::getBody();

        $updateData = [];
        if (isset($body['name'])) {
            $v = new Validator($body);
            $v->minLength('name', 2, 'Name')->maxLength('name', 255, 'Name')->validate();
            $updateData['name'] = Validator::sanitize($body['name']);
        }
        if (isset($body['phone'])) {
            $updateData['phone'] = Validator::sanitize($body['phone']);
        }
        if (isset($body['avatar'])) {
            $updateData['avatar'] = $body['avatar'];
        }

        if (empty($updateData)) {
            Response::error('No data to update', 400);
        }

        $this->userModel->update($user['id'], $updateData);
        $updatedUser = $this->userModel->findById($user['id']);

        Response::success($updatedUser, 'Profile updated successfully');
    }

    public function changePassword(): void {
        $user = Auth::authenticate();
        $body = Validator::getBody();

        $v = new Validator($body);
        $v->required('current_password', 'Current password')
          ->required('new_password', 'New password')
          ->minLength('new_password', 6, 'New password')
          ->validate();

        // Get full user with password
        $fullUser = $this->userModel->findByEmail($user['email']);
        if (!password_verify($body['current_password'], $fullUser['password'])) {
            Response::error('Current password is incorrect', 400);
        }

        $this->userModel->updatePassword($user['id'], password_hash($body['new_password'], PASSWORD_BCRYPT));

        Response::success(null, 'Password changed successfully');
    }
}
