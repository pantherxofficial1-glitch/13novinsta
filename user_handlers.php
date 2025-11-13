<?php
require_once __DIR__ . '/../utils/storage.php';
require_once __DIR__ . '/../utils/helpers.php';
require_once __DIR__ . '/../utils/telegram_api.php';
require_once __DIR__ . '/instagram_api.php';

class UserHandlers {
    private static $userRequests = [];
    
    public static function handleStart($userId, $username, $firstName, $text) {
        $userData = Storage::ensureUser($userId);
        
        // Handle referral
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
                        
                        // Send referral notification
                        self::sendReferralNotification($referrerId, $userId, $firstName);
                    }
                }
            }
        }
        
        self::sendJoinScreen($userId);
    }
    
    public static function handleIgCommand($userId, $username, $firstName, $args) {
        // Rate limiting
        $now = time();
        if (isset(self::$userRequests[$userId]) && ($now - self::$userRequests[$userId]) < RATE_LIMIT_SECONDS) {
            TelegramAPI::sendMessage($userId, "⏳ Please wait " . RATE_LIMIT_SECONDS . " seconds between requests!");
            return;
        }
        self::$userRequests[$userId] = $now;
        
        $userData = Storage::ensureUser($userId);
        
        // Check if banned
        if (Storage::isBanned($userId)) {
            TelegramAPI::sendMessage($userId, "🚫 Your account has been banned.");
            return;
        }
        
        // Check verification
        if (!$userData['verified'] && !Helpers::isOwner($userId)) {
            TelegramAPI::sendMessage($userId, 
                "❌ <b>Please verify your membership first!</b>\n\n" .
                "Use /start and join our channels to access all features."
            );
            self::sendJoinScreen($userId);
            return;
        }
        
        // Check referral requirements
        $data = Storage::loadData();
        $refsNeeded = $data['settings']['refs_for_search'];
        
        if ($data['settings']['require_refs_for_search'] && 
            !$userData['has_initial_access'] && 
            $userData['referrals'] < $refsNeeded &&
            !Helpers::isOwner($userId)) {
            
            $remainingRefs = $refsNeeded - $userData['referrals'];
            TelegramAPI::sendMessage($userId,
                "❌ <b>Referral Requirement Not Met!</b>\n\n" .
                "You need $remainingRefs more referrals to unlock your first search.\n\n" .
                "📊 <b>Your Stats:</b>\n" .
                "• Current Referrals: {$userData['referrals']}/$refsNeeded\n" .
                "• Remaining Needed: $remainingRefs\n\n" .
                "Use <code>/referral</code> to get your referral link and share with friends!"
            );
            return;
        }
        
        // Check quota
        if (!Helpers::isOwner($userId) && $userData['quota'] <= 0) {
            TelegramAPI::sendMessage($userId,
                "❌ <b>No searches left!</b>\n\n" .
                "Refer $refsNeeded friends to get free searches.\n" .
                "Use <code>/referral</code> to get your referral link."
            );
            return;
        }
        
        if (empty($args)) {
            TelegramAPI::sendMessage($userId, "❌ Please provide username: /ig username");
            return;
        }
        
        $searchUsername = trim($args[0], '@');
        
        if (strlen($searchUsername) > 30) {
            TelegramAPI::sendMessage($userId, "❌ Username too long!");
            return;
        }
        
        $searchMsg = TelegramAPI::sendMessage($userId, "🔍 Searching for @$searchUsername...");
        
        try {
            $profile = InstagramAPI::fetchProfile($searchUsername);
            
            if (isset($profile['error'])) {
                TelegramAPI::sendMessage($userId, "❌ {$profile['error']}");
                return;
            }
            
            $premiumContact = Helpers::generatePremiumContact($searchUsername);
            Storage::saveSearchHistory($searchUsername, $profile, $premiumContact, $userId);
            
            // Deduct quota for non-owners
            if (!Helpers::isOwner($userId)) {
                $userData['quota']--;
                Storage::saveData();
            }
            
            // Format response
            $bio = Helpers::truncateBio($profile['biography'] ?? 'No bio available');
            $currentTime = date('h:i:s A');
            $currentDate = date('d/m/Y');
            
            $responseText = 
                "🌟 <b>INSTAGRAM PRO FINDER - REAL DATA</b>\n" .
                "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n" .
                "👤 <b>Name:</b> " . ($profile['full_name'] ?? 'Not set') . "\n" .
                "📌 <b>Username:</b> @" . ($profile['username'] ?? $searchUsername) . "\n" .
                "📝 <b>Bio:</b> $bio\n" .
                "👥 <b>Followers:</b> " . Helpers::formatNumber($profile['followers'] ?? 0) . "\n" .
                "👤 <b>Following:</b> " . Helpers::formatNumber($profile['following'] ?? 0) . "\n" .
                "📊 <b>Total Posts:</b> " . Helpers::formatNumber($profile['posts'] ?? 0) . "\n" .
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
            $profilePic = $profile['profile_pic_url'] ?? null;
            if ($profilePic && filter_var($profilePic, FILTER_VALIDATE_URL)) {
                TelegramAPI::sendPhoto($userId, $profilePic, $responseText);
            } else {
                TelegramAPI::sendMessage($userId, $responseText);
            }
            
        } catch (Exception $e) {
            TelegramAPI::sendMessage($userId, "❌ Failed to fetch profile. Please try again later.");
        }
    }
    
    public static function handleReferralCommand($userId) {
        $userData = Storage::ensureUser($userId);
        $data = Storage::loadData();
        
        // Get bot username for referral link
        $botInfo = TelegramAPI::getMe();
        $botUsername = $botInfo['result']['username'] ?? 'your_bot';
        
        $refLink = "https://t.me/$botUsername?start=ref_{$userData['ref_code']}";
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
    
    public static function handleHistoryCommand($userId) {
        if (Helpers::isOwner($userId)) {
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
    
    public static function handleLastCommand($userId, $args) {
        if (empty($args)) {
            TelegramAPI::sendMessage($userId, "❌ Usage: /last username");
            return;
        }
        
        $username = trim($args[0], '@');
        $lastSearch = Storage::getLastSearch($username);
        
        if (!$lastSearch) {
            TelegramAPI::sendMessage($userId, "❌ No search found for that username.");
            return;
        }
        
        if (!Helpers::isOwner($userId) && $lastSearch['searched_by'] != $userId) {
            TelegramAPI::sendMessage($userId, "🚫 Access denied.");
            return;
        }
        
        $profile = $lastSearch['profile'];
        $contact = $lastSearch['contact'];
        $searchTime = date('h:i:s A', $lastSearch['timestamp']);
        
        $response = 
            "🔍 <b>Last Search Result for @$username</b>\n\n" .
            "👤 <b>Name:</b> " . ($profile['full_name'] ?? 'N/A') . "\n" .
            "👥 <b>Followers:</b> " . ($profile['followers'] ?? 'N/A') . "\n" .
            "📱 <b>Phone:</b> {$contact['phone']}\n" .
            "📧 <b>Email:</b> {$contact['email']}\n" .
            "⏰ <b>Searched at:</b> $searchTime";
        
        TelegramAPI::sendMessage($userId, $response);
    }
    
    public static function handleGetContactCommand($userId, $args) {
        if (empty($args)) {
            TelegramAPI::sendMessage($userId, "❌ Usage: /getcontact username");
            return;
        }
        
        $username = trim($args[0], '@');
        $lastSearch = Storage::getLastSearch($username);
        
        if (!$lastSearch) {
            TelegramAPI::sendMessage($userId, "❌ No contact found for that username.");
            return;
        }
        
        if (!Helpers::isOwner($userId) && $lastSearch['searched_by'] != $userId) {
            TelegramAPI::sendMessage($userId, "🚫 Access denied.");
            return;
        }
        
        $contact = $lastSearch['contact'];
        
        TelegramAPI::sendMessage($userId,
            "📞 <b>Contact Info for @$username</b>\n\n" .
            "📱 <b>Phone:</b> {$contact['phone']}\n" .
            "📧 <b>Email:</b> {$contact['email']}"
        );
    }
    
    public static function handleGiftCodeCommand($userId, $args) {
        if (empty($args)) {
            TelegramAPI::sendMessage($userId, "❌ Usage: /giftcode CODE");
            return;
        }
        
        $code = strtoupper(trim($args[0]));
        $data = Storage::loadData();
        $giftcodeData = $data['giftcodes'][$code] ?? null;
        
        if (!$giftcodeData) {
            TelegramAPI::sendMessage($userId, "❌ Invalid gift code.");
            return;
        }
        
        $userIdStr = (string)$userId;
        if (in_array($userIdStr, $giftcodeData['redeemed_by'] ?? [])) {
            TelegramAPI::sendMessage($userId, "❌ You have already redeemed this code.");
            return;
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
    
    public static function handleHelpCommand($userId) {
        if (Helpers::isOwner($userId)) {
            $helpText = 
                "🆘 INSTAGRAM PRO FINDER - OWNER HELP\n" .
                "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n" .
                "User Commands:\n" .
                "🔍 /ig username - Search profiles\n" .
                "👥 /referral - Get referral link\n" .
                "📚 /history - Search history\n" .
                "📞 /getcontact - Get contact info\n" .
                "📅 /last - Last search result\n" .
                "🎁 /giftcode - Redeem gift code\n\n" .
                "Owner Commands:\n" .
                "📊 /stats - Bot statistics\n" .
                "📢 /broadcast - Send message to all users\n" .
                "💰 /addcoins - Add coins to user\n" .
                "💰 /removecoins - Remove coins from user\n" .
                "🎁 /setgift - Create gift code\n" .
                "🎁 /makegiftcode - Create gift code\n" .
                "🔧 /set_channel - Set channel ID\n" .
                "📝 /editcaption - Change join caption\n" .
                "🖼 /editphoto - Change join image\n" .
                "🔗 /editlink - Manage channel links\n" .
                "🔗 /addchannel - Add new channel\n" .
                "🔗 /removechannel - Remove channel\n" .
                "🔗 /viewlink - View all links\n" .
                "🏷️ /editbutton - Edit button text\n" .
                "🏷️ /editbuttonname - Edit button name\n" .
                "👥 /editreferral - Set referrals needed\n" .
                "👥 /editreferralcion - Set referrals\n" .
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
                "📚 /history - Your search history\n" .
                "📞 /getcontact username - Get contact info\n" .
                "📅 /last username - Last search result\n" .
                "🎁 /giftcode code - Redeem gift code\n\n" .
                "💡 Use /start to begin and join channels!";
        }
        
        TelegramAPI::sendMessage($userId, $helpText);
    }
    
    public static function handleCheckJoinedCallback($callbackQueryId, $userId, $messageId, $chatId) {
        TelegramAPI::answerCallbackQuery($callbackQueryId);
        
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
        
        TelegramAPI::editMessageCaption($chatId, $messageId, $welcomeMsg);
    }
    
    private static function sendJoinScreen($userId) {
        $data = Storage::loadData();
        $joinConfig = $data['join_config'];
        
        TelegramAPI::sendPhoto(
            $userId,
            $joinConfig['photo_url'],
            $joinConfig['caption'],
            'HTML',
            Helpers::buildJoinKeyboard()
        );
    }
    
    private static function sendReferralNotification($referrerId, $newUserId, $newUserName) {
        try {
            $referrerData = Storage::ensureUser($referrerId);
            $data = Storage::loadData();
            $refsNeeded = $data['settings']['refs_for_search'];
            
            $currentRefs = $referrerData['referrals'];
            $remainingRefs = $refsNeeded - ($currentRefs % $refsNeeded);
            
            $notificationText = 
                "🎉 𝗡𝗘𝗪 𝗨𝗦𝗘𝗥 𝗝𝗢𝗜𝗡𝗘𝗗!\n\n" .
                "👤 𝗨𝘀𝗲𝗿: $newUserName\n" .
                "🆔 𝗜𝗗: $newUserId\n\n" .
                "✅ 𝗥𝗘𝗙𝗘𝗥𝗥𝗔𝗟 𝗖𝗢𝗜𝗡𝗦 𝗔𝗗𝗗𝗘𝗗 𝗧𝗢 𝗦𝗘𝗔𝗥𝗖𝗛 𝗖𝗢𝗜𝗡𝗦!\n\n" .
                "📊 𝗬𝗼𝘂𝗿 𝗥𝗲𝗳𝗲𝗿𝗿𝗮𝗹 𝗦𝘁𝗮𝘁𝘀:\n" .
                "├─ 𝗧𝗼𝘁𝗮𝗹 𝗥𝗲𝗳𝗲𝗿𝗿𝗮𝗹𝘀: $currentRefs\n" .
                "├─ 𝗔𝘃𝗮𝗶𝗹𝗮𝗯𝗹𝗲 𝗦𝗲𝗮𝗿𝗰𝗵𝗲𝘀: {$referrerData['quota']}\n" .
                "└─ 𝗡𝗲𝘅𝘁 𝗳𝗿𝗲𝗲 𝘀𝗲𝗮𝗿𝗰𝗵 𝗶𝗻: $remainingRefs 𝗿𝗲𝗳𝗲𝗿𝗿𝗮𝗹𝘀\n\n" .
                "🎁 𝗞𝗲𝗲𝗽 𝘀𝗵𝗮𝗿𝗶𝗻𝗴 𝘆𝗼𝘂𝗿 𝗿𝗲𝗳𝗲𝗿𝗿𝗮𝗹 𝗹𝗶𝗻𝗸 𝘁𝗼 𝗲𝗮𝗿𝗻 𝗺𝗼𝗿𝗲 𝗳𝗿𝗲𝗲 𝘀𝗲𝗮𝗿𝗰𝗵𝗲𝘀!";
            
            TelegramAPI::sendMessage($referrerId, $notificationText);
            
            // Check if referrer earned a free search
            if ($currentRefs % $refsNeeded == 0) {
                $bonusText = 
                    "🎊 𝗖𝗢𝗡𝗚𝗥𝗔𝗧𝗨𝗟𝗔𝗧𝗜𝗢𝗡𝗦!\n\n" .
                    "🏆 𝗬𝗼𝘂'𝘃𝗲 𝗿𝗲𝗮𝗰𝗵𝗲𝗱 $currentRefs 𝗿𝗲𝗳𝗲𝗿𝗿𝗮𝗹𝘀!\n\n" .
                    "🎯 𝗙𝗥𝗘𝗘 𝗦𝗘𝗔𝗥𝗖𝗛 𝗥𝗘𝗪𝗔𝗥𝗗 𝗔𝗗𝗗𝗘𝗗!\n\n" .
                    "📈 𝗡𝗲𝘄 𝗦𝗲𝗮𝗿𝗰𝗵 𝗕𝗮𝗹𝗮𝗻𝗰𝗲: {$referrerData['quota']}\n\n" .
                    "🚀 𝗨𝘀𝗲 /ig 𝘂𝘀𝗲𝗿𝗻𝗮𝗺𝗲 𝘁𝗼 𝘀𝘁𝗮𝗿𝘁 𝘀𝗲𝗮𝗿𝗰𝗵𝗶𝗻𝗴!";
                
                TelegramAPI::sendMessage($referrerId, $bonusText);
            }
            
        } catch (Exception $e) {
            error_log("Error sending referral notification: " . $e->getMessage());
        }
    }
}
?>