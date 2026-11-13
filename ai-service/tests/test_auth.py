from fastapi.testclient import TestClient
from app.core.config import get_settings
from app.main import create_app

app = create_app()
client = TestClient(app)


def test_no_token():
    """Thiếu X-Internal-Token phải trả 401 SERVICE_UNAUTHORIZED trên mọi endpoint nội bộ."""
    res_health = client.get("/internal/v1/health")
    assert res_health.status_code == 401
    assert res_health.json()["error"]["code"] == "SERVICE_UNAUTHORIZED"

    res_match = client.post("/internal/v1/matching/score", json={})
    assert res_match.status_code == 401


def test_wrong_token():
    """Sai X-Internal-Token phải trả 401 SERVICE_UNAUTHORIZED."""
    res = client.get(
        "/internal/v1/health",
        headers={"X-Internal-Token": "invalid-secret-token"},
    )
    assert res.status_code == 401
    assert res.json()["error"]["code"] == "SERVICE_UNAUTHORIZED"


def test_valid_token():
    """Đúng X-Internal-Token phải cho phép truy cập health check."""
    token = get_settings().INTERNAL_SERVICE_TOKEN
    res = client.get(
        "/internal/v1/health",
        headers={"X-Internal-Token": token},
    )
    assert res.status_code == 200
    body = res.json()
    assert body["data"]["status"] == "ok"
