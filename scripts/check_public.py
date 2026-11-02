"""Check Git publication candidates without printing potentially sensitive values."""
from pathlib import Path
import re
import subprocess
from urllib.parse import unquote, urlsplit

from package import ROOT, SECRET_PATTERN, IMAGES, MO_FILES, translation_text

# Exact, visually reviewed directory graphics; these never enter the installable ZIP.
DIRECTORY_ARTWORK = {
    "design/wordpress-org/exports/banner-772x250.png": (772, 250, 4_000_000),
    "design/wordpress-org/exports/banner-1544x500.png": (1544, 500, 4_000_000),
    "design/wordpress-org/exports/icon-128x128.png": (128, 128, 1_000_000),
    "design/wordpress-org/exports/icon-256x256.png": (256, 256, 1_000_000),
}


def check(root=ROOT):
    root = Path(root).resolve()
    candidates = subprocess.check_output(
        ["git", "ls-files", "--cached", "--others", "--exclude-standard", "-z"], cwd=root
    ).decode("utf-8").split("\0")
    paths = sorted(set(name for name in candidates if name))
    errors = []
    for name in paths:
        path = root / name
        parts = {part.lower() for part in Path(name).parts}
        forbidden = {".runtime", "dist", "private", ".private", "node_modules", "vendor", "coverage"}
        if (parts & forbidden or path.name.startswith(".env") or ".local." in path.name
                or path.suffix.lower() in {".log", ".zip", ".pdf", ".pem", ".key", ".p12", ".pfx", ".sql", ".har", ".pyc"}
                or path.name in {"lafise-proposal.md", "bank-questions.md"}):
            errors.append(f"{name}: file excluded from public distribution")
            continue
        if path.is_symlink() or not path.resolve().is_relative_to(root) or not path.is_file():
            errors.append(f"{name}: missing or unsafe publication path")
            continue
        try:
            content = path.read_text(encoding="utf-8-sig")
        except UnicodeDecodeError:
            # Admit only the individually reviewed screenshots and directory artwork.
            data = path.read_bytes()
            if name in MO_FILES:
                try:
                    content = translation_text(data)
                except ValueError:
                    errors.append(f"{name}: invalid translation catalogue")
                    continue
            elif name in DIRECTORY_ARTWORK:
                width, height, limit = DIRECTORY_ARTWORK[name]
                if (not data.startswith(b"\x89PNG\r\n\x1a\n") or data[12:16] != b"IHDR"
                        or int.from_bytes(data[16:20], "big") != width
                        or int.from_bytes(data[20:24], "big") != height or len(data) > limit):
                    errors.append(f"{name}: invalid directory artwork")
                    continue
            elif name not in IMAGES or not data.startswith(b"\xff\xd8\xff") or not data.endswith(b"\xff\xd9") or len(data) > 2_000_000:
                errors.append(f"{name}: binary file requires explicit manual review")
                continue
            if name not in MO_FILES:
                content = data.decode("utf-8", errors="ignore")
        for number, line in enumerate(content.splitlines(), 1):
            # This existing negative test deliberately rejects URL userinfo.
            reviewed = line
            if name == "tests/unit.php":
                reviewed = reviewed.replace("https://" + "user:pass" + "@bank.com", "SYNTHETIC-URL")
            if SECRET_PATTERN.search(line) or re.search(r"https?://[^\s/@]+:[^\s/@]+@", reviewed):
                errors.append(f"{name}:{number}: possible secret (value withheld)")
        if path.suffix == ".md":
            for target in re.findall(r"\[[^\]]*\]\(([^)]+)\)", content):
                target = target.strip("<>").split("#", 1)[0]
                if not target or urlsplit(target).scheme:
                    continue
                linked = (path.parent / unquote(target)).resolve()
                if not linked.is_relative_to(root) or not linked.is_file():
                    errors.append(f"{name}: broken or external local Markdown link")
    return paths, errors


if __name__ == "__main__":
    paths, errors = check()
    for error in errors:
        print(error)
    if errors:
        raise SystemExit(1)
    print(f"PASS: {len(paths)} Git publication candidates; known secret patterns, paths and local Markdown links checked.")
    print("Review all candidates manually too; this check cannot identify every possible secret or client reference.")
