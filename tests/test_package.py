"""Regression checks for public packaging, with disposable synthetic files."""
import hashlib
import importlib.util
from pathlib import Path
import tempfile
import unittest
import zipfile

SPEC = importlib.util.spec_from_file_location("public_package", Path(__file__).resolve().parents[1] / "scripts/package.py")
builder = importlib.util.module_from_spec(SPEC)
SPEC.loader.exec_module(builder)


class PackageTests(unittest.TestCase):
    def setUp(self):
        self.directory = tempfile.TemporaryDirectory()
        self.addCleanup(self.directory.cleanup)
        self.root = Path(self.directory.name)
        for name in builder.FILES:
            path = self.root / name
            path.parent.mkdir(parents=True, exist_ok=True)
            if name in builder.IMAGES:
                path.write_bytes(b"\xff\xd8\xffSYNTHETIC-IMAGE\xff\xd9")
            else:
                path.write_text("Synthetic public file\n", encoding="utf-8")
        (self.root / "y2kwebs-lafise.php").write_text("<?php\n/**\n * Version: 0.2.1\n */\n", encoding="utf-8")

    def test_allowlist_checksum_and_reproducibility(self):
        for name in ("docs/internal-notes.md", "src/debug.log", "config/credentials.local.php", ".runtime/access.txt", "tests/fixture.php"):
            extra = self.root / name
            extra.parent.mkdir(parents=True, exist_ok=True)
            extra.write_text("PRIVATE SYNTHETIC MATERIAL", encoding="utf-8")
        archive, digest = builder.package(self.root)
        first = archive.read_bytes()
        with zipfile.ZipFile(archive) as package:
            self.assertEqual(set(package.namelist()), {"y2kwebs-lafise/" + name for name in builder.FILES})
            self.assertIsNone(package.testzip())
            self.assertFalse(any(b"PRIVATE SYNTHETIC MATERIAL" in package.read(name) for name in package.namelist()))
        self.assertEqual(digest, hashlib.sha256(first).hexdigest())
        self.assertEqual(Path(str(archive) + ".sha256").read_text(), digest + "  " + archive.name + "\n")
        builder.package(self.root)
        self.assertEqual(first, archive.read_bytes())

    def test_secret_rejected_before_overwriting_archive(self):
        archive, _ = builder.package(self.root)
        before = archive.read_bytes()
        # Construct an invalid synthetic token, never an actual credential.
        (self.root / "src/Bank.php").write_text("ghp_" + "A" * 36, encoding="utf-8")
        with self.assertRaisesRegex(ValueError, "Secret pattern"):
            builder.package(self.root)
        self.assertEqual(before, archive.read_bytes())

    def test_missing_runtime_file_rejected(self):
        (self.root / "src/Bank.php").unlink()
        with self.assertRaises(FileNotFoundError):
            builder.package(self.root)

    def test_invalid_public_screenshot_rejected(self):
        (self.root / "docs/images/checkout.jpg").write_bytes(b"not-a-jpeg")
        with self.assertRaisesRegex(ValueError, "Invalid public screenshot"):
            builder.package(self.root)

    def test_version_mismatch_rejected(self):
        (self.root / "y2kwebs-lafise.php").write_text("<?php\n * Version: 9.9.9\n", encoding="utf-8")
        with self.assertRaisesRegex(ValueError, "version differ"):
            builder.package(self.root)


if __name__ == "__main__":
    unittest.main()
