import requests
from bs4 import BeautifulSoup
import mysql.connector
import datetime

# Configurando a conexão com o banco de dados
con = mysql.connector.connect(
    host='localhost',
    user='root',
    password='',
    database='horsingbet'
)

cur = con.cursor()

# Criar a tabela resultados se não existir
create_table_query = '''CREATE TABLE IF NOT EXISTS resultados
                    (date TEXT,
                    event_name TEXT,
                    category TEXT,
                    position INT,
                    competitor TEXT,
                    horse TEXT,
                    time TEXT); '''
cur.execute(create_table_query)

# URL da página de resultados
url = 'https://www.sgpsistema.com/?page=mainres&evt=4850&categ=75597'

# Fazer a requisição para a URL
response = requests.get(url)
soup = BeautifulSoup(response.text, 'html.parser')

# Extrair informações sobre o evento
event_info = soup.find('p').text
event_name = event_info.split('-')[0].strip()
event_date = ' '.join(event_info.split('-')[1].strip().split()[:3])
event_date = datetime.datetime.strptime(event_date, '%d de %B de %Y').strftime('%Y-%m-%d')

# Extrair informações sobre a categoria
category_name = soup.find('h3', {'class': 'clear'}).text.split('-')[1].strip()

# Extrair informações sobre os resultados
table = soup.find('table')
results = table.find_all('tr')[1:]

for row in results:
    cols = row.find_all('td')
    position = int(cols[0].text.strip())
    competitor_name = cols[2].text.strip()
    horse_name = cols[3].text.strip()
    time = cols[6].text.strip()

    if time != 'SAT':
        # Verificar se o resultado já existe na tabela 'geral'
        cur.execute("SELECT * FROM geral WHERE date = %s AND event_name = %s AND category = %s AND competitor = %s AND horse = %s AND time = %s", (event_date, event_name, category_name, competitor_name, horse_name, time))
        result = cur.fetchone()
        
        if result is None:
            # Se o resultado não existir, insira-o na tabela 'geral'
            insert_query = "INSERT INTO geral (date, event_name, category, competitor, horse, time, num_competitors, position) VALUES (%s, %s, %s, %s, %s, %s, %s, %s)"
            cur.execute(insert_query, (event_date, event_name, category_name, competitor_name, horse_name, time, len(results), position))

            # E também insira na tabela 'resultados'
            insert_query = "INSERT INTO resultados (date, event_name, category, position, competitor, horse, time) VALUES (%s, %s, %s, %s, %s, %s, %s)"
            cur.execute(insert_query, (event_date, event_name, category_name, position, competitor_name, horse_name, time))
            
            # Atualização dos pontos só ocorre se o resultado for novo
            cur.execute("SELECT * FROM competidor_estatisticas WHERE name = %s", (competitor_name,))
            competitor = cur.fetchone()
            points = len(results) - position
            if competitor is None:
                cur.execute("INSERT INTO competidor_estatisticas (name, best_time, first_place_count, second_place_count, third_place_count, points) VALUES (%s, %s, %s, %s, %s, %s)",
                            (competitor_name, time, 1 if position == 1 else 0, 1 if position == 2 else 0, 1 if position == 3 else 0, points))
            else:
                best_time = min(float(time.replace(",", ".")), float(competitor[2]))
                first_place_count = competitor[3] + (1 if position == 1 else 0)
                second_place_count = competitor[4] + (1 if position == 2 else 0)
                third_place_count = competitor[5] + (1 if position == 3 else 0)
                points = competitor[6] + points
                cur.execute("UPDATE competidor_estatisticas SET best_time = %s, first_place_count = %s, second_place_count = %s, third_place_count = %s, points = %s WHERE name = %s",
                            (best_time, first_place_count, second_place_count, third_place_count, points, competitor_name))

            cur.execute("SELECT * FROM cavalo_estatisticas WHERE name = %s", (horse_name,))
            horse = cur.fetchone()
            if horse is None:
                cur.execute("INSERT INTO cavalo_estatisticas (name, best_time, first_place_count, second_place_count, third_place_count, points) VALUES (%s, %s, %s, %s, %s, %s)",
                            (horse_name, time, 1 if position == 1 else 0, 1 if position == 2 else 0, 1 if position == 3 else 0, points))
            else:
                best_time = min(float(time.replace(",", ".")), float(horse[2]))
                first_place_count = horse[3] + (1 if position == 1 else 0)
                second_place_count = horse[4] + (1 if position == 2 else 0)
                third_place_count = horse[5] + (1 if position == 3 else 0)
                points = horse[6] + points
                cur.execute("UPDATE cavalo_estatisticas SET best_time = %s, first_place_count = %s, second_place_count = %s, third_place_count = %s, points = %s WHERE name = %s",
                            (best_time, first_place_count, second_place_count, third_place_count, points, horse_name))

# Commit as mudanças
con.commit()

# Feche a conexão com o banco de dados
con.close()
