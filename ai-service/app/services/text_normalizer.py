import unicodedata
import re

def normalize(text: str) -> str:
    if not text:
        return ""
    # Chuẩn hóa Unicode NFC để gộp dấu tiếng Việt
    text = unicodedata.normalize("NFC", text)
    # Chuyển chữ thường
    text = text.lower()
    # Gộp khoảng trắng, newline thừa
    text = re.sub(r'\s+', ' ', text)
    return text.strip()
