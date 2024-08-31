<?php
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
    $selectedDate = date('Y-m-d', strtotime('+1 day')); // Default: Tomorrow

    // Check if a date is provided via GET request
    if (isset($_GET['date']) && in_array($_GET['date'], $dates)) {
        $selectedDate = $_GET['date'];
    }

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
?>

<!DOCTYPE html>
<html>
<head>
    <title>Weather Forecast</title>
</head>
<body>
    <h2>Select a Date for Weather Forecast</h2>
    <form method="get" action="">
        <label for="date">Choose Date:</label>
        <input type="date" id="date" name="date" min="<?php echo min($dates); ?>" max="<?php echo max($dates); ?>" value="<?php echo date('Y-m-d', strtotime('+1 day')); ?>">
        <input type="submit" value="Show Weather">
    </form>
</body>
</html>
