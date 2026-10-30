"""Harmless isolated rule/runner probes; fixture code is parsed, never executed."""

from copy import deepcopy
from contextlib import redirect_stderr, redirect_stdout
from io import StringIO
import json
from pathlib import Path
import sys
import tempfile
import unittest
from unittest.mock import patch

from ruamel.yaml import YAML

sys.path.insert(0, str(Path(__file__).resolve().parent / "Support"))
import source_security as security

# Every enabled rule needs a vulnerable and a safe comparison, not just a match.
CASES = {
    "php.request-sql": ("php", "use Illuminate\\Support\\Facades\\DB; DB::select('SELECT * FROM x WHERE id = '.request('id'));",
                        "use Illuminate\\Support\\Facades\\DB; DB::select('SELECT * FROM x WHERE id = ?', [request('id')]);"),
    "php.request-command": ("php", "shell_exec($request->input('command'));", "shell_exec('fixed-command');"),
    "php.request-unserialize": ("php", "unserialize(request('data'));", "json_decode(request('data'), true);"),
    "php.request-file": ("php", "file_get_contents(request('path'));", "file_put_contents('/fixed/path', request('content'));"),
    "php.request-ssrf": ("php", "use Illuminate\\Support\\Facades\\Http; Http::get(request('url'));", "Http::get('https://example.invalid', [request('q')]);"),
    "php.request-redirect": ("php", "redirect()->away(request('url'));", "redirect(route('home', ['q' => request('q')]));"),
    "php.disabled-tls": ("php", "Http::withoutVerifying()->get('https://example.invalid');", "Http::get('https://example.invalid');"),
    "php.request-html": ("php", "echo $request->input('description');", "echo e($request->input('description'));"),
    "php.weak-password-hash": ("php", "$hash = md5($password);", "$key = md5($email); $hash = Hash::make($password);"),
    "js.dynamic-code": ("ts", "eval(input);", "JSON.parse(input);"),
    "js.dom-xss": ("tsx", "const value = window.location.hash; const node = <div dangerouslySetInnerHTML={{__html: value}} />;",
                   "const value = window.location.hash; const node = <div>{value}</div>;"),
    "js.disabled-tls": ("ts", 'process.env.NODE_TLS_REJECT_UNAUTHORIZED = "0";', 'process.env.NODE_TLS_REJECT_UNAUTHORIZED = "1";'),
    "workflow.untrusted-shell": ("yml", 'run: echo "${{ github.event.pull_request.title }}"',
                                 'env:\n  TITLE: ${{ github.event.pull_request.title }}\nrun: echo "$TITLE"'),
    "workflow.write-all": ("yml", "permissions: write-all", "permissions:\n  contents: read"),
    "blade.unescaped": ("blade.php", "{!! $description !!}", "{{ $description }}"),
}


