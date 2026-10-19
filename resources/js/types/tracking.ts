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

export type RangeKey = '24h' | '7d' | '30d' | 'custom';

export type AnalyticsFilters = {
    key: RangeKey;
    from: string;
    to: string;
    granularity: 'hour' | 'day';
};

export type Metrics = {
    page_views: number;
    impressions: number;
    clicks: number;
    conversions: number;
    attributed_conversions: number;
    click_through_rate: number | null;
    conversion_rate: number | null;
};

export type CountMetric =
    | 'page_views'
    | 'impressions'
    | 'clicks'
    | 'conversions';

export type SeriesPoint = { bucket: string } & Record<CountMetric, number>;

export type Analytics = {
    totals: Metrics;
    previous: Metrics;
    series: SeriesPoint[];
};

export type CampaignPerformance = {
    id: number;
    name: string;
    site: string;
    status: CampaignStatus;
    impressions: number;
    clicks: number;
    conversions: number;
    click_through_rate: number | null;
    conversion_rate: number | null;
};

export type AlertType =
    | 'volume_drop'
    | 'processing_failures'
    | 'processing_stalled';

export type MonitoringAlert = {
    id: number;
    type: AlertType;
    label: string;
    summary: string;
    site: SiteSummary;
    campaign: { id: number; name: string } | null;
    triggered_at: string;
    last_evaluated_at: string;
    acknowledged_at: string | null;
    acknowledged_by: string | null;
    resolved_at: string | null;
};
