<?php
/**
 * Builds the public ticket context and resolves only the source documents a
 * sender explicitly chose for an outbound email.
 */
class PluginTicketemailclientHistory
{
    /**
     * @return list<array{id:int, filename:string, mime:string, preview_url:string}>
     */
    public static function availableAttachments(Ticket $ticket): array
    {
        global $DB;

        $item_ids = [
            'Ticket' => [(int) $ticket->getField('id')],
            'ITILFollowup' => [],
        ];
        foreach ($DB->request('glpi_itilfollowups', [
            'items_id' => (int) $ticket->getField('id'),
            'itemtype' => 'Ticket',
            'is_private' => 0,
        ]) as $followup) {
            $item_ids['ITILFollowup'][] = (int) $followup['id'];
        }

        $documents = [];
        foreach ($item_ids as $itemtype => $ids) {
            foreach ($ids as $items_id) {
                foreach ($DB->request('glpi_documents_items', [
                    'itemtype' => $itemtype,
                    'items_id' => $items_id,
                ]) as $link) {
                    $documents[(int) $link['documents_id']] = true;
                }
            }
        }

        $available = [];
        foreach (array_keys($documents) as $documents_id) {
            $document = new Document();
            if (!$document->getFromDB($documents_id)) {
                continue;
            }
            $path = self::documentPath((string) $document->getField('filepath'));
            if ($path === null) {
                continue;
            }
            $available[] = [
                'id' => $documents_id,
                'filename' => self::filename($document),
                'mime' => (string) ($document->getField('mime') ?: 'application/octet-stream'),
                'preview_url' => Plugin::getWebDir('ticketemailclient') . '/front/history_attachment.php'
                    . '?tickets_id=' . (int) $ticket->getField('id')
                    . '&documents_id=' . $documents_id,
            ];
        }

        usort($available, static fn (array $left, array $right): int => strnatcasecmp($left['filename'], $right['filename']));
        return $available;
    }

    /**
     * @return array{filename:string, mime:string, path:string}|null
     */
    public static function resolveAttachment(Ticket $ticket, int $documents_id): ?array
    {
        foreach (self::availableAttachments($ticket) as $attachment) {
            if ($attachment['id'] !== $documents_id) {
                continue;
            }
            $document = new Document();
            if (!$document->getFromDB($documents_id)) {
                return null;
            }
            $path = self::documentPath((string) $document->getField('filepath'));
            if ($path === null) {
                return null;
            }
            return [
                'filename' => self::filename($document),
                'mime' => (string) ($document->getField('mime') ?: 'application/octet-stream'),
                'path' => $path,
            ];
        }
        return null;
    }

    /**
     * @param list<int|string> $selected_ids
     * @return list<array{id:string, stored:string, path:string, filename:string, mime:string}>
     */
    public static function copySelectedAttachments(Ticket $ticket, array $selected_ids): array
    {
        $selected = self::selectedAttachments($ticket, $selected_ids);

        $destination_root = GLPI_PLUGIN_DOC_DIR . '/ticketemailclient/' . (int) $ticket->getField('id');
        if (!is_dir($destination_root) && !mkdir($destination_root, 0o755, true) && !is_dir($destination_root)) {
            throw new RuntimeException('Unable to prepare attachment storage.');
        }

        $attachments = [];
        $created_paths = [];
        try {
            foreach ($selected as $documents_id => $attachment) {
                $document = new Document();
                if (!$document->getFromDB($documents_id)) {
                    throw new RuntimeException('A selected history attachment is no longer available.');
                }
                $source = self::documentPath((string) $document->getField('filepath'));
                if ($source === null) {
                    throw new RuntimeException('A selected history attachment is no longer available.');
                }
                $stored = bin2hex(random_bytes(16));
                $destination = $destination_root . '/' . $stored;
                $created_paths[] = $destination;
                if (!copy($source, $destination)) {
                    throw new RuntimeException('Unable to copy a selected history attachment.');
                }
                $attachments[] = [
                    'id' => bin2hex(random_bytes(8)),
                    'stored' => $stored,
                    'path' => $destination,
                    'filename' => $attachment['filename'],
                    'mime' => $attachment['mime'],
                ];
            }
        } catch (Throwable $exception) {
            foreach ($created_paths as $path) {
                @unlink($path);
            }
            throw $exception;
        }

        return $attachments;
    }

