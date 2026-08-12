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
 * @file      emails_templates.queries.php
 * @author    Nils Laumaillé (nils@teampass.net)
 * @copyright 2009-2026 Teampass.net
 * @license   GPL-3.0
 * @see       https://www.teampass.net
 *
 * Administration of the email templates: an administrator customizes the
 * subject and the body of any email TeamPass sends, per language, without
 * touching the shipped language files.
 *
 * The stored rows are a diff over those files — an empty table reproduces the
 * default behaviour exactly. `Language::get()` applies them transparently, so
 * this handler only reads and writes the diff; it never renders an email.
 */

use TeampassClasses\SessionManager\SessionManager;
use Symfony\Component\HttpFoundation\Request as SymfonyRequest;
use TeampassClasses\Language\Language;
use TeampassClasses\PerformChecks\PerformChecks;
use TeampassClasses\ConfigManager\ConfigManager;
use TeampassClasses\EmailService\EmailSettings;
use TeampassClasses\EmailService\EmailService;
use voku\helper\AntiXSS;

// Load functions
require_once 'main.functions.php';
require_once __DIR__ . '/emails_templates_logic.php';

// init
loadClasses('DB');
$session = SessionManager::getSession();
$request = SymfonyRequest::createFromGlobals();
$lang = new Language($session->get('user-language') ?? 'english');

// Load config
$configManager = new ConfigManager();
$SETTINGS = $configManager->getAllSettings();

// Do checks
$checkUserAccess = new PerformChecks(
    dataSanitizer(
        [
            'type' => htmlspecialchars($request->request->get('type', ''), ENT_QUOTES, 'UTF-8'),
        ],
        [
            'type' => 'trim|escape',
        ],
    ),
    [
        'user_id' => returnIfSet($session->get('user-id'), null),
        'user_key' => returnIfSet($session->get('key'), null),
    ]
);
// Handle the case
echo $checkUserAccess->caseHandler();
if (
    $checkUserAccess->checkSession() === false
    || $checkUserAccess->userAccessPage('emails_templates') === false
) {
    // Not allowed page
    $session->set('system-error_code', ERR_NOT_ALLOWED);
    include TEAMPASS_ROOT . '/public/error.php';
    exit;
}

// Define Timezone
date_default_timezone_set($SETTINGS['timezone'] ?? 'UTC');

// Set header properties
header('Content-type: text/html; charset=utf-8');
header('Cache-Control: no-cache, no-store, must-revalidate');
error_reporting(E_ERROR);

// --------------------------------- //

// Read POST variables
$post_type = (string) $request->request->filter('type', '', FILTER_SANITIZE_SPECIAL_CHARS);
$post_key = (string) $request->request->filter('key', '', FILTER_SANITIZE_SPECIAL_CHARS);
$post_data = (string) $request->request->filter('data', '', FILTER_SANITIZE_FULL_SPECIAL_CHARS);

// Check KEY on every action
if ($post_key !== $session->get('key')) {
    echo (string) prepareExchangedData(
        ['error' => true, 'message' => $lang->get('key_is_not_correct')],
        'encode'
    );
    exit;
}

/**
 * Maximum size accepted for a subject or a body, in bytes.
 * Well below the MEDIUMTEXT limit; a template far larger than this is a mistake.
 */
const EMAILS_TEMPLATES_MAX_LENGTH = 65536;

$userId = (int) $session->get('user-id');
$antiXss = new AntiXSS();

/** @var array<string, array<string, mixed>> $emailsCatalog */
$emailsCatalog = require TEAMPASS_APP . '/config/emails_templates.php';

/**
 * Returns the list of languages an administrator may customize.
 *
 * @return array<int, array{name: string, label: string}>
 */
function emailsTemplatesAvailableLanguages(): array
{
    $languages = [];
    $rows = DB::query(
        'SELECT name, label FROM ' . prefixTable('languages') . ' ORDER BY label ASC'
    );
    foreach ($rows as $row) {
        $languages[] = [
            'name' => (string) $row['name'],
            'label' => (string) $row['label'],
        ];
    }

    return $languages;
}

/**
 * Tells whether a language name is one of the installed languages.
 *
 * @param string $language Language name, as stored in the languages table
 *
 * @return bool
 */
function emailsTemplatesLanguageExists(string $language): bool
{
    if ($language === '') {
        return false;
    }

    return (int) DB::queryFirstField(
        'SELECT COUNT(*) FROM ' . prefixTable('languages') . ' WHERE name = %s',
        $language
    ) > 0;
}

/**
 * Loads the stored customizations for one language, keyed by language key.
 *
 * @param string $language Language name
 *
 * @return array<string, array{content: string, updated_at: int, updated_by: string}>
 */
