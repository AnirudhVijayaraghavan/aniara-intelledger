"""Local-only Semgrep entry point. Raw scanner output never leaves this process."""

import json
import os
from pathlib import Path
import re
import subprocess
import sys

VERSION = "1.182.1"
ROOT = Path(__file__).resolve().parents[2]
EXECUTABLE = ROOT / ".phpunit.cache/semgrep-venv" / (
    "Scripts/semgrep.exe" if os.name == "nt" else "bin/semgrep"
)
ROOTS = ("app", "bootstrap", "config", "database", "routes", "resources", "tests", ".github")
EXTENSIONS = {".php", ".js", ".jsx", ".ts", ".tsx", ".yml", ".yaml"}
GENERATED = (
    "bootstrap/cache/", "bootstrap/ssr/", "resources/js/actions/",
    "resources/js/routes/", "resources/js/wayfinder/",
)


class ScanFailure(Exception):
    """Only fixed, non-sensitive diagnostic messages may cross this boundary."""


def environment():
    result = os.environ.copy()
    result.update({
        "SEMGREP_APP_TOKEN": "", "SEMGREP_SEND_METRICS": "off",
        "SEMGREP_ENABLE_VERSION_CHECK": "0", "SEMGREP_LOG_FILE": os.devnull,
        "SEMGREP_SETTINGS_FILE": str(ROOT / ".phpunit.cache/semgrep-settings.yml"),
        "OTEL_SDK_DISABLED": "true",
        "PYTHONDONTWRITEBYTECODE": "1",
    })
    return result


def inventory(root):
    files = []
    for directory in ROOTS:
        for path in (root / directory).rglob("*"):
            relative = path.relative_to(root).as_posix()
            if path.is_file() and path.suffix in EXTENSIONS and not relative.startswith(GENERATED):
                files.append(relative)
    files.extend(path.name for path in root.iterdir()
                 if path.is_file() and path.suffix in EXTENSIONS and path.name != "semgrep.yml")
    return sorted(files)


def native(arguments, root):
    try:
        return subprocess.run([str(EXECUTABLE), *arguments], cwd=root,
                              env=environment(), capture_output=True, timeout=240,
                              encoding="utf-8", errors="strict", check=False)
    except (OSError, subprocess.TimeoutExpired, UnicodeError):
        raise ScanFailure("Scanner could not execute completely; no clean result.") from None


def validate_report(output, code, expected):
    try:
        report = json.loads(output)
        if (not isinstance(report, dict) or report.get("version") != VERSION
                or not isinstance(report.get("results"), list)
                or report.get("errors") != []
                or report.get("skipped_rules") != []
                or report.get("engine_requested") != "OSS"
                or not isinstance(report.get("paths"), dict)
                or not isinstance(report["paths"].get("scanned"), list)):
            raise ValueError
        scanned = {Path(path).as_posix().removeprefix("./") for path in report["paths"]["scanned"]}
        if not expected or not set(expected).issubset(scanned):
            raise ScanFailure("Required source files were skipped; no clean result.")
        findings = []
        for finding in report["results"]:
            rule = finding["check_id"]
            path = Path(finding["path"]).as_posix().removeprefix("./")
            line = finding["start"]["line"]
            severity = finding["extra"]["severity"]
            if (not re.fullmatch(r"il\.[a-z.-]+", rule) or path not in expected
                    or type(line) is not int or line < 1 or severity != "ERROR"):
                raise ValueError
            findings.append({"rule": rule, "path": path, "line": line, "severity": severity})
        if code != (1 if findings else 0):
            raise ValueError
        return findings
    except (ValueError, TypeError, KeyError):
        raise ScanFailure("Scanner returned errors or an invalid report; no clean result.") from None


def scan(root=ROOT, files=None, config=None):
    version = native(["--version"], root)
    if version.returncode != 0 or version.stdout.strip() != VERSION:
        raise ScanFailure("Pinned Semgrep version is missing or incompatible.")
    expected = inventory(root) if files is None else files
    # Directory targets avoid Windows command-line length limits. Coverage is
    # checked independently, including files that Semgrep defaults might ignore.
    targets = [name for name in ROOTS if (root / name).is_dir()]
    targets.extend(path for path in expected if "/" not in path)
    result = native([
        "scan", "--config", str(config or ROOT / "semgrep.yml"),
        "--oss-only", "--error", "--strict", "--metrics=off",
        "--disable-version-check", "--disable-nosem", "--no-git-ignore",
        "--no-rewrite-rule-ids", "--jobs=1", "--max-memory=512",
        "--timeout=15", "--timeout-threshold=1", "--optimizations=none", "--json", "--quiet",
        *targets,
    ], root)
    return validate_report(result.stdout, result.returncode, expected)


def main():
    try:
        findings = scan()
        for finding in findings:
            print(json.dumps(finding, ensure_ascii=True))
        print(f"Semgrep {VERSION}: {len(inventory(ROOT))} required files; {len(findings)} blocking findings.")
        return 1 if findings else 0
    except ScanFailure as failure:
        print(str(failure), file=sys.stderr)
        return 2
    except Exception:
        # Do not expose a traceback, raw scanner stderr, source, or local paths.
        print("Source-security check failed unexpectedly; no clean result.", file=sys.stderr)
        return 2


if __name__ == "__main__":
    sys.exit(main())
