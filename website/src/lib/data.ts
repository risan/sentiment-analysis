import examples from '../data/examples.json';
import metrics from '../data/metrics.json';

export type Example = (typeof examples)[number];

export { examples, metrics };

export function examplesFor(language?: 'en' | 'id'): Example[] {
  if (!language) {
    return examples;
  }

  return examples.filter((example) => example.language === language);
}

export function featuredExample(): Example {
  return examples.find((example) => example.language === 'en') ?? examples[0];
}

export function formatNumber(value: number | null, fractionDigits = 0): string {
  if (value === null) {
    return '—';
  }

  return value.toLocaleString('en-US', {
    minimumFractionDigits: fractionDigits,
    maximumFractionDigits: fractionDigits,
  });
}

export function formatPercent(value: number | null): string {
  if (value === null) {
    return '—';
  }

  return `${(value * 100).toFixed(1)}%`;
}

export function phpFloat(value: number): string {
  return Number.isInteger(value) ? value.toFixed(1) : String(value);
}

export function labelCase(label: string): string {
  return `Label::${label.charAt(0).toUpperCase()}${label.slice(1)}`;
}
