"""Validate the complete Spanish PO and deterministically build WordPress POT/MO files."""
from pathlib import Path
import argparse
import gettext
import io
import json
import re
import struct

ROOT = Path(__file__).resolve().parent.parent
DOMAIN = "y2kwebs-lafise"


def read_po(text):
    entries, entry, field = {}, {}, None

    def finish():
        nonlocal entry, field
        if entry:
            if set(entry) != {"msgid", "msgstr"} or entry["msgid"] in entries:
                raise ValueError("Invalid or duplicate PO entry")
            entries[entry["msgid"]] = entry["msgstr"]
        entry, field = {}, None

    for line in text.splitlines():
        line = line.strip()
        if not line:
            finish()
        elif line.startswith("#, ") and "fuzzy" in line:
            raise ValueError("Fuzzy Spanish translation")
        elif line.startswith("#"):
            continue
        elif line.startswith("msgid ") or line.startswith("msgstr "):
            field, value = line.split(" ", 1)
            entry[field] = json.loads(value)
        elif line.startswith('"') and field:
            entry[field] += json.loads(line)
        else:
            raise ValueError("Unsupported PO syntax; context/plural support must be added explicitly")
    finish()
    return entries


def source_strings(root=ROOT):
    root = Path(root)
    strings = {}
    paths = [root / "y2kwebs-lafise.php", *sorted((root / "src").glob("*.php"))]
    pattern = re.compile(r"\b(?:__|esc_html__|esc_attr__)\(\s*'((?:\\.|[^'\\])*)'\s*,\s*'y2kwebs-lafise'\s*\)")
    for path in paths:
        text = path.read_text(encoding="utf-8-sig")
        for match in pattern.finditer(text):
            value = match.group(1).replace("\\'", "'").replace("\\\\", "\\")
            strings.setdefault(value, []).append(f"{path.relative_to(root).as_posix()}:{text[:match.start()].count(chr(10)) + 1}")
    header = (root / "y2kwebs-lafise.php").read_text(encoding="utf-8-sig")
    for field in ("Plugin Name", "Plugin URI", "Description", "Author", "Author URI"):
        value = re.search(r"\* " + field + r": (.+)", header).group(1)
        strings.setdefault(value, []).append("y2kwebs-lafise.php")
    return strings


def compile_mo(entries):
    keys = sorted(entries)
    originals = [value.encode("utf-8") for value in keys]
    translations = [entries[key].encode("utf-8") for key in keys]
    offset = 28 + 16 * len(keys)
    original_table, translation_table, original_data, translation_data = [], [], b"", b""
    for value in originals:
        original_table.append((len(value), offset + len(original_data)))
        original_data += value + b"\0"
    offset += len(original_data)
    for value in translations:
        translation_table.append((len(value), offset + len(translation_data)))
        translation_data += value + b"\0"
    # WordPress POMO expects the hash-table offset even when its length is zero.
    result = struct.pack("<7I", 0x950412DE, 0, len(keys), 28, 28 + 8 * len(keys), 0, 28 + 16 * len(keys))
    result += b"".join(struct.pack("<2I", *item) for item in original_table + translation_table)
    result += original_data + translation_data
    gettext.GNUTranslations(io.BytesIO(result))
    return result


def build(root=ROOT, check=False):
    root = Path(root)
    entries = read_po((root / "languages" / f"{DOMAIN}-es_ES.po").read_text(encoding="utf-8"))
    sources = source_strings(root)
    if set(entries) != set(sources) | {""}:
        raise ValueError(f"Catalogue/source mismatch: missing={sorted(set(sources) - set(entries))}; obsolete={sorted(set(entries) - set(sources) - {''})}")
    if any(not value for key, value in entries.items() if key):
        raise ValueError("Every visible source string requires a reviewed Spanish translation")
    pot_header = "Project-Id-Version: Y2KWebs Gateway for LAFISE\nMIME-Version: 1.0\nContent-Type: text/plain; charset=UTF-8\nContent-Transfer-Encoding: 8bit\nX-Domain: y2kwebs-lafise\n"
    pot = "# Copyright (C) 2026 Y2KWebs\n# Distributed under GPL-2.0-or-later.\nmsgid \"\"\nmsgstr " + json.dumps(pot_header) + "\n\n"
    for text, references in sorted(sources.items()):
        pot += "#: " + " ".join(references) + "\nmsgid " + json.dumps(text, ensure_ascii=False) + '\nmsgstr ""\n\n'
    outputs = {f"{DOMAIN}.pot": pot.encode("utf-8"), f"{DOMAIN}-es_ES.mo": compile_mo(entries)}
    for name, data in outputs.items():
        path = root / "languages" / name
        if check:
            if not path.exists() or path.read_bytes() != data:
                raise ValueError(f"Outdated translation artifact: {name}")
        else:
            path.write_bytes(data)
    return len(sources)


if __name__ == "__main__":
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("--check", action="store_true")
    args = parser.parse_args()
    print(f"PASS: {build(check=args.check)} source strings have Spanish translations; POT/MO are reproducible.")
