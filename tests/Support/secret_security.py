"""Pinned local Gitleaks; captured reports and stderr are never published."""

import hashlib
import io
import json
import os
from pathlib import Path, PurePosixPath
import platform
import re
import shutil
import subprocess
import sys
import tarfile
import tempfile
import urllib.request
import zipfile

ROOT = Path(__file__).resolve().parents[2]
TOOL = ROOT / ".phpunit.cache/gitleaks"
LOCK = json.loads((ROOT / "gitleaks.lock.json").read_text(encoding="utf-8"))
VERSION = LOCK["version"]
EXECUTABLE = TOOL / ("gitleaks.exe" if os.name == "nt" else "gitleaks")
SNAPSHOT_ROOTS = {"app", "bootstrap", "config", "database", "routes", "resources", "tests", ".github"}
TEXT_TYPES = {".php", ".ts", ".tsx", ".js", ".jsx", ".mjs", ".json", ".yml", ".yaml", ".toml",
              ".xml", ".md", ".txt", ".css", ".html", ".sh", ".ps1", ".lock", ".ini", ".example"}
TEXT_NAMES = {".npmrc", ".yarnrc", ".editorconfig", "Dockerfile", "Makefile", "artisan"}


class SecretFailure(Exception):
    """Messages contain fixed diagnostics, never scanner output or credentials."""


def digest(content):
    return hashlib.sha256(content).hexdigest()


def platform_pin():
    name = ("windows" if os.name == "nt" else platform.system().lower()) + "-x64"
    if platform.machine().lower() not in {"amd64", "x86_64"} or name not in LOCK["platforms"]:
        raise SecretFailure("Unsupported scanner platform; no clean result.")
    return LOCK["platforms"][name]


def environment():
    result = {key: value for key, value in os.environ.items()
              if not key.startswith(("GITLEAKS_", "GIT_CONFIG_"))
              and key not in {"GIT_DIR", "GIT_WORK_TREE", "GIT_INDEX_FILE", "GIT_OBJECT_DIRECTORY", "GIT_ALTERNATE_OBJECT_DIRECTORIES"}}
    result.update({"GOMAXPROCS": "1", "GOMEMLIMIT": "512MiB", "NO_COLOR": "1",
                   "GIT_CONFIG_NOSYSTEM": "1", "GIT_CONFIG_GLOBAL": os.devnull,
                   "GIT_TERMINAL_PROMPT": "0", "PYTHONDONTWRITEBYTECODE": "1"})
    return result


def run(arguments, cwd=ROOT, timeout=180):
    try:
        return subprocess.run([str(argument) for argument in arguments], cwd=cwd, env=environment(),
                              capture_output=True, timeout=timeout, check=False)
    except (OSError, subprocess.TimeoutExpired):
        raise SecretFailure("Security subprocess failed or timed out; no clean result.") from None


def git(root, *arguments):
    result = run(["git", "-C", root, *arguments])
    if result.returncode != 0:
        raise SecretFailure("Required Git metadata or operation is unavailable; no history result.")
    return result.stdout


def normalize_rules(raw):
    text = raw.decode("utf-8")
    normalized, count = re.subn(r"(?ms)(^\[allowlist\]\n.*?^paths = \[\n).*?^\]\n", r"\1]\n", text, count=1)
    if count != 1 or digest(normalized.encode("utf-8")) != LOCK["normalizedRulesSha256"]:
        raise SecretFailure("Upstream rule normalization changed; review required.")
    return normalized.encode("utf-8")


def download(url):
    try:
        with urllib.request.urlopen(url, timeout=45) as response:
            content = response.read(64 * 1024 * 1024 + 1)
        if len(content) > 64 * 1024 * 1024:
            raise SecretFailure("Scanner download exceeded its size limit.")
        return content
    except (OSError, ValueError):
        raise SecretFailure("Scanner download failed; no installation result.") from None


