import { BootstrapData, ConnectorSettings, RequiredPage, RestrictionRule, Settings } from './types';

function bootstrap(): BootstrapData {
  return window.aeroPaywallAdmin;
}

async function postForm(action: string, nonce: string, extra: Record<string, string>): Promise<any> {
  const body = new URLSearchParams({ action, nonce, ...extra });
  const response = await fetch(bootstrap().ajaxUrl, { method: 'POST', body });
  const json = await response.json();
  if (!json.success) {
    throw new Error(json.data?.message ?? 'Request failed');
  }
  return json.data;
}

export function testConnection(baseUrl: string): Promise<{ message: string; status: number }> {
  return postForm('aero_paywall_test_connection', bootstrap().nonces.testConnection, { base_url: baseUrl });
}

export function saveSettings(settings: Partial<Settings>): Promise<Partial<Settings>> {
  return postForm('aero_paywall_save_settings', bootstrap().nonces.saveSettings, {
    settings: JSON.stringify(settings),
  }).then((data) => data.settings);
}

export function saveRestrictionRules(rules: RestrictionRule[]): Promise<RestrictionRule[]> {
  return postForm('aero_paywall_save_restriction_rules', bootstrap().nonces.restrictionRules, {
    rules: JSON.stringify(rules),
  }).then((data) => data.rules);
}

export function getConnectorSettings(): Promise<ConnectorSettings> {
  return postForm('aero_paywall_get_connector_settings', bootstrap().nonces.connectorSettings, {});
}

export function updateConnectorSetting(key: string, value: unknown): Promise<{ key: string; value: unknown }> {
  return postForm('aero_paywall_update_connector_settings', bootstrap().nonces.connectorSettings, {
    key,
    value: JSON.stringify(value),
  });
}

export function createRequiredPages(): Promise<{ pages: RequiredPage[]; settings: Partial<Settings> }> {
  return postForm('bday_aero_create_pages', bootstrap().nonces.createPages, {});
}
