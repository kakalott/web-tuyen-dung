import numpy as np
import pytest
from app.schemas.matching import MatchingRequest
from app.services import matching_pipeline, tfidf_baseline


class FixedCosineEncoder:
    """Giả lập SentenceTransformer trả về cặp vector có cosine similarity đúng bằng target_cos."""

    def __init__(self, target_cos: float):
        self.target_cos = target_cos

    def encode(self, texts: list[str]) -> np.ndarray:
        # Vector 1: [1.0, 0.0]
        # Vector 2: [cos(theta), sin(theta)] -> dot product = cos(theta), norm = 1.0
        c = self.target_cos
        s = float(np.sqrt(max(0.0, 1.0 - c * c)))
        return np.array([[1.0, 0.0], [c, s]], dtype=np.float64)


def test_with_required():
    """TC-AI-01: Kiểm tra công thức Hybrid 60% Semantic + 40% SkillCoverage."""
    req = MatchingRequest(
        cv_text="Backend developer skilled in PHP Laravel and MySQL",
        jd_text="Looking for PHP Laravel MySQL Git engineer",
        cv_confirmed_skill_ids=[3, 4, 5],
        jd_required_skill_ids=[3, 4, 5, 6],
        jd_preferred_skill_ids=[10],
        algorithm_version="hybrid-v1",
    )

    # semantic = 0.78, skill_coverage = 3/4 = 0.75
    # match_score = round(100 * (0.60 * 0.78 + 0.40 * 0.75), 2) = round(76.8, 2) = 76.8
    encoder = FixedCosineEncoder(target_cos=0.78)
    resp = matching_pipeline.score(req, custom_encoder=encoder)
    data = resp.data

    assert 0.0 <= data.baseline_score <= 100.0
    assert data.semantic_similarity == 0.78
    assert data.skill_coverage == 0.75
    assert data.match_score == 76.8
    assert data.matched_skills == [3, 4, 5]
    assert data.missing_skills == [6]
    assert data.matched_preferred_skills == []
    assert data.algorithm_version == "hybrid-v1"
    assert "NO_REQUIRED_SKILLS" not in data.warnings


def test_without_required():
    """TC-AI-02: Khi jd_required_skill_ids rỗng -> skill_coverage phải là None và 100% semantic."""
    req = MatchingRequest(
        cv_text="Frontend engineer with Vue and TypeScript experience",
        jd_text="Frontend developer building SPA applications",
        cv_confirmed_skill_ids=[1, 2, 8],
        jd_required_skill_ids=[],
        jd_preferred_skill_ids=[8],
    )

    encoder = FixedCosineEncoder(target_cos=0.78)
    resp = matching_pipeline.score(req, custom_encoder=encoder)
    data = resp.data

    assert data.skill_coverage is None
    assert data.match_score == 78.0
    assert data.matched_skills == []
    assert data.missing_skills == []
    assert data.matched_preferred_skills == [8]
    assert "NO_REQUIRED_SKILLS" in data.warnings


def test_preferred_skills_do_not_inflate_score():
    """TC-AI-03: Thay đổi preferred_skill_ids không làm thay đổi match_score hoặc skill_coverage."""
    encoder = FixedCosineEncoder(target_cos=0.80)

    req_no_pref = MatchingRequest(
        cv_text="Python FastAPI developer",
        jd_text="Python backend engineer",
        cv_confirmed_skill_ids=[1, 2, 9],
        jd_required_skill_ids=[1, 3],
        jd_preferred_skill_ids=[],
    )
    req_with_pref = MatchingRequest(
        cv_text="Python FastAPI developer",
        jd_text="Python backend engineer",
        cv_confirmed_skill_ids=[1, 2, 9],
        jd_required_skill_ids=[1, 3],
        jd_preferred_skill_ids=[2, 9],
    )

    res1 = matching_pipeline.score(req_no_pref, custom_encoder=encoder).data
    res2 = matching_pipeline.score(req_with_pref, custom_encoder=encoder).data

    assert res1.skill_coverage == res2.skill_coverage == 0.5
    assert res1.match_score == res2.match_score == 68.0
    assert res2.matched_preferred_skills == [2, 9]


def test_semantic_similarity_clamping():
    """Đảm bảo cosine âm được clamp về 0.0."""
    encoder = FixedCosineEncoder(target_cos=-0.45)
    req = MatchingRequest(
        cv_text="Chef cooking Italian food",
        jd_text="Kernel systems programmer in Rust",
        cv_confirmed_skill_ids=[],
        jd_required_skill_ids=[],
    )
    data = matching_pipeline.score(req, custom_encoder=encoder).data
    assert data.semantic_similarity == 0.0
    assert data.match_score == 0.0


def test_tfidf_zero_vector_warning():
    """Văn bản chỉ có ký tự đặc biệt/rỗng sau normalize -> trả 0.0 kèm cảnh báo TFIDF_ZERO_VECTOR."""
    score, warnings = tfidf_baseline.calculate_baseline("   ---   ", "   ***   ")
    assert score == 0.0
    assert "TFIDF_ZERO_VECTOR" in warnings