def install():
    pin = platform_pin()
    if not TOOL.resolve().is_relative_to(ROOT.resolve()) or TOOL.is_symlink():
        raise SecretFailure("Unsafe tool installation directory.")
    TOOL.mkdir(parents=True, exist_ok=True)
    cached = ROOT / ".phpunit.cache/gitleaks-download" / pin["asset"]
    content = cached.read_bytes() if cached.is_file() else download(
        f"https://github.com/gitleaks/gitleaks/releases/download/v{VERSION}/{pin['asset']}")
    if digest(content) != pin["sha256"]:
        raise SecretFailure("Scanner archive checksum mismatch.")
    # Extract only the named regular binary; never extract archive paths.
    if os.name == "nt":
        with zipfile.ZipFile(io.BytesIO(content)) as archive:
            binary = archive.read("gitleaks.exe")
    else:
        with tarfile.open(fileobj=io.BytesIO(content)) as archive:
            member = archive.getmember("gitleaks")
            if not member.isfile():
                raise SecretFailure("Scanner archive contains an invalid binary.")
            binary = archive.extractfile(member).read()
    if digest(binary) != pin["binarySha256"]:
        raise SecretFailure("Scanner binary checksum mismatch.")
    rules_cache = ROOT / ".phpunit.cache/gitleaks-download/default.toml"
    raw = rules_cache.read_bytes() if rules_cache.is_file() else download(
        f"https://raw.githubusercontent.com/gitleaks/gitleaks/v{VERSION}/config/gitleaks.toml")
    if digest(raw) != LOCK["rulesSha256"]:
        raise SecretFailure("Scanner rules checksum mismatch.")
    rules = normalize_rules(raw)
    for path, data in ((EXECUTABLE, binary), (TOOL / "rules.toml", rules)):
        if path.is_symlink():
            raise SecretFailure("Unsafe tool installation file.")
        path.write_bytes(data)
    EXECUTABLE.chmod(0o755)
    print(f"Installed checksum-verified Gitleaks {VERSION} and pinned provider rules.")


def verify_tool():
    pin = platform_pin()
    if (not EXECUTABLE.is_file() or digest(EXECUTABLE.read_bytes()) != pin["binarySha256"]
            or not (TOOL / "rules.toml").is_file()
            or digest((TOOL / "rules.toml").read_bytes()) != LOCK["normalizedRulesSha256"]):
        raise SecretFailure("Pinned scanner or rules are missing or changed; run composer secret:install.")
    version = run([EXECUTABLE, "version"])
    if version.returncode != 0 or version.stdout.decode("utf-8").strip() != VERSION:
        raise SecretFailure("Unexpected scanner version; no clean result.")


def safe_path(value):
    path = PurePosixPath(value.replace("\\", "/"))
    if path.is_absolute() or ".." in path.parts or re.match(r"^[A-Za-z]:", value):
        raise SecretFailure("Scanner returned an invalid relative path.")
    return path.as_posix().removeprefix("./")


def parse_report(result, root, permitted=None):
    try:
        report = json.loads(result.stdout)
        if report is None:
            report = []
        if not isinstance(report, list) or result.returncode != (10 if report else 0):
            raise ValueError
        # Native error logs make even a nominal success an incomplete scan.
        if re.search(rb"\b(ERR|FTL|error|fatal)\b", result.stderr, re.IGNORECASE):
            raise ValueError
        findings = []
        for finding in report:
            value = finding["File"].replace("\\", "/")
            path = Path(value)
            if path.is_absolute():
                value = path.relative_to(root).as_posix()
            value = safe_path(value)
            rule, line = finding["RuleID"], finding["StartLine"]
            commit = finding.get("Commit", "")
            if (not re.fullmatch(r"[a-z0-9-]+", rule) or type(line) is not int or line < 1
                    or (commit and not re.fullmatch(r"[a-f0-9]{40,64}", commit))
                    or (permitted is not None and value not in permitted)):
                raise ValueError
            findings.append({"rule": rule, "path": value, "line": line, "commit": commit})
        return findings
    except (ValueError, TypeError, KeyError, AttributeError):
        raise SecretFailure("Scanner error or invalid report; no clean result.") from None


