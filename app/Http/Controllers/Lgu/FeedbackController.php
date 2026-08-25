<?php

namespace App\Http\Controllers\Lgu;

use App\Support\LguMockData;
use Illuminate\Http\Request;
use Illuminate\View\View;

class FeedbackController extends LguController
{
    /**
     * All Feedback: entries for destinations/establishments in this municipality only.
     */
    public function index(Request $request): View
    {
        $municipality = $request->user()->organization_subtitle;

        return $this->renderLgu($request, 'lgu.feedback.index', 'feedback.index', 'Tourist Feedback', [
            'municipality' => $municipality,
            'feedback' => LguMockData::feedback($municipality),
        ]);
    }

    /**
     * Experience Analytics: municipality-level sentiment breakdown and trends.
     */
    public function analytics(Request $request): View
    {
        $municipality = $request->user()->organization_subtitle;
        $feedback = collect(LguMockData::feedback($municipality));
        $destinationNames = collect(LguMockData::destinations($municipality))->pluck('name');

        return $this->renderLgu($request, 'lgu.feedback.analytics', 'feedback.analytics', 'Experience Analytics', [
            'municipality' => $municipality,
            'sentiment' => LguMockData::sentimentBreakdown($municipality),
            'sentimentTrend' => LguMockData::sentimentTrend($municipality),
            'byDestination' => $feedback->whereIn('subject', $destinationNames)->groupBy('subject')->map->count()->sortDesc()->take(5),
            'byEstablishment' => $feedback->whereNotIn('subject', $destinationNames)->groupBy('subject')->map->count()->sortDesc()->take(5),
        ]);
    }
}
