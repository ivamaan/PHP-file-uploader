<?php
// Configuration
$upload_dir = 'uploads/';
$max_file_size = 100 * 1024 * 1024; // 100MB
$password_hash = password_hash('123456', PASSWORD_DEFAULT); // Set the password hash

// Allowed file extensions
$allowed_extensions = ['jpg', 'jpeg', 'png', 'gif', 'pdf', 'doc', 'docx', 'zip', 'rar', 'mp4', 'mp3', 'txt'];

// Start session for password protection
session_start();

// Initialize an array to hold error messages
$error_messages = [];

// Check for password
if (isset($_POST['password']) && password_verify($_POST['password'], $password_hash)) {
    $_SESSION['authenticated'] = true;
} elseif (!isset($_SESSION['authenticated'])) {
    // If not authenticated, show password form
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $error_messages[] = 'Incorrect password!';
    }
    ?>
    <!DOCTYPE html>
    <html>
    <head>
        <link rel="stylesheet" href="style.css">
        <title>Password Protected</title>
        <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, viewport-fit=cover">
    </head>
    <body>
        <div class="bg-image magicpattern"></div>
        <form class="passform" method="post">
            <input class="input" type="password" name="password" placeholder="......" required>
            <button type="submit">Submit</button>
        </form>
    </body>
    </html>
    <?php
    exit;
}

// Create upload directory if it doesn't exist
if (!is_dir($upload_dir)) {
    mkdir($upload_dir, 0755);
}

// Handle file upload
if (isset($_FILES['file'])) {
    $file = $_FILES['file'];
    $file_size = $file['size'];
    $file_tmp = $file['tmp_name'];

    if ($file_size > $max_file_size) {
        $error_messages[] = 'File too large!';
    } else {
        // Get the uploaded file name
        $file_name = basename($file['name']); // Sanitize file name
        $file_extension = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));

        // Check if the file extension is allowed
        if (!in_array($file_extension, $allowed_extensions)) {
            $error_messages[] = 'Invalid file type! Allowed types: ' . implode(', ', $allowed_extensions) . '.';
        } else {
            // Check MIME type
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mime_type = finfo_file($finfo, $file_tmp);
            finfo_close($finfo);

            // Basic check for allowed MIME types
                $allowed_mime_types = [
                    'image/jpeg',
                    'image/png',
                    'image/gif',
                    'application/pdf',
                    'application/msword',
                    'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                    'application/zip',
                    'application/x-rar-compressed',
                    'video/mp4',
                    'audio/mpeg',
                    'text/plain',  // Plain text files
                    'text/rtf',    // Rich text format files
                ];
            if (!in_array($mime_type, $allowed_mime_types)) {
                $error_messages[] = 'Invalid file type!';
            } else {
                // Get the current date and time for the new file name
                $current_datetime = date("Ymd-His", strtotime('-4 hours -30 minutes'));
                // Remove any special characters from file name
                $file_name_without_extension = preg_replace("/[^\p{L}\p{N}_-]/u", "", pathinfo($file_name, PATHINFO_FILENAME));
                // Create new file name
                $new_file_name = $current_datetime . "_" . $file_name_without_extension . "." . $file_extension;

                // Move the uploaded file to the upload directory with the new file name
                if (move_uploaded_file($file_tmp, $upload_dir . $new_file_name)) {
                    // Only set success message if the upload was successful
                    $success_message = 'File uploaded successfully!';
                } else {
                    $error_messages[] = 'Error uploading file!';
                }
            }
        }
    }
}

// Get list of uploaded files
$files = scandir($upload_dir);
$files = array_diff($files, array('.', '..')); // Remove '.' and '..'

// Create an associative array of files and their modification times
$file_times = [];
foreach ($files as $file) {
    $file_times[$file] = filemtime($upload_dir . $file);
}

// Sort files by modification time, newest first
arsort($file_times);

// Rebuild the files array based on the sorted order
$sorted_files = array_keys($file_times);

