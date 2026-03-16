"""
config.py — centralised settings for LANACC Python ETL
"""
import os
from dotenv import load_dotenv

BASE_DIR = os.path.abspath(os.path.join(os.path.dirname(__file__), '..'))
load_dotenv(os.path.join(BASE_DIR, '.env'))

# ── Database connection ──────────────────────────────────────────
DB = {
    "host":     os.getenv("DB_HOST",     "localhost"),
    "port":     int(os.getenv("DB_PORT", "3306")),
    "database": os.getenv("DB_NAME",     "lanacc"),
    "user":     os.getenv("DB_USER",     "lanacc_user"),
    "password": os.getenv("DB_PASSWORD", "lanacc_pass_2026"),
}

# ── Data root folder (update in .env) ────────────────────────────
# Windows example: C:/Users/YourName/Documents/landco2026
DATA_ROOT = os.getenv("DATA_ROOT", "./data")

FOLDERS = {
    "bdo":          os.path.join(DATA_ROOT, "BDO Accounts 2026"),
    "bim_salaries": os.path.join(DATA_ROOT, "BIM Salaries 2026"),
    "accounts":     os.path.join(DATA_ROOT, "Landco Accounts 2026"),
    "salaries":     os.path.join(DATA_ROOT, "Salaries Landco 2026"),
    "shareholders": os.path.join(DATA_ROOT, "Shareholders 2026"),
}

# ── Month name map (Portuguese → int) ────────────────────────────
MONTH_MAP = {
    "janeiro": 1, "janeirio": 1, "jan": 1,
    "fevereiro": 2, "feb": 2, "fev": 2,
    "marco": 3, "março": 3, "march": 3, "mar": 3,
    "abril": 4, "april": 4, "apr": 4,
    "maio": 5, "may": 5,
    "junho": 6, "june": 6, "jun": 6,
    "julho": 7, "july": 7, "jul": 7,
    "agosto": 8, "august": 8, "aug": 8,
    "setembro": 9, "september": 9, "sep": 9,
    "outubro": 10, "october": 10, "oct": 10,
    "novembro": 11, "november": 11, "nov": 11,
    "dezembro": 12, "december": 12, "dec": 12,
}

def month_from_str(s: str) -> int:
    """Convert Portuguese/English month name to int."""
    if not s:
        return 0
    return MONTH_MAP.get(str(s).strip().lower(), 0)

