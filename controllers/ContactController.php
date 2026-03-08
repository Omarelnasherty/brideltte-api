<?php
require_once __DIR__ . '/../models/ContactMessage.php';
require_once __DIR__ . '/../utils/Response.php';
require_once __DIR__ . '/../utils/Validator.php';
require_once __DIR__ . '/../middleware/RateLimit.php';

class ContactController {
    private ContactMessage $contactModel;

    public function __construct() {
        $this->contactModel = new ContactMessage();
    }

    public function send(): void {
        RateLimit::check(5, 60); // Max 5 messages per minute
        $body = Validator::getBody();

        $v = new Validator($body);
        $v->required('name', 'Name')
          ->minLength('name', 2, 'Name')
          ->required('email', 'Email')
          ->email('email')
          ->required('subject', 'Subject')
          ->minLength('subject', 3, 'Subject')
          ->required('message', 'Message')
          ->minLength('message', 10, 'Message')
          ->validate();

        $this->contactModel->create([
            'name' => Validator::sanitize($body['name']),
            'email' => strtolower(trim($body['email'])),
            'subject' => Validator::sanitize($body['subject']),
            'message' => Validator::sanitize($body['message']),
        ]);

        Response::success(null, 'Message sent successfully! We\'ll get back to you soon.', 201);
    }
}
