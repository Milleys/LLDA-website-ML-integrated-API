import os
import pandas as pd
from flask import Flask, request, redirect, url_for, render_template, flash
from sklearn.model_selection import train_test_split
from sklearn.ensemble import RandomForestClassifier
from sklearn.metrics import classification_report
import pickle
import re

app = Flask(__name__)
app.config['UPLOAD_FOLDER'] = 'uploads/'

# Load your initial pre-trained model
model_path = 'model_saved.pkl'
if os.path.exists(model_path):
    with open(model_path, 'rb') as file:
        model = pickle.load(file)
else:
    model = RandomForestClassifier(n_estimators=100, random_state=42)

@app.route('/')
def index():
    return render_template('repo.html')

@app.route('/upload', methods=['POST'])
def upload_file():
    if 'file1' not in request.files or 'file2' not in request.files:
        return redirect(request.url)
    file1 = request.files['file1']
    file2 = request.files['file2']
    if file1.filename == '' or file2.filename == '':
        return redirect(request.url)
    if file1 and file2:
        file_path1 = os.path.join(app.config['UPLOAD_FOLDER'], file1.filename)
        file_path2 = os.path.join(app.config['UPLOAD_FOLDER'], file2.filename)
        file1.save(file_path1)
        file2.save(file_path2)
        return redirect(url_for('train_and_evaluate', filename1=file1.filename, filename2=file2.filename))

@app.route('/train_and_evaluate/<filename1>/<filename2>')
def train_and_evaluate(filename1, filename2):
    file_path1 = os.path.join(app.config['UPLOAD_FOLDER'], filename1)
    file_path2 = os.path.join(app.config['UPLOAD_FOLDER'], filename2)

    water_quality_df = pd.read_csv(file_path1, encoding='latin1')
    weather_df = pd.read_csv(file_path2, encoding='iso-8859-1')

    water_quality_df['Phytoplankton (cells/ml)'] = water_quality_df['Phytoplankton (cells/ml)'].str.replace(',', '')
    exclude_columns = ['Month', 'Wind', 'Condition']

    def remove_non_numeric(value):
        if isinstance(value, str) and value not in exclude_columns:
            return re.sub(r'[^0-9.]', '', value)
        else:
            return value

    for column in weather_df.columns:
        if column not in exclude_columns:
            weather_df[column] = weather_df[column].apply(remove_non_numeric)

    numeric_columns = [col for col in weather_df.columns if col not in exclude_columns]
    weather_df[numeric_columns] = weather_df[numeric_columns].apply(pd.to_numeric, errors='coerce')

    wind_mapping = {
        'N': 1, 'NNE': 2, 'NE': 3, 'ENE': 4, 'E': 5, 'ESE': 6, 'SE': 7, 'SSE': 8,
        'S': 9, 'SSW': 10, 'SW': 11, 'WSW': 12, 'W': 13, 'WNW': 14, 'NW': 15, 'NNW': 16,
        'VAR': 17, 'CALM': 18
    }

    condition_mapping = {
        'Fair': 1, 'Mostly Cloudy': 2, 'Partly Cloudy': 3, 'Cloudy': 4,
        'Light Rain': 5, 'Light Rain Shower': 6, 'Rain': 7, 'Heavy Rain': 8,
        'Thunder': 9, 'Light Rain with Thunder': 10, 'T-Storm': 11,
        'Heavy Rain Shower': 12, 'Rain Shower': 13, 'Showers in the Vicinity': 14,
        'Thunder in the Vicinity': 15, 'Mostly Cloudy / Windy': 16,
        'Fair / Windy': 17, 'Partly Cloudy / Windy': 18, 'Rain / Windy': 19,
        'Light Rain Shower / Windy': 20, 'Heavy Rain / Windy': 21
    }

    weather_df['Wind'] = weather_df['Wind'].map(wind_mapping)
    weather_df['Condition'] = weather_df['Condition'].map(condition_mapping)

    weather_monthly_avg = weather_df.groupby(['Year', 'Month']).mean().reset_index()
    merged_df = pd.merge(water_quality_df, weather_monthly_avg, on=['Month', 'Year'])

    features = ['Temperature', 'Humidity', 'Wind', 'Wind Speed', 'Condition', 
                'pH (units)', 'Ammonia (mg/L)', 'Nitrate (mg/L)', 
                'Inorganic Phosphate (mg/L)', 'BOD (mg/l)', 
                'Dissolved Oxygen (mg/l)', 'Total coliforms (MPN/100ml)']
    target = 'Phytoplankton (cells/ml)'

    merged_df[target] = pd.to_numeric(merged_df[target], errors='coerce')
    merged_df['Phytoplankton_Class'] = (merged_df[target] > 1000).astype(int)
    merged_df = merged_df.dropna()

    X = merged_df[features]
    y = merged_df['Phytoplankton_Class']

    X_train, X_test, y_train, y_test = train_test_split(X, y, test_size=0.2, random_state=42)

    model.fit(X_train, y_train)

    with open(model_path, 'wb') as file:
        pickle.dump(model, file)

    y_pred = model.predict(X_test)
    report = classification_report(y_test, y_pred, zero_division='warn')

    return render_template('repo.html', report=report)

if __name__ == '__main__':
    if not os.path.exists(app.config['UPLOAD_FOLDER']):
        os.makedirs(app.config['UPLOAD_FOLDER'])
    app.run(debug=True)
