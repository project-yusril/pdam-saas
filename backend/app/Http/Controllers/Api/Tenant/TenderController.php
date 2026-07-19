<?php

namespace App\Http\Controllers\Api\Tenant;

use App\Http\Controllers\Controller;
use App\Models\Tender;
use App\Models\TenderBid;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TenderController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Tender::with('bids.vendor:id,name')
            ->when($request->input('status'), fn ($q, $v) => $q->where('status', $v))
            ->orderByDesc('publish_date');

        return ApiResponse::paginated($query->paginate($request->input('per_page', 25)));
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:200'],
            'description' => ['nullable', 'string'],
            'category' => ['nullable', 'string', 'max:50'],
            'budget_ceiling' => ['required', 'numeric', 'min:0'],
            'publish_date' => ['required', 'date'],
            'submission_deadline' => ['required', 'date', 'after:publish_date'],
        ]);

        $data['tender_number'] = 'TDR-'.now()->format('ym').'-'.strtoupper(uniqid());
        $data['status'] = 'published';

        return ApiResponse::success(Tender::create($data), status: 201);
    }

    public function show(Tender $tender): JsonResponse
    {
        $tender->load('bids.vendor:id,name');

        return ApiResponse::success($tender);
    }

    public function submitBid(Request $request, Tender $tender): JsonResponse
    {
        $data = $request->validate([
            'vendor_id' => ['required', 'integer', 'exists:vendors,id'],
            'bid_price' => ['required', 'numeric', 'min:0'],
            'technical_proposal' => ['nullable', 'string'],
        ]);

        $bid = TenderBid::create([
            'pdam_org_id' => $tender->pdam_org_id,
            'tender_id' => $tender->id,
            'vendor_id' => $data['vendor_id'],
            'bid_price' => $data['bid_price'],
            'technical_proposal' => $data['technical_proposal'] ?? null,
        ]);

        return ApiResponse::success($bid, status: 201);
    }

    public function evaluate(Request $request, Tender $tender): JsonResponse
    {
        $data = $request->validate([
            'evaluations' => ['required', 'array', 'min:1'],
            'evaluations.*.bid_id' => ['required', 'integer', 'exists:tender_bids,id'],
            'evaluations.*.technical_score' => ['required', 'numeric', 'between:0,100'],
            'evaluations.*.price_score' => ['required', 'numeric', 'between:0,100'],
        ]);

        $bids = TenderBid::where('tender_id', $tender->id)->get()->keyBy('id');

        foreach ($data['evaluations'] as $eval) {
            $bid = $bids->get($eval['bid_id']);
            if (! $bid) {
                continue;
            }

            $totalScore = round(($eval['technical_score'] + $eval['price_score']) / 2, 2);
            $bid->update([
                'technical_score' => $eval['technical_score'],
                'price_score' => $eval['price_score'],
                'total_score' => $totalScore,
            ]);
        }

        $ranked = TenderBid::where('tender_id', $tender->id)
            ->orderByDesc('total_score')
            ->get();

        foreach ($ranked as $i => $bid) {
            $bid->update(['rank' => $i + 1]);
        }

        return ApiResponse::success($ranked);
    }

    public function award(Request $request, Tender $tender): JsonResponse
    {
        $data = $request->validate([
            'winner_vendor_id' => ['required', 'integer', 'exists:vendors,id'],
            'final_price' => ['required', 'numeric', 'min:0'],
        ]);

        $tender->update([
            'winner_vendor_id' => $data['winner_vendor_id'],
            'final_price' => $data['final_price'],
            'status' => 'awarded',
            'award_date' => now()->toDateString(),
        ]);

        return ApiResponse::success($tender);
    }
}
