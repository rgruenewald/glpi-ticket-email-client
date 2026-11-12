<?php
/**
 * inc/mailboxguard.class.php — best-effort incoming mailbox collision.
 *
 * Compares normalized recipient addresses against active
 * glpi_mailcollectors.login values that themselves look like emails.
 * Aliases, forwarding, and non-email logins are NOT detected.
 */
class PluginTicketmailerMailboxGuard
{
    /**
     * @param list<string> $recipients normalized addresses
     * @return list<string> matched collector login emails
     */
    public static function findMatches(array $recipients): array
    {
        $want = [];
        foreach ($recipients as $addr) {
            $norm = self::normalize($addr);
            if ($norm !== '') {
                $want[$norm] = true;
            }
        }
        if ($want === []) {
            return [];
        }

        $matches = [];
        foreach (self::activeCollectorLogins() as $login) {
            $norm = self::normalize($login);
            if ($norm !== '' && isset($want[$norm])) {
                $matches[$norm] = $login;
            }
        }
        return array_values($matches);
    }

    /**
     * @return list<string>
     */
    public static function activeCollectorLogins(): array
    {
        global $DB;
        $logins = [];
        $table = 'glpi_mailcollectors';
        if (!$DB->tableExists($table)) {
            return [];
        }
        $it = $DB->request([
            'SELECT' => ['login', 'is_active'],
            'FROM'   => $table,
            'WHERE'  => ['is_active' => 1],
        ]);
        foreach ($it as $row) {
            $login = trim((string) ($row['login'] ?? ''));
            // Only email-valued logins participate in the match.
            if ($login !== '' && filter_var($login, FILTER_VALIDATE_EMAIL)) {
                $logins[] = $login;
            }
        }
        return $logins;
    }

    public static function normalize(string $email): string
    {
        return strtolower(trim($email));
    }
}
