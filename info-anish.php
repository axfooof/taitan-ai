<?php
// Telegram bot with persistent keyboard and loading→result edit
$botToken = '8202358132:AAG7LoOSPgcO4IgAEzR6UkAmxqMo7OOpgAg';

$WELCOME_IMAGE = 'https://i.postimg.cc/V6NR0x7p/IMG-20250916-164932-403.jpg';
$WELCOME_TEXT  = "╔══════════════════════════════════╗
║ 🔥 Welcome to — Anish Exploits 🔥 ║
╠══════════════════════════════════╣
║ Skills & Off-Exploits ➜ https://anishexploits.site
║ Developer ➜ @Cyb3rS0ldier
╚══════════════════════════════════╝";
$BACKEND_BASE  = 'https://random-remove-batch-tea.trycloudflare.com/search?mobile='; // +91XXXXXXXXXX appended

// Blocked numbers with special responses
$BLOCKED_NUMBERS = [
    '' => "😂 nikal be gandu\n❌ papa ka info chahiye\n🚫 This number is protected",
    '' => "😂 nikal be gandu\n❌ papa ka info chahiye\n🚫 This number is protected",
];

// ---------- helpers ----------
function tg($method, $params) {
    global $botToken;
    $url = "https://api.telegram.org/bot{$botToken}/{$method}";
    $ch  = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $params
    ]);
    $res = curl_exec($ch);
    curl_close($ch);
    return json_decode($res, true);
}
function html_pre($t) {
    return '<pre>'.htmlspecialchars($t, ENT_QUOTES|ENT_SUBSTITUTE, 'UTF-8').'</pre>';
}
function mainKeyboard() {
    return [
        'keyboard' => [
            [
                ['text'=>'Enter Number']
            ],
        ],
        'resize_keyboard'   => true,
        'one_time_keyboard' => false
    ];
}

// Check if number is blocked
function isBlockedNumber($phone) {
    global $BLOCKED_NUMBERS;
    
    // Clean the phone number by removing any non-digit characters except +
    $cleanPhone = preg_replace('/[^0-9+]/', '', $phone);
    
    // Check if the number (with or without country code) is in blocked list
    foreach ($BLOCKED_NUMBERS as $blockedNum => $response) {
        // Check if the number ends with the blocked number (to account for country codes)
        if (strpos($cleanPhone, $blockedNum) !== false || 
            substr($cleanPhone, -strlen($blockedNum)) === $blockedNum) {
            return $BLOCKED_NUMBERS[$blockedNum];
        }
    }
    
    return false;
}

// send loading msg, call API, then edit (or fallback to new message)
function processNumber($chatId, $phone) {
    // First check if number is blocked
    $blockedResponse = isBlockedNumber($phone);
    if ($blockedResponse !== false) {
        tg('sendMessage', [
            'chat_id' => $chatId,
            'text'    => $blockedResponse,
            'reply_markup' => json_encode(mainKeyboard())
        ]);
        return;
    }
    
    // step 1 – show loading
    $loading = tg('sendMessage', [
        'chat_id' => $chatId,
        'text'    => "⏳ Processing your number...",
        'reply_markup' => json_encode(mainKeyboard())
    ]);

    // step 2 – backend call
    $apiUrl = $GLOBALS['BACKEND_BASE'] . urlencode($phone);
    $raw    = @file_get_contents($apiUrl);
    $json   = json_decode($raw, true);
    $pretty = $json ? json_encode($json, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) : ($raw ?: 'No response');

    $text = "<b>Number received:</b> " . htmlspecialchars($phone) . "\n" . html_pre($pretty);

    // step 3 – edit the same message
    $mid = $loading['result']['message_id'] ?? null;
    if ($mid) {
        $edit = tg('editMessageText', [
            'chat_id'    => $chatId,
            'message_id' => $mid,
            'text'       => $text,
            'parse_mode' => 'HTML',
            'reply_markup' => json_encode(mainKeyboard())
        ]);
        // fallback if editing failed (e.g. error from Telegram)
        if (empty($edit['ok'])) {
            tg('sendMessage', [
                'chat_id' => $chatId,
                'text'    => $text,
                'parse_mode' => 'HTML',
                'reply_markup' => json_encode(mainKeyboard())
            ]);
        }
    } else {
        // if for some reason we didn't get message_id, just send new
        tg('sendMessage', [
            'chat_id' => $chatId,
            'text'    => $text,
            'parse_mode' => 'HTML',
            'reply_markup' => json_encode(mainKeyboard())
        ]);
    }
}

// ---------- main ----------
$update = json_decode(file_get_contents('php://input'), true);
if (!$update) { http_response_code(200); exit; }

if (!empty($update['message'])) {
    $msg   = $update['message'];
    $chat  = $msg['chat']['id'];
    $from  = $msg['from'];
    $text  = trim($msg['text'] ?? '');

    // 1️⃣ contact shared
    if (!empty($msg['contact']['phone_number'])) {
        processNumber($chat, $msg['contact']['phone_number']);
        exit;
    }

    // 2️⃣ text commands
    $low = mb_strtolower($text,'UTF-8');
    if (in_array($low, ['/start','start','back'])) {
        $name = $from['first_name'] ?? $from['username'] ?? 'User';
        tg('sendPhoto', [
            'chat_id' => $chat,
            'photo'   => $WELCOME_IMAGE,
            'caption' => sprintf($WELCOME_TEXT, htmlspecialchars($name)),
            'parse_mode' => 'HTML',
            'reply_markup' => json_encode(mainKeyboard())
        ]);
        exit;
    }

    if ($low === 'enter number') {
        tg('sendMessage', [
            'chat_id' => $chat,
            'text'    => "📞 Enter Your 10 Digit Number",
            'reply_markup' => json_encode(mainKeyboard())
        ]);
        exit;
    }
    
    // Handle Contact Developer button
    if ($low === 'contact developer') {
        tg('sendMessage', [
            'chat_id' => $chat,
            'text'    => "Contact the developer at: https://t.me/MAFIA_HACKERi",
            'reply_markup' => json_encode(mainKeyboard())
        ]);
        exit;
    }

    // 3️⃣ if the text looks like a phone number
    if (preg_match('/^\+?[0-9]{8,15}$/', $text)) {
        processNumber($chat, $text);
        exit;
    }
}

http_response_code(200);