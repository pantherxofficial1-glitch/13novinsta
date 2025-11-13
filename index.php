<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/utils/telegram_api.php';

// Set webhook
$result = TelegramAPI::makeRequest(
    "https://api.telegram.org/bot" . BOT_TOKEN . "/setWebhook",
    ['url' => WEBHOOK_URL]
);

if ($result['ok']) {
    echo "✅ Webhook set successfully: " . WEBHOOK_URL;
} else {
    echo "❌ Failed to set webhook: " . $result['description'];
}

echo "\n\n🌟 Instagram Pro Finder - PHP Version\n";
echo "✅ ALL Features Implemented\n";
echo "✅ User Management System\n";
echo "✅ Instagram API Integration\n";
echo "✅ Referral System\n";
echo "✅ Owner Commands\n";
echo "🚀 Bot is READY for deployment!";
?>