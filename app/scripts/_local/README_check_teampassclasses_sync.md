# TeamPass Libraries Synchronization Checker

## Purpose

This script compares TeamPass custom libraries between the source directory (`includes/libraries/teampassclasses/`) and the vendor directory (`vendor/teampassclasses/`) to detect any discrepancies.

This is useful when modifications are accidentally made directly in the vendor directory instead of the source directory, which can cause issues during `composer update`.

## Usage

### Basic check
```bash
php scripts/check_teampassclasses_sync.php
```

This will:
- Compare all PHP files between source and vendor directories
- Display a summary of differences
- Show which files differ (with MD5 hashes)

### Verbose mode
```bash
php scripts/check_teampassclasses_sync.php --verbose
```

This will additionally:
- Show a preview of the actual differences (first 10 lines)
- Display line-by-line diffs with color coding

## Output

The script uses color-coded output:
- 🟢 **Green**: All libraries are synchronized
- 🔴 **Red**: Differences found, files missing, or content differs
- 🟡 **Yellow**: Warnings and file differences
- 🔵 **Blue**: Library names being checked

## Exit codes

- `0`: All libraries are synchronized
- `1`: Differences found or error occurred

## Workflow

### If differences are found:

1. **Review the differences** shown by the script

2. **Determine the correct version**:
   - If changes in **vendor** are correct → copy back to source
   - If changes in **source** are correct → update vendor

3. **Copy changes back to source** (if vendor was modified):
   ```bash
   cp vendor/teampassclasses/<library>/src/<File>.php \
      includes/libraries/teampassclasses/<library>/src/
   ```

4. **Update vendor from source**:
   ```bash
   composer update teampassclasses/<library>
   ```

5. **Verify synchronization**:
   ```bash
   php scripts/check_teampassclasses_sync.php
   ```

## Examples

### Example 1: All synchronized
```
TeamPass Libraries Synchronization Checker
======================================================================

Found 13 libraries in source directory
Found 13 libraries in vendor directory

...

✓ All libraries are synchronized!
```

### Example 2: Differences found
```
TeamPass Libraries Synchronization Checker
======================================================================

...

✗ Found differences in 1 library(ies)

Library: ldapextra
----------------------------------------------------------------------
  • src/ActiveDirectoryExtra.php
    Status: Content differs
    Source hash: 151eec5bf9941522beaa89e11c3d4082
    Vendor hash: c3b0ed52743dad703481ce444af5e3f7
```

## Integration with Git

You can add this check to your pre-commit hook or CI/CD pipeline:

```bash
# In .git/hooks/pre-commit or CI script
php scripts/check_teampassclasses_sync.php
if [ $? -ne 0 ]; then
    echo "TeamPass libraries are out of sync!"
    echo "Please synchronize them before committing."
    exit 1
fi
```

## Best Practices

1. **Always modify source files first**: Make changes in `includes/libraries/teampassclasses/`
2. **Run composer update after changes**: `composer update teampassclasses/<library>`
3. **Check before committing**: Run this script before committing changes
4. **Never modify vendor directly**: Vendor directory is managed by Composer

## Technical Details

The script:
- Compares MD5 hashes of all PHP files
- Recursively scans all subdirectories
- Reports missing files in either directory
- Shows line-by-line diffs in verbose mode
- Uses color-coded terminal output for readability
