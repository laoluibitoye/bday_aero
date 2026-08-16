import { useState } from 'react';
import { Settings } from '../types';
import { Card, TextField, Button } from '../components/Field';
import { saveSettings } from '../api';

interface Props {
  settings: Settings;
  patchSettings: (patch: Partial<Settings>) => void;
  onToast: (kind: 'success' | 'error', message: string) => void;
  onSaved: (saved: Partial<Settings>) => void;
}

export function AppearanceTab({ settings, patchSettings, onToast, onSaved }: Props): JSX.Element {
  const [saving, setSaving] = useState(false);

  async function handleSave(): Promise<void> {
    setSaving(true);
    try {
      const saved = await saveSettings(settings);
      onSaved(saved);
      onToast('success', 'Appearance saved.');
    } catch (err) {
      onToast('error', err instanceof Error ? err.message : 'Save failed');
    } finally {
      setSaving(false);
    }
  }

  return (
    <div className="aero-tab">
      <Card title="Accent color" description="Fallback only — the primary source of truth is admin-web's branding settings.">
        <div className="aero-color-field">
          <input
            type="color"
            value={settings.aero_paywall_accent_color}
            onChange={(e) => patchSettings({ aero_paywall_accent_color: e.target.value })}
          />
          <TextField
            label="Hex value"
            value={settings.aero_paywall_accent_color}
            placeholder="#1a73e8"
            onChange={(v) => patchSettings({ aero_paywall_accent_color: v })}
          />
        </div>
      </Card>

      <Button onClick={handleSave} loading={saving}>
        Save changes
      </Button>
    </div>
  );
}
