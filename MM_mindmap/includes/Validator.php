
<?php
// includes/Validator.php
class Validator {
    public static function email($email) {
        return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
    }
    
    public static function username($username) {
        return preg_match('/^[a-zA-Z0-9_]{3,20}$/', $username);
    }
    
    public static function password($password) {
        return strlen($password) >= 6;
    }
    
    public static function required($value) {
        return !empty(trim($value));
    }
    
    public static function length($value, $min = 0, $max = null) {
        $length = strlen($value);
        if ($length < $min) return false;
        if ($max !== null && $length > $max) return false;
        return true;
    }
    
    public static function url($url) {
        return filter_var($url, FILTER_VALIDATE_URL) !== false;
    }
    
    public static function hexColor($color) {
        return preg_match('/^#[a-f0-9]{6}$/i', $color);
    }
    
    public static function sanitizeString($string) {
        return htmlspecialchars(trim($string), ENT_QUOTES, 'UTF-8');
    }
    
    public static function sanitizeEmail($email) {
        return filter_var(trim($email), FILTER_SANITIZE_EMAIL);
    }
    
    public static function validateMindMapData($data) {
        $errors = [];
        
        if (!self::required($data['title'] ?? '')) {
            $errors[] = 'Title is required';
        } elseif (!self::length($data['title'], 1, 255)) {
            $errors[] = 'Title must be between 1 and 255 characters';
        }
        
        if (!self::required($data['central_node'] ?? '')) {
            $errors[] = 'Central node text is required';
        }
        
        if (isset($data['category_id']) && !is_numeric($data['category_id'])) {
            $errors[] = 'Invalid category ID';
        }
        
        return $errors;
    }
    
    public static function validateNodeData($data) {
        $errors = [];
        
        if (!self::required($data['node_text'] ?? '')) {
            $errors[] = 'Node text is required';
        }
        
        if (isset($data['color']) && !self::hexColor($data['color'])) {
            $errors[] = 'Invalid color format';
        }
        
        if (isset($data['background_color']) && !self::hexColor($data['background_color'])) {
            $errors[] = 'Invalid background color format';
        }
        
        if (isset($data['font_size']) && (!is_numeric($data['font_size']) || $data['font_size'] < 8 || $data['font_size'] > 72)) {
            $errors[] = 'Font size must be between 8 and 72';
        }
        
        return $errors;
    }
}
?>
