from fastapi import FastAPI
from app.api.routes_cv import router as cv_router
from app.api.routes_matching import router as matching_router

def create_app() -> FastAPI:
    app = FastAPI(title="Smart Recruitment AI Service", version="1.0.0")

    @app.get("/internal/v1/health")
    async def health():
        return {"status": "ok", "service": "ai-service"}

    app.include_router(cv_router, prefix="/internal/v1/cv", tags=["CV Extraction"])
    app.include_router(matching_router, prefix="/internal/v1/matching", tags=["Matching"])
    return app

app = create_app()
