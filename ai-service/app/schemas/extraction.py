from pydantic import BaseModel, Field
from typing import List, Optional

class SkillCatalogItem(BaseModel):
    skill_id: int
    canonical_name: str
    aliases: List[str] = []

class DetectedSkill(BaseModel):
    skill_id: int
    matched_alias: str

class ExtractionResponse(BaseModel):
    parser_status: str = Field(..., description="'success' or 'failed'")
    extracted_text: Optional[str] = None
    normalized_text: Optional[str] = None
    detected_skills: List[DetectedSkill] = []
    warnings: List[str] = []
    parser_version: Optional[str] = None
