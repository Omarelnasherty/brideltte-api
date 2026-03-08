<?php

class Validator {
    private array $errors = [];
    private array $data;

    public function __construct(array $data) {
        $this->data = $data;
    }

    public function required(string $field, string $label = ''): self {
        $label = $label ?: $field;
        if (!isset($this->data[$field]) || trim((string)$this->data[$field]) === '') {
            $this->errors[$field] = "$label is required";
        }
        return $this;
    }

    public function email(string $field, string $label = 'Email'): self {
        if (isset($this->data[$field]) && !filter_var($this->data[$field], FILTER_VALIDATE_EMAIL)) {
            $this->errors[$field] = "$label must be a valid email address";
        }
        return $this;
    }

    public function minLength(string $field, int $min, string $label = ''): self {
        $label = $label ?: $field;
        if (isset($this->data[$field]) && strlen((string)$this->data[$field]) < $min) {
            $this->errors[$field] = "$label must be at least $min characters";
        }
        return $this;
    }

    public function maxLength(string $field, int $max, string $label = ''): self {
        $label = $label ?: $field;
        if (isset($this->data[$field]) && strlen((string)$this->data[$field]) > $max) {
            $this->errors[$field] = "$label must not exceed $max characters";
        }
        return $this;
    }

    public function numeric(string $field, string $label = ''): self {
        $label = $label ?: $field;
        if (isset($this->data[$field]) && !is_numeric($this->data[$field])) {
            $this->errors[$field] = "$label must be a number";
        }
        return $this;
    }

    public function min(string $field, $min, string $label = ''): self {
        $label = $label ?: $field;
        if (isset($this->data[$field]) && is_numeric($this->data[$field]) && $this->data[$field] < $min) {
            $this->errors[$field] = "$label must be at least $min";
        }
        return $this;
    }

    public function max(string $field, $max, string $label = ''): self {
        $label = $label ?: $field;
        if (isset($this->data[$field]) && is_numeric($this->data[$field]) && $this->data[$field] > $max) {
            $this->errors[$field] = "$label must not exceed $max";
        }
        return $this;
    }

    public function in(string $field, array $values, string $label = ''): self {
        $label = $label ?: $field;
        if (isset($this->data[$field]) && !in_array($this->data[$field], $values)) {
            $this->errors[$field] = "$label must be one of: " . implode(', ', $values);
        }
        return $this;
    }

    public function date(string $field, string $label = ''): self {
        $label = $label ?: $field;
        if (isset($this->data[$field])) {
            $d = \DateTime::createFromFormat('Y-m-d', $this->data[$field]);
            if (!$d || $d->format('Y-m-d') !== $this->data[$field]) {
                $this->errors[$field] = "$label must be a valid date (YYYY-MM-DD)";
            }
        }
        return $this;
    }

    public function futureDate(string $field, string $label = ''): self {
        $label = $label ?: $field;
        if (isset($this->data[$field])) {
            $d = \DateTime::createFromFormat('Y-m-d', $this->data[$field]);
            if ($d && $d < new \DateTime('today')) {
                $this->errors[$field] = "$label must be a future date";
            }
        }
        return $this;
    }

    public function passes(): bool {
        return empty($this->errors);
    }

    public function errors(): array {
        return $this->errors;
    }

    public function validate(): void {
        if (!$this->passes()) {
            require_once __DIR__ . '/Response.php';
            Response::validationError($this->errors);
        }
    }

    // Get sanitized value
    public static function sanitize(string $value): string {
        return htmlspecialchars(strip_tags(trim($value)), ENT_QUOTES, 'UTF-8');
    }

    // Get request body as array
    public static function getBody(): array {
        $body = json_decode(file_get_contents('php://input'), true);
        return is_array($body) ? $body : [];
    }

    // Get query params
    public static function getQuery(): array {
        return $_GET;
    }

    // Get pagination params
    public static function getPagination(): array {
        $page = max(1, (int)($_GET['page'] ?? 1));
        $limit = min(MAX_PAGE_SIZE, max(1, (int)($_GET['limit'] ?? DEFAULT_PAGE_SIZE)));
        $offset = ($page - 1) * $limit;
        return ['page' => $page, 'limit' => $limit, 'offset' => $offset];
    }
}
