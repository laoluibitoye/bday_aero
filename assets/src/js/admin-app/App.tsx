import { useEffect, useState } from 'react';
import { Settings, RestrictionRule, ConnectorSettings, RequiredPage } from './types';
import { ShieldIcon, DashboardIcon, PlugIcon, LockIcon, PaletteIcon, MessageIcon, SlidersIcon } from './components/Icon';
import { DashboardTab } from './tabs/DashboardTab';
import { WizardTab } from './tabs/WizardTab';
import { ConnectionTab } from './tabs/ConnectionTab';
import { GatingTab } from './tabs/GatingTab';
import { AppearanceTab } from './tabs/AppearanceTab';
import { PaywallCopyTab } from './tabs/PaywallCopyTab';
import { AdvancedTab } from './tabs/AdvancedTab';

type TabId = 'dashboard' | 'wizard' | 'connection' | 'gating' | 'appearance' | 'paywallCopy' | 'advanced';

const NAV_ITEMS: Array<{ id: TabId; label: string; icon: (props: { size?: number }) => JSX.Element }> = [
  { id: 'dashboard', label: 'Dashboard', icon: DashboardIcon },
  { id: 'connection', label: 'Connection', icon: PlugIcon },
  { id: 'gating', label: 'Gating', icon: LockIcon },
  { id: 'appearance', label: 'Appearance', icon: PaletteIcon },
  { id: 'paywallCopy', label: 'Paywall Copy', icon: MessageIcon },
  { id: 'advanced', label: 'Advanced', icon: SlidersIcon },
];

const TAB_IDS: TabId[] = [...NAV_ITEMS.map((n) => n.id), 'wizard'];

function isTabId(value: string): value is TabId {
  return (TAB_IDS as string[]).includes(value);
}

// The DEV MODE / license-inactive admin notices and the post-edit metabox
// (class-premium-map.php) link straight into a specific tab via
// admin.php?page=aero-paywall#connection — this reads that on first paint
// so those links actually land where they say they do. A first-run site
// (setup not complete) still opens on the Wizard; everyone else lands on
// the new Dashboard overview rather than jumping straight into a form.
function getInitialTab(setupComplete: boolean): TabId {
  const hash = window.location.hash.replace('#', '');
  if (isTabId(hash)) {
    return hash;
  }
  return setupComplete ? 'dashboard' : 'wizard';
}

