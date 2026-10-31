"""Build the explicitly selected public plugin files, without development material."""
from pathlib import Path
import hashlib
import re
import zipfile

ROOT = Path(__file__).resolve().parent.parent
VERSION = "0.2.1"
IMAGES = frozenset(("docs/images/checkout.jpg", "docs/images/settings.jpg"))
# New files must be deliberately added; never recursively package documentation.
FILES = (
    "y2kwebs-lafise.php", "uninstall.php", "readme.txt", "README.md", "LICENSE", "SECURITY.md", "CONTRIBUTING.md",
    "src/Bank.php", "src/Blocks.php", "src/Config.php", "src/Gateway.php",
    "src/Money.php", "src/Payments.php", "src/Plugin.php", "src/Protocol.php",
    "src/Refunds.php", "src/Routes.php", "src/Secrets.php", "src/Settings.php",
    "src/Store.php", "src/ThreeDS.php",
    "assets/blocks.js", "assets/callback.js", "assets/checkout.css",
    "assets/checkout.js", "assets/jwe.mjs", "assets/verify.js",
    "config/protocol.json",
    "docs/configuration.md", "docs/architecture.md", "docs/pilot.md", "docs/validation.md",
    # README links must work inside the installed package too.
    "docs/development.md", "docs/releasing.md",
    "docs/images/checkout.jpg", "docs/images/settings.jpg",
)
SECRET_PATTERN = re.compile(
    r"-----BEGIN (?:RSA |EC |OPENSSH |ENCRYPTED )?PRIVATE KEY-----"
    r"|gh[pousr]_[A-Za-z0-9]{30,}|github_pat_[A-Za-z0-9_]{40,}"
    r"|AKIA[0-9A-Z]{16}|sk_live_[A-Za-z0-9]{16,}"
)


def package(root=ROOT):
    root = Path(root).resolve()
    # Validate everything before replacing a previously generated artifact.
    contents = {}
    for name in FILES:
        path = root / name
        if path.is_symlink() or not path.resolve().is_relative_to(root):
            raise ValueError(f"Unsafe package path: {name}")
        data = path.read_bytes()
        if name in IMAGES:
            if not data.startswith(b"\xff\xd8\xff") or not data.endswith(b"\xff\xd9") or len(data) > 2_000_000:
                raise ValueError(f"Invalid public screenshot: {name}")
            content = data.decode("utf-8", errors="ignore")
        else:
            content = data.decode("utf-8-sig")
        if SECRET_PATTERN.search(content):
            raise ValueError(f"Secret pattern in: {name}")
        contents[name] = data
    header = contents["y2kwebs-lafise.php"].decode("utf-8-sig")
    if not re.search(r"\* Version: " + re.escape(VERSION) + r"\s", header):
        raise ValueError("Plugin header and package version differ")
    out = root / "dist"
    out.mkdir(exist_ok=True)
    archive = out / f"y2kwebs-lafise-{VERSION}.zip"
    with zipfile.ZipFile(archive, "w", compression=zipfile.ZIP_DEFLATED) as z:
        for name, data in sorted(contents.items()):
            info = zipfile.ZipInfo("y2kwebs-lafise/" + name, (2026, 10, 29, 0, 0, 0))
            info.create_system = 3
            info.external_attr = 0o100644 << 16
            info.compress_type = zipfile.ZIP_DEFLATED
            z.writestr(info, data)
    digest = hashlib.sha256(archive.read_bytes()).hexdigest()
    (out / (archive.name + ".sha256")).write_bytes((digest + "  " + archive.name + "\n").encode("ascii"))
    return archive, digest


if __name__ == "__main__":
    archive, digest = package()
    print(f"Packaged {len(FILES)} files: {archive}\nSHA256 {digest}")
