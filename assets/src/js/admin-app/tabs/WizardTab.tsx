import { useState } from 'react';
import { Settings, RestrictionRule } from '../types';
import { Card, TextField, Button } from '../components/Field';
import { saveSettings, testConnection } from '../api';
import { urlLooksInvalid } from '../validation';

interface Props {
  settings: Settings;
  patchSettings: (patch: Partial<Settings>) => void;
  rules: RestrictionRule[];
  setRules: (rules: RestrictionRule[]) => void;
  onFinish: () => void;
  onToast: (kind: 'success' | 'error', message: string) => void;
  onSaved: (saved: Partial<Settings>) => void;
}

const STEP_LABELS = ['Connect', 'Restrictions', 'Account page', 'Review'];

export function WizardTab({ settings, patchSettings, onFinish, onToast, onSaved }: Props): JSX.Element {
  const [step, setStep] = useState(0);
  const [testing, setTesting] = useState(false);
  const [testResult, setTestResult] = useState<string | null>(null);
  const [finishing, setFinishing] = useState(false);
  const boot = window.aeroPaywallAdmin;

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

  function togglePostType(slug: string): void {
    const selected = settings.aero_paywall_restricted_post_types;
    const next = selected.includes(slug) ? selected.filter((s) => s !== slug) : [...selected, slug];
    patchSettings({ aero_paywall_restricted_post_types: next.length > 0 ? next : ['post'] });
  }

  async function handleFinish(): Promise<void> {
    setFinishing(true);
    try {
      // Sends the whole settings object (not just the fields this wizard
      // walked through) plus the two finish-specific flags — so anything
      // already filled in on another tab before Finish is clicked is
      // persisted together with it, rather than only this step's fields.
      const saved = await saveSettings({
        ...settings,
        aero_paywall_enabled: true,
      });
      patchSettings({ aero_paywall_enabled: true });
      onSaved({ ...saved, aero_paywall_enabled: true });
      onToast('success', 'AeroPaywall is set up and enabled.');
      onFinish();
    } catch (err) {
      onToast('error', err instanceof Error ? err.message : 'Setup could not be saved');
    } finally {
      setFinishing(false);
    }
  }

  return (
    <div className="aero-tab aero-wizard">
      <ol className="aero-wizard__steps">
        {STEP_LABELS.map((label, i) => (
          <li key={label} className={i === step ? 'aero-wizard__step--active' : ''}>
            {label}
          </li>
        ))}
      </ol>

      {step === 0 && (
        <Card title="Connect this site" description="Point AeroPaywall at your Subscription Service.">
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
      )}

      {step === 1 && (
        <Card title="Choose what's restricted" description="You can refine this anytime from the Restrictions tab.">
          <div className="aero-checkbox-grid">
            {boot.postTypes.map((pt) => (
              <label key={pt.slug} className="aero-checkbox-grid__item">
                <input
                  type="checkbox"
                  checked={settings.aero_paywall_restricted_post_types.includes(pt.slug)}
                  onChange={() => togglePostType(pt.slug)}
                />
                {pt.label}
              </label>
            ))}
          </div>
        </Card>
      )}

      {step === 2 && (
        <Card title="My Account page">
          {settings.aero_paywall_account_page_url ? (
            <p>
              A My Account page was created automatically:{' '}
              <a href={settings.aero_paywall_account_page_url} target="_blank" rel="noopener noreferrer">
                {settings.aero_paywall_account_page_url}
              </a>
            </p>
          ) : (
            <p>No Account page was auto-created yet — activate the plugin, or set one manually below.</p>
          )}
          <TextField
            label="My Account page URL"
            value={settings.aero_paywall_account_page_url}
            placeholder="https://example.com/my-account"
            onChange={(v) => patchSettings({ aero_paywall_account_page_url: v })}
          />
        </Card>
      )}

      {step === 3 && (
        <Card title="Review" description="Finishing setup enables AeroPaywall on this site.">
          <ul className="aero-wizard__review">
            <li>Subscription Service: {settings.aero_paywall_api_base_url || '(not set)'}</li>
            <li>Restricted content types: {settings.aero_paywall_restricted_post_types.join(', ')}</li>
            <li>Account page: {settings.aero_paywall_account_page_url || '(not set)'}</li>
          </ul>
        </Card>
      )}

      <div className="aero-wizard__nav">
        <Button variant="ghost" onClick={() => setStep((s) => Math.max(0, s - 1))} disabled={step === 0}>
          Back
        </Button>
        {step < STEP_LABELS.length - 1 ? (
          <Button onClick={() => setStep((s) => Math.min(STEP_LABELS.length - 1, s + 1))}>Next</Button>
        ) : (
          <Button onClick={handleFinish} loading={finishing}>
            Finish setup
          </Button>
        )}
      </div>
    </div>
  );
}
