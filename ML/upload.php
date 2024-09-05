<?php
#UPLOAD DATA SET

// Set the target directory
$target_dir = "C:/Users/John Wilson/Desktop/Datamodel/";
$url = "prediction_MI.php";
// Check if the form is submitted
if ($_SERVER['REQUEST_METHOD'] == 'POST') {

    // Get the uploaded file information
    $file = $_FILES['dataset'];
    $file_name = basename($file['name']);
    $target_file = $target_dir . $file_name;
    
    // Validate if the file is a CSV
    $file_type = pathinfo($file_name, PATHINFO_EXTENSION);

    // Check for errors in file upload
    if ($file['error'] === UPLOAD_ERR_OK) {
        
        // Server-side validation for CSV extension
        if (strtolower($file_type) != "csv") {
            echo "Sorry, only CSV files are allowed.";
        } else {
            // Move the uploaded file to the specified folder if it's a CSV
            if (move_uploaded_file($file['tmp_name'], $target_file)) {
                echo "The file " . htmlspecialchars($file_name) . " has been uploaded successfully.";
                header('Location: '.$url);
            } else {
                echo "Sorry, there was an error uploading your file.";
            }
        }
    } else {
        echo "Error in file upload. Error code: " . $file['error'];
    }
}



?>