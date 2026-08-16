import { useState } from 'react';
import { PromptCopy, Settings } from '../types';
import { Card, TextField, TextAreaField, Button } from '../components/Field';
import { saveSettings } from '../api';

interface Props {
  settings: Settings;
  patchSettings: (patch: Partial<Settings>) => void;
  onToast: (kind: 'success' | 'error', message: string) => void;
  onSaved: (saved: Partial<Settings>) => void;
}

const STAGES: { key: keyof PromptCopy; title: string; description: string }[] = [
  {
    key: 'register_prompt',
    title: 'Stage 1 — Create a free account',
    description: 'Shown when a reader hits the free-article limit and needs to register to keep reading.',
  },
  {
    key: 'profile_prompt',
    title: 'Stage 2 — Complete your profile',
    description: 'Shown to a registered reader who still needs to complete their profile before continuing.',
  },
  {
    key: 'paid_lock',
    title: 'Stage 3 — Subscribe',
    description: 'The actual paywall: shown once a reader has used up every free/registered read. This is where "Subscribe Now"-style offers and sales copy go.',
  },
];

/**
 * Everything here maps straight onto prompt-forms.ts's card rendering —
 * these four fields (headline/subcopy/CTA/offer badge) are the entire
 * admin-configurable surface of the reader-facing paywall prompt, so an
 * offer, a CTA wording test, or new sales copy can ship without a code
 * deploy. Blank fields fall back to AeroPaywall_Settings::prompt_copy()'s
 * own defaults server-side — clearing a field back to empty here and
 * saving restores the original copy, it doesn't leave the prompt blank.
 */
export function PaywallCopyTab({ settings, patchSettings, onToast, onSaved }: Props): JSX.Element {
  const [saving, setSaving] = useState(false);
  const copy = settings.aero_paywall_prompt_copy;

  function patchStage(stage: keyof PromptCopy, field: keyof PromptCopy[typeof stage], value: string): void {
    patchSettings({
      aero_paywall_prompt_copy: {
        ...copy,
        [stage]: { ...copy[stage], [field]: value },
      },
    });
  }

  async function handleSave(): Promise<void> {
    setSaving(true);
    try {
      const saved = await saveSettings(settings);
      onSaved(saved);
      onToast('success', 'Paywall copy saved.');
    } catch (err) {
      onToast('error', err instanceof Error ? err.message : 'Save failed');
    } finally {
      setSaving(false);
    }
  }

  return (
    <div className="aero-tab">
      {STAGES.map(({ key, title, description }) => (
        <Card key={key} title={title} description={description}>
          <TextField
            label="Offer badge (optional)"
            value={copy[key].offerBadge}
            placeholder="e.g. Special Offer"
            onChange={(v) => patchStage(key, 'offerBadge', v)}
            description="A small eyebrow line above the headline. Leave blank to show none."
          />
          <TextField
            label="Headline"
            value={copy[key].headline}
            onChange={(v) => patchStage(key, 'headline', v)}
          />
          <TextAreaField
            label="Sub-copy"
            value={copy[key].subcopy}
            onChange={(v) => patchStage(key, 'subcopy', v)}
          />
          <TextField
            label="Button text"
            value={copy[key].cta}
            onChange={(v) => patchStage(key, 'cta', v)}
          />
        </Card>
      ))}

      <Button onClick={handleSave} loading={saving}>
        Save changes
      </Button>
    </div>
  );
}
