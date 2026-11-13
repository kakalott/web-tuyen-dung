import os
from pydantic_settings import BaseSettings
from pydantic import ConfigDict
from functools import lru_cache

class Settings(BaseSettings):
    INTERNAL_TOKEN: str = os.getenv("INTERNAL_SERVICE_TOKEN", "change_this_internal_secret_token_in_production")
    INTERNAL_SERVICE_TOKEN: str = os.getenv("INTERNAL_SERVICE_TOKEN", "change_this_internal_secret_token_in_production")
    EMBEDDING_MODEL_NAME: str = "sentence-transformers/paraphrase-multilingual-MiniLM-L12-v2"
    MATCHING_ALGORITHM_VERSION: str = "v1.2.0"
    ALGORITHM_VERSION: str = "v1.2.0"
    MAX_PDF_SIZE_BYTES: int = 10485760
    MAX_PDF_PAGES: int = 15

    model_config = ConfigDict(
        env_file=".env",
        extra="ignore"
    )

@lru_cache()
def get_settings() -> Settings:
    return Settings()
