import paho.mqtt.client as mqtt
import json
from pymongo import MongoClient
from datetime import datetime
import uuid
import time

# 🔧 Validações
def is_valid_number(val):
    try:
        return isinstance(val, (int, float)) and not isinstance(val, bool)
    except:
        return False

def is_valid_datetime(val):
    try:
        datetime.fromisoformat(val)
        return True
    except:
        return False

# 🧠 Guardar erro no Mongo
def regista_erro(mensagem, sensor, motivo):
    erro = {
        "Id": uuid.uuid4().hex,
        "tipoSensor": sensor,
        "tipoErro": motivo,
        "mensOrig": mensagem,
        "hora": datetime.now()
    }
    mycol_erros.insert_one(erro)
    print(f"❌ Erro: {motivo} → {mensagem}")

# 🛠 Corrigir JSON mal formatado
def try_fix_json(text):
    try:
        return json.loads(text)
    except json.JSONDecodeError:
        try:
            for key in ["Player", "Hour", "Sound", "Marsami", "RoomOrigin", "RoomDestiny", "Status"]:
                text = text.replace(f"{key}:", f'"{key}":')
            return json.loads(text)
        except:
            return None

# 📦 MongoDB setup
mongo_client = MongoClient('mongodb://localhost:27017/')
db = mongo_client["labirinto_db"]
mycol_mov = db["MedicoesMovimento"]
mycol_sound = db["MedicoesSom"]
mycol_erros = db["Erros"]

# 📊 Estado para prevenção de spam
ultimo_som = {}  # {player: valor}
ultimo_mov = {}  # {(player, marsami): (orig, dest, status)}
ultima_msg_tempo = {}  # {(player, tipo): timestamp}

# 🔗 MQTT callbacks
def on_connect(client, userdata, flags, rc):
    print("✅ Ligado ao MQTT com código:", rc)
    for i in range(100):
        client.subscribe(f"pisid_mazemov_{i}", qos=2)
        client.subscribe(f"pisid_mazesound_{i}", qos=2)

def on_message(client, userdata, msg):
    decoded = msg.payload.decode("utf-8")
    data = try_fix_json(decoded)
    if not data:
        regista_erro(decoded, "Desconhecido", "JSON inválido")
        return

    print(f"📩 {msg.topic}: {data}")

    agora = time.time()

    if "mazesound" in msg.topic:
        if not all(k in data for k in ["Sound", "Hour", "Player"]):
            regista_erro(data, "Som", "Campos em falta")
            return
        if not is_valid_number(data["Sound"]):
            regista_erro(data, "Som", "Sound inválido")
            return
        if not is_valid_datetime(data["Hour"]):
            regista_erro(data, "Som", "Hora inválida")
            return

        # 🚫 Som repetido (spam)
        if ultimo_som.get(data["Player"]) == data["Sound"]:
            print("🔁 Som repetido ignorado.")
            return
        ultimo_som[data["Player"]] = data["Sound"]

        # 🚫 Anti-flood para som
        chave_tempo = (data["Player"], "som")
        if agora - ultima_msg_tempo.get(chave_tempo, 0) < 0.1:
            print("🚫 Mensagem de som muito rápida ignorada.")
            return
        ultima_msg_tempo[chave_tempo] = agora

        mycol_sound.insert_one({
            "soundLevel": data["Sound"],
            "grupoID": data["Player"],
            "hora": data["Hour"]
        })
        print("✅ Som guardado")

    elif "mazemov" in msg.topic:
        campos_necessarios = ["Marsami", "RoomOrigin", "RoomDestiny", "Status", "Player"]
        if not all(k in data for k in campos_necessarios):
            regista_erro(data, "Movimento", "Campos em falta")
            return

        try:
            for k in campos_necessarios:
                data[k] = int(data[k])
        except:
            regista_erro(data, "Movimento", "Campo numérico inválido")
            return

        # 🚫 Movimento repetido (spam)
        chave_mov = (data["Player"], data["Marsami"])
        valores_mov = (data["RoomOrigin"], data["RoomDestiny"], data["Status"])
        if ultimo_mov.get(chave_mov) == valores_mov:
            print("🔁 Movimento repetido ignorado.")
            return
        ultimo_mov[chave_mov] = valores_mov

        # 🚫 Anti-flood para movimento
        chave_tempo = (data["Player"], "mov")
        if agora - ultima_msg_tempo.get(chave_tempo, 0) < 0.1:
            print("🚫 Mensagem de movimento muito rápida ignorada.")
            return
        ultima_msg_tempo[chave_tempo] = agora

        hora_valida = data.get("Hour") if ("Hour" in data and is_valid_datetime(data["Hour"])) else datetime.now().isoformat()
        print(f"🕒 Hora atribuída ao movimento: {hora_valida}")

        mycol_mov.insert_one({
            "marsamiID": data["Marsami"],
            "salaOrigem": data["RoomOrigin"],
            "salaDestino": data["RoomDestiny"],
            "estado": data["Status"],
            "grupoID": data["Player"],
            "hora": hora_valida
        })
        print("✅ Movimento guardado")

# 🚀 Início do cliente MQTT (executável diretamente)
if __name__ == "__main__":
    client = mqtt.Client()
    client.on_connect = on_connect
    client.on_message = on_message
    client.connect("broker.emqx.io", 1883)
    print("🔄 A escutar mensagens MQTT...")
    client.loop_forever()
