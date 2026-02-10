<?php
// ==================== CONFIGURATION ====================
$host = 'sql201.infinityfree.com';
$user = 'if0_41110288';
$pass = 'PrincessAmi2009';
$db = 'if0_41110288_chat_app';
$conn = new mysqli($host, $user, $pass, $db);

// API Keys
define('TMDB_API_KEY', '6cce5270f49eadc6dcef5fa7cc2050dd');
define('YOUTUBE_API_KEY', 'AIzaSyBo_MoLOas-pgBQQxeqOduKpEEuxvasbh8');
define('GIPHY_API_KEY', 'tH2BXmDEeZaOLVKx5epUQxvAsbVtKhnT');

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Set timezone
date_default_timezone_set('UTC');

// Auto-create tables if they don't exist
createTablesIfNotExist();

// ==================== API ENDPOINTS ====================
if (isset($_GET['action'])) {
    $action = $_GET['action'];
    header('Content-Type: application/json');
    
    switch($action) {
        case 'refresh':
            handleRefresh();
            break;
        case 'unsend':
            handleUnsend();
            break;
        case 'get_movies':
            getMovies();
            break;
        case 'get_trailer':
            getTrailer();
            break;
        case 'get_shorts':
            getYouTubeShorts();
            break;
        case 'get_gifs':
            searchGiphy();
            break;
        case 'get_stories':
            getStories();
            break;
        case 'get_profile':
            getProfile();
            break;
        case 'get_chat_rooms':
            getChatRooms();
            break;
        case 'get_unread_count':
            getUnreadCount();
            break;
        case 'view_story':
            viewStory();
            break;
        case 'like_post':
            likePost();
            break;
        case 'get_comments':
            getComments();
            break;
        case 'search_users':
            searchUsers();
            break;
        default:
            echo json_encode(['error' => 'Invalid action']);
    }
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['send_msg'])) {
        sendMessage();
    } elseif (isset($_POST['update_profile'])) {
        updateProfile();
    } elseif (isset($_POST['post_comment'])) {
        postComment();
    } elseif (isset($_POST['create_chat'])) {
        createChat();
    } elseif (isset($_POST['clear_chats'])) {
        clearChats();
    } elseif (isset($_FILES['avatar'])) {
        uploadAvatar();
    }
    exit;
}

// ==================== API HANDLERS ====================
function handleRefresh() {
    global $conn;
    $me = $conn->real_escape_string($_GET['me'] ?? 'anonymous');
    $room = $conn->real_escape_string($_GET['room'] ?? 'public');
    
    $q = "SELECT m.*, p.message as p_text, p.type as p_type 
          FROM messages m 
          LEFT JOIN messages p ON m.parent_id = p.id 
          WHERE m.is_deleted = 0 AND " . 
          ($room == 'public' ? 
           "m.recipient = 'public'" : 
           "((m.username='$me' AND m.recipient='$room') OR (m.username='$room' AND m.recipient='$me'))") . 
          " ORDER BY m.id DESC LIMIT 50";
    
    $result = $conn->query($q);
    $messages = [];
    while($row = $result->fetch_assoc()) {
        $messages[] = $row;
    }
    echo json_encode(array_reverse($messages));
}

function handleUnsend() {
    global $conn;
    $id = (int)$_GET['id'];
    $me = $conn->real_escape_string($_GET['me'] ?? 'anonymous');
    $conn->query("UPDATE messages SET is_deleted = 1 WHERE id = $id AND username = '$me'");
    echo json_encode(['status' => 'ok']);
}

function sendMessage() {
    global $conn;
    $u = htmlspecialchars($conn->real_escape_string($_POST['u'] ?? 'anonymous'));
    $m = $conn->real_escape_string($_POST['m'] ?? '');
    $r = htmlspecialchars($conn->real_escape_string($_POST['r'] ?? 'public'));
    $type = $conn->real_escape_string($_POST['type'] ?? 'text');
    $pid = !empty($_POST['pid']) ? (int)$_POST['pid'] : 'NULL';
    
    $stmt = $conn->prepare("INSERT INTO messages (username, message, recipient, type, parent_id) VALUES (?, ?, ?, ?, ?)");
    $stmt->bind_param("ssssi", $u, $m, $r, $type, $pid);
    $stmt->execute();
    
    echo json_encode(['success' => true, 'id' => $conn->insert_id]);
}

function getMovies() {
    // Fetch from TMDB API
    $url = "https://api.themoviedb.org/3/movie/now_playing?api_key=" . TMDB_API_KEY . "&language=en-US&page=1";
    $trendingUrl = "https://api.themoviedb.org/3/trending/movie/week?api_key=" . TMDB_API_KEY;
    
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => 1,
        CURLOPT_URL => $url,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_TIMEOUT => 10
    ]);
    $nowPlaying = json_decode(curl_exec($ch), true);
    
    curl_setopt($ch, CURLOPT_URL, $trendingUrl);
    $trending = json_decode(curl_exec($ch), true);
    curl_close($ch);
    
    $movies = array_merge(
        $nowPlaying['results'] ?? [],
        $trending['results'] ?? []
    );
    
    // Store in database for caching
    global $conn;
    foreach(array_slice($movies, 0, 20) as $movie) {
        $stmt = $conn->prepare("INSERT IGNORE INTO movies (tmdb_id, title, description, poster_url, rating, release_date) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("isssds", 
            $movie['id'],
            $movie['title'],
            $movie['overview'],
            "https://image.tmdb.org/t/p/w500" . $movie['poster_path'],
            $movie['vote_average'],
            $movie['release_date']
        );
        $stmt->execute();
    }
    
    echo json_encode(array_slice($movies, 0, 20));
}

function getTrailer() {
    $movieTitle = urlencode($_GET['title'] ?? '');
    $url = "https://www.googleapis.com/youtube/v3/search?part=snippet&q=" . $movieTitle . "+official+trailer&key=" . YOUTUBE_API_KEY . "&type=video&maxResults=1";
    
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => 1,
        CURLOPT_URL => $url,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_TIMEOUT => 10
    ]);
    
    $response = json_decode(curl_exec($ch), true);
    curl_close($ch);
    
    if(isset($response['items'][0]['id']['videoId'])) {
        echo json_encode(['videoId' => $response['items'][0]['id']['videoId']]);
    } else {
        echo json_encode(['error' => 'No trailer found']);
    }
}

function getYouTubeShorts() {
    $url = "https://www.googleapis.com/youtube/v3/search?part=snippet&q=movies+shorts&key=" . YOUTUBE_API_KEY . "&type=video&videoDuration=short&maxResults=20";
    
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => 1,
        CURLOPT_URL => $url,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_TIMEOUT => 10
    ]);
    
    $response = json_decode(curl_exec($ch), true);
    curl_close($ch);
    
    echo json_encode($response['items'] ?? []);
}

function searchGiphy() {
    $query = urlencode($_GET['q'] ?? 'trending');
    $url = "https://api.giphy.com/v1/gifs/search?api_key=" . GIPHY_API_KEY . "&q=" . $query . "&limit=12";
    
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => 1,
        CURLOPT_URL => $url,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_TIMEOUT => 10
    ]);
    
    $response = json_decode(curl_exec($ch), true);
    curl_close($ch);
    
    echo json_encode($response['data'] ?? []);
}

