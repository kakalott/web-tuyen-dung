from fastapi import FastAPI
from app.api.routes_cv import router as cv_router

def create_app() -> FastAPI:
    app = FastAPI(title="Smart Recruitment AI Service", version="1.0.0")

    @app.get("/internal/v1/health")
    async def health():
        return {"status": "ok", "service": "ai-service"}

    app.include_router(cv_router, prefix="/internal/v1/cv", tags=["CV Extraction"])
    return app

app = create_app()
