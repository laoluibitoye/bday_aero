import { useState } from 'react';
import { RestrictionRule, TaxonomyOption, PostTypeOption } from '../types';
import { Button, ConfirmDialog } from './Field';

function newRuleId(): string {
  return 'rule-' + Math.random().toString(36).slice(2, 10);
}

function emptyRule(defaultPostType: string): RestrictionRule {
  return {
    id: newRuleId(),
    post_type: defaultPostType,
    taxonomy: '',
    term_ids: [],
    number_allowed: null,
    period_days: null,
    require_registration: false,
  };
}

interface RuleEditorProps {
  rules: RestrictionRule[];
  onChange: (rules: RestrictionRule[]) => void;
  postTypes: PostTypeOption[];
  taxonomies: TaxonomyOption[];
}

export function RuleEditor({ rules, onChange, postTypes, taxonomies }: RuleEditorProps): JSX.Element {
  const defaultPostType = postTypes[0]?.slug ?? 'post';

  function updateRule(id: string, patch: Partial<RestrictionRule>): void {
    onChange(rules.map((rule) => (rule.id === id ? { ...rule, ...patch } : rule)));
  }

  function removeRule(id: string): void {
    onChange(rules.filter((rule) => rule.id !== id));
  }

  function addRule(): void {
    onChange([...rules, emptyRule(defaultPostType)]);
  }

  return (
    <div className="aero-rules">
      <div className="aero-rules__table" role="table">
        <div className="aero-rules__row aero-rules__row--head" role="row">
          <span role="columnheader">Post type</span>
          <span role="columnheader">Taxonomy / terms</span>
          <span role="columnheader">Number allowed</span>
          <span role="columnheader">Requires registration</span>
          <span role="columnheader" aria-hidden="true" />
        </div>
        {rules.length === 0 && (
          <p className="aero-rules__empty">
            No restriction rules yet — by default all content is free. Add a rule to start gating content.
          </p>
        )}
        {rules.map((rule) => (
          <RuleRow
            key={rule.id}
            rule={rule}
            postTypes={postTypes}
            taxonomies={taxonomies}
            onChange={(patch) => updateRule(rule.id, patch)}
            onRemove={() => removeRule(rule.id)}
          />
        ))}
      </div>
      <Button variant="secondary" onClick={addRule}>
        + Add Restricted Content
      </Button>
      <p className="aero-field__description">By default all content is free. Rules are processed top to bottom — a post is governed by the first rule that matches it.</p>
    </div>
  );
}

function RuleRow({
  rule,
  postTypes,
  taxonomies,
  onChange,
  onRemove,
}: {
  rule: RestrictionRule;
  postTypes: PostTypeOption[];
  taxonomies: TaxonomyOption[];
  onChange: (patch: Partial<RestrictionRule>) => void;
  onRemove: () => void;
}): JSX.Element {
  const [termFilter, setTermFilter] = useState('');
  const [confirmingRemove, setConfirmingRemove] = useState(false);
  const selectedTaxonomy = taxonomies.find((tax) => tax.slug === rule.taxonomy);
  const visibleTerms = selectedTaxonomy
    ? selectedTaxonomy.terms.filter((term) => term.name.toLowerCase().includes(termFilter.toLowerCase()))
    : [];
  const postTypeLabel = postTypes.find((pt) => pt.slug === rule.post_type)?.label ?? rule.post_type;

  function toggleTerm(id: number): void {
    const term_ids = rule.term_ids.includes(id) ? rule.term_ids.filter((t) => t !== id) : [...rule.term_ids, id];
    onChange({ term_ids });
  }

  return (
    <div className="aero-rules__row" role="row">
      <span role="cell">
        <select className="aero-rules__select" value={rule.post_type} onChange={(e) => onChange({ post_type: e.target.value })}>
          {postTypes.map((pt) => (
            <option key={pt.slug} value={pt.slug}>
              {pt.label}
            </option>
          ))}
        </select>
      </span>

      <span role="cell" className="aero-rules__taxonomy-cell">
        <select
          className="aero-rules__select"
          value={rule.taxonomy}
          onChange={(e) => onChange({ taxonomy: e.target.value, term_ids: [] })}
        >
          <option value="">Any (whole post type)</option>
          {taxonomies.map((tax) => (
            <option key={tax.slug} value={tax.slug}>
              {tax.label}
            </option>
          ))}
        </select>
        {selectedTaxonomy && (
          <div className="aero-rules__term-picker">
            <input
              type="search"
              placeholder={`Search ${selectedTaxonomy.label}…`}
              value={termFilter}
              onChange={(e) => setTermFilter(e.target.value)}
            />
            <div className="aero-rules__term-list">
              {visibleTerms.map((term) => (
                <label key={term.id}>
                  <input
                    type="checkbox"
                    checked={rule.term_ids.includes(term.id)}
                    onChange={() => toggleTerm(term.id)}
                  />
                  {term.name}
                </label>
              ))}
            </div>
          </div>
        )}
      </span>

      <span role="cell" className="aero-rules__number-cell">
        <input
          type="number"
          min={1}
          placeholder="Unlimited"
          value={rule.number_allowed ?? ''}
          onChange={(e) => onChange({ number_allowed: e.target.value === '' ? null : Number(e.target.value) })}
        />
        {rule.number_allowed !== null && (
          <input
            type="number"
            min={1}
            className="aero-rules__period-input"
            title="Reset period in days (defaults to the global metering cycle if blank)"
            placeholder="days"
            value={rule.period_days ?? ''}
            onChange={(e) => onChange({ period_days: e.target.value === '' ? null : Number(e.target.value) })}
          />
        )}
      </span>

      <span role="cell">
        <input
          type="checkbox"
          checked={rule.require_registration}
          onChange={(e) => onChange({ require_registration: e.target.checked })}
        />
      </span>

      <span role="cell">
        <button
          type="button"
          className="aero-rules__remove"
          aria-label="Remove rule"
          onClick={() => setConfirmingRemove(true)}
        >
          ×
        </button>
        {confirmingRemove && (
          <ConfirmDialog
            title="Remove this restriction rule?"
            message={
              <>
                This removes the rule for <strong>{postTypeLabel}</strong>
                {selectedTaxonomy ? <> ({selectedTaxonomy.label})</> : null}. Posts it currently governs will fall
                through to the next matching rule, or become free if none match.
              </>
            }
            onConfirm={() => {
              setConfirmingRemove(false);
              onRemove();
            }}
            onCancel={() => setConfirmingRemove(false)}
          />
        )}
      </span>
    </div>
  );
}
