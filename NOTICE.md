# Third-party notices

This package ships data and an algorithm derived from the following work.

## VADER

The scoring algorithm is a PHP port of VADER (Valence Aware Dictionary and sEntiment
Reasoner), `vaderSentiment` 3.3.2. The English lexicon (`resources/en/lexicon.php`), the emoji
descriptions (`resources/en/emoji.php`), the English rule tables (`resources/en/rules.php`) and the
emoticons and emoji-description words in the Indonesian lexicon are generated from the VADER
files `vader_lexicon.txt` and `emoji_utf8_lexicon.txt`. The sources are kept, unmodified, in
`tools/data/en/`; the `tools/` directory is not part of the Composer package.

If you use VADER, please cite:

> Hutto, C.J. & Gilbert, E.E. (2014). VADER: A Parsimonious Rule-based Model for Sentiment
> Analysis of Social Media Text. Eighth International Conference on Weblogs and Social Media
> (ICWSM-14). Ann Arbor, MI, June 2014.

VADER is licensed under the MIT License:

> The MIT License (MIT)
>
> Copyright (c) 2016 C.J. Hutto
>
> Permission is hereby granted, free of charge, to any person obtaining a copy
> of this software and associated documentation files (the "Software"), to deal
> in the Software without restriction, including without limitation the rights
> to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
> copies of the Software, and to permit persons to whom the Software is
> furnished to do so, subject to the following conditions:
>
> The above copyright notice and this permission notice shall be included in all
> copies or substantial portions of the Software.
>
> THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
> IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
> FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
> AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
> LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
> OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
> SOFTWARE.

## Indonesian lexicon

The Indonesian word list and rules (`resources/id/`) were written for this package and are
released under the package's MIT license. They were authored independently: no existing
Indonesian sentiment lexicon (InSet, SentiStrength-ID, Kamus Alay or any other) was opened,
copied or adapted, because none of them carries a license that allows redistribution.
The Indonesian lexicon only borrows language-neutral entries from VADER (emoticons and the
English words of emoji descriptions), as described above.
