from fastapi import APIRouter, Depends
from app.api.auth import verify_internal_token
from app.schemas.matching import MatchingRequest, MatchingResponse
from app.services import matching_pipeline

router = APIRouter()


@router.post(
    "/score",
    response_model=MatchingResponse,
    dependencies=[Depends(verify_internal_token)],
)
def score_matching(request: MatchingRequest) -> MatchingResponse:
    return matching_pipeline.score(request)
