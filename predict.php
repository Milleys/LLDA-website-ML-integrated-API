<!-- C:\xampp\htdocs\my_website\predict.php -->
<?php
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $data = [
        'Temperature' => [floatval($_POST['temperature'])],
        'Humidity' => [floatval($_POST['humidity'])],
        'Wind' => [floatval($_POST['wind'])],
        'Wind Speed' => [floatval($_POST['wind_speed'])],
        'Ammonia (mg/L)' => [floatval($_POST['ammonia'])],
        'Inorganic Phosphate (mg/L)' => [floatval($_POST['phosphate'])],
        'BOD (mg/l)' => [floatval($_POST['bod'])]
    ];

    $json_data = json_encode($data);

    $url = 'http://127.0.0.1:5000/predict_and_learn';  // Flask API URL
    $ch = curl_init($url);

    curl_setopt($ch, CURLOPT_POSTFIELDS, $json_data);
    curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type:application/json'));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

    $result = curl_exec($ch);
    curl_close($ch);

    // Decode the JSON response from the Flask API
    $response = json_decode($result, true);
    $prediction = $response['prediction'][0];
    $status = $response['status'];


}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Phytoplankton Prediction</title>
</head>
<body>
    <h2>Phytoplankton Prediction Result</h2>
    <?php if (isset($prediction)): ?>
        <p><strong>Predicted Phytoplankton Count (cells/ml):</strong> <?php echo $prediction; ?></p>
        <p><strong>Status:</strong> <?php echo $status; ?></p>
    <?php else: ?>
        <p>No prediction made yet. Please submit the form.</p>
    <?php endif; ?>

    <h2>Enter New Data for Prediction</h2>
    <form action="predict.php" method="post">
        Temperature: <input type="text" name="temperature" required><br>
        Humidity: <input type="text" name="humidity" required><br>
        Wind: <input type="text" name="wind" required><br>
        Wind Speed: <input type="text" name="wind_speed" required><br>
        Ammonia (mg/L): <input type="text" name="ammonia" required><br>
        Inorganic Phosphate (mg/L): <input type="text" name="phosphate" required><br>
        BOD (mg/l): <input type="text" name="bod" required><br>
        <input type="submit" value="Predict">
    </form>
</body>
</html>