    public static function render(Ticket $ticket): string
    {
        global $DB;

        $entries = [[
            'date' => (string) $ticket->getField('date_creation'),
            'author' => self::userDisplayName((int) $ticket->getField('users_id')),
            'content' => (string) $ticket->getField('content'),
        ]];
        foreach ($DB->request('glpi_itilfollowups', [
            'items_id' => (int) $ticket->getField('id'),
            'itemtype' => 'Ticket',
            'is_private' => 0,
            'ORDER' => 'date ASC',
        ]) as $followup) {
            $entries[] = [
                'date' => (string) $followup['date'],
                'author' => self::userDisplayName((int) $followup['users_id']),
                'content' => (string) $followup['content'],
            ];
        }

        $html = '<p>' . htmlspecialchars(sprintf(
            __('Ticket #%1$d: %2$s', 'ticketemailclient'),
            (int) $ticket->getField('id'),
            (string) $ticket->getField('name'),
        ), ENT_QUOTES, 'UTF-8') . '</p>';
        foreach ($entries as $entry) {
            $html .= sprintf(
                '<p><small>%s</small></p><blockquote>%s</blockquote>',
                htmlspecialchars(sprintf(
                    __('On %1$s, %2$s wrote:', 'ticketemailclient'),
                    $entry['date'],
                    $entry['author'],
                ), ENT_QUOTES, 'UTF-8'),
                $entry['content'],
            );
        }
        return $html;
    }

    /**
     * @param list<int|string> $selected_ids
     */
    public static function validateSelectedAttachments(Ticket $ticket, array $selected_ids): void
    {
        self::selectedAttachments($ticket, $selected_ids);
    }

    /**
     * @param list<int|string> $selected_ids
     * @return array<int, array{id:int, filename:string, mime:string}>
     */
    private static function selectedAttachments(Ticket $ticket, array $selected_ids): array
    {
        $available = [];
        foreach (self::availableAttachments($ticket) as $attachment) {
            $available[$attachment['id']] = $attachment;
        }

        $selected = [];
        foreach ($selected_ids as $selected_id) {
            $documents_id = filter_var($selected_id, FILTER_VALIDATE_INT);
            if ($documents_id === false || $documents_id <= 0 || !isset($available[$documents_id])) {
                throw new InvalidArgumentException('A selected history attachment is no longer available.');
            }
            $selected[$documents_id] = $available[$documents_id];
        }

        return $selected;
    }

    public static function appendToMessage(string $body_html, string $history_html): string
    {
        return $history_html === '' ? $body_html : $body_html . '<hr>' . $history_html;
    }

    private static function documentPath(string $filepath): ?string
    {
        $documents_root = realpath(GLPI_DOC_DIR);
        $path = $documents_root === false ? false : realpath($documents_root . '/' . $filepath);
        if ($path === false || !is_file($path) || !str_starts_with($path, $documents_root . DIRECTORY_SEPARATOR)) {
            return null;
        }
        return $path;
    }

    private static function filename(Document $document): string
    {
        return (string) ($document->getField('filename') ?: $document->getField('name') ?: __('Attachment', 'ticketemailclient'));
    }

    private static function userDisplayName(int $users_id): string
    {
        if ($users_id <= 0) {
            return __('Unknown', 'ticketemailclient');
        }
        $user = new User();
        return $user->getFromDB($users_id) ? (string) $user->getFriendlyName() : __('Unknown', 'ticketemailclient');
    }
}
