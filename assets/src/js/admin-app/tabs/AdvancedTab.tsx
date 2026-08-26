import { useState } from 'react';
import { Settings, ConnectorSettings } from '../types';
import { Card, TextField, Toggle, NumberField, Button } from '../components/Field';
import { saveSettings, getConnectorSettings, updateConnectorSetting } from '../api';
import { urlLooksInvalid } from '../validation';

interface Props {
  settings: Settings;
  patchSettings: (patch: Partial<Settings>) => void;
  connectorSettings: ConnectorSettings;
  setConnectorSettings: (value: ConnectorSettings) => void;
  onToast: (kind: 'success' | 'error', message: string) => void;
  onGoToWizard: () => void;
  onSaved: (saved: Partial<Settings>) => void;
}

export function AdvancedTab({
  settings,
  patchSettings,
  connectorSettings,
  setConnectorSettings,
  onToast,
  onGoToWizard,
  onSaved,
}: Props): JSX.Element {
  const [saving, setSaving] = useState(false);
  const [savingMetering, setSavingMetering] = useState(false);
  const thresholds = connectorSettings.funnel_thresholds ?? { stage2: 2, stage3: 3, stage4: 4 };

  async function handleSave(): Promise<void> {
    setSaving(true);
    try {
      const saved = await saveSettings(settings);
      onSaved(saved);
      onToast('success', 'Advanced settings saved.');
    } catch (err) {
      onToast('error', err instanceof Error ? err.message : 'Save failed');
    } finally {
      setSaving(false);
    }
  }

  async function refreshMetering(): Promise<void> {
    try {
      const fresh = await getConnectorSettings();
      setConnectorSettings(fresh);
    } catch (err) {
      onToast('error', err instanceof Error ? err.message : 'Could not load metering settings');
    }
  }

  async function saveMeteringField(key: keyof ConnectorSettings, value: unknown): Promise<void> {
    setSavingMetering(true);
    try {
      await updateConnectorSetting(key, value);
      setConnectorSettings({ ...connectorSettings, [key]: value as never });
      onToast('success', 'Metering setting saved.');
    } catch (err) {
      onToast('error', err instanceof Error ? err.message : 'Save failed');
    } finally {
      setSavingMetering(false);
    }
  }

  return (
    <div className="aero-tab">
      <Card title="Free-article metering" description="Enforced by the Subscription Service, not stored in WordPress — changes here apply immediately across web and mobile.">
        <NumberField
          label="Reset period (days)"
          value={connectorSettings.meter_cycle_days ?? 30}
          min={1}
          onChange={(v) => saveMeteringField('meter_cycle_days', v)}
        />
        <div className="aero-metering-grid">
          <NumberField
            label="Register prompt at"
            value={thresholds.stage2}
            min={1}
            onChange={(v) => saveMeteringField('funnel_thresholds', { ...thresholds, stage2: v })}
          />
          <NumberField
            label="Profile prompt at"
            value={thresholds.stage3}
            min={1}
            onChange={(v) => saveMeteringField('funnel_thresholds', { ...thresholds, stage3: v })}
          />
          <NumberField
            label="Hard lock at"
            value={thresholds.stage4}
            min={1}
            onChange={(v) => saveMeteringField('funnel_thresholds', { ...thresholds, stage4: v })}
          />
        </div>
        <Button variant="ghost" onClick={refreshMetering} disabled={savingMetering}>
          Refresh from server
        </Button>
      </Card>

      <Card title="Search engines">
        <p className="aero-field__description">
          Verified search-engine crawlers (Googlebot, Bingbot, etc.) always bypass metering and restrictions automatically, so gated
          content stays indexable. This isn't a setting — it's always on.
        </p>
        <Toggle
          label="Paywalled-content structured data (JSON-LD)"
          checked={settings.aero_paywall_jsonld_enabled}
          onChange={(v) => patchSettings({ aero_paywall_jsonld_enabled: v })}
          description="Marks gated articles with isAccessibleForFree: false so search engines index them as intentionally paywalled, per Google's guidelines."
        />
      </Card>

      <Card title="Ad-free">
        <Toggle
          label="Ad-free for subscribers"
          checked={settings.aero_paywall_adfree_enabled}
          onChange={(v) => patchSettings({ aero_paywall_adfree_enabled: v })}
        />
      </Card>

      <Card title="Account & sign-in">
        <TextField
          label="My Account page URL"
          value={settings.aero_paywall_account_page_url}
          placeholder="https://example.com/my-account"
          onChange={(v) => patchSettings({ aero_paywall_account_page_url: v })}
          error={urlLooksInvalid(settings.aero_paywall_account_page_url) ? "Doesn't look like a valid URL yet." : null}
        />
        <TextField
          label="Log In page URL (optional)"
          value={settings.aero_paywall_login_page_url}
          placeholder="https://example.com/login"
          onChange={(v) => patchSettings({ aero_paywall_login_page_url: v })}
          description={
            <>
              A dedicated page (containing <code>[aeropaywall_account tab=&quot;login&quot;]</code>) the
              header nav links to instead of the My Account page. Leave blank to keep using{' '}
              <code>{'{account}'}?tab=login</code>.
            </>
          }
          error={
            settings.aero_paywall_login_page_url && urlLooksInvalid(settings.aero_paywall_login_page_url)
              ? "Doesn't look like a valid URL yet."
              : null
          }
        />
        <TextField
          label="Create Account page URL (optional)"
          value={settings.aero_paywall_register_page_url}
          placeholder="https://example.com/create-account"
          onChange={(v) => patchSettings({ aero_paywall_register_page_url: v })}
          description={
            <>
              Same idea, for signup (<code>tab=&quot;register&quot;</code>). Leave blank to keep using{' '}
              <code>{'{account}'}?tab=register</code>.
            </>
          }
          error={
            settings.aero_paywall_register_page_url && urlLooksInvalid(settings.aero_paywall_register_page_url)
              ? "Doesn't look like a valid URL yet."
              : null
          }
        />
        <TextField
          label="Google Sign-In client ID"
          value={settings.aero_paywall_google_client_id}
          placeholder="xxxx.apps.googleusercontent.com"
          onChange={(v) => patchSettings({ aero_paywall_google_client_id: v })}
          description="Leave blank to hide the “Continue with Google” button."
        />
        <TextField
          label="Apple Sign-In client ID (Services ID)"
          value={settings.aero_paywall_apple_client_id}
          placeholder="com.example.aeropaywall.web"
          onChange={(v) => patchSettings({ aero_paywall_apple_client_id: v })}
          description="Leave blank to hide the “Continue with Apple” button."
        />
      </Card>

      <Card title="Setup Wizard">
        <Button variant="ghost" onClick={onGoToWizard}>
          Re-run the setup wizard
        </Button>
      </Card>

      <Button onClick={handleSave} loading={saving}>
        Save changes
      </Button>
    </div>
  );
}
