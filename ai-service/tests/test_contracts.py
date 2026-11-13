from unittest.mock import patch
from fastapi.testclient import TestClient
from app.core.config import get_settings
from app.main import create_app
from tests.test_matching import FixedCosineEncoder

app = create_app()
client = TestClient(app)


def _auth_headers() -> dict[str, str]:
    settings = get_settings()
    return {"X-Internal-Token": settings.INTERNAL_TOKEN}


def test_matching_endpoint_unauthorized():
    """Thiếu hoặc sai X-Internal-Token phải trả 401 SERVICE_UNAUTHORIZED."""
    payload = {
        "cv_text": "Laravel developer",
        "jd_text": "PHP developer",
    }
    res_no_token = client.post("/internal/v1/matching/score", json=payload)
    assert res_no_token.status_code == 401
    assert res_no_token.json()["detail"] == "SERVICE_UNAUTHORIZED"

    res_wrong_token = client.post(
        "/internal/v1/matching/score",
        json=payload,
        headers={"X-Internal-Token": "wrong-secret-token"},
    )
    assert res_wrong_token.status_code == 401
    assert res_wrong_token.json()["detail"] == "SERVICE_UNAUTHORIZED"


def test_contract_success():
    """Kiểm tra JSON contract chuẩn khi gọi API-AIX-02 thành công."""
    payload = {
        "cv_text": "PHP Laravel MySQL developer",
        "jd_text": "Backend PHP Laravel engineer",
        "cv_confirmed_skill_ids": [1, 2],
        "jd_required_skill_ids": [1, 2, 3],
        "jd_preferred_skill_ids": [4],
        "algorithm_version": "hybrid-v1",
    }

    fake_encoder = FixedCosineEncoder(target_cos=0.75)
    with patch("app.services.embedding_service.get_encoder", return_value=fake_encoder):
        res = client.post(
            "/internal/v1/matching/score",
            json=payload,
            headers=_auth_headers(),
        )

    assert res.status_code == 200
    body = res.json()
    assert "data" in body
    data = body["data"]

    expected_keys = {
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
    assert expected_keys.issubset(set(data.keys()))
    assert isinstance(data["baseline_score"], float)
    assert data["semantic_similarity"] == 0.75
    assert data["skill_coverage"] == 0.666667
    # round(100 * (0.60 * 0.75 + 0.40 * 0.666667), 2) = 71.67
    assert data["match_score"] == 71.67
    assert data["matched_skills"] == [1, 2]
    assert data["missing_skills"] == [3]


def test_contract_null_coverage_json():
    """Xác nhận JSON trả về skill_coverage: null đúng chuẩn khi không có required skills."""
    payload = {
        "cv_text": "General software engineer",
        "jd_text": "Junior developer intern",
        "cv_confirmed_skill_ids": [1],
        "jd_required_skill_ids": [],
        "jd_preferred_skill_ids": [1],
    }

    fake_encoder = FixedCosineEncoder(target_cos=0.82)
    with patch("app.services.embedding_service.get_encoder", return_value=fake_encoder):
        res = client.post(
            "/internal/v1/matching/score",
            json=payload,
            headers=_auth_headers(),
        )

    assert res.status_code == 200
    data = res.json()["data"]
    assert data["skill_coverage"] is None
    assert data["match_score"] == 82.0
    assert "NO_REQUIRED_SKILLS" in data["warnings"]


def test_contract_422():
    """Thiếu trường bắt buộc cv_text/jd_text hoặc chuỗi rỗng phải trả 422."""
    res = client.post(
        "/internal/v1/matching/score",
        json={"cv_text": "", "jd_text": "Valid JD"},
        headers=_auth_headers(),
    )
    assert res.status_code == 422
