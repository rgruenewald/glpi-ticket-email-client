<?php
/**
 * inc/recipients.class.php — strict raw recipient parsing (v2 A4).
 *
 * parseRaw() splits raw To/CC/BCC input, rejects every malformed
 * non-empty token, normalizes valid addresses. Invalid tokens are
 * never silently discarded.
 */
class PluginTicketemailclientRecipients
{
    /**
     * Parse raw recipient input. Returns valid addresses and
     * malformed tokens separately.
     *
     * @return array{valid:list<string>,invalid:list<string>}
     */
    public static function parseRaw(string $raw): array
    {
        $raw = trim($raw);
        if ($raw === '') {
            return ['valid' => [], 'invalid' => []];
        }
        $tokens = preg_split('/[,;\r\n]+/', $raw) ?: [];
        $valid = [];
        $invalid = [];
        foreach ($tokens as $token) {
            $token = trim($token);
            if ($token === '') {
                continue;
            }
            if (!filter_var($token, FILTER_VALIDATE_EMAIL)) {
                $invalid[] = $token;
                continue;
            }
            $valid[strtolower($token)] = $token;
        }
        return [
            'valid'   => array_values($valid),
            'invalid' => $invalid,
        ];
    }

    /**
     * Back-compat wrapper: only valid addresses (silent drop).
     * Prefer parseRaw() for send/validate paths.
     *
     * @return list<string>
     */
    public static function normalise(string $raw): array
    {
        return self::parseRaw($raw)['valid'];
    }

    /**
     * @param list<string> $recipients_to
     * @param list<string> $recipients_cc
     * @param list<string> $recipients_bcc
     */
    public static function hasAny(
        array $recipients_to,
        array $recipients_cc,
        array $recipients_bcc,
    ): bool {
        return $recipients_to !== []
            || $recipients_cc !== []
            || $recipients_bcc !== [];
    }

    /**
     * @param list<string> $addresses
     */
    public static function hasInvalid(array $addresses): bool
    {
        foreach ($addresses as $addr) {
            if (!filter_var($addr, FILTER_VALIDATE_EMAIL)) {
                return true;
            }
        }
        return false;
    }
}
