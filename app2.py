import os
import pandas as pd
from flask import Flask, request, redirect, url_for, render_template, flash
from sklearn.model_selection import train_test_split, GridSearchCV
from sklearn.svm import SVR
from sklearn.preprocessing import StandardScaler
from sklearn.metrics import mean_squared_error, mean_absolute_error, r2_score
import pickle
import re

app = Flask(__name__)
app.config['UPLOAD_FOLDER'] = 'uploads/'
app.secret_key = 'your_secret_key'

model_path = 'best_svr_model.pkl'

def load_model():
    """Load the pre-trained model if it exists."""
    if os.path.exists(model_path):
        with open(model_path, 'rb') as file:
            return pickle.load(file)
    else:
        return None

def save_model(model):
    """Save the model to a file."""
    with open(model_path, 'wb') as file:
        pickle.dump(model, file)

def retrain_model(X_train, y_train):
    """Retrain the model with new data and return the trained model."""
    scaler = StandardScaler()
    X_train_scaled = scaler.fit_transform(X_train)

    param_grid = {
        'kernel': ['linear', 'poly', 'rbf', 'sigmoid'],
        'C': [1, 10, 100],
        'epsilon': [0.1, 0.2, 0.3],
        'gamma': ['scale', 'auto']
    }

    grid_search = GridSearchCV(SVR(), param_grid, cv=5, scoring='neg_mean_squared_error', n_jobs=-1)
    grid_search.fit(X_train_scaled, y_train)

    return grid_search.best_estimator_

@app.route('/')
def index():
    return render_template('repo.html')

@app.route('/upload', methods=['POST'])
def upload_file():
    if 'file1' not in request.files or 'file2' not in request.files:
        flash('No file part')
        return redirect(request.url)
    
    file1 = request.files['file1']
    file2 = request.files['file2']
    
    if file1.filename == '' or file2.filename == '':
        flash('No selected file')
        return redirect(request.url)
    
    if file1 and file2:
        try:
            file_path1 = os.path.join(app.config['UPLOAD_FOLDER'], file1.filename)
            file_path2 = os.path.join(app.config['UPLOAD_FOLDER'], file2.filename)
            file1.save(file_path1)
            file2.save(file_path2)
            return redirect(url_for('train_and_evaluate', filename1=file1.filename, filename2=file2.filename))
        except Exception as e:
            flash(f'Error saving files: {e}')
            return redirect(request.url)

@app.route('/train_and_evaluate/<filename1>/<filename2>')
def train_and_evaluate(filename1, filename2):
    try:
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
            'N': 1, 'NNE': 2, 'NE': 3, 'ENE': 4,
            'E': 5, 'ESE': 6, 'SE': 7, 'SSE': 8,
            'S': 9, 'SSW': 10, 'SW': 11, 'WSW': 12,
            'W': 13, 'WNW': 14, 'NW': 15, 'NNW': 16,
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
        merged_df = merged_df.dropna()

        X = merged_df[features]
        y = merged_df[target]
        X_train, X_test, y_train, y_test = train_test_split(X, y, test_size=0.2, random_state=42)

        scaler = StandardScaler()
        X_train_scaled = scaler.fit_transform(X_train)
        X_test_scaled = scaler.transform(X_test)

        # Retrain the model with new data
        best_svr_model = retrain_model(X_train_scaled, y_train)

        # Make predictions and evaluate
        y_pred_best_svr = best_svr_model.predict(X_test_scaled)
        mse_best_svr = mean_squared_error(y_test, y_pred_best_svr)
        mae_best_svr = mean_absolute_error(y_test, y_pred_best_svr)
        r2_best_svr = r2_score(y_test, y_pred_best_svr)

        # Save the updated model
        save_model(best_svr_model)

        report = (
                  f'Optimized SVR - Mean Squared Error: {mse_best_svr}\n'
                  f'Optimized SVR - Mean Absolute Error: {mae_best_svr}\n'
                  f'Optimized SVR - R^2 Score: {r2_best_svr}')

        return render_template('repo.html', report=report)
    except Exception as e:
        flash(f'Error during training and evaluation: {e}')
        return redirect(url_for('index'))

if __name__ == '__main__':
    if not os.path.exists(app.config['UPLOAD_FOLDER']):
        os.makedirs(app.config['UPLOAD_FOLDER'])
    app.run(debug=True)
