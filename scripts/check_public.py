"""Check Git publication candidates without printing potentially sensitive values."""
from pathlib import Path
import re
import subprocess
from urllib.parse import unquote, urlsplit

from package import ROOT, SECRET_PATTERN, IMAGES


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
            # Only the two deliberately captured, manually reviewed synthetic screenshots are public.
            data = path.read_bytes()
            if name not in IMAGES or not data.startswith(b"\xff\xd8\xff") or not data.endswith(b"\xff\xd9") or len(data) > 2_000_000:
                errors.append(f"{name}: binary file requires explicit manual review")
                continue
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
