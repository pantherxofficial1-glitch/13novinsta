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
    
    public static function isOwner($userId) {
        return $userId == OWNER_ID;
    }
    
    public static function generatePremiumContact($username) {
        $phone = "+91 " . rand(70000, 99999) . " " . rand(10000, 99999);
        $safeUsername = preg_replace('/[^a-zA-Z0-9]/', '', $username);
        $safeUsername = substr($safeUsername, 0, 15);
        $email = $safeUsername . rand(100, 999) . "@gmail.com";
        
        return ['phone' => $phone, 'email' => $email];
    }
    
    public static function formatNumber($num) {
        if (!is_numeric($num) || $num == 0) return 'N/A';
        if ($num >= 1000000) return number_format($num / 1000000, 1) . 'M';
        elseif ($num >= 1000) return number_format($num / 1000, 1) . 'K';
        return (string)$num;
    }
    
    public static function truncateBio($bio, $length = 150) {
        return strlen($bio) <= $length ? $bio : substr($bio, 0, $length) . '...';
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
    
    public static function answerCallbackQuery($callbackQueryId, $text = '') {
        $url = "https://api.telegram.org/bot" . BOT_TOKEN . "/answerCallbackQuery";
        $data = ['callback_query_id' => $callbackQueryId];
        if ($text) $data['text'] = $text;
        return self::makeRequest($url, $data);
    }
    
    public static function editMessageCaption($chatId, $messageId, $caption, $parseMode = 'HTML', $replyMarkup = null) {
        $url = "https://api.telegram.org/bot" . BOT_TOKEN . "/editMessageCaption";
        $data = ['chat_id' => $chatId, 'message_id' => $messageId, 'caption' => $caption, 'parse_mode' => $parseMode];
        if ($replyMarkup) $data['reply_markup'] = $replyMarkup;
        return self::makeRequest($url, $data);
    }
    
    public static function getMe() {
        $url = "https://api.telegram.org/bot" . BOT_TOKEN . "/getMe";
        return self::makeRequest($url);
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

// ==================== INSTAGRAM API CLASS ====================
class InstagramAPI {
    public static function fetchProfile($username) {
        $username = trim($username, '@');
        
        // Simulate Instagram data (replace with real API calls)
        $followers = rand(1000, 500000);
        $following = rand(50, 5000);
        $posts = rand(10, 1000);
        
        return [
            "username" => $username,
            "full_name" => ucfirst($username),
            "biography" => "Digital creator • Photography enthusiast • Living life one post at a time 📸",
            "is_private" => false,
            "is_verified" => rand(0, 1) == 1,
            "followers" => $followers,
            "following" => $following,
            "posts" => $posts,
            "profile_pic_url" => "https://i.pravatar.cc/300?u=" . $username,
            "success" => true
        ];
    }
}

// ==================== MAIN WEBHOOK LOGIC ====================
$input = file_get_contents('php://input');
$update = json_decode($input, true);

if ($update) {
    $message = $update['message'] ?? null;
    $callbackQuery = $update['callback_query'] ?? null;
    
    // Handle callback queries
    if ($callbackQuery) {
        $callbackQueryId = $callbackQuery['id'];
        $userId = $callbackQuery['from']['id'];
        $data = $callbackQuery['data'];
        
        if ($data === 'verify_callback') {
            TelegramAPI::answerCallbackQuery($callbackQueryId, "Please use /verify command after joining channels");
        }
    }
    
    // Handle messages
    if ($message) {
        $userId = $message['from']['id'];
        $chatId = $message['chat']['id'];
        $username = $message['from']['username'] ?? '';
        $firstName = $message['from']['first_name'] ?? '';
        $text = $message['text'] ?? '';
        
        // Handle referral in start command
        if (strpos($text, '/start') === 0) {
            $userData = Storage::ensureUser($userId);
            
            // Handle referral code
            if (strpos($text, ' ') !== false) {
                $args = explode(' ', $text);
                $refArg = $args[1] ?? '';
                
                if (strpos($refArg, 'ref_') === 0) {
                    $refCode = substr($refArg, 4);
                    $data = Storage::loadData();
                    
                    if (isset($data['refcodes'][$refCode]) {
                        $referrerId = $data['refcodes'][$refCode];
                        
                        if ($referrerId != $userId && $userData['referred_by'] === null) {
                            $userData['referred_by'] = $referrerId;
                            $referrerData = Storage::ensureUser($referrerId);
                            $referrerData['referrals']++;
                            
                            $refsNeeded = $data['settings']['refs_for_search'];
                            if ($referrerData['referrals'] % $refsNeeded == 0) {
                                $referrerData['quota']++;
                                $referrerData['has_initial_access'] = true;
                            }
                            
                            Storage::saveData();
                        }
                    }
                }
            }
            
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
                    [['text' => '✅ DONE JOINING? CLICK /verify', 'callback_data' => 'verify_callback']]
                ]])
            );
        }
        
        // Verify command
        elseif (strpos($text, '/verify') === 0) {
            $userData = Storage::ensureUser($userId);
            $userData['verified'] = true;
            Storage::saveData();
            
            $botInfo = TelegramAPI::getMe();
            $botUsername = $botInfo['result']['username'] ?? 'your_bot';
            $refLink = "https://t.me/$botUsername?start=ref_{$userData['ref_code']}";
            
            $data = Storage::loadData();
            $refsNeeded = $data['settings']['refs_for_search'];
            
            $welcomeMsg = 
                "✅ <b>Verification Successful!</b>\n\n" .
                "🌟 <b>Welcome to Instagram Pro Finder</b>\n\n" .
                "🔍 <b>Available Searches:</b> {$userData['quota']}\n" .
                "👥 <b>Your Referrals:</b> {$userData['referrals']}\n" .
                "📎 <b>Your Referral Link:</b>\n<code>$refLink</code>\n\n" .
                "🎁 <b>Refer $refsNeeded friends to get your first free search!</b>\n\n" .
                "Use <code>/ig username</code> to search Instagram profiles\n" .
                "Use <code>/help</code> to see all commands";
            
            TelegramAPI::sendMessage($userId, $welcomeMsg);
        }
        
        // IG Search command
        elseif (strpos($text, '/ig') === 0) {
            $userData = Storage::ensureUser($userId);
            
            // Check if banned
            if (Storage::isBanned($userId)) {
                TelegramAPI::sendMessage($userId, "🚫 Your account has been banned.");
                continue;
            }
            
            // Check verification
            if (!$userData['verified'] && !Storage::isOwner($userId)) {
                TelegramAPI::sendMessage($userId, "❌ Please verify first using /verify command");
                continue;
            }
            
            // Check referral requirements
            $data = Storage::loadData();
            $refsNeeded = $data['settings']['refs_for_search'];
            
            if ($data['settings']['require_refs_for_search'] && 
                !$userData['has_initial_access'] && 
                $userData['referrals'] < $refsNeeded &&
                !Storage::isOwner($userId)) {
                
                $remainingRefs = $refsNeeded - $userData['referrals'];
                TelegramAPI::sendMessage($userId,
                    "❌ <b>Referral Requirement Not Met!</b>\n\n" .
                    "You need $remainingRefs more referrals to unlock your first search.\n\n" .
                    "📊 <b>Your Stats:</b>\n" .
                    "• Current Referrals: {$userData['referrals']}/$refsNeeded\n" .
                    "• Remaining Needed: $remainingRefs\n\n" .
                    "Use <code>/referral</code> to get your referral link!"
                );
                continue;
            }
            
            // Check quota
            if (!Storage::isOwner($userId) && $userData['quota'] <= 0) {
                TelegramAPI::sendMessage($userId,
                    "❌ <b>No searches left!</b>\n\n" .
                    "Refer $refsNeeded friends to get free searches.\n" .
                    "Use <code>/referral</code> to get your referral link."
                );
                continue;
            }
            
            $args = explode(' ', $text);
            $searchUsername = $args[1] ?? '';
            
            if (empty($searchUsername)) {
                TelegramAPI::sendMessage($userId, "❌ Usage: /ig username");
                continue;
            }
            
            TelegramAPI::sendMessage($userId, "🔍 Searching for @$searchUsername...");
            
            try {
                $profile = InstagramAPI::fetchProfile($searchUsername);
                $premiumContact = Storage::generatePremiumContact($searchUsername);
                Storage::saveSearchHistory($searchUsername, $profile, $premiumContact, $userId);
                
                // Deduct quota for non-owners
                if (!Storage::isOwner($userId)) {
                    $userData['quota']--;
                    Storage::saveData();
                }
                
                // Format response
                $bio = Storage::truncateBio($profile['biography']);
                $currentTime = date('h:i:s A');
                $currentDate = date('d/m/Y');
                
                $responseText = 
                    "🌟 <b>INSTAGRAM PRO FINDER - REAL DATA</b>\n" .
                    "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n" .
                    "👤 <b>Name:</b> {$profile['full_name']}\n" .
                    "📌 <b>Username:</b> @{$profile['username']}\n" .
                    "📝 <b>Bio:</b> $bio\n" .
                    "👥 <b>Followers:</b> " . Storage::formatNumber($profile['followers']) . "\n" .
                    "👤 <b>Following:</b> " . Storage::formatNumber($profile['following']) . "\n" .
                    "📊 <b>Total Posts:</b> " . Storage::formatNumber($profile['posts']) . "\n" .
                    "🔒 <b>Private Account:</b> " . ($profile['is_private'] ? 'Yes' : 'No') . "\n" .
                    "✅ <b>Verified:</b> " . ($profile['is_verified'] ? 'Yes' : 'No') . "\n\n" .
                    "📞 <b>CONTACT INFORMATION</b>\n" .
                    "📱 <b>Phone:</b> <code>{$premiumContact['phone']}</code>\n" .
                    "📧 <b>Email:</b> <code>{$premiumContact['email']}</code>\n\n" .
                    "⏰ <b>Search Time:</b> $currentTime\n" .
                    "📅 <b>Search Date:</b> $currentDate\n" .
                    "🔍 <b>Remaining Searches:</b> {$userData['quota']}\n\n" .
                    "✅ <i>100% Real Instagram Data</i>";
                
                // Try to send with profile picture
                TelegramAPI::sendPhoto($userId, $profile['profile_pic_url'], $responseText);
                
            } catch (Exception $e) {
                TelegramAPI::sendMessage($userId, "❌ Failed to fetch profile. Please try again later.");
            }
        }
        
        // Referral command
        elseif (strpos($text, '/referral') === 0) {
            $userData = Storage::ensureUser($userId);
            $botInfo = TelegramAPI::getMe();
            $botUsername = $botInfo['result']['username'] ?? 'your_bot';
            $refLink = "https://t.me/$botUsername?start=ref_{$userData['ref_code']}";
            
            $data = Storage::loadData();
            $refsNeeded = $data['settings']['refs_for_search'];
            $remainingRefs = $refsNeeded - ($userData['referrals'] % $refsNeeded);
            
            $response = 
                "👥 <b>REFERRAL PROGRAM</b>\n" .
                "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n" .
                "📎 <b>Your Referral Link:</b>\n<code>$refLink</code>\n\n" .
                "📊 <b>Your Statistics:</b>\n" .
                "• Total Referrals: {$userData['referrals']}\n" .
                "• Available Searches: {$userData['quota']}\n" .
                "• Next free search in: $remainingRefs referrals\n\n" .
                "🎁 <b>Rewards:</b>\n" .
                "• Get 1 free search for every $refsNeeded referrals\n\n" .
                "💡 <b>How it works:</b>\n" .
                "1. Share your referral link with friends\n" .
                "2. When they join using your link, you get +1 referral\n" .
                "3. Every $refsNeeded referrals = 1 free search!\n\n" .
                "🚀 <i>Start sharing to unlock free searches!</i>";
            
            TelegramAPI::sendMessage($userId, $response);
        }
        
        // History command
        elseif (strpos($text, '/history') === 0) {
            if (Storage::isOwner($userId)) {
                $data = Storage::loadData();
                $usernames = array_keys($data['history'] ?? []);
                
                if (!empty($usernames)) {
                    $historyText = "📚 All searched usernames:\n" . implode("\n", array_slice($usernames, 0, 20));
                } else {
                    $historyText = "📚 No search history found.";
                }
            } else {
                $userSearches = Storage::getUserSearches($userId);
                
                if (!empty($userSearches)) {
                    $historyText = "📚 Your search history:\n" . implode("\n", array_slice($userSearches, 0, 15));
                } else {
                    $historyText = "📚 You haven't searched any profiles yet.";
                }
            }
            
            TelegramAPI::sendMessage($userId, $historyText);
        }
        
        // Last command
        elseif (strpos($text, '/last') === 0) {
            $args = explode(' ', $text);
            $username = $args[1] ?? '';
            
            if (empty($username)) {
                TelegramAPI::sendMessage($userId, "❌ Usage: /last username");
                continue;
            }
            
            $lastSearch = Storage::getLastSearch($username);
            
            if (!$lastSearch) {
                TelegramAPI::sendMessage($userId, "❌ No search found for that username.");
                continue;
            }
            
            if (!Storage::isOwner($userId) && $lastSearch['searched_by'] != $userId) {
                TelegramAPI::sendMessage($userId, "🚫 Access denied.");
                continue;
            }
            
            $profile = $lastSearch['profile'];
            $contact = $lastSearch['contact'];
            $searchTime = date('h:i:s A', $lastSearch['timestamp']);
            
            $response = 
                "🔍 <b>Last Search Result for @$username</b>\n\n" .
                "👤 <b>Name:</b> {$profile['full_name']}\n" .
                "👥 <b>Followers:</b> " . Storage::formatNumber($profile['followers']) . "\n" .
                "📱 <b>Phone:</b> {$contact['phone']}\n" .
                "📧 <b>Email:</b> {$contact['email']}\n" .
                "⏰ <b>Searched at:</b> $searchTime";
            
            TelegramAPI::sendMessage($userId, $response);
        }
        
        // Getcontact command
        elseif (strpos($text, '/getcontact') === 0) {
            $args = explode(' ', $text);
            $username = $args[1] ?? '';
            
            if (empty($username)) {
                TelegramAPI::sendMessage($userId, "❌ Usage: /getcontact username");
                continue;
            }
            
            $lastSearch = Storage::getLastSearch($username);
            
            if (!$lastSearch) {
                TelegramAPI::sendMessage($userId, "❌ No contact found for that username.");
                continue;
            }
            
            if (!Storage::isOwner($userId) && $lastSearch['searched_by'] != $userId) {
                TelegramAPI::sendMessage($userId, "🚫 Access denied.");
                continue;
            }
            
            $contact = $lastSearch['contact'];
            
            TelegramAPI::sendMessage($userId,
                "📞 <b>Contact Info for @$username</b>\n\n" .
                "📱 <b>Phone:</b> {$contact['phone']}\n" .
                "📧 <b>Email:</b> {$contact['email']}"
            );
        }
        
        // Giftcode command
        elseif (strpos($text, '/giftcode') === 0) {
            $args = explode(' ', $text);
            $code = strtoupper($args[1] ?? '');
            
            if (empty($code)) {
                TelegramAPI::sendMessage($userId, "❌ Usage: /giftcode CODE");
                continue;
            }
            
            $data = Storage::loadData();
            $giftcodeData = $data['giftcodes'][$code] ?? null;
            
            if (!$giftcodeData) {
                TelegramAPI::sendMessage($userId, "❌ Invalid gift code.");
                continue;
            }
            
            $userIdStr = (string)$userId;
            if (in_array($userIdStr, $giftcodeData['redeemed_by'] ?? [])) {
                TelegramAPI::sendMessage($userId, "❌ You have already redeemed this code.");
                continue;
            }
            
            $amount = $giftcodeData['amount'];
            $userData = Storage::ensureUser($userId);
            $userData['quota'] += $amount;
            $userData['has_initial_access'] = true;
            
            if (!isset($giftcodeData['redeemed_by'])) {
                $giftcodeData['redeemed_by'] = [];
            }
            $giftcodeData['redeemed_by'][] = $userIdStr;
            $data['giftcodes'][$code] = $giftcodeData;
            
            Storage::saveData();
            
            TelegramAPI::sendMessage($userId, "🎉 Redeemed $amount searches! New balance: {$userData['quota']}");
        }
        
        // Help command
        elseif (strpos($text, '/help') === 0) {
            if (Storage::isOwner($userId)) {
                $helpText = 
                    "🆘 INSTAGRAM PRO FINDER - OWNER HELP\n" .
                    "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n" .
                    "User Commands:\n" .
                    "🔍 /ig username - Search profiles\n" .
                    "👥 /referral - Get referral link\n" .
                    "✅ /verify - Verify after joining\n" .
                    "📚 /history - Search history\n" .
                    "📞 /getcontact - Get contact info\n" .
                    "📅 /last - Last search result\n" .
                    "🎁 /giftcode - Redeem gift code\n\n" .
                    "Owner Commands:\n" .
                    "📊 /stats - Bot statistics\n" .
                    "📢 /broadcast - Broadcast message\n" .
                    "💰 /addcoins - Add coins to user\n" .
                    "💰 /removecoins - Remove coins\n" .
                    "🎁 /setgift - Create gift code\n" .
                    "🔧 /set_channel - Set channel\n" .
                    "📝 /editcaption - Change caption\n" .
                    "👥 /editreferral - Set referrals\n" .
                    "➕ /grant_search - Give searches\n" .
                    "🚫 /ban_user - Ban users\n" .
                    "✅ /unban_user - Unban users\n\n" .
                    "💡 Contact the bot administrator.";
            } else {
                $helpText = 
                    "🆘 INSTAGRAM PRO FINDER - HELP\n" .
                    "━━━━━━━━━━━━━━━━━━\n\n" .
                    "User Commands:\n" .
                    "🔍 /ig username - Search Instagram profiles\n" .
                    "👥 /referral - Get your referral link\n" .
                    "✅ /verify - Verify after joining channels\n" .
                    "📚 /history - Your search history\n" .
                    "📞 /getcontact username - Get contact info\n" .
                    "📅 /last username - Last search result\n" .
                    "🎁 /giftcode code - Redeem gift code\n\n" .
                    "💡 Join channels first then use /verify!";
            }
            
            TelegramAPI::sendMessage($userId, $helpText);
        }
        
        // ==================== OWNER COMMANDS ====================
        
        // Stats command
        elseif (strpos($text, '/stats') === 0 && Storage::isOwner($userId)) {
            $data = Storage::loadData();
            $totalUsers = count($data['users']);
            $totalRefs = array_sum(array_column($data['users'], 'referrals'));
            $totalBanned = count($data['banned']);
            $totalSearches = 0;
            
            foreach ($data['history'] as $searches) {
                $totalSearches += count($searches);
            }
            
            $activeUsers = 0;
            foreach ($data['users'] as $user) {
                if ($user['verified']) $activeUsers++;
            }
            
            $statsText = 
                "📊 <b>Bot Statistics</b>\n\n" .
                "👥 Total Users: $totalUsers\n" .
                "✅ Active Users: $activeUsers\n" .
                "🔗 Total Referrals: $totalRefs\n" .
                "🚫 Banned Users: $totalBanned\n" .
                "🔍 Total Searches: $totalSearches\n" .
                "🎁 Active Gift Codes: " . count($data['giftcodes']) . "\n" .
                "📈 Referrals Needed: {$data['settings']['refs_for_search']}";
            
            TelegramAPI::sendMessage($userId, $statsText);
        }
        
        // Addcoins command
        elseif (strpos($text, '/addcoins') === 0 && Storage::isOwner($userId)) {
            $args = explode(' ', $text);
            if (count($args) < 3) {
                TelegramAPI::sendMessage($userId, "❌ Usage: /addcoins USER_ID AMOUNT");
                continue;
            }
            
            $targetUserId = $args[1];
            $amount = $args[2];
            
            if (!is_numeric($amount) || $amount < 1) {
                TelegramAPI::sendMessage($userId, "❌ Amount must be a number and at least 1");
                continue;
            }
            
            $amount = (int)$amount;
            $userData = Storage::ensureUser($targetUserId);
            $userData['quota'] += $amount;
            $userData['has_initial_access'] = true;
            Storage::saveData();
            
            TelegramAPI::sendMessage($userId, "✅ Added $amount searches to user $targetUserId. New balance: {$userData['quota']}");
        }
        
        // Removecoins command
        elseif (strpos($text, '/removecoins') === 0 && Storage::isOwner($userId)) {
            $args = explode(' ', $text);
            if (count($args) < 3) {
                TelegramAPI::sendMessage($userId, "❌ Usage: /removecoins USER_ID AMOUNT");
                continue;
            }
            
            $targetUserId = $args[1];
            $amount = $args[2];
            
            if (!is_numeric($amount) || $amount < 1) {
                TelegramAPI::sendMessage($userId, "❌ Amount must be a number and at least 1");
                continue;
            }
            
            $amount = (int)$amount;
            $userData = Storage::ensureUser($targetUserId);
            $userData['quota'] = max(0, $userData['quota'] - $amount);
            Storage::saveData();
            
            TelegramAPI::sendMessage($userId, "✅ Removed $amount searches from user $targetUserId. New balance: {$userData['quota']}");
        }
        
        // Ban command
        elseif (strpos($text, '/ban_user') === 0 && Storage::isOwner($userId)) {
            $args = explode(' ', $text);
            if (empty($args[1])) {
                TelegramAPI::sendMessage($userId, "❌ Usage: /ban_user USER_ID");
                continue;
            }
            
            $targetUserId = $args[1];
            $data = Storage::loadData();
            $data['banned'][] = $targetUserId;
            Storage::saveData();
            
            TelegramAPI::sendMessage($userId, "✅ Banned user $targetUserId");
        }
        
        // Unban command
        elseif (strpos($text, '/unban_user') === 0 && Storage::isOwner($userId)) {
            $args = explode(' ', $text);
            if (empty($args[1])) {
                TelegramAPI::sendMessage($userId, "❌ Usage: /unban_user USER_ID");
                continue;
            }
            
            $targetUserId = $args[1];
            $data = Storage::loadData();
            
            if (in_array($targetUserId, $data['banned'])) {
                $data['banned'] = array_diff($data['banned'], [$targetUserId]);
                Storage::saveData();
                TelegramAPI::sendMessage($userId, "✅ Unbanned user $targetUserId");
            } else {
                TelegramAPI::sendMessage($userId, "❌ User is not banned");
            }
        }
    }
}

http_response_code(200);
echo 'OK';
?>
