<?php
// Bot Configuration
define('BOT_TOKEN', '8204849607:AAFvXFbAHJQA4sYBal6csxi4MtECRX-mXnw');
define('OWNER_ID', 7598553230);
define('DATA_FILE', 'data.json');
define('WEBHOOK_URL', 'https://yourdomain.com/instagram-bot/webhook.php');

// Instagram API Configuration
define('INSTAGRAM_TIMEOUT', 10);
define('RATE_LIMIT_SECONDS', 10);

// Default Settings
$DEFAULT_CONFIG = [
    'channels' => [
        'invite_1' => 'https://t.me/+sYQlBAPNHJlmMDBl',
        'invite_2' => 'https://t.me/+6--m3SDgHDMxNTZl'
    ],
    'join_config' => [
        'photo_url' => 'https://i.ibb.co/4Z19YVyM/image.jpg',
        'caption' => "🌟 <b>Instagram Pro Finder Premium</b>\n\nJoin our channels to unlock powerful Instagram lookup features!",
        'button_names' => [
            '1' => '📢 JOIN CHANNEL 1',
            '2' => '📢 JOIN CHANNEL 2'
        ]
    ],
    'settings' => [
        'refs_for_search' => 6,
        'default_quota' => 0,
        'require_refs_for_search' => true
    ],
    'protect' => [
        'number' => true,
        'gmail' => true,
        'ig' => true
    ]
];

// Enable error reporting for development
error_reporting(E_ALL);
ini_set('display_errors', 1);
?>