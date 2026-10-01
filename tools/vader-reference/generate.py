#!/usr/bin/env python3
"""Generates tests/Fixtures/vader-golden.json from the vendored VADER reference.

Usage:
    python3 tools/vader-reference/generate.py            write the golden file
    python3 tools/vader-reference/generate.py --check    sanity-check the reference setup

Python standard library only, no network. The reference module and both
lexicons come from this repository, so the golden file reflects the exact
code and data bytes the PHP build uses.

The vendored module's __main__ demo (needs NLTK and input()) is never run.

Two documented deviations from vaderSentiment 3.3.2 are applied (plan D6):
  * _but_check scales by position instead of list.index(value), which
    mis-scales duplicate valences;
  * U+FE0F (emoji variation selector) is stripped before scoring.
"""

import json
import os
import random
import re
import sys

HERE = os.path.dirname(os.path.abspath(__file__))
ROOT = os.path.dirname(os.path.dirname(HERE))
DATA = os.path.join(ROOT, "tools", "data", "en")
GOLDEN = os.path.join(ROOT, "tests", "Fixtures", "vader-golden.json")

sys.path.insert(0, HERE)
import vaderSentiment as vs  # noqa: E402

LEXICON = os.path.join(DATA, "vader_lexicon.txt")
EMOJI_LEXICON = os.path.join(DATA, "emoji_utf8_lexicon.txt")


def make_unpatched():
    # os.path.join keeps an absolute second argument, so these absolute paths
    # make the reference read our files instead of the ones next to the module.
    return vs.SentimentIntensityAnalyzer(lexicon_file=LEXICON, emoji_lexicon=EMOJI_LEXICON)


class PatchedAnalyzer(vs.SentimentIntensityAnalyzer):
    """The reference with the D6 deviations."""

    @staticmethod
    def _but_check(words_and_emoticons, sentiments):
        lowered = [str(w).lower() for w in words_and_emoticons]
        if "but" in lowered:
            but_index = lowered.index("but")
            for position, sentiment in enumerate(sentiments):
                if position < but_index:
                    sentiments[position] = sentiment * 0.5
                elif position > but_index:
                    sentiments[position] = sentiment * 1.5
        return sentiments

    def polarity_scores(self, text):
        return super().polarity_scores(text.replace("️", ""))


def make_patched():
    return PatchedAnalyzer(lexicon_file=LEXICON, emoji_lexicon=EMOJI_LEXICON)


def score_raw(analyzer, text):
    """Scores without the final round() calls."""
    vs.round = lambda value, digits: value
    try:
        return analyzer.polarity_scores(text)
    finally:
        del vs.round


ESCAPE = re.compile(r"\\(?:(?P<simple>[tnr\\])|x(?P<x>[0-9a-fA-F]{2})|u(?P<u>[0-9a-fA-F]{4})|U(?P<U>[0-9a-fA-F]{8}))")
SIMPLE = {"t": "\t", "n": "\n", "r": "\r", "\\": "\\"}


def unescape(line):
    def replace(match):
        if match.group("simple"):
            return SIMPLE[match.group("simple")]
        return chr(int(match.group("x") or match.group("u") or match.group("U"), 16))

    return ESCAPE.sub(replace, line)


def read_corpus():
    with open(os.path.join(HERE, "corpus.txt"), encoding="utf-8", newline="") as handle:
        lines = handle.read().split("\n")
    texts = []
    for line in lines:
        if line == "" or line.startswith("# "):
            continue
        texts.append(unescape(line))
    return texts


def read_lexicon_words():
    words = []
    with open(LEXICON, encoding="utf-8", newline="") as handle:
        for line in handle.read().rstrip("\n").split("\n"):
            if line:
                words.append(line.strip().split("\t")[0])
    return words


def read_emoji():
    emoji = []
    with open(EMOJI_LEXICON, encoding="utf-8", newline="") as handle:
        for line in handle.read().rstrip("\n").split("\n"):
            key = line.strip().split("\t")[0]
            if len(key) == 1:
                emoji.append(key)
    return emoji


