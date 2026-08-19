---
paths:
  - phpunit.xml
---

# General

## Isolate tests from cached local configuration
Keep APP_CONFIG_CACHE pointed to a test-only cache path in phpunit.xml so feature tests always honor SQLite :memory: settings and never run RefreshDatabase against the local MySQL database.
