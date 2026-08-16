import { useState } from 'react';
import { Settings, RequiredPage } from '../types';
import { Card, Button } from '../components/Field';
import { CheckCircleIcon, AlertCircleIcon, ArrowRightIcon, UsersIcon, TagIcon } from '../components/Icon';
import { createRequiredPages } from '../api';

interface Props {
  settings: Settings;
  requiredPages: RequiredPage[];
  setRequiredPages: (pages: RequiredPage[]) => void;
  onToast: (kind: 'success' | 'error', message: string) => void;
  onSaved: (saved: Partial<Settings>) => void;
  onNavigate: (tab: string) => void;
}

/**
 * Reader-requested: a proper landing overview instead of dropping an
 * admin straight into a settings form — the "is everything actually
 * working" snapshot a premium theme's options panel opens on (connection/
 * license status, reader stats, and a one-click fix for the single most
 * common first-run gap: the required pages not existing yet).
 */
export function DashboardTab({ settings, requiredPages, setRequiredPages, onToast, onSaved, onNavigate }: Props): JSX.Element {
  const [creatingPages, setCreatingPages] = useState(false);
  const boot = window.aeroPaywallAdmin;
  const stats = boot.dashboardStats;

  const connectionOk = settings.aero_paywall_api_base_url !== '' && settings.aero_paywall_api_key !== '';
  const licenseOk = boot.licenseActive;
  const missingPages = requiredPages.filter((p) => !p.exists);

  async function handleCreatePages(): Promise<void> {
    setCreatingPages(true);
    try {
      const result = await createRequiredPages();
      setRequiredPages(result.pages);
      if (result.settings) {
        onSaved(result.settings);
      }
      onToast('success', missingPages.length === 1 ? 'Page created.' : 'Pages created.');
    } catch (err) {
      onToast('error', err instanceof Error ? err.message : 'Could not create pages');
    } finally {
      setCreatingPages(false);
    }
  }

  return (
    <div className="aero-tab">
      <div className="aero-status-grid">
        <StatusCard
          ok={settings.aero_paywall_enabled}
          okLabel="Paywall enabled"
          badLabel="Paywall disabled"
          detail={settings.aero_paywall_enabled ? 'Gating content site-wide' : 'Every reader sees full content right now'}
          onClick={() => onNavigate('restrictions')}
        />
        <StatusCard
          ok={connectionOk}
          okLabel="Connected"
          badLabel="Not connected"
          detail={connectionOk ? settings.aero_paywall_api_base_url : 'Set a Subscription Service base URL + API key'}
          onClick={() => onNavigate('connection')}
        />
        <StatusCard
          ok={licenseOk}
          okLabel="Licensed"
          badLabel="Not licensed"
          detail={licenseOk ? 'AeroPaywall is active on this site' : 'Gating and the reader SDK stay off until this is resolved'}
          onClick={() => onNavigate('connection')}
        />
      </div>

      <div className="aero-dashboard-cols">
        <Card title="Readers" description="A live snapshot from the Subscription Service — refreshed roughly every minute.">
          <div className="aero-stat-row">
            <div className="aero-stat">
              <span className="aero-stat__icon"><UsersIcon /></span>
              <span className="aero-stat__value">{stats.totalReaders.toLocaleString()}</span>
              <span className="aero-stat__label">Total readers</span>
            </div>
            <div className="aero-stat">
              <span className="aero-stat__icon"><CheckCircleIcon size={20} /></span>
              <span className="aero-stat__value">{stats.activeSubscribers.toLocaleString()}</span>
              <span className="aero-stat__label">Active subscribers</span>
            </div>
            <div className="aero-stat">
              <span className="aero-stat__icon"><TagIcon /></span>
              <span className="aero-stat__value">{stats.totalFollows.toLocaleString()}</span>
              <span className="aero-stat__label">Topic follows</span>
            </div>
          </div>

          {stats.topFollowedTerms.length > 0 && (
            <>
              <h4 className="aero-dashboard-subheading">Most-followed categories &amp; tags</h4>
              <ul className="aero-term-list">
                {stats.topFollowedTerms.map((term) => (
                  <li key={term.taxonomy + term.termLabel}>
                    <span>{term.termLabel}</span>
                    <span className="aero-term-list__count">{term.followerCount.toLocaleString()}</span>
                  </li>
                ))}
              </ul>
            </>
          )}
        </Card>

        <Card
          title="Required pages"
          description="Pages the reader-facing SDK depends on to work at all — sign-in, subscribe, and the paywall itself all link here."
        >
          <ul className="aero-page-checklist">
            {requiredPages.map((page) => (
              <li key={page.key} className={page.exists ? 'is-done' : ''}>
                <span className="aero-page-checklist__icon">
                  {page.exists ? <CheckCircleIcon /> : <AlertCircleIcon />}
                </span>
                <span className="aero-page-checklist__label">
                  {page.label}
                  {page.exists && page.url && (
                    <a href={page.url} target="_blank" rel="noopener noreferrer" className="aero-page-checklist__link">
                      view <ArrowRightIcon size={11} />
                    </a>
                  )}
                </span>
              </li>
            ))}
          </ul>
          {missingPages.length > 0 ? (
            <Button onClick={handleCreatePages} loading={creatingPages}>
              Create {missingPages.length === requiredPages.length ? 'required pages' : `${missingPages.length} missing page${missingPages.length === 1 ? '' : 's'}`}
            </Button>
          ) : (
            <p className="aero-field__description aero-all-set">
              <CheckCircleIcon size={14} /> All required pages exist.
            </p>
          )}
        </Card>
      </div>
    </div>
  );
}

function StatusCard({
  ok,
  okLabel,
  badLabel,
  detail,
  onClick,
}: {
  ok: boolean;
  okLabel: string;
  badLabel: string;
  detail: string;
  onClick: () => void;
}): JSX.Element {
  return (
    <button type="button" className={`aero-status-card ${ok ? 'is-ok' : 'is-bad'}`} onClick={onClick}>
      <span className="aero-status-card__icon">{ok ? <CheckCircleIcon /> : <AlertCircleIcon />}</span>
      <span className="aero-status-card__body">
        <span className="aero-status-card__label">{ok ? okLabel : badLabel}</span>
        <span className="aero-status-card__detail">{detail}</span>
      </span>
      <ArrowRightIcon />
    </button>
  );
}
