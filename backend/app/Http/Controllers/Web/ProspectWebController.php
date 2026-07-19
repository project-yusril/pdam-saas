<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\CustomerProspect;
use Illuminate\View\View;

/**
 * ProspectWebController — UI web alur survey (Fase 3.2 & 3.4).
 * - index: antrian prospek untuk hublang_head (filter status)
 * - show : detail prospek + laporan survey (foto rumah, peta GPS, penilaian)
 *          sebagai bahan keputusan survey_head (approve/reject/re-survey).
 */
class ProspectWebController extends Controller
{
    public function index(): View
    {
        // Antrian utama hublang: menunggu review (pending_review) + status lain
        $pendingReview = CustomerProspect::where('status', 'pending_review')
            ->orderBy('created_at')
            ->get();

        $awaitingDecision = CustomerProspect::where('status', 'survey_submitted')
            ->with('survey')
            ->orderBy('updated_at')
            ->get();

        $inProgress = CustomerProspect::whereIn('status', ['surveying', 're_survey_needed'])
            ->orderBy('updated_at')
            ->get();

        return view('admin.prospects.index', compact('pendingReview', 'awaitingDecision', 'inProgress'));
    }

    public function show(CustomerProspect $prospect): View
    {
        $prospect->load('survey', 'street');

        return view('admin.prospects.show', compact('prospect'));
    }
}
