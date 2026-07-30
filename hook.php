<?php
/**
 * hook.php — lifecycle hooks for ticketmailer v2.
 */

/**
 * Install / upgrade. Fresh install uses install.sql.
 * Existing v1 DBs gain v2 columns via update-1.1.0.sql when needed.
 */
function plugin_ticketmailer_install(): bool
{
    $sql_file = __DIR__ . '/sql/install.sql';
    if (!is_file($sql_file)
        || !PluginTicketmailerHook::runSqlScript((string) file_get_contents($sql_file))
    ) {
        return false;
    }

    return PluginTicketmailerHook::migrateSchema(__DIR__ . '/sql');
}

function plugin_ticketmailer_uninstall(): bool
{
    $sql_file = __DIR__ . '/sql/uninstall.sql';
    if (is_file($sql_file)) {
        PluginTicketmailerHook::runSqlScript((string) file_get_contents($sql_file));
    }
    $files_root = GLPI_PLUGIN_DOC_DIR . '/ticketmailer';
    if (is_dir($files_root)) {
        PluginTicketmailerHook::rmdirRecursive($files_root);
    }
    return true;
}

/**
 * Prevent native Ticket templates from exposing costs to users without
 * GLPI's dedicated ticket-cost permission.
 */
function plugin_ticketmailer_filter_notification_template_data(NotificationTargetTicket $target): void
{
    if (Session::haveRight('ticketcost', READ)) {
        return;
    }

    foreach ([
        '##ticket.costfixed##',
        '##ticket.costmaterial##',
        '##ticket.costtime##',
        '##ticket.totalcost##',
        '##ticket.numberofcosts##',
    ] as $tag) {
        unset($target->data[$tag]);
    }
    unset($target->data['costs']);
}

/**
 * Keep followups submitted by Ticketmailer's native Internal-note form private.
 */
function plugin_ticketmailer_force_internal_note_private(ITILFollowup $followup): void
{
    if (($followup->input['_ticketmailer_internal_note'] ?? null) === '1') {
        $followup->input['is_private'] = 1;
        unset($followup->input['_ticketmailer_internal_note']);
    }
}

/**
 * Apply the optional solved status after GLPI has finished adding the followup.
 */
function plugin_ticketmailer_set_internal_note_solved(ITILFollowup $followup): void
{
    if (($followup->input['_ticketmailer_set_solved'] ?? null) !== '1'
        || $followup->getField('itemtype') !== Ticket::class) {
        return;
    }

    $ticket = new Ticket();
    if (!$ticket->getFromDB((int) $followup->getField('items_id'))
        || !$ticket->canSolve()
        || !$ticket->update([
            'id' => (int) $ticket->getID(),
            'status' => Ticket::SOLVED,
            '_disablenotif' => true,
        ])) {
        Session::addMessageAfterRedirect(
            __('The internal note was added, but the ticket status could not be set to solved.', 'ticketmailer'),
            false,
            ERROR,
        );
    }
}

function plugin_ticketmailer_post_init(): void
{
    Plugin::registerClass(
        'PluginTicketmailerLogTab',
        ['addtabon' => ['Ticket']],
    );
}

function plugin_ticketmailer_item_purge(Ticket $ticket): void
{
    global $DB;
    $tickets_id = (int) $ticket->getField('id');
    $DB->delete(
        'glpi_plugin_ticketmailer_logs',
        ['tickets_id' => $tickets_id],
    );
}
