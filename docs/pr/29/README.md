# New email: GLPI 11 runtime screenshots

Captured from the isolated local GLPI 11.0.8 test instance after integrating main into PR #29. All addresses and message content are synthetic. SMTP delivery goes only to the local Mailpit sink.

## Compose before a ticket exists

Assistance > New email, with To, CC, subject and message filled in. Sending creates the ticket; cancelling does not.

![New email composer before ticket creation](new-email-compose.png)

## Created ticket and outbound message

After Send: the subject becomes the ticket title, To becomes Requester, CC becomes Observer, and the outbound message appears in the timeline with the new ticket marker.

![Created ticket with recipient roles and outbound email](new-email-ticket.png)
