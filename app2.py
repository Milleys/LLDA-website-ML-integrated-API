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
        water_quality_df['Month'] = water_quality_df['Month'].astype(str)
        
        # Correct typos in the 'Month' column
        water_quality_df['Month'] = water_quality_df['Month'].replace({
            'Febuary': 'February',
            'Aug': 'August',
            'Sept': 'September',
            'Nov': 'November',
            'Dec': 'December'
        }, regex=False)  # Use regex=False to avoid treating the keys as regular expressions
        
        # List of columns to exclude from transformation
        exclude_columns = ['Month', 'Wind', 'Condition']
        
        # Function to remove non-numeric characters from each cell
        def remove_non_numeric(value):
            if isinstance(value, str) and value not in exclude_columns:
                return re.sub(r'[^0-9.]', '', value)
            else:
                return value
        
        # Apply the function to each cell in numeric columns of the dataframe
        for column in weather_df.columns:
            if column not in exclude_columns:
                weather_df[column] = weather_df[column].apply(remove_non_numeric)
        
        # Convert the numeric columns to numeric type
        numeric_columns = [col for col in weather_df.columns if col not in exclude_columns]
        weather_df[numeric_columns] = weather_df[numeric_columns].apply(pd.to_numeric, errors='coerce')
        
        # Define mappings for Wind column
        wind_mapping = {
            'N': 1, 'NNE': 2, 'NE': 3, 'ENE': 4,
            'E': 5, 'ESE': 6, 'SE': 7, 'SSE': 8,
            'S': 9, 'SSW': 10, 'SW': 11, 'WSW': 12,
            'W': 13, 'WNW': 14, 'NW': 15, 'NNW': 16,
            'VAR': 17, 'CALM': 18
        }
        
        # Define mappings for Condition column
        condition_mapping = {
            'Fair': 1, 'Mostly Cloudy': 2, 'Partly Cloudy': 3, 'Cloudy': 4,
            'Light Rain': 5, 'Light Rain Shower': 6, 'Rain': 7, 'Heavy Rain': 8,
            'Thunder': 9, 'Light Rain with Thunder': 10, 'T-Storm': 11,
            'Heavy Rain Shower': 12, 'Rain Shower': 13, 'Showers in the Vicinity': 14,
            'Thunder in the Vicinity': 15, 'Mostly Cloudy / Windy': 16,
            'Fair / Windy': 17, 'Partly Cloudy / Windy': 18, 'Rain / Windy': 19,
            'Light Rain Shower / Windy': 20, 'Heavy Rain / Windy': 21
        }
        
        # Apply mappings to Wind and Condition columns
        weather_df['Wind'] = weather_df['Wind'].map(wind_mapping)
        weather_df['Condition'] = weather_df['Condition'].map(condition_mapping)
        
        # Define a function to compute the mode
        def compute_mode(series):
            return series.mode().iloc[0] if not series.mode().empty else None
        
        # Group by 'Year' and 'Month'
        grouped = weather_df.groupby(['Year', 'Month'])
        
        # Aggregate to get the mean for all columns except 'Wind' and 'Condition'
        # and the mode for 'Wind' and 'Condition'
        weather_monthly_stats = grouped.agg({
            'Wind': compute_mode,
            'Condition': compute_mode,
            'Time': 'mean',
            'Temperature': 'mean',
            'Dew Point' : 'mean',
            'Humidity': 'mean',
            'Wind Speed': 'mean',
            'Wind Gust': 'mean',
            'Pressure': 'mean',
            'Precip.': 'mean'
        }).reset_index()
        
        # Select relevant features and target
        features = ['Temperature', 'Humidity', 'Wind', 'Wind Speed', 'Condition', 'pH (units)', 'Ammonia (mg/L)', 'Nitrate (mg/L)', 'Inorganic Phosphate (mg/L)', 'BOD (mg/l)', 'Dissolved Oxygen (mg/l)', 'Total coliforms (MPN/100ml)']
        target = 'Phytoplankton (cells/ml)'
        
        # Merge datasets on 'Month' and 'Year'
        merged_df = pd.merge(water_quality_df, weather_monthly_stats, on=['Month', 'Year'])
        
        # Split data into training and testing sets
        X = merged_df[features]
        y = merged_df[target]
        X_train, X_test, y_train, y_test = train_test_split(X, y, test_size=0.2, random_state=42)
        
        # Standardize features
        scaler = StandardScaler()
        X_train_scaled = scaler.fit_transform(X_train)
        X_test_scaled = scaler.transform(X_test)
        
        # Define parameter grid for Grid Search with SVR
        param_grid = {
            'kernel': ['linear', 'poly', 'rbf', 'sigmoid'],
            'C': [1, 10, 100],
            'epsilon': [0.1, 0.2, 0.3],
            'gamma': ['scale', 'auto']
        }
        
        # Initialize GridSearchCV with SVR
        grid_search = GridSearchCV(SVR(), param_grid, cv=5, scoring='neg_mean_squared_error', n_jobs=-1)
        
        # Fit GridSearchCV
        grid_search.fit(X_train_scaled, y_train)
        
        # Best parameters and score
        best_svr_model = grid_search.best_estimator_
        
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
            f'Optimized SVR - R^2 Score: {r2_best_svr}'
        )
        
        return render_template('repo.html', report=report)
    except Exception as e:
        flash(f'Error during training and evaluation: {e}')
        return redirect(url_for('index'))

if __name__ == '__main__':
    if not os.path.exists(app.config['UPLOAD_FOLDER']):
        os.makedirs(app.config['UPLOAD_FOLDER'])
    app.run(debug=True)
