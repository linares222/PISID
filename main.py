import threading
import time
import signal
import sys
from mqtt_mongo import on_connect as mqtt_on_connect, on_message as mqtt_on_message
from mongo_mqtt import start_mongo_mqtt
import paho.mqtt.client as mqtt

# Flag de controlo global
running = True

def signal_handler(sig, frame):
    global running
    print("\nCtrl+C recebido. A terminar as threads...")
    running = False
    sys.exit(0)

signal.signal(signal.SIGINT, signal_handler)  # Captura Ctrl+C

def start_mqtt_mongo():
    client = mqtt.Client()
    client.on_connect = mqtt_on_connect
    client.on_message = mqtt_on_message
    client.connect("broker.emqx.io", 1883)
    print("A escutar mensagens MQTT (MQTT → Mongo)...")
    client.loop_forever()

def main():
    t1 = threading.Thread(target=start_mqtt_mongo, name="Thread-MQTT→Mongo", daemon=True)
    t2 = threading.Thread(target=start_mongo_mqtt, name="Thread-Mongo→MQTT", daemon=True)

    print("Iniciando threads MQTT → Mongo e Mongo → MQTT...\n")
    t1.start()
    t2.start()

    # Mantém processo vivo e permite Ctrl+C
    while running:
        time.sleep(1)

if __name__ == "__main__":
    main()