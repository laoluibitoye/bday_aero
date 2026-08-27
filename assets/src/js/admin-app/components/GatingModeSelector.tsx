import { ConnectorSettings } from '../types';

export type GatingMode = NonNullable<ConnectorSettings['meter_scope_mode']>;

interface GatingModeOption {
  value: GatingMode;
  label: string;
  description: string;
}

// Labels and descriptions here must stay byte-identical to admin-web's own
// SCOPE_MODE_OPTIONS (app/dashboard/settings/page.tsx) — reader-audited bug:
// this used to use different display names for the same meter_scope_mode
// value ("Premium Only"/"Metered"/"Full Paywall" here vs "Restricted only"/
// "Hybrid (recommended)"/"Global lock" there), so a WP admin and an
// admin-web staffer describing the same setting to each other were using
// different vocabulary for it. hard_wall has no free-preview state at all —
// SDK/backend treat it as "every restricted article is locked for every
// non-subscriber, no metering."
export const GATING_MODE_OPTIONS: GatingModeOption[] = [
  {
    value: 'restricted_only',
    label: 'Restricted only',
    description: 'Only premium-marked articles count toward the limit and get gated. Free content is never touched.',
  },
  {
    value: 'hybrid',
    label: 'Hybrid (recommended)',
    description: 'Every article a reader views counts toward their limit, but only premium articles ever get gated by it.',
  },
  {
    value: 'global_lock',
    label: 'Global lock',
    description: 'Every article counts and every article is gated once the limit is hit — including free content.',
  },
  {
    value: 'hard_wall',
    label: 'Hard Wall',
    description: 'No free reads at all. Every restricted article is locked immediately for every non-subscriber.',
  },
];

function isGatingMode(value: unknown): value is GatingMode {
  return typeof value === 'string' && GATING_MODE_OPTIONS.some((opt) => opt.value === value);
}

interface Props {
  value: ConnectorSettings['meter_scope_mode'];
  onChange: (value: GatingMode) => void;
  disabled?: boolean;
}

/**
 * Replaces the old two-control setup (WP-local paywall_mode soft/hard +
 * this same meter_scope_mode) with one preset selector — this is the only
 * place a WP admin now chooses how gating behaves. A plain vertical radio
 * list rather than SegmentedControl: that component's own doc comment
 * ("reads better... when there are only 2-3 of them") and its 480px max
 * width don't hold up for 4 options that each need a full sentence of
 * description, so this follows the Card-wrapped-radio-row fallback the
 * implementation brief calls out instead.
 */
export function GatingModeSelector({ value, onChange, disabled }: Props): JSX.Element {
  // Should not normally happen — meter_scope_mode is a closed enum server-side — but a stale
  // deploy or a value written outside this UI (direct API call, older admin-web build) could
  // leave it holding something else. Rather than silently coercing to a default (which would
  // save a mode the admin never chose the moment they touch anything else on this tab) or
  // crashing, show it as a distinct, non-selectable state until they explicitly pick one.
  const isCustom = value !== undefined && !isGatingMode(value);

  return (
    <div className="aero-gating-mode" role="radiogroup" aria-label="Gating mode">
      {GATING_MODE_OPTIONS.map((opt) => (
        <label key={opt.value} className={`aero-gating-mode__option ${value === opt.value ? 'is-selected' : ''}`}>
          <input
            type="radio"
            name="aero-gating-mode"
            checked={value === opt.value}
            onChange={() => onChange(opt.value)}
            disabled={disabled}
          />
          <span>
            <strong className="aero-gating-mode__label">{opt.label}</strong>
            <span className="aero-gating-mode__description">{opt.description}</span>
          </span>
        </label>
      ))}
      {isCustom && (
        <div className="aero-gating-mode__option aero-gating-mode__option--custom" aria-disabled="true">
          <span className="aero-gating-mode__custom-dot" aria-hidden="true" />
          <span>
            <strong className="aero-gating-mode__label">Custom</strong>
            <span className="aero-gating-mode__description">
              Current value ({JSON.stringify(value)}) doesn&apos;t match any mode above — pick one to replace it.
            </span>
          </span>
        </div>
      )}
    </div>
  );
}
