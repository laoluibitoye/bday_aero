/**
 * Same shield mark as the wp-admin menu icon (class-admin-ui.php's
 * menu_icon_data_uri()) — one brand identity instead of a dashicon in the
 * sidebar and an unrelated emoji here. Colored via `currentColor` so it
 * picks up whatever text/accent color its container sets, rather than a
 * hardcoded fill baked into a raster/emoji glyph.
 */
export function ShieldIcon({ size = 24 }: { size?: number }): JSX.Element {
  return (
    <svg width={size} height={size} viewBox="0 0 24 24" fill="none" aria-hidden="true">
      <path d="M12 2 4 5v6c0 5.25 3.4 9.5 8 11 4.6-1.5 8-5.75 8-11V5l-8-3z" fill="currentColor" />
      <path
        d="M9 12.3l2 2 4-4.6"
        stroke="#fff"
        strokeWidth={1.6}
        strokeLinecap="round"
        strokeLinejoin="round"
        fill="none"
      />
    </svg>
  );
}

// One consistent, hand-drawn icon set for the sidebar nav (Foxiz/premium-
// theme-panel convention: every nav item gets its own glyph, not just the
// brand mark) — plain stroked SVGs, no icon-font/library dependency, sized
// and colored via props/currentColor exactly like ShieldIcon above.
type IconProps = { size?: number };
const base = { fill: 'none', stroke: 'currentColor', strokeWidth: 1.7, strokeLinecap: 'round' as const, strokeLinejoin: 'round' as const };

export function DashboardIcon({ size = 18 }: IconProps): JSX.Element {
  return (
    <svg width={size} height={size} viewBox="0 0 24 24" {...base} aria-hidden="true">
      <rect x="3" y="3" width="7" height="9" rx="1.5" />
      <rect x="14" y="3" width="7" height="5" rx="1.5" />
      <rect x="14" y="12" width="7" height="9" rx="1.5" />
      <rect x="3" y="16" width="7" height="5" rx="1.5" />
    </svg>
  );
}

export function PlugIcon({ size = 18 }: IconProps): JSX.Element {
  return (
    <svg width={size} height={size} viewBox="0 0 24 24" {...base} aria-hidden="true">
      <path d="M9 3v4M15 3v4M7 7h10v3a5 5 0 0 1-5 5 5 5 0 0 1-5-5V7z" />
      <path d="M12 15v3M9 21h6" />
    </svg>
  );
}

export function LockIcon({ size = 18 }: IconProps): JSX.Element {
  return (
    <svg width={size} height={size} viewBox="0 0 24 24" {...base} aria-hidden="true">
      <rect x="4" y="10.5" width="16" height="10" rx="2" />
      <path d="M7.5 10.5V7a4.5 4.5 0 0 1 9 0v3.5" />
    </svg>
  );
}

export function PaletteIcon({ size = 18 }: IconProps): JSX.Element {
  return (
    <svg width={size} height={size} viewBox="0 0 24 24" {...base} aria-hidden="true">
      <path d="M12 3a9 9 0 1 0 0 18c1.1 0 1.8-.9 1.8-2 0-.5-.2-1-.5-1.3-.3-.4-.5-.8-.5-1.3 0-1.1.9-2 2-2h2.1c1.7 0 3.1-1.4 3.1-3.1C20 6.4 16.4 3 12 3z" />
      <circle cx="7.5" cy="11" r="1.2" fill="currentColor" stroke="none" />
      <circle cx="10.5" cy="7.5" r="1.2" fill="currentColor" stroke="none" />
      <circle cx="15" cy="8" r="1.2" fill="currentColor" stroke="none" />
    </svg>
  );
}

export function MessageIcon({ size = 18 }: IconProps): JSX.Element {
  return (
    <svg width={size} height={size} viewBox="0 0 24 24" {...base} aria-hidden="true">
      <path d="M4 5.5h16v11H9l-4 3.5v-3.5H4v-11z" />
      <path d="M8 10h8M8 13.5h5" />
    </svg>
  );
}

export function SlidersIcon({ size = 18 }: IconProps): JSX.Element {
  return (
    <svg width={size} height={size} viewBox="0 0 24 24" {...base} aria-hidden="true">
      <path d="M4 6h9M17 6h3M4 12h3M9 12h11M4 18h13M21 18h-1" />
      <circle cx="13" cy="6" r="2" fill="var(--aero-card-bg)" />
      <circle cx="6" cy="12" r="2" fill="var(--aero-card-bg)" />
      <circle cx="17" cy="18" r="2" fill="var(--aero-card-bg)" />
    </svg>
  );
}

export function FileTextIcon({ size = 18 }: IconProps): JSX.Element {
  return (
    <svg width={size} height={size} viewBox="0 0 24 24" {...base} aria-hidden="true">
      <path d="M6 3.5h8l4 4V20a1 1 0 0 1-1 1H6a1 1 0 0 1-1-1V4.5a1 1 0 0 1 1-1z" />
      <path d="M14 3.5V8h4" />
      <path d="M8.5 12.5h7M8.5 15.5h7M8.5 18h4" />
    </svg>
  );
}

export function CheckCircleIcon({ size = 16 }: IconProps): JSX.Element {
  return (
    <svg width={size} height={size} viewBox="0 0 24 24" {...base} aria-hidden="true">
      <circle cx="12" cy="12" r="9" />
      <path d="M8 12.3l2.5 2.5L16 9.3" />
    </svg>
  );
}

export function AlertCircleIcon({ size = 16 }: IconProps): JSX.Element {
  return (
    <svg width={size} height={size} viewBox="0 0 24 24" {...base} aria-hidden="true">
      <circle cx="12" cy="12" r="9" />
      <path d="M12 8v5" />
      <circle cx="12" cy="16" r="0.9" fill="currentColor" stroke="none" />
    </svg>
  );
}

export function ArrowRightIcon({ size = 14 }: IconProps): JSX.Element {
  return (
    <svg width={size} height={size} viewBox="0 0 24 24" {...base} aria-hidden="true">
      <path d="M5 12h14M13 6l6 6-6 6" />
    </svg>
  );
}

export function UsersIcon({ size = 20 }: IconProps): JSX.Element {
  return (
    <svg width={size} height={size} viewBox="0 0 24 24" {...base} aria-hidden="true">
      <circle cx="9" cy="8" r="3.2" />
      <path d="M3 20c0-3.3 2.7-6 6-6s6 2.7 6 6" />
      <path d="M16 4.3a3.2 3.2 0 0 1 0 6.2M21 20c0-2.8-2-5.2-4.7-5.9" />
    </svg>
  );
}

export function TagIcon({ size = 20 }: IconProps): JSX.Element {
  return (
    <svg width={size} height={size} viewBox="0 0 24 24" {...base} aria-hidden="true">
      <path d="M11.5 3.5H5A1.5 1.5 0 0 0 3.5 5v6.5c0 .4.16.78.44 1.06l9.5 9.5a1.5 1.5 0 0 0 2.12 0l6.5-6.5a1.5 1.5 0 0 0 0-2.12l-9.5-9.5a1.5 1.5 0 0 0-1.06-.44z" />
      <circle cx="8" cy="8" r="1.3" fill="currentColor" stroke="none" />
    </svg>
  );
}
