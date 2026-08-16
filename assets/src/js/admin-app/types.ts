export interface PostTypeOption {
  slug: string;
  label: string;
}

export interface TermOption {
  id: number;
  name: string;
}

export interface TaxonomyOption {
  slug: string;
  label: string;
  terms: TermOption[];
}

export interface RestrictionRule {
  id: string;
  post_type: string;
  taxonomy: string;
  term_ids: number[];
  number_allowed: number | null;
  period_days: number | null;
  require_registration: boolean;
}

export interface PromptStageCopy {
  headline: string;
  subcopy: string;
  cta: string;
  offerBadge: string;
}

export type PromptCopy = Record<'register_prompt' | 'profile_prompt' | 'paid_lock', PromptStageCopy>;

export interface Settings {
  aero_paywall_enabled: boolean;
  aero_paywall_api_base_url: string;
  aero_paywall_api_key: string;
  aero_paywall_licensing_api_base_url: string;
  aero_paywall_license_key: string;
  aero_paywall_sdk_cdn_base: string;
  aero_paywall_sdk_version: string;
  aero_paywall_account_page_url: string;
  aero_paywall_subscribe_page_url: string;
  aero_paywall_login_page_url: string;
  aero_paywall_register_page_url: string;
  aero_paywall_google_client_id: string;
  aero_paywall_apple_client_id: string;
  aero_paywall_accent_color: string;
  aero_paywall_adfree_enabled: boolean;
  aero_paywall_private_mode_enforcement: 'off' | 'soft' | 'hard';
  aero_paywall_restricted_post_types: string[];
  aero_paywall_preview_word_count: number;
  aero_paywall_paywall_mode: 'soft' | 'hard';
  aero_paywall_bypass_roles: string[];
  aero_paywall_jsonld_enabled: boolean;
  aero_paywall_restriction_exceptions: Record<string, number[]>;
  aero_paywall_prompt_copy: PromptCopy;
  /** category term IDs only — {category: number[]} */
  aero_paywall_premium_terms: { category: number[] };
}

export interface DashboardStats {
  totalReaders: number;
  activeSubscribers: number;
  totalFollows: number;
  topFollowedTerms: Array<{ termLabel: string; taxonomy: string; followerCount: number }>;
}

export interface RequiredPage {
  key: string;
  label: string;
  exists: boolean;
  url: string | null;
}

export interface FunnelThresholds {
  stage2: number;
  stage3: number;
  stage4: number;
}

export interface ConnectorSettings {
  meter_scope_mode?: 'restricted_only' | 'hybrid' | 'global_lock' | 'hard_wall';
  meter_limit?: number;
  meter_cycle_days?: number;
  funnel_thresholds?: FunnelThresholds;
  meter_ip_fallback_enabled?: boolean;
  restrictions_combine_mode?: boolean;
}

export interface BootstrapData {
  ajaxUrl: string;
  nonces: {
    testConnection: string;
    saveSettings: string;
    restrictionRules: string;
    connectorSettings: string;
    createPages: string;
  };
  setupComplete: boolean;
  settings: Settings;
  restrictionRules: RestrictionRule[];
  postTypes: PostTypeOption[];
  taxonomies: TaxonomyOption[];
  roles: Record<string, string>;
  connectorSettings: ConnectorSettings;
  dashboardStats: DashboardStats;
  requiredPages: RequiredPage[];
  licenseActive: boolean;
  devModeBypass: boolean;
}

declare global {
  interface Window {
    aeroPaywallAdmin: BootstrapData;
  }
}
