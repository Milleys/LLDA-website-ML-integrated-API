<?php
// Check if the form has been submitted
if (isset($_POST["Retrain"])) {
    // Check if a dataset has been selected
    if (!empty($_POST['selected_dataset'])) {
        // Get the selected dataset value (e.g., dataset filename)
        $selectedDataset = $_POST['selected_dataset'];
        
        // Encode the dataset name to JSON
        $json_data = json_encode(['dataset' => $selectedDataset]);
    
        // Send the selected dataset to the Flask API
        $url = 'http://127.0.0.1:5000/retrain_model';  // Flask API URL
        $ch = curl_init($url);
    
        curl_setopt($ch, CURLOPT_POSTFIELDS, $json_data);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type:application/json'));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    
        // Execute the cURL request
        $result = curl_exec($ch);
        curl_close($ch);
    
        // Decode the JSON response from the Flask API
        $response = json_decode($result, true);
        
        if (isset($response['mse']) && isset($response['mae']) && isset($response['r2'])) {
            // Display the metrics returned from Flask
            $mse = $response['mse'];
            $mae = $response['mae'];
            $r2 = $response['r2'];
    
            
        } else {
            echo "<p>Error: " . htmlspecialchars($response['error']) . "</p>";
        }
    } else {
        echo "<p>No dataset selected.</p>";
    }
}




if (isset($_POST["Train"])) {
    // Check if a dataset has been selected
    if (!empty($_POST['selected_dataset'])) {
        // Get the selected dataset value (e.g., dataset filename)
        $selectedDataset = $_POST['selected_dataset'];
        
        // Encode the dataset name to JSON
        $json_data = json_encode(['dataset' => $selectedDataset]);
    
        // Send the selected dataset to the Flask API
        $url = 'http://127.0.0.1:5000/export_model';  // Flask API URL
        $ch = curl_init($url);
    
        curl_setopt($ch, CURLOPT_POSTFIELDS, $json_data);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type:application/json'));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    
        // Execute the cURL request
        $result = curl_exec($ch);
        curl_close($ch);
    
        // Decode the JSON response from the Flask API
        $response = json_decode($result, true);
        
        if (isset($response['update'])) {
            // Display the metrics returned from Flask
            $mse = $response['update'];
           
            echo "<script type='text/javascript'>alert('Model Exported Successfully');</script>";
            
        } else {
            echo "<script type='text/javascript'>alert('". htmlspecialchars($response['error']) ."');</script>";
        }
    } else {
        echo "<script type='text/javascript'>alert('No Dataset Selected');</script>";
    }
}
?>