function emailsTemplatesStoredRows(string $language): array
{
    $stored = [];
    $rows = DB::query(
        'SELECT t.template_key, t.content, t.updated_at, u.login AS updated_by_login
         FROM ' . prefixTable('emails_templates') . ' AS t
         LEFT JOIN ' . prefixTable('users') . ' AS u ON u.id = t.updated_by
         WHERE t.language = %s',
        $language
    );
    foreach ($rows as $row) {
        $stored[(string) $row['template_key']] = [
            'content' => (string) $row['content'],
            'updated_at' => (int) ($row['updated_at'] ?? 0),
            'updated_by' => (string) ($row['updated_by_login'] ?? ''),
        ];
    }

    return $stored;
}

/**
 * Writes a customization, or removes it when the content is empty.
 *
 * An empty content is not a valid override — `Language` ignores it — so it is
 * stored as "no customization at all" instead of an unusable row.
 *
 * @param string $templateKey Language key being customized
 * @param string $language    Language name
 * @param string $content     Normalized content
 * @param int    $userId      Author of the change
 *
 * @return bool True when a customization is now stored
 */
function emailsTemplatesUpsert(string $templateKey, string $language, string $content, int $userId): bool
{
    if ($content === '') {
        DB::delete(
            prefixTable('emails_templates'),
            'template_key = %s AND language = %s',
            $templateKey,
            $language
        );

        return false;
    }

    DB::insertUpdate(
        prefixTable('emails_templates'),
        [
            'template_key' => $templateKey,
            'language' => $language,
            'content' => $content,
            'updated_at' => time(),
            'updated_by' => $userId,
        ]
    );

    return true;
}

