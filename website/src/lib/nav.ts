export interface NavGroup {
  label?: string;
  slugs: string[];
}

export const navGroups: NavGroup[] = [
  {
    label: 'Getting started',
    slugs: ['getting-started/introduction', 'getting-started/installation', 'getting-started/quick-start'],
  },
  {
    label: 'Guides',
    slugs: [
      'guides/understanding-scores',
      'guides/languages',
      'guides/customizing-the-lexicon',
      'guides/thresholds',
      'guides/long-text',
      'guides/performance',
    ],
  },
  {
    label: 'API reference',
    slugs: [
      'reference/sentiment',
      'reference/analyzer',
      'reference/result',
      'reference/label',
      'reference/language',
    ],
  },
  {
    slugs: ['upgrading-from-v1', 'credits'],
  },
];

export const navOrder = navGroups.flatMap((group) => group.slugs);

export const siteName = 'Sentiment Analysis for PHP';
export const repoUrl = 'https://github.com/risan/sentiment-analysis';
