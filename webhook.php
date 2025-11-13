<?php
// ==================== CONFIG ====================
define('BOT_TOKEN', '8204849607:AAFvXFbAHJQA4sYBal6csxi4MtECRX-mXnw');
define('OWNER_ID', 7598553230);
define('DATA_FILE', __DIR__ . '/data.json');
define('INSTAGRAM_TIMEOUT', 10);
define('RATE_LIMIT_SECONDS', 10);

// ==================== STORAGE CLASS ====================
class Storage {
    private static $data = null;
    
    public static function loadData() {
        if (self::$data !== null) return self::$data;
        
        if (file_exists(DATA_FILE)) {
            self::$data = json_decode(file_get_contents(DATA_FILE), true);
        } else {
            self::$data = [
                'users' => [],
                'refcodes' => [],
                'channels' => [
                    'invite_1' => 'https://t.me/+sYQlBAPNHJlmMDBl',
                    'invite_2' => 'https://t.me/+6--m3SDgHDMxNTZl'
                ],
                'join_config' => [
                    'photo_url' => 'https://i.ibb.co/4Z19YVyM/image.jpg',
                    'caption' => "🌟 <b>Instagram Pro Finder Premium</b>\n\nJoin our channels to unlock powerful Instagram lookup features!",
                    'button_names' => ['1' => '📢 JOIN CHANNEL 1', '2' => '📢 JOIN CHANNEL 2']
                ],
                'settings' => ['refs_for_search' => 6, 'default_quota' => 0, 'require_refs_for_search' => true],
                'history' => [],
                'giftcodes' => [],
                'banned' => []
            ];
            self::saveData();
        }
        return self::$data;
    }
    
    public static function saveData() {
        file_put_contents(DATA_FILE, json_encode(self::$data, JSON_PRETTY_PRINT));
    }
    
    public static function ensureUser($userId) {
        $uid = (string)$userId;
        $data = self::loadData();
        
        if (!isset($data['users'][$uid])) {
            $refCode = substr(str_shuffle('ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789'), 0, 8);
            $data['users'][$uid] = [
                'quota' => $data['settings']['default_quota'],
                'referrals' => 0,
                'ref_code' => $refCode,
                'created_at' => time(),
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
}

// ==================== TELEGRAM API CLASS ====================
class TelegramAPI {
    public static function sendMessage($chatId, $text, $parseMode = 'HTML', $replyMarkup = null) {
        $url = "https://api.telegram.org/bot" . BOT_TOKEN . "/sendMessage";
        $data = ['chat_id' => $chatId, 'text' => $text, 'parse_mode' => $parseMode];
        if ($replyMarkup) $data['reply_markup'] = $replyMarkup;
        return self::makeRequest($url, $data);
    }
    
    public static function sendPhoto($chatId, $photo, $caption = '', $parseMode = 'HTML', $replyMarkup = null) {
        $url = "https://api.telegram.org/bot" . BOT_TOKEN . "/sendPhoto";
        $data = ['chat_id' => $chatId, 'photo' => $photo, 'caption' => $caption, 'parse_mode' => $parseMode];
        if ($replyMarkup) $data['reply_markup'] = $replyMarkup;
        return self::makeRequest($url, $data);
    }
    
    private static function makeRequest($url, $data = []) {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        $response = curl_exec($ch);
        curl_close($ch);
        return json_decode($response, true);
    }
}

// ==================== MAIN WEBHOOK LOGIC ====================
$input = file_get_contents('php://input');
$update = json_decode($input, true);

if ($update) {
    $message = $update['message'] ?? null;
    
    if ($message) {
        $userId = $message['from']['id'];
        $text = $message['text'] ?? '';
        
        if (strpos($text, '/start') === 0) {
            $userData = Storage::ensureUser($userId);
            
            // Send join screen
            $data = Storage::loadData();
            TelegramAPI::sendPhoto(
                $userId,
                $data['join_config']['photo_url'],
                $data['join_config']['caption'],
                'HTML',
                json_encode(['inline_keyboard' => [
                    [['text' => '📢 JOIN CHANNEL 1', 'url' => $data['channels']['invite_1']]],
                    [['text' => '📢 JOIN CHANNEL 2', 'url' => $data['channels']['invite_2']]],
                    [['text' => '✅ CHECK JOINED', 'callback_data' => 'check_joined']]
                ]])
            );
        }
        
        if (strpos($text, '/help') === 0) {
            TelegramAPI::sendMessage($userId, 
                "🆘 INSTAGRAM PRO FINDER - HELP\n\n" .
                "🔍 /ig username - Search profiles\n" .
                "👥 /referral - Get referral link\n" .
                "📚 /history - Search history\n" .
                "🎁 /giftcode - Redeem gift code\n\n" .
                "💡 Use /start to begin!"
            );
        }
    }
}

http_response_code(200);
echo 'OK';
?>
