<?php
// ==================== CONFIGURATION ====================
error_reporting(0); // Disable error display for production
ini_set('display_errors', 0); // Hide errors from users

// Database configuration
$host = 'sql201.infinityfree.com';
$user = 'if0_41110288';
$pass = 'PrincessAmi2009';
$db = 'if0_41110288_chat_app';

// Connect to database
$conn = @new mysqli($host, $user, $pass, $db);

// Check connection
if ($conn->connect_error) {
    // Silently handle connection error
    $conn = null;
}

// API Keys - Use constants for better performance
define('TMDB_API_KEY', '6cce5270f49eadc6dcef5fa7cc2050dd');
define('YOUTUBE_API_KEY', 'AIzaSyBo_MoLOas-pgBQQxeqOduKpEEuxvasbh8');
define('GIPHY_API_KEY', 'tH2BXmDEeZaOLVKx5epUQxvAsbVtKhnT');

// Set timezone
date_default_timezone_set('UTC');

// Simple API handler to avoid heavy processing
if (isset($_GET['action'])) {
    header('Content-Type: application/json');
    
    // Simple switch for basic actions
    switch($_GET['action']) {
        case 'refresh':
            handleRefresh();
            break;
        case 'unsend':
            handleUnsend();
            break;
        case 'get_movies':
            getMoviesSimple();
            break;
        case 'get_trailer':
            getTrailerSimple();
            break;
        case 'get_profile':
            echo json_encode(['username' => 'movielover', 'bio' => 'Movie enthusiast', 'post_count' => 0, 'followers' => 0, 'following' => 0]);
            break;
        default:
            echo json_encode(['status' => 'ok']);
    }
    exit;
}

// Handle POST requests
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['send_msg'])) {
        sendMessageSimple();
    }
    exit;
}

// ==================== SIMPLIFIED API FUNCTIONS ====================
function handleRefresh() {
    global $conn;
    if (!$conn) {
        echo json_encode([]);
        return;
    }
    
    $me = isset($_GET['me']) ? $conn->real_escape_string($_GET['me']) : 'anonymous';
    $room = isset($_GET['room']) ? $conn->real_escape_string($_GET['room']) : 'public';
    
    $q = "SELECT * FROM messages WHERE is_deleted = 0 ORDER BY id DESC LIMIT 20";
    $result = $conn->query($q);
    
    $messages = [];
    if ($result) {
        while($row = $result->fetch_assoc()) {
            $messages[] = $row;
        }
    }
    
    echo json_encode(array_reverse($messages));
}

function handleUnsend() {
    global $conn;
    if (!$conn) {
        echo json_encode(['status' => 'error']);
        return;
    }
    
    if (isset($_GET['id'])) {
        $id = (int)$_GET['id'];
        $me = isset($_GET['me']) ? $conn->real_escape_string($_GET['me']) : 'anonymous';
        $conn->query("UPDATE messages SET is_deleted = 1 WHERE id = $id AND username = '$me'");
    }
    
    echo json_encode(['status' => 'ok']);
}

function sendMessageSimple() {
    global $conn;
    if (!$conn) {
        echo json_encode(['success' => false]);
        return;
    }
    
    $u = isset($_POST['u']) ? htmlspecialchars($conn->real_escape_string($_POST['u'])) : 'anonymous';
    $m = isset($_POST['m']) ? $conn->real_escape_string($_POST['m']) : '';
    $r = isset($_POST['r']) ? htmlspecialchars($conn->real_escape_string($_POST['r'])) : 'public';
    $type = isset($_POST['type']) ? $conn->real_escape_string($_POST['type']) : 'text';
    
    $q = "INSERT INTO messages (username, message, recipient, type) VALUES ('$u', '$m', '$r', '$type')";
    $conn->query($q);
    
    echo json_encode(['success' => true, 'id' => $conn->insert_id]);
}

