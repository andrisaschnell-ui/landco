"""
db.py — MySQL connection helpers for LANACC ETL
"""
import mysql.connector
from mysql.connector import Error
from config import DB
from rich.console import Console

console = Console()

def get_connection():
    """Return a live MySQL connection."""
    return mysql.connector.connect(**DB)


def execute_many(sql: str, rows: list, importer: str = "python",
                 filename: str = "") -> dict:
    """
    Execute an INSERT/REPLACE statement for a batch of rows.
    Logs result to import_log table.
    Returns dict with loaded / skipped counts.
    """
    loaded = skipped = 0
    error_msg = None
    status = "success"

    conn = None
    try:
        conn = get_connection()
        cur = conn.cursor()
        for row in rows:
            try:
                cur.execute(sql, row)
                loaded += 1
            except Error as e:
                skipped += 1
                console.print(f"[yellow]  SKIP row: {e}[/yellow]")

        conn.commit()
        console.print(f"[green]  ✓ {loaded} rows loaded, {skipped} skipped[/green]")

    except Error as e:
        status = "failed"
        error_msg = str(e)
        console.print(f"[red]  ✗ DB error: {e}[/red]")
        if conn:
            conn.rollback()
    finally:
        if conn and conn.is_connected():
            conn.close()

    _log(importer, filename, loaded, skipped, status, error_msg)
    return {"loaded": loaded, "skipped": skipped}


def execute_one(sql: str, params: tuple = ()):
    """Execute a single INSERT/UPDATE and return last insert id."""
    conn = get_connection()
    try:
        cur = conn.cursor()
        cur.execute(sql, params)
        conn.commit()
        return cur.lastrowid
    finally:
        conn.close()


def fetch_all(sql: str, params: tuple = ()) -> list:
    """Run a SELECT and return all rows as dicts."""
    conn = get_connection()
    try:
        cur = conn.cursor(dictionary=True)
        cur.execute(sql, params)
        return cur.fetchall()
    finally:
        conn.close()


def _log(importer, filename, loaded, skipped, status, error_msg):
    """Insert a record into import_log."""
    sql = """
        INSERT INTO import_log
          (filename, importer, records_loaded, records_skipped, status, error_message, imported_by)
        VALUES (%s, %s, %s, %s, %s, %s, 'python')
    """
    try:
        conn = get_connection()
        cur = conn.cursor()
        cur.execute(sql, (filename, importer, loaded, skipped, status, error_msg))
        conn.commit()
    except Exception:
        pass
    finally:
        if conn and conn.is_connected():
            conn.close()
