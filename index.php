<?php

// Predefined districts for dropdown
$districts = ['Merchant', 'Imperial', 'Port', 'Worker'];

// Function to load submissions from submissions.json
function loadSubmissions() {
    $submissions = [];
    $submissionsFile = 'submissions.json';
    
    if (file_exists($submissionsFile)) {
        $submissions = json_decode(file_get_contents($submissionsFile), true);
    }
    
    return $submissions;
}

// Function to save submissions to submissions.json
function saveSubmissions($submissions) {
    $submissionsFile = 'submissions.json';
    file_put_contents($submissionsFile, json_encode($submissions, JSON_PRETTY_PRINT));
}

// Handle POST request for submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['district'], $_POST['world'], $_POST['type'], $_POST['age'])) {
    $submissions = loadSubmissions();
    
    $submission = [
        'id' => count($submissions) ? $submissions[count($submissions) - 1]['id'] + 1 : 1,
        'district' => $_POST['district'],
        'world' => $_POST['world'],
        'type' => $_POST['type'],
        'age' => $_POST['age'],
        'status' => 'alive', // Initial status
        'votes' => ['alive' => 1, 'dead' => 0], // Initial votes
        'deletionTime' => date('c', strtotime('+8 minutes')), // Deletion time
    ];
    
    $submissions[] = $submission;
    saveSubmissions($submissions);
    
    echo json_encode(['success' => true]);
    exit;
}

// Handle GET request for submissions
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action']) && $_GET['action'] === 'submissions') {
    $submissions = loadSubmissions();
    
    // Update status if deletion time is reached
    foreach ($submissions as &$submission) {
        if (strtotime($submission['deletionTime']) <= time()) {
            $submission['status'] = 'deleted';
        }
    }
    
    // Filter out deleted submissions
    $activeSubmissions = array_filter($submissions, function($submission) {
        return $submission['status'] !== 'deleted';
    });
    
    echo json_encode(array_values($activeSubmissions));
    exit;
}

// Handle POST request for voting
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_GET['action']) && $_GET['action'] === 'vote' && isset($_GET['id'], $_GET['status'])) {
    $submissions = loadSubmissions();
    $id = $_GET['id'];
    $status = $_GET['status'];
    
    foreach ($submissions as &$submission) {
        if ($submission['id'] == $id) {
            if (isset($_POST['previousVote']) && $_POST['previousVote'] !== 'none') {
                $submission['votes'][$_POST['previousVote']]--;
            }
            
            // Check if the submission is already marked as 'dead'
            if ($submission['status'] === 'alive') {
                $submission['votes']['alive']--; // Decrease alive votes
                $submission['votes']['dead']++; // Increase dead votes
                
                // Update status based on votes (removed the alive condition)
                if ($submission['votes']['dead'] > $submission['votes']['alive']) {
                    $submission['status'] = 'dead';
                } else {
                    $submission['status'] = 'unknown'; // Handle tie case
                }
            }
            
            break;
        }
    }
    
    saveSubmissions($submissions);
    echo json_encode(['success' => true]);
    exit;
}

// Handle DELETE request for submission deletion
if ($_SERVER['REQUEST_METHOD'] === 'DELETE' && isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id'])) {
    $submissions = loadSubmissions();
    $id = $_GET['id'];
    
    foreach ($submissions as $key => $submission) {
        if ($submission['id'] == $id) {
            unset($submissions[$key]);
            break;
        }
    }
    
    saveSubmissions($submissions);
    echo json_encode(['success' => true]);
    exit;
}

