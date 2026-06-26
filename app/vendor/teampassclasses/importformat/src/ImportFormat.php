<?php

declare(strict_types=1);

/**
 * Teampass - a collaborative passwords manager.
 * ---
 * This file is part of the TeamPass project.
 *
 * TeamPass is free software: you can redistribute it and/or modify it
 * under the terms of the GNU General Public License as published by
 * the Free Software Foundation, version 3 of the License.
 *
 * TeamPass is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program. If not, see <https://www.gnu.org/licenses/>.
 *
 * Certain components of this file may be under different licenses. For
 * details, see the `licenses` directory or individual file headers.
 * ---
 * @file      ImportFormat.php
 * @author    Nils Laumaillé (nils@teampass.net)
 * @copyright 2009-2026 Teampass.net
 * @license   GPL-3.0
 * @see       https://www.teampass.net
 */

namespace TeampassClasses\ImportFormat;

/**
 * Normalises third-party password-manager exports into TeamPass' internal
 * import model.
 *
 * Each parser turns a raw export (Bitwarden JSON, LastPass CSV, 1Password CSV,
 * KeePassXC CSV) into a flat list of associative arrays sharing the exact same
 * shape as a TeamPass CSV import row, so the existing import pipeline
 * (folder creation + item creation + finalization) can be reused unchanged.
 *
 * A normalised row has the keys:
 *   - label       (string) item title
 *   - login       (string) username
 *   - pwd         (string) password
 *   - url         (string) URL
 *   - description (string) notes
 *   - folder      (string) folder path, "/" or "\" delimited (empty = root)
 *
 * The class is pure: it has no database, session or filesystem dependency,
 * which keeps it fully unit-testable.
 */
class ImportFormat
{
    /**
     * Supported source formats.
     */
    public const FORMAT_BITWARDEN  = 'bitwarden';
    public const FORMAT_LASTPASS   = 'lastpass';
    public const FORMAT_ONEPASSWORD = '1password';
    public const FORMAT_KEEPASSXC  = 'keepassxc';

    /**
     * Return the list of supported format identifiers.
     *
     * @return array<int,string>
     */
    public static function supportedFormats(): array
    {
        return [
            self::FORMAT_BITWARDEN,
            self::FORMAT_LASTPASS,
            self::FORMAT_ONEPASSWORD,
            self::FORMAT_KEEPASSXC,
        ];
    }

    /**
     * Dispatch parsing to the adapter matching the requested format.
     *
     * @param string $format  One of the FORMAT_* constants.
     * @param string $content Raw file content.
     *
     * @return array{error:bool,message:string,items:array<int,array<string,string>>}
     */
    public static function parse(string $format, string $content): array
    {
        switch (strtolower(trim($format))) {
            case self::FORMAT_BITWARDEN:
                return self::parseBitwarden($content);
            case self::FORMAT_LASTPASS:
                return self::parseLastpass($content);
            case self::FORMAT_ONEPASSWORD:
                return self::parseOnePassword($content);
            case self::FORMAT_KEEPASSXC:
                return self::parseKeepassxc($content);
            default:
                return self::error('import_error_unknown_format');
        }
    }

    /**
     * Parse a Bitwarden unencrypted JSON export (personal vault).
     *
     * Structure (relevant subset):
     *   {
     *     "encrypted": false,
     *     "folders": [ { "id": "<uuid>", "name": "Folder/Sub" } ],
     *     "items":   [ { "type": 1, "name": "...", "notes": "...",
     *                    "folderId": "<uuid|null>",
     *                    "login": { "username": "...", "password": "...",
     *                               "uris": [ { "uri": "https://..." } ] } } ]
     *   }
     *
     * @param string $content Raw JSON.
     *
     * @return array{error:bool,message:string,items:array<int,array<string,string>>}
     */
    public static function parseBitwarden(string $content): array
    {
        $data = json_decode($content, true);
        if (!is_array($data) || !isset($data['items']) || !is_array($data['items'])) {
            return self::error('import_error_invalid_structure');
        }

        // Encrypted exports cannot be read (no master key available here).
        if (isset($data['encrypted']) && $data['encrypted'] === true) {
            return self::error('import_error_encrypted_export');
        }

        // Build folder map: id => name (path).
        $folderMap = [];
        if (isset($data['folders']) && is_array($data['folders'])) {
            foreach ($data['folders'] as $folder) {
                if (isset($folder['id'], $folder['name'])) {
                    $folderMap[(string) $folder['id']] = (string) $folder['name'];
                }
            }
        }

        $items = [];
        foreach ($data['items'] as $entry) {
            if (!is_array($entry)) {
                continue;
            }

            $login    = (isset($entry['login']) && is_array($entry['login'])) ? $entry['login'] : [];
            $username = isset($login['username']) ? (string) $login['username'] : '';
            $password = isset($login['password']) ? (string) $login['password'] : '';

            // First URI is used as the item URL.
            $url = '';
            if (isset($login['uris']) && is_array($login['uris']) && isset($login['uris'][0]['uri'])) {
                $url = (string) $login['uris'][0]['uri'];
            }

            $notes = isset($entry['notes']) ? (string) $entry['notes'] : '';

            // Preserve data carried by non-login item types (card, identity, TOTP)
            // by appending it to the notes so nothing is silently lost.
            $notes = self::appendBitwardenExtras($entry, $login, $notes);

            $folderName = '';
            if (isset($entry['folderId']) && $entry['folderId'] !== null
                && isset($folderMap[(string) $entry['folderId']])
            ) {
                $folderName = $folderMap[(string) $entry['folderId']];
            }

            $items[] = self::row(
                isset($entry['name']) ? (string) $entry['name'] : '',
                $username,
                $password,
                $url,
                $notes,
                $folderName
            );
        }

        return self::success($items);
    }

