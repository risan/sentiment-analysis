---
title: Credits & license
description: The people and data behind Sentiment Analysis for PHP. VADER by C.J. Hutto (MIT), an Indonesian lexicon written for this project, and evaluation on IndoNLU SmSA.
---

Sentiment Analysis for PHP is built on the work of others. This page names them and states the licenses.

## License

Sentiment Analysis for PHP is open source software with the **MIT License**. You can use it for free, for personal and commercial work. The full text is in [`LICENSE.md`](https://github.com/risan/sentiment-analysis/blob/main/LICENSE.md).

[Risan Bagja Pradana](https://github.com/risan) created and maintains it. The source code, issues and releases are at [github.com/risan/sentiment-analysis](https://github.com/risan/sentiment-analysis).

## VADER

The scoring model and the English lexicon are based on **VADER** (Valence Aware Dictionary and sEntiment Reasoner). **C.J. Hutto** and **Eric Gilbert** created it, and it has the MIT license:

> Hutto, C.J. & Gilbert, E.E. (2014). *VADER: A Parsimonious Rule-based Model for Sentiment Analysis of Social Media Text.* Eighth International Conference on Weblogs and Social Media (ICWSM-14). Ann Arbor, MI, June 2014.

The English results of this package are checked against the reference implementation, [`vaderSentiment`](https://github.com/cjhutto/vaderSentiment) 3.3.2. This package is an independent PHP version. It does not wrap or call the Python library or any outside service.

The package includes the VADER lexicon and its emoji descriptions under the MIT license. Their copyright notice is in `NOTICE.md` in the package.

## Indonesian lexicon

The Indonesian lexicon and its rules (negations, intensifiers and contrast words) were **written for this project**. They have the same MIT license.

They were written without any existing Indonesian sentiment word list. The well-known lists have no open license, so the package could not ship a copy. The word scores are our own judgments on the VADER scale. Emoticons and emoji are shared with the English data.

## Evaluation data

The accuracy of the Indonesian scoring is measured on the **IndoNLU SmSA** sentiment dataset. It is part of the [IndoNLU benchmark](https://github.com/IndoNLP/indonlu) by Wilie et al. (2020), with the Apache-2.0 license. Only the evaluation script downloads the dataset. It is not part of this package.

> Wilie, B., Vincentio, K., Winata, G.I., et al. (2020). *IndoNLU: Benchmark and Resources for Evaluating Indonesian Natural Language Understanding.* AACL-IJCNLP 2020.

## Built with

This website is built with [Astro](https://astro.build) and [Tailwind CSS](https://tailwindcss.com). [Cloudflare Workers](https://workers.cloudflare.com) serves it as static files.
