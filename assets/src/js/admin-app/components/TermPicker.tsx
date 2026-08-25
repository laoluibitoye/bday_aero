import { useMemo, useState } from 'react';
import { TaxonomyOption } from '../types';

// WordPress's own taxonomy labels ("Categories", "Tags") already say what they are, but in a
// dense settings screen that wording is easy to skim past — this badge is the same information
// restated as a small, consistently-colored tag so "is this a category or a tag" reads at a
// glance rather than requiring the admin to read the heading. Any custom taxonomy just falls
// back to its own label, unbadged.
const KNOWN_TAXONOMY_KIND: Record<string, string> = {
  category: 'Category',
  post_tag: 'Tag',
};

// Rendering every checkbox for a taxonomy with hundreds/thousands of terms (unbounded per
// class-restrictions-picker.php — see its own docblock) made this list the single longest,
// most cumbersome part of the Restrictions screen. Capping the *unfiltered* list keeps the
// initial render cheap; typing a search always shows every match regardless of this cap, and
// anything already selected always shows in the side panel below regardless of the cap or the
// current search text.
const UNFILTERED_VISIBLE_CAP = 150;

interface TermPickerProps {
  taxonomy: TaxonomyOption;
  selected: number[];
  onChange: (ids: number[]) => void;
  /** Stacks the selected list below the search results instead of beside them — for the
   *  narrow per-row taxonomy cell in RuleEditor, where there isn't room for two columns. */
  compact?: boolean;
}

export function TermPicker({ taxonomy, selected, onChange, compact = false }: TermPickerProps): JSX.Element {
  const [query, setQuery] = useState('');

  const filtered = useMemo(() => {
    const q = query.trim().toLowerCase();
    if (!q) return taxonomy.terms;
    return taxonomy.terms.filter((term) => term.name.toLowerCase().includes(q));
  }, [taxonomy.terms, query]);

  const isCapped = query.trim() === '' && filtered.length > UNFILTERED_VISIBLE_CAP;
  const visible = isCapped ? filtered.slice(0, UNFILTERED_VISIBLE_CAP) : filtered;

  const selectedTerms = useMemo(
    () => taxonomy.terms.filter((term) => selected.includes(term.id)),
    [taxonomy.terms, selected]
  );

  function toggle(id: number): void {
    onChange(selected.includes(id) ? selected.filter((existing) => existing !== id) : [...selected, id]);
  }

  function remove(id: number): void {
    onChange(selected.filter((existing) => existing !== id));
  }

  function selectAllVisible(): void {
    const next = new Set(selected);
    for (const term of visible) next.add(term.id);
    onChange(Array.from(next));
  }

  function clearAll(): void {
    onChange([]);
  }

  const kind = KNOWN_TAXONOMY_KIND[taxonomy.slug];
  const nounLower = taxonomy.label.toLowerCase();

  return (
    <div className={`aero-term-picker${compact ? ' aero-term-picker--compact' : ''}`}>
      <div className="aero-term-picker__header">
        {kind && <span className={`aero-term-picker__badge aero-term-picker__badge--${taxonomy.slug}`}>{kind}</span>}
        <h4>{taxonomy.label}</h4>
        <span className="aero-term-picker__count">
          {selected.length} of {taxonomy.terms.length} selected
        </span>
      </div>

      <div className="aero-term-picker__body">
        <div className="aero-term-picker__list-col">
          <input
            type="search"
            className="aero-term-picker__search"
            placeholder={`Search ${nounLower}…`}
            value={query}
            onChange={(e) => setQuery(e.target.value)}
          />
          <div className="aero-term-picker__actions">
            <button type="button" className="aero-term-picker__action" onClick={selectAllVisible} disabled={visible.length === 0}>
              Select {query.trim() ? 'matching' : 'visible'}
            </button>
            <button type="button" className="aero-term-picker__action" onClick={clearAll} disabled={selected.length === 0}>
              Clear all
            </button>
          </div>
          <div className="aero-term-picker__list" role="listbox" aria-multiselectable="true" aria-label={taxonomy.label}>
            {visible.length === 0 ? (
              <p className="aero-term-picker__empty">No matching {nounLower}.</p>
            ) : (
              visible.map((term) => (
                <label key={term.id} className="aero-term-picker__item">
                  <input type="checkbox" checked={selected.includes(term.id)} onChange={() => toggle(term.id)} />
                  {term.name}
                </label>
              ))
            )}
          </div>
          {isCapped && (
            <p className="aero-term-picker__hint">
              Showing first {UNFILTERED_VISIBLE_CAP} of {filtered.length} — search to find more.
            </p>
          )}
        </div>

        <div className="aero-term-picker__selected-col">
          <h5>Selected{selectedTerms.length > 0 ? ` (${selectedTerms.length})` : ''}</h5>
          {selectedTerms.length === 0 ? (
            <p className="aero-term-picker__empty">Nothing selected yet.</p>
          ) : (
            <ul className="aero-term-picker__chips">
              {selectedTerms.map((term) => (
                <li key={term.id} className="aero-term-picker__chip">
                  <span>{term.name}</span>
                  <button type="button" aria-label={`Remove ${term.name}`} onClick={() => remove(term.id)}>
                    ×
                  </button>
                </li>
              ))}
            </ul>
          )}
        </div>
      </div>
    </div>
  );
}
