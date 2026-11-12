<?php
/**
 * inc/replypolicy.class.php — entity/profile reply policy.
 *
 * Modes: available | promoted | hide_native.
 * Precedence: exact entity+profile → entity default → global available.
 *
 * hide_native requires a verified GLPI extension point. Absent that
 * proof (T08 spike), effectivePolicy() never suppresses native reply
 * via DOM/CSS; hide_native is treated as promoted.
 */
class PluginTicketmailerReplyPolicy
{
    public const MODE_AVAILABLE   = 'available';
    public const MODE_PROMOTED    = 'promoted';
    public const MODE_HIDE_NATIVE = 'hide_native';

    /** @return list<string> */
    public static function modes(): array
    {
        return [
            self::MODE_AVAILABLE,
            self::MODE_PROMOTED,
            self::MODE_HIDE_NATIVE,
        ];
    }

    /**
     * Stored policy for entity/profile without hide_native remapping.
     */
    public static function resolveStored(int $entities_id, ?int $profiles_id): string
    {
        global $DB;
        if ($profiles_id !== null) {
            $it = $DB->request([
                'FROM'  => 'glpi_plugin_ticketmailer_reply_policies',
                'WHERE' => [
                    'entities_id' => $entities_id,
                    'profiles_id' => $profiles_id,
                ],
                'LIMIT' => 1,
            ]);
            foreach ($it as $row) {
                return (string) $row['mode'];
            }
        }
        $it = $DB->request([
            'FROM'  => 'glpi_plugin_ticketmailer_reply_policies',
            'WHERE' => [
                'entities_id' => $entities_id,
                'profiles_id' => null,
            ],
            'LIMIT' => 1,
        ]);
        foreach ($it as $row) {
            return (string) $row['mode'];
        }
        return self::MODE_AVAILABLE;
    }

    /**
     * Effective UI policy. hide_native → promoted until a real
     * GLPI-supported native-reply hide hook is proven.
     */
    public static function effectivePolicy(int $entities_id, ?int $profiles_id = null): string
    {
        $mode = self::resolveStored($entities_id, $profiles_id);
        if ($mode === self::MODE_HIDE_NATIVE) {
            // ponytail: no verified extension point → never hide native
            return self::MODE_PROMOTED;
        }
        if (!in_array($mode, self::modes(), true)) {
            return self::MODE_AVAILABLE;
        }
        return $mode;
    }

    public static function isEmailReplyAvailable(int $entities_id, ?int $profiles_id = null): bool
    {
        $mode = self::effectivePolicy($entities_id, $profiles_id);
        return in_array($mode, [
            self::MODE_AVAILABLE,
            self::MODE_PROMOTED,
        ], true);
    }
}
