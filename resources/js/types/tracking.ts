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
