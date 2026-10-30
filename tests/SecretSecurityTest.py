"""Synthetic credential probes; no generated token is printed or sent to a service."""

from contextlib import redirect_stderr, redirect_stdout
from io import StringIO
import json
from pathlib import Path
import random
import shutil
import subprocess
import sys
import tempfile
import unittest
from unittest.mock import patch

sys.path.insert(0, str(Path(__file__).resolve().parent / "Support"))
import secret_security as security


def synthetic(prefix, length):
    generator = random.Random(20261028 + length)
    return prefix + "".join(generator.choice("abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789") for _ in range(length))


def commit(root, message):
    security.git(root, "add", ".")
    security.git(root, "-c", "user.name=Security Probe", "-c", "user.email=probe@example.invalid",
                 "-c", "commit.gpgsign=false", "commit", "--quiet", "-m", message)


class SecretSecurityTests(unittest.TestCase):
    def test_metadata_free_snapshot_honors_ignores_and_includes_package_configuration(self):
        with tempfile.TemporaryDirectory(prefix="intelledger-secret-snapshot-") as directory:
            root = Path(directory).resolve()
            (root / ".gitignore").write_text(".env\n", encoding="utf-8")
            (root / ".env").write_text('api_key="' + synthetic("", 48) + '"', encoding="utf-8")
            (root / ".npmrc").write_text('//registry.example.invalid/:_authToken=' + synthetic("npm_", 36), encoding="utf-8")
            (root / "database").mkdir()
            (root / "database/runtime.sqlite").write_bytes(b"Not source code or a real database.")
            findings, count, label = security.tree(root)
            self.assertEqual(2, count)
            self.assertIn("tracking unavailable", label)
            self.assertEqual({".npmrc"}, {finding["path"] for finding in findings})
            self.assertFalse((root / ".git").exists())

    def test_native_detection_tree_history_and_exemption_counterexample(self):
        with tempfile.TemporaryDirectory(prefix="intelledger-secret-probe-") as directory:
            root = Path(directory).resolve()
            security.git(root, "init", "--quiet")
            fixtures = {
                "tests/token.txt": ('token = "' + synthetic("gh" + "p_", 36) + '" # gitleaks:allow\n', "github-pat"),
                "package-lock.json": (json.dumps({"api_key": synthetic("", 40)}), "generic-api-key"),
                "app/key.txt": ("-----BEGIN " + "RSA PRIVATE KEY-----\n" + synthetic("", 64) + "\n-----END " + "RSA PRIVATE KEY-----\n", "private-key"),
            }
            for name, (contents, _) in fixtures.items():
                path = root / name
                path.parent.mkdir(parents=True, exist_ok=True)
                path.write_text(contents, encoding="utf-8")
            commit(root, "Synthetic credentials, never issued by any provider")
            (root / ".env").write_text('api_key="' + synthetic("", 45) + '"', encoding="utf-8")
            findings, count, scope = security.tree(root)
            self.assertEqual(len(fixtures), count)
            self.assertEqual("tracked working tree", scope)
            locations = {(finding["path"], finding["rule"]) for finding in findings}
            for name, (_, rule) in fixtures.items():
                self.assertIn((name, rule), locations)
            self.assertNotIn(".env", {finding["path"] for finding in findings})
            # Real CLI + Composer forwarding: fixture source is never executed.
            (root / "composer.json").write_text(json.dumps({"name": "intelledger/secret-probe", "scripts": {
                "probe": ['python -B "' + str(security.ROOT / "tests/Support/secret_security.py") + '" tree "' + str(root) + '"']
            }}), encoding="utf-8")
            composer = Path(shutil.which("composer"))
            if composer.suffix.lower() == ".bat":
                composer = composer.with_name("composer.phar")
            forwarded = security.run([shutil.which("php"), composer, "--no-plugins", "--no-interaction", "probe"], root)
            self.assertNotEqual(0, forwarded.returncode)
            for token in (synthetic("gh" + "p_", 36), synthetic("", 40), synthetic("", 64)):
                self.assertFalse(token.encode() in forwarded.stdout + forwarded.stderr,
                                 "Generated credentials must never appear in command output.")
            self.assertIn(b'"rule"', forwarded.stdout)
            for name in fixtures:
                (root / name).write_text("No credential here.\n", encoding="utf-8")
            (root / ".env").unlink()
            commit(root, "Remove synthetic credentials from current tree")
            self.assertEqual([], security.tree(root)[0])
            historical, commits, _ = security.history(root)
            self.assertEqual(2, commits)
            self.assertTrue(historical)
            self.assertTrue(all(finding["commit"] for finding in historical))
            shallow = root / "shallow"
            security.git(root, "clone", "--quiet", "--depth=1", root.as_uri(), str(shallow))
            with self.assertRaises(security.SecretFailure):
                security.history(shallow)
            # Only an unusable short-format example at this precise path is exempt.
            document = root / ".agents/skills/laravel-best-practices/rules/config.md"
            document.parent.mkdir(parents=True)
            document.write_text("STRIPE_SECRET=" + "sk_" + "live_" + "abc123\n", encoding="utf-8")
            # Scan relative to the fixture root so the exception's exact path applies.
            single = root / "example"
            single.mkdir()
            relative = Path(".agents/skills/laravel-best-practices/rules/config.md")
            example = single / relative
            example.parent.mkdir(parents=True)
            example.write_bytes(document.read_bytes())
            self.assertEqual([], security.scan_native(single, "dir"))
            example.write_text('STRIPE_SECRET="' + synthetic("sk_" + "live_", 32) + '"\n', encoding="utf-8")
            self.assertTrue(security.scan_native(single, "dir"))
            malformed = root / "invalid.toml"
            malformed.write_text("[invalid", encoding="utf-8")
            for source, config in ((single, malformed), (root / "missing", None)):
                with self.assertRaises(security.SecretFailure):
                    security.scan_native(source, "dir", config=config)

    def test_reports_and_exit_status_fail_closed_without_secret_output(self):
        root = security.ROOT
        fixture = {"RuleID": "github-pat", "File": "app/probe.php", "StartLine": 1, "Commit": "",
                   "Secret": "SENSITIVE-SENTINEL", "Match": "PRIVATE-CONTEXT", "Email": "PRIVATE-ACTOR"}
        result = subprocess.CompletedProcess([], 10, json.dumps([fixture]).encode(), b"")
        sanitized = security.parse_report(result, root, {"app/probe.php"})
        self.assertEqual([{"rule": "github-pat", "path": "app/probe.php", "line": 1, "commit": ""}], sanitized)
        for code, data, error in ((0, b"invalid", b""), (1, b"[]", b""), (10, b"[]", b""),
                                   (0, result.stdout, b""), (10, result.stdout, b"ERR SENSITIVE-SENTINEL")):
            with self.subTest(code=code):
                with self.assertRaises(security.SecretFailure) as failure:
                    security.parse_report(subprocess.CompletedProcess([], code, data, error), root)
                self.assertNotIn("SENSITIVE-SENTINEL", str(failure.exception))
        for outcome, expected in (([], 0), (sanitized, 1), (security.SecretFailure("Safe failure."), 2),
                                  (RuntimeError("SENSITIVE-SENTINEL"), 2)):
            with redirect_stdout(StringIO()) as output, redirect_stderr(StringIO()) as error:
                with patch.object(security, "tree", side_effect=outcome if isinstance(outcome, Exception) else None,
                                  return_value=(outcome, 1, "synthetic scope")):
                    self.assertEqual(expected, security.main(["tree"]))
                self.assertNotIn("SENSITIVE-SENTINEL", output.getvalue() + error.getvalue())

    def test_missing_tools_wrong_hashes_timeouts_and_missing_history_are_errors(self):
        with patch.object(security, "EXECUTABLE", Path("missing-secret-scanner")):
            with self.assertRaises(security.SecretFailure):
                security.verify_tool()
        with patch.object(security, "platform_pin", return_value={"binarySha256": "0" * 64}):
            with self.assertRaises(security.SecretFailure):
                security.verify_tool()
        with patch.object(security.subprocess, "run", side_effect=subprocess.TimeoutExpired("probe", 1)):
            with self.assertRaises(security.SecretFailure):
                security.run(["probe"])
        with tempfile.TemporaryDirectory(prefix="intelledger-secret-empty-") as directory:
            with self.assertRaises(security.SecretFailure):
                security.history(Path(directory))
            security.git(Path(directory), "init", "--quiet")
            with self.assertRaises(security.SecretFailure):
                security.history(Path(directory))
        with self.assertRaises(security.SecretFailure):
            security.normalize_rules(b"changed upstream rules")


if __name__ == "__main__":
    unittest.main(verbosity=1)