// Handle file deletion
if (isset($_GET['delete'])) {
    $file_name = $_GET['delete'];
    $file_path = $upload_dir . $file_name;
    if (file_exists($file_path) && unlink($file_path)) {
        // Redirect to the same page to refresh the file list
        header("Location: " . $_SERVER['PHP_SELF']);
        exit;
    } else {
        $error_messages[] = 'File not found or error deleting file!';
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, viewport-fit=cover">
    <title>File Uploader</title>
    <link rel="stylesheet" href="style.css">
    <script>
        function uploadFile(event) {
            event.preventDefault(); // Prevent form submission

            const formData = new FormData(event.target);
            const xhr = new XMLHttpRequest();
            const progressBar = document.getElementById('progress-bar');

            xhr.open('POST', '', true);

            // Update progress bar
            xhr.upload.addEventListener('progress', function(e) {
                if (e.lengthComputable) {
                    const percentComplete = (e.loaded / e.total) * 100;
                    progressBar.value = percentComplete;
                    progressBar.innerText = percentComplete.toFixed(2) + '% uploaded';
                }
            });

            // Handle the response
            xhr.onload = function() {
                const messageContainer = document.getElementById('message-container');
                messageContainer.innerHTML = ''; // Clear previous messages

                if (xhr.status === 200) {
                    // Success message
                    messageContainer.innerHTML = '<div class="alert alert-success">File uploaded successfully!</div>';
                    setTimeout(function() {
                        location.reload(); // Refresh the page after 2 seconds
                    }, 1000); // 2000 milliseconds = 2 seconds
                } else {
                    // Error message
                    messageContainer.innerHTML = '<div class="alert alert-danger">Error uploading file!</div>';
                }
            };

            xhr.send(formData);
        }
    </script>
</head>
<body>
    <div class="bg-image"></div>
    <div class="container">
        <a href="./"><h1>File Uploader</h1></a>

        <!-- Display messages -->
        <div id="message-container">
            <?php if (!empty($error_messages)): ?>
                <div class="alert alert-danger">
                    <?php foreach ($error_messages as $message): ?>
                        <p><?php echo htmlspecialchars($message); ?></p>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <!-- Display success message -->
            <?php if (isset($success_message)): ?>
                <div class="alert alert-success">
                    <?php echo htmlspecialchars($success_message); ?>
                </div>
            <?php endif; ?>
        </div>

        <form onsubmit="uploadFile(event)" enctype="multipart/form-data">
            <input type="file" name="file" required>
            <button type="submit">Upload</button>
            <progress id="progress-bar" value="0" max="100" style="width: 90%; display: block; padding: 2px; margin: auto; margin-top: 20px; border-radius: 25px; border: 0;"></progress>
        </form>
        <ul>
            <?php foreach ($sorted_files as $file_name): ?>
                <?php
                $file_path = 'uploads/' . $file_name; // Adjust the path as needed
                $file_size_human = filesize($file_path); // Get size
                $file_size_human = formatSize($file_size_human); // Format size
                ?>
                <li>
                    <a href="<?php echo htmlspecialchars($file_path); ?>" style="font-size:12px;"><?php echo htmlspecialchars($file_name); ?></a>
                    <br>
                    <span style="font-size:9px; color:#666;">Size: <?php echo $file_size_human; ?></span>
                    <br>
                    <a href="?delete=<?php echo urlencode($file_name); ?>" style="font-size:10px; color:red;" onclick="return confirm('Are you sure you want to delete this file?');">Delete</a>
                </li>
            <?php endforeach; ?>
        </ul>
        
            <p>2024</p>
    </div>

</body>
</html>

<?php
// Function to format file size
function formatSize($size) {
    if ($size >= 1024 * 1024) {
        return round($size / (1024 * 1024), 2) . ' MB';
    } elseif ($size >= 1024) {
        return round($size / 1024, 2) . ' KB';
    } else {
        return $size . ' bytes';
    }
}
?>