<?php
require_once __DIR__ . '/../utils/storage.php';
require_once __DIR__ . '/../utils/helpers.php';
require_once __DIR__ . '/../utils/telegram_api.php';

class OwnerHandlers {
    public static function handleStatsCommand($userId) {
        if (!Helpers::isOwner($userId)) {
            TelegramAPI::sendMessage($userId, "🚫 <b>Owner Only Command</b>");
            return;
        }
        
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
            if ($user['verified']) {
                $activeUsers++;
            }
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
    
    public static function handleBroadcastCommand($userId, $args) {
        if (!Helpers::isOwner($userId)) {
            TelegramAPI::sendMessage($userId, "🚫 <b>Owner Only Command</b>");
            return;
        }
        
        if (empty($args)) {
            TelegramAPI::sendMessage($userId, "❌ Usage: /broadcast YOUR_MESSAGE");
            return;
        }
        
        $message = implode(' ', $args);
        $data = Storage::loadData();
        $users = array_keys($data['users']);
        $success = 0;
        $failed = 0;
        
        $progressMsg = TelegramAPI::sendMessage($userId, "📢 Broadcasting to " . count($users) . " users...");
        
        foreach ($users as $user) {
            try {
                TelegramAPI::sendMessage(
                    $user,
                    "📢 <b>Announcement from Admin</b>\n\n$message"
                );
                $success++;
            } catch (Exception $e) {
                $failed++;
            }
            usleep(100000); // 0.1 second delay
        }
        
        TelegramAPI::sendMessage($userId,
            "✅ <b>Broadcast Complete</b>\n\n" .
            "✅ Success: $success\n" .
            "❌ Failed: $failed\n" .
            "📊 Total: " . count($users)
        );
    }
    
    public static function handleAddCoinsCommand($userId, $args) {
        if (!Helpers::isOwner($userId)) {
            TelegramAPI::sendMessage($userId, "🚫 <b>Owner Only Command</b>");
            return;
        }
        
        if (count($args) < 2) {
            TelegramAPI::sendMessage($userId, "❌ Usage: /addcoins USER_ID AMOUNT");
            return;
        }
        
        $targetUserId = $args[0];
        $amount = $args[1];
        
        if (!is_numeric($amount) || $amount < 1) {
            TelegramAPI::sendMessage($userId, "❌ Amount must be a number and at least 1");
            return;
        }
        
        $amount = (int)$amount;
        $userData = Storage::ensureUser($targetUserId);
        $userData['quota'] += $amount;
        $userData['has_initial_access'] = true;
        Storage::saveData();
        
        TelegramAPI::sendMessage($userId, "✅ Added $amount searches to user $targetUserId. New balance: {$userData['quota']}");
    }
    
    // Add other owner command handlers following the same pattern...
    // removecoins, setgift, set_channel, editcaption, etc.
    
    public static function handleRemoveCoinsCommand($userId, $args) {
        if (!Helpers::isOwner($userId)) {
            return;
        }
        
        if (count($args) < 2) {
            TelegramAPI::sendMessage($userId, "❌ Usage: /removecoins USER_ID AMOUNT");
            return;
        }
        
        $targetUserId = $args[0];
        $amount = $args[1];
        
        if (!is_numeric($amount) || $amount < 1) {
            TelegramAPI::sendMessage($userId, "❌ Amount must be a number and at least 1");
            return;
        }
        
        $amount = (int)$amount;
        $userData = Storage::ensureUser($targetUserId);
        $userData['quota'] = max(0, $userData['quota'] - $amount);
        Storage::saveData();
        
        TelegramAPI::sendMessage($userId, "✅ Removed $amount searches from user $targetUserId. New balance: {$userData['quota']}");
    }
    
    public static function handleSetGiftCommand($userId, $args) {
        if (!Helpers::isOwner($userId)) {
            return;
        }
        
        if (count($args) < 2) {
            TelegramAPI::sendMessage($userId, "❌ Usage: /setgift CODE AMOUNT");
            return;
        }
        
        $code = strtoupper($args[0]);
        $amount = $args[1];
        
        if (!is_numeric($amount) || $amount < 1) {
            TelegramAPI::sendMessage($userId, "❌ Amount must be a number and at least 1");
            return;
        }
        
        $amount = (int)$amount;
        $data = Storage::loadData();
        $data['giftcodes'][$code] = [
            'amount' => $amount,
            'created_by' => (string)$userId,
            'created_at' => time(),
            'redeemed_by' => []
        ];
        Storage::saveData();
        
        TelegramAPI::sendMessage($userId, "✅ Gift code '$code' created for $amount searches");
    }
    
    public static function handleBanUserCommand($userId, $args) {
        if (!Helpers::isOwner($userId)) {
            return;
        }
        
        if (empty($args)) {
            TelegramAPI::sendMessage($userId, "❌ Usage: /ban_user USER_ID");
            return;
        }
        
        $targetUserId = $args[0];
        $data = Storage::loadData();
        $data['banned'][] = $targetUserId;
        Storage::saveData();
        
        TelegramAPI::sendMessage($userId, "✅ Banned user $targetUserId");
    }
    
    public static function handleUnbanUserCommand($userId, $args) {
        if (!Helpers::isOwner($userId)) {
            return;
        }
        
        if (empty($args)) {
            TelegramAPI::sendMessage($userId, "❌ Usage: /unban_user USER_ID");
            return;
        }
        
        $targetUserId = $args[0];
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
?>