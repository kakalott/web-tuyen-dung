from typing import List, Tuple
import numpy as np
from sklearn.feature_extraction.text import TfidfVectorizer
from sklearn.metrics.pairwise import cosine_similarity
from app.services.text_normalizer import normalize


def calculate_baseline(cv_text: str, jd_text: str) -> Tuple[float, List[str]]:
    warnings: List[str] = []
    norm_cv = normalize(cv_text)
    norm_jd = normalize(jd_text)

    if not norm_cv or not norm_jd:
        warnings.append("TFIDF_ZERO_VECTOR")
        return 0.0, warnings

    try:
        vectorizer = TfidfVectorizer(ngram_range=(1, 2), min_df=1)
        tfidf_matrix = vectorizer.fit_transform([norm_cv, norm_jd])

        # Kiểm tra norm của từng vector để tránh chia cho 0
        row_norms = np.asarray(tfidf_matrix.power(2).sum(axis=1)).ravel()
        if np.any(row_norms == 0):
            warnings.append("TFIDF_ZERO_VECTOR")
            return 0.0, warnings

        cos_sim = float(cosine_similarity(tfidf_matrix[0:1], tfidf_matrix[1:2])[0][0])
        cos_sim = max(0.0, min(1.0, cos_sim))
        return round(cos_sim * 100.0, 2), warnings

    except ValueError:
        # Xảy ra khi TfidfVectorizer gặp empty vocabulary
        warnings.append("TFIDF_ZERO_VECTOR")
        return 0.0, warnings
