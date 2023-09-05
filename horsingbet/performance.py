import mysql.connector
from collections import defaultdict
import math

def calculate_points(position, num_competitors):
    if position > num_competitors * 0.5:
        return 0
    position = max(1, position)
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

combo_stats = defaultdict(lambda: [float('inf'), 0, 0, 0, 0.0])

for result in results:
    date, event_name, category, competitor, horse, time, num_competitors, position = result
    time = float(time.replace(",", "."))
    
    if time <= 16.109:
        continue

    points_earned = calculate_points(position, num_competitors)
    combo_key = (competitor, horse)  # Chave para o conjunto competidor + cavalo

    combo_stats[combo_key][0] = min(time, combo_stats[combo_key][0])
    combo_stats[combo_key][1] += points_earned
    combo_stats[combo_key][2] += 1
    combo_stats[combo_key][3] += 1 if position == 1 else 0
    combo_stats[combo_key][4] += time
    
combo_inserts = []

for (competitor, horse), stats in combo_stats.items():
    avg_time = stats[4] / stats[2]
    combo_inserts.append((competitor, horse, stats[0], stats[1], stats[2], stats[3], avg_time))

# Inserção em lote para conjuntos competidor + cavalo
cur.executemany('''
    INSERT INTO competitorhorseperformance (competitor_name, horse_name, best_time, points, num_races, num_victories, average_time) 
    VALUES (%s, %s, %s, %s, %s, %s, %s) 
    ON DUPLICATE KEY UPDATE 
    best_time = LEAST(best_time, VALUES(best_time)), 
    points = points + VALUES(points), 
    num_races = num_races + VALUES(num_races), 
    num_victories = num_victories + VALUES(num_victories), 
    average_time = (average_time * (num_races - 1) + VALUES(average_time)) / num_races
''', combo_inserts)

print("Total Competitor-Horse Combinations Processed:", len(combo_inserts))

con.commit()
cur.close()
con.close()
