<?php
// =============================================
// WATTZ CHAT - Complete Private Messaging App
// with Voice & Video Calls
// =============================================

// Configuration
define('SUPABASE_URL', 'https://jccbtmwcvqooeppfbors.supabase.co');
define('SUPABASE_ANON_KEY', 'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJpc3MiOiJzdXBhYmFzZSIsInJlZiI6ImpjY2J0bXdjdnFvb2VwcGZib3JzIiwicm9sZSI6ImFub24iLCJpYXQiOjE3NzExNjg3MDIsImV4cCI6MjA4Njc0NDcwMn0.mEoZFGpRGVRyrv29QLldCo9_ba65VnlNHr0677xgvtI');
define('SUPABASE_SERVICE_KEY', 'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJpc3MiOiJzdXBhYmFzZSIsInJlZiI6ImpjY2J0bXdjdnFvb2VwcGZib3JzIiwicm9sZSI6InNlcnZpY2Vfcm9sZSIsImlhdCI6MTc3MTE2ODcwMiwiZXhwIjoyMDg2NzQ0NzAyfQ.dBYorOGX2JJFvXcYUgVwpXso7DUFgDmpOV0zYq1vxAs');
define('DEFAULT_USER_ID', '00000000-0000-0000-0000-000000000001');
define('DEFAULT_USERNAME', 'Me');
define('DEFAULT_AVATAR', 'https://via.placeholder.com/100');

// API Function
function api_request($endpoint, $method = 'GET', $data = null, $useService = false) {
    $url = SUPABASE_URL . '/rest/v1/' . $endpoint;
    $key = $useService ? SUPABASE_SERVICE_KEY : SUPABASE_ANON_KEY;
    
    $headers = [
        'apikey: ' . $key,
        'Authorization: Bearer ' . $key,
        'Content-Type: application/json',
        'Prefer: return=representation'
    ];
    
    $ch = curl_init();
    
    if ($method === 'GET' && is_array($data)) {
        $params = [];
        foreach ($data as $key => $value) {
            $params[] = $key . '=' . urlencode($value);
        }
        if (!empty($params)) {
            $url .= '?' . implode('&', $params);
        }
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_HTTPGET, true);
    } else {
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
        if ($data) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        }
    }
    
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    return [
        'data' => json_decode($response, true),
        'code' => $httpCode,
        'raw' => $response
    ];
}

// Upload Function
function upload_file($file, $bucket) {
    $fileName = uniqid() . '_' . basename($file['name']);
    $fileContent = file_get_contents($file['tmp_name']);
    
    $url = SUPABASE_URL . "/storage/v1/object/{$bucket}/{$fileName}";
    
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Bearer ' . SUPABASE_SERVICE_KEY,
        'Content-Type: ' . $file['type']
    ]);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $fileContent);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode === 200 || $httpCode === 201) {
        return SUPABASE_URL . "/storage/v1/object/public/{$bucket}/{$fileName}";
    }
    
    return false;
}

// Handle actions
$action = $_GET['action'] ?? 'chats';
$chat_id = $_GET['chat'] ?? null;
$call_type = $_GET['call'] ?? null;
$call_with = $_GET['with'] ?? null;

// Handle API endpoints
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    
    // Send message
    $data = json_decode(file_get_contents('php://input'), true);
    
    if (isset($data['message'])) {
        $message_data = [
            'sender_id' => DEFAULT_USER_ID,
            'receiver_id' => $data['receiver_id'],
            'content' => $data['message'],
            'type' => 'text',
            'created_at' => date('Y-m-d H:i:s')
        ];
        $result = api_request('messages', 'POST', $message_data, true);
        echo json_encode(['success' => $result['code'] === 201]);
        exit;
    }
    
    // Upload media message
    if (isset($_FILES['media'])) {
        $url = upload_file($_FILES['media'], 'chat_media');
        if ($url) {
            $message_data = [
                'sender_id' => DEFAULT_USER_ID,
                'receiver_id' => $_POST['receiver_id'],
                'media_url' => $url,
                'type' => 'image',
                'created_at' => date('Y-m-d H:i:s')
            ];
            $result = api_request('messages', 'POST', $message_data, true);
            echo json_encode(['success' => $result['code'] === 201, 'url' => $url]);
        } else {
            echo json_encode(['success' => false]);
        }
        exit;
    }
    
    // Upload voice message
    if (isset($_FILES['voice'])) {
        $url = upload_file($_FILES['voice'], 'voice_notes');
        if ($url) {
            $message_data = [
                'sender_id' => DEFAULT_USER_ID,
                'receiver_id' => $_POST['receiver_id'],
                'media_url' => $url,
                'type' => 'voice',
                'created_at' => date('Y-m-d H:i:s')
            ];
            $result = api_request('messages', 'POST', $message_data, true);
            echo json_encode(['success' => $result['code'] === 201, 'url' => $url]);
        } else {
            echo json_encode(['success' => false]);
        }
        exit;
    }
    
    // Delete message
    if (isset($data['delete_message'])) {
        $result = api_request('messages', 'PATCH', [
            'is_deleted' => true
        ], true);
        echo json_encode(['success' => true]);
        exit;
    }
    
    // Add reaction
    if (isset($data['react'])) {
        $msg_result = api_request('messages', 'GET', [
            'id' => 'eq.' . $data['message_id'],
            'select' => 'reactions'
        ]);
        
        $reactions = $msg_result['data'][0]['reactions'] ?? [];
        $reactions[] = ['emoji' => $data['emoji'], 'user_id' => DEFAULT_USER_ID];
        
        $result = api_request('messages', 'PATCH', [
            'reactions' => $reactions
        ], true);
        echo json_encode(['success' => true]);
        exit;
    }
}