    /**
     * Parse a LastPass CSV export.
     *
     * Header: url,username,password,totp,extra,name,grouping,fav
     *   - name     => label
     *   - grouping => folder path (backslash "\" delimited, e.g. "A\B")
     *   - extra    => notes
     * Secure notes use the sentinel url "http://sn".
     *
     * @param string $content Raw CSV.
     *
     * @return array{error:bool,message:string,items:array<int,array<string,string>>}
     */
    public static function parseLastpass(string $content): array
    {
        $rows = self::csvToAssoc($content);
        if ($rows === null) {
            return self::error('import_error_invalid_structure');
        }

        $items = [];
        foreach ($rows as $row) {
            $url = self::pick($row, ['url']);
            // LastPass marks secure notes with the "http://sn" sentinel URL.
            if (strcasecmp($url, 'http://sn') === 0) {
                $url = '';
            }

            $items[] = self::row(
                self::pick($row, ['name']),
                self::pick($row, ['username']),
                self::pick($row, ['password']),
                $url,
                self::pick($row, ['extra', 'notes']),
                self::pick($row, ['grouping', 'group'])
            );
        }

        return self::success($items);
    }

    /**
     * Parse a 1Password CSV export (1Password 8 "Export to CSV").
     *
     * Header (column order may vary): Title,Url,Username,Password,OTPAuth,
     *   Favorite,Archived,Tags,Notes
     *   - Title => label
     *   - Tags  => folder (first tag is used when present, else flat)
     *
     * Mapping is header-keyed (case-insensitive) so column reordering between
     * 1Password versions does not break the import.
     *
     * @param string $content Raw CSV.
     *
     * @return array{error:bool,message:string,items:array<int,array<string,string>>}
     */
    public static function parseOnePassword(string $content): array
    {
        $rows = self::csvToAssoc($content);
        if ($rows === null) {
            return self::error('import_error_invalid_structure');
        }

        $items = [];
        foreach ($rows as $row) {
            // Use the first tag as a folder when available (1Password CSV has no
            // vault/folder column). Tags are comma separated.
            $tags   = self::pick($row, ['tags', 'tag']);
            $folder = '';
            if ($tags !== '') {
                $parts  = explode(',', $tags);
                $folder = trim($parts[0]);
            }

            $items[] = self::row(
                self::pick($row, ['title', 'name']),
                self::pick($row, ['username', 'login']),
                self::pick($row, ['password']),
                self::pick($row, ['url', 'website']),
                self::pick($row, ['notes', 'note']),
                $folder
            );
        }

        return self::success($items);
    }

    /**
     * Parse a KeePassXC CSV export.
     *
     * Header: "Group","Title","Username","Password","URL","Notes","TOTP",
     *         "Icon","Last Modified","Created"
     *   - Group => folder path ("/" delimited, usually prefixed with "Root/")
     *
     * The leading "Root/" (or bare "Root") segment is stripped so items are not
     * nested under a redundant top-level folder.
     *
     * @param string $content Raw CSV.
     *
     * @return array{error:bool,message:string,items:array<int,array<string,string>>}
     */
    public static function parseKeepassxc(string $content): array
    {
        $rows = self::csvToAssoc($content);
        if ($rows === null) {
            return self::error('import_error_invalid_structure');
        }

        $items = [];
        foreach ($rows as $row) {
            $group = self::pick($row, ['group', 'grouping']);
            $group = self::stripKeepassRoot($group);

            $items[] = self::row(
                self::pick($row, ['title', 'name']),
                self::pick($row, ['username', 'login']),
                self::pick($row, ['password']),
                self::pick($row, ['url']),
                self::pick($row, ['notes', 'note']),
                $group
            );
        }

        return self::success($items);
    }

    // ---------------------------------------------------------------------
    // Internal helpers
    // ---------------------------------------------------------------------