def random_sentences(count, seed):
    rng = random.Random(seed)
    words = sorted({w for w in read_lexicon_words() if re.fullmatch(r"[a-z]+", w)})
    emoticons = [w for w in read_lexicon_words() if not re.search(r"[A-Za-z0-9]", w) and " " not in w]
    emoji = read_emoji()
    boosters = sorted(w for w in vs.BOOSTER_DICT if " " not in w)
    negations = sorted(vs.NEGATE)
    fillers = ["the", "a", "an", "is", "was", "it", "this", "that", "movie", "food", "and", "i", "we", "they",
               "of", "to", "in", "for", "with", "on", "at", "very", "so", "least", "never", "without", "doubt",
               "no", "or", "nor", "kind", "sort", "just", "enough", "table", "phone", "today", "yesterday"]
    idioms = sorted(list(vs.SPECIAL_CASES) + ["kind of", "sort of", "at least", "very least", "never so",
                                              "never this", "without doubt", "no or", "no nor"])
    endings = ["", "", "", ".", "!", "!!", "!!!", "!!!!!", "?", "??", "???", "????", "!?", "?!?!"]
    attachments = ["", "", "", "", ",", ".", "!", "?", ";", ":", "...", "!!"]

    sentences = []
    for _ in range(count):
        tokens = []
        for _ in range(rng.randint(2, 18)):
            roll = rng.random()
            if roll < 0.32:
                token = rng.choice(words)
            elif roll < 0.42:
                token = rng.choice(boosters)
            elif roll < 0.52:
                token = rng.choice(negations)
            elif roll < 0.57:
                token = "but"
            elif roll < 0.62:
                token = rng.choice(idioms)
            elif roll < 0.66:
                token = rng.choice(emoticons)
            elif roll < 0.69:
                token = rng.choice(emoji)
            else:
                token = rng.choice(fillers)

            case_roll = rng.random()
            if case_roll < 0.08:
                token = token.upper()
            elif case_roll < 0.12:
                token = token.capitalize()

            if rng.random() < 0.15:
                token += rng.choice(attachments)
            tokens.append(token)

        sentence = " ".join(tokens)
        if rng.random() < 0.06:
            sentence = sentence.upper()
        sentences.append(sentence + rng.choice(endings))
    return sentences


def neutral_rounding_tie():
    return ":) " + " ".join(["table"] * 45)


def build_texts():
    texts = read_corpus()
    texts += ["", neutral_rounding_tie()]
    texts += random_sentences(2000, seed=20161001)
    return texts


def reference_rules():
    return {
        "version": "3.3.2",
        "negations": list(vs.NEGATE),
        "boosters": dict(vs.BOOSTER_DICT),
        "specialCases": {phrase: float(value) for phrase, value in vs.SPECIAL_CASES.items()},
    }


def build_golden():
    analyzer = make_patched()
    cases = []
    for text in build_texts():
        raw = score_raw(analyzer, text)
        rounded = analyzer.polarity_scores(text)
        cases.append({
            "text": text,
            "raw": {key: raw[key] for key in ("neg", "neu", "pos", "compound")},
            "neg": rounded["neg"],
            "neu": rounded["neu"],
            "pos": rounded["pos"],
            "compound": rounded["compound"],
        })
    return cases


def write_golden(cases):
    os.makedirs(os.path.dirname(GOLDEN), exist_ok=True)
    with open(GOLDEN, "w", encoding="utf-8", newline="\n") as handle:
        handle.write('{\n"reference": ')
        handle.write(json.dumps(reference_rules(), ensure_ascii=False))
        handle.write(',\n"cases": [\n')
        handle.write(",\n".join(json.dumps(case, ensure_ascii=False) for case in cases))
        handle.write("\n]\n}\n")


def check():
    unpatched = make_unpatched()

    smart = unpatched.polarity_scores("VADER is smart, handsome, and funny.")
    assert smart["compound"] == 0.8316, smart
    assert smart["neu"] == 0.254, smart
    assert smart["pos"] == 0.746, smart

    negated = unpatched.polarity_scores("VADER is not smart, handsome, nor funny.")
    assert negated["compound"] == -0.7424, negated

    patched = make_patched()
    differing = 0
    for text in build_texts():
        if unpatched.polarity_scores(text) == patched.polarity_scores(text):
            continue
        differing += 1
        assert "️" in text or "but" in text.lower(), "unexpected patched/unpatched difference: %r" % text
    print("ok: README anchors reproduced; %d texts differ, all only because of U+FE0F or 'but'" % differing)


def main():
    if "--check" in sys.argv[1:]:
        check()
        return
    cases = build_golden()
    write_golden(cases)
    print("wrote %d cases to %s" % (len(cases), os.path.relpath(GOLDEN, ROOT)))


if __name__ == "__main__":
    main()
