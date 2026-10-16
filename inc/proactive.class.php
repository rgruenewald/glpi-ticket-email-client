<?php
/** New-message context and durable duplicate protection, without storing drafts. */
class PluginTicketmailerProactive
{
    public const TTL = 86400;

    public static function canUse(int $entity): bool
    {
        return Session::getCurrentInterface() === 'central'
            && Session::haveRight('ticket', CREATE)
            && Session::haveRight('ticket', UPDATE)
            && (Session::haveRight('ticket', Ticket::READMY) || Session::haveRight('ticket', Ticket::READALL))
            && Session::haveAccessToEntity($entity);
    }

    public static function getMenuContent(): array
    {
        $entity = (int) ($_SESSION['glpiactive_entity'] ?? 0);
        if (!self::canUse($entity)) {
            return [];
        }
        return [
            'title' => __('New email', 'ticketmailer'),
            'page' => Plugin::getWebDir('ticketmailer', false) . '/front/new.php',
            'icon' => 'ti ti-mail-plus',
        ];
    }

    public static function createContext(int $entity, int $user): array
    {
        self::cleanup();
        $token = bin2hex(random_bytes(24));
        $context = ['token'=>$token, 'entities_id'=>$entity, 'users_id'=>$user, 'expires'=>time() + self::TTL];
        $_SESSION['ticketmailer_proactive'][$token] = $context;
        return $context;
    }

    public static function context(string $token, int $user): ?array
    {
        if (!preg_match('/^[a-f0-9]{48}$/D', $token)) {
            return null;
        }
        $context = $_SESSION['ticketmailer_proactive'][$token] ?? null;
        if (!is_array($context) || $context['users_id'] !== $user || $context['expires'] < time()) {
            return null;
        }
        return $context;
    }

    public static function directory(array $context): string
    {
        $dir = GLPI_PLUGIN_DOC_DIR . '/ticketmailer/.outbound/' . $context['token'];
        if (!is_dir($dir) && !mkdir($dir, 0700, true) && !is_dir($dir)) {
            throw new RuntimeException('Could not create outbound context.');
        }
        return $dir;
    }

    /** Hold this lock until the request completes; session locks alone are insufficient. */
    public static function lock(array $context)
    {
        $handle = fopen(self::directory($context) . '/lock', 'c');
        if ($handle === false || !flock($handle, LOCK_EX)) {
            throw new RuntimeException('Could not lock outbound context.');
        }
        return $handle;
    }

    public static function state(array $context): array
    {
        $path = self::directory($context) . '/state.json';
        if (!is_file($path)) {
            return ['phase'=>'open', 'tickets_id'=>0];
        }
        $state = json_decode((string) file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
        if (!is_array($state)) {
            throw new RuntimeException('Invalid outbound state.');
        }
        return $state;
    }

    public static function saveState(array $context, array $state): void
    {
        $path = self::directory($context) . '/state.json';
        $json = json_encode($state, JSON_THROW_ON_ERROR);
        if (file_put_contents($path . '.tmp', $json, LOCK_EX) !== strlen($json) || !rename($path . '.tmp', $path)) {
            throw new RuntimeException('Could not persist outbound state.');
        }
    }

    public static function ticketInput(int $entity, string $subject, array $to, array $cc): array
    {
        $actor = static function (string $email): array {
            $ids = User::getUsersIdByEmails($email, ['glpi_users.is_active'=>1, 'glpi_users.is_deleted'=>0]);
            return ['itemtype'=>'User', 'items_id'=>count($ids) === 1 ? (int) $ids[0] : 0,
                'alternative_email'=>$email, 'use_notification'=>1];
        };
        return [
            'name'=>$subject, 'content'=>'', 'entities_id'=>$entity, 'status'=>1,
            'users_id_recipient'=>(int) Session::getLoginUserID(),
            '_disablenotif'=>true,
            '_actors'=>['requester'=>array_map($actor, $to), 'observer'=>array_map($actor, $cc), 'assign'=>[]],
        ];
    }

    public static function adoptUploads(array $context, int $ticket): void
    {
        $target = GLPI_PLUGIN_DOC_DIR . '/ticketmailer/' . $ticket;
        if (!is_dir($target) && !mkdir($target, 0755, true) && !is_dir($target)) {
            throw new RuntimeException('Could not store ticket attachments.');
        }
        foreach (glob(self::directory($context) . '/uploads/*') ?: [] as $source) {
            if (!rename($source, $target . '/' . basename($source))) {
                throw new RuntimeException('Could not transfer ticket attachment.');
            }
        }
    }

    /** Delete expired temporary contexts; ticket attachments and audit records remain untouched. */
    public static function cleanup(): void
    {
        $root = GLPI_PLUGIN_DOC_DIR . '/ticketmailer/.outbound';
        foreach (glob($root . '/*', GLOB_ONLYDIR) ?: [] as $dir) {
            if (!preg_match('/^[a-f0-9]{48}$/D', basename($dir)) || filemtime($dir) >= time() - self::TTL) {
                continue;
            }
            $handle = fopen($dir . '/lock', 'c');
            if ($handle === false || !flock($handle, LOCK_EX | LOCK_NB)) {
                if (is_resource($handle)) { fclose($handle); }
                continue;
            }
            foreach (glob($dir . '/uploads/*') ?: [] as $file) {
                if (is_file($file) && !is_link($file)) { unlink($file); }
            }
            if (is_dir($dir . '/uploads')) { rmdir($dir . '/uploads'); }
            foreach (['state.json', 'state.json.tmp'] as $name) {
                if (is_file($dir . '/' . $name)) { unlink($dir . '/' . $name); }
            }
            unlink($dir . '/lock');
            rmdir($dir);
            flock($handle, LOCK_UN);
            fclose($handle);
        }
        foreach ($_SESSION['ticketmailer_proactive'] ?? [] as $token=>$context) {
            if ($context['expires'] < time()) { unset($_SESSION['ticketmailer_proactive'][$token]); }
        }
    }
}