    /**
     * Append Bitwarden extra fields (card, identity, TOTP) to the notes so the
     * information is not lost when the item is not a plain login.
     *
     * @param array<string,mixed> $entry The Bitwarden item.
     * @param array<string,mixed> $login The login sub-object.
     * @param string              $notes Current notes value.
     *
     * @return string Notes possibly enriched with extra fields.
     */
    private static function appendBitwardenExtras(array $entry, array $login, string $notes): string
    {
        $extra = [];

        // TOTP secret.
        if (isset($login['totp']) && $login['totp'] !== '' && $login['totp'] !== null) {
            $extra[] = 'TOTP: ' . (string) $login['totp'];
        }

        // Card data.
        if (isset($entry['card']) && is_array($entry['card'])) {
            foreach ($entry['card'] as $key => $value) {
                if ($value !== '' && $value !== null && !is_array($value)) {
                    $extra[] = ucfirst((string) $key) . ': ' . (string) $value;
                }
            }
        }

        // Identity data.
        if (isset($entry['identity']) && is_array($entry['identity'])) {
            foreach ($entry['identity'] as $key => $value) {
                if ($value !== '' && $value !== null && !is_array($value)) {
                    $extra[] = ucfirst((string) $key) . ': ' . (string) $value;
                }
            }
        }

        if ($extra === []) {
            return $notes;
        }

        $suffix = implode("\n", $extra);
        return $notes === '' ? $suffix : $notes . "\n" . $suffix;
    }

    /**
     * Remove a leading "Root/" (or bare "Root") segment from a KeePassXC group.
     *
     * @param string $group The raw group path.
     *
     * @return string The group path without the redundant root segment.
     */
    private static function stripKeepassRoot(string $group): string
    {
        $group = trim($group);
        if ($group === '' || strcasecmp($group, 'Root') === 0) {
            return '';
        }
        if (stripos($group, 'Root/') === 0) {
            return substr($group, 5);
        }
        return $group;
    }

    /**
     * Parse CSV content into a list of header-keyed associative rows.
     *
     * Keys are lower-cased and trimmed. Uses a stream so quoted fields with
     * embedded newlines are handled correctly. Returns null when the content
     * has no usable header line.
     *
     * @param string $content Raw CSV.
     *
     * @return array<int,array<string,string>>|null
     */
    private static function csvToAssoc(string $content): ?array
    {
        // Strip a UTF-8 BOM if present.
        $content = preg_replace('/^\xEF\xBB\xBF/', '', $content) ?? $content;

        $stream = fopen('php://temp', 'r+');
        if ($stream === false) {
            return null;
        }
        fwrite($stream, $content);
        rewind($stream);

        $header = fgetcsv($stream, 0, ',', '"', '\\');
        if ($header === false || $header === null || $header === [null]) {
            fclose($stream);
            return null;
        }

        // Normalise header keys.
        $keys = [];
        foreach ($header as $col) {
            $keys[] = strtolower(trim((string) $col));
        }
        $colCount = count($keys);

        $rows = [];
        while (($data = fgetcsv($stream, 0, ',', '"', '\\')) !== false) {
            // Skip fully empty lines.
            if ($data === [null]) {
                continue;
            }
            // Pad / trim the row to the header length.
            $data = array_slice(array_pad($data, $colCount, ''), 0, $colCount);
            $assoc = [];
            foreach ($keys as $i => $key) {
                if ($key === '') {
                    continue;
                }
                $assoc[$key] = (string) ($data[$i] ?? '');
            }
            $rows[] = $assoc;
        }
        fclose($stream);

        return $rows;
    }

    /**
     * Return the first non-empty value among the given candidate keys.
     *
     * @param array<string,string> $row  The associative row.
     * @param array<int,string>    $keys Candidate keys (already lower-case).
     *
     * @return string The trimmed value or '' when none match.
     */
    private static function pick(array $row, array $keys): string
    {
        foreach ($keys as $key) {
            if (isset($row[$key]) && trim($row[$key]) !== '') {
                return trim($row[$key]);
            }
        }
        return '';
    }

    /**
     * Build a normalised import row.
     *
     * @return array<string,string>
     */
    private static function row(
        string $label,
        string $login,
        string $pwd,
        string $url,
        string $description,
        string $folder
    ): array {
        return [
            'label'       => trim($label),
            'login'       => trim($login),
            'pwd'         => $pwd,
            'url'         => trim($url),
            'description' => $description,
            'folder'      => trim($folder),
        ];
    }

    /**
     * Build a success result, dropping rows without a label.
     *
     * @param array<int,array<string,string>> $items
     *
     * @return array{error:bool,message:string,items:array<int,array<string,string>>}
     */
    private static function success(array $items): array
    {
        $items = array_values(array_filter(
            $items,
            static fn (array $item): bool => $item['label'] !== ''
        ));

        return [
            'error'   => false,
            'message' => '',
            'items'   => $items,
        ];
    }

    /**
     * Build an error result.
     *
     * @param string $messageKey Language key describing the error.
     *
     * @return array{error:bool,message:string,items:array<int,array<string,string>>}
     */
    private static function error(string $messageKey): array
    {
        return [
            'error'   => true,
            'message' => $messageKey,
            'items'   => [],
        ];
    }
}