// Get all chats
$chats_data = api_request('messages', 'GET', [
    'select' => '*,sender:sender_id(username),receiver:receiver_id(username)',
    'or' => '(sender_id.eq.' . DEFAULT_USER_ID . ',receiver_id.eq.' . DEFAULT_USER_ID . ')',
    'order' => 'created_at.desc'
]);
$all_messages = $chats_data['data'] ?? [];

// Group by chat partner
$chat_partners = [];
foreach ($all_messages as $msg) {
    $partner_id = $msg['sender_id'] === DEFAULT_USER_ID ? $msg['receiver_id'] : $msg['sender_id'];
    $partner_name = $msg['sender_id'] === DEFAULT_USER_ID ? 
        ($msg['receiver']['username'] ?? 'User') : 
        ($msg['sender']['username'] ?? 'User');
    
    if (!isset($chat_partners[$partner_id])) {
        $chat_partners[$partner_id] = [
            'id' => $partner_id,
            'name' => $partner_name,
            'last_message' => $msg['content'] ?? ($msg['type'] === 'image' ? '📷 Photo' : ($msg['type'] === 'voice' ? '🎤 Voice' : '')),
            'last_time' => $msg['created_at'],
            'type' => $msg['type']
        ];
    }
}

// Get messages for selected chat
$messages = [];
if ($chat_id) {
    $messages_data = api_request('messages', 'GET', [
        'select' => '*',
        'or' => '(and(sender_id.eq.' . DEFAULT_USER_ID . ',receiver_id.eq.' . $chat_id . '),and(sender_id.eq.' . $chat_id . ',receiver_id.eq.' . DEFAULT_USER_ID . '))',
        'is_deleted' => 'eq.false',
        'order' => 'created_at.asc'
    ]);
    $messages = $messages_data['data'] ?? [];
}

