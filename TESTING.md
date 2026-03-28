# local_gradefiller – Developer guide

## Running tests locally

### Prerequisites

The plugin must be installed inside a live Moodle 4.5+ instance:

```
{moodle_root}/local/gradefiller/
```

### PHPUnit

Initialise the test database once (from the Moodle root):

```bash
php admin/tool/phpunit/cli/init.php
```

Run all plugin tests:

```bash
# From the Moodle root
vendor/bin/phpunit --configuration local/gradefiller/phpunit.xml

# Or via the built-in runner
php admin/tool/phpunit/cli/util.php --run --filter local_gradefiller
```

Run a single test class:

```bash
vendor/bin/phpunit --configuration local/gradefiller/phpunit.xml \
  --filter manager_test
```

Generate an HTML coverage report (requires Xdebug or PCOV):

```bash
vendor/bin/phpunit --configuration local/gradefiller/phpunit.xml \
  --coverage-html local/gradefiller/coverage-html
```

### Behat

Initialise Behat (from the Moodle root):

```bash
php admin/tool/behat/cli/init.php
```

Run all plugin scenarios:

```bash
php admin/tool/behat/cli/run.php --tags=@local_gradefiller
```

Run a single scenario by tag:

```bash
php admin/tool/behat/cli/run.php \
  --tags=@local_gradefiller_upload \
  --profile=chrome
```

---

## CI/CD

The `.github/workflows/ci.yml` pipeline runs on every push and pull request to
`main` / `develop` and on `feature/**` / `fix/**` branches.

### Jobs

| Job | When | What |
|-----|------|------|
| **lint** | always | PHP syntax check + PHPCS (Moodle coding standard) |
| **phpunit** | after lint | PHPUnit on Moodle 4.5 × PHP 8.3 × PostgreSQL |
| **behat** | after phpunit | Acceptance tests with Chrome (Selenium) — disabled until CI server ready |
| **security** | after lint | `composer audit` dependency vulnerability check |

Coverage reports are uploaded to [Codecov](https://codecov.io) automatically.

### Secrets / environment variables

No secrets are required for the basic pipeline. To enable Codecov upload,
add a repository secret named `CODECOV_TOKEN` if your repository is private.

---

## Test structure

```
tests/
├── bootstrap.php                              # Locates Moodle root; loaded by phpunit.xml
├── manager_test.php                           # Integration tests – manager (needs DB)
├── download_handler_test.php                  # Unit tests – download_handler utility
├── file_handler_test.php                      # Unit tests – file_handler utility
├── format_university_standard_test.php        # Unit tests – Apogée spreadsheet format
├── source_grade_source_offlinequiz_test.php   # Unit tests – offlinequiz grade driver
├── source_papergrade_test.php                 # Integration tests – papergrade grade driver
├── fixtures/                                  # Optional XLSX fixture for spreadsheet tests
│   └── sample_apogee.xlsx
└── behat/
    ├── gradefiller.feature                    # Acceptance scenarios
    └── behat_local_gradefiller.php            # Custom Behat step definitions
```

### Adding a new test

1. Create `tests/my_class_test.php` extending `\advanced_testcase`.
2. Call `$this->resetAfterTest(true)` in `setUp()`.
3. Annotate the class with `@covers \local_gradefiller\my_class`.
4. The file is picked up automatically by `phpunit.xml`.

### XLSX fixture

Some spreadsheet tests (`format_university_standard_test`) require a real `.xlsx`
file placed at `tests/fixtures/sample_apogee.xlsx`. The file must follow the
Apogée/university-standard layout: 17 header rows, identifiers in column A from
row 18 onwards, grades in column E. If the fixture is absent, those tests are
automatically skipped.
