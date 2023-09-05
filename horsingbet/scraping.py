import requests
from bs4 import BeautifulSoup
import mysql.connector
from urllib.parse import urljoin
from datetime import datetime
import sys
import io

# Configura a saída padrão com a codificação 'utf-8'
sys.stdout = io.TextIOWrapper(sys.stdout.buffer, encoding='utf-8')

# Configurando a conexão com o banco de dados
con = mysql.connector.connect(
    host='localhost',
    user='root',
    password='',
    database='horsingbet'
)

cur = con.cursor()

create_table_query = '''CREATE TABLE IF NOT EXISTS geral
                    (date DATE,
                    event_name TEXT,
                    category TEXT,
                    competitor TEXT,
                    horse TEXT,
                    time TEXT,
                    num_competitors INT,
                    position INT); '''

cur.execute(create_table_query)
con.commit()

# Obtenha a data mais recente a partir do banco de dados
cur.execute("SELECT MAX(date) FROM geral")
result = cur.fetchone()
latest_date = result[0] if result else None

base_url = 'https://www.sgpsistema.com/'
years = range(2015, datetime.now().year + 1)  # 2010 até o ano atual

for year in years:
    page_url = f"https://www.sgpsistema.com/?page=mainres&type={year}&uf=0"
    page = requests.get(page_url)
    soup = BeautifulSoup(page.content, 'html.parser')
    print(page_url)

    events = soup.find_all('p')
    for event in events:
        content = event.contents[0] if event.contents else None
        if content and isinstance(content, str):  # Verificar se content é uma string
            try:
                date = datetime.strptime(content.strip().split('-')[0].strip(), '%d/%m/%Y').date()
            except ValueError:
                continue  # Se a data não for válida, pule para o próximo evento
        else:
            continue  # Se content não for uma string, pule para o próximo evento

        # Verifique se a data do evento é após a data mais recente no banco de dados
        if latest_date and date <= latest_date:
            continue

        event_name_element = event.find('b')
        event_link_element = event.find('a')

        if event_name_element is not None:  # Verificar se encontramos o elemento do nome do evento
            event_name = event_name_element.text
        else:
            continue  # Se não encontrar o elemento, pule para o próximo evento

        if event_link_element is not None:  # Verificar se encontramos o elemento do link do evento
            event_link = urljoin(base_url, event_link_element['href'])
        else:
            continue  # Se não encontrar o elemento, pule para o próximo evento

        print(event_link)
        # Acessando o link do evento
        event_page = requests.get(event_link)
        event_soup = BeautifulSoup(event_page.content, 'html.parser')
        categories = event_soup.find_all('a', class_="smallbutt")

        for category in categories:
            category_text = category.text.strip() if category.text else None
            if not category_text:  # Se o texto da categoria não for encontrado, passe para a próxima categoria
                continue

            try:
                # Dividir o texto da categoria para obter o nome da categoria e o número de inscrições
                category_parts = category_text.split()
                num_inscriptions = category_parts[-2]  # Pega a penúltima parte como o número de inscrições
                num_inscriptions = int(num_inscriptions)  # tenta converter o número de inscrições para int
                category_name = ' '.join(category_parts[:-2])  # o nome da categoria é todo o texto antes do número de inscrições
            except Exception as e:
                print(f"Erro ao processar categoria: {category_text}. Erro: {str(e)}")
                continue

            category_link = urljoin(base_url, category['href'])
            category_name_element = category.find('div')
            if category_name_element is not None:  # Verificar se encontramos o elemento do nome da categoria
                category_name = category_name_element.text
            else:
                continue  # Se não encontrar o elemento, pule para a próxima categoria

            print(category_link)
            print(category_name)

            # Acessando o link da categoria
            category_page = requests.get(category_link)
            category_soup = BeautifulSoup(category_page.content, 'html.parser')
            rows = category_soup.find_all('tr')

            for row in rows:
                cols = row.find_all('td')
                if len(cols) >= 7:  # Verifica se há colunas suficientes
                    position = cols[0].text.strip()
                    competitor = cols[2].text.strip()
                    horse = cols[3].text.strip()
                    time = cols[6].text.strip()

                    try:
                        position = int(position)  # Tenta converter a posição para um número inteiro
                    except ValueError as e:
                        print(f"Erro ao processar posição: {position}. Erro: {str(e)}")
                        continue

                    try:
                        # Verifica se o valor 'horse' pode ser convertido para um número float válido
                        float_time = float(time)
                    except ValueError:
                        print(f"Ignorando competidor inválido: {competitor}")
                        continue

                    # Use as variáveis position, competitor, horse e time aqui...
                    print(position, competitor, horse, time)

                    insert_query = '''INSERT INTO geral (date, event_name, category, competitor, horse, time, num_competitors, position)
                                        VALUES (%s, %s, %s, %s, %s, %s, %s, %s);'''
                    data = (date, event_name, category_name, competitor, horse, time, num_inscriptions, position)
                    cur.execute(insert_query, data)
                    con.commit()

# Fechando a conexão
cur.close()
con.close()
