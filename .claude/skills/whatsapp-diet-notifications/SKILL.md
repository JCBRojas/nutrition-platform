---
name: whatsapp-diet-notifications
description: Implementa o modifica las notificaciones por WhatsApp (Meta Cloud API) cuando se crea, edita o cancela una dieta en nutrition-platform. Usar al tocar DietService, eventos de dieta, colas, el canal WhatsApp, plantillas o el webhook de estados.
---

# Notificaciones WhatsApp de cambios de dieta

Contexto general en `CLAUDE.md` §6. Stack: Laravel 10, cola database, Guzzle (vía `Http` facade).

## Archivos a crear

```
app/Events/DietVersionCreated.php                  // public DietHistory $history; public string $changeType
app/Listeners/SendDietChangeNotification.php       // ShouldQueue, $queue = 'notifications', $tries = 3, backoff [60, 300, 900]
app/Notifications/DietChangedNotification.php      // via(): [WhatsAppChannel::class]; toWhatsApp(): WhatsAppMessage
app/Notifications/Channels/WhatsAppChannel.php     // send($notifiable, $notification)
app/Services/WhatsApp/WhatsAppClient.php           // sendTemplate(string $to, string $template, array $params, string $lang = 'es')
app/Services/WhatsApp/WhatsAppMessage.php          // DTO: template, params
app/Models/WhatsAppMessage.php + migración whatsapp_messages
app/Http/Controllers/Webhooks/WhatsAppWebhookController.php  // verify (GET) + handle (POST)
```

Registrar evento → listener en `EventServiceProvider::$listen`.

## Disparo

En `DietService`, dentro de la transacción, tras crear el `DietHistory`:

```php
DB::afterCommit(fn () => event(new DietVersionCreated($history, $changeType)));
```

`$changeType`: `creada` (v1), `cancelada` (si `changes === 'cancelada'`), `modificada` (resto). No disparar desde controladores ni Livewire.

## Destinatarios

```php
User::role('despachador')->where('is_active', true)->where('whatsapp_opt_in', true)->whereNotNull('phone')->get();
```
Enviar con `Notification::send($users, new DietChangedNotification($history, $changeType))`.
Requiere columnas `phone`, `is_active`, `whatsapp_opt_in` (módulo de usuarios, `CLAUDE.md` §5) y `User::routeNotificationForWhatsApp()`.

## Cliente HTTP

```php
Http::withToken(config('services.whatsapp.token'))
    ->timeout(10)->retry(2, 500, throw: false)
    ->post("https://graph.facebook.com/{$version}/{$phoneNumberId}/messages", [
        'messaging_product' => 'whatsapp',
        'to' => ltrim($to, '+'),
        'type' => 'template',
        'template' => [
            'name' => $template,
            'language' => ['code' => 'es'],
            'components' => [[
                'type' => 'body',
                'parameters' => array_map(fn ($p) => ['type' => 'text', 'text' => (string) $p], $params),
            ]],
        ],
    ]);
```
- Si `config('services.whatsapp.enabled')` es false: solo `Log::info` y registrar con status `skipped`.
- Guardar `wamid` (`messages.0.id`) y el error (`error.message`, `error.code`) en `whatsapp_messages`.
- Lanzar excepción en 5xx/429 para que la cola reintente; 4xx → marcar `failed` sin reintento.

## Plantilla (aprobar en Meta Business Manager)

Nombre `cambio_dieta`, idioma `es`, categoría *Utility*:
```
Dieta {{1}} – Habitación {{2}}. Versión {{3}} registrada el {{4}}. Revise el panel de despacho.
```
Params: `[$changeType, $diet->habitation, $history->version, $history->created_at->timezone('America/Bogota')->format('d/m/Y h:i a')]`.
**No** incluir nombre del paciente ni detalle clínico (datos sensibles).

## Tabla whatsapp_messages

```
id, user_id (fk nullable), diet_history_id (fk), to, template, payload json,
wamid string nullable index, status enum(queued,sent,delivered,read,failed,skipped),
error text nullable, timestamps
```

## Webhook

- `GET /webhooks/whatsapp`: si `hub_verify_token === services.whatsapp.verify_token` devolver `hub_challenge`.
- `POST /webhooks/whatsapp`: validar `X-Hub-Signature-256` = `sha256=` + `hash_hmac('sha256', $request->getContent(), app_secret)` con `hash_equals`; actualizar `status` por `wamid`.
- Excluir de CSRF en `VerifyCsrfToken::$except` y registrar fuera del grupo `auth`.

## .env

```
WHATSAPP_ENABLED=false
WHATSAPP_TOKEN=
WHATSAPP_PHONE_NUMBER_ID=
WHATSAPP_API_VERSION=v21.0
WHATSAPP_VERIFY_TOKEN=
WHATSAPP_APP_SECRET=
WHATSAPP_TEMPLATE_DIET_CHANGE=cambio_dieta
QUEUE_CONNECTION=database
```
Añadir el bloque `whatsapp` a `config/services.php` (ver `CLAUDE.md` §6) y las claves vacías a `.env.example`.

## Tests

- `Event::fake()` → crear/editar dieta vía `DietService` dispara `DietVersionCreated` con el `changeType` correcto.
- `Notification::fake()` → solo despachadores activos con opt-in reciben.
- `Http::fake()` → payload correcto, `wamid` persistido, 4xx marca `failed`, 5xx lanza excepción.
- Webhook: firma inválida → 403; firma válida actualiza status.

## Checklist

1. Migraciones (`users` campos, `whatsapp_messages`, `jobs`) y `php artisan migrate`.
2. Config + `.env.example`.
3. Evento/listener/notificación/canal/cliente.
4. Webhook + exclusión CSRF.
5. Tests verdes, `./vendor/bin/pint`.
6. Worker: `php artisan queue:work --queue=notifications,default`.
