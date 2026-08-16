import { useState } from 'react';
import { Settings, RestrictionRule } from '../types';
import { Card, Button, NumberField } from '../components/Field';
import { RuleEditor } from '../components/RuleEditor';
import { saveSettings, saveRestrictionRules } from '../api';

interface Props {
  settings: Settings;
  patchSettings: (patch: Partial<Settings>) => void;
  rules: RestrictionRule[];
  setRules: (rules: RestrictionRule[]) => void;
  onToast: (kind: 'success' | 'error', message: string) => void;
  onSaved: (saved: Partial<Settings>) => void;
}

export function RestrictionsTab({ settings, patchSettings, rules, setRules, onToast, onSaved }: Props): JSX.Element {
  const [saving, setSaving] = useState(false);
  const boot = window.aeroPaywallAdmin;

  function togglePostType(slug: string): void {
    const selected = settings.aero_paywall_restricted_post_types;
    const next = selected.includes(slug) ? selected.filter((s) => s !== slug) : [...selected, slug];
    patchSettings({ aero_paywall_restricted_post_types: next.length > 0 ? next : ['post'] });
  }

  async function handleSaveAll(): Promise<void> {
    setSaving(true);
    try {
      // Whole settings object here too (see ConnectionTab's handleSave for
      // why) — the restriction-rules endpoint is separate and unaffected.
      const [saved] = await Promise.all([saveSettings(settings), saveRestrictionRules(rules)]);
      onSaved(saved);
      onToast('success', 'Restrictions saved.');
    } catch (err) {
      onToast('error', err instanceof Error ? err.message : 'Save failed');
    } finally {
      setSaving(false);
    }
  }

  const categoryTaxonomy = boot.taxonomies.find((t) => t.slug === 'category');
  const premiumCategoryIds = settings.aero_paywall_premium_terms.category;

  function togglePremiumCategory(termId: number): void {
    const next = premiumCategoryIds.includes(termId)
      ? premiumCategoryIds.filter((id) => id !== termId)
      : [...premiumCategoryIds, termId];
    patchSettings({ aero_paywall_premium_terms: { category: next } });
  }

  return (
    <div className="aero-tab">
      <Card title="Restricted content types" description="Only content of these types can ever be marked premium.">
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

      {categoryTaxonomy && (
        <Card
          title="Premium categories"
          description="In Soft mode, any post assigned to a checked category is premium — the everyday way to make a whole section subscriber-only without touching individual posts or writing a rule below. A post's own “Force premium”/“Force free” override always wins over this."
        >
          <div className="aero-checkbox-grid">
            {categoryTaxonomy.terms.map((term) => (
              <label key={term.id} className="aero-checkbox-grid__item">
                <input type="checkbox" checked={premiumCategoryIds.includes(term.id)} onChange={() => togglePremiumCategory(term.id)} />
                {term.name}
              </label>
            ))}
          </div>
        </Card>
      )}

      <Card
        title="Content restrictions"
        description="Set who can read what, and how much is free. Restrictions are processed top to bottom — a post is governed by the first matching rule."
      >
        <RuleEditor rules={rules} onChange={setRules} postTypes={boot.postTypes} taxonomies={boot.taxonomies} />
      </Card>

      <Card title="Preview length" description="How much of a gated article is shown for free before the paywall, when no in-content break marker is used.">
        <NumberField
          label="Preview word count"
          value={settings.aero_paywall_preview_word_count}
          min={1}
          onChange={(v) => patchSettings({ aero_paywall_preview_word_count: v })}
          description="Insert an [aeropaywall_break] shortcode in a post's content to override this with an author-chosen cut-off point for that article."
        />
      </Card>

      <Card title="Restriction exceptions" description="Content in these categories/tags is never gated, even if it would otherwise match a rule above.">
        <ExceptionsEditor
          value={settings.aero_paywall_restriction_exceptions}
          taxonomies={boot.taxonomies}
          onChange={(value) => patchSettings({ aero_paywall_restriction_exceptions: value })}
        />
      </Card>

      <Button onClick={handleSaveAll} loading={saving}>
        Save changes
      </Button>
    </div>
  );
}

function ExceptionsEditor({
  value,
  taxonomies,
  onChange,
}: {
  value: Record<string, number[]>;
  taxonomies: { slug: string; label: string; terms: { id: number; name: string }[] }[];
  onChange: (value: Record<string, number[]>) => void;
}): JSX.Element {
  function toggleTerm(taxonomy: string, termId: number): void {
    const current = value[taxonomy] ?? [];
    const next = current.includes(termId) ? current.filter((id) => id !== termId) : [...current, termId];
    onChange({ ...value, [taxonomy]: next });
  }

  return (
    <div className="aero-exceptions">
      {taxonomies.map((tax) => (
        <div key={tax.slug} className="aero-exceptions__group">
          <h4>{tax.label}</h4>
          <div className="aero-checkbox-grid">
            {tax.terms.map((term) => (
              <label key={term.id} className="aero-checkbox-grid__item">
                <input
                  type="checkbox"
                  checked={(value[tax.slug] ?? []).includes(term.id)}
                  onChange={() => toggleTerm(tax.slug, term.id)}
                />
                {term.name}
              </label>
            ))}
          </div>
        </div>
      ))}
    </div>
  );
}
