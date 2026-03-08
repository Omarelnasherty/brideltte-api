<?php
require_once __DIR__ . '/../models/Favorite.php';
require_once __DIR__ . '/../utils/Response.php';
require_once __DIR__ . '/../utils/Validator.php';
require_once __DIR__ . '/../middleware/Auth.php';

class FavoriteController {
    private Favorite $favoriteModel;

    public function __construct() {
        $this->favoriteModel = new Favorite();
    }

    public function myFavorites(): void {
        $user = Auth::authenticate();
        $favorites = $this->favoriteModel->getUserFavorites($user['id']);
        Response::success($favorites);
    }

    public function toggle(): void {
        $user = Auth::authenticate();
        $body = Validator::getBody();

        $v = new Validator($body);
        $v->required('vendor_id', 'Vendor ID')->validate();

        $result = $this->favoriteModel->toggle($user['id'], (int)$body['vendor_id']);
        $message = $result['added'] ? 'Added to favorites' : 'Removed from favorites';
        Response::success($result, $message);
    }

    public function check(array $params): void {
        $user = Auth::authenticate();
        $vendorId = (int)($params['vendorId'] ?? 0);
        $isFavorite = $this->favoriteModel->isFavorite($user['id'], $vendorId);
        Response::success(['is_favorite' => $isFavorite]);
    }
}
