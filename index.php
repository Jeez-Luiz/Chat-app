<?php
// =============================================
// WATTZ CHAT - Private Messaging with Calls
// =============================================

// Config
define('SUPABASE_URL', 'https://jccbtmwcvqooeppfbors.supabase.co');
define('SUPABASE_ANON_KEY', 'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJpc3MiOiJzdXBhYmFzZSIsInJlZiI6ImpjY2J0bXdjdnFvb2VwcGZib3JzIiwicm9sZSI6ImFub24iLCJpYXQiOjE3NzExNjg3MDIsImV4cCI6MjA4Njc0NDcwMn0.mEoZFGpRGVRyrv29QLldCo9_ba65VnlNHr0677xgvtI');
define('SUPABASE_SERVICE_KEY', 'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJpc3MiOiJzdXBhYmFzZSIsInJlZiI6ImpjY2J0bXdjdnFvb2VwcGZib3JzIiwicm9sZSI6InNlcnZpY2Vfcm9sZSIsImlhdCI6MTc3MTE2ODcwMiwiZXhwIjoyMDg2NzQ0NzAyfQ.dBYorOGX2JJFvXcYUgVwpXso7DUFgDmpOV0zYq1vxAs');
define('MY_ID', '00000000-0000-0000-0000-000000000001');

// API Helper
function api($endpoint, $method = 'GET', $data = null) {
    $url = SUPABASE_URL . '/rest/v1/' . $endpoint;
    $ch = curl_init();
    $headers = [
        'apikey: ' . SUPABASE_SERVICE_KEY,
        'Authorization: Bearer ' . SUPABASE_SERVICE_KEY,
        'Content-Type: application/json',
        'Prefer: return=representation'
    ];
    
    if ($method === 'GET' && $data) {
        $url .= '?' . http_build_query($data);
        curl_setopt($ch, CURLOPT_URL, $url);
    } else {
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
        if ($data) curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    }
    
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    $res = curl_exec($ch);
    curl_close($ch);
    return json_decode($res, true);
}

// Upload file
function upload($file, $bucket) {
    $name = uniqid() . '_' . $file['name'];
    $url = SUPABASE_URL . "/storage/v1/object/{$bucket}/{$name}";
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Bearer ' . SUPABASE_SERVICE_KEY,
        'Content-Type: ' . $file['type']
    ]);
    curl_setopt($ch, CURLOPT_POSTFIELDS, file_get_contents($file['tmp_name']));
    $res = curl_exec($ch);
    curl_close($ch);
    return SUPABASE_URL . "/storage/v1/object/public/{$bucket}/{$name}";
}

// Handle actions
$chat = $_GET['chat'] ?? null;
$action = $_GET['action'] ?? '';

// API Endpoints
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    $data = json_decode(file_get_contents('php://input'), true);
    
    // Send message
    if (isset($data['msg'])) {
        $res = api('messages', 'POST', [
            'sender_id' => MY_ID,
            'receiver_id' => $data['to'],
            'content' => $data['msg'],
            'type' => 'text'
        ], true);
        echo json_encode(['ok' => true]);
        exit;
    }
    
    // Delete message
    if (isset($data['delete'])) {
        api('messages?id=eq.' . $data['id'], 'PATCH', ['is_deleted' => true], true);
        echo json_encode(['ok' => true]);
        exit;
    }
    
    // Add reaction
    if (isset($data['react'])) {
        $msg = api('messages?id=eq.' . $data['id'], 'GET')[0] ?? null;
        $reactions = $msg['reactions'] ?? [];
        $reactions[] = ['emoji' => $data['emoji'], 'user' => MY_ID];
        api('messages?id=eq.' . $data['id'], 'PATCH', ['reactions' => $reactions], true);
        echo json_encode(['ok' => true]);
        exit;
    }
}

// Upload media
if (isset($_FILES['file'])) {
    header('Content-Type: application/json');
    $url = upload($_FILES['file'], 'chat_media');
    api('messages', 'POST', [
        'sender_id' => MY_ID,
        'receiver_id' => $_POST['to'],
        'media_url' => $url,
        'type' => 'image'
    ], true);
    echo json_encode(['url' => $url]);
    exit;
}

