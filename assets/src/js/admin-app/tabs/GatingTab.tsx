import { useState } from 'react';
import { Settings, RestrictionRule, TaxonomyOption, ConnectorSettings } from '../types';
import { Card, Button, NumberField, Toggle, SegmentedControl } from '../components/Field';
import { RuleEditor } from '../components/RuleEditor';
import { TermPicker } from '../components/TermPicker';
import { GatingModeSelector } from '../components/GatingModeSelector';
import { saveSettings, saveRestrictionRules, updateConnectorSetting } from '../api';

interface Props {
  settings: Settings;
  patchSettings: (patch: Partial<Settings>) => void;
  rules: RestrictionRule[];
  setRules: (rules: RestrictionRule[]) => void;
  connectorSettings: ConnectorSettings;
  setConnectorSettings: (value: ConnectorSettings) => void;
  onToast: (kind: 'success' | 'error', message: string) => void;
  onSaved: (saved: Partial<Settings>) => void;
}

export function GatingTab({
  settings,
  patchSettings,
  rules,
  setRules,
  connectorSettings,
  setConnectorSettings,
  onToast,
  onSaved,
}: Props): JSX.Element {
  const [saving, setSaving] = useState(false);
  const [savingMetering, setSavingMetering] = useState(false);
  const boot = window.aeroPaywallAdmin;

  function togglePostType(slug: string): void {
    const selected = settings.aero_paywall_restricted_post_types;
    const next = selected.includes(slug) ? selected.filter((s) => s !== slug) : [...selected, slug];
    patchSettings({ aero_paywall_restricted_post_types: next.length > 0 ? next : ['post'] });
  }

  function toggleBypassRole(slug: string): void {
    const next = settings.aero_paywall_bypass_roles.includes(slug)
      ? settings.aero_paywall_bypass_roles.filter((r) => r !== slug)
      : [...settings.aero_paywall_bypass_roles, slug];
    patchSettings({ aero_paywall_bypass_roles: next });
  }

  async function handleSaveAll(): Promise<void> {
    setSaving(true);
    try {
      // Whole settings object here too (see ConnectionTab's handleSave for
      // why) — the restriction-rules endpoint is separate and unaffected.
      const [saved] = await Promise.all([saveSettings(settings), saveRestrictionRules(rules)]);
      onSaved(saved);
      onToast('success', 'Gating settings saved.');
    } catch (err) {
      onToast('error', err instanceof Error ? err.message : 'Save failed');
    } finally {
      setSaving(false);
    }
  }

  // Same saveMeteringField pattern AdvancedTab.tsx uses for meter_cycle_days/funnel_thresholds —
  // meter_scope_mode is a subscription-service field, saved immediately on change rather than
  // batched into the WP-local "Save changes" button below.
  async function saveMeteringField(key: keyof ConnectorSettings, value: unknown): Promise<void> {
    setSavingMetering(true);
    try {
      await updateConnectorSetting(key, value);
      setConnectorSettings({ ...connectorSettings, [key]: value as never });
      onToast('success', 'Gating mode saved.');
    } catch (err) {
      onToast('error', err instanceof Error ? err.message : 'Save failed');
    } finally {
      setSavingMetering(false);
    }
  }

  const categoryTaxonomy = boot.taxonomies.find((t) => t.slug === 'category');
  const premiumCategoryIds = settings.aero_paywall_premium_terms.category;
  const restrictedPostTypes =
    settings.aero_paywall_restricted_post_types.length > 0 ? settings.aero_paywall_restricted_post_types : ['post'];

  return (
    <div className="aero-tab">
      <Card title="Master switch" description="Off = the site behaves exactly as it did before this plugin — no gating, no SDK. This is the rollback control.">
        <Toggle
          label="AeroPaywall enabled"
          checked={settings.aero_paywall_enabled}
          onChange={(v) => patchSettings({ aero_paywall_enabled: v })}
        />
      </Card>

      <Card
        title="Gating mode"
        description="How AeroPaywall decides what counts toward a reader's free-article limit, and what actually gets locked once they hit it. Enforced by the Subscription Service — changes here apply immediately across web and mobile."
      >
        <GatingModeSelector
          value={connectorSettings.meter_scope_mode}
          onChange={(v) => saveMeteringField('meter_scope_mode', v)}
          disabled={savingMetering}
        />
      </Card>

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
          description="Any post assigned to a checked category is classified premium — the everyday way to make a whole section subscriber-only without touching individual posts or writing a rule below. A post's own “Force premium”/“Force free” override always wins over this."
        >
          <TermPicker
            taxonomy={categoryTaxonomy}
            selected={premiumCategoryIds}
            onChange={(ids) => patchSettings({ aero_paywall_premium_terms: { category: ids } })}
          />
        </Card>
      )}

      {categoryTaxonomy && (
        <Card
          title="Category limits"
          description="A quicker way to set a per-category free-read cap without opening the full rule editor below. This is the same rule list the Advanced editor manages — the two always stay in sync."
        >
          <CategoryOverrideTable taxonomy={categoryTaxonomy} restrictedPostTypes={restrictedPostTypes} rules={rules} setRules={setRules} />
        </Card>
      )}

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

      <details className="aero-advanced">
        <summary className="aero-advanced__summary">Advanced</summary>
        <div className="aero-advanced__body">
          <Card
            title="Content restrictions"
            description="Set who can read what, and how much is free. Restrictions are processed top to bottom — a post is governed by the first matching rule. The power-user tool the Category limits table above is a friendlier alternative to, not a replacement for."
          >
            <RuleEditor rules={rules} onChange={setRules} postTypes={boot.postTypes} taxonomies={boot.taxonomies} />
          </Card>

          <Card title="Bypass restrictions" description="These roles always see full content, regardless of any restriction above.">
            <div className="aero-checkbox-grid">
              {Object.entries(boot.roles).map(([slug, name]) => (
                <label key={slug} className="aero-checkbox-grid__item">
                  <input type="checkbox" checked={settings.aero_paywall_bypass_roles.includes(slug)} onChange={() => toggleBypassRole(slug)} />
                  {name}
                </label>
              ))}
            </div>
          </Card>

          <Card title="Metering behavior" description="Enforced by the Subscription Service, not stored in WordPress — changes here apply immediately across web and mobile.">
            <Toggle
              label="Combined restrictions"
              checked={connectorSettings.restrictions_combine_mode ?? false}
              onChange={(v) => saveMeteringField('restrictions_combine_mode', v)}
              description="Use a single free-article count across every restriction rule, instead of a separate count per rule."
            />
            <Toggle
              label="IP address fallback"
              checked={connectorSettings.meter_ip_fallback_enabled ?? false}
              onChange={(v) => saveMeteringField('meter_ip_fallback_enabled', v)}
              description="Also count reads by IP address, so clearing cookies alone doesn't reset a reader's free-article count. Off by default — a shared office/campus IP would otherwise share one allowance."
            />
          </Card>

          <Card title="Private browsing">
            <SegmentedControl
              label="Private-browsing guard"
              value={settings.aero_paywall_private_mode_enforcement}
              onChange={(v) => patchSettings({ aero_paywall_private_mode_enforcement: v as Settings['aero_paywall_private_mode_enforcement'] })}
              options={[
                { value: 'off', label: 'Off' },
                { value: 'soft', label: 'Soft prompt' },
                { value: 'hard', label: 'Hard block' },
              ]}
            />
          </Card>
        </div>
      </details>

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
  taxonomies: TaxonomyOption[];
  onChange: (value: Record<string, number[]>) => void;
}): JSX.Element {
  return (
    <div className="aero-exceptions">
      {taxonomies.map((tax) => (
        <div key={tax.slug} className="aero-exceptions__group">
          <TermPicker
            taxonomy={tax}
            selected={value[tax.slug] ?? []}
            onChange={(ids) => onChange({ ...value, [tax.slug]: ids })}
          />
        </div>
      ))}
    </div>
  );
}

