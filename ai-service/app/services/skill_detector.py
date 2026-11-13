import re
from typing import List
from app.schemas.extraction import SkillCatalogItem, DetectedSkill

def detect_skills(normalized_text: str, catalog: List[SkillCatalogItem]) -> List[DetectedSkill]:
    detected = []
    seen_skill_ids = set()
    
    # Tạo danh sách các từ khóa cần tìm (bao gồm canonical_name và aliases)
    search_terms = []
    for item in catalog:
        terms = [item.canonical_name] + item.aliases
        for term in terms:
            # Chuẩn hóa term giống như văn bản
            norm_term = term.lower().strip()
            if norm_term:
                search_terms.append((norm_term, item.skill_id, term))
                
    # Sắp xếp độ dài giảm dần để ưu tiên match cụm dài (e.g. "Vue.js" trước "Vue")
    search_terms.sort(key=lambda x: len(x[0]), reverse=True)
    
    for norm_term, skill_id, original_alias in search_terms:
        if skill_id in seen_skill_ids:
            continue
            
        # Regex boundary: sử dụng \b nhưng xử lý an toàn với ký tự đặc biệt (C++, C#)
        escaped_term = re.escape(norm_term)
        pattern = r'(?<!\w)' + escaped_term + r'(?!\w)'
        
        if re.search(pattern, normalized_text):
            detected.append(DetectedSkill(skill_id=skill_id, matched_alias=original_alias))
            seen_skill_ids.add(skill_id)
            
    return detected
