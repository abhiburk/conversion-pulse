<?php

namespace App\Http\Controllers;

use App\Enums\CampaignStatus;
use App\Models\Site;
use Database\Seeders\DemoSeeder;
use Illuminate\View\View;

class DemoStoreController extends Controller
{
    /**
     * Show a sample storefront that runs the tracking SDK against the demo workspace, so live events can be sent without installing the snippet elsewhere.
     */
    public function __invoke(): View
    {
        $site = Site::firstWhere('site_key', DemoSeeder::STORE_SITE_KEY);
        $campaign = $site?->campaigns()
            ->where('status', CampaignStatus::Active)
            ->where('name', 'Exit-intent 10% discount')
            ->first();

        return view('demo', [
            'siteKey' => $site?->site_key,
            'campaignId' => $campaign?->public_id,
        ]);
    }
}
