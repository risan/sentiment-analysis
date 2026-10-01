import { defineConfig } from 'astro/config';
import mdx from '@astrojs/mdx';
import sitemap from '@astrojs/sitemap';
import tailwindcss from '@tailwindcss/vite';

export default defineConfig({
  site: 'https://sentiment-analysis.risanb.com',
  trailingSlash: 'always',
  integrations: [mdx(), sitemap({ filter: (page) => !page.endsWith('/404/') })],
  markdown: {
    shikiConfig: { theme: 'github-light' },
  },
  vite: {
    plugins: [tailwindcss()],
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
});
