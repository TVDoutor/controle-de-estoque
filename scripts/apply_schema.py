import mysql.connector
from pathlib import Path

HOST = "108.167.168.27"
USER = "tvdout68_controle_estoque"
PASSWORD = "controle_estoque"
DATABASE = "tvdout68_controle_estoque"

schema_path = Path(__file__).resolve().parent.parent / "schema.sql"
if not schema_path.exists():
    raise SystemExit(f"Arquivo de schema não encontrado: {schema_path}")

sql_content = schema_path.read_text(encoding="utf-8")

try:
    connection = mysql.connector.connect(
        host=HOST,
        user=USER,
        password=PASSWORD,
        database=DATABASE,
        autocommit=True,
    )
except mysql.connector.Error as err:
    raise SystemExit(f"Erro de conexão com o MySQL: {err}")

cursor = connection.cursor()

def iter_statements(sql: str):
    statement_parts = []
    for raw_line in sql.splitlines():
        line = raw_line.strip()
        if not line or line.startswith('--'):
            continue
        statement_parts.append(raw_line.strip())
        if line.endswith(';'):
            yield ' '.join(statement_parts)
            statement_parts = []
    if statement_parts:
        yield ' '.join(statement_parts)

try:
    for stmt in iter_statements(sql_content):
        clean_stmt = stmt.strip()
        if clean_stmt.lower().startswith(('create database', 'use ')):
            continue
        prepared = clean_stmt.replace('controle_estoque', DATABASE).rstrip(';')
        cursor.execute(prepared)
finally:
    cursor.close()
    connection.close()

print("Schema aplicado com sucesso.")
