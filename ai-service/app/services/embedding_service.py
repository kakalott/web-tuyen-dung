from typing import Callable, List, Optional, Protocol
import numpy as np
from fastapi import HTTPException
from app.core.config import get_settings
from app.services.text_normalizer import normalize


class TextEncoder(Protocol):
    def encode(self, texts: List[str]) -> np.ndarray:
        ...


_model_instance: Optional[TextEncoder] = None


def get_encoder(custom_encoder: Optional[TextEncoder] = None) -> TextEncoder:
    if custom_encoder is not None:
        return custom_encoder

    global _model_instance
    if _model_instance is None:
        settings = get_settings()
        try:
            from sentence_transformers import SentenceTransformer
            _model_instance = SentenceTransformer(settings.EMBEDDING_MODEL_NAME)
        except Exception as exc:
            raise HTTPException(
                status_code=503,
                detail={
                    "error": {
                        "code": "MODEL_UNAVAILABLE",
                        "message": f"Embedding model unavailable: {exc}",
                    }
                },
            )
    return _model_instance


def encode(texts: List[str], custom_encoder: Optional[TextEncoder] = None) -> np.ndarray:
    encoder = get_encoder(custom_encoder)
    vectors = encoder.encode(texts)
    return np.asarray(vectors, dtype=np.float64)


def similarity(
    cv_text: str,
    jd_text: str,
    custom_encoder: Optional[TextEncoder] = None,
) -> float:
    norm_cv = normalize(cv_text)
    norm_jd = normalize(jd_text)

    if not norm_cv or not norm_jd:
        return 0.0

    vectors = encode([norm_cv, norm_jd], custom_encoder=custom_encoder)
    vec_cv, vec_jd = vectors[0], vectors[1]

    norm_a = float(np.linalg.norm(vec_cv))
    norm_b = float(np.linalg.norm(vec_jd))
    if norm_a == 0.0 or norm_b == 0.0:
        return 0.0

    cos_val = float(np.dot(vec_cv, vec_jd) / (norm_a * norm_b))
    clamped = max(0.0, min(1.0, cos_val))
    return round(clamped, 6)
