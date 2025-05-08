import mysql.connector
import paho.mqtt.client as mqtt
import json
from datetime import datetime

# ⚙️ Conexão MySQL local (pisid20245)
conn = mysql.connector.connect(
    host="localhost",
    user="root",
    password="",
    database="pisid20245"
)
cursor = conn.cursor()

# ⚙️ Conexão à BD remota (maze)
conn_maze = mysql.connector.connect(
    host="194.210.86.10",
    user="aluno",
    password="aluno",
    database="maze"
)
cursor_maze = conn_maze.cursor()

# 📩 Callback MQTT
def on_message(client, userdata, msg):
    try:
        payload = json.loads(msg.payload.decode())
    except:
        print("❌ JSON inválido")
        return

    print(f"📥 Recebido em {msg.topic}: {payload}")

    if "filtered" in msg.topic:
        id_grupo = payload.get("Player")

        # 📋 Obter o IDUtilizador associado ao grupo
        cursor.execute("""
            SELECT IDUtilizador FROM utilizador
            WHERE IDGrupo = %s
        """, (id_grupo,))
        res_utilizador = cursor.fetchone()

        if not res_utilizador:
            print(f"⚠️ Ignorado: Grupo {id_grupo} não registado.")
            return

        id_utilizador = res_utilizador[0]

        # 📋 Verificar se existe jogo ativo
        cursor.execute("""
            SELECT IDJogo FROM jogo
            WHERE IDUtilizador = %s AND Estado = 'Ativo'
            ORDER BY DataHoraInicio DESC
            LIMIT 1
        """, (id_utilizador,))
        jogo_ativo = cursor.fetchone()

        if not jogo_ativo:
            print(f"⚠️ Nenhum jogo ativo para o grupo {id_grupo}")
            return

        id_jogo_ativo = jogo_ativo[0]

        try:
            if "som" in msg.topic:
                cursor.execute("""
                    INSERT INTO medicoessom (Som, Hora, IDJogo)
                    VALUES (%s, %s, %s)
                """, (
                    payload.get("Sound", 0),
                    payload.get("Hour", datetime.now().isoformat()),
                    id_jogo_ativo
                ))
                conn.commit()
                print("✅ Inserido em medicoessom")

            elif "movimento" in msg.topic:
                sala_origem = payload.get("RoomOrigin", 0)
                sala_destino = payload.get("RoomDestiny", 0)
                marsami_nome = payload.get("Marsami", 0)
                status = payload.get("Status", 0)
                hora = str(payload.get("Hour", datetime.now().isoformat()))

                # ⚠️ Ignorar movimentos com status inválido
                if status == 0:
                    print(f"⚠️ Ignorado: Movimento com status 0 (Marsami {marsami_nome})")
                    return

                # ⚠️ Verificar ligação entre salas (sala 0 é exceção)
                if sala_origem != 0:
                    cursor_maze.execute("""
                        SELECT COUNT(*) FROM Corridor
                        WHERE (Rooma = %s AND Roomb = %s)
                           OR (Rooma = %s AND Roomb = %s)
                    """, (sala_origem, sala_destino, sala_destino, sala_origem))
                    ligadas = cursor_maze.fetchone()[0]
                    if ligadas == 0:
                        print(f"🚫 Salas {sala_origem} e {sala_destino} não estão ligadas")
                        return

                # Verifica se o marsami existe no jogo
                cursor.execute("""
                    SELECT COUNT(*) FROM marsami
                    WHERE IDMarsami = %s AND IDJogo = %s
                """, (marsami_nome, id_jogo_ativo))
                exists = cursor.fetchone()[0]

                if not exists:
                    print(f"⚠️ Marsami {marsami_nome} não existe no jogo {id_jogo_ativo}")
                    return

                # Registrar movimento
                cursor.execute("""
                    INSERT INTO medicoespassagens (SalaOrigem, SalaDestino, Estado, Hora, IDJogo)
                    VALUES (%s, %s, %s, %s, %s)
                """, (
                    sala_origem,
                    sala_destino,
                    status,
                    hora,
                    id_jogo_ativo
                ))
                conn.commit()
                print("✅ Inserido em medicoespassagens")

                # Atualizar último movimento do marsami
                cursor.callproc("AtualizarMarsami", [marsami_nome, hora, id_jogo_ativo])
                print(f"🤖 Marsami atualizado")

                # Atualizar ocupação do labirinto
                cursor.callproc("RemoverMarsamiSalaOrigem", [id_jogo_ativo, sala_origem, marsami_nome])
                cursor.callproc("AdicionarMarsamiSalaDestino", [id_jogo_ativo, sala_destino, marsami_nome])
                conn.commit()
                print("🏁 Ocupação atualizada")

        except Exception as e:
            print("❌ Erro ao tratar movimento:", e)

    elif "alertas" in msg.topic:
        tipo_alerta = payload.get("type")

        if tipo_alerta == "NOVO_MARSAMI":
            print("🚨 Novo Marsami detectado! - ignorado")
            return
        else:
            id_grupo = payload.get("Player")
            cursor.execute("""
                SELECT IDUtilizador FROM utilizador
                WHERE IDGrupo = %s
            """, (id_grupo,))
            res_utilizador = cursor.fetchone()

            if not res_utilizador:
                print(f"⚠️ Ignorado: Grupo {id_grupo} não registado.")
                return

            id_utilizador = res_utilizador[0]

            cursor.execute("""
                SELECT IDJogo FROM jogo
                WHERE IDUtilizador = %s AND Estado = 'Ativo'
                ORDER BY DataHoraInicio DESC LIMIT 1
            """, (id_utilizador,))
            jogo_ativo = cursor.fetchone()

            if not jogo_ativo:
                print(f"⚠️ Nenhum jogo ativo para o grupo {id_grupo}")
                return

            id_jogo_ativo = jogo_ativo[0]

            cursor.execute("""
                INSERT INTO mensagens (Hora, TipoAlerta, Msg, HoraEscrita, IDJogo)
                VALUES (%s, %s, %s, %s, %s)
            """, (
                payload.get("hora", datetime.now().isoformat()),
                payload.get("type", "ALERTA_DESCONHECIDO"),
                payload.get("msg", "Mensagem automática"),
                datetime.now(),
                id_jogo_ativo
            ))
            conn.commit()
            print("🚨 Alerta inserido com sucesso!")

# 🚀 Início da escuta MQTT
def start_mqtt():
    client = mqtt.Client()
    client.on_message = on_message
    client.connect("broker.emqx.io", 1883)
    client.subscribe("pisid/filtered/+/som", qos=2)
    client.subscribe("pisid/filtered/+/movimento", qos=2)
    client.subscribe("pisid/alertas/+", qos=2)
    print("🔄 A escutar MQTT e enviar para MySQL com QoS 2...")
    client.loop_forever()

if __name__ == "__main__":
    start_mqtt()
