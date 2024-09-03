<?php


session_start();
$_SESSION["date"] = isset($_SESSION["date"]) ? $_SESSION["date"] : date('Y-m-d'); // Ensure session date is initialized

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
    if (isset($_GET['date']) && in_array($_GET['date'], $dates)) {
        $selectedDate = $_GET['date'];
        $_SESSION["date"] = $selectedDate;
    } else {
        $selectedDate = $_SESSION["date"];
    }


    // Find index for the selected date
    $index = array_search($selectedDate, $dates);

    if ($index !== false) {
        echo "<div style='position: absolute; bottom: 200px; right: 50px; width: 550px; border: 1px solid green; padding: 10px; box-shadow: 0px 0px 10px rgba(0, 0, 0, 0.1);'>";
        // Display weather data for the selected date
        echo "<h2 style='color: black; text-align: center; font-size: 1.2em;'>Weather Forecast for {$selectedDate}</h2>";
        echo "<table style='width: 100%; border-collapse: collapse;'>";
        echo "<tr style='background-color: #e0f7e0;'>";
        echo "<th style='border: 1px solid green; padding: 5px;'>Weather Code</th>";
        echo "<th style='border: 1px solid green; padding: 5px;'>Max Temp (°C)</th>";
        echo "<th style='border: 1px solid green; padding: 5px;'>Min Temp (°C)</th>";
        echo "</tr>";
        echo "<tr>";
        echo "<td style='border: 1px solid green; padding: 5px;'>{$weatherCode[$index]}</td>";
        echo "<td style='border: 1px solid green; padding: 5px;'>{$temperatureMax[$index]}</td>";
        echo "<td style='border: 1px solid green; padding: 5px;'>{$temperatureMin[$index]}</td>";
        echo "</tr>";
        echo "<tr>";
        echo "<th style='border: 1px solid green; padding: 5px;'>Max Humidity (%)</th>";
        echo "<th style='border: 1px solid green; padding: 5px;'>Min Humidity (%)</th>";
        echo "<th style='border: 1px solid green; padding: 5px;'>Max Wind (m/s)</th>";
        echo "</tr>";
        echo "<tr>";
        echo "<td style='border: 1px solid green; padding: 5px;'>{$humidityMax[$index]}</td>";
        echo "<td style='border: 1px solid green; padding: 5px;'>{$humidityMin[$index]}</td>";
        echo "<td style='border: 1px solid green; padding: 5px;'>{$windSpeedMax[$index]}</td>";
        echo "</tr>";
        echo "</table>";
        echo "</div>";
    } else {
        echo "<div style='position: fixed; bottom: 20px; right: 20px; width: 300px; background-color: #fff0f0; border: 1px solid red; padding: 10px; box-shadow: 0px 0px 10px rgba(0, 0, 0, 0.1);'>";
        echo "<p style='color: red; text-align: center;'>Data for the selected date is not available.</p>";
        echo "</div>";
    }
    
} else {
    echo "Unable to fetch weather data.";
}

// Handle Flask API request and response
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $results = [];

    $data = [
        'Temperature' => [floatval($temperatureMax[$index])],
        'Humidity' => [floatval($humidityMax[$index])],
        'Wind Speed' => [floatval($windSpeedMax[$index])],
        'Date' => $_SESSION["date"],
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
        $results['error_message'] = $error_message;
    } else {
        $results['status'] = $response['status'];
        $results['predictions'] = $response['results'];
    }
}

$phyto = [];

if (isset($results)) {
    if (isset($results['error_message'])) {
        echo "Error: " . $results['error_message'] . "\n";
    } else {
        foreach ($results['predictions'] as $station_name => $result) {
            #echo "Results for " . $station_name . "\n";
            #echo "Predicted Phytoplankton Count (cells/ml): " . $result['prediction'][0] . "\n";
            $phyto[$station_name] = $result['prediction'][0]; // Store predictions by station name
            #echo "Forecast:\n";
            foreach ($result['forecast'] as $key => $value) {
                #echo $key . ": " . $value . "\n";
            }
        }
    }
} else {
    
}






