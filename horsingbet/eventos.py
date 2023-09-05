import io
import requests
from bs4 import BeautifulSoup
from urllib.parse import urljoin
from datetime import datetime, timedelta
import mysql.connector
import sys
import re
from decimal import Decimal


sys.stdout = io.TextIOWrapper(sys.stdout.buffer, encoding='utf-8')

con = mysql.connector.connect(
    host='localhost',
    user='root',
    password='',
    database='horsingbet'
)

cur = con.cursor()

def truncate_tables():
    tables = ['eventos', 'categorias', 'largada']
    for table in tables:
        cur.execute(f'TRUNCATE {table}')
        cur.execute(f'ALTER TABLE {table} AUTO_INCREMENT = 1')
    con.commit()


truncate_tables()
base_url = 'https://www.sgpsistema.com/'
page_url = f"https://www.sgpsistema.com/?page=provas&type=all"

meses_dict = {
    'Janeiro': '01',
    'Fevereiro': '02',
    'Março': '03',
    'Abril': '04',
    'Maio': '05',
    'Junho': '06',
    'Julho': '07',
    'Agosto': '08',
    'Setembro': '09',
    'Outubro': '10',
    'Novembro': '11',
    'Dezembro': '12'
}

def insert_event(event):
    try:
        image_url = urljoin(base_url, event.find('img', class_='cpicon')['src'])
        name = event.find('b').text.strip()
        date_text = event.find('div', class_='infocase').text.split('-')[1].strip().split(' - ')[0]
        match = re.search(r'(\d+) a (\d+) de (\w+) de (\d+)', date_text)
        if match:
            dia_inicio = int(match.group(1))
            dia_fim = int(match.group(2))
            if dia_inicio > dia_fim:
                dia_inicio, dia_fim = dia_fim, dia_inicio
            month = meses_dict[match.group(3)]
            year = match.group(4)
        else:
            match = re.search(r'(\d+) de (\w+) de (\d+)', date_text)
            if match:
                dia_inicio = dia_fim = int(match.group(1))
                month = meses_dict[match.group(2)]
                year = match.group(3)

        dia_inicio = datetime.strptime(f"{dia_inicio}/{month}/{year}", '%d/%m/%Y').date()
        dia_fim = datetime.strptime(f"{dia_fim}/{month}/{year}", '%d/%m/%Y').date()
        cur.execute('INSERT INTO eventos (image_url, name, dia_inicio, dia_fim) VALUES (%s, %s, %s, %s)', (image_url, name, dia_inicio, dia_fim))
        con.commit()
        event_id = cur.lastrowid
        return event_id, dia_inicio, dia_fim
    except (mysql.connector.Error, mysql.connector.Warning) as e:
        print(f"Erro ao inserir evento: {e}")
        return None, None, None

def fetch_and_insert_live_data(event_id, live_url, dia_inicio, dia_fim):
    try:
        live_page = requests.get(live_url)
        live_soup = BeautifulSoup(live_page.content, 'html.parser')

        dias_da_semana = {0:'Segunda', 1:'Terça', 2:'Quarta', 3:'Quinta', 4:'Sexta', 5:'Sábado', 6:'Domingo'}
        dias_do_evento = {}
        for i in range((dia_fim - dia_inicio).days + 1):
            dia = dia_inicio + timedelta(days=i)
            dias_do_evento[dias_da_semana[dia.weekday()]] = dia
        print(f'os dias do evento são {dias_do_evento}')

        if not live_soup.find_all('a', class_='liveitem'):
            print("Elementos de categoria não encontrados")
            sys.exit()

        dia_semana_text = None
        category_id = None

        def process_element(element):
            nonlocal dia_semana_text, category_id
            if element.name == 'h3':
                dia_semana_text = element.text.strip()
            elif element.name == 'a' and 'liveitem' in element.get('class', []):
                category_name = element.text.split('(')[0].strip()
                if dia_semana_text in dias_do_evento:
                    dia_semana = dias_do_evento[dia_semana_text]
                    cur.execute('INSERT INTO categorias (event_id, name, dia_semana) VALUES (%s, %s, %s)', (event_id, category_name, dia_semana))
                    category_id = cur.lastrowid
                    print(f"numero {category_id} nome {category_name} referente ao evento {event_id}")
                    con.commit()
        con.start_transaction()
        for element in live_soup.recursiveChildGenerator():
            if isinstance(element, str):
                continue  # Pule este elemento, pois é uma string
            process_element(element)

        # Processamento dos competidores
        if category_id:
            base_url = live_url.split('/live')[0]
            category_url = urljoin(base_url, live_soup.find('a', class_='liveitem')['href'])
            category_page = requests.get(category_url)
            category_soup = BeautifulSoup(category_page.content, 'html.parser')
            competitors = category_soup.find_all('tr')

            for competitor in competitors:
                cols = competitor.find_all('td')
                if len(cols) >= 3:
                    competitor_name = cols[1].text.strip()
                    horse_name = cols[2].text.strip()
                    cur.execute('INSERT INTO largada (category_id, event_id, competitor_name, horse_name) VALUES (%s, %s, %s, %s)', (category_id, event_id, competitor_name, horse_name))
                    con.commit()

    except requests.exceptions.RequestException as e:
        con.rollback()
        print(f"Erro ao buscar dados ao vivo: {e}")



