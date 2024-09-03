from flask import Flask, request, jsonify
import pandas as pd
import pickle
import os
from statsmodels.tsa.statespace.sarimax import SARIMAX
import warnings

app = Flask(__name__)

# Load the scaler and model
with open('xgb_scaler.pkl', 'rb') as file:
    scaler = pickle.load(file)
with open('xgb_model.pkl', 'rb') as file:
    model = pickle.load(file)

# Define the path to your CSV file
csv_file_path = 'updated_dataset_with_predictions.csv'

@app.route('/predict_and_learn', methods=['POST'])
def predict_and_learn():
    data = request.get_json()
    web_df = pd.DataFrame(data)

    # Warnings management
    warnings.filterwarnings("ignore", message="Non-invertible starting MA parameters found. Using zeros as starting parameters.")
    warnings.filterwarnings("ignore", message="A date index has been provided, but it has no associated frequency information and so will be ignored when e.g. forecasting.")
    warnings.filterwarnings("ignore", message="No supported index is available. Prediction results will be given with an integer index beginning at `start`.")
    warnings.filterwarnings("ignore", message="No supported index is available. In the next version, calling this method in a model without a supported index will result in an exception.")

    # Load dataset for SARIMA
    merged_df = pd.read_csv(csv_file_path)
    station_name = 'Stn. IV (Central Bay)'  # Adjust the station name as necessary
    merged_df = merged_df[merged_df['Monitoring Stations'] == station_name]
    
    # Drop rows with missing values
    merged_df.dropna(inplace=True)

    # Ensure there's a Date column for time series forecasting
    merged_df['Date'] = pd.to_datetime(merged_df[['Year', 'Month']].assign(DAY=1))
    merged_df.set_index('Date', inplace=True)

    # SARIMA parameters
    sarima_order = (1, 1, 1)
    seasonal_order = (1, 1, 1, 12)  # Monthly data with yearly seasonality
    
    # Initialize dictionaries for models and forecasts
    forecasts = {}

    # List of parameters to model
    parameters = ['pH (units)', 'Ammonia (mg/L)', 'Inorganic Phosphate (mg/L)', 'BOD (mg/l)', 'Total coliforms (MPN/100ml)']

    
    # Fit SARIMA model and make forecasts
    for parameter in parameters:
        try:
            sarima_model = SARIMAX(merged_df[parameter], order=sarima_order, seasonal_order=seasonal_order)
            sarima_model_fit = sarima_model.fit(disp=False)
            forecast = sarima_model_fit.forecast(steps=1)  # Forecast the next time step
            forecasts[parameter] = forecast.values[0]
        except Exception as e:
            return jsonify({'status': f'Error in SARIMA for {parameter}: {e}'})
    
    # Convert SARIMA forecast to DataFrame
    forecast_df = pd.DataFrame([forecasts])

    # Combine SARIMA forecast with website input
    combined_df = pd.concat([web_df, forecast_df], axis=1)

    # Standardize the combined input
    combined_scaled = scaler.transform(combined_df)

    # Make prediction
    prediction = model.predict(combined_scaled)
    combined_df['Phytoplankton (cells/ml)'] = prediction  # Append prediction to DataFrame

  
    # Save the new data with prediction to the CSV file
    if os.path.exists(csv_file_path):
        # Load the existing dataset to ensure columns match
        existing_df = pd.read_csv(csv_file_path)
        
        # Reorder combined_df to match the order of columns in existing_df
        combined_df = combined_df.reindex(columns=existing_df.columns)
        
        # Append the data to the CSV file
        combined_df.to_csv(csv_file_path, mode='a', header=False, index=False)
    else:
        # If the file does not exist, save the combined_df as a new CSV
        combined_df.to_csv(csv_file_path, mode='w', header=True, index=False)


    return jsonify({'status': 'Prediction made and saved successfully', 'prediction': prediction.tolist()})

if __name__ == '__main__':
    app.run(debug=True)
