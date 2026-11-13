from typing import List, Optional
from pydantic import BaseModel, Field


class MatchingRequest(BaseModel):
    cv_text: str = Field(..., min_length=1, description="Confirmed CV text (PII minimized)")
    jd_text: str = Field(..., min_length=1, description="Job description text")
    cv_confirmed_skill_ids: List[int] = Field(default_factory=list)
    jd_required_skill_ids: List[int] = Field(default_factory=list)
    jd_preferred_skill_ids: List[int] = Field(default_factory=list)
    algorithm_version: Optional[str] = Field(default=None)


class MatchingData(BaseModel):
    baseline_score: float = Field(..., ge=0.0, le=100.0)
    semantic_similarity: float = Field(..., ge=0.0, le=1.0)
    skill_coverage: Optional[float] = Field(default=None, ge=0.0, le=1.0)
    match_score: float = Field(..., ge=0.0, le=100.0)
    matched_skills: List[int] = Field(default_factory=list)
    missing_skills: List[int] = Field(default_factory=list)
    matched_preferred_skills: List[int] = Field(default_factory=list)
    model_name: str
    algorithm_version: str
    warnings: List[str] = Field(default_factory=list)


class MatchingResponse(BaseModel):
    data: MatchingData
