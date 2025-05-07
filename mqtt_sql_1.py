import mysql.connector
import paho.mqtt.client as mqtt
import json
from datetime import datetime

# Conexão MySQL
conn = mysql.connector.connect(
    host="localhost",
    user="root",
    password="",
    database="pisid20245"
)
cursor = conn.cursor()

# Ligações válidas permitidas diretamente em código
ligacoes_validas = {
    (1, 2), (1, 3),
    (2, 4),
    (3, 2),
    (5, 3), (5, 4), (5, 6),
    (6, 8),
    (8, 9), (8, 10),
    (9, 7),
    (7, 5),
    (10, 1)
}

# Callback MQTT
def on_message(client, userdata, msg):
    try:
        payload = json.loads(msg.payload.decode())
    except:
        print("JSON inválido")
        return

    print(f"Recebido em {msg.topic}: {payload}")

    if "filtered" in msg.topic:
        id_grupo = payload.get("Player")

        # Obter o IDUtilizador associado ao grupo
        cursor.execute("SELECT IDUtilizador FROM utilizador WHERE IDGrupo = %s", (id_grupo,))
        res_utilizador = cursor.fetchone()

        if not res_utilizador:
            print(f"Ignorado: Grupo {id_grupo} não registado.")
            return

        id_utilizador = res_utilizador[0]

        # Verificar se existe jogo ativo
        cursor.execute("SELECT IDJogo FROM jogo WHERE IDUtilizador = %s AND Estado = 'Ativo' ORDER BY DataHoraInicio DESC LIMIT 1", (id_utilizador,))
        jogo_ativo = cursor.fetchone()

        if jogo_ativo:
            id_jogo_ativo = jogo_ativo[0]
        else:
            print(f"Nenhum jogo ativo para o grupo {id_grupo}")
            return

        try:
            if "som" in msg.topic:
                # NOTE: Assuming IDSom is AUTO_INCREMENT in the medicoessom table.
                # The database schema MUST be updated for this to work correctly.
                cursor.execute("INSERT INTO medicoessom (Som, Hora, IDJogo) VALUES (%s, %s, %s)", (
                    payload.get("Sound", 0),
                    payload.get("Hour", datetime.now().isoformat()),
                    id_jogo_ativo
                ))
                conn.commit()
                print("✅Inserido em medicoessom")

            elif "movimento" in msg.topic:
                sala_origem = payload.get("RoomOrigin", 0)
                sala_destino = payload.get("RoomDestiny", 0)
                marsami_nome = payload.get("Marsami", 0)
                hora = str(payload.get("Hour", datetime.now().isoformat()))

                # If RoomOrigin is 0, it's a new Marsami spawn, not a standard passage.
                # This is handled by NOVO_MARSAMI alert from mongo_mqtt.py.
                if sala_origem == 0:
                    print(f"Movimento de {marsami_nome} com SalaOrigem 0 ignorado para inserção em medicoespassagens (tratado como NOVO_MARSAMI).")
                    # We might still want to run procedures related to Marsami appearance if not covered by NOVO_MARSAMI handler
                    # For now, skipping the rest of this block for sala_origem == 0
                    return

                # Verificar se a ligação SalaOrigem → SalaDestino é válida
                if (sala_origem, sala_destino) not in ligacoes_validas:
                    alerta_payload = json.dumps({
                        "type": "ALERTA_MOVIMENTO_INVALIDO",
                        "Player": id_grupo, # Added Player field
                        "marsami": marsami_nome,
                        "de": sala_origem,
                        "para": sala_destino,
                        "hora": hora
                    })
                    client.publish(f"pisid/alertas/{id_grupo}", alerta_payload, qos=1)
                    print(f"ALERTA_MOVIMENTO_INVALIDO enviado para {id_grupo} ({sala_origem}→{sala_destino})")
                    return

                # NOTE: Assuming IDMedicao is AUTO_INCREMENT in the medicoespassagens table.
                # The database schema MUST be updated for this to work correctly.
                cursor.execute("INSERT INTO medicoespassagens (SalaOrigem, SalaDestino, Estado, Hora, IDJogo) VALUES (%s, %s, %s, %s, %s)", (
                    sala_origem,
                    sala_destino,
                    payload.get("Status", 0),
                    hora,
                    id_jogo_ativo
                ))
                conn.commit()
                print("Inserido em medicoespassagens")

                is_even = marsami_nome % 2 == 0
                cursor.callproc("AtualizarOuInserirMarsami", [marsami_nome, is_even, hora, id_jogo_ativo])
                print(f"Marsami {'Even' if is_even else 'Odd'} atualizado/inserido")

                cursor.callproc("RemoverMarsamiSalaOrigem", [id_jogo_ativo, sala_origem, marsami_nome])
                conn.commit()

                cursor.callproc("AdicionarMarsamiSalaDestino", [id_jogo_ativo, sala_destino, marsami_nome])
                conn.commit()

                cursor.callproc("AtualizarOcupacaoLabirinto", [sala_origem, sala_destino, is_even, hora, id_jogo_ativo])
                conn.commit()
                print("🏁 Ocupação labirinto atualizada")

        except Exception as e:
            print("Erro ao inserir no MySQL:", e)

    elif "alertas" in msg.topic:
        tipo_alerta = payload.get("type")

        if tipo_alerta == "NOVO_MARSAMI":
            player = payload.get("player")
            marsami_id = payload.get("marsamiID")

            if player is None or marsami_id is None:
                print("Dados incompletos no NOVO_MARSAMI, ignorado.")
                return

            cursor.callproc('VerificarCriarNovoJogo', (player, marsami_id))
            conn.commit()
            print(f"Verificação/criação de novo jogo realizada para Grupo {player}, Marsami {marsami_id}")

            cursor.execute("SELECT jogo.IDJogo FROM jogo JOIN utilizador ON jogo.IDUtilizador = utilizador.IDUtilizador WHERE utilizador.IDGrupo = %s AND jogo.Estado = 'Ativo' ORDER BY jogo.DataHoraInicio DESC LIMIT 1", (player,))
            novo_jogo = cursor.fetchone()

            if novo_jogo:
                id_jogo_ativo = novo_jogo[0]
                print(f"Jogo ativo atualizado para {id_jogo_ativo}")
            else:
                print("Erro ao atualizar jogo ativo após criação")
        else: # Handle other alert types
            id_grupo_from_payload = payload.get("Player")
            final_id_grupo = None

            if id_grupo_from_payload is not None:
                final_id_grupo = id_grupo_from_payload
            else:
                try:
                    # Attempt to extract group ID from topic pisid/alertas/{id_grupo}
                    topic_parts = msg.topic.split('/')
                    if len(topic_parts) > 2 and topic_parts[0] == "pisid" and topic_parts[1] == "alertas":
                        final_id_grupo = int(topic_parts[2])
                    else:
                        print(f"⚠️ Formato de tópico inesperado para extrair id_grupo: {msg.topic}")
                        return
                except (IndexError, ValueError) as e:
                    print(f"⚠️ Não foi possível extrair id_grupo do tópico {msg.topic} (erro: {e}) ou do payload para alerta: {payload}")
                    return
            
            if final_id_grupo is None:
                print(f"⚠️ Não foi possível determinar o id_grupo para o alerta: {payload} no tópico {msg.topic}")
                return

            cursor.execute("SELECT IDUtilizador FROM utilizador WHERE IDGrupo = %s", (final_id_grupo,))
            res_utilizador = cursor.fetchone()
            if not res_utilizador:
                print(f"⚠️ Ignorado: Grupo {final_id_grupo} (do alerta) não registado.")
                return
            
            id_utilizador = res_utilizador[0]
            cursor.execute("SELECT IDJogo FROM jogo WHERE IDUtilizador = %s AND Estado = 'Ativo' ORDER BY DataHoraInicio DESC LIMIT 1", (id_utilizador,))
            jogo_ativo = cursor.fetchone()
            if not jogo_ativo:
                print(f"⚠️ Nenhum jogo ativo para o grupo {final_id_grupo} (do alerta)")
                return
            id_jogo_ativo = jogo_ativo[0]
            # NOTE: Assuming IDMensagem is AUTO_INCREMENT in the mensagens table.
            # The database schema MUST be updated for this to work correctly.
            cursor.execute("INSERT INTO mensagens (Hora, TipoAlerta, Msg, HoraEscrita, IDJogo) VALUES (%s, %s, %s, %s, %s)", (
                payload.get("hora", datetime.now().isoformat()),
                payload.get("type", "ALERTA_DESCONHECIDO"),
                payload.get("msg", "Mensagem automática"),
                datetime.now(),
                id_jogo_ativo
            ))
            conn.commit()
            print("Alerta inserido com sucesso!")

def start_mqtt():
    # Use the newer callback API version to address DeprecationWarning
    client = mqtt.Client(mqtt.CallbackAPIVersion.VERSION2)
    client.on_message = on_message
    client.connect("broker.emqx.io", 1883)
    client.subscribe("pisid/filtered/+/som", qos=2)
    client.subscribe("pisid/filtered/+/movimento", qos=2)
    client.subscribe("pisid/alertas/+", qos=2)
    print("A escutar MQTT e enviar para MySQL com validações de salas...")
    client.loop_forever()

if __name__ == "__main__":
    start_mqtt()
