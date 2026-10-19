export type CampaignStatus = 'draft' | 'active' | 'paused' | 'archived';

export type Site = {
    id: number;
    name: string;
    domain: string;
    site_key: string;
};

export type SiteSummary = Pick<Site, 'id' | 'name' | 'domain'>;

export type Campaign = {
    id: number;
    public_id: string;
    name: string;
    status: CampaignStatus;
    created_at: string;
    site: SiteSummary;
};

export type EventType =
    | 'page_view'
    | 'campaign_impression'
    | 'campaign_click'
    | 'conversion';

export type PipelineStats = {
    pending: number;
    oldestPendingAt: string | null;
    processedLastDay: number;
    failed: number;
};

export type FailedEvent = {
    id: number;
    event_id: string;
    type: EventType;
    site: string;
    campaign: string | null;
    url: string | null;
    properties: Record<string, string | number | boolean | null> | null;
    attempts: number;
    last_error: string | null;
    occurred_at: string;
    failed_at: string | null;
};

export type Paginated<T> = {
    data: T[];
    current_page: number;
    last_page: number;
    total: number;
    prev_page_url: string | null;
    next_page_url: string | null;
};
