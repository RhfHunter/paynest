<?php
// config.php - কনফিগারেশন ফাইল
define('BOT_TOKEN', '8267061467:AAE3ooMu15sD89ebEIGhxMFOpZHfTDvbrLw');
define('BOT_USERNAME', '@freehot_bdbot');
define('DATA_DIR', __DIR__ . '/data/');
define('JSON_FILES', [
    'users' => DATA_DIR . 'users.json',
    'challenges' => DATA_DIR . 'challenges.json',
    'games' => DATA_DIR . 'games.json',
    'user_states' => DATA_DIR . 'user_states.json',
    'transactions' => DATA_DIR . 'transactions.json'
]);

// ডিরেক্টরি তৈরি
if (!file_exists(DATA_DIR)) {
    mkdir(DATA_DIR, 0777, true);
}

// ডিফল্ট JSON ফাইল তৈরি
foreach (JSON_FILES as $file) {
    if (!file_exists($file)) {
        file_put_contents($file, json_encode([]));
    }
}
