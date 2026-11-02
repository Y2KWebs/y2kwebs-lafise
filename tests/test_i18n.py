"""Translation coverage and build regressions, without WordPress or network access."""
import gettext
import io
from pathlib import Path
import sys
import unittest

sys.path.insert(0, str(Path(__file__).resolve().parents[1] / "scripts"))
from build_translations import ROOT, build, compile_mo, read_po, source_strings


class TranslationTests(unittest.TestCase):
    def test_catalogue_covers_every_source_and_artifacts_are_current(self):
        entries = read_po((ROOT / "languages/y2kwebs-lafise-es_ES.po").read_text(encoding="utf-8"))
        self.assertEqual(set(entries), set(source_strings()) | {""})
        self.assertTrue(all(entries.values()))
        self.assertEqual(build(check=True), len(entries) - 1)

    def test_compiled_catalogue_preserves_utf8_and_english_fallback(self):
        catalogue = gettext.GNUTranslations(io.BytesIO(compile_mo({"": "Content-Type: text/plain; charset=UTF-8\n", "Card number": "Número de tarjeta"})))
        self.assertEqual(catalogue.gettext("Card number"), "Número de tarjeta")
        self.assertEqual(catalogue.gettext("Missing entry"), "Missing entry")

    def test_unreviewed_and_duplicate_entries_are_rejected(self):
        with self.assertRaisesRegex(ValueError, "Fuzzy"):
            read_po('#, fuzzy\nmsgid "Card"\nmsgstr "Tarjeta"\n')
        with self.assertRaisesRegex(ValueError, "duplicate"):
            read_po('msgid "Card"\nmsgstr "Tarjeta"\n\nmsgid "Card"\nmsgstr "Otra"\n')
