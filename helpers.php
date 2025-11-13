<?php
require_once __DIR__ . '/storage.php';

class Helpers {
    public static function generatePremiumContact($username) {
        $phone = "+91 " . rand(70000, 99999) . " " . rand(10000, 99999);
        $safeUsername = preg_replace('/[^a-zA-Z0-9]/', '', $username);
        $safeUsername = substr($safeUsername, 0, 15);
        $email = $safeUsername . rand(100, 999) . "@gmail.com";
        
        return [
            'phone' => $phone,
            'email' => $email
        ];
    }
    
    public static function formatNumber($num) {
        if (!is_numeric($num) || $num == 0) {
            return 'N/A';
        }
        
        if ($num >= 1000000) {
            return number_format($num / 1000000, 1) . 'M';
        } elseif ($num >= 1000) {
            return number_format($num / 1000, 1) . 'K';
        }
        
        return (string)$num;
    }
    
    public static function buildJoinKeyboard() {
        $data = Storage::loadData();
        $keyboard = [];
        $joinConfig = $data['join_config'];
        $buttonNames = $joinConfig['button_names'];
        
        for ($i = 1; $i <= 3; $i++) {
            $inviteKey = "invite_$i";
            if (isset($data['channels'][$inviteKey]) && $data['channels'][$inviteKey]) {
                $btnText = $buttonNames[$i] ?? "📢 JOIN CHANNEL $i";
                $keyboard[] = [[
                    'text' => $btnText,
                    'url' => $data['channels'][$inviteKey]
                ]];
            }
        }
        
        $keyboard[] = [[
            'text' => '✅ CHECK JOINED',
            'callback_data' => 'check_joined'
        ]];
        
        return json_encode(['inline_keyboard' => $keyboard]);
    }
    
    public static function isOwner($userId) {
        return $userId == OWNER_ID;
    }
    
    public static function truncateBio($bio, $length = 150) {
        if (strlen($bio) <= $length) {
            return $bio;
        }
        return substr($bio, 0, $length) . '...';
    }
}
?>