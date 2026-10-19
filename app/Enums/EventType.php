<?php

namespace App\Enums;

enum EventType: string
{
    case PageView = 'page_view';
    case CampaignImpression = 'campaign_impression';
    case CampaignClick = 'campaign_click';
    case Conversion = 'conversion';

    /**
     * Whether events of this type must reference a campaign.
     */
    public function requiresCampaign(): bool
    {
        return match ($this) {
            self::CampaignImpression, self::CampaignClick => true,
            self::PageView, self::Conversion => false,
        };
    }
}
