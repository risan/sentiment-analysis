import { defineConfig } from 'astro/config';
import starlight from '@astrojs/starlight';

const site = 'https://sentiment-analysis.risanb.com';
const description =
  'Fast, dependency-free sentiment analysis for PHP 8.3+. VADER-based scoring for English and Indonesian, with negation, boosters, emoji and emoticons.';

export default defineConfig({
  site,
  vite: {
    build: {
      rolldownOptions: {
        // Astro 7.3 emits this directive itself for every MDX page. It is harmless, but noisy.
        onLog(level, log, defaultHandler) {
          if (log.code === 'MODULE_LEVEL_DIRECTIVE' && log.message.includes('astro:head-inject')) {
            return;
          }

          defaultHandler(level, log);
        },
      },
    },
  },
  integrations: [
    starlight({
      title: 'Sentiment Analysis',
      description,
      logo: { src: './src/assets/logo.svg', alt: '' },
      favicon: '/favicon.svg',
      social: [
        {
          icon: 'github',
          label: 'GitHub',
          href: 'https://github.com/risan/sentiment-analysis',
        },
      ],
      editLink: {
        baseUrl: 'https://github.com/risan/sentiment-analysis/edit/main/website/',
      },
      customCss: [
        '@fontsource-variable/inter',
        '@fontsource-variable/bricolage-grotesque',
        '@fontsource-variable/jetbrains-mono',
        './src/styles/custom.css',
      ],
      components: {
        Hero: './src/components/Hero.astro',
      },
      expressiveCode: {
        themes: ['vitesse-dark', 'vitesse-light'],
        styleOverrides: {
          borderRadius: '0.75rem',
          codeFontFamily: "'JetBrains Mono Variable', ui-monospace, SFMono-Regular, Menlo, monospace",
        },
      },
      head: [
        { tag: 'meta', attrs: { property: 'og:site_name', content: 'Sentiment Analysis for PHP' } },
        { tag: 'meta', attrs: { property: 'og:image', content: `${site}/og.png` } },
        { tag: 'meta', attrs: { property: 'og:image:width', content: '1200' } },
        { tag: 'meta', attrs: { property: 'og:image:height', content: '630' } },
        {
          tag: 'meta',
          attrs: {
            property: 'og:image:alt',
            content: 'Sentiment Analysis for PHP: English and Indonesian sentiment scoring.',
          },
        },
        { tag: 'meta', attrs: { name: 'twitter:card', content: 'summary_large_image' } },
        { tag: 'meta', attrs: { name: 'twitter:image', content: `${site}/og.png` } },
        { tag: 'meta', attrs: { name: 'author', content: 'Risan Bagja Pradana' } },
      ],
      sidebar: [
        {
          label: 'Getting started',
          items: [
            'getting-started/introduction',
            'getting-started/installation',
            'getting-started/quick-start',
          ],
        },
        {
          label: 'Guides',
          items: [
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
          items: [
            'reference/sentiment',
            'reference/analyzer',
            'reference/result',
            'reference/label',
            'reference/language',
          ],
        },
        'upgrading-from-v1',
        'credits',
      ],
    }),
  ],
});