class SourceSecurityTests(unittest.TestCase):
    def test_every_enabled_rule_has_paired_fixtures(self):
        rules = YAML(typ="safe").load((security.ROOT / "semgrep.yml").read_text(encoding="utf-8"))["rules"]
        self.assertEqual({rule["id"] for rule in rules}, {"il." + rule for rule in CASES})

    def report(self):
        return {"version": security.VERSION, "results": [], "errors": [], "skipped_rules": [],
                "engine_requested": "OSS", "paths": {"scanned": ["app/safe.php"]}}

    def test_report_fails_closed(self):
        original = self.report()
        self.assertEqual([], security.validate_report(json.dumps(original), 0, ["app/safe.php"]))
        for change in (
            {"errors": [{"message": "SENSITIVE-SENTINEL"}]}, {"errors": None},
            {"paths": {"scanned": []}}, {"results": None}, {"version": "wrong"},
            {"skipped_rules": ["unexpected"]}, {"engine_requested": "PRO"},
        ):
            with self.subTest(change=list(change)):
                report = deepcopy(original)
                report.update(change)
                with self.assertRaises(security.ScanFailure) as failure:
                    security.validate_report(json.dumps(report), 0, ["app/safe.php"])
                self.assertNotIn("SENSITIVE-SENTINEL", str(failure.exception))
        for output, code, expected in (("invalid", 0, ["app/safe.php"]), ("{}", 0, ["app/safe.php"]),
                                        (json.dumps(original), 2, ["app/safe.php"]),
                                        (json.dumps(original), 1, ["app/safe.php"]),
                                        (json.dumps(original), 0, [])):
            with self.subTest(code=code, expected=expected):
                with self.assertRaises(security.ScanFailure):
                    security.validate_report(output, code, expected)

    def test_findings_are_sanitized_and_blocking(self):
        report = self.report()
        report["results"] = [{"check_id": "il.php.request-sql", "path": "app/safe.php", "start": {"line": 3},
                              "extra": {"severity": "ERROR", "lines": "SENSITIVE-SENTINEL", "message": "PRIVATE"}}]
        findings = security.validate_report(json.dumps(report), 1, ["app/safe.php"])
        self.assertEqual([{"rule": "il.php.request-sql", "path": "app/safe.php", "line": 3, "severity": "ERROR"}], findings)
        for code in (0, 2):
            with self.assertRaises(security.ScanFailure):
                security.validate_report(json.dumps(report), code, ["app/safe.php"])

    def test_execution_failure_is_not_clean(self):
        with patch.object(security, "EXECUTABLE", Path("missing-scanner-executable")):
            with self.assertRaises(security.ScanFailure):
                security.native(["--version"], security.ROOT)
        wrong_version = security.subprocess.CompletedProcess([], 0, "0.0.0", "")
        with patch.object(security, "native", return_value=wrong_version):
            with self.assertRaises(security.ScanFailure):
                security.scan()

    def test_entry_point_preserves_failure_exit_codes_without_sensitive_output(self):
        finding = {"rule": "il.php.request-sql", "path": "app/example.php", "line": 1, "severity": "ERROR"}
        for result, expected in (([], 0), ([finding], 1), (security.ScanFailure("Safe failure diagnostic."), 2),
                                 (RuntimeError("SENSITIVE-SENTINEL"), 2)):
            with self.subTest(expected=expected), redirect_stdout(StringIO()) as out, redirect_stderr(StringIO()) as error:
                with patch.object(security, "scan", side_effect=result if isinstance(result, Exception) else None,
                                  return_value=result):
                    self.assertEqual(expected, security.main())
                self.assertNotIn("SENSITIVE-SENTINEL", out.getvalue() + error.getvalue())

    def test_native_positive_negative_and_error_probes(self):
        with tempfile.TemporaryDirectory(prefix="intelledger-sast-") as directory:
            root = Path(directory)
            (root / "app").mkdir()
            (root / ".semgrepignore").write_text("# Intentionally scan every fixture.\n", encoding="utf-8")
            expected = {}
            for index, (rule, (extension, unsafe, safe)) in enumerate(CASES.items()):
                for label, contents in (("unsafe", unsafe), ("safe", safe)):
                    name = f"app/{index}-{label}.{extension}"
                    prefix = "<?php\n" if extension == "php" else ""
                    (root / name).write_text(prefix + contents + "\n", encoding="utf-8")
                    expected[name] = {"il." + rule} if label == "unsafe" else set()
            findings = security.scan(root, sorted(expected))
            actual = {name: set() for name in expected}
            for finding in findings:
                actual[finding["path"]].add(finding["rule"])
            self.assertEqual(expected, actual)
            broken = root / "app/invalid.php"
            broken.write_text("<?php DB::select(request('id')); function broken( {", encoding="utf-8")
            with self.assertRaises(security.ScanFailure):
                security.scan(root, sorted([*expected, "app/invalid.php"]))
            broken.unlink()
            invalid_config = root / "invalid-config.yml"
            invalid_config.write_text("rules: [invalid]", encoding="utf-8")
            with self.assertRaises(security.ScanFailure):
                security.scan(root, sorted(expected), invalid_config)
            for name in expected:
                (root / name).write_text("<?php\n" if name.endswith(".php") and not name.endswith(".blade.php") else "\n", encoding="utf-8")
            self.assertEqual([], security.scan(root, sorted(expected)))


if __name__ == "__main__":
    unittest.main(verbosity=1)
