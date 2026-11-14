<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\UploadCVRequest;
use App\Http\Resources\CVResource;
use App\Services\CVService;
use App\Models\CandidateCV;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class CandidateCVController extends Controller
{
    public function __construct(private CVService $cvService) {}

    public function index(Request $request)
    {
        $cvs = CandidateCV::where('candidate_profile_id', $request->user()->candidateProfile->id)
            ->with(['latestExtraction.skills'])
            ->latest('uploaded_at')
            ->paginate();
        return CVResource::collection($cvs);
    }

    public function show(Request $request, int $cvId)
    {
        $cv = CandidateCV::with(['latestExtraction.skills'])->findOrFail($cvId);
        Gate::authorize('view', $cv);

        return new CVResource($cv);
    }

    public function upload(UploadCVRequest $request)
    {
        $cv = $this->cvService->upload($request->user(), $request->file('file'));
        // Trigger extract after upload
        $this->cvService->extract($cv, $request->file('file'));
        
        return (new CVResource($cv->fresh()))->response()->setStatusCode(201);
    }

    public function downloadOwn(Request $request, int $cvId)
    {
        $cv = CandidateCV::findOrFail($cvId);
        Gate::authorize('view', $cv);
        
        return $this->cvService->downloadOwn($request->user(), $cvId);
    }
}