// Get data
$users = api('profiles', 'GET', ['id' => 'neq.' . MY_ID, 'select' => 'id,username,avatar_url,is_online']);

$messages = [];
if ($chat) {
    $messages = api('messages', 'GET', [
        'or' => "(and(sender_id.eq." . MY_ID . ",receiver_id.eq." . $chat . "),and(sender_id.eq." . $chat . ",receiver_id.eq." . MY_ID . "))",
        'is_deleted' => 'eq.false',
        'order' => 'created_at.asc'
    ]);
}

$chats = [];
$all = api('messages', 'GET', [
    'or' => '(sender_id.eq.' . MY_ID . ',receiver_id.eq.' . MY_ID . ')',
    'order' => 'created_at.desc'
]);
foreach ($all as $msg) {
    $pid = $msg['sender_id'] === MY_ID ? $msg['receiver_id'] : $msg['sender_id'];
    if (!isset($chats[$pid])) {
        $chats[$pid] = [
            'id' => $pid,
            'last' => $msg['content'] ?? ($msg['type'] === 'image' ? '📷 Photo' : '🎤 Voice'),
            'time' => $msg['created_at']
        ];
    }
}
foreach ($users as $u) {
    if (!isset($chats[$u['id']])) {
        $chats[$u['id']] = ['id' => $u['id'], 'last' => 'Start chatting', 'time' => ''];
    }
    $chats[$u['id']]['name'] = $u['username'];
    $chats[$u['id']]['avatar'] = $u['avatar_url'] ?? 'https://via.placeholder.com/50';
    $chats[$u['id']]['online'] = $u['is_online'] ?? false;
}
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Wattz Chat</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { background: #000; color: #fff; font-family: -apple-system, system-ui; height: 100vh; overflow: hidden; }
        
        .app { display: grid; grid-template-columns: 300px 1fr; height: 100vh; }
        .sidebar { background: #000; border-right: 1px solid #222; overflow-y: auto; }
        .sidebar h2 { padding: 20px; font-size: 20px; border-bottom: 1px solid #222; }
        .chat-item { display: flex; align-items: center; gap: 12px; padding: 15px; border-bottom: 1px solid #222; cursor: pointer; }
        .chat-item.active { background: #111; }
        .chat-avatar { width: 45px; height: 45px; border-radius: 50%; object-fit: cover; }
        .chat-info { flex: 1; }
        .chat-name { font-weight: 600; display: flex; align-items: center; gap: 8px; }
        .online-dot { width: 8px; height: 8px; border-radius: 50%; background: #34c759; display: inline-block; }
        .chat-preview { color: #888; font-size: 13px; }
        .chat-time { color: #888; font-size: 11px; }
        
        .chat-area { display: flex; flex-direction: column; height: 100vh; }
        .chat-header { padding: 15px 20px; border-bottom: 1px solid #222; display: flex; align-items: center; gap: 15px; background: #000; }
        .chat-header h3 { flex: 1; }
        .chat-header i { font-size: 22px; cursor: pointer; margin: 0 10px; }
        .messages { flex: 1; overflow-y: auto; padding: 20px; display: flex; flex-direction: column; gap: 10px; }
        .message { max-width: 65%; padding: 12px 16px; border-radius: 18px; position: relative; word-wrap: break-word; }
        .message.sent { align-self: flex-end; background: #0095f6; border-bottom-right-radius: 4px; }
        .message.received { align-self: flex-start; background: #222; border-bottom-left-radius: 4px; }
        .message img { max-width: 200px; border-radius: 12px; cursor: pointer; }
        .message-time { font-size: 10px; color: rgba(255,255,255,0.5); margin-top: 4px; }
        .reactions { position: absolute; bottom: -20px; right: 0; background: #111; border-radius: 20px; padding: 2px 8px; font-size: 12px; display: flex; gap: 2px; }
        
        .input-area { padding: 15px 20px; border-top: 1px solid #222; background: #000; }
        .input-wrapper { display: flex; align-items: center; gap: 12px; background: #111; border-radius: 24px; padding: 8px 16px; }
        .input-wrapper i { font-size: 20px; color: #888; cursor: pointer; }
        .input-wrapper input { flex: 1; background: none; border: none; color: #fff; font-size: 15px; outline: none; }
        
        .context-menu { position: fixed; background: #111; border-radius: 12px; padding: 8px 0; min-width: 160px; display: none; z-index: 1000; }
        .context-menu div { padding: 10px 15px; cursor: pointer; display: flex; align-items: center; gap: 10px; }
        .context-menu div:hover { background: #222; }
        
        .call-modal { position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.95); display: none; flex-direction: column; align-items: center; justify-content: center; z-index: 2000; }
        .call-avatar { width: 150px; height: 150px; border-radius: 50%; animation: pulse 2s infinite; }
        .call-avatar img { width: 100%; height: 100%; border-radius: 50%; object-fit: cover; }
        .call-status { font-size: 24px; margin: 20px 0; }
        .call-controls { display: flex; gap: 30px; margin-top: 30px; }
        .call-btn { width: 60px; height: 60px; border-radius: 50%; display: flex; align-items: center; justify-content: center; cursor: pointer; font-size: 24px; }
        .call-btn.end { background: #ff3b30; }
        .call-btn.accept { background: #34c759; }
        video { position: absolute; top: 0; left: 0; width: 100%; height: 100%; object-fit: cover; display: none; }
        .local-video { position: absolute; top: 20px; right: 20px; width: 150px; height: 200px; border-radius: 12px; border: 2px solid #fff; }
        
        @keyframes pulse { 0% { transform: scale(1); } 50% { transform: scale(1.1); } }
        @media (max-width: 600px) { .app { grid-template-columns: 1fr; } .sidebar { display: <?= $chat ? 'none' : 'block' ?>; } }
    </style>
</head>
<body>
    <div class="app">
        <!-- Sidebar -->
        <div class="sidebar">
            <h2>Chats</h2>
            <?php foreach ($chats as $c): ?>
            <div class="chat-item <?= $chat === $c['id'] ? 'active' : '' ?>" onclick="window.location.href='?chat=<?= $c['id'] ?>'">
                <img src="<?= $c['avatar'] ?>" class="chat-avatar">
                <div class="chat-info">
                    <div class="chat-name">
                        <?= htmlspecialchars($c['name']) ?>
                        <?php if ($c['online']): ?><span class="online-dot"></span><?php endif; ?>
                    </div>
                    <div class="chat-preview"><?= htmlspecialchars($c['last']) ?></div>
                </div>
                <div class="chat-time"><?= $c['time'] ? date('H:i', strtotime($c['time'])) : '' ?></div>
            </div>
            <?php endforeach; ?>
        </div>
        
        <!-- Chat Area -->
        <div class="chat-area">
            <?php if ($chat): 
                $user = array_filter($users, fn($u) => $u['id'] === $chat);
                $user = reset($user);
            ?>
            <div class="chat-header">
                <i class="fa-solid fa-arrow-left" onclick="window.location.href='?'" style="display: none;"></i>
                <img src="<?= $user['avatar_url'] ?? 'https://via.placeholder.com/40' ?>" style="width: 40px; height: 40px; border-radius: 50%;">
                <h3><?= htmlspecialchars($user['username']) ?></h3>
                <i class="fa-solid fa-phone" onclick="startCall('audio')"></i>
                <i class="fa-solid fa-video" onclick="startCall('video')"></i>
            </div>
            
            <div class="messages" id="messages">
                <?php foreach ($messages as $msg): 
                    $sent = $msg['sender_id'] === MY_ID;
                ?>
                <div class="message <?= $sent ? 'sent' : 'received' ?>" data-id="<?= $msg['id'] ?>" data-type="<?= $msg['type'] ?>" data-media="<?= $msg['media_url'] ?? '' ?>" oncontextmenu="showMenu(event, this)">
                    <?php if ($msg['type'] === 'image'): ?>
                        <img src="<?= $msg['media_url'] ?>" onclick="window.open(this.src)">
                    <?php elseif ($msg['type'] === 'voice'): ?>
                        <div><i class="fa-solid fa-play"></i> Voice message</div>
                    <?php else: ?>
                        <?= htmlspecialchars($msg['content']) ?>
                    <?php endif; ?>
                    <div class="message-time"><?= date('H:i', strtotime($msg['created_at'])) ?></div>
                    <?php if (!empty($msg['reactions'])): ?>
                    <div class="reactions">
                        <?php foreach (json_decode($msg['reactions'], true) as $r): ?>
                            <span><?= $r['emoji'] ?></span>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
            </div>
            
            <div class="input-area">
                <div class="input-wrapper">
                    <i class="fa-regular fa-image" onclick="document.getElementById('file').click()"></i>
                    <i class="fa-solid fa-microphone" onclick="alert('Voice recording coming soon')"></i>
                    <input type="text" id="msgInput" placeholder="Message..." onkeypress="if(event.key==='Enter') sendMsg()">
                    <i class="fa-regular fa-paper-plane" onclick="sendMsg()"></i>
                </div>
            </div>
            
            <form id="uploadForm" style="display: none;">
                <input type="file" id="file" accept="image/*" onchange="sendFile(this)">
            </form>
            
            <?php else: ?>
            <div style="display: flex; align-items: center; justify-content: center; height: 100%; color: #888; flex-direction: column; gap: 20px;">
                <i class="fa-regular fa-message" style="font-size: 60px;"></i>
                <h2>Select a chat</h2>
            </div>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Context Menu -->
    <div class="context-menu" id="menu">
        <div onclick="copyMsg()"><i class="fa-regular fa-copy"></i> Copy</div>
        <div onclick="showEmoji()"><i class="fa-regular fa-face-smile"></i> React</div>
        <div id="downloadBtn" onclick="downloadMsg()"><i class="fa-regular fa-circle-down"></i> Download</div>
        <div onclick="deleteMsg()" style="color: #ff3b30;"><i class="fa-regular fa-trash-can"></i> Delete</div>
    </div>
    
    <!-- Emoji Picker -->
    <div class="context-menu" id="emoji" style="min-width: 280px; bottom: 100px;">
        <div style="display: flex; gap: 10px; flex-wrap: wrap; padding: 10px;">
            <?php foreach (['❤️','😂','😮','😢','👍','🔥','🎉','😍','🤔','👎'] as $e): ?>
            <span style="font-size: 24px; cursor: pointer;" onclick="addReaction('<?= $e ?>')"><?= $e ?></span>
            <?php endforeach; ?>
        </div>
    </div>
    
    <!-- Call Modal -->
    <div class="call-modal" id="callModal">
        <video id="remoteVideo" autoplay></video>
        <video id="localVideo" class="local-video" autoplay muted></video>
        <div class="call-avatar" id="callAvatar"><img src="https://via.placeholder.com/150"></div>
        <div class="call-status" id="callStatus">Calling...</div>
        <div class="call-controls">
            <div class="call-btn" onclick="toggleMute()"><i class="fa-solid fa-microphone"></i></div>
            <div class="call-btn" onclick="toggleVideo()"><i class="fa-solid fa-video"></i></div>
            <div class="call-btn end" onclick="endCall()"><i class="fa-solid fa-phone"></i></div>
        </div>
    </div>
    
    <audio id="ringtone" loop src="data:audio/mp3;base64,SUQzBAAAAAAAI1RTU0UAAAAPAAADTGF2ZjU4LjI5LjEwMAAAAAAAAAAAAAAA//tQAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAWGluZwAAAA8AAAACAAABIADAwMDAwMDAwMDAwMDAwMDAwMDAwMDAwMDAwMDAwMDAwMDAwMDAwMDAwMDAwMDAwMDAwMDAwMDAwMDAwMDAwMD//8kAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA//sQZAAB8AAAAyQAAAAAAAKAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA"></audio>

    <script>
        // Config
        const chatId = '<?= $chat ?>';
        const userId = '<?= MY_ID ?>';
        let selectedMsg = null;
        let selectedType = null;
        let selectedMedia = null;
        
        // Messages
        function sendMsg() {
            const input = document.getElementById('msgInput');
            if (!input.value.trim() || !chatId) return;
            
            fetch('?', {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({msg: input.value, to: chatId})
            }).then(() => location.reload());
        }
        
        function sendFile(input) {
            const form = new FormData();
            form.append('file', input.files[0]);
            form.append('to', chatId);
            
            fetch('?', {method: 'POST', body: form})
                .then(() => location.reload());
        }
        
        // Context Menu
        function showMenu(e, el) {
            e.preventDefault();
            selectedMsg = el.dataset.id;
            selectedType = el.dataset.type;
            selectedMedia = el.dataset.media;
            
            const menu = document.getElementById('menu');
            menu.style.display = 'block';
            menu.style.left = e.pageX + 'px';
            menu.style.top = e.pageY + 'px';
            
            document.getElementById('downloadBtn').style.display = 
                (selectedType === 'image' || selectedType === 'voice') ? 'flex' : 'none';
            
            setTimeout(() => document.addEventListener('click', hideMenu), 100);
        }
        
        function hideMenu() {
            document.getElementById('menu').style.display = 'none';
            document.getElementById('emoji').style.display = 'none';
            document.removeEventListener('click', hideMenu);
        }
        
        function deleteMsg() {
            if (!selectedMsg) return;
            fetch('?', {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({delete: true, id: selectedMsg})
            }).then(() => location.reload());
        }
        
        function copyMsg() {
            const msg = document.querySelector(`[data-id="${selectedMsg}"]`).childNodes[0]?.textContent;
            if (msg) navigator.clipboard.writeText(msg);
            hideMenu();
        }
        
        function downloadMsg() {
            if (selectedMedia) window.open(selectedMedia);
            hideMenu();
        }
        
        function showEmoji() {
            document.getElementById('menu').style.display = 'none';
            const emoji = document.getElementById('emoji');
            emoji.style.display = 'block';
            emoji.style.left = document.getElementById('menu').style.left;
            emoji.style.top = (parseInt(document.getElementById('menu').style.top) - 100) + 'px';
        }
        
        function addReaction(emoji) {
            fetch('?', {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({react: true, id: selectedMsg, emoji: emoji})
            }).then(() => location.reload());
            hideMenu();
        }
        
        // Calls
        let localStream;
        let callTimer;
        let seconds = 0;
        
        function startCall(type) {
            document.getElementById('callModal').style.display = 'flex';
            document.getElementById('callStatus').textContent = 'Calling...';
            document.getElementById('ringtone').play();
            
            if (type === 'video') {
                navigator.mediaDevices.getUserMedia({video: true, audio: true})
                    .then(stream => {
                        localStream = stream;
                        document.getElementById('localVideo').srcObject = stream;
                        document.getElementById('localVideo').style.display = 'block';
                        document.getElementById('callAvatar').style.display = 'none';
                    });
            } else {
                navigator.mediaDevices.getUserMedia({audio: true})
                    .then(stream => localStream = stream);
            }
            
            setTimeout(acceptCall, 2000);
        }
        
        function acceptCall() {
            document.getElementById('ringtone').pause();
            document.getElementById('callStatus').textContent = 'Connected';
            callTimer = setInterval(() => {
                seconds++;
                let m = Math.floor(seconds/60), s = seconds%60;
                document.getElementById('callStatus').textContent = `${m}:${s.toString().padStart(2,'0')}`;
            }, 1000);
        }
        
        function endCall() {
            if (localStream) localStream.getTracks().forEach(t => t.stop());
            clearInterval(callTimer);
            seconds = 0;
            document.getElementById('callModal').style.display = 'none';
            document.getElementById('ringtone').pause();
            document.getElementById('localVideo').style.display = 'none';
            document.getElementById('callAvatar').style.display = 'block';
        }
        
        function toggleMute() {
            if (localStream) {
                localStream.getAudioTracks()[0].enabled = !localStream.getAudioTracks()[0].enabled;
            }
        }
        
        function toggleVideo() {
            if (localStream) {
                const track = localStream.getVideoTracks()[0];
                if (track) track.enabled = !track.enabled;
            }
        }
        
        // Auto scroll
        window.onload = () => {
            const m = document.getElementById('messages');
            if (m) m.scrollTop = m.scrollHeight;
        }
        
        // Auto refresh
        if (chatId) setInterval(() => location.reload(), 3000);
    </script>
</body>
</html>
