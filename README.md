# PHP Checker

**A PHP coding rules checker powered by PHPStan.**

PHP Checker is an extensible command-line tool for analyzing PHP source code using PHPStan and custom coding rules. It helps developers identify coding issues, enforce documentation conventions, and review violations directly from the terminal.

It also provides Git diff integration, allowing developers to focus on violations introduced by recently changed code.

## Features

* **Static analysis** powered by PHPStan.
* **Custom coding rules** that can be extended to enforce project-specific conventions.
* **Rule selection** to enable or skip individual rules.
* **Git diff integration** to report violations on added or modified lines.
* **Patch output** to display Git diffs directly in the terminal.
* **Colored console output** for readable diagnostics and status messages.
* **Relative file paths** for easier navigation within a project.
* **Composer integration** for installation and dependency management.

## Requirements

* PHP 8.2 or later.
* Composer 2.x.
* Git installed and available in `PATH` for Git integration.

The project uses the following core dependencies:

* PHPStan `^2.3`
* Symfony Console `^7.0`
* Symfony Process `^7.0`

## Installation

### Install from GitHub

PHP Checker is not yet published on Packagist. You can install it directly from GitHub using Composer.

Add the repository to your project's `composer.json`:

```json
{
    "repositories": [
        {
            "type": "vcs",
            "url": "https://github.com/FlexiTechLab/php-checker"
        }
    ],
    "require-dev": {
        "flexitechlab/php-checker": "dev-main@dev"
    }
}
```

Then install the dependencies:

```bash
composer update flexitechlab/php-checker
```

Replace `dev-main@dev` with a tagged version when versioned releases become available.

### Verify the installation

After installation, the executable should be available at:

```bash
vendor/bin/php-checker
```

Display available commands:

```bash
vendor/bin/php-checker list
```

Display help for the `check` command:

```bash
vendor/bin/php-checker help check
```

### Install from a local checkout

If you are developing PHP Checker locally, you can also configure Composer to use a local checkout:

```json
{
    "repositories": [
        {
            "type": "path",
            "url": "../php-checker",
            "options": {
                "symlink": true
            }
        }
    ],
    "require-dev": {
        "flexitechlab/php-checker": "*"
    }
}
```

Adjust the path to match the location of your local PHP Checker repository.

**Note:** PHP Checker must have a valid `composer.json`, a compatible PHP version constraint, and a correctly configured executable under its `bin` field.

## Usage

### Analyze a project

Run the checker from the project root:

```bash
vendor/bin/php-checker check
```

Analyze a specific directory:

```bash
vendor/bin/php-checker check ./src
```

The checker reports detected violations and returns an appropriate exit code, making it suitable for local development and CI pipelines.

### Check only changed lines

Use `--diff` to report violations located on added or modified lines in the local Git working tree:

```bash
vendor/bin/php-checker check --diff
```

This is useful when working on an existing codebase where you want to focus on issues related to your current changes.

### Display Git patches

Use `--show-diff` to display patch content in the terminal alongside reported violations:

```bash
vendor/bin/php-checker check --show-diff
```

Combine both options to filter violations to changed lines and display the corresponding Git patches:

```bash
vendor/bin/php-checker check --diff --show-diff
```

Git integration uses the local Git repository and does not require GitHub authentication or API access.

**Note:** Git diff output depends on the local repository state. Changes to tracked files are compared against `HEAD`. Untracked files may require separate handling, depending on the operation.

## Coding Rules

PHP Checker uses rule identifiers to identify individual coding rules.

For example, the `phpdoc.method` rule requires methods to have PHPDoc documentation.

Example diagnostic:

```text
src/Example.php:15
[phpdoc.method] Method save() must have PHPDoc.
```

### Configure enabled rules

Run `rules:configure` to choose which rules the `check` command runs:

```bash
vendor/bin/php-checker rules:configure
```

On a real terminal the command shows an interactive checklist of every
registered rule. Use the arrow keys (or `k`/`j`) to move, space to toggle the
highlighted rule, `a` to toggle all rules, and enter to save. Press `q` or
Escape to cancel without changing anything. Rules that are already enabled are
shown pre-selected.

When the terminal does not support interactive input (for example when the
command runs from a script, with `--no-interaction`, or on a platform without
`stty`), the command falls back to entering multiple rule identifiers
separated by commas. Pressing enter keeps the previously selected rules, and
entering `none` disables every rule.

The selection is saved to `php-checker.json` at the project root:

```json
{
    "version": 1,
    "enabledRules": [
        "phpdoc.method",
        "typeDeclaration.return"
    ]
}
```

The `check` command runs **only** the rules listed in `enabledRules`; the
list is an allowlist. PHPStan's built-in rules are not executed, so checks
that are not explicitly enabled do not produce violations. An empty list runs
no rules at all, which lets you disable rule checking entirely without
deleting the configuration file.

