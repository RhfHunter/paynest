<?php
// json_db.php - JSON ডাটাবেস হ্যান্ডলার
require_once 'config.php';

class JsonDB {
    
    // ফাইল থেকে ডাটা লোড
    public static function load($type) {
        if (!isset(JSON_FILES[$type])) {
            return [];
        }
        
        $file = JSON_FILES[$type];
        if (!file_exists($file)) {
            file_put_contents($file, json_encode([]));
        }
        
        $data = file_get_contents($file);
        return json_decode($data, true) ?: [];
    }
    
    // ফাইলে ডাটা সেভ
    public static function save($type, $data) {
        if (!isset(JSON_FILES[$type])) {
            return false;
        }
        
        $file = JSON_FILES[$type];
        $json = json_encode($data, JSON_PRETTY_PRINT);
        return file_put_contents($file, $json);
    }
    
    // ইউজার ম্যানেজমেন্ট
    public static function getUser($telegram_id) {
        $users = self::load('users');
        return $users[$telegram_id] ?? null;
    }
    
    public static function saveUser($user_data) {
        $users = self::load('users');
        $telegram_id = $user_data['telegram_id'];
        $users[$telegram_id] = $user_data;
        return self::save('users', $users);
    }
    
    public static function updateUserBalance($telegram_id, $amount) {
        $users = self::load('users');
        if (isset($users[$telegram_id])) {
            $users[$telegram_id]['balance'] += $amount;
            $users[$telegram_id]['updated_at'] = date('Y-m-d H:i:s');
            self::save('users', $users);
            return $users[$telegram_id]['balance'];
        }
        return false;
    }
    
    // ইউজার স্টেট ম্যানেজমেন্ট
    public static function setUserState($telegram_id, $state, $data = []) {
        $states = self::load('user_states');
        $states[$telegram_id] = [
            'user_id' => $telegram_id,
            'state' => $state,
            'data' => $data,
            'updated_at' => date('Y-m-d H:i:s')
        ];
        return self::save('user_states', $states);
    }
    
    public static function getUserState($telegram_id) {
        $states = self::load('user_states');
        return $states[$telegram_id] ?? null;
    }
    
    public static function clearUserState($telegram_id) {
        $states = self::load('user_states');
        if (isset($states[$telegram_id])) {
            unset($states[$telegram_id]);
            return self::save('user_states', $states);
        }
        return true;
    }
    
    // চ্যালেঞ্জ ম্যানেজমেন্ট
    public static function createChallenge($challenger_id, $opponent_id, $amount) {
        $challenges = self::load('challenges');
        
        $challenge_id = 'ch_' . time() . '_' . rand(1000, 9999);
        $challenge = [
            'id' => $challenge_id,
            'challenger_id' => $challenger_id,
            'opponent_id' => $opponent_id,
            'amount' => (float)$amount,
            'status' => 'pending',
            'game_link' => '',
            'winner_id' => null,
            'challenge_message_id' => 0,
            'opponent_message_id' => 0,
            'created_at' => date('Y-m-d H:i:s')
        ];
        
        $challenges[$challenge_id] = $challenge;
        self::save('challenges', $challenges);
        
        return $challenge_id;
    }
    
    public static function getChallenge($challenge_id) {
        $challenges = self::load('challenges');
        return $challenges[$challenge_id] ?? null;
    }
    
    public static function updateChallenge($challenge_id, $data) {
        $challenges = self::load('challenges');
        if (isset($challenges[$challenge_id])) {
            $challenges[$challenge_id] = array_merge($challenges[$challenge_id], $data);
            return self::save('challenges', $challenges);
        }
        return false;
    }
    
    // গেম ম্যানেজমেন্ট
    public static function createGame($challenge_id, $player_x, $player_o) {
        $games = self::load('games');
        
        $game_id = 'game_' . time() . '_' . rand(1000, 9999);
        $game = [
            'id' => $game_id,
            'challenge_id' => $challenge_id,
            'player_x' => $player_x,
            'player_o' => $player_o,
            'current_turn' => $player_x,
            'board_state' => '---------',
            'status' => 'waiting',
            'result' => null,
            'winner_id' => null,
            'created_at' => date('Y-m-d H:i:s')
        ];
        
        $games[$game_id] = $game;
        self::save('games', $games);
        
        return $game_id;
    }
    
    public static function getGame($game_id) {
        $games = self::load('games');
        return $games[$game_id] ?? null;
    }
    
    public static function updateGame($game_id, $data) {
        $games = self::load('games');
        if (isset($games[$game_id])) {
            $games[$game_id] = array_merge($games[$game_id], $data);
            return self::save('games', $games);
        }
        return false;
    }
    
    // ট্রানজেকশন ম্যানেজমেন্ট
    public static function addTransaction($user_id, $type, $amount, $description = '', $challenge_id = null) {
        $transactions = self::load('transactions');
        
        $user = self::getUser($user_id);
        $balance_before = $user ? $user['balance'] : 0;
        $balance_after = $balance_before + $amount;
        
        $transaction_id = 'txn_' . time() . '_' . rand(1000, 9999);
        $transaction = [
            'id' => $transaction_id,
            'user_id' => $user_id,
            'type' => $type,
            'amount' => (float)$amount,
            'balance_before' => (float)$balance_before,
            'balance_after' => (float)$balance_after,
            'challenge_id' => $challenge_id,
            'description' => $description,
            'status' => 'completed',
            'created_at' => date('Y-m-d H:i:s')
        ];
        
        $transactions[$transaction_id] = $transaction;
        self::save('transactions', $transactions);
        
        // ইউজার ব্যালেন্স আপডেট
        if ($user) {
            self::updateUserBalance($user_id, $amount);
        }
        
        return $transaction_id;
    }
    
    // ইউটিলিটি ফাংশন
    public static function generateGameLink($challenge_id) {
        $token = bin2hex(random_bytes(16));
        return "https://rhfhunter.github.io/paynest/game.php?game={$token}&challenge={$challenge_id}";
    }
    
    public static function checkWin($board) {
        $winning_combinations = [
            [0, 1, 2], [3, 4, 5], [6, 7, 8], // Rows
            [0, 3, 6], [1, 4, 7], [2, 5, 8], // Columns
            [0, 4, 8], [2, 4, 6]             // Diagonals
        ];
        
        foreach ($winning_combinations as $combo) {
            if ($board[$combo[0]] != '-' && 
                $board[$combo[0]] == $board[$combo[1]] && 
                $board[$combo[1]] == $board[$combo[2]]) {
                return $board[$combo[0]];
            }
        }
        
        // ড্র চেক
        if (strpos($board, '-') === false) {
            return 'D';
        }
        
        return false;
    }
}
