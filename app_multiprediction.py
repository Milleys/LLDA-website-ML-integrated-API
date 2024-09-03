from flask import Flask, request, jsonify
import pandas as pd
import pickle
import os
from statsmodels.tsa.statespace.sarimax import SARIMAX
import warnings

app = Flask(__name__)

# Load the scaler and XGBoost model
with open('xgb_scaler.pkl', 'rb') as file:
    scaler = pickle.load(file)
with open('xgb_model.pkl', 'rb') as file:
    xgb_model = pickle.load(file)

# Define the path to your CSV file
csv_file_path = 'updated_dataset_with_predictions.csv'

# List of station names
station_names = ['Stn. I (Central West Bay)', 'Stn V (Northern West Bay)', 'Stn XIII (Taytay)', 'Stn XV (San Pedro)', 'Stn.XVI (Sta Rosa)',  'Stn XIX (Muntinlupa)']

@app.route('/predict_and_learn', methods=['POST'])
def predict_and_learn():
    try:
        data = request.get_json()
        web_df = pd.DataFrame(data)
        
        # Extract and remove the 'Date' column from web_df
        if 'Date' in web_df.columns:
            selected_date = web_df.pop('Date').iloc[0]
        else:
            return jsonify({'status': 'Error', 'message': 'Date column is missing in the input data'})

        # Warnings management
        warnings.filterwarnings("ignore", message="Non-invertible starting MA parameters found. Using zeros as starting parameters.")
        warnings.filterwarnings("ignore", message="A date index has been provided, but it has no associated frequency information and so will be ignored when e.g. forecasting.")
        warnings.filterwarnings("ignore", message="No supported index is available. Prediction results will be given with an integer index beginning at `start`.")
        warnings.filterwarnings("ignore", message="No supported index is available. In the next version, calling this method in a model without a supported index will result in an exception.")

        # Define SARIMA parameters
        sarima_order = (1, 1, 1)
        seasonal_order = (1, 1, 1, 12)  # Monthly data with yearly seasonality
        
        # Initialize dictionaries for models and forecasts
        sarima_models = {}
        forecasts = {}
        
        # List of parameters to model
        parameters = ['pH (units)', 'Ammonia (mg/L)', 'Inorganic Phosphate (mg/L)', 'BOD (mg/l)', 'Total coliforms (MPN/100ml)']
        
        # Load dataset and process for each station
        results = {}
        for station_name in station_names:
            # Load dataset for SARIMA
            merged_df = pd.read_csv(csv_file_path)
            merged_df = merged_df[merged_df['Monitoring Stations'] == station_name]
            
            # Drop rows with missing values
            merged_df.dropna(inplace=True)

            # Ensure there's a Date column for time series forecasting
            merged_df['Date'] = pd.to_datetime(merged_df[['Year', 'Month']].assign(DAY=1))
            merged_df.set_index('Date', inplace=True)
            
            # Fit SARIMA models
            for parameter in parameters:
                try:
                    sarima_model = SARIMAX(merged_df[parameter], order=sarima_order, seasonal_order=seasonal_order)
                    sarima_model_fit = sarima_model.fit(disp=False)
                    sarima_models[parameter] = sarima_model_fit
                except Exception as e:
                    return jsonify({'status': 'Error', 'message': f'An error occurred while fitting the model for {parameter} at {station_name}: {e}'})

            # Define the target date for prediction
            target_date = pd.Timestamp(selected_date)
            
            # Calculate number of periods from the last observed date to the target date
            last_date = merged_df.index[-1]
            forecast_steps = (target_date.year - last_date.year) * 12 + (target_date.month - last_date.month)
            
            # Forecast for the target date
            forecast_results = {}
            for parameter in parameters:
                try:
                    model_fit = sarima_models[parameter]
                    forecast = model_fit.get_forecast(steps=forecast_steps).predicted_mean
                    forecast_value = forecast.values[-1]  # Value for the target date
                    forecast_results[parameter] = forecast_value
                except Exception as e:
                    return jsonify({'status': 'Error', 'message': f'Error in SARIMA for {parameter} at {station_name}: {e}'})
            
            # Convert the results to a DataFrame with parameters as columns and target date as a column
            forecast_df = pd.DataFrame([forecast_results])
            # Combine SARIMA forecast with website input
            combined_df = pd.concat([web_df, forecast_df], axis=1)

            # Standardize the combined input
            combined_scaled = scaler.transform(combined_df)

            # Make prediction using XGBoost model
            prediction = xgb_model.predict(combined_scaled)
            combined_df['Phytoplankton (cells/ml)'] = prediction  # Append prediction to DataFrame
            
            # Format the date as 'MM/DD/YYYY'
            formatted_date = target_date.strftime('%m/%d/%Y')
            
            # Create a new DataFrame with the Date column
            date_df = pd.DataFrame({'Date': [formatted_date]})
            station_df = pd.DataFrame({'Monitoring Stations': [station_name]})
            
            # Concatenate the date_df with combined_df along the columns
            combined_df = pd.concat([combined_df, date_df, station_df], axis=1)

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
            
            forecast_df_dict = forecast_df.to_dict(orient='records')[0]
            results[station_name] = {
                'prediction': prediction.tolist(),
                'forecast': forecast_df_dict
            }
        
        return jsonify({
            'status': 'Prediction made and saved successfully',
            'results': results
        })

    except Exception as e:
        return jsonify({'status': 'Error', 'message': str(e)}), 500

if __name__ == '__main__':
    app.run(debug=True)