Unknown rule identifiers are rejected with a clear error message, so a typo
cannot silently disable a rule. A missing or malformed configuration file is
reported with a clear error message as well.

### Enable specific rules programmatically

Use the fluent API to select the rules to run:

```php
use PhpChecker\PhpChecker;
use PhpChecker\Engine\PhpStan\PhpStanRunner;
use PhpChecker\Rules\RuleRegistry;

$checker = new PhpChecker(
    new PhpStanRunner(),
    new RuleRegistry(),
);

$violations = $checker
    ->path('./src')
    ->useRules(['phpdoc.method'])
    ->run();
```

Adjust the imports to match the classes and namespaces provided by your installed version.

### Run PHPStan's built-in rules

Only the configured custom rules run by default. Call `level()` to explicitly
opt into PHPStan's built-in analysis at a given level as well:

```php
$violations = $checker
    ->path('./src')
    ->useRules(['typeDeclaration.parameter'])
    ->level(8)
    ->run();
```

When a built-in level is enabled, only the built-in counterparts of the
enabled custom rules are suppressed, so the same problem is not reported
twice while the rest of PHPStan's analysis is preserved.

### Skip rules

You can exclude selected rules:

```php
$violations = $checker
    ->path('./src')
    ->useRules(['phpdoc.method', 'typeDeclaration.return'])
    ->skipRules(['phpdoc.method'])
    ->run();
```

An empty rule selection runs no custom rules. Rule selection and exclusion behavior is controlled by the checker configuration loaded from `php-checker.json`.

## Console Output

PHP Checker uses colored terminal output to distinguish successful checks from detected violations.

Example:

```text
PASS  No violations found.
```

When violations are detected:

```text
FAIL  1 violation(s) found.

src/Example.php:15
    ✘ [phpdoc.method] Method save() must have PHPDoc.
```

File paths are displayed relative to the project root whenever the project root is available to the reporter.

## Development

### Clone the repository

```bash
git clone https://github.com/FlexiTechLab/php-checker.git
cd php-checker
```

### Install dependencies

```bash
composer install
```

### Run tests

```bash
vendor/bin/phpunit
```

### Run static analysis

```bash
vendor/bin/phpstan analyse
```

Use the repository's configured PHPStan configuration file if the default configuration is not automatically discovered.

Before submitting a contribution, ensure that the relevant tests and static analysis checks pass.

## Contributing

Contributions are welcome, including bug reports, documentation improvements, new coding rules, tests, and feature enhancements.

### How to contribute

1. Fork the repository.
2. Create a feature branch.
3. Implement your changes.
4. Add or update tests where appropriate.
5. Run the test suite and static analysis.
6. Commit your changes with a descriptive commit message.
7. Open a pull request describing the changes and their motivation.

### Contribution guidelines

* Follow the existing PHP coding style and project architecture.
* Use strict types where appropriate.
* Provide explicit parameter and return types.
* Add PHPDoc generic annotations when required by static analysis.
* Give coding rules stable, descriptive identifiers.
* Avoid breaking existing public APIs unless the change is necessary and documented.
* Keep changes focused and include tests for new behavior.

For bug reports, include the PHP version, relevant command, expected behavior, actual behavior, and a reproducible example whenever possible.

## License

PHP Checker is released under the [MIT License](LICENSE).

The MIT License permits use, copying, modification, distribution, sublicensing, and sale of the software, subject to its notice and warranty-disclaimer terms.

### Third-party dependencies and licenses

PHP Checker relies on open-source packages. Their licenses remain applicable to the respective packages and must be respected when redistributing them.

| Package                                                 | Purpose                                       | License      |
| ------------------------------------------------------- | --------------------------------------------- | ------------ |
| [PHPStan](https://github.com/phpstan/phpstan)           | Static analysis engine                        | MIT          |
| [Symfony Console](https://github.com/symfony/console)   | CLI commands and terminal output              | MIT          |
| [Symfony Process](https://github.com/symfony/process)   | Execute local Git processes                   | MIT          |
| [nikic/PHP-Parser](https://github.com/nikic/PHP-Parser) | PHP syntax parsing, including AST-based rules | BSD-3-Clause |
| [PHPUnit](https://github.com/sebastianbergmann/phpunit) | Development and testing                       | BSD-3-Clause |

The table lists core and commonly relevant packages, not necessarily every transitive dependency installed by Composer. The exact dependency versions and metadata are recorded in `composer.lock` when that file is committed.

Please consult each package's official repository and license file for its complete terms.

## Acknowledgements

PHP Checker builds on the PHP ecosystem's open-source tooling, especially PHPStan and Symfony Console.

Thanks to everyone who contributes through code, documentation, testing, and feedback.

---

Maintained by [FlexiTechLab](https://github.com/FlexiTechLab).
