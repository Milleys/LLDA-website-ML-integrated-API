<?php
session_start();
$_SESSION["date"];
print_r($_SESSION);

# Weather API
$latitude = "14.5243"; // Latitude for Taguig
$longitude = "121.0792"; // Longitude for Taguig
$timezone = "Asia/Singapore"; // Timezone for Taguig

// API URL for 7-Day Forecast including humidity
$apiUrl = "https://api.open-meteo.com/v1/forecast?latitude={$latitude}&longitude={$longitude}&daily=weather_code,temperature_2m_max,temperature_2m_min,relative_humidity_2m_max,relative_humidity_2m_min,wind_speed_10m_max,wind_direction_10m_dominant&timezone={$timezone}";

// Fetch weather data from the API
$weatherData = @file_get_contents($apiUrl);

if ($weatherData === FALSE) {
    echo "Error fetching weather data. Please check the URL.";
    exit;
}

$weatherArray = json_decode($weatherData, true);

if (isset($weatherArray['daily'])) {
    $dailyData = $weatherArray['daily'];
    $dates = $dailyData['time'];
    $temperatureMax = $dailyData['temperature_2m_max'];
    $temperatureMin = $dailyData['temperature_2m_min'];
    $humidityMax = $dailyData['relative_humidity_2m_max'];
    $humidityMin = $dailyData['relative_humidity_2m_min'];
    $windSpeedMax = $dailyData['wind_speed_10m_max'];
    $windDirectionDominant = $dailyData['wind_direction_10m_dominant'];
    $weatherCode = $dailyData['weather_code'];

    // Default to show tomorrow's data


    // Check if a date is provided via GET request
    if (isset($_GET['date']) && in_array($_GET['date'], $dates)) {
        $selectedDate = $_GET['date'];
        $_SESSION["date"] = $selectedDate;
    }

    // Ensure the correct date is being used
    echo "<p>Selected Date: {$selectedDate}</p>";

    // Find index for the selected date
    $index = array_search($selectedDate, $dates);

    if ($index !== false) {
        // Display weather data for the selected date
        echo "<h2>Weather Forecast for {$selectedDate}</h2>";
        echo "<table border='1'>";
        echo "<tr><th>Weather Code</th><th>Max Temperature (°C)</th><th>Min Temperature (°C)</th><th>Max Humidity (%)</th><th>Min Humidity (%)</th><th>Max Wind Speed (m/s)</th><th>Dominant Wind Direction (°)</th></tr>";
        echo "<tr>";
        echo "<td>{$weatherCode[$index]}</td>";
        echo "<td>{$temperatureMax[$index]}</td>";
        echo "<td>{$temperatureMin[$index]}</td>";
        echo "<td>{$humidityMax[$index]}</td>";
        echo "<td>{$humidityMin[$index]}</td>";
        echo "<td>{$windSpeedMax[$index]}</td>";
        echo "<td>{$windDirectionDominant[$index]}</td>";
        echo "</tr>";
        echo "</table>";
    } else {
        echo "Data for the selected date is not available.";
    }
} else {
    echo "Unable to fetch weather data.";
}

# Flask API
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $data = [
        'Temperature' => [floatval($temperatureMax[$index])],
        'Humidity' => [floatval($humidityMax[$index])],
        'Wind Speed' => [floatval($windSpeedMax[$index])],
        'Date' =>  $_SESSION["date"],
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

    if (isset($response['status']) && $response['status'] == 'Error') {
        $error_message = $response['message'];
    } else {
        $prediction = $response['prediction'][0] ?? null;
        $status = $response['status'] ?? 'No status available';
        $forecast = $response['forecast'] ?? [];

        // Convert forecast to a string for display
        $forecastString = '';
        foreach ($forecast as $key => $value) {
            $forecastString .= "<p><strong>{$key}:</strong> {$value}</p>";
        }
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Phytoplankton Prediction</title>
</head>
<body>
    <h2>Phytoplankton Prediction Result</h2>
    <?php if (isset($error_message)): ?>
        <p style="color: red;"><strong>Error:</strong> <?php echo $error_message; ?></p>
    <?php elseif (isset($prediction)): ?>
        <p><strong>Predicted Phytoplankton Count (cells/ml):</strong> <?php echo $prediction; ?></p>
        <p><strong>Status:</strong> <?php echo $status; ?></p>
        <div><strong>Forecast:</strong><?php echo $forecastString; ?></div>
    <?php else: ?>
        <p>No prediction made yet. Please submit the form.</p>
    <?php endif; ?>

    <h2>Enter New Data for Prediction</h2>
    <form action="predict.php" method="post">
        <input type="submit" value="Predict">
    </form>

    <h2>Select a Date for Weather Forecast</h2>
    <form method="get" action="">
        <label for="date">Choose Date:</label>
        <input type="date" id="date" name="date" min="<?php echo min($dates); ?>" max="<?php echo max($dates); ?>" value="<?php echo isset($_GET['date']) ? $_GET['date'] : date('Y-m-d', strtotime('+1 day')); ?>">
        <input type="submit" value="Show Weather">
    </form>
</body>
</html>