?>



<!DOCTYPE html>
<html>

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">


    <link rel="stylesheet" href="./global.css" />
    <link rel="stylesheet" href="page-styles/status.css" />
    <link rel="stylesheet" href="./index.css" />
    <link rel="stylesheet" href="./calendar.css" />
    <link rel="stylesheet" href="./footer.css" />
    <link rel="stylesheet" href="popup-styles/minor.css" />
    <link rel="stylesheet" href="popup-styles/moderate.css" />
    <link rel="stylesheet" href="popup-styles/massive.css" />
    <link rel="stylesheet"
        href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" />



</head>

<body>
    <div class="wrapper">
        <!-- header-->
    <div class="header-navigation">
        <div class="items">
            <div class="home"><a href="home.html" style=" text-decoration:none; color: black; ">Home</a></div>
            <div class="status" id="statusText"><a href="status.html"
                    style=" text-decoration:none; color: black; 
    text-shadow: 1px 0 0 rgba(0, 0, 0, 0.5), 0 1px 0 rgba(0, 0, 0, 0.5), -1px 0 0 rgba(0, 0, 0, 0.5), 0 -1px 0 rgba(0, 0, 0, 0.5);">Status</a></div>
            <div class="status" id="repositoriesText"><a href="repo.html"
                    style=" text-decoration:none; color: black;">Repositories</a></div>
            <div class="status" id="reportText"><a href="report.html"
                    style=" text-decoration:none; color: black;">Report</a></div>
            <div class="button" id="buttonContainer">
                <div class="log-out"><a href="login.html" style="text-decoration: none; color: white;">Log Out</a></div>
            </div>
        </div>
        <img class="updated-logo-v5-no-bilog-1" alt="" src="assets/updated-logo-v-5-no-bilog-10.png">
        <img class="llda-logo-2" alt="" src="assets/llda-logo-21.png">
    </div>

    <!-- body content-->
    <div class="status-content">
        <div class="lake-status">Lake Status</div>
        <div class="algal-blooms">Algal Blooms</div>
        <div class="rectangle-div-1">
        </div>
        <div class="rectangle-div-2">
        </div>
        <div class="rectangle-div-3">
        </div>
        <div class="water-quality">Water Quality</div>
        <div class="fish-kills">Fish Kills</div>


        <img class="laguna-lake-from-llda-2" alt="" src="assets/Laguna Lake from LLDA 2.png">
        <img class="legend-4-icon" alt="" src="assets/Legend 4.svg" id="legend4Icon">
        <img class="legend-5-icon" alt="" src="assets/Legend 5.svg" id="legend5Icon">
        <img class="legend-6-icon" alt="" src="assets/Legend 6.svg" id="legend6Icon">
        <img class="legend-7-icon" alt="" src="assets/Legend 7.svg" id="legend7Icon">
        <img class="legend-8-icon" alt="" src="assets/Legend 8.svg" id="legend8Icon">
        <img class="legend-9-icon" alt="" src="assets/Legend 9.svg" id="legend9Icon">

        <div id="Station_XIII" class="popup-overlay" style="display:none">
            <div class="moderate">
                <div class="moderate-container">
