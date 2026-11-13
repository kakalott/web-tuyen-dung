from typing import List
import pypdf
from app.schemas.extraction import SkillCatalogItem, ExtractionResponse
from app.services import pdf_parser, text_normalizer, skill_detector

def extract(pdf_bytes: bytes, catalog: List[SkillCatalogItem]) -> ExtractionResponse:
    # 1. Trích xuất text từ PDF
    extracted_text, warnings = pdf_parser.extract_text(pdf_bytes)
    
    if "PDF_INVALID" in warnings or "PDF_ENCRYPTED" in warnings or "PDF_NO_TEXT" in warnings:
        return ExtractionResponse(
            parser_status="failed",
            warnings=warnings,
            parser_version=pypdf.__version__
        )
        
    # 2. Chuẩn hóa văn bản
    normalized_text = text_normalizer.normalize(extracted_text)
    
    # 3. Nhận diện kỹ năng
    detected_skills = skill_detector.detect_skills(normalized_text, catalog)
    
    # 4. Trả về kết quả
    return ExtractionResponse(
        parser_status="success",
        extracted_text=extracted_text,
        normalized_text=normalized_text,
        detected_skills=detected_skills,
        warnings=warnings,
        parser_version=pypdf.__version__
    )
