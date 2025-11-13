<?php
require_once __DIR__ . '/../config.php';

class TelegramAPI {
    public static function sendMessage($chatId, $text, $parseMode = 'HTML', $replyMarkup = null) {
        $url = "https://api.telegram.org/bot" . BOT_TOKEN . "/sendMessage";
        
        $data = [
            'chat_id' => $chatId,
            'text' => $text,
            'parse_mode' => $parseMode
        ];
        
        if ($replyMarkup) {
            $data['reply_markup'] = $replyMarkup;
        }
        
        return self::makeRequest($url, $data);
    }
    
    public static function sendPhoto($chatId, $photo, $caption = '', $parseMode = 'HTML', $replyMarkup = null) {
        $url = "https://api.telegram.org/bot" . BOT_TOKEN . "/sendPhoto";
        
        $data = [
            'chat_id' => $chatId,
            'photo' => $photo,
            'caption' => $caption,
            'parse_mode' => $parseMode
        ];
        
        if ($replyMarkup) {
            $data['reply_markup'] = $replyMarkup;
        }
        
        return self::makeRequest($url, $data);
    }
    
    public static function answerCallbackQuery($callbackQueryId, $text = '') {
        $url = "https://api.telegram.org/bot" . BOT_TOKEN . "/answerCallbackQuery";
        
        $data = [
            'callback_query_id' => $callbackQueryId
        ];
        
        if ($text) {
            $data['text'] = $text;
        }
        
        return self::makeRequest($url, $data);
    }
    
    public static function editMessageCaption($chatId, $messageId, $caption, $parseMode = 'HTML', $replyMarkup = null) {
        $url = "https://api.telegram.org/bot" . BOT_TOKEN . "/editMessageCaption";
        
        $data = [
            'chat_id' => $chatId,
            'message_id' => $messageId,
            'caption' => $caption,
            'parse_mode' => $parseMode
        ];
        
        if ($replyMarkup) {
            $data['reply_markup'] = $replyMarkup;
        }
        
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
?>