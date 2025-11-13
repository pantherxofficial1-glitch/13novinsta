<?php
require_once __DIR__ . '/../config.php';

class Storage {
    private static $data = null;
    
    public static function loadData() {
        if (self::$data !== null) {
            return self::$data;
        }
        
        if (file_exists(DATA_FILE)) {
            $content = file_get_contents(DATA_FILE);
            self::$data = json_decode($content, true);
        } else {
            global $DEFAULT_CONFIG;
            self::$data = array_merge([
                'users' => [],
                'refcodes' => [],
                'history' => [],
                'giftcodes' => [],
                'banned' => []
            ], $DEFAULT_CONFIG);
            self::saveData();
        }
        
        return self::$data;
    }
    
    public static function saveData() {
        if (self::$data !== null) {
            file_put_contents(DATA_FILE, json_encode(self::$data, JSON_PRETTY_PRINT));
        }
    }
    
    public static function ensureUser($userId) {
        $uid = (string)$userId;
        $data = self::loadData();
        
        if (!isset($data['users'][$uid])) {
            $refCode = self::generateRefCode();
            $data['users'][$uid] = [
                'quota' => $data['settings']['default_quota'],
                'referrals' => 0,
                'ref_code' => $refCode,
                'created_at' => time(),
                'granted_by_owner' => 0,
                'referred_by' => null,
                'verified' => false,
                'has_initial_access' => false
            ];
            $data['refcodes'][$refCode] = $uid;
            self::$data = $data;
            self::saveData();
        }
        
        return $data['users'][$uid];
    }
    
    public static function generateRefCode($length = 8) {
        $characters = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz';
        $code = '';
        for ($i = 0; $i < $length; $i++) {
            $code .= $characters[rand(0, strlen($characters) - 1)];
        }
        return $code;
    }
    
    public static function isBanned($userId) {
        $data = self::loadData();
        return in_array((string)$userId, $data['banned']);
    }
    
    public static function saveSearchHistory($username, $profileData, $contactInfo, $userId) {
        $username = strtolower($username);
        $data = self::loadData();
        
        $record = [
            'timestamp' => time(),
            'profile' => $profileData,
            'contact' => $contactInfo,
            'searched_by' => (string)$userId
        ];
        
        if (!isset($data['history'][$username])) {
            $data['history'][$username] = [];
        }
        
        $data['history'][$username][] = $record;
        self::$data = $data;
        self::saveData();
    }
    
    public static function getLastSearch($username) {
        $username = strtolower($username);
        $data = self::loadData();
        $searches = $data['history'][$username] ?? [];
        return !empty($searches) ? end($searches) : null;
    }
    
    public static function getUserSearches($userId) {
        $data = self::loadData();
        $userSearches = [];
        
        foreach ($data['history'] as $username => $searches) {
            foreach ($searches as $search) {
                if ($search['searched_by'] == (string)$userId) {
                    $userSearches[] = $username;
                    break;
                }
            }
        }
        
        return $userSearches;
    }
}
?>