def scan_native(root, mode, permitted=None, config=None):
    verify_tool()
    with tempfile.TemporaryDirectory(prefix="intelledger-secret-ignore-") as directory:
        ignore = Path(directory) / "empty.ignore"
        ignore.write_text("", encoding="utf-8")
        if config is None:
            # Resolve only the pinned extension path. Running from the scan root
            # keeps native path rules identical for tree and historical scans.
            contents = (ROOT / ".gitleaks.toml").read_text(encoding="utf-8")
            original = 'path = ".phpunit.cache/gitleaks/rules.toml"'
            if contents.count(original) != 1:
                raise SecretFailure("Unexpected rules extension; review scanner configuration.")
            config = Path(directory) / "config.toml"
            config.write_text(contents.replace(original, "path = " + json.dumps((TOOL / "rules.toml").as_posix())), encoding="utf-8")
        arguments = [EXECUTABLE, mode, ".", "--config", str(config),
                     "--redact=100", "--report-format=json", "--report-path=-", "--exit-code=10",
                     "--ignore-gitleaks-allow", "--gitleaks-ignore-path", str(ignore),
                     "--no-banner", "--no-color", "--log-level=error", "--timeout=150",
                     "--max-target-megabytes=0"]
        if mode == "git":
            arguments += ["--log-opts=--all --full-history --root --no-ext-diff --no-textconv"]
        return parse_report(run(arguments, cwd=root), root, permitted)


def tree(root=ROOT):
    tracked = run(["git", "-C", root, "rev-parse", "--is-inside-work-tree"])
    is_git = tracked.returncode == 0 and tracked.stdout.strip() == b"true"
    with tempfile.TemporaryDirectory(prefix="intelledger-secret-tree-") as directory:
        temporary = Path(directory).resolve()
        if is_git:
            names = git(root, "ls-files", "--cached", "-z").decode("utf-8").split("\0")
        else:
            # No development .git changes: a disposable index applies this
            # workspace's ignore files to the explicitly labelled snapshot.
            metadata = temporary / "inventory.git"
            git(temporary, "init", "--bare", "--quiet", str(metadata))
            names = git(root, "--git-dir=" + str(metadata), "--work-tree=" + str(root),
                        "ls-files", "--others", "--exclude-standard", "-z").decode("utf-8").split("\0")
            names = [name for name in names if name and
                     ("/" not in name or name.split("/", 1)[0] in SNAPSHOT_ROOTS) and
                     (Path(name).suffix in TEXT_TYPES or Path(name).name in TEXT_NAMES or Path(name).name.startswith(".git"))]
        target = temporary / "source"
        target.mkdir()
        copied = []
        for name in sorted(set(filter(None, names))):
            relative = safe_path(name)
            source = root / relative
            if source.is_symlink():
                raise SecretFailure("Tracked symlink requires explicit security review; no clean result.")
            if not source.exists():
                continue  # A deleted working-tree file remains covered by history.
            if not source.is_file():
                raise SecretFailure("Tracked non-file or submodule requires security review.")
            destination = target / relative
            destination.parent.mkdir(parents=True, exist_ok=True)
            shutil.copyfile(source, destination)
            copied.append(relative)
        if not copied:
            raise SecretFailure("Empty source inventory; no clean result.")
        findings = scan_native(target, "dir", set(copied))
        return findings, len(copied), "tracked working tree" if is_git else "available-source snapshot (Git tracking unavailable)"


def history(root=ROOT):
    if git(root, "rev-parse", "--is-shallow-repository").strip() != b"false":
        raise SecretFailure("Shallow history is incomplete; fetch full history before scanning.")
    count = int(git(root, "rev-list", "--count", "--all").strip())
    if count < 1:
        raise SecretFailure("Empty Git history; no clean result.")
    return scan_native(root, "git"), count, "available Git history"


def main(arguments=None):
    try:
        if sys.version_info[:2] != (3, 11):
            raise SecretFailure("Use the approved Python 3.11 runtime.")
        arguments = sys.argv[1:] if arguments is None else arguments
        if arguments == ["install"]:
            install()
            return 0
        if not arguments or arguments[0] not in {"tree", "history"} or len(arguments) > 2:
            raise SecretFailure("Choose install, tree, or history with an optional explicit repository path.")
        root = Path(arguments[1]).resolve() if len(arguments) == 2 else ROOT
        findings, count, scope = tree(root) if arguments[0] == "tree" else history(root)
        for finding in findings:
            print(json.dumps(finding, ensure_ascii=True))
        print(f"Gitleaks {VERSION}: {scope}; {count} {'files' if arguments[0] == 'tree' else 'commits'}; {len(findings)} findings.")
        return 1 if findings else 0
    except SecretFailure as failure:
        print(str(failure), file=sys.stderr)
        return 2
    except Exception:
        print("Secret-security operation failed; no clean result.", file=sys.stderr)
        return 2


if __name__ == "__main__":
    sys.exit(main())