function getMoviesSimple() {
    // Simple cached response to avoid API calls on free hosting
    $movies = [
        [
            'id' => 1,
            'title' => 'Dune: Part Two',
            'overview' => 'Paul Atreides unites with Chani and the Fremen while seeking revenge against the conspirators who destroyed his family.',
            'poster_path' => '/8b8R8l88Qje9dn9OE8PY05Nx1S8.jpg',
            'vote_average' => 8.4,
            'release_date' => '2024-03-01'
        ],
        [
            'id' => 2,
            'title' => 'Godzilla x Kong: The New Empire',
            'overview' => 'The epic battle continues! Legendary Pictures\' cinematic Monsterverse follows up the explosive showdown of Godzilla vs. Kong.',
            'poster_path' => '/tMefBSflR6PGQLv7WvFPpKLZkyk.jpg',
            'vote_average' => 7.2,
            'release_date' => '2024-03-29'
        ],
        [
            'id' => 3,
            'title' => 'Kung Fu Panda 4',
            'overview' => 'Po is gearing up to become the spiritual leader of his Valley of Peace, but he needs to find and train a new Dragon Warrior.',
            'poster_path' => '/kDp1vUBnMpe8ak4rjgl3cLELqjU.jpg',
            'vote_average' => 7.1,
            'release_date' => '2024-03-08'
        ]
    ];
    
    echo json_encode($movies);
}

function getTrailerSimple() {
    // Simple response with a popular movie trailer
    echo json_encode(['videoId' => 'Way9Dexny3w']); // Dune 2 trailer
}

