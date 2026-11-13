import pytest
import json
from fastapi.testclient import TestClient
from unittest.mock import patch
from app.main import create_app
from app.core.config import get_settings

app = create_app()
client = TestClient(app)
settings = get_settings()

# Dữ liệu danh mục kỹ năng giả lập cho test case
MOCK_SKILL_CATALOG = [
    {"skill_id": 1, "canonical_name": "PHP", "aliases": []},
    {"skill_id": 2, "canonical_name": "Laravel", "aliases": []},
    {"skill_id": 3, "canonical_name": "JavaScript", "aliases": ["JS", "ECMAScript"]}
]

HEADERS = {
    "X-Internal-Token": settings.INTERNAL_TOKEN
}

@patch('app.services.pdf_parser.extract_text')
def test_extract_text_and_alias(mock_extract):
    """
    TC-CV-01: Upload PDF hợp lệ có text -> nhận diện đúng skill_id chuẩn qua alias.
    """
    # Giả lập pypdf đọc thành công và trả về chuỗi văn bản
    mock_extract.return_value = ("Kinh nghiệm lập trình PHP, Laravel và JS.", [])
    
    response = client.post(
        "/internal/v1/cv/extract",
        headers=HEADERS,
        data={"skill_catalog": json.dumps(MOCK_SKILL_CATALOG)},
        files={"file": ("cv.pdf", b"%PDF-1.4 mock content", "application/pdf")}
    )
    
    assert response.status_code == 200
    data = response.json()
    assert data["parser_status"] == "success"
    assert "extracted_text" in data
    
    # Kiểm tra nhận diện chính xác 3 kỹ năng (JS phải được map về skill_id 3 của JavaScript)
    detected_ids = [s["skill_id"] for s in data["detected_skills"]]
    assert 1 in detected_ids # PHP
    assert 2 in detected_ids # Laravel
    assert 3 in detected_ids # JavaScript (qua alias JS)

@patch('app.services.pdf_parser.extract_text')
def test_no_text_pdf(mock_extract):
    """
    TC-CV-03: Upload PDF chỉ chứa ảnh/không có text layer -> parser_status='failed', warning PDF_NO_TEXT.
    """
    # Giả lập file PDF trắng hoặc scan
    mock_extract.return_value = ("", ["PDF_NO_TEXT"])
    
    response = client.post(
        "/internal/v1/cv/extract",
        headers=HEADERS,
        data={"skill_catalog": json.dumps(MOCK_SKILL_CATALOG)},
        files={"file": ("scan.pdf", b"%PDF-1.4 image only", "application/pdf")}
    )
    
    assert response.status_code == 200
    data = response.json()
    assert data["parser_status"] == "failed"
    assert "PDF_NO_TEXT" in data["warnings"]
    assert len(data["detected_skills"]) == 0

def test_invalid_pdf_magic_bytes():
    """
    Kiểm tra file giả mạo (không bắt đầu bằng %PDF-) -> bắt lỗi ngay tại pdf_parser.
    """
    response = client.post(
        "/internal/v1/cv/extract",
        headers=HEADERS,
        data={"skill_catalog": json.dumps(MOCK_SKILL_CATALOG)},
        files={"file": ("fake.pdf", b"MZ executable fake pdf", "application/pdf")}
    )
    
    assert response.status_code == 200
    data = response.json()
    assert data["parser_status"] == "failed"
    assert "PDF_INVALID" in data["warnings"]

def test_missing_internal_token():
    """
    Kiểm tra bảo mật: Không gửi X-Internal-Token -> bị chặn 401.
    """
    response = client.post(
        "/internal/v1/cv/extract",
        data={"skill_catalog": json.dumps(MOCK_SKILL_CATALOG)},
        files={"file": ("cv.pdf", b"%PDF-1.4 mock content", "application/pdf")}
    )
    
    assert response.status_code == 401
    assert response.json()["detail"] == "SERVICE_UNAUTHORIZED"
