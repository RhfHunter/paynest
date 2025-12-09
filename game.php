<?php
// game.php - গেম ইন্টারফেস
require_once 'json_db.php';

$game_token = $_GET['game'] ?? '';
$challenge_id = $_GET['challenge'] ?? '';

// চ্যালেঞ্জ লোড
$challenge = JsonDB::getChallenge($challenge_id);
if (!$challenge || $challenge['status'] != 'accepted') {
    die("❌ এই গেমটি পাওয়া যায়নি বা গ্রহণ করা হয়নি!");
}

// গেম লোড
$games = JsonDB::load('games');
$game = null;
foreach ($games as $g) {
    if ($g['challenge_id'] == $challenge_id) {
        $game = $g;
        break;
    }
}

if (!$game) {
    die("❌ গেম ডাটা পাওয়া যায়নি!");
}

// সেশন শুরু
session_start();
if (!isset($_SESSION['telegram_id'])) {
    // Telegram Login Widget এর জন্য
    ?>
    <!DOCTYPE html>
    <html>
    <head>
        <title>Tic Tac Toe - Login</title>
        <script src="https://telegram.org/js/telegram-widget.js?19" async></script>
    </head>
    <body>
        <script>
            let botUsername = '<?php echo BOT_USERNAME; ?>';
            let requestAccess = true;
            
            function onTelegramAuth(user) {
                // ইউজার আইডি সার্ভারে সেন্ড করুন
                fetch('game.php?game=<?php echo $game_token; ?>&challenge=<?php echo $challenge_id; ?>', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify({telegram_id: user.id})
                }).then(response => {
                    window.location.reload();
                });
            }
        </script>
        
        <div style="text-align: center; margin-top: 50px;">
            <h2>গেমে যোগ দিতে টেলিগ্রাম দিয়ে লগইন করুন</h2>
            <script 
                async
                src="https://telegram.org/js/telegram-widget.js?19" 
                data-telegram-login="<?php echo BOT_USERNAME; ?>" 
                data-size="large" 
                data-auth-url="game.php" 
                data-request-access="write"
            ></script>
        </div>
    </body>
    </html>
    <?php
    exit;
}

$current_user_id = $_SESSION['telegram_id'];

// শুধুমাত্র গেমের প্লেয়ারদের অনুমতি
if ($current_user_id != $game['player_x'] && $current_user_id != $game['player_o']) {
    die("🚫 এই গেমে শুধুমাত্র আমন্ত্রিত প্লেয়াররা খেলতে পারবেন!");
}

// গেম লজিক
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action == 'move') {
        $position = intval($_POST['position']);
        
        // ভ্যালিডেশন
        if ($position < 0 || $position > 8) {
            die(json_encode(['error' => 'Invalid position']));
        }
        
        if ($game['board_state'][$position] != '-') {
            die(json_encode(['error' => 'Position already taken']));
        }
        
        if ($game['current_turn'] != $current_user_id) {
            die(json_encode(['error' => 'Not your turn']));
        }
        
        // মুভ করুন
        $board = $game['board_state'];
        $symbol = ($current_user_id == $game['player_x']) ? 'X' : 'O';
        $board[$position] = $symbol;
        
        // বোর্ড আপডেট
        $game['board_state'] = $board;
        $game['current_turn'] = ($current_user_id == $game['player_x']) ? $game['player_o'] : $game['player_x'];
        
        // জয়/ড্র চেক
        $result = JsonDB::checkWin($board);
        
        if ($result == 'X' || $result == 'O') {
            $game['status'] = 'completed';
            $game['result'] = ($result == 'X') ? 'X_win' : 'O_win';
            $game['winner_id'] = ($result == 'X') ? $game['player_x'] : $game['player_o'];
            
            // চ্যালেঞ্জ আপডেট
            JsonDB::updateChallenge($challenge_id, [
                'status' => 'completed',
                'winner_id' => $game['winner_id']
            ]);
            
            // টাকা ট্রান্সফার
            $winner_id = $game['winner_id'];
            $amount = $challenge['amount'];
            
            // বিজয়ী পুরস্কার পান
            JsonDB::addTransaction($winner_id, 'win', $amount * 2, 
                "গেম জয়: {$amount} টাকা x 2", $challenge_id);
            
        } elseif ($result == 'D') {
            $game['status'] = 'completed';
            $game['result'] = 'draw';
            
            // ড্র হলে প্রত্যেকে নিজের টাকা ফেরত
            JsonDB::addTransaction($game['player_x'], 'refund', $amount, 
                "ড্র, ফেরত: {$amount} টাকা", $challenge_id);
            JsonDB::addTransaction($game['player_o'], 'refund', $amount, 
                "ড্র, ফেরত: {$amount} টাকা", $challenge_id);
        }
        
        // গেম আপডেট
        JsonDB::updateGame($game['id'], $game);
        
        echo json_encode([
            'success' => true,
            'board' => $board,
            'turn' => $game['current_turn'],
            'game_over' => ($game['status'] == 'completed'),
            'winner' => $game['winner_id'] ?? null,
            'result' => $game['result'] ?? null
        ]);
        exit;
    }
}