// Get all users for new chat
$users_data = api_request('profiles', 'GET', [
    'select' => 'id,username,avatar_url',
    'id' => 'neq.' . DEFAULT_USER_ID,
    'limit' => '50'
]);
$users = $users_data['data'] ?? [];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Wattz Chat <?= $chat_id ? '• Chat' : '' ?></title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;
            background: #000;
            color: #fff;
            height: 100vh;
            overflow: hidden;
        }
        
        /* App Container */
        .app {
            display: grid;
            grid-template-columns: 350px 1fr;
            height: 100vh;
        }
        
        /* Sidebar */
        .sidebar {
            background: #000;
            border-right: 1px solid #262626;
            display: flex;
            flex-direction: column;
            overflow: hidden;
        }
        
        .sidebar-header {
            padding: 20px;
            border-bottom: 1px solid #262626;
        }
        
        .sidebar-header h1 {
            font-size: 24px;
            background: linear-gradient(45deg, #fff, #888);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            margin-bottom: 16px;
        }
        
        .new-chat-btn {
            width: 100%;
            padding: 12px;
            background: #0095f6;
            border: none;
            border-radius: 8px;
            color: #fff;
            font-weight: 600;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }
        
        .chats-list {
            flex: 1;
            overflow-y: auto;
        }
        
        .chat-item {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 16px;
            border-bottom: 1px solid #262626;
            cursor: pointer;
            transition: background 0.2s;
        }
        
        .chat-item:hover {
            background: #1a1a1a;
        }
        
        .chat-item.active {
            background: #1a1a1a;
        }
        
        .chat-avatar {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            background: linear-gradient(45deg, #f09433, #df2029);
            padding: 2px;
        }
        
        .chat-avatar img {
            width: 100%;
            height: 100%;
            border-radius: 50%;
            border: 2px solid #000;
            object-fit: cover;
        }
        
        .chat-info {
            flex: 1;
        }
        
        .chat-name {
            font-weight: 600;
            margin-bottom: 4px;
        }
        
        .chat-preview {
            color: #888;
            font-size: 14px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            max-width: 200px;
        }
        
        .chat-time {
            color: #888;
            font-size: 12px;
        }
        
        /* Chat Area */
        .chat-area {
            display: flex;
            flex-direction: column;
            height: 100vh;
            background: #000;
        }
        
        .chat-header {
            padding: 16px 20px;
            border-bottom: 1px solid #262626;
            display: flex;
            align-items: center;
            gap: 16px;
            background: rgba(0,0,0,0.9);
            backdrop-filter: blur(10px);
        }
        
        .chat-header-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            object-fit: cover;
        }
        
        .chat-header-info {
            flex: 1;
        }
        
        .chat-header-name {
            font-weight: 600;
            font-size: 16px;
        }
        
        .chat-header-status {
            color: #888;
            font-size: 13px;
        }
        
        .chat-header-actions {
            display: flex;
            gap: 20px;
        }
        
        .chat-header-actions i {
            font-size: 22px;
            cursor: pointer;
            color: #fff;
        }
        
        /* Messages */
        .messages-container {
            flex: 1;
            overflow-y: auto;
            padding: 20px;
            display: flex;
            flex-direction: column;
            gap: 8px;
            background: #000;
        }
        
        .message {
            max-width: 65%;
            padding: 12px 16px;
            border-radius: 18px;
            position: relative;
            word-wrap: break-word;
            animation: fadeIn 0.3s;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .message.sent {
            align-self: flex-end;
            background: #0095f6;
            border-bottom-right-radius: 4px;
        }
        
        .message.received {
            align-self: flex-start;
            background: #262626;
            border-bottom-left-radius: 4px;
        }
        
        .message-image {
            max-width: 250px;
            border-radius: 12px;
            cursor: pointer;
        }
        
        .message-voice {
            display: flex;
            align-items: center;
            gap: 12px;
            min-width: 200px;
        }
        
        .voice-play-btn {
            width: 36px;
            height: 36px;
            border-radius: 50%;
            background: rgba(255,255,255,0.2);
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
        }
        
        .voice-wave {
            flex: 1;
            height: 30px;
            background: linear-gradient(90deg, #fff 0%, #888 100%);
            mask: linear-gradient(90deg, transparent 0%, #fff 20%, #fff 80%, transparent 100%);
        }
        
        .message-time {
            font-size: 11px;
            color: rgba(255,255,255,0.6);
            margin-top: 4px;
            display: flex;
            align-items: center;
            gap: 4px;
        }
        
        .message-reactions {
            position: absolute;
            bottom: -20px;
            right: 0;
            background: #1a1a1a;
            border: 1px solid #333;
            border-radius: 20px;
            padding: 4px 8px;
            font-size: 12px;
            display: flex;
            gap: 4px;
        }
        
        /* Input Area */
        .input-area {
            padding: 16px 20px;
            border-top: 1px solid #262626;
            background: #000;
        }
        
        .input-wrapper {
            display: flex;
            align-items: center;
            gap: 12px;
            background: #1a1a1a;
            border-radius: 24px;
            padding: 8px 16px;
        }
        
        .input-wrapper i {
            font-size: 22px;
            color: #888;
            cursor: pointer;
            transition: color 0.2s;
        }
        
        .input-wrapper i:hover {
            color: #0095f6;
        }
        
        .input-wrapper input {
            flex: 1;
            background: transparent;
            border: none;
            color: #fff;
            font-size: 16px;
            padding: 8px 0;
            outline: none;
        }
        
        .input-wrapper input::placeholder {
            color: #888;
        }
        
        .recording-indicator {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 12px;
            background: #1a1a1a;
            border-radius: 24px;
            color: #ff3b30;
        }
        
        .recording-dot {
            width: 10px;
            height: 10px;
            border-radius: 50%;
            background: #ff3b30;
            animation: pulse 1s infinite;
        }
        
        @keyframes pulse {
            0% { opacity: 1; transform: scale(1); }
            50% { opacity: 0.5; transform: scale(1.2); }
            100% { opacity: 1; transform: scale(1); }
        }
        
        /* Call Modal */
        .call-modal {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0,0,0,0.95);
            z-index: 2000;
            display: none;
            flex-direction: column;
            align-items: center;
            justify-content: space-between;
            padding: 60px 20px;
        }
        
        .call-modal.active {
            display: flex;
        }
        
        .call-avatar {
            width: 180px;
            height: 180px;
            border-radius: 50%;
            background: linear-gradient(45deg, #f09433, #df2029);
            padding: 3px;
            animation: pulse 2s infinite;
        }
        
        .call-avatar img {
            width: 100%;
            height: 100%;
            border-radius: 50%;
            border: 3px solid #000;
            object-fit: cover;
        }
        
        .call-status {
            font-size: 24px;
            font-weight: 600;
            margin-top: 20px;
        }
        
        .call-timer {
            font-size: 20px;
            color: #888;
            margin-top: 10px;
        }
        
        .call-video {
            width: 100%;
            height: 100%;
            object-fit: cover;
            display: none;
        }
        
        .call-video.active {
            display: block;
        }
        
        .local-video {
            position: absolute;
            top: 20px;
            right: 20px;
            width: 150px;
            height: 200px;
            border-radius: 12px;
            border: 2px solid #fff;
            object-fit: cover;
            display: none;
        }
        
        .local-video.active {
            display: block;
        }
        
        .call-controls {
            display: flex;
            gap: 30px;
            margin-top: 40px;
        }
        
        .call-btn {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            font-size: 24px;
        }
        
        .call-btn.end {
            background: #ff3b30;
        }
        
        .call-btn.accept {
            background: #34c759;
        }
        
        .call-btn.muted {
            background: #ff3b30;
        }
        
        /* Context Menu */
        .context-menu {
            position: fixed;
            background: #1a1a1a;
            border-radius: 12px;
            padding: 8px 0;
            min-width: 180px;
            box-shadow: 0 8px 24px rgba(0,0,0,0.5);
            z-index: 1500;
            display: none;
        }
        
        .context-menu.active {
            display: block;
        }
        
        .menu-item {
            padding: 12px 16px;
            display: flex;
            align-items: center;
            gap: 12px;
            cursor: pointer;
            transition: background 0.2s;
        }
        
        .menu-item:hover {
            background: #262626;
        }
        
        .menu-item.danger {
            color: #ff3b30;
        }
        
        /* Emoji Picker */
        .emoji-picker {
            position: fixed;
            bottom: 100px;
            left: 50%;
            transform: translateX(-50%);
            background: #1a1a1a;
            border-radius: 30px;
            padding: 12px;
            display: none;
            gap: 8px;
            z-index: 1600;
            border: 1px solid #333;
        }
        
        .emoji-picker.active {
            display: flex;
        }
        
        .emoji-picker span {
            font-size: 28px;
            cursor: pointer;
            padding: 4px;
            transition: transform 0.2s;
        }
        
        .emoji-picker span:hover {
            transform: scale(1.2);
        }
        
        /* New Chat Modal */
        .modal {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0,0,0,0.9);
            z-index: 2000;
            display: none;
            align-items: center;
            justify-content: center;
        }
        
        .modal.active {
            display: flex;
        }
        
        .modal-content {
            background: #1a1a1a;
            border-radius: 16px;
            width: 90%;
            max-width: 400px;
            max-height: 80vh;
            overflow: hidden;
        }
        
        .modal-header {
            padding: 20px;
            border-bottom: 1px solid #262626;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .modal-header h2 {
            font-size: 20px;
        }
        
        .modal-header i {
            font-size: 24px;
            cursor: pointer;
        }
        
        .modal-body {
            padding: 20px;
            overflow-y: auto;
            max-height: 60vh;
        }
        
        .user-item {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 12px;
            border-radius: 8px;
            cursor: pointer;
            transition: background 0.2s;
        }
        
        .user-item:hover {
            background: #262626;
        }
        
        .user-avatar {
            width: 44px;
            height: 44px;
            border-radius: 50%;
            object-fit: cover;
        }
        
        .user-name {
            font-weight: 600;
        }
        
        /* Mobile */
        @media (max-width: 768px) {
            .app {
                grid-template-columns: 1fr;
            }
            
            .sidebar {
                display: <?= $chat_id ? 'none' : 'block' ?>;
            }
            
            .chat-area {
                display: <?= $chat_id ? 'flex' : 'none' ?>;
            }
            
            .back-btn {
                display: block !important;
            }
        }
    </style>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body>
    <div class="app">
        <!-- Sidebar -->
        <div class="sidebar">
            <div class="sidebar-header">
                <h1>Wattz Chat</h1>
                <button class="new-chat-btn" onclick="openNewChatModal()">
                    <i class="fa-solid fa-plus"></i> New Chat
                </button>
            </div>
            
            <div class="chats-list">
                <?php if (empty($chat_partners)): ?>
                    <div style="text-align: center; padding: 40px; color: #888;">
                        <i class="fa-regular fa-message" style="font-size: 48px; margin-bottom: 16px;"></i>
                        <p>No chats yet</p>
                        <p style="font-size: 14px; margin-top: 8px;">Start a new conversation</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($chat_partners as $partner_id => $chat): ?>
                        <div class="chat-item <?= $chat_id === $partner_id ? 'active' : '' ?>" 
                             onclick="window.location.href='?chat=<?= $partner_id ?>'">
                            <div class="chat-avatar">
                                <img src="<?= DEFAULT_AVATAR ?>" alt="">
                            </div>
                            <div class="chat-info">
                                <div class="chat-name"><?= htmlspecialchars($chat['name']) ?></div>
                                <div class="chat-preview">
                                    <?php if ($chat['type'] === 'image'): ?>
                                        📷 Photo
                                    <?php elseif ($chat['type'] === 'voice'): ?>
                                        🎤 Voice message
                                    <?php else: ?>
                                        <?= htmlspecialchars($chat['last_message']) ?>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="chat-time"><?= date('H:i', strtotime($chat['last_time'])) ?></div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Chat Area -->
        <div class="chat-area">
            <?php if ($chat_id): 
                $partner_name = '';
                foreach ($chat_partners as $pid => $p) {
                    if ($pid === $chat_id) {
                        $partner_name = $p['name'];
                        break;
                    }
                }
            ?>
                <div class="chat-header">
                    <i class="fa-solid fa-arrow-left back-btn" onclick="window.location.href='?'" 
                       style="display: none; font-size: 20px; cursor: pointer;"></i>
                    <img src="<?= DEFAULT_AVATAR ?>" class="chat-header-avatar">
                    <div class="chat-header-info">
                        <div class="chat-header-name"><?= htmlspecialchars($partner_name) ?></div>
                        <div class="chat-header-status">Online</div>
                    </div>
                    <div class="chat-header-actions">
                        <i class="fa-solid fa-phone" onclick="startCall('audio', '<?= $chat_id ?>')"></i>
                        <i class="fa-solid fa-video" onclick="startCall('video', '<?= $chat_id ?>')"></i>
                    </div>
                </div>
                
                <div class="messages-container" id="messagesContainer">
                    <?php foreach ($messages as $msg): ?>
                        <?php 
                        $isSent = $msg['sender_id'] === DEFAULT_USER_ID;
                        $reactions = json_decode($msg['reactions'] ?? '[]', true);
                        ?>
                        <div class="message <?= $isSent ? 'sent' : 'received' ?>" 
                             data-id="<?= $msg['id'] ?>"
                             oncontextmenu="showContextMenu(event, '<?= $msg['id'] ?>', '<?= $msg['type'] ?>', '<?= $msg['media_url'] ?? '' ?>')">
                            
                            <?php if ($msg['type'] === 'image'): ?>
                                <img src="<?= $msg['media_url'] ?>" class="message-image" onclick="window.open(this.src)">
                            <?php elseif ($msg['type'] === 'voice'): ?>
                                <div class="message-voice">
                                    <div class="voice-play-btn" onclick="playVoice(this, '<?= $msg['media_url'] ?>')">
                                        <i class="fa-solid fa-play"></i>
                                    </div>
                                    <div class="voice-wave"></div>
                                    <span>0:30</span>
                                </div>
                            <?php else: ?>
                                <?= htmlspecialchars($msg['content']) ?>
                            <?php endif; ?>
                            
                            <div class="message-time">
                                <?= date('H:i', strtotime($msg['created_at'])) ?>
                                <?php if ($isSent): ?>
                                    <i class="fa-regular fa-check-circle"></i>
                                <?php endif; ?>
                            </div>
                            
                            <?php if (!empty($reactions)): ?>
                                <div class="message-reactions">
                                    <?php foreach ($reactions as $r): ?>
                                        <span><?= $r['emoji'] ?></span>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
                
                <div class="input-area">
                    <div class="input-wrapper" id="inputWrapper">
                        <i class="fa-regular fa-image" onclick="document.getElementById('mediaInput').click()"></i>
                        <i class="fa-solid fa-microphone" onclick="startVoiceRecording()" id="voiceBtn"></i>
                        <input type="text" id="messageInput" placeholder="Message..." 
                               onkeypress="if(event.key==='Enter') sendMessage()">
                        <i class="fa-regular fa-paper-plane" onclick="sendMessage()"></i>
                    </div>
                    
                    <div class="recording-indicator" id="recordingIndicator" style="display: none;">
                        <span class="recording-dot"></span>
                        <span>Recording... <span id="recordingTime">0:00</span></span>
                        <i class="fa-regular fa-circle-stop" onclick="stopVoiceRecording()" style="margin-left: auto;"></i>
                    </div>
                </div>
                
                <input type="file" id="mediaInput" accept="image/*" style="display: none" onchange="sendMedia(this)">
                
            <?php else: ?>
                <div style="display: flex; align-items: center; justify-content: center; height: 100%; color: #888; flex-direction: column; gap: 20px;">
                    <i class="fa-regular fa-message" style="font-size: 64px;"></i>
                    <h2>Your messages</h2>
                    <p style="text-align: center; max-width: 300px;">Send private photos and messages to a friend or start a voice/video call</p>
                    <button class="new-chat-btn" onclick="openNewChatModal()" style="width: auto; padding: 12px 24px;">New Message</button>
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Context Menu -->
    <div class="context-menu" id="contextMenu">
        <div class="menu-item" onclick="replyToMessage()">
            <i class="fa-regular fa-reply"></i> Reply
        </div>
        <div class="menu-item" onclick="copyMessage()">
            <i class="fa-regular fa-copy"></i> Copy
        </div>
        <div class="menu-item" onclick="openEmojiPicker()">
            <i class="fa-regular fa-face-smile"></i> React
        </div>
        <div class="menu-item" id="downloadOption" onclick="downloadMedia()">
            <i class="fa-regular fa-circle-down"></i> Download
        </div>
        <div class="menu-item danger" onclick="deleteMessage()">
            <i class="fa-regular fa-trash-can"></i> Delete
        </div>
    </div>
    
    <!-- Emoji Picker -->
    <div class="emoji-picker" id="emojiPicker">
        <span onclick="addReaction('❤️')">❤️</span>
        <span onclick="addReaction('😂')">😂</span>
        <span onclick="addReaction('😮')">😮</span>
        <span onclick="addReaction('😢')">😢</span>
        <span onclick="addReaction('👍')">👍</span>
        <span onclick="addReaction('🔥')">🔥</span>
        <span onclick="addReaction('🎉')">🎉</span>
    </div>
    
    <!-- New Chat Modal -->
    <div class="modal" id="newChatModal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>New Chat</h2>
                <i class="fa-solid fa-xmark" onclick="closeNewChatModal()"></i>
            </div>
            <div class="modal-body">
                <input type="text" placeholder="Search users..." style="width: 100%; padding: 12px; background: #262626; border: none; border-radius: 8px; color: #fff; margin-bottom: 20px;" 
                       onkeyup="searchUsers(this.value)" id="userSearch">
                
                <div id="usersList">
                    <?php foreach ($users as $user): ?>
                        <div class="user-item" onclick="startChat('<?= $user['id'] ?>')">
                            <img src="<?= $user['avatar_url'] ?? DEFAULT_AVATAR ?>" class="user-avatar">
                            <div class="user-info">
                                <div class="user-name"><?= htmlspecialchars($user['username']) ?></div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Call Modal -->
    <div class="call-modal" id="callModal">
        <video id="remoteVideo" class="call-video" autoplay playsinline></video>
        <video id="localVideo" class="local-video" autoplay playsinline muted></video>
        
        <div id="callAvatar" class="call-avatar">
            <img src="<?= DEFAULT_AVATAR ?>" alt="">
        </div>
        
        <div>
            <div class="call-status" id="callStatus">Calling...</div>
            <div class="call-timer" id="callTimer">00:00</div>
        </div>
        
        <div class="call-controls">
            <div class="call-btn" id="muteBtn" onclick="toggleMute()">
                <i class="fa-solid fa-microphone"></i>
            </div>
            <div class="call-btn" id="videoBtn" onclick="toggleVideo()">
                <i class="fa-solid fa-video"></i>
            </div>
            <div class="call-btn end" id="endCallBtn" onclick="endCall()">
                <i class="fa-solid fa-phone"></i>
            </div>
        </div>
    </div>
    
    <audio id="ringtone" loop style="display: none;">
        <source src="data:audio/mp3;base64,SUQzBAAAAAAAI1RTU0UAAAAPAAADTGF2ZjU4LjI5LjEwMAAAAAAAAAAAAAAA//tQAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAWGluZwAAAA8AAAACAAABIADAwMDAwMDAwMDAwMDAwMDAwMDAwMDAwMDAwMDAwMDAwMDAwMDAwMDAwMDAwMDAwMDAwMDAwMDAwMDAwMDAwMD//8kAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA//sQZAAB8AAAAyQAAAAAAAKAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA" type="audio/mp3">
    </audio>

    <script>
        // ==================== CONFIGURATION ====================
        const userId = '<?= DEFAULT_USER_ID ?>';
        const chatId = '<?= $chat_id ?>';
        let selectedMessageId = null;
        let selectedMessageType = null;
        let selectedMediaUrl = null;
        
        // ==================== MESSAGING ====================
        
        // Send text message
        async function sendMessage() {
            const input = document.getElementById('messageInput');
            const message = input.value.trim();
            
            if (!message || !chatId) return;
            
            input.value = '';
            
            const response = await fetch('?', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ 
                    message: message,
                    receiver_id: chatId
                })
            });
            
            const result = await response.json();
            
            if (result.success) {
                // Add message to UI
                appendMessage({
                    content: message,
                    type: 'text',
                    sender_id: userId,
                    created_at: new Date().toISOString()
                });
            }
        }
        
        // Send media
        async function sendMedia(input) {
            const file = input.files[0];
            if (!file || !chatId) return;
            
            const formData = new FormData();
            formData.append('media', file);
            formData.append('receiver_id', chatId);
            
            const response = await fetch('?', {
                method: 'POST',
                body: formData
            });
            
            const result = await response.json();
            
            if (result.success) {
                appendMessage({
                    media_url: result.url,
                    type: 'image',
                    sender_id: userId,
                    created_at: new Date().toISOString()
                });
            }
            
            input.value = '';
        }
        
        // Append message to UI
        function appendMessage(msg) {
            const container = document.getElementById('messagesContainer');
            if (!container) return;
            
            const div = document.createElement('div');
            div.className = `message ${msg.sender_id === userId ? 'sent' : 'received'}`;
            div.dataset.id = msg.id || Date.now();
            
            if (msg.type === 'image') {
                div.innerHTML = `<img src="${msg.media_url}" class="message-image" onclick="window.open(this.src)">`;
            } else if (msg.type === 'voice') {
                div.innerHTML = `
                    <div class="message-voice">
                        <div class="voice-play-btn" onclick="playVoice(this, '${msg.media_url}')">
                            <i class="fa-solid fa-play"></i>
                        </div>
                        <div class="voice-wave"></div>
                        <span>0:30</span>
                    </div>
                `;
            } else {
                div.textContent = msg.content;
            }
            
            const timeDiv = document.createElement('div');
            timeDiv.className = 'message-time';
            const date = msg.created_at ? new Date(msg.created_at) : new Date();
            timeDiv.innerHTML = `${date.getHours().toString().padStart(2,'0')}:${date.getMinutes().toString().padStart(2,'0')}`;
            if (msg.sender_id === userId) {
                timeDiv.innerHTML += ' <i class="fa-regular fa-check-circle"></i>';
            }
            div.appendChild(timeDiv);
            
            container.appendChild(div);
            container.scrollTop = container.scrollHeight;
        }
        
        // ==================== VOICE RECORDING ====================
        
        let mediaRecorder;
        let audioChunks = [];
        let recordingInterval;
        let recordingSeconds = 0;
        
        async function startVoiceRecording() {
            try {
                const stream = await navigator.mediaDevices.getUserMedia({ audio: true });
                mediaRecorder = new MediaRecorder(stream);
                
                mediaRecorder.ondataavailable = event => {
                    audioChunks.push(event.data);
                };
                
                mediaRecorder.onstop = async () => {
                    const audioBlob = new Blob(audioChunks, { type: 'audio/webm' });
                    const formData = new FormData();
                    formData.append('voice', audioBlob);
                    formData.append('receiver_id', chatId);
                    
                    const response = await fetch('?', {
                        method: 'POST',
                        body: formData
                    });
                    
                    const result = await response.json();
                    
                    if (result.success) {
                        appendMessage({
                            media_url: result.url,
                            type: 'voice',
                            sender_id: userId,
                            created_at: new Date().toISOString()
                        });
                    }
                    
                    audioChunks = [];
                    document.getElementById('recordingIndicator').style.display = 'none';
                    document.getElementById('inputWrapper').style.display = 'flex';
                    recordingSeconds = 0;
                    
                    // Stop all tracks
                    stream.getTracks().forEach(track => track.stop());
                };
                
                mediaRecorder.start();
                document.getElementById('voiceBtn').style.color = '#ff3b30';
                document.getElementById('inputWrapper').style.display = 'none';
                document.getElementById('recordingIndicator').style.display = 'flex';
                
                recordingInterval = setInterval(() => {
                    recordingSeconds++;
                    const mins = Math.floor(recordingSeconds / 60);
                    const secs = recordingSeconds % 60;
                    document.getElementById('recordingTime').textContent = 
                        `${mins}:${secs.toString().padStart(2, '0')}`;
                }, 1000);
                
            } catch (err) {
                alert('Microphone access required for voice notes');
            }
        }
        
        function stopVoiceRecording() {
            if (mediaRecorder && mediaRecorder.state !== 'inactive') {
                mediaRecorder.stop();
                clearInterval(recordingInterval);
                document.getElementById('voiceBtn').style.color = '#888';
            }
        }
        
        // Play voice message
        function playVoice(btn, url) {
            const audio = new Audio(url);
            audio.play();
            btn.innerHTML = '<i class="fa-solid fa-pause"></i>';
            
            audio.onended = () => {
                btn.innerHTML = '<i class="fa-solid fa-play"></i>';
            };
        }
        
        // ==================== CONTEXT MENU ====================
        
        function showContextMenu(event, messageId, type, mediaUrl) {
            event.preventDefault();
            
            selectedMessageId = messageId;
            selectedMessageType = type;
            selectedMediaUrl = mediaUrl;
            
            const menu = document.getElementById('contextMenu');
            menu.style.display = 'block';
            menu.style.left = event.pageX + 'px';
            menu.style.top = event.pageY + 'px';
            
            // Show/hide download option
            document.getElementById('downloadOption').style.display = 
                (type === 'image' || type === 'voice') ? 'flex' : 'none';
            
            setTimeout(() => {
                document.addEventListener('click', hideContextMenu);
            }, 100);
        }
        
        function hideContextMenu() {
            document.getElementById('contextMenu').style.display = 'none';
            document.removeEventListener('click', hideContextMenu);
        }
        
        async function deleteMessage() {
            if (!selectedMessageId) return;
            
            await fetch('?', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ delete_message: true, message_id: selectedMessageId })
            });
            
            document.querySelector(`[data-id="${selectedMessageId}"]`).remove();
            hideContextMenu();
        }
        
        function copyMessage() {
            const msgElement = document.querySelector(`[data-id="${selectedMessageId}"]`);
            const text = msgElement?.childNodes[0]?.textContent;
            if (text) {
                navigator.clipboard.writeText(text);
            }
            hideContextMenu();
        }
        
        function downloadMedia() {
            if (selectedMediaUrl) {
                window.open(selectedMediaUrl, '_blank');
            }
            hideContextMenu();
        }
        
        function openEmojiPicker() {
            const menu = document.getElementById('contextMenu');
            const picker = document.getElementById('emojiPicker');
            
            menu.style.display = 'none';
            picker.style.display = 'flex';
            picker.style.left = menu.style.left;
            picker.style.top = (parseInt(menu.style.top) - 60) + 'px';
        }
        
        async function addReaction(emoji) {
            if (!selectedMessageId) return;
            
            await fetch('?', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ 
                    react: true, 
                    message_id: selectedMessageId,
                    emoji: emoji
                })
            });
            
            hideContextMenu();
            document.getElementById('emojiPicker').style.display = 'none';
        }
        
        // ==================== WEBRTC CALLS ====================
        
        let peerConnection;
        let localStream;
        let callTimer;
        let callSeconds = 0;
        let isCallActive = false;
        
        const servers = {
            iceServers: [
                { urls: 'stun:stun.l.google.com:19302' },
                { urls: 'stun:stun1.l.google.com:19302' }
            ]
        };
        
        async function startCall(type, partnerId) {
            document.getElementById('callModal').classList.add('active');
            document.getElementById('callStatus').textContent = type === 'audio' ? 'Calling...' : 'Video calling...';
            
            if (type === 'video') {
                try {
                    localStream = await navigator.mediaDevices.getUserMedia({ video: true, audio: true });
                    document.getElementById('localVideo').srcObject = localStream;
                    document.getElementById('localVideo').classList.add('active');
                    document.getElementById('callAvatar').style.display = 'none';
                } catch (err) {
                    console.log('Video failed, using audio only');
                }
            } else {
                try {
                    localStream = await navigator.mediaDevices.getUserMedia({ audio: true });
                } catch (err) {
                    alert('Microphone access required for calls');
                    endCall();
                    return;
                }
            }
            
            // Play ringtone
            document.getElementById('ringtone').play();
            
            // Simulate call acceptance after 3 seconds (in real app, this would be via signaling)
            setTimeout(() => {
                if (isCallActive) return;
                acceptCall();
            }, 3000);
        }
        
        function acceptCall() {
            document.getElementById('ringtone').pause();
            document.getElementById('callStatus').textContent = 'Connected';
            isCallActive = true;
            
            // Start timer
            callTimer = setInterval(() => {
                callSeconds++;
                const mins = Math.floor(callSeconds / 60);
                const secs = callSeconds % 60;
                document.getElementById('callTimer').textContent = 
                    `${mins.toString().padStart(2,'0')}:${secs.toString().padStart(2,'0')}`;
            }, 1000);
        }
        
        function endCall() {
            if (localStream) {
                localStream.getTracks().forEach(track => track.stop());
            }
            if (peerConnection) {
                peerConnection.close();
            }
            
            clearInterval(callTimer);
            callSeconds = 0;
            isCallActive = false;
            
            document.getElementById('callModal').classList.remove('active');
            document.getElementById('ringtone').pause();
            document.getElementById('localVideo').classList.remove('active');
            document.getElementById('callAvatar').style.display = 'block';
            document.getElementById('callTimer').textContent = '00:00';
        }
        
        function toggleMute() {
            if (localStream) {
                const audioTrack = localStream.getAudioTracks()[0];
                if (audioTrack) {
                    audioTrack.enabled = !audioTrack.enabled;
                    document.getElementById('muteBtn').style.background = 
                        audioTrack.enabled ? '' : '#ff3b30';
                }
            }
        }
        
        function toggleVideo() {
            if (localStream) {
                const videoTrack = localStream.getVideoTracks()[0];
                if (videoTrack) {
                    videoTrack.enabled = !videoTrack.enabled;
                    document.getElementById('localVideo').style.display = 
                        videoTrack.enabled ? 'block' : 'none';
                    document.getElementById('videoBtn').style.background = 
                        videoTrack.enabled ? '' : '#ff3b30';
                }
            }
        }
        
        // ==================== MODALS ====================
        
        function openNewChatModal() {
            document.getElementById('newChatModal').classList.add('active');
        }
        
        function closeNewChatModal() {
            document.getElementById('newChatModal').classList.remove('active');
        }
        
        function startChat(userId) {
            window.location.href = '?chat=' + userId;
        }
        
        // Search users
        function searchUsers(query) {
            // In a real app, this would search via API
            // For now, just filter the existing list
            const items = document.querySelectorAll('.user-item');
            items.forEach(item => {
                const name = item.querySelector('.user-name').textContent.toLowerCase();
                if (name.includes(query.toLowerCase())) {
                    item.style.display = 'flex';
                } else {
                    item.style.display = 'none';
                }
            });
        }
        
        // ==================== UTILITIES ====================
        
        // Scroll to bottom on load
        window.onload = function() {
            const container = document.getElementById('messagesContainer');
            if (container) {
                container.scrollTop = container.scrollHeight;
            }
        };
        
        // Auto-refresh messages every 3 seconds (simulate real-time)
        if (chatId) {
            setInterval(() => {
                location.reload();
            }, 3000);
        }
    </script>
</body>
</html>
