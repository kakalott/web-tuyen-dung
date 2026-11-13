from fastapi import APIRouter, UploadFile, File, Form, Depends, HTTPException
import json
from pydantic import ValidationError
from typing import List
from app.schemas.extraction import ExtractionResponse, SkillCatalogItem
from app.services.extraction_pipeline import extract
from app.api.auth import verify_internal_token

router = APIRouter()

@router.post("/extract", response_model=ExtractionResponse, dependencies=[Depends(verify_internal_token)])
async def extract_cv(
    file: UploadFile = File(...),
    skill_catalog: str = Form(...)
):
    # 1. Đọc nội dung PDF
    pdf_bytes = await file.read()
    
    # 2. Parse danh mục kỹ năng từ JSON string
    try:
        catalog_data = json.loads(skill_catalog)
        catalog = [SkillCatalogItem(**item) for item in catalog_data]
    except (json.JSONDecodeError, ValidationError) as e:
        raise HTTPException(status_code=422, detail="INVALID_REQUEST: skill_catalog format is invalid")
        
    # 3. Chạy Pipeline trích xuất
    response = extract(pdf_bytes, catalog)
    return response
