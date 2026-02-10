<?php
// Turn off errors for InfinityFree
error_reporting(0);
ini_set('display_errors', 0);
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
        .voice-wave { 
            background: linear-gradient(90deg, 
                rgba(255,255,255,0.8) 0%, 
                rgba(255,255,255,0.4) 25%, 
                rgba(255,255,255,0.6) 50%, 
                rgba(255,255,255,0.3) 75%, 
                rgba(255,255,255,0.7) 100%);
            background-size: 200% 100%; 
            animation: wave 1.5s linear infinite; 
        }
        @keyframes wave { 
            0% { background-position: 200% 0; } 
            100% { background-position: -200% 0; } 
        }
    </style>
</head>
<body class="h-screen overflow-hidden">
    <!-- Loading Screen -->
    <div id="loading" class="fixed inset-0 bg-black z-50 flex items-center justify-center">
        <div class="text-center">
            <div class="w-16 h-16 border-4 border-blue-500 border-t-transparent rounded-full animate-spin mx-auto"></div>
            <p class="mt-4 text-xl">Moviez Ultra</p>
            <p class="text-gray-400 mt-2">Starting your movie experience...</p>
        </div>
    </div>

    <!-- Main App -->
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
                        <span id="unread-count" class="bg-red-500 text-xs px-2 py-1 rounded-full hidden">0</span>
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
                                        <div class="w-16 h-16 rounded-full bg-gradient-to-r from-purple-500 to-pink-500 flex items-center justify-center">
                                            <i class="fas fa-plus text-white"></i>
                                        </div>
                                    </div>
                                    <span class="text-xs mt-1">Your Story</span>
                                </div>
                                <div id="stories-container" class="flex space-x-4"></div>
                            </div>
                        </div>
                        <div id="feed-container" class="h-[calc(100%-80px)] overflow-y-auto p-4"></div>
                    </div>

                    <!-- Reels Tab -->
                    <div id="reels-tab" class="tab-content h-full hidden">
                        <div class="h-full relative bg-black">
                            <div id="reels-container" class="h-full overflow-y-auto snap-y snap-mandatory"></div>
                            <div class="absolute right-4 bottom-20 space-y-2">
                                <button id="mute-btn" class="block text-white bg-black/50 rounded-full p-3">
                                    <i class="fas fa-volume-up"></i>
                                </button>
                                <button id="pause-btn" class="block text-white bg-black/50 rounded-full p-3">
                                    <i class="fas fa-pause"></i>
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Chat Tab -->
                    <div id="chat-tab" class="tab-content h-full hidden flex flex-col">
                        <div class="border-b border-gray-800 p-4">
                            <h2 class="text-xl font-bold">Movie Chat</h2>
                            <p class="text-gray-400 text-sm">Talk about movies with fans worldwide</p>
                        </div>
                        
                        <!-- Chat Selection -->
                        <div id="chat-list" class="flex-1 overflow-y-auto p-4 space-y-2">
                            <div class="p-3 bg-gray-900 rounded-lg hover:bg-gray-800 cursor-pointer" onclick="openChat('public')">
                                <div class="flex items-center justify-between">
                                    <div class="flex items-center space-x-3">
                                        <div class="w-10 h-10 rounded-full bg-blue-600 flex items-center justify-center">
                                            <i class="fas fa-users"></i>
                                        </div>
                                        <div>
                                            <h4 class="font-bold">Public Chat Room</h4>
                                            <p class="text-sm text-gray-400">Join the conversation</p>
                                        </div>
                                    </div>
                                    <span class="text-xs text-gray-400">100+ online</span>
                                </div>
                            </div>
                            
                            <div class="p-3 bg-gray-900 rounded-lg hover:bg-gray-800 cursor-pointer" onclick="openChat('cinema_fans')">
                                <div class="flex items-center space-x-3">
                                    <div class="w-10 h-10 rounded-full bg-purple-600 flex items-center justify-center">
                                        <i class="fas fa-film"></i>
                                    </div>
                                    <div>
                                        <h4 class="font-bold">Cinema Fans</h4>
                                        <p class="text-sm text-gray-400">Discuss latest releases</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Active Chat (hidden by default) -->
                        <div id="active-chat" class="h-full hidden flex flex-col">
                            <div class="border-b border-gray-800 p-4 flex items-center space-x-3">
                                <button onclick="closeChat()" class="md:hidden">
                                    <i class="fas fa-arrow-left"></i>
                                </button>
                                <div class="w-10 h-10 rounded-full bg-gray-700 flex items-center justify-center">
                                    <i class="fas fa-users"></i>
                                </div>
                                <div>
                                    <h3 id="chat-room-name" class="font-bold">Public Chat</h3>
                                    <p id="chat-status" class="text-sm text-gray-400">Online</p>
                                </div>
                            </div>
                            
                            <div id="messages-container" class="flex-1 overflow-y-auto p-4 space-y-3"></div>
                            
                            <div class="border-t border-gray-800 p-4">
                                <div class="flex items-center space-x-2">
                                    <button onclick="toggleGiphy()" class="p-2 rounded-full hover:bg-gray-800">
                                        <i class="fas fa-gift text-purple-400"></i>
                                    </button>
                                    <button id="voice-btn" class="p-2 rounded-full hover:bg-gray-800">
                                        <i class="fas fa-microphone text-red-400"></i>
                                    </button>
                                    <div class="flex-1 relative">
                                        <input type="text" id="message-input" 
                                               class="w-full bg-gray-900 border border-gray-700 rounded-full py-3 px-5 pr-12 focus:outline-none focus:border-blue-500"
                                               placeholder="Type a message...">
                                        <button id="send-btn" class="absolute right-3 top-2.5 text-blue-500">
                                            <i class="fas fa-paper-plane"></i>
                                        </button>
                                    </div>
                                </div>
                                
                                <!-- Voice Recording UI -->
                                <div id="voice-recording-ui" class="mt-3 hidden items-center justify-between">
                                    <div class="flex items-center space-x-3">
                                        <div class="voice-wave w-24 h-8 rounded"></div>
                                        <span id="recording-time" class="text-sm">0:00</span>
                                    </div>
                                    <div class="flex space-x-2">
                                        <button id="cancel-recording" class="px-4 py-1 bg-red-600 rounded">Cancel</button>
                                        <button id="send-recording" class="px-4 py-1 bg-green-600 rounded">Send</button>
                                    </div>
                                </div>
                                
                                <!-- Giphy Drawer -->
                                <div id="giphy-drawer" class="mt-3 hidden bg-gray-900 rounded-lg p-3">
                                    <div class="flex justify-between mb-2">
                                        <input type="text" id="giphy-search" 
                                               class="flex-1 bg-gray-800 border border-gray-700 rounded py-2 px-3 mr-2"
                                               placeholder="Search GIFs...">
                                        <button onclick="toggleGiphy()" class="px-3 bg-gray-700 rounded">
                                            <i class="fas fa-times"></i>
                                        </button>
                                    </div>
                                    <div id="giphy-results" class="grid grid-cols-3 gap-2 max-h-40 overflow-y-auto"></div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Profile Tab -->
                    <div id="profile-tab" class="tab-content h-full hidden overflow-y-auto p-6">
                        <div class="max-w-md mx-auto">
                            <div class="text-center mb-8">
                                <div class="relative mx-auto w-32 h-32 mb-4">
                                    <div class="w-full h-full rounded-full bg-gradient-to-r from-purple-500 to-pink-500 flex items-center justify-center text-4xl">
                                        <i class="fas fa-user"></i>
                                    </div>
                                    <button onclick="uploadAvatar()" class="absolute bottom-0 right-0 bg-blue-600 rounded-full p-2">
                                        <i class="fas fa-camera"></i>
                                    </button>
                                </div>
                                <h1 id="profile-username" class="text-2xl font-bold">@movielover</h1>
                                <p id="profile-bio" class="text-gray-400 mt-2">Movie enthusiast & critic</p>
                            </div>
                            
                            <div class="grid grid-cols-3 gap-4 text-center mb-8">
                                <div class="bg-gray-900 rounded-lg p-4">
                                    <div class="text-2xl font-bold" id="post-count">12</div>
                                    <div class="text-gray-400 text-sm">Posts</div>
                                </div>
                                <div class="bg-gray-900 rounded-lg p-4">
                                    <div class="text-2xl font-bold" id="followers-count">245</div>
                                    <div class="text-gray-400 text-sm">Followers</div>
                                </div>
                                <div class="bg-gray-900 rounded-lg p-4">
                                    <div class="text-2xl font-bold" id="following-count">156</div>
                                    <div class="text-gray-400 text-sm">Following</div>
                                </div>
                            </div>
                            
                            <div class="space-y-4">
                                <button onclick="editProfile()" class="w-full py-3 bg-gray-900 rounded-lg hover:bg-gray-800">
                                    <i class="fas fa-edit mr-2"></i> Edit Profile
                                </button>
                                <button onclick="showSettings()" class="w-full py-3 bg-gray-900 rounded-lg hover:bg-gray-800">
                                    <i class="fas fa-cog mr-2"></i> Settings
                                </button>
                                <button onclick="switchTab('feed')" class="w-full py-3 bg-blue-600 rounded-lg hover:bg-blue-700">
                                    <i class="fas fa-film mr-2"></i> Browse Movies
                                </button>
                            </div>
                            
                            <!-- Edit Profile Form -->
                            <div id="edit-profile-form" class="mt-6 hidden bg-gray-900 rounded-lg p-6">
                                <h3 class="text-xl font-bold mb-4">Edit Profile</h3>
                                <div class="space-y-4">
                                    <div>
                                        <label class="block text-sm mb-1">Username</label>
                                        <input type="text" id="edit-username" 
                                               class="w-full bg-gray-800 border border-gray-700 rounded py-2 px-3"
                                               value="movielover">
                                    </div>
                                    <div>
                                        <label class="block text-sm mb-1">Bio</label>
                                        <textarea id="edit-bio" 
                                                  class="w-full bg-gray-800 border border-gray-700 rounded py-2 px-3 h-24">Movie enthusiast & critic</textarea>
                                    </div>
                                    <div class="flex justify-end space-x-2">
                                        <button onclick="cancelEdit()" class="px-4 py-2 border border-gray-600 rounded">
                                            Cancel
                                        </button>
                                        <button onclick="saveProfile()" class="px-4 py-2 bg-blue-600 rounded">
                                            Save Changes
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Movie Trailer Modal -->
        <div id="trailer-modal" class="fixed inset-0 bg-black bg-opacity-90 z-50 hidden flex items-center justify-center p-4">
            <div class="relative w-full max-w-4xl">
                <button onclick="closeTrailer()" class="absolute -top-10 right-0 text-white text-2xl">
                    <i class="fas fa-times"></i>
                </button>
                <div id="youtube-player" class="w-full aspect-video bg-black rounded-lg overflow-hidden">
                    <iframe id="youtube-iframe" class="w-full h-full" 
                            frameborder="0" allowfullscreen
                            allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture">
                    </iframe>
                </div>
            </div>
        </div>

        <!-- Voice Player Modal -->
        <div id="voice-player-modal" class="fixed inset-0 bg-black bg-opacity-90 z-50 hidden flex items-center justify-center">
            <div class="bg-gray-900 rounded-lg p-6 max-w-md w-full">
                <h3 class="text-xl font-bold mb-4">Voice Message</h3>
                <div class="voice-wave w-full h-12 rounded mb-4"></div>
                <div class="flex items-center justify-center space-x-4">
                    <button id="play-voice" class="p-3 rounded-full bg-blue-600">
                        <i class="fas fa-play"></i>
                    </button>
                    <button id="pause-voice" class="p-3 rounded-full bg-gray-700">
                        <i class="fas fa-pause"></i>
                    </button>
                    <span id="voice-duration" class="text-lg">0:00</span>
                </div>
                <button onclick="closeVoicePlayer()" class="mt-6 w-full py-2 border border-gray-600 rounded">
                    Close
                </button>
            </div>
        </div>
    </div>

    <script>
        // Configuration
        const CONFIG = {
            TMDB_API_KEY: '6cce5270f49eadc6dcef5fa7cc2050dd',
            YOUTUBE_API_KEY: 'AIzaSyBo_MoLOas-pgBQQxeqOduKpEEuxvasbh8',
            GIPHY_API_KEY: 'tH2BXmDEeZaOLVKx5epUQxvAsbVtKhnT'
        };

        // State
        let state = {
            currentTab: 'feed',
            currentUser: 'movielover',
            currentChat: null,
            movies: [],
            messages: [],
            mediaRecorder: null,
            audioChunks: [],
            isRecording: false,
            youtubePlayer: null
        };

        // Initialize
        document.addEventListener('DOMContentLoaded', function() {
            setTimeout(() => {
                document.getElementById('loading').classList.add('hidden');
                document.getElementById('app').classList.remove('hidden');
                initApp();
            }, 1000);
        });

        function initApp() {
            switchTab('feed');
            setupEventListeners();
            loadMovies();
            loadStories();
            updateUnreadCount();
        }

        function setupEventListeners() {
            // Send message
            document.getElementById('send-btn').addEventListener('click', sendMessage);
            document.getElementById('message-input').addEventListener('keypress', function(e) {
                if (e.key === 'Enter') sendMessage();
            });
            
            // Voice recording
            document.getElementById('voice-btn').addEventListener('mousedown', startRecording);
            document.addEventListener('mouseup', stopRecording);
            document.getElementById('cancel-recording').addEventListener('click', cancelRecording);
            document.getElementById('send-recording').addEventListener('click', sendRecording);
            
            // Giphy search
            document.getElementById('giphy-search').addEventListener('input', searchGiphy);
            
            // YouTube controls
            document.getElementById('mute-btn').addEventListener('click', toggleMute);
            document.getElementById('pause-btn').addEventListener('click', togglePause);
            
            // Voice player
            document.getElementById('play-voice').addEventListener('click', playVoiceNote);
            document.getElementById('pause-voice').addEventListener('click', pauseVoiceNote);
            
            // Setup voice recording
            setupVoiceRecording();
        }

        function switchTab(tab) {
            state.currentTab = tab;
            
            // Hide all tabs
            document.querySelectorAll('.tab-content').forEach(el => {
                el.classList.add('hidden');
            });
            
            // Show selected tab
            document.getElementById(tab + '-tab').classList.remove('hidden');
            
            // Update active button
            document.querySelectorAll('.tab-btn').forEach(btn => {
                btn.classList.remove('bg-gray-900');
            });
            event?.target?.closest('.tab-btn')?.classList.add('bg-gray-900');
            
            // Load tab-specific data
            switch(tab) {
                case 'feed':
                    loadMovies();
                    break;
                case 'reels':
                    loadYouTubeShorts();
                    break;
                case 'chat':
                    if (!state.currentChat) {
                        document.getElementById('chat-list').classList.remove('hidden');
                        document.getElementById('active-chat').classList.add('hidden');
                    }
                    break;
            }
        }

        // Movies
        async function loadMovies() {
            try {
                const response = await fetch('api.php?action=get_movies');
                state.movies = await response.json();
                renderMovies();
            } catch(error) {
                // Fallback to sample data
                state.movies = getSampleMovies();
                renderMovies();
            }
        }

        function getSampleMovies() {
            return [
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
                    overview: 'The epic battle continues! The explosive showdown of Godzilla vs. Kong with new threats emerging.',
                    poster_path: 'https://image.tmdb.org/t/p/w500/tMefBSflR6PGQLv7WvFPpKLZkyk.jpg',
                    vote_average: 7.2,
                    release_date: '2024-03-29'
                },
                {
                    id: 3,
                    title: 'Kung Fu Panda 4',
                    overview: 'Po is gearing up to become the spiritual leader of his Valley of Peace, but needs to find a new Dragon Warrior.',
                    poster_path: 'https://image.tmdb.org/t/p/w500/kDp1vUBnMpe8ak4rjgl3cLELqjU.jpg',
                    vote_average: 7.1,
                    release_date: '2024-03-08'
                }
            ];
        }

        function renderMovies() {
            const container = document.getElementById('feed-container');
            container.innerHTML = state.movies.map(movie => `
                <div class="bg-gray-900 rounded-xl overflow-hidden mb-6 movie-card">
                    <div class="relative">
                        <img src="${movie.poster_path}" 
                             alt="${movie.title}"
                             class="w-full h-64 object-cover">
                        <div class="absolute top-4 right-4">
                            <button onclick="likeMovie(${movie.id})" class="like-btn p-2 bg-black/50 rounded-full">
                                <i class="far fa-heart text-white text-xl"></i>
                            </button>
                        </div>
                        <div class="absolute bottom-4 right-4">
                            <button onclick="playTrailer('${movie.title}')" 
                                    class="play-btn px-4 py-2 bg-red-600 rounded-full flex items-center space-x-2">
                                <i class="fas fa-play"></i>
                                <span>Trailer</span>
                            </button>
                        </div>
                    </div>
                    <div class="p-4">
                        <div class="flex justify-between items-start mb-2">
                            <h3 class="text-xl font-bold">${movie.title}</h3>
                            <span class="bg-yellow-500 text-black px-2 py-1 rounded text-sm font-bold">
                                ${movie.vote_average.toFixed(1)} ★
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
            document.querySelectorAll('.movie-card').forEach(card => {
                card.addEventListener('dblclick', function(e) {
                    const heart = document.createElement('div');
                    heart.className = 'heart-animation absolute top-1/2 left-1/2 transform -translate-x-1/2 -translate-y-1/2';
                    heart.innerHTML = '<i class="fas fa-heart text-red-500 text-6xl"></i>';
                    this.querySelector('.relative').appendChild(heart);
                    
                    setTimeout(() => heart.remove(), 800);
                    
                    const movieId = this.querySelector('.like-btn').getAttribute('onclick').match(/\d+/)[0];
                    likeMovie(movieId);
                });
            });
        }

        // Stories
        function loadStories() {
            const stories = [
                {username: 'cinemafan', avatar: null},
                {username: 'filmcritic', avatar: null},
                {username: 'movielover', avatar: null},
                {username: 'moviebuff', avatar: null},
                {username: 'reelviewer', avatar: null}
            ];
            
            const container = document.getElementById('stories-container');
            container.innerHTML = stories.map(story => `
                <div class="flex flex-col items-center cursor-pointer" onclick="viewStory('${story.username}')">
                    <div class="story-ring">
                        <div class="w-16 h-16 rounded-full bg-gradient-to-r from-blue-500 to-purple-500 flex items-center justify-center">
                            <span class="text-white font-bold text-xl">${story.username.charAt(0)}</span>
                        </div>
                    </div>
                    <span class="text-xs mt-1">${story.username}</span>
                </div>
            `).join('');
        }

        // Chat
        function openChat(room) {
            state.currentChat = room;
            document.getElementById('chat-list').classList.add('hidden');
            document.getElementById('active-chat').classList.remove('hidden');
            document.getElementById('chat-room-name').textContent = 
                room === 'public' ? 'Public Chat Room' : 
                room === 'cinema_fans' ? 'Cinema Fans' : room;
            
            loadMessages(room);
        }

        function closeChat() {
            state.currentChat = null;
            document.getElementById('active-chat').classList.add('hidden');
            document.getElementById('chat-list').classList.remove('hidden');
        }

        async function loadMessages(room) {
            try {
                const response = await fetch(`api.php?action=refresh&room=${room}`);
                const messages = await response.json();
                renderMessages(messages);
            } catch(error) {
                // Sample messages
                const messages = [
                    {id: 1, username: 'movielover', message: 'Anyone seen the new movie?', created_at: new Date().toISOString()},
                    {id: 2, username: 'cinemafan', message: 'Yes, it was amazing!', created_at: new Date().toISOString()},
                    {id: 3, username: 'filmcritic', message: 'The cinematography was stunning', created_at: new Date().toISOString()}
                ];
                renderMessages(messages);
            }
        }

        function renderMessages(messages) {
            const container = document.getElementById('messages-container');
            container.innerHTML = messages.map(msg => `
                <div class="${msg.username === state.currentUser ? 'text-right' : 'text-left'}">
                    <div class="inline-block max-w-xs lg:max-w-md rounded-lg p-3 
                                ${msg.username === state.currentUser ? 
                                  'bg-blue-600 text-white rounded-br-none' : 
                                  'bg-gray-800 rounded-bl-none'}">
                        <div class="font-bold text-sm">${msg.username}</div>
                        <p>${msg.message}</p>
                        <div class="text-xs mt-1 opacity-70">
                            ${new Date(msg.created_at).toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'})}
                        </div>
                    </div>
                </div>
            `).join('');
            
            container.scrollTop = container.scrollHeight;
        }

        async function sendMessage() {
            const input = document.getElementById('message-input');
            const message = input.value.trim();
            
            if (!message) return;
            
            try {
                const formData = new FormData();
                formData.append('action', 'send');
                formData.append('username', state.currentUser);
                formData.append('message', message);
                formData.append('room', state.currentChat || 'public');
                
                await fetch('api.php', {
                    method: 'POST',
                    body: formData
                });
                
                input.value = '';
                loadMessages(state.currentChat || 'public');
            } catch(error) {
                // Add locally
                const messages = JSON.parse(localStorage.getItem('local_messages') || '[]');
                messages.push({
                    id: Date.now(),
                    username: state.currentUser,
                    message: message,
                    created_at: new Date().toISOString()
                });
                localStorage.setItem('local_messages', JSON.stringify(messages));
                input.value = '';
                loadMessages(state.currentChat || 'public');
            }
        }

        // YouTube Shorts/Reels
        async function loadYouTubeShorts() {
            const container = document.getElementById('reels-container');
            container.innerHTML = `
                <div class="h-full snap-start flex items-center justify-center bg-black">
                    <div class="text-center p-8">
                        <i class="fas fa-play-circle text-6xl text-gray-600 mb-4"></i>
                        <h3 class="text-2xl font-bold mb-2">Movie Shorts</h3>
                        <p class="text-gray-400 mb-6">Watch trending movie clips and trailers</p>
                        <button onclick="playTrailer('movie clips')" class="px-6 py-3 bg-red-600 rounded-full">
                            <i class="fas fa-play mr-2"></i> Play Demo
                        </button>
                    </div>
                </div>
            `;
        }

        function toggleMute() {
            const btn = document.getElementById('mute-btn');
            const icon = btn.querySelector('i');
            if (icon.classList.contains('fa-volume-up')) {
                icon.classList.replace('fa-volume-up', 'fa-volume-mute');
            } else {
                icon.classList.replace('fa-volume-mute', 'fa-volume-up');
            }
        }

        function togglePause() {
            const btn = document.getElementById('pause-btn');
            const icon = btn.querySelector('i');
            if (icon.classList.contains('fa-pause')) {
                icon.classList.replace('fa-pause', 'fa-play');
            } else {
                icon.classList.replace('fa-play', 'fa-pause');
            }
        }

        // Voice Messages
        async function setupVoiceRecording() {
            if (navigator.mediaDevices && navigator.mediaDevices.getUserMedia) {
                try {
                    const stream = await navigator.mediaDevices.getUserMedia({ audio: true });
                    state.mediaRecorder = new MediaRecorder(stream);
                    
                    state.mediaRecorder.ondataavailable = event => {
                        if (event.data.size > 0) {
                            state.audioChunks.push(event.data);
                        }
                    };
                    
                    state.mediaRecorder.onstop = () => {
                        const audioBlob = new Blob(state.audioChunks, { type: 'audio/webm' });
                        state.audioBlob = audioBlob;
                    };
                } catch(error) {
                    console.log('Voice recording not available');
                }
            }
        }

        function startRecording() {
            if (!state.mediaRecorder) {
                alert('Microphone access required for voice messages');
                return;
            }
            
            state.isRecording = true;
            state.audioChunks = [];
            document.getElementById('voice-recording-ui').classList.remove('hidden');
            state.mediaRecorder.start();
        }

        function stopRecording() {
            if (state.isRecording && state.mediaRecorder) {
                state.mediaRecorder.stop();
                state.isRecording = false;
            }
        }

        function cancelRecording() {
            state.isRecording = false;
            state.audioChunks = [];
            document.getElementById('voice-recording-ui').classList.add('hidden');
        }

        function sendRecording() {
            if (state.audioBlob) {
                alert('Voice message sent! (In a real app, this would upload to server)');
                document.getElementById('voice-recording-ui').classList.add('hidden');
                state.audioBlob = null;
            }
        }

        // Giphy
        async function searchGiphy() {
            const query = document.getElementById('giphy-search').value;
            if (!query) return;
            
            try {
                const response = await fetch(`https://api.giphy.com/v1/gifs/search?api_key=${CONFIG.GIPHY_API_KEY}&q=${encodeURIComponent(query)}&limit=12`);
                const data = await response.json();
                renderGiphyResults(data.data);
            } catch(error) {
                console.log('Giphy search failed');
            }
        }

        function renderGiphyResults(gifs) {
            const container = document.getElementById('giphy-results');
            container.innerHTML = gifs.map(gif => `
                <img src="${gif.images.fixed_height.url}" 
                     class="w-full h-24 object-cover rounded cursor-pointer"
                     onclick="sendGif('${gif.images.fixed_height.url}')">
            `).join('');
        }

        function toggleGiphy() {
            const drawer = document.getElementById('giphy-drawer');
            drawer.classList.toggle('hidden');
            if (!drawer.classList.contains('hidden')) {
                searchGiphy();
            }
        }

        function sendGif(gifUrl) {
            alert(`GIF sent: ${gifUrl}`);
            toggleGiphy();
        }

        // Movie Trailers
        async function playTrailer(movieTitle) {
            try {
                const response = await fetch(`https://www.googleapis.com/youtube/v3/search?part=snippet&q=${encodeURIComponent(movieTitle + ' trailer')}&key=${CONFIG.YOUTUBE_API_KEY}&type=video&maxResults=1`);
                const data = await response.json();
                
                if (data.items && data.items.length > 0) {
                    const videoId = data.items[0].id.videoId;
                    showTrailer(videoId);
                } else {
                    showTrailer('Way9Dexny3w'); // Default Dune 2 trailer
                }
            } catch(error) {
                showTrailer('Way9Dexny3w'); // Fallback
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

        // Profile
        function editProfile() {
            document.getElementById('edit-profile-form').classList.remove('hidden');
        }

        function cancelEdit() {
            document.getElementById('edit-profile-form').classList.add('hidden');
        }

        function saveProfile() {
            const newUsername = document.getElementById('edit-username').value;
            const newBio = document.getElementById('edit-bio').value;
            
            state.currentUser = newUsername;
            document.getElementById('profile-username').textContent = '@' + newUsername;
            document.getElementById('profile-bio').textContent = newBio;
            
            cancelEdit();
            alert('Profile updated! (Changes saved locally)');
        }

        function uploadAvatar() {
            alert('In a real app, this would open file picker for avatar upload');
        }

        function showSettings() {
            alert('Settings panel would open here');
        }

        // Utility functions
        function likeMovie(movieId) {
            console.log('Liked movie:', movieId);
            // In real app, send to server
        }

        function showComments(movieId) {
            alert(`Comments for movie ${movieId} would appear here`);
        }

        function shareMovie(title) {
            if (navigator.share) {
                navigator.share({
                    title: title,
                    text: 'Check out this movie on Moviez Ultra!',
                    url: window.location.href
                });
            } else {
                alert(`Share: ${title}`);
            }
        }

        function viewStory(username) {
            alert(`Viewing ${username}'s story`);
        }

        function updateUnreadCount() {
            // Simulate unread messages
            const count = Math.floor(Math.random() * 5);
            const badge = document.getElementById('unread-count');
            if (count > 0) {
                badge.textContent = count;
                badge.classList.remove('hidden');
            } else {
                badge.classList.add('hidden');
            }
        }

        // Auto-update chat
        setInterval(() => {
            if (state.currentTab === 'chat' && state.currentChat) {
                loadMessages(state.currentChat);
            }
            updateUnreadCount();
        }, 5000);
    </script>
</body>
</html>
