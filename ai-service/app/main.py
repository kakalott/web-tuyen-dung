from fastapi import FastAPI, Depends, Request, HTTPException
from fastapi.responses import JSONResponse
from app.api.auth import verify_internal_token
from app.api.routes_cv import router as cv_router
from app.api.routes_matching import router as matching_router

def create_app() -> FastAPI:
    app = FastAPI(title="Smart Recruitment AI Service", version="1.0.0")

    @app.exception_handler(HTTPException)
    async def custom_http_exception_handler(request: Request, exc: HTTPException):
        code = "SERVICE_UNAUTHORIZED" if exc.status_code == 401 else (
            exc.detail if isinstance(exc.detail, str) else exc.detail.get("code", "ERROR")
        )
        msg = exc.detail if isinstance(exc.detail, str) else exc.detail.get("message", str(exc.detail))
        return JSONResponse(
            status_code=exc.status_code,
            content={
                "detail": "SERVICE_UNAUTHORIZED" if exc.status_code == 401 else exc.detail,
                "error": {
                    "code": code,
                    "message": msg,
                }
            }
        )

    @app.get("/internal/v1/health", dependencies=[Depends(verify_internal_token)])
    async def health():
        return {
            "status": "ok",
            "service": "ai-service",
            "data": {
                "status": "ok",
                "service": "ai-service",
            }
        }

    app.include_router(cv_router, prefix="/internal/v1/cv", tags=["CV Extraction"])
    app.include_router(matching_router, prefix="/internal/v1/matching", tags=["Matching"])
    return app

app = create_app()
