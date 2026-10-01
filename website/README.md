# Website

The homepage and documentation for [Sentiment Analysis for PHP](https://sentiment-analysis.risanb.com), built with [Astro](https://astro.build) and [Starlight](https://starlight.astro.build). The build is fully static and is served as static assets by a Cloudflare Worker (no Worker script).

## Develop

Requires Node.js 22.12 or newer.

```bash
npm ci
npm run dev       # http://localhost:4321
npm run build     # static site in dist/
npm run preview   # serve dist/ with wrangler, like production (http://localhost:8787)
npm run check:local  # build, serve with a local Workers runtime, GET-check key pages and the 404
```

## Content

- `src/content/docs/`: the pages, in Markdown and MDX.
- `src/data/examples.json`: example sentences with their scores. It is generated from the real PHP library by `tools/website-examples.php` and committed, because the Cloudflare build has no PHP. The homepage and the docs import it, so never type a score into a page by hand.
- `src/data/metrics.json`: benchmark and Indonesian accuracy numbers. A `null` renders as an em dash. Fill the numbers in here once they are measured, and every page that shows them updates.
- `og-source/og.html`: the source of `public/og.png` (1200x630). The command to render it is in the file.

## Deploy

Deploying is a manual step: it needs your Cloudflare account.

Precondition: the `risanb.com` zone is in your Cloudflare account. `wrangler.jsonc` attaches the Worker to the custom domain `sentiment-analysis.risanb.com`, and the deploy fails if that zone is not yours.

Either:

1. **Workers Builds (recommended).** In the Cloudflare dashboard, go to Workers & Pages, create a Worker from this Git repository, and set:
   - project name: `sentiment-analysis` (must equal `name` in `wrangler.jsonc`)
   - production branch: `main`
   - root directory: `website`
   - build command: `npm ci && npm run build`
   - deploy command: `npx wrangler deploy`

   Node 24 comes from `.node-version`. Every push to `main` then deploys.

2. **From your machine.**

   ```bash
   cd website
   npx wrangler login
   npm run deploy
   ```

Unknown URLs get the site's own 404 page (`not_found_handling: "404-page"`).