// Create tables if they don't exist
function createTables() {
    global $conn;
    if (!$conn) return;
    
    $conn->query("CREATE TABLE IF NOT EXISTS messages (
        id INT AUTO_INCREMENT PRIMARY KEY,
        username VARCHAR(50) NOT NULL,
        message TEXT NOT NULL,
        type VARCHAR(20) DEFAULT 'text',
        recipient VARCHAR(50) DEFAULT 'public',
        parent_id INT DEFAULT NULL,
        is_read TINYINT DEFAULT 0,
        is_deleted TINYINT DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");
    
    $conn->query("CREATE TABLE IF NOT EXISTS users (
        id INT AUTO_INCREMENT PRIMARY KEY,
        username VARCHAR(50) UNIQUE NOT NULL,
        email VARCHAR(100) DEFAULT 'user@example.com',
        avatar VARCHAR(255),
        bio TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");
    
    // Insert default user if not exists
    $conn->query("INSERT IGNORE INTO users (username, email) VALUES ('movielover', 'user@example.com')");
}

// Call create tables
if ($conn) {
    createTables();
}

// ==================== HTML FRONTEND ====================
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
        .story-ring { 
            background: linear-gradient(45deg, #f09433, #e6683c, #dc2743, #cc2366, #bc1888);
            padding: 2px; border-radius: 50%; 
        }
        @keyframes heart { 
            0% { transform: scale(0); } 
            70% { transform: scale(1.2); } 
            100% { transform: scale(1); } 
        }
        .heart-animation { animation: heart 0.8s ease-out; }
        .gradient-text { 
            background: linear-gradient(45deg, #FF6B6B, #4ECDC4);
            -webkit-background-clip: text; -webkit-text-fill-color: transparent; 
        }
        .shorts-player iframe { pointer-events: auto; }
    </style>
</head>
<body class="h-screen overflow-hidden">
    <!-- Simple Loading Screen -->
    <div id="loading" class="fixed inset-0 bg-black z-50 flex items-center justify-center">
        <div class="text-center">
            <div class="w-16 h-16 border-4 border-blue-500 border-t-transparent rounded-full animate-spin mx-auto"></div>
            <p class="mt-4 text-xl">Moviez Ultra Infinity</p>
            <p class="text-gray-400 mt-2">Loading your movie experience...</p>
        </div>
    </div>

    <!-- Main App (hidden until loaded) -->
    <div id="app" class="hidden">
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
                    <button onclick="switchTab('reels')" class="tab-btn w-full text-left py-3 px-4 rounded-lg hover:bg-gray-900 flex items-center space-x-3 text-lg">
                        <i class="fas fa-play w-6"></i><span class="hidden md:block">Reels</span>
                    </button>
                    <button onclick="switchTab('chat')" class="tab-btn w-full text-left py-3 px-4 rounded-lg hover:bg-gray-900 flex items-center space-x-3 text-lg">
                        <i class="fas fa-comment w-6"></i><span class="hidden md:block">Chat</span>
                    </button>
                    <button onclick="switchTab('profile')" class="tab-btn w-full text-left py-3 px-4 rounded-lg hover:bg-gray-900 flex items-center space-x-3 text-lg">
                        <i class="fas fa-user w-6"></i><span class="hidden md:block">Profile</span>
                    </button>
                </nav>
            </div>

            <!-- Main Content -->
            <div class="flex-1 flex flex-col h-screen">
                <!-- Mobile Header -->
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
                                    <div class="story-ring">
                                        <div class="w-16 h-16 rounded-full bg-gray-700 flex items-center justify-center">
                                            <i class="fas fa-plus text-gray-300"></i>
                                        </div>
                                    </div>
                                    <span class="text-xs mt-1">Your Story</span>
                                </div>
                                <div id="stories-container" class="flex space-x-4">
                                    <!-- Stories will be loaded here -->
                                </div>
                            </div>
                        </div>
                        <div id="feed-container" class="h-[calc(100%-80px)] overflow-y-auto p-4">
                            <!-- Movies will be loaded here -->
                        </div>
                    </div>

                    <!-- Reels Tab -->
                    <div id="reels-tab" class="tab-content h-full hidden">
                        <div class="h-full relative">
                            <div id="reels-container" class="h-full overflow-y-auto">
                                <!-- YouTube Shorts will be loaded here -->
                            </div>
                        </div>
                    </div>

                    <!-- Chat Tab -->
                    <div id="chat-tab" class="tab-content h-full hidden flex flex-col">
                        <div class="border-b border-gray-800 p-4">
                            <h2 class="text-xl font-bold">Movie Chat</h2>
                            <p class="text-gray-400 text-sm">Discuss movies with other fans</p>
                        </div>
                        <div id="messages-container" class="flex-1 overflow-y-auto p-4 space-y-3">
                            <!-- Messages will appear here -->
                        </div>
                        <div class="border-t border-gray-800 p-4">
                            <div class="flex">
                                <input type="text" id="message-input" 
                                       class="flex-1 bg-gray-900 border border-gray-700 rounded-l-full py-3 px-5 focus:outline-none focus:border-blue-500"
                                       placeholder="Type a message...">
                                <button id="send-btn" class="bg-blue-600 px-6 rounded-r-full">
                                    <i class="fas fa-paper-plane"></i>
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Profile Tab -->
                    <div id="profile-tab" class="tab-content h-full hidden overflow-y-auto p-6">
                        <div class="max-w-md mx-auto">
                            <div class="text-center mb-8">
                                <div class="w-32 h-32 rounded-full bg-gradient-to-r from-purple-500 to-pink-500 mx-auto mb-4 flex items-center justify-center text-4xl">
                                    <i class="fas fa-user"></i>
                                </div>
                                <h1 id="profile-username" class="text-2xl font-bold">@movielover</h1>
                                <p id="profile-bio" class="text-gray-400 mt-2">Movie enthusiast</p>
                            </div>
                            
                            <div class="grid grid-cols-3 gap-4 text-center mb-8">
                                <div class="bg-gray-900 rounded-lg p-4">
                                    <div class="text-2xl font-bold" id="post-count">0</div>
                                    <div class="text-gray-400 text-sm">Posts</div>
                                </div>
                                <div class="bg-gray-900 rounded-lg p-4">
                                    <div class="text-2xl font-bold" id="followers-count">0</div>
                                    <div class="text-gray-400 text-sm">Followers</div>
                                </div>
                                <div class="bg-gray-900 rounded-lg p-4">
                                    <div class="text-2xl font-bold" id="following-count">0</div>
                                    <div class="text-gray-400 text-sm">Following</div>
                                </div>
                            </div>
                            
                            <div class="space-y-4">
                                <button onclick="editProfile()" class="w-full py-3 bg-gray-900 rounded-lg hover:bg-gray-800">
                                    Edit Profile
                                </button>
                                <button onclick="switchTab('feed')" class="w-full py-3 bg-blue-600 rounded-lg hover:bg-blue-700">
                                    Back to Movies
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Trailer Modal -->
        <div id="trailer-modal" class="fixed inset-0 bg-black z-50 hidden flex items-center justify-center p-4">
            <div class="relative w-full max-w-4xl">
                <button onclick="closeTrailer()" class="absolute -top-12 right-0 text-white text-2xl">
                    <i class="fas fa-times"></i> Close
                </button>
                <div class="aspect-video bg-gray-900 rounded-lg overflow-hidden">
                    <iframe id="youtube-iframe" class="w-full h-full" 
                            frameborder="0" allowfullscreen
                            allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture">
                    </iframe>
                </div>
            </div>
        </div>
    </div>

    <script>
        // State management
        const state = {
            currentTab: 'feed',
            currentUser: 'movielover',
            messages: [],
            movies: []
        };

        // Initialize app
        document.addEventListener('DOMContentLoaded', function() {
            // Hide loading screen after 1.5 seconds
            setTimeout(() => {
                document.getElementById('loading').classList.add('hidden');
                document.getElementById('app').classList.remove('hidden');
                loadInitialData();
            }, 1500);
        });

        // Tab switching
        function switchTab(tab) {
            state.currentTab = tab;
            
            // Hide all tabs
            document.querySelectorAll('.tab-content').forEach(el => {
                el.classList.add('hidden');
            });
            
            // Show selected tab
            document.getElementById(tab + '-tab').classList.remove('hidden');
            
            // Update tab buttons
            document.querySelectorAll('.tab-btn').forEach(btn => {
                btn.classList.remove('bg-gray-900');
            });
            
            // Load tab data
            switch(tab) {
                case 'feed':
                    loadMovies();
                    loadStories();
                    break;
                case 'reels':
                    loadReels();
                    break;
                case 'chat':
                    loadMessages();
                    break;
                case 'profile':
                    loadProfile();
                    break;
            }
        }

        // Load initial data
        async function loadInitialData() {
            await loadMovies();
            loadStories();
            loadMessages();
            loadProfile();
            switchTab('feed');
        }

        // Load movies
        async function loadMovies() {
            try {
                const response = await fetch('?action=get_movies');
                state.movies = await response.json();
                renderMovies();
            } catch(error) {
                console.log('Using sample movies');
                state.movies = [
                    {
                        id: 1,
                        title: 'Dune: Part Two',
                        overview: 'Paul Atreides unites with Chani and the Fremen while seeking revenge against the conspirators who destroyed his family.',
                        poster_path: 'https://image.tmdb.org/t/p/w500/8b8R8l88Qje9dn9OE8PY05Nx1S8.jpg',
                        vote_average: 8.4,
                        release_date: '2024-03-01'
                    },
                    {
                        id: 2,
                        title: 'Godzilla x Kong: The New Empire',
                        overview: 'The epic battle continues! Legendary Pictures cinematic Monsterverse follows up the explosive showdown of Godzilla vs. Kong.',
                        poster_path: 'https://image.tmdb.org/t/p/w500/tMefBSflR6PGQLv7WvFPpKLZkyk.jpg',
                        vote_average: 7.2,
                        release_date: '2024-03-29'
                    }
                ];
                renderMovies();
            }
        }

        // Render movies
        function renderMovies() {
            const container = document.getElementById('feed-container');
            container.innerHTML = state.movies.map(movie => `
                <div class="bg-gray-900 rounded-xl overflow-hidden mb-6">
                    <div class="relative">
                        <img src="${movie.poster_path}" 
                             alt="${movie.title}"
                             class="w-full h-64 object-cover">
                        <div class="absolute top-4 right-4">
                            <button onclick="likeMovie(${movie.id})" class="p-2 bg-black/50 rounded-full">
                                <i class="far fa-heart text-white text-xl"></i>
                            </button>
                        </div>
                        <div class="absolute bottom-4 right-4">
                            <button onclick="playTrailer('${movie.title}')" 
                                    class="px-4 py-2 bg-red-600 rounded-full flex items-center space-x-2">
                                <i class="fas fa-play"></i>
                                <span>Trailer</span>
                            </button>
                        </div>
                    </div>
                    <div class="p-4">
                        <div class="flex justify-between items-start mb-2">
                            <h3 class="text-xl font-bold">${movie.title}</h3>
                            <span class="bg-yellow-500 text-black px-2 py-1 rounded text-sm font-bold">
                                ${movie.vote_average} ★
                            </span>
                        </div>
                        <p class="text-gray-300 mb-4">${movie.overview.substring(0, 150)}...</p>
                        <div class="flex items-center justify-between text-sm text-gray-400">
                            <span>${movie.release_date.substring(0, 4)}</span>
                            <div class="flex items-center space-x-4">
                                <button onclick="showComments(${movie.id})" class="hover:text-white">
                                    <i class="far fa-comment"></i> Comment
                                </button>
                                <button onclick="shareMovie('${movie.title}')" class="hover:text-white">
                                    <i class="far fa-share-square"></i> Share
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            `).join('');
            
            // Add double-tap to like
            document.querySelectorAll('img').forEach(img => {
                img.addEventListener('dblclick', function(e) {
                    const heart = document.createElement('div');
                    heart.className = 'heart-animation absolute top-1/2 left-1/2 transform -translate-x-1/2 -translate-y-1/2';
                    heart.innerHTML = '<i class="fas fa-heart text-red-500 text-6xl"></i>';
                    this.parentElement.appendChild(heart);
                    
                    setTimeout(() => heart.remove(), 800);
                    
                    // Find movie ID
                    const movieId = this.closest('.bg-gray-900').querySelector('button[onclick^="likeMovie"]')
                        .getAttribute('onclick').match(/\d+/)[0];
                    likeMovie(movieId);
                });
            });
        }

        // Load stories
        function loadStories() {
            const stories = [
                {username: 'movielover', avatar: null},
                {username: 'cinemafan', avatar: null},
                {username: 'filmcritic', avatar: null},
                {username: 'movienight', avatar: null}
            ];
            
            const container = document.getElementById('stories-container');
            container.innerHTML = stories.map(story => `
                <div class="flex flex-col items-center">
                    <div class="story-ring">
                        <div class="w-16 h-16 rounded-full bg-gray-800 flex items-center justify-center">
                            <span class="text-white font-bold text-xl">${story.username.charAt(0)}</span>
                        </div>
                    </div>
                    <span class="text-xs mt-1">${story.username}</span>
                </div>
            `).join('');
        }

        // Load messages
        async function loadMessages() {
            try {
                const response = await fetch(`?action=refresh&me=${state.currentUser}&room=public`);
                const messages = await response.json();
                state.messages = messages;
                renderMessages();
            } catch(error) {
                console.log('Using sample messages');
                state.messages = [
                    {id: 1, username: 'movielover', message: 'Anyone seen Dune 2 yet?', created_at: new Date().toISOString()},
                    {id: 2, username: 'cinemafan', message: 'Yes! The visuals are amazing!', created_at: new Date().toISOString()},
                    {id: 3, username: 'filmcritic', message: 'Best movie of the year so far', created_at: new Date().toISOString()}
                ];
                renderMessages();
            }
        }

        // Render messages
        function renderMessages() {
            const container = document.getElementById('messages-container');
            container.innerHTML = state.messages.map(msg => `
                <div class="${msg.username === state.currentUser ? 'text-right' : 'text-left'}">
                    <div class="inline-block max-w-xs rounded-lg p-3 ${msg.username === state.currentUser ? 'bg-blue-600 text-white rounded-br-none' : 'bg-gray-800 rounded-bl-none'}">
                        <div class="font-bold text-sm">${msg.username}</div>
                        <p>${msg.message}</p>
                        <div class="text-xs mt-1 opacity-70">
                            ${new Date(msg.created_at).toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'})}
                        </div>
                    </div>
                </div>
            `).join('');
            
            // Scroll to bottom
            container.scrollTop = container.scrollHeight;
        }

        // Send message
        async function sendMessage() {
            const input = document.getElementById('message-input');
            const message = input.value.trim();
            
            if (!message) return;
            
            try {
                const formData = new FormData();
                formData.append('send_msg', '1');
                formData.append('u', state.currentUser);
                formData.append('m', message);
                formData.append('r', 'public');
                formData.append('type', 'text');
                
                await fetch('', {
                    method: 'POST',
                    body: formData
                });
                
                input.value = '';
                loadMessages(); // Reload messages
            } catch(error) {
                console.log('Message sent locally');
                // Add locally
                state.messages.push({
                    id: Date.now(),
                    username: state.currentUser,
                    message: message,
                    created_at: new Date().toISOString()
                });
                renderMessages();
                input.value = '';
            }
        }

        // Play trailer
        async function playTrailer(movieTitle) {
            try {
                const response = await fetch(`?action=get_trailer&title=${encodeURIComponent(movieTitle)}`);
                const data = await response.json();
                showTrailer(data.videoId || 'Way9Dexny3w');
            } catch(error) {
                showTrailer('Way9Dexny3w'); // Default Dune 2 trailer
            }
        }

        function showTrailer(videoId) {
            const modal = document.getElementById('trailer-modal');
            const iframe = document.getElementById('youtube-iframe');
            
            iframe.src = `https://www.youtube.com/embed/${videoId}?autoplay=1&controls=1&modestbranding=1&rel=0`;
            modal.classList.remove('hidden');
        }

        function closeTrailer() {
            const modal = document.getElementById('trailer-modal');
            const iframe = document.getElementById('youtube-iframe');
            
            iframe.src = '';
            modal.classList.add('hidden');
        }

        // Load reels
        function loadReels() {
            const container = document.getElementById('reels-container');
            const shorts = [
                {id: 'video1', title: 'Movie Moments', channel: 'CineMagic'},
                {id: 'video2', title: 'Behind the Scenes', channel: 'FilmFactory'},
                {id: 'video3', title: 'Actor Interviews', channel: 'StarTalk'}
            ];
            
            container.innerHTML = shorts.map(short => `
                <div class="h-full relative">
                    <div class="h-full flex items-center justify-center bg-gray-900">
                        <div class="text-center">
                            <i class="fas fa-play text-6xl text-gray-600 mb-4"></i>
                            <h3 class="text-xl font-bold">${short.title}</h3>
                            <p class="text-gray-400">${short.channel}</p>
                            <p class="text-sm text-gray-500 mt-4">YouTube Shorts integration requires<br>additional YouTube API setup</p>
                        </div>
                    </div>
                </div>
            `).join('');
        }

        // Load profile
        async function loadProfile() {
            try {
                const response = await fetch(`?action=get_profile&username=${state.currentUser}`);
                const profile = await response.json();
                
                if (profile.username) {
                    document.getElementById('profile-username').textContent = '@' + profile.username;
                    document.getElementById('profile-bio').textContent = profile.bio || 'Movie enthusiast';
                    document.getElementById('post-count').textContent = profile.post_count || 0;
                    document.getElementById('followers-count').textContent = profile.followers || 0;
                    document.getElementById('following-count').textContent = profile.following || 0;
                }
            } catch(error) {
                // Use default values
                console.log('Using default profile data');
            }
        }

        // Event listeners
        document.getElementById('send-btn').addEventListener('click', sendMessage);
        document.getElementById('message-input').addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                sendMessage();
            }
        });

        // Utility functions
        function likeMovie(movieId) {
            console.log('Liked movie:', movieId);
            // You can implement actual like functionality here
        }

        function showComments(movieId) {
            alert('Comments feature would show here for movie ID: ' + movieId);
        }

        function shareMovie(title) {
            if (navigator.share) {
                navigator.share({
                    title: title,
                    text: 'Check out this movie on Moviez Ultra!',
                    url: window.location.href
                });
            } else {
                alert('Share: ' + title);
            }
        }

        function editProfile() {
            const newUsername = prompt('Enter new username:', state.currentUser);
            if (newUsername) {
                state.currentUser = newUsername;
                loadProfile();
                alert('Profile updated! Note: This is a demo. In a real app, this would save to database.');
            }
        }

        // Simple chat auto-refresh
        setInterval(() => {
            if (state.currentTab === 'chat') {
                loadMessages();
            }
        }, 5000);
    </script>
</body>
</html>
