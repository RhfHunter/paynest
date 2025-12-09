<?php
// bot.php - মূল টেলিগ্রাম বট
require_once 'config.php';
require_once 'json_db.php';

// টেলিগ্রাম API এর জন্য
function sendMessage($chat_id, $text, $reply_markup = null) {
    $url = "https://api.telegram.org/bot" . BOT_TOKEN . "/sendMessage";
    
    $data = [
        'chat_id' => $chat_id,
        'text' => $text,
        'parse_mode' => 'HTML'
    ];
    
    if ($reply_markup) {
        $data['reply_markup'] = $reply_markup;
    }
    
    $options = [
        'http' => [
            'method' => 'POST',
            'header' => "Content-Type: application/json\r\n",
            'content' => json_encode($data)
        ]
    ];
    
    $context = stream_context_create($options);
    $result = file_get_contents($url, false, $context);
    
    return json_decode($result, true);
}

function sendReplyKeyboard($chat_id, $text, $keyboard) {
    $reply_markup = [
        'keyboard' => $keyboard,
        'resize_keyboard' => true,
        'one_time_keyboard' => true
    ];
    
    return sendMessage($chat_id, $text, json_encode($reply_markup));
}

// ইনকামিং রিকুয়েস্ট প্রসেস
$update = json_decode(file_get_contents('php://input'), true);

if (!$update) {
    die('No update received');
}

$message = $update['message'] ?? null;
$callback_query = $update['callback_query'] ?? null;
$chat_id = $message['chat']['id'] ?? null;
$user_id = $message['from']['id'] ?? null;
$text = $message['text'] ?? '';
$reply_to_message = $message['reply_to_message'] ?? null;

// নতুন ইউজার রেজিস্টার
if (!JsonDB::getUser($user_id)) {
    $user_data = [
        'telegram_id' => $user_id,
        'username' => $message['from']['username'] ?? '',
        'first_name' => $message['from']['first_name'] ?? '',
        'last_name' => $message['from']['last_name'] ?? '',
        'balance' => 0.00,
        'created_at' => date('Y-m-d H:i:s'),
        'updated_at' => date('Y-m-d H:i:s')
    ];
    JsonDB::saveUser($user_data);
}

