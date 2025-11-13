<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/handlers/user_handlers.php';
require_once __DIR__ . '/handlers/owner_handlers.php';

// Get the input data
$input = file_get_contents('php://input');
$update = json_decode($input, true);

if (!$update) {
    exit;
}

// Extract basic information
$message = $update['message'] ?? null;
$callbackQuery = $update['callback_query'] ?? null;

if ($message) {
    $userId = $message['from']['id'];
    $chatId = $message['chat']['id'];
    $username = $message['from']['username'] ?? '';
    $firstName = $message['from']['first_name'] ?? '';
    $text = $message['text'] ?? '';
    
    // Handle commands
    if (strpos($text, '/') === 0) {
        $parts = explode(' ', $text);
        $command = strtolower($parts[0]);
        $args = array_slice($parts, 1);
        
        switch ($command) {
            case '/start':
                UserHandlers::handleStart($userId, $username, $firstName, $text);
                break;
                
            case '/ig':
                UserHandlers::handleIgCommand($userId, $username, $firstName, $args);
                break;
                
            case '/referral':
                UserHandlers::handleReferralCommand($userId);
                break;
                
            case '/history':
                UserHandlers::handleHistoryCommand($userId);
                break;
                
            case '/last':
                UserHandlers::handleLastCommand($userId, $args);
                break;
                
            case '/getcontact':
                UserHandlers::handleGetContactCommand($userId, $args);
                break;
                
            case '/giftcode':
                UserHandlers::handleGiftCodeCommand($userId, $args);
                break;
                
            case '/help':
                UserHandlers::handleHelpCommand($userId);
                break;
                
            case '/stats':
                OwnerHandlers::handleStatsCommand($userId);
                break;
                
            case '/broadcast':
                OwnerHandlers::handleBroadcastCommand($userId, $args);
                break;
                
            case '/addcoins':
                OwnerHandlers::handleAddCoinsCommand($userId, $args);
                break;
                
            case '/removecoins':
                OwnerHandlers::handleRemoveCoinsCommand($userId, $args);
                break;
                
            case '/setgift':
                OwnerHandlers::handleSetGiftCommand($userId, $args);
                break;
                
            case '/ban_user':
                OwnerHandlers::handleBanUserCommand($userId, $args);
                break;
                
            case '/unban_user':
                OwnerHandlers::handleUnbanUserCommand($userId, $args);
                break;
                
            default:
                // Unknown command
                break;
        }
    }
}

if ($callbackQuery) {
    $callbackQueryId = $callbackQuery['id'];
    $userId = $callbackQuery['from']['id'];
    $message = $callbackQuery['message'];
    $messageId = $message['message_id'];
    $chatId = $message['chat']['id'];
    $data = $callbackQuery['data'];
    
    if ($data === 'check_joined') {
        UserHandlers::handleCheckJoinedCallback($callbackQueryId, $userId, $messageId, $chatId);
    }
}

// Send OK response
http_response_code(200);
echo 'OK';
?>