<!--Prediction from here-->
                        <?php 
                            $station_to_display = 'Stn XIII (Taytay)'; // Replace 'Station Name' with the actual station name you want to display
                            if (isset($phyto[$station_to_display])) {
                                // Round off the prediction to the nearest whole number
                                $rounded_prediction = round($phyto[$station_to_display]);
                                echo "Prediction for " . $station_to_display . ": " . $rounded_prediction . "/mL \n";
                                    if ($rounded_prediction <= 10000) {
                                        echo "Minor: Minimal or no noticeable impact on water quality. Some visible bloom may be present, but effects on aquatic life are typically limited.";
                                        
                                    } elseif ($rounded_prediction <= 100000) {
                                        echo "Moderate: Visible bloom with potential moderate impacts on water quality, including reduced oxygen levels. Potentially harmful to aquatic ecosystems.";
                                    } else {
                                        echo "Massive: Severe bloom with significant impacts, including potential toxin production, severe oxygen depletion, and major disruptions to aquatic life and water usability.";
                                    }

                            } else {
                                echo "No prediction available for " . $station_to_display . "\n";
                            }

                        ?>
<!--To Here-->
                    
                </div>
            </div>
        </div>



        <div id="Station_V" class="popup-overlay" style="display:none">
            <div class="massive">
                <div class="massive-container">

<!--Prediction from here-->
                        <?php 
                            $station_to_display = 'Stn V (Northern West Bay)'; // Replace 'Station Name' with the actual station name you want to display
                            if (isset($phyto[$station_to_display])) {
                                // Round off the prediction to the nearest whole number
                                $rounded_prediction = round($phyto[$station_to_display]);
                                echo "Prediction for " . $station_to_display . ": " . $rounded_prediction . "/mL \n";
                                    if ($rounded_prediction <= 10000) {
                                        echo "Minor: Minimal or no noticeable impact on water quality. Some visible bloom may be present, but effects on aquatic life are typically limited.";
                                        
                                    } elseif ($rounded_prediction <= 100000) {
                                        echo "Moderate: Visible bloom with potential moderate impacts on water quality, including reduced oxygen levels. Potentially harmful to aquatic ecosystems.";
                                    } else {
                                        echo "Massive: Severe bloom with significant impacts, including potential toxin production, severe oxygen depletion, and major disruptions to aquatic life and water usability.";
                                    }

                            } else {
                                echo "No prediction available for " . $station_to_display . "\n";
                            }

                        ?>
<!--To Here-->
                 
                </div>
            </div>
        </div>



        <div id="Station_XIX" class="popup-overlay" style="display:none">
            <div class="minor">
                <div class="minor-container">
<!--Prediction from here-->
                    <?php 
                        $station_to_display = 'Stn XIX (Muntinlupa)'; // Replace 'Station Name' with the actual station name you want to display
                        if (isset($phyto[$station_to_display])) {
                            // Round off the prediction to the nearest whole number
                            $rounded_prediction = round($phyto[$station_to_display]);
                            echo "Prediction for " . $station_to_display . ": " . $rounded_prediction . "/mL \n";
                                if ($rounded_prediction <= 10000) {
                                    echo "Minor: Minimal or no noticeable impact on water quality. Some visible bloom may be present, but effects on aquatic life are typically limited.";
                                    
                                } elseif ($rounded_prediction <= 100000) {
                                    echo "Moderate: Visible bloom with potential moderate impacts on water quality, including reduced oxygen levels. Potentially harmful to aquatic ecosystems.";
                                } else {
                                    echo "Massive: Severe bloom with significant impacts, including potential toxin production, severe oxygen depletion, and major disruptions to aquatic life and water usability.";
                                }

                        } else {
                            echo "No prediction available for " . $station_to_display . "\n";
                        }

                    ?>
<!--To Here-->         


                    
                </div>
            </div>
        </div>



        <div id="Station_I" class="popup-overlay" style="display:none">
            <div class="minor">
                <div class="minor-container">
