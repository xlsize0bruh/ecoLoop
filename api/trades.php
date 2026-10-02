<?php
session_start();
require_once 'db.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user'])) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

$user = $_SESSION['user'];
$action = $_GET['action'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    if ($action === 'list') {
        $trades = readJson('trades.json');

        // Return trades where user is either proposer or receiver
        $myTrades = array_filter($trades, function($t) use ($user) {
            return $t['proposer_id'] === $user['id'] || $t['receiver_id'] === $user['id'];
        });

        echo json_encode(['success' => true, 'trades' => array_values($myTrades)]);
        exit;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);

    if ($action === 'propose') {
        $offeredItemId = $input['offered_item_id'] ?? '';
        $wantedItemId = $input['wanted_item_id'] ?? '';

        if (!$offeredItemId || !$wantedItemId) {
            echo json_encode(['success' => false, 'error' => 'Missing item IDs']);
            exit;
        }

        $items = readJson('items.json');
        $wantedItem = null;
        $offeredItem = null;

        foreach($items as $i) {
            if ($i['id'] === $wantedItemId) $wantedItem = $i;
            if ($i['id'] === $offeredItemId) $offeredItem = $i;
        }

        if (!$wantedItem || !$offeredItem) {
            echo json_encode(['success' => false, 'error' => 'Item not found']);
            exit;
        }

        if ($offeredItem['owner_id'] !== $user['id'] || $wantedItem['owner_id'] === $user['id'] || $wantedItem['pincode'] !== $user['pincode']) apiError('Choose your own offered item and a local item from another member.');
        if (projectLocked($offeredItemId) || projectLocked($wantedItemId)) apiError('A project has reserved or collected one of these items.');
        $trades = readJson('trades.json');

        // Neither item may already be in an active trade.
        foreach($trades as $t) {
            if ($t['status'] !== 'cancelled') {
                if ($t['offered_item_id'] === $offeredItemId || $t['wanted_item_id'] === $offeredItemId ||
                    $t['offered_item_id'] === $wantedItemId || $t['wanted_item_id'] === $wantedItemId) {
                    echo json_encode(['success' => false, 'error' => 'One of the items is already involved in an active trade.']);
                    exit;
                }
            }
        }

        // The item you OFFER must not be one you've currently lent out or have on loan.
        // (The item you WANT may be borrowed — you can agree now and trade once it's returned.)
        $borrows = readJson('borrows.json');
        foreach($borrows as $b) {
            if (in_array($b['status'], ['pending', 'accepted']) && $b['item_id'] === $offeredItemId) {
                echo json_encode(['success' => false, 'error' => 'The item you are offering is tied up in a borrow right now.']);
                exit;
            }
        }

        $newTrade = [
            'id' => uniqid(),
            'proposer_id' => $user['id'],
            'proposer_username' => $user['username'],
            'receiver_id' => $wantedItem['owner_id'],
            'receiver_username' => $wantedItem['owner_username'],
            'offered_item_id' => $offeredItem['id'],
            'offered_item_title' => $offeredItem['title'],
            'wanted_item_id' => $wantedItem['id'],
            'wanted_item_title' => $wantedItem['title'],
            'status' => 'pending', // pending, accepted, completed, cancelled
            'proposer_completed' => false,
            'receiver_completed' => false,
            'created_at' => time()
        ];

        $trades[] = $newTrade;
        writeJson('trades.json', $trades);

        echo json_encode(['success' => true, 'trade' => $newTrade]);
        exit;
    }

    if ($action === 'accept') {
        $tradeId = $input['trade_id'] ?? '';
        $trades = readJson('trades.json');

        $found = false;
        foreach ($trades as &$t) {
            if ($t['id'] === $tradeId && $t['receiver_id'] === $user['id'] && $t['status'] === 'pending') {
                $t['status'] = 'accepted';
                $found = true;
                break;
            }
        }

        if ($found) {
            writeJson('trades.json', $trades);
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false, 'error' => 'Trade not found or unauthorized']);
        }
        exit;
    }

    if ($action === 'cancel') {
        $tradeId = $input['trade_id'] ?? '';
        $trades = readJson('trades.json');

        $found = false;
        foreach ($trades as &$t) {
            if ($t['id'] === $tradeId && ($t['receiver_id'] === $user['id'] || $t['proposer_id'] === $user['id']) && $t['status'] === 'pending') {
                $t['status'] = 'cancelled';
                $found = true;
                break;
            }
        }

        if ($found) {
            writeJson('trades.json', $trades);
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false, 'error' => 'Trade not found or unauthorized']);
        }
        exit;
    }

    if ($action === 'complete') {
        $tradeId = $input['trade_id'] ?? '';
        $rating = (int)($input['rating'] ?? 0); // 1 for positive, 0 for neutral, -1 for negative

        $trades = readJson('trades.json');
        $users = readJson('users.json');

        $tradeRef = null;
        foreach ($trades as &$t) {
            if ($t['id'] === $tradeId) {
                if (!in_array($user['id'], [$t['proposer_id'],$t['receiver_id']]) || $t['status'] !== 'accepted') apiError('Only participants may complete an accepted trade.');
                $flag = $user['id'] === $t['proposer_id'] ? 'proposer_completed' : 'receiver_completed';
                if ($t[$flag]) { echo json_encode(['success'=>true]); exit; }
                if ($t['proposer_id'] === $user['id']) {
                    $t['proposer_completed'] = true;
                } else if ($t['receiver_id'] === $user['id']) {
                    $t['receiver_completed'] = true;
                }

                if ($t['proposer_completed'] && $t['receiver_completed']) {
                    $t['status'] = 'completed';
                }
                $tradeRef = $t;
                break;
            }
        }

        if (!$tradeRef) {
            echo json_encode(['success' => false, 'error' => 'Trade not found']);
            exit;
        }

        // Apply rating
        $targetUserId = ($tradeRef['proposer_id'] === $user['id']) ? $tradeRef['receiver_id'] : $tradeRef['proposer_id'];

        foreach ($users as &$u) {
            if ($u['id'] === $targetUserId) {
                if ($rating > 0) {
                    $u['positive_reviews'] = ($u['positive_reviews'] ?? 0) + 1;
                }
                if ($rating < 0) {
                    $u['negative_reviews'] = ($u['negative_reviews'] ?? 0) + 1;
                }
                break;
            }
        }

        writeJson('trades.json', $trades);
        writeJson('users.json', $users);

        echo json_encode(['success' => true]);
        exit;
    }
}

echo json_encode(['success' => false, 'error' => 'Invalid action.']);
