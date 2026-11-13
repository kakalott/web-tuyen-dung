import math
import numpy as np
from app.schemas.matching import MatchingRequest
from app.services import matching_pipeline, tfidf_baseline


class FixedCosineEncoder:
    """Encoder giả lập trả về 2 vector có cosine chính xác bằng target_cos."""
    def __init__(self, target_cos: float):
        self.target_cos = target_cos

    def encode(self, texts: list[str]) -> np.ndarray:
        c = max(-1.0, min(1.0, self.target_cos))
        s = math.sqrt(max(0.0, 1.0 - c * c))
        return np.array([[1.0, 0.0], [c, s]], dtype=np.float64)


def test_with_required():
    """Kiểm tra công thức 60/40 và kỹ năng ưu tiên không cộng vào SkillCoverage."""
    req = MatchingRequest(
        cv_text="Senior PHP Laravel developer",
        jd_text="Backend PHP Laravel MySQL developer",
        cv_confirmed_skill_ids=[1, 2, 10],
        jd_required_skill_ids=[1, 2, 3, 4],  # Khớp 2/4 = 0.5
        jd_preferred_skill_ids=[10],         # Khớp 1 preferred (không cộng điểm)
    )
    res = matching_pipeline.score(req, custom_encoder=FixedCosineEncoder(0.80))
    data = res.data

    assert data.semantic_similarity == 0.8
    assert data.skill_coverage == 0.5
    # 100 * (0.60 * 0.80 + 0.40 * 0.50) = 68.0
    assert data.match_score == 68.0
    assert data.matched_skills == [1, 2]
    assert data.missing_skills == [3, 4]
    assert data.matched_preferred_skills == [10]


def test_without_required():
    """Khi JD không có required_skill_ids: skill_coverage = None và MatchScore = 100 * Semantic."""
    req = MatchingRequest(
        cv_text="Software engineer",
        jd_text="Intern developer",
        cv_confirmed_skill_ids=[1, 2],
        jd_required_skill_ids=[],
        jd_preferred_skill_ids=[1],
    )
    res = matching_pipeline.score(req, custom_encoder=FixedCosineEncoder(0.75))
    data = res.data

    assert data.skill_coverage is None
    assert data.match_score == 75.0
    assert "NO_REQUIRED_SKILLS" in data.warnings


def test_negative_cosine_clamped_to_zero():
    """Cosine âm từ embedding phải được clamp chặt về 0.0."""
    req = MatchingRequest(
        cv_text="Chef cooking food",
        jd_text="Kernel C++ driver developer",
        cv_confirmed_skill_ids=[],
        jd_required_skill_ids=[1],
    )
    res = matching_pipeline.score(req, custom_encoder=FixedCosineEncoder(-0.45))
    assert res.data.semantic_similarity == 0.0
    assert res.data.match_score == 0.0


def test_tfidf_zero_vector():
    """Văn bản chỉ chứa ký tự không tạo token từ vựng phải trả 0.0 + TFIDF_ZERO_VECTOR."""
    score, warnings = tfidf_baseline.calculate_baseline("   ", "   ")
    assert score == 0.0
    assert "TFIDF_ZERO_VECTOR" in warnings
