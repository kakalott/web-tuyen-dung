from fastapi import Header, HTTPException, status
from app.core.config import get_settings

settings = get_settings()

async def verify_internal_token(x_internal_token: str = Header(None)):
    if not x_internal_token or x_internal_token != settings.INTERNAL_TOKEN:
        raise HTTPException(
            status_code=status.HTTP_401_UNAUTHORIZED,
            detail="SERVICE_UNAUTHORIZED"
        )
