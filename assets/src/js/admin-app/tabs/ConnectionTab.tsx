import { useState } from 'react';
import { Settings } from '../types';
import { Card, TextField, Toggle, Button } from '../components/Field';
import { saveSettings, testConnection } from '../api';
import { urlLooksInvalid } from '../validation';

interface Props {
  settings: Settings;
  patchSettings: (patch: Partial<Settings>) => void;
  onToast: (kind: 'success' | 'error', message: string) => void;
  onSaved: (saved: Partial<Settings>) => void;
}

export function ConnectionTab({ settings, patchSettings, onToast, onSaved }: Props): JSX.Element {
  const [saving, setSaving] = useState(false);
  const [testing, setTesting] = useState(false);
  const [testResult, setTestResult] = useState<string | null>(null);

  async function handleSave(): Promise<void> {
    setSaving(true);
    try {
      // Sends every field currently in `settings`, not just this tab's own
      // — otherwise an edit made on another tab (e.g. the Wizard's
      // Connect step, which shares these exact fields) but never saved
      // from there would be silently discarded the moment this tab's Save
      // button runs, since the server only persists whatever subset of
      // fields arrives in a given request.
      const saved = await saveSettings(settings);
      onSaved(saved);
      onToast('success', 'Connection settings saved.');
    } catch (err) {
      onToast('error', err instanceof Error ? err.message : 'Save failed');
    } finally {
      setSaving(false);
    }
  }

  async function handleTest(): Promise<void> {
    setTesting(true);
    setTestResult(null);
    try {
      const result = await testConnection(settings.aero_paywall_api_base_url);
      setTestResult(result.message);
    } catch (err) {
      setTestResult(err instanceof Error ? err.message : 'Connection test failed');
    } finally {
      setTesting(false);
    }
  }

  return (
    <div className="aero-tab">
      <Card title="Master switch" description="Off = the site behaves exactly as it did before this plugin — no gating, no SDK. This is the rollback control.">
        <Toggle
          label="AeroPaywall enabled"
          checked={settings.aero_paywall_enabled}
          onChange={(v) => patchSettings({ aero_paywall_enabled: v })}
        />
      </Card>

      <Card title="Subscription Service" description="Where this site's reader accounts, subscriptions, and metering live.">
        <TextField
          label="Subscription Service base URL"
          value={settings.aero_paywall_api_base_url}
          placeholder="https://accounts.example.com/api/v1"
          onChange={(v) => patchSettings({ aero_paywall_api_base_url: v })}
          error={urlLooksInvalid(settings.aero_paywall_api_base_url) ? "Doesn't look like a valid URL yet." : null}
        />
        <TextField
          label="Connector API key"
          type="password"
          value={settings.aero_paywall_api_key}
          onChange={(v) => patchSettings({ aero_paywall_api_key: v })}
        />
        <div className="aero-connection-test">
          <Button variant="secondary" onClick={handleTest} loading={testing}>
            Test connection
          </Button>
          {testResult && <span role="status">{testResult}</span>}
        </div>
      </Card>

      <Card title="Licensing">
        <TextField
          label="Licensing Platform base URL"
          value={settings.aero_paywall_licensing_api_base_url}
          placeholder="https://licensing.aeropaywall.com/api/v1"
          onChange={(v) => patchSettings({ aero_paywall_licensing_api_base_url: v })}
          error={urlLooksInvalid(settings.aero_paywall_licensing_api_base_url) ? "Doesn't look like a valid URL yet." : null}
        />
        <TextField
          label="License key"
          value={settings.aero_paywall_license_key}
          placeholder="AP-XXXX-XXXX"
          onChange={(v) => patchSettings({ aero_paywall_license_key: v })}
        />
      </Card>

      <Card title="SDK">
        <TextField
          label="SDK CDN base URL"
          value={settings.aero_paywall_sdk_cdn_base}
          placeholder="https://cdn.aeropaywall.com/sdk"
          onChange={(v) => patchSettings({ aero_paywall_sdk_cdn_base: v })}
        />
        <TextField
          label="SDK version"
          value={settings.aero_paywall_sdk_version}
          placeholder="latest"
          onChange={(v) => patchSettings({ aero_paywall_sdk_version: v })}
        />
      </Card>

      <Button onClick={handleSave} loading={saving}>
        Save changes
      </Button>
    </div>
  );
}
