from unittest.mock import MagicMock, patch
from fastapi.testclient import TestClient
from app.core.config import get_settings
from app.main import create_app
from tests.test_matching import FixedCosineEncoder

app = create_app()
client = TestClient(app)


def _headers() -> dict[str, str]:
    token = getattr(get_settings(), "INTERNAL_SERVICE_TOKEN", get_settings().INTERNAL_TOKEN)
    return {"X-Internal-Token": token}


def test_contract_success():
    """Kiểm tra schema JSON đầu ra của cả /matching/score và /cv/extract."""
    # 1. Contract Matching (API-AIX-02)
    payload = {
        "cv_text": "PHP Laravel developer",
        "jd_text": "PHP Laravel backend",
        "cv_confirmed_skill_ids": [1],
        "jd_required_skill_ids": [1, 2],
        "jd_preferred_skill_ids": [],
    }
    with patch("app.services.embedding_service.get_encoder", return_value=FixedCosineEncoder(0.8)):
        res_match = client.post("/internal/v1/matching/score", json=payload, headers=_headers())

    assert res_match.status_code == 200
    data = res_match.json()["data"]
    required_keys = {
        "baseline_score",
        "semantic_similarity",
        "skill_coverage",
        "match_score",
        "matched_skills",
        "missing_skills",
        "matched_preferred_skills",
        "model_name",
        "algorithm_version",
        "warnings",
    }
    assert required_keys.issubset(data.keys())

    # 2. Contract Extraction (API-AIX-01)
    mock_page = MagicMock()
    mock_page.extract_text.return_value = "PHP developer"
    mock_reader = MagicMock()
    mock_reader.is_encrypted = False
    mock_reader.pages = [mock_page]

    with patch("app.services.pdf_parser.PdfReader", return_value=mock_reader):
        res_ext = client.post(
            "/internal/v1/cv/extract",
            headers=_headers(),
            files={"file": ("cv.pdf", b"%PDF-1.4 valid", "application/pdf")},
            data={"skill_catalog": '[{"skill_id": 1, "canonical_name": "PHP", "aliases": []}]'},
        )

    assert res_ext.status_code == 200
    ext_body = res_ext.json()
    for key in ["parser_status", "extracted_text", "normalized_text", "detected_skills", "warnings"]:
        assert key in ext_body


def test_contract_422():
    """Payload sai định dạng hoặc thiếu trường bắt buộc phải trả 422."""
    res_match = client.post(
        "/internal/v1/matching/score",
        json={"cv_text": "", "jd_text": "Valid JD"},
        headers=_headers(),
    )
    assert res_match.status_code == 422

    res_ext = client.post(
        "/internal/v1/cv/extract",
        headers=_headers(),
        files={"file": ("cv.pdf", b"%PDF-1.4 valid", "application/pdf")},
        data={"skill_catalog": "not-valid-json"},
    )
    assert res_ext.status_code == 422
