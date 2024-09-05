<?php
include "retrain.php";


#FLASK
if (isset($_POST['submit_flask'])) {

    $data = [
        'Temperature' => [floatval($_POST['temperature'])],
        'Humidity' => [floatval($_POST['humidity'])],
        'Wind Speed' => [floatval($_POST['wind_speed'])],
        'pH (units)' => [floatval($_POST['ph'])],
        'Ammonia (mg/L)' => [floatval($_POST['ammonia'])],
        'Inorganic Phosphate (mg/L)' => [floatval($_POST['phosphate'])],
        'BOD (mg/l)' => [floatval($_POST['bod'])],
        'Total coliforms (MPN/100ml)' => [floatval($_POST['coliforms'])]
    ];

    $json_data = json_encode($data);

    $url = 'http://127.0.0.1:5000/model_testing';  // Flask API URL
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
    $rounded = round($prediction);
}







?>



<!DOCTYPE html>
<html>

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">


    <link rel="stylesheet" href="./global.css" />
    <link rel="stylesheet" href="./index.css" />
    <link rel="stylesheet" href="/page-styles/data.css" />
    <link rel="stylesheet" href="./footer.css" />
    <link rel="stylesheet"
        href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" />



</head>

<body>
    <div class="wrapper">
        <!-- header-->
    <div class="header-navigation">
        <div class="items">
            <div class="home"  id="statusText" ><a href="home.html" style=" text-decoration:none; color: black;">Home</a></div>
            <div class="status" id="statusText"><a href="status.html"
                    style=" text-decoration:none; color: black;">Status</a></div>
            <div class="status" id="repositoriesText"><a href="repo.html"
                style=" text-decoration:none; color: black; 
    text-shadow: 1px 0 0 rgba(0, 0, 0, 0.5), 0 1px 0 rgba(0, 0, 0, 0.5), -1px 0 0 rgba(0, 0, 0, 0.5), 0 -1px 0 rgba(0, 0, 0, 0.5);">Repositories</a></div>
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
    <div>
      


<h1 class="pred_h1">PREDICTION</h1>

<div class="table-container">
<table>
    <tr>
        <th>Temperature</th>
        <th>Humidity</th>
        <th>Wind Speed</th>
        <th>pH (units)</th>
        <th>Ammonia (mg/L)</th>
        <th>Inorganic Phosphate (mg/L)</th>
        <th>BOD (mg/l)</th>
        <th>Total coliforms (MPN/100ml)</th>
        <th></th>
        <th>Phytoplankton (cells/ml)</th>
    </tr>

    <tr>
        <form action="prediction_MI.php" method="POST">
        <td><input class="pred_input" type = "text" name="temperature" required></td>
        <td><input class="pred_input" type = "text" name="humidity" required></td>
        <td><input class="pred_input" type = "text" name="wind_speed" required></td>
        <td><input class="pred_input" type = "text" name="ph" required></td>
        <td><input class="pred_input" type = "text" name="ammonia" required></td>
        <td><input class="pred_input" type = "text" name="phosphate" required></td>
        <td><input class="pred_input" type = "text" name="bod"></td>
        <td><input class="pred_input" type = "text" name="coliforms"></td>
        <td><input type = "submit" value = "Predict" name = "submit_flask"></td>
        <td><input class="pred_input" type = "text" value = "<?php echo $rounded; ?>"></td>
        </form>
   
    </tr>
</table>
</div>



<h1 class="pred_h1">Model Testing/Retraining</h1><p class="model_p"><sup>This is used for uploading new datasets or retraing model with the current dataset</sup></p>

<div class="ML">
    <form action="upload.php" method="POST" enctype="multipart/form-data">
    <label>Upload Dataset (CSV Only): </label>
    <input type="file" name="dataset" accept=".csv" required>
    <input type="submit" value="Upload">
    </form>

    <label for="dataset">Datasets Available</label>

<form class="train-form" action="" method="POST">
    <select name="selected_dataset" id="dataset">
        <option value="">Select a Dataset</option>

        <?php
        // Define the folder path
        $folderPath = 'C:/Users/John Wilson/Desktop/Datamodel';  // Change this to the correct folder

        // Check if the directory exists
        if (is_dir($folderPath)) {
            // Open the folder
            if ($folderHandle = opendir($folderPath)) {
                // Read through the files in the folder
                while (($file = readdir($folderHandle)) !== false) {
                    // Only display files (ignore . and .. directories)
                    if ($file != '.' && $file != '..' && !is_dir($folderPath . '/' . $file)) {
                        // Only show files with specific extensions (e.g., .csv or .txt)
                        $fileExtension = pathinfo($file, PATHINFO_EXTENSION);
                        if ($fileExtension == 'csv' || $fileExtension == 'txt') {
                            echo "<option value=\"$file\">$file</option>";
                        }
                    }
                }
                // Close the directory handle
                closedir($folderHandle);
            } else {
                echo "<option value=''>Unable to open directory</option>";
            }
        } else {
            echo "<option value=''>Folder does not exist</option>";
        }
        ?>

    </select>
    <input type="submit" value="Preview" name ="Retrain">
    <input type="submit" value = "Train" name = "Train">
</form>



    

</div>

<div class="results">

    <p>Training Evaluation Results</p>
    <textarea class="area-results">
        <?php
            echo "Mean Squared Error (MSE): $mse";
            echo "Mean Absolute Error (MAE): $mae";
            echo "R-squared (R²): $r2";
        ?>
       
    </textarea>


</div>



        
    </div>
    <div class="foot">
        <footer>
            <div class="footer">
                <div class="items1">
                    <div class="research-proponents">Research Proponents</div>
                    <div class="marcus-henson-l">Marcus Henson L. Garcia</div>
                    <div class="john-wilson-d">John Wilson D. Lorin</div>
                    <div class="joshua-a-rancap">Joshua A. Rancap</div>
                </div>
                <div class="laguna-lake-development">Laguna Lake Development Authority</div>
                <div class="social-icons">
                    <img class="buttons-icon" alt="" src="Buttons/fb.png">
    
                    <img class="buttons-icon" alt="" src="Buttons/ig.png">
    
                    <img class="buttons-icon" alt="" src="Buttons/yt.png">
    
                </div>
                <div class="divider">
                </div>
                <div class="llda-all-rights">© 2024 LLDA. All Rights Reserved </div>
            </div>
        </footer>
    </div>
    </div>
    

    <script>
        var homeText = document.getElementById("homeText");
        if(homeText) {
        homeText.addEventListener("click", function (e) {
        // Add your code here
        });
        }
        var statusText = document.getElementById("statusText");
        if(statusText) {
        statusText.addEventListener("click", function (e) {
        // Add your code here
        });
        }
        var repositoriesText = document.getElementById("repositoriesText");
        if(repositoriesText) {
        repositoriesText.addEventListener("click", function (e) {
        window.location.href = "Repositories.html"
        });
        }
        var reportText = document.getElementById("reportText");
        if(reportText) {
        reportText.addEventListener("click", function (e) {
        // Add your code here
        });
        }
        var buttonContainer = document.getElementById("buttonContainer");
        if(buttonContainer) {
        buttonContainer.addEventListener("click", function (e) {
        // Add your code here
        });
        }
        </script>


        
</body>


</html>