<!--Prediction from here-->
                    <?php 
                        $station_to_display = 'Stn. I (Central West Bay)'; // Replace 'Station Name' with the actual station name you want to display
                        if (isset($phyto[$station_to_display])) {
                            // Round off the prediction to the nearest whole number
                            $rounded_prediction = round($phyto[$station_to_display]);
                            echo "Prediction for " . $station_to_display . ": " . $rounded_prediction . "/mL \n";
                                if ($rounded_prediction <= 10000) {
                                    echo "Minor: Minimal or no noticeable impact on water quality. Some visible bloom may be present, but effects on aquatic life are typically limited.";
                                    
                                } elseif ($rounded_prediction <= 100000) {
                                    echo "Moderate: Visible bloom with potential moderate impacts on water quality, including reduced oxygen levels. Potentially harmful to aquatic ecosystems.";
                                } else {
                                    echo "Massive: Severe bloom with significant impacts, including potential toxin production, severe oxygen depletion, and major disruptions to aquatic life and water usability.";
                                }

                        } else {
                            echo "No prediction available for " . $station_to_display . "\n";
                        }

                    ?>
<!--To Here--> 
                </div>
            </div>
        </div>



        <div id="Station_XV" class="popup-overlay" style="display:none">
            <div class="minor">
                <div class="minor-container">
<!--Prediction from here-->
                    <?php 
                        $station_to_display = 'Stn XV (San Pedro)'; // Replace 'Station Name' with the actual station name you want to display
                        if (isset($phyto[$station_to_display])) {
                            // Round off the prediction to the nearest whole number
                            $rounded_prediction = round($phyto[$station_to_display]);
                            echo "Prediction for " . $station_to_display . ": " . $rounded_prediction . "/mL \n";
                                if ($rounded_prediction <= 10000) {
                                    echo "Minor: Minimal or no noticeable impact on water quality. Some visible bloom may be present, but effects on aquatic life are typically limited.";
                                    
                                } elseif ($rounded_prediction <= 100000) {
                                    echo "Moderate: Visible bloom with potential moderate impacts on water quality, including reduced oxygen levels. Potentially harmful to aquatic ecosystems.";
                                } else {
                                    echo "Massive: Severe bloom with significant impacts, including potential toxin production, severe oxygen depletion, and major disruptions to aquatic life and water usability.";
                                }

                        } else {
                            echo "No prediction available for " . $station_to_display . "\n";
                        }

                    ?>
<!--To Here-->
                </div>
            </div>
        </div>



        <div id="Station_XVI" class="popup-overlay" style="display:none">
            <div class="minor">
                <div class="minor-container">
<!--Prediction from here-->
                    <?php 
                        $station_to_display = 'Stn.XVI (Sta Rosa)'; // Replace 'Station Name' with the actual station name you want to display
                        if (isset($phyto[$station_to_display])) {
                            // Round off the prediction to the nearest whole number
                            $rounded_prediction = round($phyto[$station_to_display]);
                            echo "Prediction for " . $station_to_display . ": " . $rounded_prediction . "/mL \n";
                                if ($rounded_prediction <= 10000) {
                                    echo "Minor: Minimal or no noticeable impact on water quality. Some visible bloom may be present, but effects on aquatic life are typically limited.";
                                    
                                } elseif ($rounded_prediction <= 100000) {
                                    echo "Moderate: Visible bloom with potential moderate impacts on water quality, including reduced oxygen levels. Potentially harmful to aquatic ecosystems.";
                                } else {
                                    echo "Massive: Severe bloom with significant impacts, including potential toxin production, severe oxygen depletion, and major disruptions to aquatic life and water usability.";
                                }

                        } else {
                            echo "No prediction available for " . $station_to_display . "\n";
                        }

                    ?>