// Serve the main HTML page with embedded CSS and JavaScript
header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Soul Obelisk and Scarab Tracker</title>
<style>
    /* General Styles */
    body {
        background-color: #292929;
        font-family: Arial, sans-serif;
        font-size: 1rem;
        line-height: 1.6;
        color: #ccc;
        margin: 0;
        padding: 0;
    }

    header {
        text-align: center;
        padding: 20px 0;
    }

    h1 {
        color: #f1c232; /* RuneScape gold */
        text-shadow: 2px 2px 4px rgba(0, 0, 0, 0.5);
        font-size: 36px;
        margin: 0;
    }

    main {
        max-width: 800px;
        margin: 0 auto;
        padding: 20px;
    }

    /* Form Styles */
    form {
        background-color: rgba(255, 255, 255, 0.1);
        padding: 20px;
        border-radius: 8px;
        box-shadow: 0 0 10px rgba(0, 0, 0, 0.3);
        max-width: 600px;
        margin: 0 auto;
        margin-bottom: 20px;
    }

    label {
        display: block;
        margin-bottom: 8px;
        font-weight: bold;
        color: #f1c232; /* RuneScape gold */
    }

    input[type="text"],
    select {
        width: 250px; /* Default width for most inputs */
        padding: 10px;
        margin-bottom: 12px;
        border: 1px solid #ccc;
        border-radius: 4px;
        background-color: rgba(255, 255, 255, 0.8); /* White with transparency */
        color: #333;
    }

    input#world {
        width: 50px; /* Specific width for 'world' input */
    }

    button {
        padding: 12px 24px;
        border: none;
        border-radius: 4px;
        cursor: pointer;
        font-size: 16px;
        text-transform: uppercase;
        background-color: #007bff; /* Blue for Submit button */
        color: #fff;
        transition: background-color 0.3s ease;
    }

    button:hover {
        background-color: #0056b3; /* Darker blue on hover */
    }

    /* Submission Styles */
    #submissions {
        margin-top: 20px;
    }

    .submission {
        background-color: rgba(255, 255, 255, 0.1); /* Transparent white */
        margin-bottom: 15px;
        padding: 15px;
        border-radius: 8px;
        box-shadow: 0 2px 4px rgba(0, 0, 0, 0.3);
    }

    .submission-content {
        display: flex;
        flex-wrap: wrap;
    }

    .submission p {
        margin: 5px;
        color: #ccc; /* Light gray */
    }

    .submission p span {
        font-weight: bold;
        color: #f1c232; /* RuneScape gold */
        margin-right: 5px;
    }

    .green {
        border-left: 50px solid #4CAF50; /* Green border for alive submissions */
    }

    .red {
        border-left: 50px solid #f44336; /* Red border for dead submissions */
    }

    .timer {
        color: red !important;
    }

    /* Vote Buttons */
    .vote-buttons {
        margin-top: 10px;
    }

    .vote-btn {
        margin-right: 10px;
        padding: 10px 16px;
        border: none;
        border-radius: 4px;
        cursor: pointer;
        font-size: 14px;
        text-transform: uppercase;
        transition: background-color 0.3s ease;
    }

    .vote-btn.alive {
        background-color: #4CAF50; /* Dark Green */
        color: #fff;
    }

    .vote-btn.alive:hover {
        background-color: #45a049; /* Darker green on hover */
    }

    .vote-btn.dead {
        background-color: #f44336; /* Dark Red */
        color: #fff;
    }

    .vote-btn.dead:hover {
        background-color: #d32f2f; /* Darker red on hover */
    }

    /* Responsive Font Sizes */
    @media (max-width: 1200px) {
        html {
            font-size: 14px;
        }
    }

    @media (max-width: 992px) {
        html {
            font-size: 13px;
        }
    }

    @media (max-width: 768px) {
        html {
            font-size: 12px;
        }
    }

    @media (max-width: 576px) {
        html {
            font-size: 11px;
        }
    }

    /* Footer Styles */
    #footer {
        text-align: center;
        margin-top: 20px;
        font-size: 14px;
        color: #555;
    }

    #footer a {
        color: #007bff; /* Link color */
        text-decoration: none;
    }

    #footer a:hover {
        text-decoration: underline; /* Underline on hover */
    }
</style>

	<script>
        // Updated vote function
        async function vote(id, status) {
            const votes = JSON.parse(localStorage.getItem('votes')) || {};
            const previousVote = votes[id];
        
            if (previousVote === status) return; // No change
        
            const response = await fetch(`?action=vote&id=${id}&status=${status}`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({ previousVote })
            });
        
            const result = await response.json();
            if (result.success) {
                votes[id] = status;
                localStorage.setItem('votes', JSON.stringify(votes));
                loadSubmissions();
            }
        }
        
        // Other JavaScript functions and code here
        // Ensure all script functions are defined within <script> tags
    </script>
</head>
<body>
    <header>
        <h1>Soul Obelisk and Scarab Tracker</h1>
    </header>
    <main>
        <form id="submissionForm">
            <label for="district">District:</label>
            <select id="district" name="district" required>
                <?php foreach ($districts as $district) : ?>
                    <option value="<?php echo htmlspecialchars($district); ?>"><?php echo htmlspecialchars($district); ?></option>
                <?php endforeach; ?>
            </select>

            <label for="world">World:</label>
            <input type="text" id="world" name="world" required>

            <label for="type">Type:</label>
            <select id="type" name="type">
                <option value="Soul Obelisk">Soul Obelisk</option>
                <option value="Scarab">Scarab</option>
            </select>

            <label for="age">Age:</label>
            <select id="age" name="age">
                <option value="Fresh">Fresh</option>
                <option value="Unknown">Unknown</option>
            </select>