function getStories() {
    global $conn;
    // Get stories from last 24 hours
    $q = "SELECT s.*, u.username, u.avatar,
           (SELECT COUNT(*) FROM story_views sv WHERE sv.story_id = s.id) as view_count
          FROM stories s
          JOIN users u ON s.user_id = u.id
          WHERE s.expires_at > NOW()
          ORDER BY s.created_at DESC
          LIMIT 10";
    
    $result = $conn->query($q);
    $stories = [];
    while($row = $result->fetch_assoc()) {
        $stories[] = $row;
    }
    echo json_encode($stories);
}

function getProfile() {
    global $conn;
    $username = $conn->real_escape_string($_GET['username'] ?? 'anonymous');
    
    $q = "SELECT u.*, 
           (SELECT COUNT(*) FROM posts p WHERE p.user_id = u.id) as post_count,
           (SELECT COUNT(*) FROM user_follows f WHERE f.following_id = u.id) as followers,
           (SELECT COUNT(*) FROM user_follows f WHERE f.follower_id = u.id) as following
          FROM users u 
          WHERE u.username = '$username'";
    
    $result = $conn->query($q);
    echo json_encode($result->fetch_assoc() ?: ['error' => 'User not found']);
}

function updateProfile() {
    global $conn;
    $username = $conn->real_escape_string($_POST['username'] ?? '');
    $bio = $conn->real_escape_string($_POST['bio'] ?? '');
    $currentUser = $conn->real_escape_string($_POST['current_user'] ?? 'anonymous');
    
    $q = "UPDATE users SET username = ?, bio = ? WHERE username = ?";
    $stmt = $conn->prepare($q);
    $stmt->bind_param("sss", $username, $bio, $currentUser);
    $stmt->execute();
    
    echo json_encode(['success' => $stmt->affected_rows > 0]);
}

function getChatRooms() {
    global $conn;
    $q = "SELECT c.*, 
           (SELECT COUNT(*) FROM chat_participants cp WHERE cp.chat_id = c.id) as member_count,
           (SELECT username FROM users u WHERE u.id = c.created_by) as creator
          FROM chats c 
          WHERE c.type = 'public'
          ORDER BY c.created_at DESC
          LIMIT 10";
    
    $result = $conn->query($q);
    $rooms = [];
    while($row = $result->fetch_assoc()) {
        $rooms[] = $row;
    }
    echo json_encode($rooms);
}

function getUnreadCount() {
    global $conn;
    $username = $conn->real_escape_string($_GET['username'] ?? 'anonymous');
    
    $q = "SELECT COUNT(*) as count FROM messages 
          WHERE recipient = ? AND username != ? AND is_read = 0 AND is_deleted = 0";
    $stmt = $conn->prepare($q);
    $stmt->bind_param("ss", $username, $username);
    $stmt->execute();
    $result = $stmt->get_result();
    
    echo json_encode($result->fetch_assoc());
}

function viewStory() {
    global $conn;
    $story_id = (int)$_GET['story_id'];
    $viewer = $conn->real_escape_string($_GET['viewer'] ?? 'anonymous');
    
    // Get viewer ID
    $userResult = $conn->query("SELECT id FROM users WHERE username = '$viewer'");
    if($userResult->num_rows > 0) {
        $viewer_id = $userResult->fetch_assoc()['id'];
        
        // Record view
        $stmt = $conn->prepare("INSERT IGNORE INTO story_views (story_id, viewer_id) VALUES (?, ?)");
        $stmt->bind_param("ii", $story_id, $viewer_id);
        $stmt->execute();
    }
    
    echo json_encode(['success' => true]);
}

function likePost() {
    global $conn;
    $post_id = (int)$_GET['post_id'];
    $username = $conn->real_escape_string($_GET['username'] ?? 'anonymous');
    
    // Get user ID
    $userResult = $conn->query("SELECT id FROM users WHERE username = '$username'");
    if($userResult->num_rows > 0) {
        $user_id = $userResult->fetch_assoc()['id'];
        
        // Toggle like
        $check = $conn->query("SELECT * FROM post_likes WHERE post_id = $post_id AND user_id = $user_id");
        if($check->num_rows > 0) {
            $conn->query("DELETE FROM post_likes WHERE post_id = $post_id AND user_id = $user_id");
        } else {
            $conn->query("INSERT INTO post_likes (post_id, user_id) VALUES ($post_id, $user_id)");
        }
    }
    
    // Get new like count
    $countResult = $conn->query("SELECT COUNT(*) as count FROM post_likes WHERE post_id = $post_id");
    echo json_encode(['count' => $countResult->fetch_assoc()['count']]);
}

function getComments() {
    global $conn;
    $post_id = (int)$_GET['post_id'];
    
    $q = "SELECT c.*, u.username 
          FROM comments c 
          JOIN users u ON c.user_id = u.id 
          WHERE c.post_id = ? 
          ORDER BY c.created_at ASC";
    
    $stmt = $conn->prepare($q);
    $stmt->bind_param("i", $post_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $comments = [];
    while($row = $result->fetch_assoc()) {
        $comments[] = $row;
    }
    echo json_encode($comments);
}

function postComment() {
    global $conn;
    $post_id = (int)$_POST['post_id'];
    $username = $conn->real_escape_string($_POST['username'] ?? 'anonymous');
    $comment = $conn->real_escape_string($_POST['comment'] ?? '');
    
    // Get user ID
    $userResult = $conn->query("SELECT id FROM users WHERE username = '$username'");
    if($userResult->num_rows > 0) {
        $user_id = $userResult->fetch_assoc()['id'];
        
        $stmt = $conn->prepare("INSERT INTO comments (post_id, user_id, comment) VALUES (?, ?, ?)");
        $stmt->bind_param("iis", $post_id, $user_id, $comment);
        $stmt->execute();
    }
    
    echo json_encode(['success' => true]);
}

function searchUsers() {
    global $conn;
    $query = $conn->real_escape_string($_GET['q'] ?? '');
    
    $q = "SELECT id, username, avatar, bio FROM users 
          WHERE username LIKE ? OR bio LIKE ?
          LIMIT 10";
    
    $stmt = $conn->prepare($q);
    $searchTerm = "%$query%";
    $stmt->bind_param("ss", $searchTerm, $searchTerm);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $users = [];
    while($row = $result->fetch_assoc()) {
        $users[] = $row;
    }
    echo json_encode($users);
}

function createChat() {
    global $conn;
    $type = $conn->real_escape_string($_POST['type'] ?? 'p2p');
    $name = $conn->real_escape_string($_POST['name'] ?? '');
    $username = $conn->real_escape_string($_POST['username'] ?? 'anonymous');
    
    // Get user ID
    $userResult = $conn->query("SELECT id FROM users WHERE username = '$username'");
    if($userResult->num_rows > 0) {
        $user_id = $userResult->fetch_assoc()['id'];
        
        $stmt = $conn->prepare("INSERT INTO chats (name, type, created_by) VALUES (?, ?, ?)");
        $stmt->bind_param("ssi", $name, $type, $user_id);
        $stmt->execute();
        $chat_id = $conn->insert_id;
        
        // Add creator as participant
        $conn->query("INSERT INTO chat_participants (chat_id, user_id) VALUES ($chat_id, $user_id)");
        
        echo json_encode(['success' => true, 'chat_id' => $chat_id]);
    } else {
        echo json_encode(['error' => 'User not found']);
    }
}

function clearChats() {
    global $conn;
    $username = $conn->real_escape_string($_POST['username'] ?? 'anonymous');
    
    // Mark user's messages as deleted
    $conn->query("UPDATE messages SET is_deleted = 1 WHERE username = '$username'");
    echo json_encode(['success' => true]);
}

function uploadAvatar() {
    global $conn;
    $username = $conn->real_escape_string($_POST['username'] ?? 'anonymous');
    
    if(isset($_FILES['avatar']) && $_FILES['avatar']['error'] == 0) {
        $allowed = ['jpg', 'jpeg', 'png', 'gif'];
        $ext = strtolower(pathinfo($_FILES['avatar']['name'], PATHINFO_EXTENSION));
        
        if(in_array($ext, $allowed)) {
            // Create uploads directory if it doesn't exist
            if(!is_dir('uploads')) {
                mkdir('uploads', 0755, true);
            }
            
            $filename = uniqid() . '.' . $ext;
            $path = 'uploads/' . $filename;
            
            if(move_uploaded_file($_FILES['avatar']['tmp_name'], $path)) {
                // Update database
                $stmt = $conn->prepare("UPDATE users SET avatar = ? WHERE username = ?");
                $stmt->bind_param("ss", $path, $username);
                $stmt->execute();
                
                echo json_encode(['success' => true, 'avatar' => $path]);
                return;
            }
        }
    }
    
    echo json_encode(['error' => 'Upload failed']);
}

    
    // Create default user if not exists
    $conn->query("INSERT IGNORE INTO users (username, email, password) VALUES ('movielover', 'user@example.com', '')");
}

