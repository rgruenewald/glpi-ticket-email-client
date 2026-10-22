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
    Config::deleteConfigurationValues('plugin:ticketmailer', array_keys(PluginTicketmailerConfig::interfaceOptions()));
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
    if (($followup->input['_ticketmailer_internal_note'] ?? null) !== '1') {
        return;
    }
    $followup->input['is_private'] = 1;
    $ticket = new Ticket();
    $expected = filter_var($followup->input['_ticketmailer_expected_status'] ?? null, FILTER_VALIDATE_INT);
    $waiting = !empty($followup->input['pending']);
    $solved = ($followup->input['_ticketmailer_set_solved'] ?? null) === '1';
    try {
        if (($followup->input['itemtype'] ?? '') !== Ticket::class
            || !$ticket->getFromDB((int) ($followup->input['items_id'] ?? 0))) {
            throw new InvalidArgumentException(__('GLPI does not allow adding a followup to this ticket.', 'ticketmailer'));
        }
        PluginTicketmailerTimeline::validateSubmission($ticket, $expected === false ? -1 : $expected, $waiting, $solved);
        // Snapshot the explicit form action before native templates are expanded.
        $followup->input['_ticketmailer_target_status'] = $solved ? Ticket::SOLVED : ($waiting ? Ticket::WAITING : (int) $ticket->getField('status'));
        $followup->input = PluginTicketmailerTimeline::preserveStatus($followup->input);
    } catch (InvalidArgumentException $error) {
        Session::addMessageAfterRedirect($error->getMessage(), false, ERROR);
        $followup->input = false;
    }
}

/** Native templates and pending=0 must not turn a note into an implicit reopen. */
function plugin_ticketmailer_prepare_internal_note(ITILFollowup $followup): void
{
    if (($followup->input['_ticketmailer_internal_note'] ?? null) !== '1') {
        return;
    }
    $ticket = new Ticket();
    $expected = (int) ($followup->input['_ticketmailer_expected_status'] ?? -1);
    $target = (int) $followup->input['_ticketmailer_target_status'];
    $waiting = $target === Ticket::WAITING && $target !== $expected;
    $solved = $target === Ticket::SOLVED && $target !== $expected;
    try {
        if (!$ticket->getFromDB((int) $followup->input['items_id'])) {
            throw new InvalidArgumentException(__('GLPI does not allow adding a followup to this ticket.', 'ticketmailer'));
        }
        PluginTicketmailerTimeline::validateSubmission($ticket, $expected, $waiting, $solved);
        $followup->input = PluginTicketmailerTimeline::preserveStatus($followup->input);
        $followup->input['is_private'] = 1;
        $followup->input['_job'] = $ticket;
        if ($waiting) {
            $followup->input['pending'] = 1;
        }
    } catch (InvalidArgumentException $error) {
        Session::addMessageAfterRedirect($error->getMessage(), false, ERROR);
        $followup->input = false;
    }
}

/** Apply only an explicitly requested, still-permitted solution after the note. */
function plugin_ticketmailer_set_internal_note_solved(ITILFollowup $followup): void
{
    if (($followup->input['_ticketmailer_internal_note'] ?? null) !== '1'
        || $followup->getField('itemtype') !== Ticket::class) {
        return;
    }
    $expected = (int) $followup->input['_ticketmailer_expected_status'];
    $target = (int) $followup->input['_ticketmailer_target_status'];
    if ($target === $expected) {
        return;
    }
    $ticket = new Ticket();
    $ok = $ticket->getFromDB((int) $followup->getField('items_id'));
    if ($ok && $target === Ticket::SOLVED) {
        $ok = (int) $ticket->getField('status') === $expected
            && PluginTicketmailerTimeline::canChangeStatus($ticket, Ticket::SOLVED)
            && $ticket->update([
                'id' => (int) $ticket->getID(),
                'status' => Ticket::SOLVED,
                '_disablenotif' => true,
            ]) && $ticket->getFromDB((int) $ticket->getID());
    }
    if (!$ok || (int) $ticket->getField('status') !== $target) {
        Session::addMessageAfterRedirect(
            __('The internal note was added, but the ticket status could not be changed. Check the current ticket status.', 'ticketmailer'),
            false, ERROR,
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