function newOverrideRuleId(): string {
  return 'rule-' + Math.random().toString(36).slice(2, 10);
}

type CategoryAccessState = 'free' | 'metered' | 'locked';

/**
 * A friendlier, per-category view over the exact same `rules` array RuleEditor manages below —
 * not a separate data model or API call. It only recognizes/creates single-purpose rules
 * (taxonomy: 'category', that one term id in term_ids); a rule with number_allowed: null
 * (classification-only, no cap) reads as Free here, since this simplified control has no way to
 * express "counts but never locks" — that combination stays editable only via the Advanced
 * RuleEditor above, per the implementation brief.
 */
function CategoryOverrideTable({
  taxonomy,
  restrictedPostTypes,
  rules,
  setRules,
}: {
  taxonomy: TaxonomyOption;
  restrictedPostTypes: string[];
  rules: RestrictionRule[];
  setRules: (rules: RestrictionRule[]) => void;
}): JSX.Element {
  const defaultPostType = restrictedPostTypes[0] ?? 'post';

  function findRule(termId: number): RestrictionRule | undefined {
    return rules.find(
      (r) => r.taxonomy === 'category' && restrictedPostTypes.includes(r.post_type) && r.term_ids.includes(termId)
    );
  }

  function stateFor(rule: RestrictionRule | undefined): CategoryAccessState {
    if (!rule || rule.number_allowed === null) return 'free';
    return rule.number_allowed === 0 ? 'locked' : 'metered';
  }

  function setFree(termId: number): void {
    const rule = findRule(termId);
    if (!rule) return;
    setRules(rules.filter((r) => r.id !== rule.id));
  }

  function upsert(termId: number, numberAllowed: number): void {
    const rule = findRule(termId);
    if (rule) {
      setRules(rules.map((r) => (r.id === rule.id ? { ...r, number_allowed: numberAllowed } : r)));
    } else {
      setRules([
        ...rules,
        {
          id: newOverrideRuleId(),
          post_type: defaultPostType,
          taxonomy: 'category',
          term_ids: [termId],
          number_allowed: numberAllowed,
          period_days: null,
          require_registration: false,
        },
      ]);
    }
  }

  return (
    <div className="aero-category-overrides" role="table">
      <div className="aero-category-overrides__row aero-category-overrides__row--head" role="row">
        <span role="columnheader">Category</span>
        <span role="columnheader">Access</span>
      </div>
      {taxonomy.terms.length === 0 && <p className="aero-rules__empty">No categories on this site yet.</p>}
      {taxonomy.terms.map((term) => {
        const rule = findRule(term.id);
        const state = stateFor(rule);
        const meteredCount = rule?.number_allowed && rule.number_allowed > 0 ? rule.number_allowed : 3;
        const groupName = `aero-cat-access-${term.id}`;

        return (
          <div key={term.id} className="aero-category-overrides__row" role="row">
            <span className="aero-category-overrides__name" role="cell">
              {term.name}
            </span>
            <span className="aero-category-overrides__control" role="radiogroup" aria-label={`Access for ${term.name}`}>
              <label className="aero-category-overrides__option">
                <input type="radio" name={groupName} checked={state === 'free'} onChange={() => setFree(term.id)} />
                Free
              </label>
              <label className="aero-category-overrides__option">
                <input
                  type="radio"
                  name={groupName}
                  checked={state === 'metered'}
                  onChange={() => upsert(term.id, meteredCount)}
                />
                Metered
                {state === 'metered' && (
                  <input
                    type="number"
                    min={1}
                    className="aero-category-overrides__count"
                    value={meteredCount}
                    aria-label={`Free reads allowed for ${term.name}`}
                    onChange={(e) => upsert(term.id, Math.max(1, Number(e.target.value)))}
                  />
                )}
              </label>
              <label className="aero-category-overrides__option">
                <input type="radio" name={groupName} checked={state === 'locked'} onChange={() => upsert(term.id, 0)} />
                Locked
              </label>
            </span>
          </div>
        );
      })}
    </div>
  );
}