// ==================== HTML FRONTEND ====================
if(!isset($_GET['action']) && $_SERVER['REQUEST_METHOD'] !== 'POST') {
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Moviez Ultra Infinity</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * { -webkit-tap-highlight-color: transparent; }
        body { background: #000; color: #fff; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; }
        .scrollbar-hide { -ms-overflow-style: none; scrollbar-width: none; }
        .scrollbar-hide::-webkit-scrollbar { display: none; }
        .story-ring { 
            background: linear-gradient(45deg, #f09433, #e6683c, #dc2743, #cc2366, #bc1888);
            padding: 2px; border-radius: 50%; animation: pulse 2s infinite; 
        }
        @keyframes pulse { 
            0% { transform: scale(1); } 
            50% { transform: scale(1.05); } 
            100% { transform: scale(1); } 
        }
        @keyframes heart { 
            0% { transform: scale(0); opacity: 1; } 
            70% { transform: scale(1.2); } 
            100% { transform: scale(1); } 
        }
        .heart-animation { animation: heart 0.8s ease-out; }
        .voice-wave { 
            background: linear-gradient(90deg, rgba(255,255,255,0.8) 0%, rgba(255,255,255,0.4) 25%, 
            rgba(255,255,255,0.6) 50%, rgba(255,255,255,0.3) 75%, rgba(255,255,255,0.7) 100%);
            background-size: 200% 100%; animation: wave 1.5s linear infinite; 
        }
        @keyframes wave { 
            0% { background-position: 200% 0; } 
            100% { background-position: -200% 0; } 
        }
        .tab-content { transition: opacity 0.3s; }
        .shorts-player iframe { pointer-events: auto; }
        .gradient-text { 
            background: linear-gradient(45deg, #FF6B6B, #4ECDC4, #45B7D1, #96CEB4, #FFEAA7);
            -webkit-background-clip: text; -webkit-text-fill-color: transparent; 
        }
        .typing-dot { 
            width: 6px; height: 6px; border-radius: 50%; background: #888; 
            margin: 0 2px; animation: typing 1.4s infinite; 
        }
        @keyframes typing { 
            0%, 80%, 100% { transform: translateY(0); } 
            40% { transform: translateY(-8px); } 
        }
        .custom-scrollbar::-webkit-scrollbar { width: 4px; }
        .custom-scrollbar::-webkit-scrollbar-track { background: transparent; }
        .custom-scrollbar::-webkit-scrollbar-thumb { background: #555; border-radius: 2px; }
    </style>
</head>
<body class="h-screen overflow-hidden">
    <div class="flex h-screen max-w-6xl mx-auto">
        <!-- Left Sidebar -->
        <div class="w-20 md:w-64 border-r border-gray-800 hidden md:flex flex-col py-6 px-2 md:px-6">
            <div class="mb-8 px-2">
                <h1 class="text-2xl font-bold gradient-text hidden md:block">Moviez Ultra</h1>
                <div class="md:hidden text-center"><i class="fas fa-film text-2xl gradient-text"></i></div>
            </div>
            <nav class="space-y-4 flex-1">
                <button onclick="switchTab('feed')" class="tab-btn w-full text-left py-3 px-4 rounded-lg hover:bg-gray-900 flex items-center space-x-3 text-lg bg-gray-900">
                    <i class="fas fa-home w-6"></i><span class="hidden md:block">Feed</span>
                </button>
                <button onclick="switchTab('search')" class="tab-btn w-full text-left py-3 px-4 rounded-lg hover:bg-gray-900 flex items-center space-x-3 text-lg">
                    <i class="fas fa-search w-6"></i><span class="hidden md:block">Search</span>
                </button>
                <button onclick="switchTab('reels')" class="tab-btn w-full text-left py-3 px-4 rounded-lg hover:bg-gray-900 flex items-center space-x-3 text-lg">
                    <i class="fas fa-play w-6"></i><span class="hidden md:block">Reels</span>
                </button>
                <button onclick="switchTab('chat')" class="tab-btn w-full text-left py-3 px-4 rounded-lg hover:bg-gray-900 flex items-center space-x-3 text-lg">
                    <i class="fas fa-comment w-6"></i><span class="hidden md:block">Messages</span>
                    <span id="unread-count" class="bg-red-500 text-xs px-2 py-1 rounded-full hidden">0</span>
                </button>
                <button onclick="switchTab('profile')" class="tab-btn w-full text-left py-3 px-4 rounded-lg hover:bg-gray-900 flex items-center space-x-3 text-lg">
                    <i class="fas fa-user w-6"></i><span class="hidden md:block">Profile</span>
                </button>
            </nav>
            <div class="mt-auto space-y-4">
                <button onclick="switchTab('settings')" class="w-full text-left py-3 px-4 rounded-lg hover:bg-gray-900 flex items-center space-x-3">
                    <i class="fas fa-cog w-6"></i><span class="hidden md:block">Settings</span>
                </button>
            </div>
        </div>

        <!-- Main Content -->
        <div class="flex-1 flex flex-col h-screen">
            <div class="md:hidden flex items-center justify-between px-4 py-3 border-b border-gray-800">
                <h1 class="text-xl font-bold gradient-text">Moviez Ultra</h1>
                <div class="flex items-center space-x-4">
                    <button onclick="switchTab('chat')"><i class="fas fa-comment text-xl"></i></button>
                    <button onclick="switchTab('profile')"><i class="fas fa-user text-xl"></i></button>
                </div>
            </div>

            <!-- Tab Contents -->
            <div class="flex-1 overflow-hidden">
                <!-- Feed Tab -->
                <div id="feed-tab" class="tab-content h-full">
                    <div class="stories-section border-b border-gray-800 px-4 py-3 overflow-x-auto">
                        <div class="flex space-x-4">
                            <div class="flex flex-col items-center">
                                <div class="w-16 h-16 rounded-full border-2 border-dashed border-gray-600 flex items-center justify-center mb-1">
                                    <i class="fas fa-plus text-gray-400"></i>
                                </div>
                                <span class="text-xs">Your Story</span>
                            </div>
                            <div id="stories-container" class="flex space-x-4"></div>
                        </div>
                    </div>
                    <div id="feed-container" class="h-[calc(100%-80px)] overflow-y-auto custom-scrollbar p-4"></div>
                </div>

                <!-- Search Tab -->
                <div id="search-tab" class="tab-content h-full hidden">
                    <div class="p-4">
                        <div class="relative mb-6">
                            <input type="text" id="search-input" class="w-full bg-gray-900 border border-gray-700 rounded-full py-3 px-5 pl-12 focus:outline-none focus:border-blue-500" placeholder="Search movies or users...">
                            <i class="fas fa-search absolute left-5 top-3.5 text-gray-400"></i>
                        </div>
                        <div id="search-results" class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-4"></div>
                        <div class="mt-8">
                            <h2 class="text-xl font-bold mb-4">Public Chat Rooms</h2>
                            <div id="chat-rooms" class="space-y-2"></div>
                        </div>
                    </div>
                </div>

                <!-- Reels Tab -->
                <div id="reels-tab" class="tab-content h-full hidden">
                    <div class="shorts-player h-full relative">
                        <div id="reels-container" class="h-full overflow-y-auto snap-y snap-mandatory"></div>
                        <div class="shorts-controls absolute right-4 bottom-20">
                            <button id="mute-btn" class="block mb-4 text-white bg-black/50 rounded-full p-2"><i class="fas fa-volume-up"></i></button>
                            <button id="pause-btn" class="block text-white bg-black/50 rounded-full p-2"><i class="fas fa-pause"></i></button>
                        </div>
                    </div>
                </div>

                <!-- Chat Tab -->
                <div id="chat-tab" class="tab-content h-full hidden flex flex-col">
                    <div class="border-b border-gray-800 p-4 flex justify-between items-center">
                        <h2 class="text-xl font-bold">Messages</h2>
                        <button onclick="createNewChat()" class="bg-blue-600 hover:bg-blue-700 px-4 py-2 rounded-lg"><i class="fas fa-edit"></i> New</button>
                    </div>
                    <div id="chats-list" class="flex-1 overflow-y-auto custom-scrollbar"></div>
                    <div id="current-chat" class="h-full hidden flex flex-col">
                        <div class="border-b border-gray-800 p-4 flex items-center space-x-3">
                            <button onclick="closeCurrentChat()" class="md:hidden"><i class="fas fa-arrow-left"></i></button>
                            <div class="w-10 h-10 rounded-full bg-gray-700"></div>
                            <div><h3 id="chat-with-user" class="font-bold"></h3><p id="chat-status" class="text-sm text-gray-400">Online</p></div>
                        </div>
                        <div id="messages-container" class="flex-1 overflow-y-auto p-4 space-y-3 custom-scrollbar"></div>
                        <div class="border-t border-gray-800 p-4">
                            <div class="flex items-center space-x-2">
                                <button onclick="toggleGiphyDrawer()" class="p-2 rounded-full hover:bg-gray-800"><i class="fas fa-gift text-purple-400"></i></button>
                                <button id="voice-btn" class="p-2 rounded-full hover:bg-gray-800"><i class="fas fa-microphone text-red-400"></i></button>
                                <div class="flex-1 relative">
                                    <input type="text" id="message-input" class="w-full bg-gray-900 border border-gray-700 rounded-full py-3 px-5 pr-12 focus:outline-none focus:border-blue-500" placeholder="Type a message...">
                                    <button id="send-btn" class="absolute right-3 top-2.5 text-blue-500"><i class="fas fa-paper-plane"></i></button>
                                </div>
                            </div>
                            <div id="voice-recording-ui" class="mt-3 hidden items-center justify-between">
                                <div class="flex items-center space-x-3"><div class="voice-wave w-24 h-8 rounded"></div><span id="recording-time" class="text-sm">0:00</span></div>
                                <div class="flex space-x-2"><button id="cancel-recording" class="px-4 py-1 bg-red-600 rounded">Cancel</button><button id="send-recording" class="px-4 py-1 bg-green-600 rounded">Send</button></div>
                            </div>
                            <div id="giphy-drawer" class="mt-3 hidden bg-gray-900 rounded-lg p-3">
                                <div class="flex justify-between mb-2">
                                    <input type="text" id="giphy-search" class="flex-1 bg-gray-800 border border-gray-700 rounded py-2 px-3 mr-2" placeholder="Search GIFs...">
                                    <button onclick="toggleGiphyDrawer()" class="px-3 bg-gray-700 rounded"><i class="fas fa-times"></i></button>
                                </div>
                                <div id="giphy-results" class="grid grid-cols-3 gap-2 max-h-40 overflow-y-auto"></div>
                            </div>
                            <div id="reply-preview" class="mt-2 p-2 bg-gray-800 rounded hidden">
                                <div class="flex justify-between"><span class="text-sm text-gray-400">Replying to message</span><button onclick="cancelReply()" class="text-xs"><i class="fas fa-times"></i></button></div>
                                <p id="reply-preview-text" class="text-sm truncate"></p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Profile Tab -->
                <div id="profile-tab" class="tab-content h-full hidden overflow-y-auto">
                    <div class="max-w-2xl mx-auto p-6">
                        <div class="flex items-center space-x-6 mb-8">
                            <div class="relative">
                                <div class="w-32 h-32 rounded-full bg-gray-700 flex items-center justify-center text-4xl"><i class="fas fa-user"></i></div>
                                <button onclick="document.getElementById('avatar-upload').click()" class="absolute bottom-0 right-0 bg-blue-600 rounded-full p-2"><i class="fas fa-camera"></i></button>
                                <input type="file" id="avatar-upload" class="hidden" accept="image/*">
                            </div>
                            <div class="flex-1">
                                <div class="flex items-center space-x-4 mb-4">
                                    <h1 id="profile-username" class="text-2xl font-bold">@movielover</h1>
                                    <button onclick="editProfile()" class="px-4 py-1 border border-gray-600 rounded">Edit Profile</button>
                                </div>
                                <div class="flex space-x-8 mb-4">
                                    <div class="text-center"><div class="font-bold" id="post-count">0</div><div class="text-gray-400">Posts</div></div>
                                    <div class="text-center"><div class="font-bold" id="followers-count">0</div><div class="text-gray-400">Followers</div></div>
                                    <div class="text-center"><div class="font-bold" id="following-count">0</div><div class="text-gray-400">Following</div></div>
                                </div>
                                <p id="profile-bio" class="text-gray-300">No bio yet.</p>
                            </div>
                        </div>
                        <div id="edit-profile-form" class="hidden bg-gray-900 p-6 rounded-lg mb-6">
                            <h3 class="text-xl font-bold mb-4">Edit Profile</h3>
                            <div class="space-y-4">
                                <div><label class="block text-sm mb-1">Username</label><input type="text" id="edit-username" class="w-full bg-gray-800 border border-gray-700 rounded py-2 px-3"></div>
                                <div><label class="block text-sm mb-1">Bio</label><textarea id="edit-bio" class="w-full bg-gray-800 border border-gray-700 rounded py-2 px-3 h-24"></textarea></div>
                                <div class="flex justify-end space-x-2"><button onclick="cancelEdit()" class="px-4 py-2 border border-gray-600 rounded">Cancel</button><button onclick="saveProfile()" class="px-4 py-2 bg-blue-600 rounded">Save Changes</button></div>
                            </div>
                        </div>
                        <div class="grid grid-cols-3 gap-2" id="user-posts"></div>
                    </div>
                </div>

                <!-- Settings Tab -->
                <div id="settings-tab" class="tab-content h-full hidden overflow-y-auto p-6">
                    <h2 class="text-2xl font-bold mb-6">Settings</h2>
                    <div class="space-y-4 max-w-md">
                        <div class="bg-gray-900 p-4 rounded-lg">
                            <h3 class="font-bold mb-2">Chat Sync Interval</h3>
                            <p class="text-sm text-gray-400 mb-3">How often to check for new messages</p>
                            <select id="sync-interval" class="w-full bg-gray-800 border border-gray-700 rounded py-2 px-3">
                                <option value="1000">1 second</option>
                                <option value="3000" selected>3 seconds</option>
                                <option value="5000">5 seconds</option>
                                <option value="10000">10 seconds</option>
                            </select>
                        </div>
                        <div class="bg-gray-900 p-4 rounded-lg">
                            <h3 class="font-bold mb-2">Auto-play Videos</h3>
                            <div class="flex items-center justify-between"><span>Play videos automatically</span><label class="relative inline-flex items-center cursor-pointer"><input type="checkbox" id="autoplay-toggle" class="sr-only peer" checked><div class="w-11 h-6 bg-gray-700 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-blue-600"></div></label></div>
                        </div>
                        <div class="bg-gray-900 p-4 rounded-lg">
                            <h3 class="font-bold mb-2">Clear Chat History</h3>
                            <p class="text-sm text-gray-400 mb-3">Permanently delete all your messages</p>
                            <button onclick="clearChatHistory()" class="px-4 py-2 bg-red-600 rounded hover:bg-red-700">Clear All Messages</button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Modals -->
    <div id="trailer-modal" class="fixed inset-0 bg-black bg-opacity-90 z-50 hidden flex items-center justify-center">
        <div class="relative w-full max-w-4xl">
            <button onclick="closeTrailer()" class="absolute -top-10 right-0 text-white text-2xl"><i class="fas fa-times"></i></button>
            <div id="youtube-player" class="w-full aspect-video"></div>
        </div>
    </div>

    <div id="voice-player-modal" class="fixed inset-0 bg-black bg-opacity-90 z-50 hidden flex items-center justify-center">
        <div class="bg-gray-900 rounded-lg p-6 max-w-md w-full">
            <h3 class="text-xl font-bold mb-4">Voice Message</h3>
            <div class="voice-wave w-full h-12 rounded mb-4"></div>
            <div class="flex items-center justify-center space-x-4">
                <button id="play-voice" class="p-3 rounded-full bg-blue-600"><i class="fas fa-play"></i></button>
                <button id="pause-voice" class="p-3 rounded-full bg-gray-700"><i class="fas fa-pause"></i></button>
                <span id="voice-duration" class="text-lg">0:00</span>
            </div>
            <button onclick="closeVoicePlayer()" class="mt-6 w-full py-2 border border-gray-600 rounded">Close</button>
        </div>
    </div>

    <div id="new-chat-modal" class="fixed inset-0 bg-black bg-opacity-90 z-50 hidden flex items-center justify-center">
        <div class="bg-gray-900 rounded-lg p-6 max-w-md w-full">
            <h3 class="text-xl font-bold mb-4">New Chat</h3>
            <div class="space-y-4">
                <div><label class="block text-sm mb-1">Search User</label><input type="text" id="search-user" class="w-full bg-gray-800 border border-gray-700 rounded py-2 px-3" placeholder="Username..."><div id="user-results" class="mt-2 max-h-40 overflow-y-auto"></div></div>
                <div><label class="block text-sm mb-1">Or Start Public Chat Room</label><input type="text" id="room-name" class="w-full bg-gray-800 border border-gray-700 rounded py-2 px-3" placeholder="Chat room name..."></div>
                <div class="flex justify-end space-x-2"><button onclick="closeNewChatModal()" class="px-4 py-2 border border-gray-600 rounded">Cancel</button><button onclick="startNewChat()" class="px-4 py-2 bg-blue-600 rounded">Start Chat</button></div>
            </div>
        </div>
    </div>

    <!-- YouTube API -->
    <script src="https://www.youtube.com/iframe_api"></script>
    <script>
        // State
        const state = {
            currentTab: 'feed',
            currentChat: null,
            currentUser: 'movielover',
            youtubePlayer: null,
            voicePlayer: null,
            mediaRecorder: null,
            audioChunks: [],
            isRecording: false,
            syncInterval: null,
            replyTo: null
        };

        // Initialize
        document.addEventListener('DOMContentLoaded', function() {
            loadUserData();
            switchTab('feed');
            loadStories();
            loadMovies();
            loadChats();
            updateUnreadCount();
            startChatSync();
        });

        // Tab Navigation
        function switchTab(tab) {
            document.querySelectorAll('.tab-content').forEach(el => el.classList.add('hidden'));
            document.getElementById(tab + '-tab').classList.remove('hidden');
            state.currentTab = tab;
            
            document.querySelectorAll('.tab-btn').forEach(btn => btn.classList.remove('bg-gray-900'));
            event?.target.closest('.tab-btn')?.classList.add('bg-gray-900');
            
            if(tab === 'reels') loadYouTubeShorts();
            if(tab === 'profile') loadProfile();
            if(tab === 'search') loadChatRooms();
        }

        // Load Movies from TMDB
        async function loadMovies() {
            try {
                const response = await fetch('?action=get_movies');
                const movies = await response.json();
                renderMovies(movies);
            } catch(e) {
                console.error('Failed to load movies:', e);
                document.getElementById('feed-container').innerHTML = '<p class="text-center py-8 text-gray-400">Failed to load movies</p>';
            }
        }

        function renderMovies(movies) {
            const container = document.getElementById('feed-container');
            container.innerHTML = movies.map(movie => `
                <div class="bg-gray-900 rounded-xl overflow-hidden mb-6">
                    <div class="relative">
                        <img src="${movie.poster_path ? 'https://image.tmdb.org/t/p/w500' + movie.poster_path : 'https://via.placeholder.com/500x750?text=No+Poster'}" 
                             class="w-full h-64 object-cover">
                        <div class="absolute top-4 right-4">
                            <button onclick="likeMovie(${movie.id})" class="like-btn p-2 bg-black/50 rounded-full">
                                <i class="far fa-heart text-white text-xl"></i>
                            </button>
                        </div>
                        <div class="absolute bottom-4 right-4">
                            <button onclick="playTrailer('${movie.title}')" class="play-btn px-4 py-2 bg-red-600 rounded-full flex items-center space-x-2">
                                <i class="fas fa-play"></i><span>Trailer</span>
                            </button>
                        </div>
                    </div>
                    <div class="p-4">
                        <div class="flex justify-between items-start mb-2">
                            <h3 class="text-xl font-bold">${movie.title}</h3>
                            <span class="bg-yellow-500 text-black px-2 py-1 rounded text-sm font-bold">${movie.vote_average?.toFixed(1) || 'N/A'} ★</span>
                        </div>
                        <p class="text-gray-300 mb-4">${(movie.overview || '').substring(0, 150)}...</p>
                        <div class="flex items-center justify-between text-sm text-gray-400">
                            <span>${movie.release_date?.substring(0,4) || 'N/A'}</span>
                            <div class="flex items-center space-x-4">
                                <button onclick="showComments(${movie.id})" class="hover:text-white"><i class="far fa-comment"></i> Comment</button>
                                <button onclick="shareMovie(${movie.id})" class="hover:text-white"><i class="far fa-share-square"></i> Share</button>
                            </div>
                        </div>
                        <div id="comments-${movie.id}" class="mt-4 hidden">
                            <div id="comments-list-${movie.id}" class="space-y-3 mb-3"></div>
                            <div class="flex"><input type="text" id="comment-input-${movie.id}" class="flex-1 bg-gray-800 border border-gray-700 rounded-l py-2 px-3" placeholder="Add a comment..."><button onclick="postComment(${movie.id})" class="bg-blue-600 px-4 rounded-r">Post</button></div>
                        </div>
                    </div>
                </div>
            `).join('');
            
            // Add double-tap to like
            document.querySelectorAll('.like-btn').forEach(btn => {
                btn.parentElement.parentElement.addEventListener('dblclick', function(e) {
                    const heart = document.createElement('div');
                    heart.className = 'heart-animation absolute top-1/2 left-1/2 transform -translate-x-1/2 -translate-y-1/2';
                    heart.innerHTML = '<i class="fas fa-heart text-red-500 text-6xl"></i>';
                    this.appendChild(heart);
                    setTimeout(() => heart.remove(), 800);
                    const movieId = this.querySelector('.like-btn').getAttribute('onclick').match(/\d+/)[0];
                    likeMovie(movieId);
                });
            });
        }

        // Play Trailer
        async function playTrailer(title) {
            try {
                const response = await fetch(`?action=get_trailer&title=${encodeURIComponent(title)}`);
                const data = await response.json();
                
                if(data.videoId) {
                    document.getElementById('trailer-modal').classList.remove('hidden');
                    
                    if(state.youtubePlayer) {
                        state.youtubePlayer.loadVideoById(data.videoId);
                    } else {
                        state.youtubePlayer = new YT.Player('youtube-player', {
                            videoId: data.videoId,
                            playerVars: { autoplay: 1, modestbranding: 1, rel: 0 },
                            events: { onStateChange: onPlayerStateChange }
                        });
                    }
                } else {
                    alert('No trailer found');
                }
            } catch(e) {
                alert('Failed to load trailer');
            }
        }

        function closeTrailer() {
            document.getElementById('trailer-modal').classList.add('hidden');
            if(state.youtubePlayer) state.youtubePlayer.stopVideo();
        }

        function onPlayerStateChange(event) {
            if(event.data === YT.PlayerState.ENDED) closeTrailer();
        }

        // YouTube Shorts
        async function loadYouTubeShorts() {
            try {
                const response = await fetch('?action=get_shorts');
                const shorts = await response.json();
                renderShorts(shorts);
            } catch(e) {
                console.error('Failed to load shorts:', e);
            }
        }

        function renderShorts(shorts) {
            const container = document.getElementById('reels-container');
            container.innerHTML = shorts.map((short, i) => `
                <div class="h-full snap-start relative">
                    <iframe src="https://www.youtube.com/embed/${short.id.videoId}?autoplay=${i===0?1:0}&controls=0&modestbranding=1&rel=0&playsinline=1" class="w-full h-full" frameborder="0" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" allowfullscreen></iframe>
                    <div class="absolute bottom-20 left-4 text-white"><h3 class="font-bold text-lg">${short.snippet.title}</h3><p class="text-sm">${short.snippet.channelTitle}</p></div>
                </div>
            `).join('');
        }

        // Stories
        async function loadStories() {
            try {
                const response = await fetch('?action=get_stories');
                const stories = await response.json();
                renderStories(stories);
            } catch(e) {
                console.error('Failed to load stories:', e);
            }
        }

        function renderStories(stories) {
            const container = document.getElementById('stories-container');
            container.innerHTML = stories.map(story => `
                <div class="flex flex-col items-center cursor-pointer" onclick="viewStory(${story.id})">
                    <div class="story-ring">
                        <div class="w-16 h-16 rounded-full bg-gray-700 flex items-center justify-center overflow-hidden">
                            ${story.avatar ? `<img src="${story.avatar}" class="w-full h-full object-cover">` : `<span class="text-white font-bold">${story.username.charAt(0)}</span>`}
                        </div>
                    </div>
                    <span class="text-xs mt-1">${story.username}</span>
                </div>
            `).join('');
        }

        function viewStory(storyId) {
            fetch(`?action=view_story&story_id=${storyId}&viewer=${state.currentUser}`);
            alert(`Viewing story ${storyId}`);
        }

        // Chat System
        async function loadChats() {
            try {
                const response = await fetch(`?action=refresh&me=${state.currentUser}&room=public`);
                const messages = await response.json();
                renderChatList(messages);
            } catch(e) {
                console.error('Failed to load chats:', e);
            }
        }

        function renderChatList(messages) {
            const container = document.getElementById('chats-list');
            // Group by sender
            const chats = {};
            messages.forEach(msg => {
                const other = msg.username === state.currentUser ? msg.recipient : msg.username;
                if(other !== 'public') {
                    if(!chats[other]) chats[other] = {lastMsg: msg, unread: 0};
                    if(!msg.is_read && msg.username !== state.currentUser) chats[other].unread++;
                }
            });
            
            container.innerHTML = Object.entries(chats).map(([user, data]) => `
                <div class="p-4 border-b border-gray-800 hover:bg-gray-900 cursor-pointer" onclick="openChat('${user}')">
                    <div class="flex items-center space-x-3">
                        <div class="w-12 h-12 rounded-full bg-gray-700 flex items-center justify-center">
                            <span class="font-bold">${user.charAt(0)}</span>
                        </div>
                        <div class="flex-1">
                            <div class="flex justify-between">
                                <h4 class="font-bold">${user}</h4>
                                <span class="text-xs text-gray-400">${new Date(data.lastMsg.created_at).toLocaleTimeString([], {hour:'2-digit', minute:'2-digit'})}</span>
                            </div>
                            <p class="text-sm text-gray-400 truncate">${data.lastMsg.type === 'voice' ? '🎤 Voice message' : data.lastMsg.message.substring(0,30)}</p>
                        </div>
                        ${data.unread > 0 ? `<span class="bg-red-500 text-xs px-2 py-1 rounded-full">${data.unread}</span>` : ''}
                    </div>
                </div>
            `).join('');
        }

        function openChat(user) {
            state.currentChat = user;
            document.getElementById('chats-list').classList.add('hidden');
            document.getElementById('current-chat').classList.remove('hidden');
            document.getElementById('chat-with-user').textContent = user;
            loadMessages(user);
        }

        function closeCurrentChat() {
            state.currentChat = null;
            document.getElementById('current-chat').classList.add('hidden');
            document.getElementById('chats-list').classList.remove('hidden');
        }

        async function loadMessages(user) {
            try {
                const response = await fetch(`?action=refresh&me=${state.currentUser}&room=${user}`);
                const messages = await response.json();
                renderMessages(messages);
            } catch(e) {
                console.error('Failed to load messages:', e);
            }
        }

        function renderMessages(messages) {
            const container = document.getElementById('messages-container');
            container.innerHTML = messages.map(msg => `
                <div class="${msg.username === state.currentUser ? 'text-right' : 'text-left'}">
                    ${msg.parent_id ? `<div class="text-xs text-gray-400 mb-1 ml-2"><i class="fas fa-reply"></i> Replying</div>` : ''}
                    <div class="inline-block max-w-xs lg:max-w-md rounded-lg p-3 ${msg.username === state.currentUser ? 'bg-blue-600 text-white rounded-br-none' : 'bg-gray-800 rounded-bl-none'}">
                        ${msg.type === 'text' ? `<p>${msg.message}</p>` : 
                          msg.type === 'gif' ? `<img src="${msg.message}" class="max-w-full rounded">` :
                          `<div class="flex items-center space-x-3 cursor-pointer" onclick="playVoiceMessage('${msg.message}')"><div class="voice-wave w-16 h-6 rounded"></div><span class="text-sm">0:15</span></div>`}
                        <div class="text-xs mt-1 opacity-70">${new Date(msg.created_at).toLocaleTimeString([], {hour:'2-digit', minute:'2-digit'})}</div>
                    </div>
                    <div class="mt-1 text-xs text-gray-400"><button onclick="replyToMessage(${msg.id}, '${msg.message.substring(0,30)}')" class="hover:text-white"><i class="fas fa-reply"></i> Reply</button></div>
                </div>
            `).join('');
            container.scrollTop = container.scrollHeight;
        }

        // Send Message
        async function sendMessage() {
            const input = document.getElementById('message-input');
            const message = input.value.trim();
            if(!message) return;
            
            const formData = new FormData();
            formData.append('send_msg', '1');
            formData.append('u', state.currentUser);
            formData.append('m', message);
            formData.append('r', state.currentChat || 'public');
            formData.append('type', 'text');
            if(state.replyTo) formData.append('pid', state.replyTo);
            
            try {
                await fetch('', {method: 'POST', body: formData});
                input.value = '';
                cancelReply();
                if(state.currentChat) loadMessages(state.currentChat);
                else loadChats();
            } catch(e) {
                console.error('Failed to send message:', e);
            }
        }

        document.getElementById('message-input').addEventListener('keypress', e => {
            if(e.key === 'Enter' && !e.shiftKey) {
                e.preventDefault();
                sendMessage();
            }
        });
        document.getElementById('send-btn').addEventListener('click', sendMessage);

        // Voice Messages
        async function setupVoiceRecording() {
            if(navigator.mediaDevices) {
                try {
                    const stream = await navigator.mediaDevices.getUserMedia({audio: true});
                    state.mediaRecorder = new MediaRecorder(stream);
                    state.mediaRecorder.ondataavailable = e => state.audioChunks.push(e.data);
                    state.mediaRecorder.onstop = async () => {
                        const audioBlob = new Blob(state.audioChunks, {type: 'audio/webm'});
                        const reader = new FileReader();
                        reader.readAsDataURL(audioBlob);
                        reader.onloadend = async () => {
                            const formData = new FormData();
                            formData.append('send_msg', '1');
                            formData.append('u', state.currentUser);
                            formData.append('m', reader.result);
                            formData.append('r', state.currentChat || 'public');
                            formData.append('type', 'voice');
                            await fetch('', {method: 'POST', body: formData});
                            if(state.currentChat) loadMessages(state.currentChat);
                        };
                    };
                } catch(e) {
                    console.error('Microphone access denied:', e);
                }
            }
        }

        document.getElementById('voice-btn').addEventListener('mousedown', startRecording);
        document.addEventListener('mouseup', stopRecording);

        function startRecording() {
            if(!state.mediaRecorder) {
                setupVoiceRecording();
                return;
            }
            state.isRecording = true;
            state.audioChunks = [];
            document.getElementById('voice-recording-ui').classList.remove('hidden');
            state.mediaRecorder.start();
        }

        function stopRecording() {
            if(state.isRecording && state.mediaRecorder.state === 'recording') {
                state.mediaRecorder.stop();
                document.getElementById('voice-recording-ui').classList.add('hidden');
                state.isRecording = false;
            }
        }

        // Giphy
        async function searchGiphy() {
            const query = document.getElementById('giphy-search').value || 'trending';
            try {
                const response = await fetch(`?action=get_gifs&q=${encodeURIComponent(query)}`);
                const gifs = await response.json();
                renderGiphyResults(gifs);
            } catch(e) {
                console.error('Failed to load GIFs:', e);
            }
        }

        function renderGiphyResults(gifs) {
            const container = document.getElementById('giphy-results');
            container.innerHTML = gifs.map(gif => `
                <img src="${gif.images?.fixed_height?.url || gif.images?.original?.url}" class="w-full h-24 object-cover rounded cursor-pointer" onclick="sendGif('${gif.images.fixed_height.url}')">
            `).join('');
        }

        function toggleGiphyDrawer() {
            const drawer = document.getElementById('giphy-drawer');
            drawer.classList.toggle('hidden');
            if(!drawer.classList.contains('hidden')) {
                searchGiphy();
            }
        }

        async function sendGif(gifUrl) {
            const formData = new FormData();
            formData.append('send_msg', '1');
            formData.append('u', state.currentUser);
            formData.append('m', gifUrl);
            formData.append('r', state.currentChat || 'public');
            formData.append('type', 'gif');
            
            await fetch('', {method: 'POST', body: formData});
            toggleGiphyDrawer();
            if(state.currentChat) loadMessages(state.currentChat);
        }

        // Reply System
        function replyToMessage(msgId, preview) {
            state.replyTo = msgId;
            document.getElementById('reply-preview').classList.remove('hidden');
            document.getElementById('reply-preview-text').textContent = preview;
        }

        function cancelReply() {
            state.replyTo = null;
            document.getElementById('reply-preview').classList.add('hidden');
        }

        // Profile
        async function loadProfile() {
            try {
                const response = await fetch(`?action=get_profile&username=${state.currentUser}`);
                const profile = await response.json();
                if(!profile.error) {
                    document.getElementById('profile-username').textContent = '@' + profile.username;
                    document.getElementById('profile-bio').textContent = profile.bio || 'No bio yet';
                    document.getElementById('post-count').textContent = profile.post_count || 0;
                    document.getElementById('followers-count').textContent = profile.followers || 0;
                    document.getElementById('following-count').textContent = profile.following || 0;
                }
            } catch(e) {
                console.error('Failed to load profile:', e);
            }
        }

        function editProfile() {
            document.getElementById('edit-profile-form').classList.remove('hidden');
            document.getElementById('edit-username').value = state.currentUser;
            document.getElementById('edit-bio').value = document.getElementById('profile-bio').textContent.replace('No bio yet', '');
        }

        function cancelEdit() {
            document.getElementById('edit-profile-form').classList.add('hidden');
        }

        async function saveProfile() {
            const formData = new FormData();
            formData.append('update_profile', '1');
            formData.append('username', document.getElementById('edit-username').value);
            formData.append('bio', document.getElementById('edit-bio').value);
            formData.append('current_user', state.currentUser);
            
            try {
                await fetch('', {method: 'POST', body: formData});
                state.currentUser = document.getElementById('edit-username').value;
                cancelEdit();
                loadProfile();
                alert('Profile updated!');
            } catch(e) {
                alert('Failed to update profile');
            }
        }

        // Chat Rooms
        async function loadChatRooms() {
            try {
                const response = await fetch('?action=get_chat_rooms');
                const rooms = await response.json();
                renderChatRooms(rooms);
            } catch(e) {
                console.error('Failed to load chat rooms:', e);
            }
        }

        function renderChatRooms(rooms) {
            const container = document.getElementById('chat-rooms');
            container.innerHTML = rooms.map(room => `
                <div class="bg-gray-900 rounded-lg p-4 hover:bg-gray-800 cursor-pointer">
                    <div class="flex items-center justify-between mb-2">
                        <h4 class="font-bold">${room.name}</h4>
                        <span class="text-xs bg-blue-600 px-2 py-1 rounded">${room.member_count} members</span>
                    </div>
                    <p class="text-sm text-gray-400">Movie discussion room</p>
                    <div class="mt-3 flex justify-between items-center">
                        <span class="text-xs text-gray-500">Created by ${room.creator}</span>
                        <button onclick="joinChatRoom(${room.id})" class="px-3 py-1 bg-green-600 rounded text-sm">Join</button>
                    </div>
                </div>
            `).join('');
        }

        // Chat Sync
        function startChatSync() {
            if(state.syncInterval) clearInterval(state.syncInterval);
            const interval = parseInt(document.getElementById('sync-interval').value) || 3000;
            state.syncInterval = setInterval(() => {
                if(state.currentChat) loadMessages(state.currentChat);
                updateUnreadCount();
            }, interval);
        }

        async function updateUnreadCount() {
            try {
                const response = await fetch(`?action=get_unread_count&username=${state.currentUser}`);
                const data = await response.json();
                const count = data.count || 0;
                const badge = document.getElementById('unread-count');
                if(count > 0) {
                    badge.textContent = count;
                    badge.classList.remove('hidden');
                } else {
                    badge.classList.add('hidden');
                }
            } catch(e) {
                console.error('Failed to get unread count:', e);
            }
        }

        // Utility Functions
        function likeMovie(movieId) {
            fetch(`?action=like_post&post_id=${movieId}&username=${state.currentUser}`);
        }

        function showComments(movieId) {
            const commentsDiv = document.getElementById(`comments-${movieId}`);
            commentsDiv.classList.toggle('hidden');
            if(!commentsDiv.classList.contains('hidden')) {
                loadComments(movieId);
            }
        }

        async function loadComments(movieId) {
            try {
                const response = await fetch(`?action=get_comments&post_id=${movieId}`);
                const comments = await response.json();
                const container = document.getElementById(`comments-list-${movieId}`);
                container.innerHTML = comments.map(comment => `
                    <div class="flex items-start space-x-2">
                        <div class="w-8 h-8 rounded-full bg-gray-700 flex-shrink-0"></div>
                        <div class="flex-1"><div class="bg-gray-800 rounded-lg p-3"><div class="font-bold text-sm">${comment.username}</div><p class="text-sm">${comment.comment}</p></div><div class="text-xs text-gray-400 mt-1">${new Date(comment.created_at).toLocaleDateString()}</div></div>
                    </div>
                `).join('');
            } catch(e) {
                console.error('Failed to load comments:', e);
            }
        }

        async function postComment(movieId) {
            const input = document.getElementById(`comment-input-${movieId}`);
            const comment = input.value.trim();
            if(!comment) return;
            
            const formData = new FormData();
            formData.append('post_comment', '1');
            formData.append('post_id', movieId);
            formData.append('username', state.currentUser);
            formData.append('comment', comment);
            
            try {
                await fetch('', {method: 'POST', body: formData});
                input.value = '';
                loadComments(movieId);
            } catch(e) {
                console.error('Failed to post comment:', e);
            }
        }

        function createNewChat() {
            document.getElementById('new-chat-modal').classList.remove('hidden');
        }

        function closeNewChatModal() {
            document.getElementById('new-chat-modal').classList.add('hidden');
        }

        async function startNewChat() {
            const roomName = document.getElementById('room-name').value;
            if(!roomName) return;
            
            const formData = new FormData();
            formData.append('create_chat', '1');
            formData.append('type', 'public');
            formData.append('name', roomName);
            formData.append('username', state.currentUser);
            
            try {
                await fetch('', {method: 'POST', body: formData});
                closeNewChatModal();
                alert('Chat room created!');
                loadChatRooms();
            } catch(e) {
                alert('Failed to create chat room');
            }
        }

        async function clearChatHistory() {
            if(confirm('Clear all your messages?')) {
                const formData = new FormData();
                formData.append('clear_chats', '1');
                formData.append('username', state.currentUser);
                await fetch('', {method: 'POST', body: formData});
                alert('Chat history cleared!');
                loadChats();
            }
        }

        function loadUserData() {
            const user = localStorage.getItem('moviez_user') || 'movielover';
            state.currentUser = user;
        }

        // AdBlock Protection
        window.addEventListener('load', () => {
            const originalOpen = window.open;
            window.open = function(url) {
                if(url && /ads?|popup|banner/i.test(url)) return null;
                return originalOpen.apply(window, arguments);
            };
            
            setInterval(() => {
                document.querySelectorAll('iframe').forEach(iframe => {
                    if(iframe.src && /ads?|banner/i.test(iframe.src)) iframe.remove();
                });
            }, 5000);
        });
    </script>
</body>
</html>
<?php } ?>