<br><br>
            <button type="submit">Submit</button>
        </form>

        <div id="submissions"></div>
    </main>

<div id="footer">
  <p>Version 0.1 [test]</p>
  <p>- - - - - - - - - -</p>
  <p>Webpage created & designed by <a target="_blank" href="https://runeapps.org/forums/profile.php?id=3297">Astrolume</a></p>
  <p>Found a bug or have a suggestion?<a target="_blank" href="https://runeapps.org/forums/misc.php?email=3297"><br>Click here [requires RuneApps.org forum account]</a></p>
</div>

    <script>
        // Function to load submissions
        async function loadSubmissions() {
            const response = await fetch('?action=submissions');
            const submissions = await response.json();
            const submissionsDiv = document.getElementById('submissions');
            submissionsDiv.innerHTML = '';

            submissions.forEach(submission => {
                const div = createSubmissionElement(submission);
                submissionsDiv.appendChild(div);
            });
        }

// Function to create submission element
function createSubmissionElement(submission) {
    const div = document.createElement('div');
    div.id = `submission-${submission.id}`;
    div.className = `submission ${submission.status === 'alive' ? 'green' : 'red'}`;
    div.innerHTML = `
        <div class="submission-content">
            <p><span>District:</span> ${submission.district}</p>
            <p><span>World:</span> ${submission.world}</p>
            <p><span>Type:</span> ${submission.type}</p>
            <p><span>Status:</span> ${submission.status}</p>
            <p class="timer"><span>Timer:</span> <span id="timer-${submission.id}"></span></p>
        </div>
        <div class="vote-buttons">
            ${submission.status === 'alive' ? 
                `<button class="vote-btn dead" onclick="vote(${submission.id}, 'dead')">Mark as Dead (${submission.votes.dead})</button>` 
                : 
                `<button class="vote-btn dead voted" disabled>Dead (${submission.votes.dead})</button>`}
        </div>
    `;

    // Start timer if deletion time is set
    if (submission.deletionTime) {
        startTimer(submission.id, new Date(submission.deletionTime).getTime());
    }

    return div;
}

        // Function to start timer for deletion countdown
        async function startTimer(submissionId, deletionTime) {
            const timerInterval = setInterval(() => {
                const now = new Date().getTime();
                const distance = deletionTime - now;

                // Calculate minutes and seconds
                const minutes = Math.floor((distance % (1000 * 60 * 60)) / (1000 * 60));
                const seconds = Math.floor((distance % (1000 * 60)) / 1000);

                // Display the timer
                const timerElement = document.getElementById(`timer-${submissionId}`);
                if (timerElement) {
                    timerElement.textContent = `Auto delete in ${minutes}m ${seconds}s`;

                    // If the countdown is finished, delete the submission and stop the timer
                    if (distance < 0) {
                        clearInterval(timerInterval);
                        timerElement.textContent = 'Deleting...';
                        deleteSubmission(submissionId);
                    }
                } else {
                    clearInterval(timerInterval);
                }
            }, 1000);
        }

        /// Function to delete a submission
async function deleteSubmission(id) {
    const response = await fetch(`?action=delete&id=${id}`, {
        method: 'DELETE'
    });

    const result = await response.json();
    if (result.success) {
        // Reload the page after deletion
        location.reload();
    }
}

        // Function to handle voting
        async function vote(id, status) {
            const votes = JSON.parse(localStorage.getItem('votes')) || {};
            const previousVote = votes[id];

            if (previousVote === status) return; // No change

            const response = await fetch(`?action=vote&id=${id}&status=${status}`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({ previousVote })
            });

            const result = await response.json();
            if (result.success) {
                votes[id] = status;
                localStorage.setItem('votes', JSON.stringify(votes));
                loadSubmissions();
            }
        }

        // Initial load of submissions
        window.onload = () => {
            loadSubmissions();
        };

        // Form submission handling
        const submissionForm = document.getElementById('submissionForm');
        submissionForm.addEventListener('submit', async (e) => {
            e.preventDefault();

            const district = document.getElementById('district').value;
            const world = document.getElementById('world').value;
            const type = document.getElementById('type').value;
            const age = document.getElementById('age').value;

            const response = await fetch('?action=submit', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded'
                },
                body: `district=${district}&world=${world}&type=${type}&age=${age}`
            });

            const result = await response.json();
            if (result.success) {
                loadSubmissions();
                document.getElementById('district').value = '';
                document.getElementById('world').value = '';
                document.getElementById('type').value = 'Soul Obelisk';
                document.getElementById('age').value = 'Fresh';
            }
        });
    </script>
</body>
</html>