<!--To Here-->
                </div>
            </div>
        </div>



        <!-- calendar -->
        <div class="form-container">
            <h2>Date Prediction</h2>
            <form method="get" action="status.php">
                <label for="prediction-date">Choose a date:</label>
                <input type="date" id="date" name="date"  min="<?php echo min($dates); ?>" max="<?php echo max($dates); ?>" value="<?php echo isset($_GET['date']) ? $_GET['date'] : date('Y-m-d', strtotime('+1 day')); ?>">
                <input type="submit" value="Forecast">
            </form>
            <form action="status.php" method="post">
                <input type="submit" value="Predict">
            </form>
        </div>
        <div class="foot">
            <!-- footer-->
            <footer>
                <div class="footer">
                    <div class="items1">
                        <div class="research-proponents" style=" color: black; ">Research Proponents</div>
                        <div class="marcus-henson-l" style=" color: black; ">Marcus Henson L. Garcia</div>
                        <div class="john-wilson-d" style=" color: black; ">John Wilson D. Lorin</div>
                        <div class="joshua-a-rancap" style=" color: black; ">Joshua A. Rancap</div>
                    </div>
                    <div class="laguna-lake-development" style=" color: black; ">Laguna Lake Development Authority</div>
                    <div class="social-icons">
                        <img class="buttons-icon" alt="" src="Buttons/fb.png">
            
                        <img class="buttons-icon" alt="" src="Buttons/ig.png">
            
                        <img class="buttons-icon" alt="" src="Buttons/yt.png">
            
                    </div>
                    <div class="divider">
                    </div>
                    <div class="llda-all-rights" style=" color: black; ">© 2024 LLDA. All Rights Reserved </div>
                </div>
            </footer>
            </div>
    </div>
    

       

</body>



</html>

