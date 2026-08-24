<?php

namespace App\Http\Controllers\Pto;

use App\Support\PtoMockData;
use App\Support\TourismCatalog;
use Illuminate\Http\Request;
use Illuminate\View\View;

class FeedbackController extends PtoController
{
    /**
     * All Feedback: every tourist feedback entry, searchable and filterable.
     */
    public function index(Request $request): View
    {
        return $this->renderPto($request, 'pto.feedback.index', 'feedback.index', 'Tourist Feedback', [
            'feedback' => PtoMockData::feedback(),
        ]);
    }

    /**
     * Experience Analytics: sentiment breakdown and trends.
     */
    public function analytics(Request $request): View
    {
        $feedback = collect(PtoMockData::feedback());

        return $this->renderPto($request, 'pto.feedback.analytics', 'feedback.analytics', 'Experience Analytics', [
            'sentiment' => PtoMockData::sentimentBreakdown(),
            'sentimentTrend' => PtoMockData::sentimentTrend(),
            'byDestination' => $feedback->whereIn('subject', collect(TourismCatalog::featuredDestinations())->pluck('name'))
                ->groupBy('subject')->map->count()->sortDesc()->take(5),
            'byEstablishment' => $feedback->whereNotIn('subject', collect(TourismCatalog::featuredDestinations())->pluck('name'))
                ->groupBy('subject')->map->count()->sortDesc()->take(5),
        ]);
    }
}