// HTML ইন্টারফেস
?>
<!DOCTYPE html>
<html>
<head>
    <title>Tic Tac Toe - Game</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            max-width: 500px;
            margin: 0 auto;
            padding: 20px;
            text-align: center;
        }
        .game-info {
            background: #f0f0f0;
            padding: 15px;
            border-radius: 10px;
            margin-bottom: 20px;
        }
        .board {
            display: grid;
            grid-template-columns: repeat(3, 100px);
            grid-gap: 5px;
            margin: 20px auto;
            width: 310px;
        }
        .cell {
            width: 100px;
            height: 100px;
            background: white;
            border: 2px solid #333;
            font-size: 36px;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            font-weight: bold;
        }
        .cell:hover {
            background: #f5f5f5;
        }
        .cell.x { color: #ff4757; }
        .cell.o { color: #2ed573; }
        .status {
            margin: 20px 0;
            font-size: 18px;
            font-weight: bold;
        }
        .winner {
            color: #2ed573;
            font-size: 24px;
            margin: 20px 0;
        }
        .draw {
            color: #ffa502;
            font-size: 24px;
            margin: 20px 0;
        }
        button {
            padding: 10px 20px;
            background: #3742fa;
            color: white;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 16px;
        }
        button:hover {
            background: #2f3542;
        }
    </style>
</head>
<body>
    <div class="game-info">
        <h2>🎮 Tic Tac Toe</h2>
        <p>💰 স্টেক: <b><?php echo $challenge['amount']; ?> টাকা</b></p>
        <p>🎯 আপনি: <b><?php echo ($current_user_id == $game['player_x']) ? 'X' : 'O'; ?></b></p>
    </div>
    
    <div class="status" id="status">
        <?php
        if ($game['status'] == 'completed') {
            if ($game['result'] == 'draw') {
                echo '<div class="draw">🎯 গেম ড্র হয়েছে!</div>';
                echo '<p>প্রত্যেকে নিজের টাকা ফেরত পাবেন</p>';
            } else {
                $winner_id = $game['winner_id'];
                $winner = JsonDB::getUser($winner_id);
                $winner_name = $winner['username'] ? "@" . $winner['username'] : $winner['first_name'];
                
                if ($winner_id == $current_user_id) {
                    echo '<div class="winner">🎉 আপনি জয়ী হয়েছেন! 🎉</div>';
                    echo '<p>💰 পুরস্কার: ' . ($challenge['amount'] * 2) . ' টাকা</p>';
                } else {
                    echo '<div class="winner">🏆 বিজয়ী: ' . $winner_name . '</div>';
                }
            }
        } elseif ($game['current_turn'] == $current_user_id) {
            echo '✅ আপনার পালা';
        } else {
            echo '⏳ প্রতিপক্ষের পালা...';
        }
        ?>
    </div>
    
    <div class="board" id="board">
        <?php
        $board = $game['board_state'];
        for ($i = 0; $i < 9; $i++) {
            $symbol = $board[$i];
            $class = '';
            if ($symbol == 'X') $class = 'x';
            if ($symbol == 'O') $class = 'o';
            
            echo '<div class="cell ' . $class . '" data-position="' . $i . '">' . 
                 ($symbol != '-' ? $symbol : '&nbsp;') . '</div>';
        }
        ?>
    </div>
    
    <?php if ($game['status'] != 'completed'): ?>
    <script>
        const board = document.getElementById('board');
        const statusDiv = document.getElementById('status');
        const currentUser = <?php echo $current_user_id; ?>;
        const currentTurn = <?php echo $game['current_turn']; ?>;
        const gameId = '<?php echo $game['id']; ?>';
        
        board.addEventListener('click', async (e) => {
            if (!e.target.classList.contains('cell')) return;
            
            const position = e.target.dataset.position;
            const symbol = e.target.textContent.trim();
            
            if (symbol !== '') return;
            if (currentTurn !== currentUser) {
                alert('এখন আপনার পালা নয়!');
                return;
            }
            
            try {
                const response = await fetch('game.php?game=<?php echo $game_token; ?>&challenge=<?php echo $challenge_id; ?>', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: 'action=move&position=' + position
                });
                
                const data = await response.json();
                
                if (data.error) {
                    alert(data.error);
                    return;
                }
                
                // বোর্ড আপডেট
                updateBoard(data.board);
                
                if (data.game_over) {
                    if (data.winner == currentUser) {
                        statusDiv.innerHTML = '<div class="winner">🎉 আপনি জয়ী হয়েছেন! 🎉</div>' +
                                             '<p>💰 পুরস্কার: <?php echo $challenge["amount"] * 2; ?> টাকা</p>';
                    } else if (data.winner) {
                        statusDiv.innerHTML = '<div class="winner">🏆 প্রতিপক্ষ জয়ী হয়েছেন</div>';
                    } else {
                        statusDiv.innerHTML = '<div class="draw">🎯 গেম ড্র হয়েছে!</div>' +
                                             '<p>প্রত্যেকে নিজের টাকা ফেরত পাবেন</p>';
                    }
                } else {
                    if (data.turn == currentUser) {
                        statusDiv.textContent = '✅ আপনার পালা';
                    } else {
                        statusDiv.textContent = '⏳ প্রতিপক্ষের পালা...';
                    }
                }
                
            } catch (error) {
                console.error('Error:', error);
                alert('একটি সমস্যা হয়েছে!');
            }
        });
        
        function updateBoard(boardState) {
            const cells = document.querySelectorAll('.cell');
            for (let i = 0; i < 9; i++) {
                const symbol = boardState[i];
                cells[i].textContent = symbol !== '-' ? symbol : '\u00A0';
                cells[i].className = 'cell';
                if (symbol == 'X') cells[i].classList.add('x');
                if (symbol == 'O') cells[i].classList.add('o');
            }
        }
        
        // অটো রিফ্রেশ প্রতিপক্ষের মুভের জন্য
        setInterval(async () => {
            if (currentTurn !== currentUser && <?php echo $game['status'] == 'ongoing' ? 'true' : 'false'; ?>) {
                try {
                    const response = await fetch('game.php?game=<?php echo $game_token; ?>&challenge=<?php echo $challenge_id; ?>');
                    const html = await response.text();
                    // পেজ রিফ্রেশ (সিম্পল ইমপ্লিমেন্টেশন)
                    location.reload();
                } catch (error) {
                    console.error('Refresh error:', error);
                }
            }
        }, 5000);
    </script>
    <?php endif; ?>
    
    <div style="margin-top: 30px;">
        <button onclick="location.href='https://t.me/<?php echo BOT_USERNAME; ?>'">
            📱 টেলিগ্রামে ফিরে যান
        </button>
    </div>
</body>
</html>
