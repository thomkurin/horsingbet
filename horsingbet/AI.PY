import pandas as pd
from sklearn.model_selection import train_test_split
from sklearn.ensemble import RandomForestClassifier
from sklearn.metrics import accuracy_score, classification_report
from sqlalchemy import create_engine
from sklearn.impute import SimpleImputer

# Conectando-se ao MySQL
USERNAME = 'root'
PASSWORD = ''
HOST = 'localhost'
PORT = '3306'
DATABASE = 'horsingbet'

engine = create_engine(f"mysql+pymysql://{USERNAME}:{PASSWORD}@{HOST}:{PORT}/{DATABASE}")

# Lendo os dados do MySQL
query = "SELECT * FROM geral"
data = pd.read_sql(query, engine)


# Engenharia de Recursos
data['win'] = data['position'] == 1
data['avg_time_last_5'] = data.groupby('competitor')['time'].transform(lambda x: x.rolling(5).mean().shift(1))

categories = data['category'].unique()

for category in categories:
    print(f"Evaluating category: {category}")
    
    category_data = data[data['category'] == category]
    
    # Preparando os dados para modelagem
    X = category_data[['avg_time_last_5', 'num_competitors']]
    y = category_data['win']
    X_train, X_test, y_train, y_test = train_test_split(X, y, test_size=0.2, random_state=42)

    # Imputação de valores faltantes
    imputer = SimpleImputer(strategy="mean")
    X_train = imputer.fit_transform(X_train)
    X_test = imputer.transform(X_test)

    # Modelagem
    clf = RandomForestClassifier(n_estimators=100, random_state=42)
    clf.fit(X_train, y_train)

    # Validação
    y_pred = clf.predict(X_test)
    print("Acurácia:", accuracy_score(y_test, y_pred))
    print(classification_report(y_test, y_pred))
    print("-" * 50)