<?php
header('Content-Type: application/json');

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Initialize or load submissions file
$submissionsFile = 'submissions.json';
if (!file_exists($submissionsFile)) {
    file_put_contents($submissionsFile, json_encode([]));
}

// Get action from request
$action = $_GET['action'] ?? '';

// Handle different actions
switch ($action) {
    case 'submissions':
        // Read and return all active submissions
        echo getSubmissions();
        break;

    case 'vote':
        // Handle vote on submission
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $id = $_GET['id'] ?? '';
            $status = $_GET['status'] ?? '';
            echo handleVote($id, $status);
        }
        break;

    default:
        // Handle new submission
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            echo handleSubmission($_POST);
        }
        break;
}

function getSubmissions() {
    global $submissionsFile;
    
    $submissions = json_decode(file_get_contents($submissionsFile), true) ?? [];
    $currentTime = time();
    
    // Filter out expired submissions (older than 20 minutes)
    $activeSubmissions = array_filter($submissions, function($sub) use ($currentTime) {
        return ($sub['timestamp'] + (20 * 60)) > $currentTime;
    });
    
    // Sort by newest first
    usort($activeSubmissions, function($a, $b) {
        return $b['timestamp'] - $a['timestamp'];
    });
    
    // Save filtered submissions back to file
    file_put_contents($submissionsFile, json_encode(array_values($activeSubmissions)));
    
    return json_encode($activeSubmissions);
}

function handleSubmission($data) {
    global $submissionsFile;
    
    // Validate required fields
    if (empty($data['district']) || empty($data['world']) || empty($data['type']) || empty($data['age'])) {
        return json_encode(['success' => false, 'error' => 'Missing required fields']);
    }
    
    // Load existing submissions
    $submissions = json_decode(file_get_contents($submissionsFile), true) ?? [];
    
    // Create new submission
    $newSubmission = [
        'id' => uniqid(),
        'district' => $data['district'],
        'world' => intval($data['world']),
        'type' => $data['type'],
        'age' => $data['age'],
        'timestamp' => time(),
        'status' => 'alive',
        'votes' => [
            'alive' => 1,
            'dead' => 0
        ],
        'deletionTime' => date('c', time() + (20 * 60)) // 20 minutes from now
    ];
    
    // Add to submissions array
    array_unshift($submissions, $newSubmission);
    
    // Save back to file
    if (file_put_contents($submissionsFile, json_encode($submissions))) {
        return json_encode(['success' => true]);
    } else {
        return json_encode(['success' => false, 'error' => 'Failed to save submission']);
    }
}

function handleVote($id, $status) {
    global $submissionsFile;
    
    if (empty($id) || !in_array($status, ['alive', 'dead'])) {
        return json_encode(['success' => false, 'error' => 'Invalid vote parameters']);
    }
    
    // Load submissions
    $submissions = json_decode(file_get_contents($submissionsFile), true) ?? [];
    
    // Find and update the submission
    foreach ($submissions as &$submission) {
        if ($submission['id'] === $id) {
            $submission['votes'][$status]++;
            $submission['status'] = ($submission['votes']['dead'] > $submission['votes']['alive']) ? 'dead' : 'alive';
            break;
        }
    }
    
    // Save changes
    if (file_put_contents($submissionsFile, json_encode($submissions))) {
        return json_encode(['success' => true]);
    } else {
        return json_encode(['success' => false, 'error' => 'Failed to save vote']);
    }
} 