export function App(): JSX.Element {
  const boot = window.aeroPaywallAdmin;
  const [activeTab, setActiveTabState] = useState<TabId>(() => getInitialTab(boot.setupComplete));
  const [settings, setSettings] = useState<Settings>(boot.settings);
  // Last-successfully-saved snapshot, kept separate from the live-edited
  // `settings` above — this is what makes "unsaved changes" detectable at
  // all. Every tab's Save button now writes the *entire* settings object
  // (see each tab's handleSave), not just its own subset, specifically so
  // this snapshot and the live state agree right after any save,
  // regardless of which tab performed it.
  const [savedSettings, setSavedSettings] = useState<Settings>(boot.settings);
  const [rules, setRules] = useState<RestrictionRule[]>(boot.restrictionRules);
  const [connectorSettings, setConnectorSettings] = useState<ConnectorSettings>(boot.connectorSettings);
  const [requiredPages, setRequiredPages] = useState<RequiredPage[]>(boot.requiredPages);
  const [toast, setToast] = useState<{ kind: 'success' | 'error'; message: string } | null>(null);

  const isDirty = JSON.stringify(settings) !== JSON.stringify(savedSettings);

  useEffect(() => {
    if (!isDirty) {
      return;
    }
    function handleBeforeUnload(e: BeforeUnloadEvent): void {
      e.preventDefault();
      e.returnValue = '';
    }
    window.addEventListener('beforeunload', handleBeforeUnload);
    return () => window.removeEventListener('beforeunload', handleBeforeUnload);
  }, [isDirty]);

  function setActiveTab(tab: TabId): void {
    setActiveTabState(tab);
    window.history.replaceState(null, '', '#' + tab);
  }

  function showToast(kind: 'success' | 'error', message: string): void {
    setToast({ kind, message });
    window.setTimeout(() => setToast(null), 4000);
  }

  function patchSettings(patch: Partial<Settings>): void {
    setSettings((prev) => ({ ...prev, ...patch }));
  }

  // Called by every tab right after its own saveSettings() call succeeds —
  // marks the just-saved fields (usually the whole object; a few flows,
  // like Wizard's Finish, add a couple of extra flags on top) as no longer
  // dirty relative to the live `settings` state.
  function markSaved(saved: Partial<Settings>): void {
    setSavedSettings((prev) => ({ ...prev, ...saved }));
    setSettings((prev) => ({ ...prev, ...saved }));
  }

  const activeNavItem = NAV_ITEMS.find((n) => n.id === activeTab);

  return (
    <div className="aero-app">
      <aside className="aero-sidebar">
        <div className="aero-sidebar__brand">
          <span className="aero-sidebar__logo" aria-hidden="true">
            <ShieldIcon size={22} />
          </span>
          <span className="aero-sidebar__title">AeroPaywall</span>
        </div>
        <nav className="aero-sidebar__nav" role="tablist" aria-label="AeroPaywall settings sections">
          {NAV_ITEMS.map(({ id, label, icon: ItemIcon }) => (
            <button
              key={id}
              type="button"
              role="tab"
              aria-selected={activeTab === id}
              className={`aero-sidebar__item ${activeTab === id ? 'is-active' : ''}`}
              onClick={() => setActiveTab(id)}
            >
              <ItemIcon />
              {label}
            </button>
          ))}
        </nav>
        <div className="aero-sidebar__footer">
          <span className={`aero-sidebar__status-dot ${boot.licenseActive ? 'is-ok' : 'is-bad'}`} aria-hidden="true" />
          {boot.licenseActive ? 'Licensed' : boot.devModeBypass ? 'Dev mode' : 'Not licensed'}
        </div>
      </aside>

      <div className="aero-main">
        <header className="aero-topbar">
          <h1>{activeTab === 'wizard' ? 'Setup Wizard' : activeNavItem?.label ?? 'AeroPaywall'}</h1>
          {isDirty && (
            <span className="aero-dirty-indicator" role="status">
              Unsaved changes
            </span>
          )}
        </header>

        {toast && <div className={`aero-toast aero-toast--${toast.kind}`}>{toast.message}</div>}

        <main className="aero-app__body">
          {activeTab === 'dashboard' && (
            <DashboardTab
              settings={settings}
              requiredPages={requiredPages}
              setRequiredPages={setRequiredPages}
              onToast={showToast}
              onSaved={markSaved}
              onNavigate={(tab) => setActiveTab(tab as TabId)}
            />
          )}
          {activeTab === 'wizard' && (
            <WizardTab
              settings={settings}
              patchSettings={patchSettings}
              rules={rules}
              setRules={setRules}
              onFinish={() => setActiveTab('dashboard')}
              onToast={showToast}
              onSaved={markSaved}
            />
          )}
          {activeTab === 'connection' && (
            <ConnectionTab settings={settings} patchSettings={patchSettings} onToast={showToast} onSaved={markSaved} />
          )}
          {activeTab === 'gating' && (
            <GatingTab
              settings={settings}
              patchSettings={patchSettings}
              rules={rules}
              setRules={setRules}
              connectorSettings={connectorSettings}
              setConnectorSettings={setConnectorSettings}
              onToast={showToast}
              onSaved={markSaved}
            />
          )}
          {activeTab === 'appearance' && (
            <AppearanceTab settings={settings} patchSettings={patchSettings} onToast={showToast} onSaved={markSaved} />
          )}
          {activeTab === 'paywallCopy' && (
            <PaywallCopyTab settings={settings} patchSettings={patchSettings} onToast={showToast} onSaved={markSaved} />
          )}
          {activeTab === 'advanced' && (
            <AdvancedTab
              settings={settings}
              patchSettings={patchSettings}
              connectorSettings={connectorSettings}
              setConnectorSettings={setConnectorSettings}
              onToast={showToast}
              onGoToWizard={() => setActiveTab('wizard')}
              onSaved={markSaved}
            />
          )}
        </main>
      </div>
    </div>
  );
}
