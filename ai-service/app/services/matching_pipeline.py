from typing import List, Optional
from app.core.config import get_settings
from app.schemas.matching import MatchingData, MatchingRequest, MatchingResponse
from app.services import embedding_service, skill_coverage, tfidf_baseline


def score(
    request: MatchingRequest,
    custom_encoder: Optional[embedding_service.TextEncoder] = None,
) -> MatchingResponse:
    settings = get_settings()
    warnings: List[str] = []

    # 1. TF-IDF Baseline
    baseline_score, tfidf_warnings = tfidf_baseline.calculate_baseline(
        request.cv_text, request.jd_text
    )
    warnings.extend(tfidf_warnings)

    # 2. Semantic Similarity (Sentence-Transformers)
    sem_sim = embedding_service.similarity(
        request.cv_text, request.jd_text, custom_encoder=custom_encoder
    )

    # 3. Skill Coverage & Explanation Lists
    cov_result = skill_coverage.calculate_coverage(
        cv_confirmed_skill_ids=request.cv_confirmed_skill_ids,
        jd_required_skill_ids=request.jd_required_skill_ids,
        jd_preferred_skill_ids=request.jd_preferred_skill_ids,
    )
    coverage_val: Optional[float] = cov_result["skill_coverage"]  # type: ignore

    # 4. Hybrid MatchScore Composition
    if coverage_val is not None:
        raw_match = 100.0 * (0.60 * sem_sim + 0.40 * coverage_val)
    else:
        raw_match = 100.0 * sem_sim
        warnings.append("NO_REQUIRED_SKILLS")

    match_score = round(max(0.0, min(100.0, raw_match)), 2)
    algo_version = request.algorithm_version or settings.ALGORITHM_VERSION

    data = MatchingData(
        baseline_score=baseline_score,
        semantic_similarity=sem_sim,
        skill_coverage=coverage_val,
        match_score=match_score,
        matched_skills=cov_result["matched_skills"],  # type: ignore
        missing_skills=cov_result["missing_skills"],  # type: ignore
        matched_preferred_skills=cov_result["matched_preferred_skills"],  # type: ignore
        model_name=settings.EMBEDDING_MODEL_NAME,
        algorithm_version=algo_version,
        warnings=warnings,
    )
    return MatchingResponse(data=data)
