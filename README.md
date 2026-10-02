# Odden Service (`getodden/crm-service`)

> This is a read-only split of the [getodden/crm](https://github.com/getodden/crm) monorepo. Please open issues and pull requests there.

The customer support, helpdesk, and SLA orchestration engine for the Odden RevOps platform. Delivers multi-channel ticketing (Email, Web, Chat, API), business-hours SLA policy enforcement, knowledge deflection, automated ticket routing, thread merging, and an embeddable customer portal.

---

## Architecture & Capabilities

```
+-------------------------------------------------------------------------+
|                               ODDEN SERVICE                             |
|                                                                         |
|  +--------------------+   +--------------------+   +-----------------+  |
|  | Multi-Channel      |   | Inbound Webhooks & |   | Embeddable Help |  |
|  | Ticketing (Email)  |   | Thread Parsers     |   | Widget & Portal |  |
|  +--------------------+   +--------------------+   +-----------------+  |
|             \                       |                       /           |
|              v                      v                      v            |
|       +---------------------------------------------------------+       |
|       |             SLA Engine & Breach Watchdogs               |       |
|       |    (Business hours calendar, First Response & Resolv)   |       |
|       +---------------------------------------------------------+       |
|             |                       |                       |           |
|             v                       v                       v           |
|  +--------------------+   +--------------------+   +-----------------+  |
|  | Skill-Based Ticket |   | Canned Responses & |   | Knowledge Base  |  |
|  | Routing Rules      |   | Private Agent Notes|   | & Deflections   |  |
|  +--------------------+   +--------------------+   +-----------------+  |
+-------------------------------------------------------------------------+
```

### Core Features

- **Multi-Channel Ticketing:** Ingest and manage tickets from Email, Web forms, Chat widgets, Phone, or API, complete with threaded messaging and private internal notes.
- **Business-Hours SLA Policies:** Configure tiered response and resolution time commitments by priority (`Urgent`, `High`, `Medium`, `Low`). Supports customizable operating hours (e.g., 9-to-5 weekdays) and holiday calendar pauses.
- **Automated SLA Breach Watchdog:** Background monitor calculating time-to-first-response and time-to-resolution, dispatching proactive escalation notifications before commitments fail.
- **Intelligent Ticket Routing:** Automatically assign incoming requests to the best available agent or team using keyword matching, language requirements, customer tier, or round-robin balancing.
- **Knowledge Base & Deflection Engine:** Deliver customer self-service articles with keyword search. Track deflected ticket counts when articles resolve customer issues prior to submission.
- **Customer Self-Service Portal:** Zero-login, tokenized customer portal allowing end-users to check status, review conversations, upload attachments, and post replies securely.
- **Embeddable Chat & Support Widget (`widget.js`):** Lightweight, zero-dependency JavaScript drawer embeddable on any web property for on-site help, knowledge lookups, and ticket creation.
- **Thread Merging & Deduplication:** Merge duplicate tickets into a primary thread while preserving all historical messages, attachments, and timestamps.
- **CSAT Survey Automation:** Automatically trigger 1-click CSAT surveys upon ticket resolution to gauge customer satisfaction scores.

---

## Installation

```bash
composer require getodden/crm-service
```

Publish configuration and migrations:

```bash
php artisan vendor:publish --tag=odden-service-migrations
php artisan vendor:publish --tag=odden-service-config
```

Run migrations:

```bash
php artisan migrate
```

---

## Quick Start & Code Examples

### 1. Creating a Ticket with SLA Assignment

```php
use Odden\Service\Actions\CreateTicketAction;
use Odden\Service\Enums\TicketPriority;
use Odden\Service\Enums\TicketSource;

$ticket = app(CreateTicketAction::class)->execute([
    'contact_id' => $contact->id,
    'subject' => 'Production API returning 504 Gateway Timeout',
    'body' => 'Our billing batch failed at 03:00 UTC due to gateway timeouts.',
    'priority' => TicketPriority::Urgent,
    'source' => TicketSource::Email,
]);

// SLA response deadlines are automatically calculated based on the priority policy
echo "First response due: " . $ticket->first_response_due_at->toIso8601String();
```

### 2. Replying to a Ticket (Public Message vs. Private Note)

```php
use Odden\Service\Actions\ReplyTicketAction;
use Odden\Service\Enums\MessageSenderType;

// Public response to customer (stops SLA first-response clock)
app(ReplyTicketAction::class)->execute(
    ticket: $ticket,
    body: 'We have identified an upstream cache lock and applied a hotfix. Please retry.',
    senderType: MessageSenderType::Agent,
    senderId: auth()->id(),
    isPrivate: false
);

// Private internal note between support engineers
app(ReplyTicketAction::class)->execute(
    ticket: $ticket,
    body: 'Escalated to DevOps on-call. Redis connection pool exhausted during cron.',
    senderType: MessageSenderType::Agent,
    senderId: auth()->id(),
    isPrivate: true
);
```

### 3. Merging Duplicate Tickets

```php
use Odden\Service\Actions\MergeTicketsAction;

// Merges $duplicateTicket into $primaryTicket, moving all messages and closing the duplicate
app(MergeTicketsAction::class)->execute(
    primaryTicket: $primaryTicket,
    secondaryTicket: $duplicateTicket,
    agentId: auth()->id()
);
```

### 4. Running SLA Breach Audits

```php
use Odden\Service\Actions\CheckSlaBreachesAction;

// Identified tickets nearing or past breach deadline; dispatches SlaBreachAlertNotification
$breachedTickets = app(CheckSlaBreachesAction::class)->execute();

// Or run via Artisan in cron:
// php artisan odden:service-check-sla
```

### 5. Embedding the Help Widget

Point the widget at the chat API base URL, including any configured prefix (defaults to the same-origin `/api/service`), then load `resources/js/widget.js`:

```html
<script>
    window.ODDEN_CHAT_API_URL = 'https://crm.yourcompany.com/api/service';
</script>
<script src="/js/odden-chat-widget.js" async></script>
```

---

## Routes

The help center (`/help`) and support portal (`/support`) are registered in the `web` group with no prefix by default. The inbound email webhook, knowledge deflection and chat widget APIs are registered in the `api` group under `/api/service`.

Configure them in `config/odden-service.php` (publish with `php artisan vendor:publish --tag=odden-service-config`) or through environment variables:

```env
ODDEN_SERVICE_PREFIX=care               # /help becomes /care/help
ODDEN_SERVICE_API_PREFIX=api/service
ODDEN_SERVICE_DOMAIN=support.example.com # optional, applies to both groups
ODDEN_SERVICE_ROUTES_ENABLED=true
```

Each group also accepts `middleware`. To register the routes yourself, set `routes.enabled` to `false` and define routes with the same names (`odden.help.*`, `odden.support.*`, `odden.service.*`), because models, emails and notifications generate links from those names.

---

## API tokens and rate limits

The inbound email webhook (`POST /api/service/inbound-email`) requires the service API token. Until `ODDEN_SERVICE_API_TOKEN` is set, it responds with `403` and creates no tickets.

```env
ODDEN_SERVICE_API_TOKEN=   # e.g. php -r "echo bin2hex(random_bytes(32));"
```

Send it as `Authorization: Bearer <token>`, an `X-Odden-Token` header, or a `?token=<token>` query parameter (for email providers that only accept a webhook URL).

The chat widget, help center votes, knowledge deflection, and support portal submissions stay public and are rate limited per IP (`ODDEN_PUBLIC_RATE_LIMIT`, requests per minute; see `config/odden-core.php`).

---

## Data Models & Schema Reference

| Model | Table | Responsibility |
| :--- | :--- | :--- |
| `Ticket` | `service_tickets` | Support tickets with priority, status, SLA deadlines, CSAT rating, and portal tokens. |
| `TicketMessage` | `service_ticket_messages` | Threaded customer messages, agent replies, and private internal notes. |
| `SlaPolicy` | `service_sla_policies` | Target resolution and response durations with business-hour definitions. |
| `TicketRoutingRule` | `service_ticket_routing_rules` | Assignment rules mapping priority, language, and keywords to agents. |
| `KnowledgeArticle` | `service_knowledge_articles` | Public self-service troubleshooting guides with deflection tracking. |
| `CannedResponse` | `service_canned_responses` | Reusable response snippets with dynamic variable interpolation. |

---

## Testing

```bash
vendor/bin/pest packages/service/tests --compact
```