<script>
            
            
            var homeText = document.getElementById("homeText");
            if (homeText) {
                homeText.addEventListener("click", function (e) {
                    // Add your code here
                });
            }
            var repositoriesText = document.getElementById("repositoriesText");
            if (repositoriesText) {
                repositoriesText.addEventListener("click", function (e) {
                    // Add your code here
                });
            }
            var reportText = document.getElementById("reportText");
            if (reportText) {
                reportText.addEventListener("click", function (e) {
                    // Add your code here
                });
            }
            var buttonContainer = document.getElementById("buttonContainer");
            if (buttonContainer) {
                buttonContainer.addEventListener("click", function (e) {
                    // Add your code here
                });
            }
            var legend4Icon = document.getElementById("legend4Icon");
            if (legend4Icon) {
                legend4Icon.addEventListener("click", function () {
                    var popup = document.getElementById("Station_XIII");
                    if (!popup) return;
                    var popupStyle = popup.style;
                    if (popupStyle) {
                        popupStyle.display = "flex";
                        popupStyle.zIndex = 100;
                        popupStyle.backgroundColor = "rgba(113, 113, 113, 0.3)";
                        popupStyle.alignItems = "center";
                        popupStyle.justifyContent = "center";
                    }
                    popup.setAttribute("closable", "");
                    var onClick = popup.onClick || function (e) {
                        if (e.target === popup && popup.hasAttribute("closable")) {
                            popupStyle.display = "none";
                        }
                    };
                    popup.addEventListener("click", onClick);
                });
            }
            var legend5Icon = document.getElementById("legend5Icon");
            if (legend5Icon) {
                legend5Icon.addEventListener("click", function () {
                    var popup = document.getElementById("Station_V");
                    if (!popup) return;
                    var popupStyle = popup.style;
                    if (popupStyle) {
                        popupStyle.display = "flex";
                        popupStyle.zIndex = 100;
                        popupStyle.backgroundColor = "rgba(113, 113, 113, 0.3)";
                        popupStyle.alignItems = "center";
                        popupStyle.justifyContent = "center";
                    }
                    popup.setAttribute("closable", "");
                    var onClick = popup.onClick || function (e) {
                        if (e.target === popup && popup.hasAttribute("closable")) {
                            popupStyle.display = "none";
                        }
                    };
                    popup.addEventListener("click", onClick);
                });
            }
            var legend6Icon = document.getElementById("legend6Icon");
            if (legend6Icon) {
                legend6Icon.addEventListener("click", function () {
                    var popup = document.getElementById("Station_XIX");
                    if (!popup) return;
                    var popupStyle = popup.style;
                    if (popupStyle) {
                        popupStyle.display = "flex";
                        popupStyle.zIndex = 100;
                        popupStyle.backgroundColor = "rgba(113, 113, 113, 0.3)";
                        popupStyle.alignItems = "center";
                        popupStyle.justifyContent = "center";
                    }
                    popup.setAttribute("closable", "");
                    var onClick = popup.onClick || function (e) {
                        if (e.target === popup && popup.hasAttribute("closable")) {
                            popupStyle.display = "none";
                        }
                    };
                    popup.addEventListener("click", onClick);
                });
            }
            var legend7Icon = document.getElementById("legend7Icon");
            if (legend7Icon) {
                legend7Icon.addEventListener("click", function () {
                    var popup = document.getElementById("Station_I");
                    if (!popup) return;
                    var popupStyle = popup.style;
                    if (popupStyle) {
                        popupStyle.display = "flex";
                        popupStyle.zIndex = 100;
                        popupStyle.backgroundColor = "rgba(113, 113, 113, 0.3)";
                        popupStyle.alignItems = "center";
                        popupStyle.justifyContent = "center";
                    }
                    popup.setAttribute("closable", "");
                    var onClick = popup.onClick || function (e) {
                        if (e.target === popup && popup.hasAttribute("closable")) {
                            popupStyle.display = "none";
                        }
                    };
                    popup.addEventListener("click", onClick);
                });
            }
            var legend8Icon = document.getElementById("legend8Icon");
            if (legend8Icon) {
                legend8Icon.addEventListener("click", function () {
                    var popup = document.getElementById("Station_XV");
                    if (!popup) return;
                    var popupStyle = popup.style;
                    if (popupStyle) {
                        popupStyle.display = "flex";
                        popupStyle.zIndex = 100;
                        popupStyle.backgroundColor = "rgba(113, 113, 113, 0.3)";
                        popupStyle.alignItems = "center";
                        popupStyle.justifyContent = "center";
                    }
                    popup.setAttribute("closable", "");
                    var onClick = popup.onClick || function (e) {
                        if (e.target === popup && popup.hasAttribute("closable")) {
                            popupStyle.display = "none";
                        }
                    };
                    popup.addEventListener("click", onClick);
                });
            }
            var legend9Icon = document.getElementById("legend9Icon");
            if (legend9Icon) {
                legend9Icon.addEventListener("click", function () {
                    var popup = document.getElementById("Station_XVI");
                    if (!popup) return;
                    var popupStyle = popup.style;
                    if (popupStyle) {
                        popupStyle.display = "flex";
                        popupStyle.zIndex = 100;
                        popupStyle.backgroundColor = "rgba(113, 113, 113, 0.3)";
                        popupStyle.alignItems = "center";
                        popupStyle.justifyContent = "center";
                    }
                    popup.setAttribute("closable", "");
                    var onClick = popup.onClick || function (e) {
                        if (e.target === popup && popup.hasAttribute("closable")) {
                            popupStyle.display = "none";
                        }
                    };
                    popup.addEventListener("click", onClick);
                });
            }

            //calendar script
            var textInputContainer = document.getElementById("textInputContainer");
            if (textInputContainer) {
                textInputContainer.addEventListener("click", function () {
                    var popup = document.getElementById("datepickersContainer");
                    if (!popup) return;
                    var popupStyle = popup.style;
                    if (popupStyle) {
                        popupStyle.display = "flex";
                        popupStyle.zIndex = 100;
                        popupStyle.backgroundColor = "rgba(113, 113, 113, 0.3)";
                        popupStyle.alignItems = "center";
                        popupStyle.justifyContent = "center";
                    }
                    popup.setAttribute("closable", "");
                    var onClick = popup.onClick || function (e) {
                        if (e.target === popup && popup.hasAttribute("closable")) {
                            popupStyle.display = "none";
                        }
                    };
                    popup.addEventListener("click", onClick);
                });
            }

        </script>