// কমান্ড প্রসেসিং
if (strpos($text, '/start') === 0) {
    $response = "🎮 <b>Tic Tac Toe Challenge Bot</b>\n\n";
    $response .= "স্বাগতম! এই বটের মাধ্যমে আপনি টিক ট্যাক টো গেম খেলতে পারবেন এবং টাকা জিততে পারবেন।\n\n";
    $response .= "📌 <b>ব্যবহার বিধি:</b>\n";
    $response .= "1. আমাদের গ্রুপে জয়েন করুন: @your_group\n";
    $response .= "2. গ্রুপে কাউকে চ্যালেঞ্জ করতে তার মেসেজে রিপ্লে করে /challenge লিখুন\n";
    $response .= "3. চ্যালেঞ্জ গ্রহণ করলে গেম লিংক পাবেন\n";
    $response .= "4. গেম জিতলে টাকা পাবেন\n\n";
    $response .= "💰 আপনার ব্যালেন্স: 0 টাকা\n";
    $response .= "💰 জমা দিতে /deposit লিখুন";
    
    sendMessage($chat_id, $response);

} elseif (strpos($text, '/challenge') === 0 && $reply_to_message) {
    // চ্যালেঞ্জ কমান্ড
    $opponent_id = $reply_to_message['from']['id'];
    $challenger = JsonDB::getUser($user_id);
    $opponent = JsonDB::getUser($opponent_id);
    
    if (!$opponent) {
        sendMessage($chat_id, "❌ এই ইউজার আমাদের বটে রেজিস্টার্ড নয়!");
        exit;
    }
    
    if ($user_id == $opponent_id) {
        sendMessage($chat_id, "❌ নিজেকে চ্যালেঞ্জ করতে পারবেন না!");
        exit;
    }
    
    // চ্যালেঞ্জ তৈরি
    $challenge_id = JsonDB::createChallenge($user_id, $opponent_id, 0);
    
    // ইউজার স্টেট সেট
    JsonDB::setUserState($user_id, 'waiting_for_amount', [
        'challenge_id' => $challenge_id,
        'opponent_id' => $opponent_id
    ]);
    
    $response = "⚔️ <b>চ্যালেঞ্জ তৈরি হয়েছে!</b>\n\n";
    $response .= "প্রতিপক্ষ: " . ($opponent['username'] ? "@" . $opponent['username'] : $opponent['first_name']) . "\n";
    $response .= "💰 কত টাকার চ্যালেঞ্জ করতে চান? (সংখ্যায় লিখুন)\n\n";
    $response .= "উদাহরণ: 100";
    
    sendMessage($chat_id, $response);

} elseif ($state = JsonDB::getUserState($user_id)) {
    // ইউজার স্টেট অনুযায়ী প্রসেস
    if ($state['state'] == 'waiting_for_amount') {
        $amount = floatval($text);
        
        if ($amount > 0) {
            $challenge_id = $state['data']['challenge_id'];
            $challenger = JsonDB::getUser($user_id);
            
            // ব্যালেন্স চেক
            if ($challenger['balance'] >= $amount) {
                // চ্যালেঞ্জ আপডেট
                JsonDB::updateChallenge($challenge_id, [
                    'amount' => $amount
                ]);
                
                // টাকা হোল্ড
                JsonDB::addTransaction($user_id, 'hold', -$amount, "চ্যালেঞ্জ স্টেক: {$amount} টাকা", $challenge_id);
                
                // প্রতিপক্ষকে মেসেজ
                $opponent_id = $state['data']['opponent_id'];
                $challenger_name = $challenger['username'] ? "@" . $challenger['username'] : $challenger['first_name'];
                
                $challenge_message = sendMessage($opponent_id, 
                    "⚔️ <b>আপনাকে চ্যালেঞ্জ করা হয়েছে!</b>\n\n" .
                    "চ্যালেঞ্জার: {$challenger_name}\n" .
                    "পরিমাণ: {$amount} টাকা\n\n" .
                    "✅ গ্রহণ করতে /accept_{$challenge_id} লিখুন\n" .
                    "❌ প্রত্যাখ্যান করতে /reject_{$challenge_id} লিখুন"
                );
                
                // চ্যালেঞ্জে মেসেজ আইডি সেভ
                if ($challenge_message && isset($challenge_message['result']['message_id'])) {
                    JsonDB::updateChallenge($challenge_id, [
                        'opponent_message_id' => $challenge_message['result']['message_id']
                    ]);
                }
                
                sendMessage($chat_id, "✅ চ্যালেঞ্জ সেট করা হয়েছে! প্রতিপক্ষের উত্তর অপেক্ষা করুন...");
                
                // স্টেট ক্লিয়ার
                JsonDB::clearUserState($user_id);
            } else {
                sendMessage($chat_id, "❌ আপনার পর্যাপ্ত ব্যালেন্স নেই!\n💰 আপনার ব্যালেন্স: {$challenger['balance']} টাকা");
            }
        } else {
            sendMessage($chat_id, "❌ দয়া করে বৈধ সংখ্যা লিখুন!");
        }
    }

} elseif (strpos($text, '/accept_') === 0) {
    // চ্যালেঞ্জ গ্রহণ
    $challenge_id = str_replace('/accept_', '', $text);
    $challenge = JsonDB::getChallenge($challenge_id);
    
    if (!$challenge || $challenge['opponent_id'] != $user_id) {
        sendMessage($chat_id, "❌ এই চ্যালেঞ্জ গ্রহণ করতে পারবেন না!");
        exit;
    }
    
    $opponent = JsonDB::getUser($user_id);
    
    // ব্যালেন্স চেক
    if ($opponent['balance'] >= $challenge['amount']) {
        // টাকা হোল্ড
        JsonDB::addTransaction($user_id, 'hold', -$challenge['amount'], "চ্যালেঞ্জ স্টেক: {$challenge['amount']} টাকা", $challenge_id);
        
        // গেম লিংক জেনারেট
        $game_link = JsonDB::generateGameLink($challenge_id);
        
        // চ্যালেঞ্জ আপডেট
        JsonDB::updateChallenge($challenge_id, [
            'status' => 'accepted',
            'game_link' => $game_link
        ]);
        
        // গেম তৈরি
        $game_id = JsonDB::createGame($challenge_id, $challenge['challenger_id'], $user_id);
        
        // উভয়কে মেসেজ
        $challenger_id = $challenge['challenger_id'];
        
        $game_message = "🎮 <b>গেম শুরু হয়েছে!</b>\n\n" .
                       "💰 স্টেক: {$challenge['amount']} টাকা\n" .
                       "🔗 গেম লিংক: {$game_link}\n\n" .
                       "এই লিংক শুধুমাত্র আপনারা দুইজনই এক্সেস করতে পারবেন।";
        
        sendMessage($challenger_id, $game_message);
        sendMessage($user_id, $game_message);
        
    } else {
        sendMessage($chat_id, "❌ আপনার পর্যাপ্ত ব্যালেন্স নেই!\n💰 আপনার ব্যালেন্স: {$opponent['balance']} টাকা");
    }

} elseif (strpos($text, '/reject_') === 0) {
    // চ্যালেঞ্জ প্রত্যাখ্যান
    $challenge_id = str_replace('/reject_', '', $text);
    $challenge = JsonDB::getChallenge($challenge_id);
    
    if ($challenge && $challenge['opponent_id'] == $user_id) {
        JsonDB::updateChallenge($challenge_id, [
            'status' => 'rejected'
        ]);
        
        // চ্যালেঞ্জারকে টাকা ফেরত
        JsonDB::addTransaction($challenge['challenger_id'], 'refund', 
            $challenge['amount'], "চ্যালেঞ্জ প্রত্যাখ্যান, ফেরত: {$challenge['amount']} টাকা", $challenge_id);
        
        // উভয়কে নোটিফাই
        sendMessage($challenge['challenger_id'], "❌ আপনার চ্যালেঞ্জ প্রত্যাখ্যান করা হয়েছে!");
        sendMessage($user_id, "✅ আপনি চ্যালেঞ্জ প্রত্যাখ্যান করেছেন!");
    }

} elseif (strpos($text, '/balance') === 0) {
    // ব্যালেন্স চেক
    $user = JsonDB::getUser($user_id);
    $balance = $user ? $user['balance'] : 0;
    
    sendMessage($chat_id, "💰 আপনার ব্যালেন্স: <b>{$balance} টাকা</b>");

} elseif (strpos($text, '/deposit') === 0) {
    // ডিপোজিট
    $response = "💰 <b>টাকা জমা দিন</b>\n\n";
    $response .= "টাকা জমা দিতে নিচের যেকোন একটি মাধ্যম ব্যবহার করুন:\n\n";
    $response .= "📱 <b>বিকাশ:</b> 01XXXXXXXXX\n";
    $response .= "📱 <b>নগদ:</b> 01XXXXXXXXX\n";
    $response .= "📱 <b>রকেট:</b> 01XXXXXXXXX\n\n";
    $response .= "টাকা সেন্ড করার পর ট্রানজেকশন আইডি সহ আমাদের জানান।";
    
    sendMessage($chat_id, $response);

} elseif (strpos($text, '/help') === 0) {
    // হেল্প
    $response = "📚 <b>সাহায্য</b>\n\n";
    $response .= "<b>কমান্ডস:</b>\n";
    $response .= "/start - বট শুরু করুন\n";
    $response .= "/challenge - কাউকে চ্যালেঞ্জ করুন (মেসেজ রিপ্লে করে)\n";
    $response .= "/balance - ব্যালেন্স চেক করুন\n";
    $response .= "/deposit - টাকা জমা দিন\n";
    $response .= "/help - সাহায্য দেখুন\n\n";
    $response .= "<b>চ্যালেঞ্জ গ্রহণ/প্রত্যাখ্যান:</b>\n";
    $response .= "/accept_[challenge_id] - চ্যালেঞ্জ গ্রহণ\n";
    $response .= "/reject_[challenge_id] - চ্যালেঞ্জ প্রত্যাখ্যান";
    
    sendMessage($chat_id, $response);

} elseif ($text && $chat_id == $user_id) {
    // ডিফল্ট মেসেজ
    sendMessage($chat_id, "❓ দয়া করে কোন কমান্ড ব্যবহার করুন।\nসাহায্যের জন্য /help লিখুন");
}
