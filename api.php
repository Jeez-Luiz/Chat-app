<?php
// api.php - Backend API only
error_reporting(0);
ini_set('display_errors', 0);

// Database configuration
$host = 'sql201.infinityfree.com';
$user = 'if0_41110288';
$pass = 'PrincessAmi2009';
$db = 'if0_41110288_chat_app';

// Connect to database
$conn = new mysqli($host, $user, $pass, $db);

// Check connection
if ($conn->connect_error) {
    die(json_encode(['error' => 'Database connection failed']));
}

// Set headers for JSON
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST');
header('Access-Control-Allow-Headers: Content-Type');

// Get action
$action = $_GET['action'] ?? $_POST['action'] ?? '';

// Handle actions
switch($action) {
    case 'refresh':
        handleRefresh($conn);
        break;
        
    case 'get_movies':
        getMovies($conn);
        break;
        
    case 'send':
        sendMessage($conn);
        break;
        
    case 'unsend':
        unsendMessage($conn);
        break;
        
    case 'get_profile':
        getProfile($conn);
        break;
        
    default:
        echo json_encode(['status' => 'ok', 'message' => 'API is working']);
}

// Close connection
$conn->close();

// ==================== FUNCTIONS ====================

function handleRefresh($conn) {
    $room = $conn->real_escape_string($_GET['room'] ?? 'public');
    
    // Create messages table if not exists
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
    
    // Get messages
    $query = "SELECT * FROM messages 
              WHERE recipient = '$room' AND is_deleted = 0 
              ORDER BY created_at DESC 
              LIMIT 50";
    
    $result = $conn->query($query);
    $messages = [];
    
    while($row = $result->fetch_assoc()) {
        $messages[] = $row;
    }
    
    echo json_encode(array_reverse($messages));
}

function getMovies($conn) {
    // Create movies table if not exists
    $conn->query("CREATE TABLE IF NOT EXISTS movies (
        id INT AUTO_INCREMENT PRIMARY KEY,
        tmdb_id INT,
        title VARCHAR(255) NOT NULL,
        overview TEXT,
        poster_url VARCHAR(500),
        rating DECIMAL(3,1),
        release_date DATE,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");
    
    // Check if we have movies
    $result = $conn->query("SELECT COUNT(*) as count FROM movies");
    $row = $result->fetch_assoc();
    
    if ($row['count'] == 0) {
        // Insert sample movies
        $movies = [
            ["Dune: Part Two", "Paul Atreides unites with Chani and the Fremen while seeking revenge.", "https://image.tmdb.org/t/p/w500/8b8R8l88Qje9dn9OE8PY05Nx1S8.jpg", 8.4, "2024-03-01"],
            ["Godzilla x Kong", "The epic battle continues with new threats emerging.", "https://image.tmdb.org/t/p/w500/tMefBSflR6PGQLv7WvFPpKLZkyk.jpg", 7.2, "2024-03-29"],
            ["Kung Fu Panda 4", "Po needs to find and train a new Dragon Warrior.", "https://image.tmdb.org/t/p/w500/kDp1vUBnMpe8ak4rjgl3cLELqjU.jpg", 7.1, "2024-03-08"]
        ];
        
        foreach($movies as $movie) {
            $stmt = $conn->prepare("INSERT INTO movies (title, overview, poster_url, rating, release_date) VALUES (?, ?, ?, ?, ?)");
            $stmt->bind_param("sssds", $movie[0], $movie[1], $movie[2], $movie[3], $movie[4]);
            $stmt->execute();
        }
    }
    
    // Get movies
    $result = $conn->query("SELECT * FROM movies ORDER BY created_at DESC LIMIT 10");
    $movies = [];
    
    while($row = $result->fetch_assoc()) {
        $movies[] = [
            'id' => $row['id'],
            'title' => $row['title'],
            'overview' => $row['overview'],
            'poster_path' => $row['poster_url'],
            'vote_average' => $row['rating'],
            'release_date' => $row['release_date']
        ];
    }
    
    echo json_encode($movies);
}

function sendMessage($conn) {
    $username = $conn->real_escape_string($_POST['username'] ?? 'anonymous');
    $message = $conn->real_escape_string($_POST['message'] ?? '');
    $room = $conn->real_escape_string($_POST['room'] ?? 'public');
    $type = $conn->real_escape_string($_POST['type'] ?? 'text');
    
    $stmt = $conn->prepare("INSERT INTO messages (username, message, recipient, type) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("ssss", $username, $message, $room, $type);
    
    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'id' => $conn->insert_id]);
    } else {
        echo json_encode(['success' => false, 'error' => $conn->error]);
    }
}

function unsendMessage($conn) {
    $id = (int)$_GET['id'];
    $username = $conn->real_escape_string($_GET['username'] ?? '');
    
    $stmt = $conn->prepare("UPDATE messages SET is_deleted = 1 WHERE id = ? AND username = ?");
    $stmt->bind_param("is", $id, $username);
    $stmt->execute();
    
    echo json_encode(['success' => true]);
}

function getProfile($conn) {
    $username = $conn->real_escape_string($_GET['username'] ?? 'movielover');
    
    // Create users table if not exists
    $conn->query("CREATE TABLE IF NOT EXISTS users (
        id INT AUTO_INCREMENT PRIMARY KEY,
        username VARCHAR(50) UNIQUE NOT NULL,
        email VARCHAR(100),
        avatar VARCHAR(255),
        bio TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");
    
    // Insert default user if not exists
    $conn->query("INSERT IGNORE INTO users (username, email, bio) VALUES ('movielover', 'user@example.com', 'Movie enthusiast & critic')");
    
    // Get user
    $stmt = $conn->prepare("SELECT * FROM users WHERE username = ?");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $user = $result->fetch_assoc();
        
        // Get counts
        $postCount = $conn->query("SELECT COUNT(*) as count FROM movies")->fetch_assoc()['count'];
        $followers = rand(100, 500);
        $following = rand(50, 200);
        
        echo json_encode([
            'username' => $user['username'],
            'bio' => $user['bio'],
            'post_count' => $postCount,
            'followers' => $followers,
            'following' => $following
        ]);
    } else {
        echo json_encode([
            'username' => $username,
            'bio' => 'Movie enthusiast',
            'post_count' => 0,
            'followers' => 0,
            'following' => 0
        ]);
    }
}
?>
