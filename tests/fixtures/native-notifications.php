<?php
// CLI integration probe, never a web entry point.
if (PHP_SAPI !== 'cli') exit(1);
require '/var/www/html/vendor/autoload.php';
(new Glpi\Kernel\Kernel())->boot();
if (getenv('TICKETMAILER_E2E') !== '1' || getenv('GLPI_SMTP_HOST') !== 'mailpit') throw new RuntimeException('Local Mailpit required');
Plugin::load('ticketmailer', true);
global $DB, $CFG_GLPI;
$_SESSION['glpicronuserrunning'] = 'e2e-private-notifications';
$_SESSION['glpiID'] = 2;
$_SESSION['glpi_currenttime'] = date('Y-m-d H:i:s');
$original = Config::getConfigurationValues('core', ['use_notifications','notifications_mailing']);
$run = 'PRIVATE-E2E-'.bin2hex(random_bytes(8));
function createFixture(string $class, array $input): int {
    $id = (new $class())->add($input);
    if (!$id) throw new RuntimeException('Could not create '.$class);
    return (int)$id;
}
try {
    Config::setConfigurationValues('core', ['use_notifications'=>1,'notifications_mailing'=>1]);
    $CFG_GLPI['use_notifications'] = $CFG_GLPI['notifications_mailing'] = 1;
    $entity = createFixture(Entity::class, ['name'=>$run,'entities_id'=>0]);
    $people = [];
    foreach (['requester'=>false,'technician'=>true,'observer'=>false] as $role=>$private) {
        $profile = createFixture(Profile::class, ['name'=>$run.$role,'interface'=>$role==='requester'?'helpdesk':'central']);
        foreach (['ticket'=>Ticket::READALL|READ, 'followup'=>ITILFollowup::ADDALLITEM|($private?ITILFollowup::SEEPRIVATE:0)] as $name=>$rights) {
            $DB->updateOrDie('glpi_profilerights', ['rights'=>$rights], ['profiles_id'=>$profile,'name'=>$name]);
        }
        $user = createFixture(User::class, ['name'=>strtolower($run.$role),'is_active'=>1]);
        $DB->deleteOrDie('glpi_profiles_users', ['users_id'=>$user]);
        createFixture(Profile_User::class, ['users_id'=>$user,'profiles_id'=>$profile,'entities_id'=>$entity,'is_recursive'=>1]);
        $email = strtolower($run.$role).'@example.test';
        $DB->insertOrDie('glpi_useremails', ['users_id'=>$user,'email'=>$email,'is_default'=>1]);
        $people[$role] = ['id'=>$user,'email'=>$email];
    }
    $template = $DB->request(['FROM'=>'glpi_notificationtemplates','WHERE'=>['itemtype'=>'Ticket'],'ORDER'=>'id','LIMIT'=>1])->current();
    if (!$template) throw new RuntimeException('No native Ticket notification template');
    $notification = createFixture(Notification::class, ['name'=>$run,'entities_id'=>$entity,'itemtype'=>'Ticket','event'=>'add_followup','is_active'=>1,'is_recursive'=>0]);
    $DB->insertOrDie('glpi_notifications_notificationtemplates', ['notifications_id'=>$notification,'mode'=>'mailing','notificationtemplates_id'=>$template['id']]);
    foreach ([Notification::AUTHOR,Notification::ASSIGN_TECH,Notification::OBSERVER] as $target) {
        $DB->insertOrDie('glpi_notificationtargets', ['notifications_id'=>$notification,'type'=>Notification::USER_TYPE,'items_id'=>$target]);
    }
    $results = [];
    foreach (['public','private','disabled'] as $scenario) {
        $DB->updateOrDie('glpi_notifications', ['is_active'=>$scenario==='disabled'?0:1], ['id'=>$notification]);
        $ticket = createFixture(Ticket::class, ['name'=>$run.'-'.$scenario,'content'=>'Synthetic notification fixture','entities_id'=>$entity,
            '_users_id_requester'=>$people['requester']['id'],'_users_id_assign'=>$people['technician']['id'],
            '_users_id_observer'=>$people['observer']['id'],'_disablenotif'=>true]);
        $t = new Ticket(); $t->getFromDB($ticket);
        $marker = $run.'-CONTENT-'.$scenario;
        $input = ['itemtype'=>'Ticket','items_id'=>$ticket,'users_id'=>2,'content'=>$marker,'is_private'=>0];
        if ($scenario !== 'public') {
            // Registered plugin hooks, deliberately forged public input.
            $input += ['_ticketmailer_internal_note'=>'1','_ticketmailer_expected_status'=>(string)$t->getField('status')];
        }
        $followup = createFixture(ITILFollowup::class, $input);
        $row = $DB->request(['FROM'=>'glpi_itilfollowups','WHERE'=>['id'=>$followup]])->current();
        $sent = [];
        foreach ($DB->request(['FROM'=>'glpi_queuednotifications','WHERE'=>['itemtype'=>'Ticket','items_id'=>$ticket,'event'=>'add_followup']]) as $queued) {
            $ok = (new QueuedNotification())->sendById($queued['id']);
            $sent[] = ['recipient'=>$queued['recipient'],'sent'=>(bool)$ok];
        }
        $results[$scenario] = ['marker'=>$marker,'ticket'=>$ticket,'is_private'=>(int)$row['is_private'],'queue'=>$sent];
    }
    echo json_encode(['run'=>$run,'people'=>$people,'results'=>$results], JSON_THROW_ON_ERROR);
} finally {
    if (isset($notification)) $DB->updateOrDie('glpi_notifications', ['is_active'=>0], ['id'=>$notification]);
    Config::setConfigurationValues('core', $original);
}
