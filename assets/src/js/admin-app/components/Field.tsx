import React from 'react';

export function Card({ title, description, children }: { title: string; description?: string; children: React.ReactNode }): JSX.Element {
  return (
    <section className="aero-card">
      <div className="aero-card__header">
        <h2>{title}</h2>
        {description && <p className="aero-card__description">{description}</p>}
      </div>
      <div className="aero-card__body">{children}</div>
    </section>
  );
}

interface TextFieldProps {
  label: string;
  value: string;
  onChange: (value: string) => void;
  placeholder?: string;
  type?: 'text' | 'password' | 'url';
  description?: React.ReactNode;
  /** Shown below the field in the danger color; non-blocking — the field
   *  stays editable and saveable even while an error is showing, so a
   *  legitimately unusual value (e.g. a local dev URL) is never locked out. */
  error?: string | null;
}

export function TextField({ label, value, onChange, placeholder, type = 'text', description, error }: TextFieldProps): JSX.Element {
  return (
    <label className="aero-field">
      <span className="aero-field__label">{label}</span>
      <input
        className={`aero-field__input ${error ? 'aero-field__input--error' : ''}`}
        type={type}
        value={value}
        placeholder={placeholder}
        aria-invalid={error ? true : undefined}
        onChange={(e) => onChange(e.target.value)}
      />
      {error && <span className="aero-field__error">{error}</span>}
      {description && <span className="aero-field__description">{description}</span>}
    </label>
  );
}

interface TextAreaFieldProps {
  label: string;
  value: string;
  onChange: (value: string) => void;
  placeholder?: string;
  description?: React.ReactNode;
  rows?: number;
}

export function TextAreaField({ label, value, onChange, placeholder, description, rows = 2 }: TextAreaFieldProps): JSX.Element {
  return (
    <label className="aero-field">
      <span className="aero-field__label">{label}</span>
      <textarea
        className="aero-field__input"
        value={value}
        placeholder={placeholder}
        rows={rows}
        onChange={(e) => onChange(e.target.value)}
      />
      {description && <span className="aero-field__description">{description}</span>}
    </label>
  );
}

interface NumberFieldProps {
  label: string;
  value: number;
  onChange: (value: number) => void;
  min?: number;
  description?: string;
}

export function NumberField({ label, value, onChange, min = 0, description }: NumberFieldProps): JSX.Element {
  return (
    <label className="aero-field">
      <span className="aero-field__label">{label}</span>
      <input
        className="aero-field__input aero-field__input--number"
        type="number"
        min={min}
        value={value}
        onChange={(e) => onChange(Number(e.target.value))}
      />
      {description && <span className="aero-field__description">{description}</span>}
    </label>
  );
}

interface ToggleProps {
  label: string;
  checked: boolean;
  onChange: (value: boolean) => void;
  description?: string;
}

export function Toggle({ label, checked, onChange, description }: ToggleProps): JSX.Element {
  return (
    <label className="aero-toggle">
      <span className="aero-switch">
        <input type="checkbox" checked={checked} onChange={(e) => onChange(e.target.checked)} />
        <span className="aero-switch__track" />
      </span>
      <span>
        <span className="aero-toggle__label">{label}</span>
        {description && <span className="aero-field__description">{description}</span>}
      </span>
    </label>
  );
}

interface SelectFieldProps {
  label: string;
  value: string;
  onChange: (value: string) => void;
  options: { value: string; label: string }[];
  description?: string;
}

export function SelectField({ label, value, onChange, options, description }: SelectFieldProps): JSX.Element {
  return (
    <label className="aero-field">
      <span className="aero-field__label">{label}</span>
      <select className="aero-field__input" value={value} onChange={(e) => onChange(e.target.value)}>
        {options.map((opt) => (
          <option key={opt.value} value={opt.value}>
            {opt.label}
          </option>
        ))}
      </select>
      {description && <span className="aero-field__description">{description}</span>}
    </label>
  );
}

export function Button({
  children,
  onClick,
  variant = 'primary',
  disabled,
  loading,
  type = 'button',
}: {
  children: React.ReactNode;
  onClick?: () => void;
  variant?: 'primary' | 'secondary' | 'danger' | 'ghost';
  disabled?: boolean;
  /** Shows a spinner alongside the label without changing the button's
   *  text/width the way swapping to "Saving…" text alone does. */
  loading?: boolean;
  type?: 'button' | 'submit';
}): JSX.Element {
  return (
    <button type={type} className={`aero-btn aero-btn--${variant}`} onClick={onClick} disabled={disabled || loading}>
      {loading && <Spinner />}
      {children}
    </button>
  );
}

export function Spinner(): JSX.Element {
  return <span className="aero-spinner" role="status" aria-label="Loading" />;
}

interface SegmentedControlProps {
  label: string;
  value: string;
  onChange: (value: string) => void;
  options: { value: string; label: string; description?: string }[];
}

/**
 * The same visual language as the post-edit metabox's inherit/premium/free
 * choice (class-premium-map.php's render_metabox()) — a short, mutually-
 * exclusive set of options reads better as a segmented control than a
 * dropdown when there are only 2-3 of them.
 */
export function SegmentedControl({ label, value, onChange, options }: SegmentedControlProps): JSX.Element {
  return (
    <div className="aero-field">
      <span className="aero-field__label">{label}</span>
      <div className="aero-segmented" role="radiogroup" aria-label={label}>
        {options.map((opt) => (
          <div key={opt.value} className="aero-segmented__option">
            <input
              type="radio"
              id={`aero-segmented-${label}-${opt.value}`}
              checked={value === opt.value}
              onChange={() => onChange(opt.value)}
            />
            <label className="aero-segmented__label" htmlFor={`aero-segmented-${label}-${opt.value}`}>
              <strong>{opt.label}</strong>
              {opt.description && <span className="aero-segmented__help">{opt.description}</span>}
            </label>
          </div>
        ))}
      </div>
    </div>
  );
}

interface ConfirmDialogProps {
  title: string;
  message: React.ReactNode;
  confirmLabel?: string;
  onConfirm: () => void;
  onCancel: () => void;
}

/**
 * A restriction rule (RuleEditor.tsx) used to be removed permanently on a
 * single click, with no undo — this is the confirmation step that was
 * missing before any destructive action in the settings app.
 */
export function ConfirmDialog({ title, message, confirmLabel = 'Remove', onConfirm, onCancel }: ConfirmDialogProps): JSX.Element {
  return (
    <div className="aero-modal-overlay" role="presentation" onClick={onCancel}>
      <div
        className="aero-modal"
        role="alertdialog"
        aria-modal="true"
        aria-labelledby="aero-modal-title"
        onClick={(e) => e.stopPropagation()}
      >
        <h3 id="aero-modal-title">{title}</h3>
        <div className="aero-modal__body">{message}</div>
        <div className="aero-modal__actions">
          <Button variant="ghost" onClick={onCancel}>
            Cancel
          </Button>
          <Button variant="danger" onClick={onConfirm}>
            {confirmLabel}
          </Button>
        </div>
      </div>
    </div>
  );
}
