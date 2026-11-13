from typing import Dict, List, Optional


def calculate_coverage(
    cv_confirmed_skill_ids: List[int],
    jd_required_skill_ids: List[int],
    jd_preferred_skill_ids: List[int],
) -> Dict[str, Optional[float] | List[int]]:
    c_set = set(cv_confirmed_skill_ids)
    r_set = set(jd_required_skill_ids)
    p_set = set(jd_preferred_skill_ids)

    matched_skills = sorted(c_set & r_set)
    missing_skills = sorted(r_set - c_set)
    matched_preferred_skills = sorted(c_set & p_set)

    if len(r_set) > 0:
        skill_coverage: Optional[float] = round(len(matched_skills) / len(r_set), 6)
    else:
        skill_coverage = None

    return {
        "skill_coverage": skill_coverage,
        "matched_skills": matched_skills,
        "missing_skills": missing_skills,
        "matched_preferred_skills": matched_preferred_skills,
    }
