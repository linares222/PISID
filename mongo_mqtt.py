import time
from datetime import datetime
import paho.mqtt.publish as publish
from pymongo import MongoClient
import json

# ⚙️ Configurações
broker = "broker.emqx.io"
intervalo_segundos = 0.5
limite_som_alerta = 21
tempo_estagnado = 5  # segundos
tempo_inatividade = 10  # segundos

# MongoDB
mongo_client = MongoClient('mongodb://localhost:27017/')
db = mongo_client["labirinto_db"]
col_som = db["MedicoesSom"]
col_mov = db["MedicoesMovimento"]
col_registos = db["Registos"]

# Inicializar últimos IDs
registos = col_registos.find_one({})
ultimo_id_som = registos.get("ultimo_som") if registos else None
ultimo_id_mov = registos.get("ultimo_mov") if registos else None

# Estado para alertas
ultimo_som = {}
ultimo_alerta_critico = set()
ultimo_alerta_estagnado = {}
ultimo_movimento = {}
marsamis_parados_alertados = set()
inatividade_alertada = set()

def atualizar_registos(ultimo_som_id, ultimo_mov_id):
    col_registos.update_one(
        {},
        {"$set": {"ultimo_som": ultimo_som_id, "ultimo_mov": ultimo_mov_id}},
        upsert=True
    )

def start_mongo_mqtt():
    global ultimo_id_som, ultimo_id_mov
    print("🚀 Ligado ao Mongo e pronto para reenviar via MQTT...")

    while True:
        time.sleep(intervalo_segundos)
        agora = datetime.now()

        # === SOM ===
        filtro_som = {"_id": {"$gt": ultimo_id_som}} if ultimo_id_som else {}
        novos_sons = list(col_som.find(filtro_som).sort("_id", 1))

        for doc in novos_sons:
            player = doc["grupoID"]
            sound = doc["soundLevel"]
            hora = str(doc["hora"])

            publish.single(
                topic=f"pisid/filtered/{player}/som",
                payload=json.dumps({
                    "Player": player,
                    "Hour": hora,
                    "Sound": sound
                }),
                hostname=broker,
                qos=1
            )
            print(f"📤 Enviado SOM (Player {player}): {sound}")

            # Alertas de som
            if sound > limite_som_alerta and (player, sound) not in ultimo_alerta_critico:
                publish.single(
                    topic=f"pisid/alertas/{player}",
                    payload=json.dumps({
                        "type": "ALERTA_SOM_CRITICO",
                        "valor": sound,
                        "hora": hora
                    }),
                    hostname=broker,
                    qos=1
                )
                print(f"🔔 ALERTA_SOM_CRITICO (Player {player}): {sound}")
                ultimo_alerta_critico.add((player, sound))

            last_val, last_time = ultimo_som.get(player, (None, None))
            if sound == last_val:
                if last_time and (agora - last_time).total_seconds() >= tempo_estagnado:
                    if player not in ultimo_alerta_estagnado:
                        publish.single(
                            topic=f"pisid/alertas/{player}",
                            payload=json.dumps({
                                "type": "ALERTA_SOM_ESTAGNADO",
                                "valor": sound,
                                "hora": hora
                            }),
                            hostname=broker,
                            qos=1
                        )
                        print(f"🔔 ALERTA_SOM_ESTAGNADO (Player {player}): {sound}")
                        ultimo_alerta_estagnado[player] = agora
            else:
                ultimo_som[player] = (sound, agora)
                ultimo_alerta_estagnado.pop(player, None)

            ultimo_id_som = doc["_id"]
            atualizar_registos(ultimo_id_som, ultimo_id_mov)

        # === MOVIMENTO ===
        filtro_mov = {"_id": {"$gt": ultimo_id_mov}} if ultimo_id_mov else {}
        novos_movs = list(col_mov.find(filtro_mov).sort("_id", 1))

        for doc in novos_movs:
            player = doc["grupoID"]
            marsami = doc["marsamiID"]
            status = doc["estado"]
            hora = str(doc["hora"])
            room_origin = doc["salaOrigem"]
            room_destiny = doc["salaDestino"]

            publish.single(
                topic=f"pisid/filtered/{player}/movimento",
                payload=json.dumps({
                    "Player": player,
                    "Marsami": marsami,
                    "RoomOrigin": room_origin,
                    "RoomDestiny": room_destiny,
                    "Status": status,
                    "Hour": hora
                }),
                hostname=broker,
                qos=1
            )
            print(f"📤 Enviado MOVIMENTO (Player {player}, Marsami {marsami})")


            ultimo_movimento[player] = agora
            inatividade_alertada.discard(player)
            ultimo_id_mov = doc["_id"]
            atualizar_registos(ultimo_id_som, ultimo_id_mov)

        # 🔔 Inatividade
        for player, last_time in ultimo_movimento.items():
            if (datetime.now() - last_time).total_seconds() > tempo_inatividade:
                if player not in inatividade_alertada:
                    publish.single(
                        topic=f"pisid/alertas/{player}",
                        payload=json.dumps({
                            "type": "ALERTA_INATIVIDADE",
                            "hora": datetime.now().isoformat()
                        }),
                        hostname=broker,
                        qos=1
                    )
                    print(f"🔔 ALERTA_INATIVIDADE (Player {player})")
                    inatividade_alertada.add(player)

if __name__ == "__main__":
    start_mongo_mqtt()