def fetch_combo_points(competitor_name, horse_name):
    try:
        
        cur.execute("SELECT points FROM competitorhorseperformance WHERE competitor_name = %s AND horse_name = %s", (competitor_name, horse_name))
        result = cur.fetchone()
        con.commit()
        return result[0] if result else 0.01  # Alterado para retornar 0.01 se não houver resultado
    except Exception as e:
        con.rollback()
        print(f"Erro ao buscar pontos combo: {e}")

def calculate_probability(points, total_points):
    if total_points == 0:
        return 0
    return points / total_points


def calculate_odds(probability, margin=0.05):
    odds = 1 / Decimal(probability)
    adjusted_odds = odds / (1 - Decimal(margin))
    return adjusted_odds


def update_odds_for_category(category_id):
    try:
        
        cur.execute("SELECT competitor_name, horse_name FROM largada WHERE category_id = %s", (category_id,))
        competitors = cur.fetchall()
        total_points = sum(fetch_combo_points(competitor_name, horse_name) for competitor_name, horse_name in competitors)

        if total_points == 0:
            print(f"Total de pontos para a categoria {category_id} é zero, não é possível calcular odds")
            return

        for competitor_name, horse_name in competitors:
            points = fetch_combo_points(competitor_name, horse_name)
            probability = calculate_probability(points, total_points)
            odds = calculate_odds(probability)
            cur.execute("UPDATE largada SET probability = %s, odds = %s WHERE category_id = %s AND competitor_name = %s AND horse_name = %s", (probability, odds, category_id, competitor_name, horse_name))
            con.commit()
    except Exception as e:
        con.rollback()
        print(f"Erro ao atualizar odds para a categoria {category_id}: {e}")

def fetch_and_process_data():
    try:
        
        page = requests.get(page_url)
        soup = BeautifulSoup(page.content, 'html.parser')
        event_elements = soup.find_all('div', class_='evtblock opevt')
        live_urls = [urljoin(base_url, event.find('a', class_='butopt lvbt')['href'] + "&opt=listas")
             for event in event_elements 
             if event.find('a', class_='butopt lvbt')]

        print(f"Encontrados {len(live_urls)} elementos de eventos com transmissão ao vivo")

        for event, live_url in zip(event_elements, live_urls):
            event_id, dia_inicio, dia_fim = insert_event(event)
            if event_id:
                print(f"Evento {event_id} inserido com sucesso")
                fetch_and_insert_live_data(event_id, live_url, dia_inicio, dia_fim)
                cur.execute("SELECT id FROM categorias WHERE event_id = %s", (event_id,))
                category_ids = [category_id[0] for category_id in cur.fetchall()]
                for category_id in category_ids:
                    update_odds_for_category(category_id)
                # Removido a segunda chamada para fetch_and_insert_live_data
                print(f"{len(category_ids)} categorias inseridas para o evento {event_id}")
        con.commit()
    except requests.exceptions.RequestException as e:
        con.rollback()
        print(f"Erro ao buscar e processar dados: {e}")

if __name__ == '__main__':
    try:
        fetch_and_process_data()
    except Exception as e:
        print(f"Erro inesperado: {e}")
    finally:
        cur.close()
        con.close()