switch ($post_type) {
    /*
     * LOAD the page: installed languages and the catalog with, for the
     * requested language, which templates are customized.
     */
    case 'load_templates_list':
        $dataReceived = prepareExchangedData($post_data, 'decode');
        $language = (string) ($dataReceived['language'] ?? '');
        if (emailsTemplatesLanguageExists($language) === false) {
            $language = (string) ($session->get('user-language') ?? 'english');
        }

        $stored = emailsTemplatesStoredRows($language);
        $groups = [];
        foreach ($emailsCatalog as $templateId => $template) {
            $subjectKey = empty($template['subject_key']) === true ? '' : (string) $template['subject_key'];
            $bodyKey = (string) $template['body_key'];

            $groups[(string) $template['group']][] = [
                'id' => (string) $templateId,
                'label' => $lang->get((string) $template['label']),
                'fragment' => ($template['fragment'] ?? false) === true,
                'customized' => isset($stored[$bodyKey]) === true
                    || ($subjectKey !== '' && isset($stored[$subjectKey]) === true),
            ];
        }

        echo (string) prepareExchangedData(
            [
                'error' => false,
                'language' => $language,
                'languages' => emailsTemplatesAvailableLanguages(),
                'groups' => $groups,
            ],
            'encode'
        );
        break;

    /*
     * GET one template: what will be sent, what is shipped, and the tokens.
     */
    case 'get_template':
        $dataReceived = prepareExchangedData($post_data, 'decode');
        $templateId = (string) ($dataReceived['template'] ?? '');
        $language = (string) ($dataReceived['language'] ?? '');

        if (isset($emailsCatalog[$templateId]) === false || emailsTemplatesLanguageExists($language) === false) {
            echo (string) prepareExchangedData(
                ['error' => true, 'message' => $lang->get('error_not_allowed_to')],
                'encode'
            );
            break;
        }

        $template = $emailsCatalog[$templateId];
        $subjectKey = empty($template['subject_key']) === true ? '' : (string) $template['subject_key'];
        $bodyKey = (string) $template['body_key'];

        // Resolved values (customization aware) and shipped values, both in the
        // selected language, so the page can show the diff.
        $targetLang = new Language($language);
        $stored = emailsTemplatesStoredRows($language);
        $englishStored = $language === 'english' ? $stored : emailsTemplatesStoredRows('english');

        $lastUpdate = 0;
        $lastAuthor = '';
        foreach ([$subjectKey, $bodyKey] as $key) {
            if ($key !== '' && isset($stored[$key]) === true && $stored[$key]['updated_at'] > $lastUpdate) {
                $lastUpdate = $stored[$key]['updated_at'];
                $lastAuthor = $stored[$key]['updated_by'];
            }
        }

        // Subject keys are shared between several emails: editing one changes
        // them all, so the page lists the other templates concerned.
        $sharedSubjectWith = [];
        if ($subjectKey !== '') {
            foreach ($emailsCatalog as $otherId => $other) {
                if ($otherId !== $templateId && (string) ($other['subject_key'] ?? '') === $subjectKey) {
                    $sharedSubjectWith[] = $lang->get((string) $other['label']);
                }
            }
        }

        echo (string) prepareExchangedData(
            [
                'error' => false,
                'template' => $templateId,
                'language' => $language,
                'group' => (string) $template['group'],
                'label' => $lang->get((string) $template['label']),
                'description' => $lang->get((string) $template['description']),
                'fragment' => ($template['fragment'] ?? false) === true,
                'subject_key' => $subjectKey,
                'body_key' => $bodyKey,
                'subject_prefix' => (string) ($template['subject_prefix'] ?? ''),
                'subject' => $subjectKey === '' ? '' : $targetLang->get($subjectKey),
                'subject_shipped' => $subjectKey === '' ? '' : $targetLang->getShipped($subjectKey),
                'body' => $targetLang->get($bodyKey),
                'body_shipped' => $targetLang->getShipped($bodyKey),
                'tokens' => array_values((array) $template['tokens']),
                'subject_tokens' => array_values((array) ($template['subject_tokens'] ?? [])),
                'required_tokens' => array_values((array) $template['required_tokens']),
                'customized' => isset($stored[$bodyKey]) === true
                    || ($subjectKey !== '' && isset($stored[$subjectKey]) === true),
                // No row in this language but one in English: the English
                // customization is what will actually be sent.
                'inherited_from_english' => $language !== 'english'
                    && isset($stored[$bodyKey]) === false
                    && isset($englishStored[$bodyKey]) === true,
                'updated_at' => $lastUpdate === 0
                    ? '' : date($SETTINGS['date_format'] . ' ' . $SETTINGS['time_format'], $lastUpdate),
                'updated_by' => $lastAuthor,
                'shared_subject_with' => $sharedSubjectWith,
            ],
            'encode'
        );
        break;

    /*
     * SAVE the subject and/or the body of one template, for one language.
     */
    case 'save_template':
        $dataReceived = prepareExchangedData($post_data, 'decode');
        $templateId = (string) ($dataReceived['template'] ?? '');
        $language = (string) ($dataReceived['language'] ?? '');

        if (isset($emailsCatalog[$templateId]) === false || emailsTemplatesLanguageExists($language) === false) {
            echo (string) prepareExchangedData(
                ['error' => true, 'message' => $lang->get('error_not_allowed_to')],
                'encode'
            );
            break;
        }

        $template = $emailsCatalog[$templateId];
        $subjectKey = empty($template['subject_key']) === true ? '' : (string) $template['subject_key'];
        $bodyKey = (string) $template['body_key'];

        $subject = $subjectKey === ''
            ? '' : emailsTemplatesNormalizeSubject((string) ($dataReceived['subject'] ?? ''));
        $body = emailsTemplatesNormalizeBody((string) ($dataReceived['body'] ?? ''), $antiXss);

        // Size guard, before reaching the column
        if (strlen($subject) > EMAILS_TEMPLATES_MAX_LENGTH || strlen($body) > EMAILS_TEMPLATES_MAX_LENGTH) {
            echo (string) prepareExchangedData(
                ['error' => true, 'message' => $lang->get('emails_templates_error_too_long')],
                'encode'
            );
            break;
        }

        // A body without its required tokens produces an email nobody can act
        // upon: refuse the save rather than storing a broken template.
        $missing = emailsTemplatesMissingTokens($body, (array) $template['required_tokens']);
        if ($body !== '' && count($missing) > 0) {
            echo (string) prepareExchangedData(
                [
                    'error' => true,
                    'message' => $lang->get('emails_templates_error_missing_tokens')
                        . ' ' . implode(' ', $missing),
                ],
                'encode'
            );
            break;
        }

        $customized = emailsTemplatesUpsert($bodyKey, $language, $body, $userId);
        if ($subjectKey !== '') {
            $customized = emailsTemplatesUpsert($subjectKey, $language, $subject, $userId) || $customized;
        }

        // Optional tokens that are no longer used: saved, but worth telling.
        $unused = emailsTemplatesUnusedTokens(
            $body,
            (array) $template['tokens'],
            (array) $template['required_tokens']
        );

        logEvents(
            $SETTINGS,
            'admin_action',
            'at_email_template_updated:' . $templateId . ':' . $language,
            (string) $userId,
            (string) $session->get('user-login')
        );

        echo (string) prepareExchangedData(
            [
                'error' => false,
                'customized' => $customized,
                'unused_tokens' => $unused,
                'message' => $lang->get('done'),
            ],
            'encode'
        );
        break;

    /*
     * RESET one template for one language: the rows are deleted and the
     * shipped strings apply again.
     */
    case 'reset_template':
        $dataReceived = prepareExchangedData($post_data, 'decode');
        $templateId = (string) ($dataReceived['template'] ?? '');
        $language = (string) ($dataReceived['language'] ?? '');

        if (isset($emailsCatalog[$templateId]) === false || emailsTemplatesLanguageExists($language) === false) {
            echo (string) prepareExchangedData(
                ['error' => true, 'message' => $lang->get('error_not_allowed_to')],
                'encode'
            );
            break;
        }

        $template = $emailsCatalog[$templateId];
        $keys = [(string) $template['body_key']];
        if (empty($template['subject_key']) === false) {
            $keys[] = (string) $template['subject_key'];
        }

        DB::delete(
            prefixTable('emails_templates'),
            'language = %s AND template_key IN %ls',
            $language,
            $keys
        );

        logEvents(
            $SETTINGS,
            'admin_action',
            'at_email_template_reset:' . $templateId . ':' . $language,
            (string) $userId,
            (string) $session->get('user-login')
        );

        echo (string) prepareExchangedData(
            ['error' => false, 'message' => $lang->get('done')],
            'encode'
        );
        break;

    /*
     * PREVIEW the content currently in the editor, with sample values.
     *
     * The content is normalized exactly like a save would, so the modal shows
     * what would actually leave the server, unsaved edits included.
     */
    case 'preview_template':
    /*
     * SEND the preview to the administrator's own address.
     *
     * The recipient is taken from the session, never from the request: this
     * endpoint must not become a way to mail arbitrary content to anyone.
     */
    case 'send_test_template':
        $dataReceived = prepareExchangedData($post_data, 'decode');
        $templateId = (string) ($dataReceived['template'] ?? '');
        $language = (string) ($dataReceived['language'] ?? '');

        if (isset($emailsCatalog[$templateId]) === false || emailsTemplatesLanguageExists($language) === false) {
            echo (string) prepareExchangedData(
                ['error' => true, 'message' => $lang->get('error_not_allowed_to')],
                'encode'
            );
            break;
        }

        $template = $emailsCatalog[$templateId];
        $subjectKey = empty($template['subject_key']) === true ? '' : (string) $template['subject_key'];

        $subject = $subjectKey === ''
            ? '' : emailsTemplatesNormalizeSubject((string) ($dataReceived['subject'] ?? ''));
        $body = emailsTemplatesNormalizeBody((string) ($dataReceived['body'] ?? ''), $antiXss);

        if (strlen($subject) > EMAILS_TEMPLATES_MAX_LENGTH || strlen($body) > EMAILS_TEMPLATES_MAX_LENGTH) {
            echo (string) prepareExchangedData(
                ['error' => true, 'message' => $lang->get('emails_templates_error_too_long')],
                'encode'
            );
            break;
        }

        $samples = emailsTemplatesSampleValues([
            'url' => (string) ($SETTINGS['cpassman_url'] ?? ''),
            'login' => (string) $session->get('user-login'),
            'name' => (string) $session->get('user-name'),
            'lastname' => (string) $session->get('user-lastname'),
            'email' => (string) $session->get('user-email'),
            'date' => date((string) $SETTINGS['date_format'], time()),
            'time' => date((string) $SETTINGS['time_format'], time()),
            'datetime' => date($SETTINGS['date_format'] . ' ' . $SETTINGS['time_format'], time()),
            'secret_placeholder' => $lang->get('emails_templates_preview_secret'),
        ]);

        // The subject carries its own token list: only #tp_status# is substituted
        // there, and only for the scheduled backup report.
        $renderedSubject = (string) ($template['subject_prefix'] ?? '') . emailsTemplatesRenderPreview(
            $subject,
            (array) ($template['subject_tokens'] ?? []),
            $samples
        );
        $renderedBody = emailsTemplatesRenderPreview($body, (array) $template['tokens'], $samples);

        if ($post_type === 'preview_template') {
            echo (string) prepareExchangedData(
                [
                    'error' => false,
                    'subject' => $renderedSubject,
                    'body' => $renderedBody,
                    'fragment' => ($template['fragment'] ?? false) === true,
                ],
                'encode'
            );
            break;
        }

        // --- send_test_template
        $recipient = (string) $session->get('user-email');
        if (filter_var($recipient, FILTER_VALIDATE_EMAIL) === false) {
            echo (string) prepareExchangedData(
                ['error' => true, 'message' => $lang->get('no_email_set')],
                'encode'
            );
            break;
        }

        $emailSettings = new EmailSettings($SETTINGS);
        $emailService = new EmailService();
        $result = json_decode(
            (string) $emailService->sendMail(
                $renderedSubject === '' ? $lang->get('emails_templates') : $renderedSubject,
                $renderedBody,
                $recipient,
                $emailSettings
            ),
            true
        );

        echo (string) prepareExchangedData(
            [
                'error' => empty($result['error']) === false,
                'message' => (string) ($result['message'] ?? ''),
                'recipient' => $recipient,
            ],
            'encode'
        );
        break;

    default:
        echo (string) prepareExchangedData(
            ['error' => true, 'message' => $lang->get('error_not_allowed_to')],
            'encode'
        );
        break;
}
