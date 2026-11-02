"""Prepare the review ZIP and a local SVN layout; never submit or publish anything."""
import hashlib
import json
from pathlib import Path
import shutil
import zipfile

from package import FILES, ROOT, VERSION, package

SCREENSHOTS = {
    "screenshot-1.jpg": "docs/images/checkout.jpg",
    "screenshot-2.jpg": "docs/images/settings.jpg",
}


def prepare(root=ROOT):
    root = Path(root).resolve()
    out = root / "dist" / "wordpress-org"
    # Refuse unexpected files rather than leaving stale files in a publication candidate.
    expected = {"manifest.json"}
    for prefix in ("svn/trunk/", f"svn/tags/{VERSION}/"):
        expected.update(prefix + name for name in FILES)
    expected.update("svn/assets/" + name for name in SCREENSHOTS)
    if out.is_symlink() or (out.exists() and not out.is_dir()):
        raise ValueError("Unsafe WordPress.org staging directory")
    if out.exists():
        for path in out.rglob("*"):
            if path.is_symlink():
                raise ValueError("Symlink in WordPress.org staging directory")
            if path.is_file() and path.relative_to(out).as_posix() not in expected:
                raise ValueError("Unexpected file in WordPress.org staging directory")
    archive, digest = package(root)
    with zipfile.ZipFile(archive) as source:
        for name in FILES:
            data = source.read("y2kwebs-lafise/" + name)
            for prefix in ("svn/trunk", f"svn/tags/{VERSION}"):
                target = out / prefix / name
                target.parent.mkdir(parents=True, exist_ok=True)
                target.write_bytes(data)
    for name, source in SCREENSHOTS.items():
        target = out / "svn/assets" / name
        target.parent.mkdir(parents=True, exist_ok=True)
        shutil.copyfile(out / "svn/trunk" / source, target)
    manifest = {
        "version": VERSION,
        "requested_slug": "y2kwebs-lafise",
        "status": "local staging only; this script performs no remote actions",
        "archive": archive.name,
        "archive_sha256": digest,
        "files": {
            name: hashlib.sha256((out / name).read_bytes()).hexdigest()
            for name in sorted(expected - {"manifest.json"})
        },
    }
    (out / "manifest.json").write_text(json.dumps(manifest, indent=2) + "\n", encoding="utf-8")
    return out, digest


if __name__ == "__main__":
    out, digest = prepare()
    print(f"Prepared WordPress.org review ZIP and local SVN layout: {out}\nSHA256 {digest}")
