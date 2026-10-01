---
title: Credits & license
description: The people and data behind Sentiment Analysis for PHP. VADER by C.J. Hutto (MIT), an Indonesian lexicon written for this project, and evaluation on IndoNLU SmSA.
---

## License

Sentiment Analysis for PHP is open source software released under the **MIT License**. Free for personal and commercial use. The full text is in [`LICENSE.md`](https://github.com/risan/sentiment-analysis/blob/main/LICENSE.md).

Created and maintained by [Risan Bagja Pradana](https://github.com/risan). Source code, issues and releases live at [github.com/risan/sentiment-analysis](https://github.com/risan/sentiment-analysis).

## VADER

The scoring model and the English lexicon are based on **VADER** (Valence Aware Dictionary and sEntiment Reasoner), by **C.J. Hutto** and **Eric Gilbert**, released under the MIT license:

> Hutto, C.J. & Gilbert, E.E. (2014). *VADER: A Parsimonious Rule-based Model for Sentiment Analysis of Social Media Text.* Eighth International Conference on Weblogs and Social Media (ICWSM-14). Ann Arbor, MI, June 2014.

The English results of this package are verified against the reference implementation, [`vaderSentiment`](https://github.com/cjhutto/vaderSentiment) 3.3.2. This package is an independent PHP implementation: it does not wrap or call the Python library or any external service.

The VADER lexicon and its emoji descriptions are included under the MIT license, with their copyright notice in the package's `NOTICE.md`.

## Indonesian lexicon

The Indonesian lexicon and its rules (negations, intensifiers, contrast words) were **written for this project** and are released under the same MIT license.

They were authored without using any existing Indonesian sentiment word list. The well-known lists carry no open license, so copying them would not have been possible to ship. Word valences are our own judgments on the VADER scale. Emoticons and emoji are shared with the English data.

## Evaluation data

Indonesian accuracy is measured on the **IndoNLU SmSA** sentiment dataset, part of the [IndoNLU benchmark](https://github.com/IndoNLP/indonlu) by Wilie et al. (2020), which is released under the Apache-2.0 license. The dataset is downloaded only by the evaluation script and is not part of this package.

> Wilie, B., Vincentio, K., Winata, G.I., et al. (2020). *IndoNLU: Benchmark and Resources for Evaluating Indonesian Natural Language Understanding.* AACL-IJCNLP 2020.

## Built with

This website is built with [Astro](https://astro.build) and [Starlight](https://starlight.astro.build), and served as static assets from [Cloudflare Workers](https://workers.cloudflare.com).
