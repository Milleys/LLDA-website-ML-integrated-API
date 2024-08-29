# C:\path\to\flask_project\app.py
from flask import Flask, request, jsonify
import pandas as pd
import pickle
import os

app = Flask(__name__)

# Load the scaler
with open('xgb_scaler.pkl', 'rb') as file:
    scaler = pickle.load(file)

# Load the model
with open('xgb_model.pkl', 'rb') as file:
    model = pickle.load(file)

# Define the path to your CSV file
csv_file_path = 'updated_dataset_with_predictions.csv'

@app.route('/predict_and_learn', methods=['POST'])
def predict_and_learn():
    data = request.get_json()
    df = pd.DataFrame(data)

    # Standardize the input
    df_scaled = scaler.transform(df)

    # Make prediction
    prediction = model.predict(df_scaled)
    df['Phytoplankton (cells/ml)'] = prediction  # Append prediction to dataframe

    # Save the new data with prediction to the CSV file
    if os.path.exists(csv_file_path):
        df.to_csv(csv_file_path, mode='a', header=False, index=False)
    else:
        df.to_csv(csv_file_path, mode='w', header=True, index=False)

    return jsonify({'status': 'Prediction made and saved successfully', 'prediction': prediction.tolist()})

if __name__ == '__main__':
    app.run(debug=True)
