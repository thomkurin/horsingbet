import mysql.connector
from collections import defaultdict
import math

def calculate_points(position, num_competitors):
    # Se a posição está acima de 50% do total de competidores, retorna 0 pontos
    if position > num_competitors * 0.5:
        return 0
    
    # Garantindo que a posição nunca seja 0 (para evitar o log de 0)
    position = max(1, position)
    
    # Suponha que a primeira posição receba 100 pontos.
    # Usamos o logaritmo na base 2 para uma diminuição mais lenta.
    return 100 / math.log2(position + 1)

con = mysql.connector.connect(
    host='localhost',
    user='root',
    password='',
    database='horsingbet'
)

cur = con.cursor()
cur.execute("SELECT * FROM geral WHERE date >= '2010-01-01'")
results = cur.fetchall()

competitor_stats = defaultdict(lambda: [float('inf'), 0, 0, 0, 0.0])
horse_stats = defaultdict(lambda: [float('inf'), 0, 0, 0, 0.0])

for result in results:
    date, event_name, category, competitor, horse, time, num_competitors, position = result
    time = float(time.replace(",", "."))
    
    if time <= 16.109:
        continue

    points_earned = calculate_points(position, num_competitors)

    # Atualizar as estatísticas do competidor
    competitor_stats[competitor][0] = min(time, competitor_stats[competitor][0])
    competitor_stats[competitor][1] += points_earned
    competitor_stats[competitor][2] += 1  # Increase race count
    competitor_stats[competitor][3] += 1 if position == 1 else 0  # Increase victory count if won
    competitor_stats[competitor][4] += time  # Accumulate total time

    # Atualizar as estatísticas do cavalo
    horse_stats[horse][0] = min(time, horse_stats[horse][0])
    horse_stats[horse][1] += points_earned
    horse_stats[horse][2] += 1
    horse_stats[horse][3] += 1 if position == 1 else 0
    horse_stats[horse][4] += time
    
# Criar listas para inserções em lote
competitor_inserts = []
horse_inserts = []

for competitor, stats in competitor_stats.items():
    avg_time = stats[4] / stats[2]
    competitor_inserts.append((competitor, stats[0], stats[1], stats[2], stats[3], avg_time))

for horse, stats in horse_stats.items():
    avg_time = stats[4] / stats[2]
    horse_inserts.append((horse, stats[0], stats[1], stats[2], stats[3], avg_time))

# Inserção em lote para competidores e cavalos
cur.executemany('''
    INSERT INTO competitorperformance (name, best_time, points, num_races, num_victories, average_time) 
    VALUES (%s, %s, %s, %s, %s, %s) 
    ON DUPLICATE KEY UPDATE 
    best_time = LEAST(best_time, VALUES(best_time)), 
    points = points + VALUES(points), 
    num_races = num_races + VALUES(num_races), 
    num_victories = num_victories + VALUES(num_victories), 
    average_time = (average_time * (num_races - 1) + VALUES(average_time)) / num_races
''', competitor_inserts)

cur.executemany('''
    INSERT INTO horseperformance (name, best_time, points, num_races, num_victories, average_time) 
    VALUES (%s, %s, %s, %s, %s, %s) 
    ON DUPLICATE KEY UPDATE 
    best_time = LEAST(best_time, VALUES(best_time)), 
    points = points + VALUES(points), 
    num_races = num_races + VALUES(num_races), 
    num_victories = num_victories + VALUES(num_victories), 
    average_time = (average_time * (num_races - 1) + VALUES(average_time)) / num_races
''', horse_inserts)

print("Total Competitors Processed:", len(competitor_inserts))

print("Total Horses Processed:", len(horse_inserts))

con.commit()
cur.close()
con.close()