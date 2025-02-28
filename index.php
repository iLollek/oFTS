<?php
session_start();

// Configuration
$PASSWORD = "yourpassword"; // Change this!
$UPLOAD_DIR = "uploads/";
$MAX_FILE_SIZE = 25 * 1024 * 1024; // 25MB
$EXPIRY_TIME = 300; // 5 minutes
$FILE_LIST = $UPLOAD_DIR . "files.json";

// Ensure the uploads folder exists
if (!file_exists($UPLOAD_DIR)) {
    mkdir($UPLOAD_DIR, 0777, true);
}

// Initialize file list JSON if not exists
if (!file_exists($FILE_LIST)) {
    file_put_contents($FILE_LIST, json_encode([]));
}

// Handle authentication
if (!isset($_SESSION['authenticated']) && isset($_POST['password'])) {
    if ($_POST['password'] === $PASSWORD) {
        $_SESSION['authenticated'] = true;
    } else {
        echo "Incorrect password!";
        exit;
    }
}

if (!isset($_SESSION['authenticated'])) {
    echo '<form method="post">Enter password: <input type="password" name="password"><button type="submit">Login</button></form>';
    exit;
}

// Handle file upload
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['file'])) {
    if ($_FILES['file']['size'] > $MAX_FILE_SIZE) {
        echo "File too large!";
        exit;
    }

    $filename = time() . '_' . basename($_FILES['file']['name']);
    $filepath = $UPLOAD_DIR . $filename;
    move_uploaded_file($_FILES['file']['tmp_name'], $filepath);

    // Save file info to shared JSON
    $files = json_decode(file_get_contents($FILE_LIST), true);
    $files[] = ["name" => $filename, "timestamp" => time()];
    file_put_contents($FILE_LIST, json_encode($files));
}

// Delete expired files
$files = json_decode(file_get_contents($FILE_LIST), true);
$updatedFiles = [];
foreach ($files as $file) {
    $filepath = $UPLOAD_DIR . $file['name'];
    if (time() - $file['timestamp'] > $EXPIRY_TIME) {
        if (file_exists($filepath)) {
            unlink($filepath);
        }
    } else {
        $updatedFiles[] = $file;
    }
}
file_put_contents($FILE_LIST, json_encode($updatedFiles));

// Handle API request for file list
if (isset($_GET['list'])) {
    echo json_encode($updatedFiles);
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>File Transfer</title>
    <script>
        function setUsername() {
            let name = prompt("Enter your name:");
            if (name) {
                sessionStorage.setItem("username", name);
            }
        }

        function getUsername() {
            return sessionStorage.getItem("username") || "Anonymous";
        }

        function fetchFiles() {
            fetch("?list=1")
                .then(response => response.json())
                .then(files => {
                    let fileList = document.getElementById("file-list");
                    fileList.innerHTML = "";
                    files.forEach(file => {
                        let expired = (Date.now() / 1000 - file.timestamp) > <?= $EXPIRY_TIME ?>;
                        let fileItem = document.createElement("li");
                        fileItem.style.color = expired ? "red" : "black";
                        fileItem.style.textDecoration = expired ? "line-through" : "none";
                        fileItem.innerHTML = expired ? file.name : `<a href='<?= $UPLOAD_DIR ?>${file.name}' download>${file.name}</a>`;
                        fileList.appendChild(fileItem);
                    });
                });
        }

        // Refresh file list every 3 seconds
        setInterval(fetchFiles, 3000);
    </script>
</head>
<body onload="fetchFiles()">
    <button onclick="setUsername()">Settings (Change Name)</button>
    <form method="post" enctype="multipart/form-data">
        <input type="file" name="file">
        <button type="submit">Upload</button>
    </form>
    <ul id="file-list"></ul>
</body>
</html>
