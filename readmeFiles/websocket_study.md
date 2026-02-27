# Étude d'Impact : WebSockets pour TeamPass

**Date :** 2026-02-01 (mise à jour 2026-02-02)
**Branche :** feature/websockets
**Statut :** ✅ Étude complète - Prêt pour implémentation

---

## 1. Contexte et Justification

Le feedback utilisateur actuel repose sur des appels AJAX ponctuels avec notifications Toastr côté client. Cela crée plusieurs problèmes :
- L'utilisateur doit rafraîchir ou attendre pour voir les changements
- Aucune notification push pour les opérations longues (chiffrement, synchronisation LDAP)
- Pas de collaboration temps réel entre utilisateurs

---

## 2. Architecture Proposée

```
┌─────────────────────────────────────────────────────────────────┐
│                         CLIENTS                                  │
│   Browser (JS)              API Client (curl/SDK)               │
└──────────┬──────────────────────────┬───────────────────────────┘
           │ WSS (TLS)                │ WSS + JWT
           ▼                          ▼
┌─────────────────────────────────────────────────────────────────┐
│              REVERSE PROXY (Apache/Nginx)                        │
│   HTTP → PHP-FPM (existant)    /ws → WebSocket Server           │
└──────────┬──────────────────────────┬───────────────────────────┘
           │                          │
           ▼                          ▼
┌──────────────────────┐    ┌────────────────────────────────────┐
│   TeamPass HTTP      │    │   WebSocket Server (Ratchet)       │
│   (Apache/PHP-FPM)   │    │   - Daemon PHP séparé              │
│                      │    │   - Port 8080 (local)              │
└──────────┬───────────┘    └──────────┬─────────────────────────┘
           │                           │
           └───────────┬───────────────┘
                       ▼
           ┌───────────────────────┐
           │   Couche Partagée     │
           │ - SessionManager      │
           │ - ConfigManager       │
           │ - DB (MeekroDB)       │
           │ - Encryption          │
           └───────────────────────┘
```

---

## 3. Choix Technologique : Ratchet

**Bibliothèque recommandée : `cboden/ratchet`**

| Critère | Ratchet | Alternatives |
|---------|---------|--------------|
| Pure PHP | ✅ Oui | Swoole (extension), Node.js (autre stack) |
| Symfony compatible | ✅ Oui | - |
| Maintenance active | ✅ Oui | - |
| Pas de dépendance externe | ✅ Oui | Redis (Pusher), Kafka |
| Courbe d'apprentissage | Faible | Moyenne à élevée |

**Dépendances à ajouter :**
```json
{
    "cboden/ratchet": "^0.4.4",
    "react/event-loop": "^1.4"
}
```

---

## 4. Composants du Moteur WebSocket

### 4.1 Structure des fichiers proposée

```
/websocket/
├── bin/
│   └── server.php              # Point d'entrée du daemon
├── src/
│   ├── WebSocketServer.php     # Classe principale serveur
│   ├── ConnectionManager.php   # Gestion des connexions
│   ├── MessageHandler.php      # Traitement des messages
│   ├── AuthValidator.php       # Validation session/JWT
│   ├── EventBroadcaster.php    # Diffusion des événements
│   ├── RateLimiter.php         # Limitation du débit
│   └── Logger.php              # Logging WebSocket
├── config/
│   └── websocket.php           # Configuration (port, host, etc.)
└── logs/
    └── websocket.log
```

### 4.1.1 Fichier de configuration détaillé

**websocket/config/websocket.php :**
```php
<?php
declare(strict_types=1);

return [
    // Serveur
    'host' => '127.0.0.1',
    'port' => 8080,

    // Polling des événements
    'poll_interval_ms' => 200,  // Intervalle de polling en millisecondes
    'poll_batch_size' => 100,   // Nombre max d'événements par batch

    // Limites
    'max_connections_per_user' => 5,
    'max_message_size' => 65536,        // 64KB
    'rate_limit_messages' => 10,        // Messages par seconde
    'rate_limit_window_ms' => 1000,     // Fenêtre de rate limiting

    // Heartbeat
    'ping_interval_sec' => 30,
    'pong_timeout_sec' => 60,

    // Logging
    'log_file' => __DIR__ . '/../logs/websocket.log',
    'log_level' => 'info',  // debug, info, warning, error

    // Nettoyage
    'event_retention_hours' => 24,      // Durée de rétention des événements traités
    'cleanup_interval_sec' => 3600,     // Intervalle de nettoyage
];
```

### 4.2 Classes principales

**WebSocketServer.php** - Serveur principal (version complète)
```php
<?php
declare(strict_types=1);

namespace TeampassWebSocket;

use Ratchet\MessageComponentInterface;
use Ratchet\ConnectionInterface;

class WebSocketServer implements MessageComponentInterface
{
    private ConnectionManager $connections;
    private AuthValidator $authValidator;
    private MessageHandler $messageHandler;
    private Logger $logger;
    private array $config;

    public function __construct(
        ConnectionManager $connections,
        AuthValidator $authValidator,
        MessageHandler $messageHandler,
        Logger $logger,
        array $config
    ) {
        $this->connections = $connections;
        $this->authValidator = $authValidator;
        $this->messageHandler = $messageHandler;
        $this->logger = $logger;
        $this->config = $config;
    }

    public function onOpen(ConnectionInterface $conn): void
    {
        $this->logger->debug('New connection attempt', ['resourceId' => $conn->resourceId]);

        // Extraire les paramètres de la requête HTTP
        $queryString = $conn->httpRequest->getUri()->getQuery();
        parse_str($queryString, $params);

        // Extraire les cookies
        $cookies = [];
        $cookieHeader = $conn->httpRequest->getHeader('Cookie');
        if (!empty($cookieHeader)) {
            foreach (explode(';', $cookieHeader[0]) as $cookie) {
                $parts = explode('=', trim($cookie), 2);
                if (count($parts) === 2) {
                    $cookies[$parts[0]] = $parts[1];
                }
            }
        }

        // Authentification
        $userData = null;

        // Essayer JWT d'abord (pour clients API)
        if (isset($params['token'])) {
            $userData = $this->authValidator->validateFromJwt($params['token']);
        }

        // Sinon essayer session cookie
        if (!$userData && isset($cookies['PHPSESSID'])) {
            $userData = $this->authValidator->validateFromCookie($cookies['PHPSESSID']);
        }

        if (!$userData) {
            $this->logger->warning('Authentication failed', ['resourceId' => $conn->resourceId]);
            $conn->send(json_encode([
                'type' => 'error',
                'error_code' => 'auth_failed',
                'message' => 'Authentication required'
            ]));
            $conn->close();
            return;
        }

        // Vérifier le nombre max de connexions par utilisateur
        $userConns = $this->connections->getUserConnections($userData['user_id']);
        $maxConns = $this->config['max_connections_per_user'] ?? 5;

        if (count($userConns) >= $maxConns) {
            $this->logger->warning('Max connections exceeded', [
                'user_id' => $userData['user_id'],
                'current' => count($userConns)
            ]);
            $conn->send(json_encode([
                'type' => 'error',
                'error_code' => 'max_connections',
                'message' => "Maximum $maxConns connections per user"
            ]));
            $conn->close();
            return;
        }

        // Stocker les données utilisateur sur la connexion
        $conn->userData = $userData;
        $conn->connectedAt = time();
        $conn->lastPong = time();
        $conn->subscriptions = [];

        // Enregistrer la connexion
        $this->connections->addConnection($userData['user_id'], $conn);

        $this->logger->info('Connection established', [
            'resourceId' => $conn->resourceId,
            'user_id' => $userData['user_id'],
            'user_login' => $userData['user_login']
        ]);

        // Envoyer confirmation
        $conn->send(json_encode([
            'type' => 'connected',
            'user_id' => $userData['user_id'],
            'server_time' => time()
        ]));
    }

    public function onMessage(ConnectionInterface $from, $msg): void
    {
        // Vérifier la taille du message
        $maxSize = $this->config['max_message_size'] ?? 65536;
        if (strlen($msg) > $maxSize) {
            $from->send(json_encode([
                'type' => 'error',
                'error_code' => 'message_too_large',
                'message' => "Message exceeds $maxSize bytes"
            ]));
            return;
        }

        // Décoder le JSON
        $message = json_decode($msg, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            $from->send(json_encode([
                'type' => 'error',
                'error_code' => 'invalid_json',
                'message' => 'Invalid JSON format'
            ]));
            return;
        }

        $this->logger->debug('Message received', [
            'resourceId' => $from->resourceId,
            'action' => $message['action'] ?? 'unknown'
        ]);

        // Traiter le message
        $response = $this->messageHandler->handle($from, $message);

        if ($response) {
            $from->send(json_encode($response));
        }
    }

    public function onClose(ConnectionInterface $conn): void
    {
        $userId = $conn->userData['user_id'] ?? 'unknown';

        $this->connections->removeConnection($conn);

        $this->logger->info('Connection closed', [
            'resourceId' => $conn->resourceId,
            'user_id' => $userId
        ]);
    }

    public function onError(ConnectionInterface $conn, \Exception $e): void
    {
        $this->logger->error('Connection error', [
            'resourceId' => $conn->resourceId,
            'error' => $e->getMessage()
        ]);

        $conn->close();
    }
}
```

**ConnectionManager.php** - Gestion des connexions (version complète)
```php
<?php
declare(strict_types=1);

namespace TeampassWebSocket;

use Ratchet\ConnectionInterface;
use SplObjectStorage;

class ConnectionManager
{
    // Toutes les connexions
    private SplObjectStorage $connections;

    // Connexions indexées par user_id : [user_id => [conn1, conn2, ...]]
    private array $userConnections = [];

    // Connexions indexées par folder_id : [folder_id => [conn1, conn2, ...]]
    private array $folderSubscriptions = [];

    public function __construct()
    {
        $this->connections = new SplObjectStorage();
    }

    public function addConnection(int $userId, ConnectionInterface $conn): void
    {
        $this->connections->attach($conn);

        if (!isset($this->userConnections[$userId])) {
            $this->userConnections[$userId] = [];
        }
        $this->userConnections[$userId][$conn->resourceId] = $conn;
    }

    public function removeConnection(ConnectionInterface $conn): void
    {
        $this->connections->detach($conn);

        $userId = $conn->userData['user_id'] ?? null;

        // Retirer des connexions utilisateur
        if ($userId && isset($this->userConnections[$userId])) {
            unset($this->userConnections[$userId][$conn->resourceId]);
            if (empty($this->userConnections[$userId])) {
                unset($this->userConnections[$userId]);
            }
        }

        // Retirer des souscriptions folder
        foreach ($this->folderSubscriptions as $folderId => &$subscribers) {
            unset($subscribers[$conn->resourceId]);
            if (empty($subscribers)) {
                unset($this->folderSubscriptions[$folderId]);
            }
        }
    }

    public function subscribeToFolder(int $userId, int $folderId): void
    {
        if (!isset($this->userConnections[$userId])) {
            return;
        }

        if (!isset($this->folderSubscriptions[$folderId])) {
            $this->folderSubscriptions[$folderId] = [];
        }

        // Ajouter toutes les connexions de cet utilisateur
        foreach ($this->userConnections[$userId] as $resourceId => $conn) {
            $this->folderSubscriptions[$folderId][$resourceId] = $conn;

            // Tracker les souscriptions sur la connexion
            if (!isset($conn->subscriptions)) {
                $conn->subscriptions = [];
            }
            $conn->subscriptions[] = "folder:$folderId";
        }
    }

    public function unsubscribeFromFolder(int $userId, int $folderId): void
    {
        if (!isset($this->userConnections[$userId])) {
            return;
        }

        foreach ($this->userConnections[$userId] as $resourceId => $conn) {
            if (isset($this->folderSubscriptions[$folderId])) {
                unset($this->folderSubscriptions[$folderId][$resourceId]);
            }
        }
    }

    public function getUserConnections(int $userId): array
    {
        return $this->userConnections[$userId] ?? [];
    }

    public function getFolderSubscribers(int $folderId): array
    {
        return $this->folderSubscriptions[$folderId] ?? [];
    }

    public function getAllConnections(): SplObjectStorage
    {
        return $this->connections;
    }

    public function getConnectionCount(): int
    {
        return $this->connections->count();
    }

    public function broadcastToUser(int $userId, array $message): void
    {
        $json = json_encode($message);
        foreach ($this->getUserConnections($userId) as $conn) {
            $conn->send($json);
        }
    }

    public function broadcastToFolder(int $folderId, array $message): void
    {
        $json = json_encode($message);
        foreach ($this->getFolderSubscribers($folderId) as $conn) {
            $conn->send($json);
        }
    }

    public function broadcastToAll(array $message): void
    {
        $json = json_encode($message);
        foreach ($this->connections as $conn) {
            $conn->send($json);
        }
    }

    public function getStats(): array
    {
        return [
            'total_connections' => $this->connections->count(),
            'unique_users' => count($this->userConnections),
            'folder_subscriptions' => count($this->folderSubscriptions),
        ];
    }
}
```

**MessageHandler.php** - Routage et traitement des messages
```php
<?php
declare(strict_types=1);

namespace TeampassWebSocket;

use Ratchet\ConnectionInterface;

class MessageHandler
{
    private ConnectionManager $connections;
    private RateLimiter $rateLimiter;
    private Logger $logger;

    // Actions disponibles côté client
    private const ALLOWED_ACTIONS = [
        'subscribe',      // S'abonner à un canal (folder, user, system)
        'unsubscribe',    // Se désabonner
        'ping',           // Heartbeat client
        'get_status',     // État de la connexion
    ];

    public function handle(ConnectionInterface $conn, array $message): ?array
    {
        $action = $message['action'] ?? null;
        $requestId = $message['request_id'] ?? null;

        if (!in_array($action, self::ALLOWED_ACTIONS, true)) {
            return $this->error('unknown_action', "Action '$action' non reconnue", $requestId);
        }

        // Rate limiting
        if (!$this->rateLimiter->check($conn)) {
            return $this->error('rate_limited', 'Trop de messages, réessayez plus tard', $requestId);
        }

        return match ($action) {
            'subscribe' => $this->handleSubscribe($conn, $message),
            'unsubscribe' => $this->handleUnsubscribe($conn, $message),
            'ping' => $this->handlePing($conn, $requestId),
            'get_status' => $this->handleGetStatus($conn, $requestId),
            default => null
        };
    }

    private function handleSubscribe(ConnectionInterface $conn, array $message): array
    {
        $channel = $message['channel'] ?? null;
        $data = $message['data'] ?? [];
        $requestId = $message['request_id'] ?? null;

        if ($channel === 'folder' && isset($data['folder_id'])) {
            // Vérifier que l'utilisateur a accès à ce folder
            $userData = $conn->userData;
            $folderId = (int) $data['folder_id'];

            if (!in_array($folderId, $userData['accessible_folders'], true)) {
                return $this->error('forbidden', 'Accès non autorisé à ce dossier', $requestId);
            }

            $this->connections->subscribeToFolder($userData['user_id'], $folderId);

            return [
                'type' => 'response',
                'status' => 'success',
                'action' => 'subscribed',
                'channel' => 'folder',
                'folder_id' => $folderId,
                'request_id' => $requestId
            ];
        }

        return $this->error('invalid_channel', 'Canal invalide', $requestId);
    }

    private function handlePing(ConnectionInterface $conn, ?string $requestId): array
    {
        $conn->lastPong = time();
        return [
            'type' => 'pong',
            'timestamp' => time(),
            'request_id' => $requestId
        ];
    }

    private function handleGetStatus(ConnectionInterface $conn, ?string $requestId): array
    {
        return [
            'type' => 'status',
            'connected' => true,
            'user_id' => $conn->userData['user_id'] ?? null,
            'subscriptions' => $conn->subscriptions ?? [],
            'connected_at' => $conn->connectedAt ?? null,
            'request_id' => $requestId
        ];
    }

    private function error(string $code, string $message, ?string $requestId): array
    {
        return [
            'type' => 'error',
            'error_code' => $code,
            'message' => $message,
            'request_id' => $requestId
        ];
    }
}
```

**RateLimiter.php** - Limitation du débit par connexion
```php
<?php
declare(strict_types=1);

namespace TeampassWebSocket;

use Ratchet\ConnectionInterface;

class RateLimiter
{
    private int $maxMessages;
    private int $windowMs;

    // Stockage en mémoire : resourceId => [timestamps]
    private array $messageHistory = [];

    public function __construct(int $maxMessages = 10, int $windowMs = 1000)
    {
        $this->maxMessages = $maxMessages;
        $this->windowMs = $windowMs;
    }

    public function check(ConnectionInterface $conn): bool
    {
        $id = $conn->resourceId;
        $now = (int) (microtime(true) * 1000);
        $windowStart = $now - $this->windowMs;

        // Initialiser ou nettoyer l'historique
        if (!isset($this->messageHistory[$id])) {
            $this->messageHistory[$id] = [];
        }

        // Supprimer les timestamps hors fenêtre
        $this->messageHistory[$id] = array_filter(
            $this->messageHistory[$id],
            fn($ts) => $ts > $windowStart
        );

        // Vérifier la limite
        if (count($this->messageHistory[$id]) >= $this->maxMessages) {
            return false;
        }

        // Ajouter le timestamp actuel
        $this->messageHistory[$id][] = $now;
        return true;
    }

    public function cleanup(ConnectionInterface $conn): void
    {
        unset($this->messageHistory[$conn->resourceId]);
    }
}
```

**Logger.php** - Logging dédié WebSocket
```php
<?php
declare(strict_types=1);

namespace TeampassWebSocket;

class Logger
{
    private string $logFile;
    private string $level;

    private const LEVELS = ['debug' => 0, 'info' => 1, 'warning' => 2, 'error' => 3];

    public function __construct(string $logFile, string $level = 'info')
    {
        $this->logFile = $logFile;
        $this->level = $level;
    }

    public function debug(string $message, array $context = []): void
    {
        $this->log('debug', $message, $context);
    }

    public function info(string $message, array $context = []): void
    {
        $this->log('info', $message, $context);
    }

    public function warning(string $message, array $context = []): void
    {
        $this->log('warning', $message, $context);
    }

    public function error(string $message, array $context = []): void
    {
        $this->log('error', $message, $context);
    }

    private function log(string $level, string $message, array $context): void
    {
        if (self::LEVELS[$level] < self::LEVELS[$this->level]) {
            return;
        }

        $timestamp = date('Y-m-d H:i:s');
        $contextStr = $context ? ' ' . json_encode($context) : '';
        $line = "[$timestamp] [$level] $message$contextStr\n";

        file_put_contents($this->logFile, $line, FILE_APPEND | LOCK_EX);
    }
}
```

### 4.3 Format des messages

**Client → Serveur :**
```json
{
    "action": "subscribe",
    "channel": "folder",
    "data": {
        "folder_id": 42
    },
    "request_id": "uuid-v4"
}
```

**Serveur → Client :**
```json
{
    "type": "event",
    "event": "item_updated",
    "data": {
        "item_id": 123,
        "folder_id": 42,
        "updated_by": "john.doe",
        "timestamp": 1706745600
    },
    "request_id": "uuid-v4"
}
```

### 4.4 Catalogue des événements

| Événement | Target Type | Description | Payload |
|-----------|-------------|-------------|---------|
| `item_created` | folder | Nouvel item créé | `{item_id, folder_id, label, created_by}` |
| `item_updated` | folder | Item modifié | `{item_id, folder_id, label, updated_by}` |
| `item_deleted` | folder | Item supprimé | `{item_id, folder_id, deleted_by}` |
| `item_copied` | folder | Item copié | `{item_id, folder_id, new_item_id, copied_by}` |
| `folder_created` | folder | Nouveau dossier | `{folder_id, parent_id, title, created_by}` |
| `folder_updated` | folder | Dossier modifié | `{folder_id, title, updated_by}` |
| `folder_deleted` | folder | Dossier supprimé | `{folder_id, deleted_by}` |
| `folder_permission_changed` | folder | Permissions modifiées | `{folder_id, updated_by}` |
| `user_keys_ready` | user | Clés utilisateur prêtes | `{user_id, status}` |
| `task_progress` | user | Progression d'une tâche | `{task_id, task_type, progress, total, status}` |
| `task_completed` | user | Tâche terminée | `{task_id, task_type, success, message}` |
| `session_expired` | user | Session expirée | `{reason}` |
| `ldap_sync_progress` | broadcast | Synchro LDAP en cours | `{progress, total, status}` |
| `ldap_sync_completed` | broadcast | Synchro LDAP terminée | `{users_added, users_updated, users_disabled}` |
| `import_progress` | user | Import CSV en cours | `{progress, total, errors}` |
| `import_completed` | user | Import terminé | `{imported, skipped, errors}` |
| `export_ready` | user | Export prêt | `{export_id, filename, download_url}` |
| `system_maintenance` | broadcast | Maintenance système | `{message, scheduled_at}` |

### 4.5 Client JavaScript

**includes/js/teampass-websocket.js :**
```javascript
/**
 * TeamPass WebSocket Client
 * Gère la connexion WebSocket avec reconnexion automatique
 */
class TeamPassWebSocket {
  constructor(options = {}) {
    this.url = options.url || this._getDefaultUrl()
    this.token = options.token || null  // JWT pour API clients
    this.reconnectInterval = options.reconnectInterval || 3000
    this.maxReconnectAttempts = options.maxReconnectAttempts || 10
    this.pingInterval = options.pingInterval || 25000  // 25s (< 30s serveur)

    this.ws = null
    this.reconnectAttempts = 0
    this.pingTimer = null
    this.isIntentionallyClosed = false
    this.subscriptions = new Set()
    this.pendingRequests = new Map()
    this.eventHandlers = new Map()

    // Handlers par défaut
    this._onOpen = () => {}
    this._onClose = () => {}
    this._onError = () => {}
  }

  _getDefaultUrl() {
    const protocol = window.location.protocol === 'https:' ? 'wss:' : 'ws:'
    return `${protocol}//${window.location.host}/ws`
  }

  _generateRequestId() {
    return 'req_' + Math.random().toString(36).substring(2, 15)
  }

  connect() {
    if (this.ws && this.ws.readyState === WebSocket.OPEN) {
      return Promise.resolve()
    }

    return new Promise((resolve, reject) => {
      this.isIntentionallyClosed = false

      const url = this.token ? `${this.url}?token=${this.token}` : this.url
      this.ws = new WebSocket(url)

      this.ws.onopen = () => {
        console.log('[TeamPass WS] Connected')
        this.reconnectAttempts = 0
        this._startPingInterval()
        this._resubscribe()
        this._onOpen()
        resolve()
      }

      this.ws.onclose = (event) => {
        console.log('[TeamPass WS] Disconnected', event.code, event.reason)
        this._stopPingInterval()
        this._onClose(event)

        if (!this.isIntentionallyClosed) {
          this._scheduleReconnect()
        }
      }

      this.ws.onerror = (error) => {
        console.error('[TeamPass WS] Error', error)
        this._onError(error)
        reject(error)
      }

      this.ws.onmessage = (event) => {
        this._handleMessage(event.data)
      }
    })
  }

  disconnect() {
    this.isIntentionallyClosed = true
    this._stopPingInterval()
    if (this.ws) {
      this.ws.close(1000, 'Client disconnect')
      this.ws = null
    }
  }

  _scheduleReconnect() {
    if (this.reconnectAttempts >= this.maxReconnectAttempts) {
      console.error('[TeamPass WS] Max reconnect attempts reached')
      this._emit('reconnect_failed', {})
      return
    }

    this.reconnectAttempts++
    const delay = this.reconnectInterval * Math.min(this.reconnectAttempts, 5)
    console.log(`[TeamPass WS] Reconnecting in ${delay}ms (attempt ${this.reconnectAttempts})`)

    setTimeout(() => {
      if (!this.isIntentionallyClosed) {
        this.connect().catch(() => {})
      }
    }, delay)
  }

  _startPingInterval() {
    this._stopPingInterval()
    this.pingTimer = setInterval(() => {
      this.send({ action: 'ping' })
    }, this.pingInterval)
  }

  _stopPingInterval() {
    if (this.pingTimer) {
      clearInterval(this.pingTimer)
      this.pingTimer = null
    }
  }

  _resubscribe() {
    // Réabonner aux canaux après reconnexion
    this.subscriptions.forEach(sub => {
      const [channel, id] = sub.split(':')
      this._sendSubscribe(channel, id ? parseInt(id) : null)
    })
  }

  _handleMessage(data) {
    let message
    try {
      message = JSON.parse(data)
    } catch (e) {
      console.error('[TeamPass WS] Invalid JSON', data)
      return
    }

    // Réponse à une requête pending
    if (message.request_id && this.pendingRequests.has(message.request_id)) {
      const { resolve, reject } = this.pendingRequests.get(message.request_id)
      this.pendingRequests.delete(message.request_id)

      if (message.type === 'error') {
        reject(message)
      } else {
        resolve(message)
      }
      return
    }

    // Événement
    if (message.type === 'event') {
      this._emit(message.event, message.data)
    } else if (message.type === 'pong') {
      // Pong reçu, connexion vivante
    }
  }

  send(payload) {
    if (!this.ws || this.ws.readyState !== WebSocket.OPEN) {
      console.warn('[TeamPass WS] Not connected, cannot send')
      return Promise.reject(new Error('Not connected'))
    }

    const requestId = this._generateRequestId()
    payload.request_id = requestId

    return new Promise((resolve, reject) => {
      this.pendingRequests.set(requestId, { resolve, reject })

      // Timeout après 10s
      setTimeout(() => {
        if (this.pendingRequests.has(requestId)) {
          this.pendingRequests.delete(requestId)
          reject(new Error('Request timeout'))
        }
      }, 10000)

      this.ws.send(JSON.stringify(payload))
    })
  }

  _sendSubscribe(channel, id) {
    const data = {}
    if (channel === 'folder' && id) {
      data.folder_id = id
    }
    return this.send({ action: 'subscribe', channel, data })
  }

  subscribeToFolder(folderId) {
    const key = `folder:${folderId}`
    this.subscriptions.add(key)
    return this._sendSubscribe('folder', folderId)
  }

  unsubscribeFromFolder(folderId) {
    const key = `folder:${folderId}`
    this.subscriptions.delete(key)
    return this.send({ action: 'unsubscribe', channel: 'folder', data: { folder_id: folderId } })
  }

  // Event handling
  on(eventName, handler) {
    if (!this.eventHandlers.has(eventName)) {
      this.eventHandlers.set(eventName, [])
    }
    this.eventHandlers.get(eventName).push(handler)
    return this
  }

  off(eventName, handler) {
    if (!this.eventHandlers.has(eventName)) return
    const handlers = this.eventHandlers.get(eventName)
    const index = handlers.indexOf(handler)
    if (index > -1) handlers.splice(index, 1)
    return this
  }

  _emit(eventName, data) {
    const handlers = this.eventHandlers.get(eventName) || []
    handlers.forEach(handler => {
      try {
        handler(data)
      } catch (e) {
        console.error('[TeamPass WS] Handler error', e)
      }
    })

    // Émettre aussi sur '*' pour les handlers génériques
    const allHandlers = this.eventHandlers.get('*') || []
    allHandlers.forEach(handler => {
      try {
        handler(eventName, data)
      } catch (e) {
        console.error('[TeamPass WS] Handler error', e)
      }
    })
  }

  // Setters pour les callbacks
  onOpen(callback) { this._onOpen = callback; return this }
  onClose(callback) { this._onClose = callback; return this }
  onError(callback) { this._onError = callback; return this }
}

// Export global pour utilisation dans TeamPass
window.TeamPassWebSocket = TeamPassWebSocket
```

**Exemple d'utilisation dans TeamPass :**
```javascript
// Initialisation au chargement de la page
const tpWs = new TeamPassWebSocket()

tpWs
  .onOpen(() => {
    console.log('WebSocket connecté')
    // S'abonner au dossier actuellement affiché
    if (store.get('selectedFolderId')) {
      tpWs.subscribeToFolder(store.get('selectedFolderId'))
    }
  })
  .onClose(() => {
    toastr.warning('Connexion temps réel perdue, reconnexion...')
  })
  .on('item_updated', (data) => {
    toastr.info(`Item "${data.label}" modifié par ${data.updated_by}`)
    // Rafraîchir la liste si on est dans le bon dossier
    if (store.get('selectedFolderId') === data.folder_id) {
      refreshItemsList()
    }
  })
  .on('item_created', (data) => {
    toastr.success(`Nouvel item "${data.label}" créé`)
  })
  .on('task_progress', (data) => {
    updateProgressBar(data.task_id, data.progress, data.total)
  })
  .on('session_expired', () => {
    window.location.href = 'includes/core/logout.php?session_expired=1'
  })
  .on('reconnect_failed', () => {
    toastr.error('Impossible de se reconnecter au serveur')
  })

tpWs.connect()

// Lors du changement de dossier
function onFolderChange(newFolderId, oldFolderId) {
  if (oldFolderId) {
    tpWs.unsubscribeFromFolder(oldFolderId)
  }
  tpWs.subscribeToFolder(newFolderId)
}
```

---

## 5. Intégration avec le Système Existant

### 5.1 Authentification

Deux modes supportés :

**Mode Web (Session cookie) :**
```php
// Dans AuthValidator.php
public function validateFromCookie(string $sessionId): ?array
{
    $session = SessionManager::getSession();

    if (!$session->has('user-id')) {
        return null;
    }

    // Vérifier key_tempo en DB
    $user = DB::queryFirstRow(
        'SELECT key_tempo FROM %l WHERE id=%i',
        prefixTable('users'),
        $session->get('user-id')
    );

    if ($user['key_tempo'] !== $session->get('key')) {
        return null; // Session invalidée
    }

    return [
        'user_id' => $session->get('user-id'),
        'user_login' => $session->get('user-login'),
        'accessible_folders' => $session->get('user-accessible_folders'),
        'is_admin' => $session->get('user-admin') === '1'
    ];
}
```

**Mode API (JWT) :**
```php
public function validateFromJwt(string $token): ?array
{
    try {
        $decoded = JWT::decode($token, new Key($this->jwtKey, 'HS256'));

        return [
            'user_id' => $decoded->sub,
            'user_login' => $decoded->username,
            'accessible_folders' => explode(',', $decoded->allowed_folders),
            'permissions' => [
                'read' => $decoded->allowed_to_read,
                'create' => $decoded->allowed_to_create,
                'update' => $decoded->allowed_to_update,
                'delete' => $decoded->allowed_to_delete
            ]
        ];
    } catch (\Exception $e) {
        return null;
    }
}
```

### 5.2 Bridge avec le code existant

Pour que les opérations HTTP déclenchent des événements WebSocket, il faut un mécanisme de bridge :

**Option A : Table de notifications (recommandée pour v1)**
```sql
CREATE TABLE teampass_websocket_events (
    id INT AUTO_INCREMENT PRIMARY KEY,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    event_type VARCHAR(50) NOT NULL,
    target_type ENUM('user', 'folder', 'broadcast') NOT NULL,
    target_id INT,
    payload JSON NOT NULL,
    processed TINYINT(1) DEFAULT 0,
    INDEX idx_processed (processed, created_at)
);
```

Le serveur WebSocket poll cette table toutes les 100-500ms :

**EventBroadcaster.php** - Version complète :
```php
<?php
declare(strict_types=1);

namespace TeampassWebSocket;

use React\EventLoop\LoopInterface;

class EventBroadcaster
{
    private ConnectionManager $connections;
    private Logger $logger;
    private LoopInterface $loop;
    private array $config;

    public function __construct(
        ConnectionManager $connections,
        Logger $logger,
        LoopInterface $loop,
        array $config
    ) {
        $this->connections = $connections;
        $this->logger = $logger;
        $this->loop = $loop;
        $this->config = $config;
    }

    public function start(): void
    {
        // Polling des événements
        $pollInterval = ($this->config['poll_interval_ms'] ?? 200) / 1000;
        $this->loop->addPeriodicTimer($pollInterval, fn() => $this->pollAndBroadcast());

        // Nettoyage périodique des anciens événements
        $cleanupInterval = $this->config['cleanup_interval_sec'] ?? 3600;
        $this->loop->addPeriodicTimer($cleanupInterval, fn() => $this->cleanupOldEvents());

        $this->logger->info('EventBroadcaster started', [
            'poll_interval' => $pollInterval,
            'cleanup_interval' => $cleanupInterval
        ]);
    }

    public function pollAndBroadcast(): void
    {
        try {
            $batchSize = $this->config['poll_batch_size'] ?? 100;

            $events = DB::query(
                'SELECT * FROM %l WHERE processed = 0 ORDER BY created_at LIMIT %i',
                prefixTable('websocket_events'),
                $batchSize
            );

            if (empty($events)) {
                return;
            }

            $processedIds = [];

            foreach ($events as $event) {
                try {
                    $this->dispatchEvent($event);
                    $processedIds[] = $event['id'];
                } catch (\Exception $e) {
                    $this->logger->error('Failed to dispatch event', [
                        'event_id' => $event['id'],
                        'error' => $e->getMessage()
                    ]);
                }
            }

            // Marquer les événements comme traités en batch
            if (!empty($processedIds)) {
                DB::query(
                    'UPDATE %l SET processed = 1 WHERE id IN %ls',
                    prefixTable('websocket_events'),
                    $processedIds
                );
            }

        } catch (\Exception $e) {
            $this->logger->error('Poll error', ['error' => $e->getMessage()]);
        }
    }

    private function dispatchEvent(array $event): void
    {
        $payload = json_decode($event['payload'], true);
        $message = [
            'type' => 'event',
            'event' => $event['event_type'],
            'data' => $payload,
            'timestamp' => strtotime($event['created_at'])
        ];

        switch ($event['target_type']) {
            case 'user':
                $this->connections->broadcastToUser((int) $event['target_id'], $message);
                break;

            case 'folder':
                $this->connections->broadcastToFolder((int) $event['target_id'], $message);
                break;

            case 'broadcast':
                $this->connections->broadcastToAll($message);
                break;
        }

        $this->logger->debug('Event dispatched', [
            'event_type' => $event['event_type'],
            'target_type' => $event['target_type'],
            'target_id' => $event['target_id']
        ]);
    }

    private function cleanupOldEvents(): void
    {
        $retentionHours = $this->config['event_retention_hours'] ?? 24;

        $deleted = DB::query(
            'DELETE FROM %l WHERE processed = 1 AND created_at < DATE_SUB(NOW(), INTERVAL %i HOUR)',
            prefixTable('websocket_events'),
            $retentionHours
        );

        $this->logger->info('Cleaned up old events', ['retention_hours' => $retentionHours]);
    }
}
```

**Option B : IPC (Inter-Process Communication)**
Socket Unix ou named pipe entre PHP-FPM et daemon WebSocket. Plus performant mais plus complexe.

### 5.3 Fonction utilitaire pour émettre des événements

À ajouter dans `sources/main.functions.php` :
```php
/**
 * Émet un événement WebSocket
 *
 * @param string $eventType Type d'événement (item_created, item_updated, etc.)
 * @param string $targetType Type de cible ('user', 'folder', 'broadcast')
 * @param int|null $targetId ID de la cible (user_id ou folder_id)
 * @param array $payload Données de l'événement
 */
function emitWebSocketEvent(
    string $eventType,
    string $targetType,
    ?int $targetId,
    array $payload
): void {
    DB::insert(
        prefixTable('websocket_events'),
        [
            'event_type' => $eventType,
            'target_type' => $targetType,
            'target_id' => $targetId,
            'payload' => json_encode($payload)
        ]
    );
}
```

---

## 6. Configuration Serveur

### 6.1 Daemon WebSocket

**websocket/bin/server.php** - Version complète avec heartbeat et event loop :
```php
<?php
declare(strict_types=1);

// Vérifier qu'on est bien en CLI
if (php_sapi_name() !== 'cli') {
    die('This script must be run from command line');
}

// Paths
$rootPath = dirname(__DIR__, 2);
require $rootPath . '/vendor/autoload.php';
require $rootPath . '/includes/config/settings.php';
require $rootPath . '/includes/libraries/Database/Meekrodb/db.class.php';

// Configuration base de données
DB::$host = DB_HOST;
DB::$user = DB_USER;
DB::$password = DB_PASSWD;
DB::$dbName = DB_NAME;
DB::$port = DB_PORT;
DB::$encoding = DB_ENCODING;

use Ratchet\Server\IoServer;
use Ratchet\Http\HttpServer;
use Ratchet\WebSocket\WsServer;
use React\EventLoop\Loop;
use TeampassWebSocket\WebSocketServer;
use TeampassWebSocket\ConnectionManager;
use TeampassWebSocket\MessageHandler;
use TeampassWebSocket\AuthValidator;
use TeampassWebSocket\EventBroadcaster;
use TeampassWebSocket\RateLimiter;
use TeampassWebSocket\Logger;

// Charger la configuration
$config = require dirname(__DIR__) . '/config/websocket.php';

// Initialiser le logger
$logger = new Logger(
    $config['log_file'],
    $config['log_level']
);

$logger->info('Starting TeamPass WebSocket Server');

// Créer l'event loop
$loop = Loop::get();

// Initialiser les composants
$connectionManager = new ConnectionManager();
$rateLimiter = new RateLimiter(
    $config['rate_limit_messages'],
    $config['rate_limit_window_ms']
);
$authValidator = new AuthValidator($rootPath);
$messageHandler = new MessageHandler($connectionManager, $rateLimiter, $logger);

// Créer le serveur WebSocket
$wsServer = new WebSocketServer(
    $connectionManager,
    $authValidator,
    $messageHandler,
    $logger,
    $config
);

// Initialiser le broadcaster d'événements
$broadcaster = new EventBroadcaster($connectionManager, $logger, $loop, $config);
$broadcaster->start();

// Timer heartbeat pour détecter les connexions mortes
$pingInterval = $config['ping_interval_sec'] ?? 30;
$pongTimeout = $config['pong_timeout_sec'] ?? 60;

$loop->addPeriodicTimer($pingInterval, function () use ($connectionManager, $pongTimeout, $logger) {
    $now = time();
    foreach ($connectionManager->getAllConnections() as $conn) {
        // Vérifier si on a reçu un pong récemment
        $lastPong = $conn->lastPong ?? $conn->connectedAt ?? $now;
        if (($now - $lastPong) > $pongTimeout) {
            $logger->warning('Connection timeout, closing', ['resourceId' => $conn->resourceId]);
            $conn->close();
            continue;
        }

        // Envoyer un ping
        $conn->send(json_encode([
            'type' => 'ping',
            'timestamp' => $now
        ]));
    }
});

// Créer le serveur
$webSock = new \React\Socket\SocketServer(
    $config['host'] . ':' . $config['port'],
    [],
    $loop
);

$server = new IoServer(
    new HttpServer(
        new WsServer($wsServer)
    ),
    $webSock,
    $loop
);

$logger->info('WebSocket server started', [
    'host' => $config['host'],
    'port' => $config['port']
]);

echo "TeamPass WebSocket server started on ws://{$config['host']}:{$config['port']}\n";
echo "Press Ctrl+C to stop\n";

// Gérer les signaux pour arrêt propre
if (extension_loaded('pcntl')) {
    pcntl_signal(SIGTERM, function () use ($loop, $logger) {
        $logger->info('Received SIGTERM, shutting down');
        $loop->stop();
    });
    pcntl_signal(SIGINT, function () use ($loop, $logger) {
        $logger->info('Received SIGINT, shutting down');
        $loop->stop();
    });
}

$loop->run();
```

### 6.2 Service systemd

**/etc/systemd/system/teampass-websocket.service :**
```ini
[Unit]
Description=TeamPass WebSocket Server
After=network.target mysql.service

[Service]
Type=simple
User=www-data
WorkingDirectory=/var/www/html/TeamPass
ExecStart=/usr/bin/php websocket/bin/server.php
Restart=always
RestartSec=5
StandardOutput=journal
StandardError=journal

[Install]
WantedBy=multi-user.target
```

### 6.3 Configuration Apache (reverse proxy)

```apache
<VirtualHost *:443>
    # ... config SSL existante ...

    # Activer les modules nécessaires
    # a2enmod proxy proxy_wstunnel rewrite

    # Proxy WebSocket
    RewriteEngine On
    RewriteCond %{HTTP:Upgrade} websocket [NC]
    RewriteCond %{HTTP:Connection} upgrade [NC]
    RewriteRule ^/ws$ ws://127.0.0.1:8080/ [P,L]

    ProxyPass /ws ws://127.0.0.1:8080/
    ProxyPassReverse /ws ws://127.0.0.1:8080/
</VirtualHost>
```

### 6.4 Configuration Nginx (alternative)

```nginx
location /ws {
    proxy_pass http://127.0.0.1:8080;
    proxy_http_version 1.1;
    proxy_set_header Upgrade $http_upgrade;
    proxy_set_header Connection "upgrade";
    proxy_set_header Host $host;
    proxy_set_header X-Real-IP $remote_addr;
    proxy_read_timeout 86400;
}
```

---

## 7. Sécurité

| Aspect | Mesure |
|--------|--------|
| Authentification | Validation session/JWT sur handshake ET sur chaque message |
| Autorisation | Vérification permissions folder avant broadcast |
| Chiffrement | TLS obligatoire (WSS) via reverse proxy |
| Rate limiting | Max 10 messages/seconde par connexion |
| Taille messages | Max 64KB par message |
| Connexions | Max 5 connexions simultanées par utilisateur |
| Timeout | Ping/pong toutes les 30s, déconnexion après 60s sans réponse |
| Audit | Log de toutes les connexions/déconnexions |

---

## 8. Impact sur l'Existant

| Composant | Impact | Actions requises |
|-----------|--------|------------------|
| Base de données | Faible | Ajouter 1 table `websocket_events` |
| composer.json | Faible | Ajouter 2 dépendances |
| Configuration serveur | Moyen | Ajouter reverse proxy + systemd service |
| Code PHP existant | Faible | Ajouter appels `emitWebSocketEvent()` aux endroits clés |
| Frontend JS | Moyen | Ajouter client WebSocket + handlers |
| Installation | Moyen | Script d'installation du daemon |
| Documentation | Moyen | Documenter configuration serveur |

---

## 9. Estimation des Composants

| Composant | Fichiers | Complexité |
|-----------|----------|------------|
| Serveur WebSocket core | 5-6 | Moyenne |
| Intégration authentification | 1-2 | Moyenne |
| Bridge événements | 1-2 | Faible |
| Client JavaScript | 1 | Moyenne |
| Migration DB | 1 | Faible |
| Configuration serveur | 2-3 | Faible |
| Scripts installation/upgrade | 2 | Faible |

---

## 10. Dépendances Techniques

**Requises côté serveur :**
- PHP 8.1+ (déjà requis)
- Extension `pcntl` (pour daemon)
- Extension `posix` (pour daemon)
- mod_proxy_wstunnel (Apache) ou équivalent Nginx

**Vérification :**
```bash
php -m | grep -E "(pcntl|posix)"
a2enmod proxy proxy_wstunnel rewrite
```

---

## 10.1 Script de Migration Base de Données

**install/scripts/websocket_migration.php :**
```php
<?php
declare(strict_types=1);

/**
 * Migration script for WebSocket support
 * Run once during upgrade to add WebSocket tables
 */

// Prevent direct access
if (!defined('TP_UPGRADE')) {
    die('Direct access not allowed');
}

// Create websocket_events table
$query = "CREATE TABLE IF NOT EXISTS `" . $pre . "websocket_events` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `event_type` VARCHAR(50) NOT NULL COMMENT 'Type of event (item_created, item_updated, etc.)',
    `target_type` ENUM('user', 'folder', 'broadcast') NOT NULL COMMENT 'Target type for routing',
    `target_id` INT UNSIGNED NULL COMMENT 'Target ID (user_id or folder_id)',
    `payload` JSON NOT NULL COMMENT 'Event payload data',
    `processed` TINYINT(1) UNSIGNED DEFAULT 0 COMMENT 'Has this event been broadcast?',
    `processed_at` TIMESTAMP NULL COMMENT 'When was this event processed',
    INDEX `idx_unprocessed` (`processed`, `created_at`),
    INDEX `idx_cleanup` (`processed`, `created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";

DB::query($query);

// Create websocket_connections table (for monitoring/debugging)
$query = "CREATE TABLE IF NOT EXISTS `" . $pre . "websocket_connections` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT UNSIGNED NOT NULL,
    `resource_id` VARCHAR(50) NOT NULL COMMENT 'Ratchet connection resource ID',
    `connected_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `disconnected_at` TIMESTAMP NULL,
    `ip_address` VARCHAR(45) NULL,
    `user_agent` TEXT NULL,
    INDEX `idx_user` (`user_id`),
    INDEX `idx_active` (`disconnected_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";

DB::query($query);

// Add websocket settings to teampass_misc
$settings = [
    ['type' => 'admin', 'intitule' => 'websocket_enabled', 'valeur' => '0'],
    ['type' => 'admin', 'intitule' => 'websocket_port', 'valeur' => '8080'],
    ['type' => 'admin', 'intitule' => 'websocket_host', 'valeur' => '127.0.0.1'],
];

foreach ($settings as $setting) {
    $exists = DB::queryFirstRow(
        'SELECT id FROM %l WHERE intitule = %s',
        $pre . 'misc',
        $setting['intitule']
    );

    if (!$exists) {
        DB::insert($pre . 'misc', $setting);
    }
}

echo "WebSocket migration completed successfully.\n";
```

---

## 10.2 Script d'Installation et Vérification

**websocket/bin/check-requirements.php :**
```php
<?php
declare(strict_types=1);

/**
 * TeamPass WebSocket Requirements Checker
 * Vérifie que toutes les dépendances sont présentes
 */

echo "=== TeamPass WebSocket Requirements Check ===\n\n";

$errors = [];
$warnings = [];

// 1. Version PHP
echo "Checking PHP version... ";
if (version_compare(PHP_VERSION, '8.1.0', '>=')) {
    echo "OK (" . PHP_VERSION . ")\n";
} else {
    echo "FAIL\n";
    $errors[] = "PHP 8.1+ required, found " . PHP_VERSION;
}

// 2. Extension pcntl
echo "Checking pcntl extension... ";
if (extension_loaded('pcntl')) {
    echo "OK\n";
} else {
    echo "WARNING\n";
    $warnings[] = "pcntl extension not loaded (recommended for signal handling)";
}

// 3. Extension posix
echo "Checking posix extension... ";
if (extension_loaded('posix')) {
    echo "OK\n";
} else {
    echo "WARNING\n";
    $warnings[] = "posix extension not loaded (recommended for daemon mode)";
}

// 4. Composer dependencies
echo "Checking Ratchet dependency... ";
$composerPath = dirname(__DIR__, 2) . '/vendor/cboden/ratchet';
if (is_dir($composerPath)) {
    echo "OK\n";
} else {
    echo "FAIL\n";
    $errors[] = "Ratchet not installed. Run: composer require cboden/ratchet";
}

echo "Checking React EventLoop... ";
$reactPath = dirname(__DIR__, 2) . '/vendor/react/event-loop';
if (is_dir($reactPath)) {
    echo "OK\n";
} else {
    echo "FAIL\n";
    $errors[] = "React EventLoop not installed. Run: composer require react/event-loop";
}

// 5. Configuration file
echo "Checking config file... ";
$configPath = dirname(__DIR__) . '/config/websocket.php';
if (file_exists($configPath)) {
    echo "OK\n";
} else {
    echo "FAIL\n";
    $errors[] = "Config file not found: $configPath";
}

// 6. Log directory
echo "Checking log directory... ";
$logDir = dirname(__DIR__) . '/logs';
if (is_dir($logDir) && is_writable($logDir)) {
    echo "OK\n";
} elseif (!is_dir($logDir)) {
    echo "Creating... ";
    if (mkdir($logDir, 0755, true)) {
        echo "OK\n";
    } else {
        echo "FAIL\n";
        $errors[] = "Cannot create log directory: $logDir";
    }
} else {
    echo "FAIL\n";
    $errors[] = "Log directory not writable: $logDir";
}

// 7. Database settings
echo "Checking database config... ";
$settingsPath = dirname(__DIR__, 2) . '/includes/config/settings.php';
if (file_exists($settingsPath)) {
    echo "OK\n";
} else {
    echo "FAIL\n";
    $errors[] = "Database settings not found. TeamPass must be installed first.";
}

// 8. Port availability
echo "Checking port 8080... ";
$socket = @fsockopen('127.0.0.1', 8080, $errno, $errstr, 1);
if ($socket) {
    fclose($socket);
    echo "IN USE (server may already be running)\n";
} else {
    echo "AVAILABLE\n";
}

// Summary
echo "\n=== Summary ===\n";

if (empty($errors) && empty($warnings)) {
    echo "All requirements met! You can start the WebSocket server.\n";
    echo "\nTo start: php websocket/bin/server.php\n";
    exit(0);
}

if (!empty($warnings)) {
    echo "\nWarnings:\n";
    foreach ($warnings as $w) {
        echo "  - $w\n";
    }
}

if (!empty($errors)) {
    echo "\nErrors (must fix before starting):\n";
    foreach ($errors as $e) {
        echo "  - $e\n";
    }
    exit(1);
}

exit(0);
```

**websocket/bin/install.sh** - Script d'installation automatique :
```bash
#!/bin/bash
set -e

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
ROOT_DIR="$(dirname "$(dirname "$SCRIPT_DIR")")"

echo "=== TeamPass WebSocket Installation ==="
echo ""

# 1. Install composer dependencies
echo "Installing Composer dependencies..."
cd "$ROOT_DIR"
composer require cboden/ratchet:^0.4.4 react/event-loop:^1.4 --no-interaction

# 2. Create directories
echo "Creating directories..."
mkdir -p "$SCRIPT_DIR/../logs"
chmod 755 "$SCRIPT_DIR/../logs"

# 3. Copy config if not exists
if [ ! -f "$SCRIPT_DIR/../config/websocket.php" ]; then
    echo "Creating default config..."
    mkdir -p "$SCRIPT_DIR/../config"
    cat > "$SCRIPT_DIR/../config/websocket.php" << 'PHPCONFIG'
<?php
declare(strict_types=1);

return [
    'host' => '127.0.0.1',
    'port' => 8080,
    'poll_interval_ms' => 200,
    'poll_batch_size' => 100,
    'max_connections_per_user' => 5,
    'max_message_size' => 65536,
    'rate_limit_messages' => 10,
    'rate_limit_window_ms' => 1000,
    'ping_interval_sec' => 30,
    'pong_timeout_sec' => 60,
    'log_file' => __DIR__ . '/../logs/websocket.log',
    'log_level' => 'info',
    'event_retention_hours' => 24,
    'cleanup_interval_sec' => 3600,
];
PHPCONFIG
fi

# 4. Check requirements
echo ""
echo "Checking requirements..."
php "$SCRIPT_DIR/check-requirements.php"

# 5. Setup systemd service (if root)
if [ "$EUID" -eq 0 ]; then
    echo ""
    read -p "Install systemd service? [y/N] " -n 1 -r
    echo
    if [[ $REPLY =~ ^[Yy]$ ]]; then
        cat > /etc/systemd/system/teampass-websocket.service << SYSTEMD
[Unit]
Description=TeamPass WebSocket Server
After=network.target mysql.service

[Service]
Type=simple
User=www-data
WorkingDirectory=$ROOT_DIR
ExecStart=/usr/bin/php $SCRIPT_DIR/server.php
Restart=always
RestartSec=5
StandardOutput=journal
StandardError=journal

[Install]
WantedBy=multi-user.target
SYSTEMD
        systemctl daemon-reload
        echo "Systemd service installed. Use: systemctl start teampass-websocket"
    fi
else
    echo ""
    echo "Run as root to install systemd service."
fi

echo ""
echo "=== Installation Complete ==="
echo ""
echo "Next steps:"
echo "1. Configure Apache/Nginx reverse proxy (see documentation)"
echo "2. Run database migration"
echo "3. Start server: php $SCRIPT_DIR/server.php"
echo "   Or with systemd: systemctl start teampass-websocket"
```

---

## 11. Recommandations

1. **Commencer par le moteur** - Implémenter le serveur WebSocket avec authentification et broadcast basique

2. **Utiliser la table de polling** plutôt qu'IPC pour la v1 - Plus simple à débugger et maintenir

3. **Prévoir un fallback** - Si WebSocket échoue, le système existant continue de fonctionner

4. **Monitoring** - Ajouter des métriques (connexions actives, messages/s, latence)

5. **Tests** - Prévoir tests de charge (combien de connexions simultanées ?)

---

## 12. Points d'Intégration dans le Code Existant

Pour émettre des événements WebSocket depuis le code PHP existant, voici les fichiers à modifier :

### 12.1 Items (sources/items.queries.php)

```php
// Après création d'un item (case 'new_item')
emitWebSocketEvent(
    'item_created',
    'folder',
    (int) $folderId,
    [
        'item_id' => $newItemId,
        'folder_id' => $folderId,
        'label' => $label,
        'created_by' => $session->get('user-login')
    ]
);

// Après modification d'un item (case 'update_item')
emitWebSocketEvent(
    'item_updated',
    'folder',
    (int) $folderId,
    [
        'item_id' => $itemId,
        'folder_id' => $folderId,
        'label' => $label,
        'updated_by' => $session->get('user-login')
    ]
);

// Après suppression d'un item (case 'delete_item')
emitWebSocketEvent(
    'item_deleted',
    'folder',
    (int) $folderId,
    [
        'item_id' => $itemId,
        'folder_id' => $folderId,
        'deleted_by' => $session->get('user-login')
    ]
);
```

### 12.2 Folders (sources/folders.queries.php)

```php
// Après création d'un dossier
emitWebSocketEvent(
    'folder_created',
    'folder',
    (int) $parentId,
    [
        'folder_id' => $newFolderId,
        'parent_id' => $parentId,
        'title' => $title,
        'created_by' => $session->get('user-login')
    ]
);

// Après modification des permissions
emitWebSocketEvent(
    'folder_permission_changed',
    'folder',
    (int) $folderId,
    [
        'folder_id' => $folderId,
        'updated_by' => $session->get('user-login')
    ]
);
```

### 12.3 Users (sources/users.queries.php)

```php
// Quand les clés utilisateur sont prêtes
emitWebSocketEvent(
    'user_keys_ready',
    'user',
    (int) $userId,
    [
        'user_id' => $userId,
        'status' => 'ready'
    ]
);
```

### 12.4 Background Tasks (scripts/background_tasks___*.php)

```php
// Progression d'une tâche longue
emitWebSocketEvent(
    'task_progress',
    'user',
    (int) $userId,
    [
        'task_id' => $taskId,
        'task_type' => 'encryption',
        'progress' => $current,
        'total' => $total,
        'status' => 'in_progress'
    ]
);

// Tâche terminée
emitWebSocketEvent(
    'task_completed',
    'user',
    (int) $userId,
    [
        'task_id' => $taskId,
        'task_type' => 'encryption',
        'success' => true,
        'message' => 'Chiffrement terminé'
    ]
);
```

### 12.5 LDAP Sync (sources/ldap.queries.php)

```php
// Progression sync LDAP
emitWebSocketEvent(
    'ldap_sync_progress',
    'broadcast',
    null,
    [
        'progress' => $current,
        'total' => $total,
        'status' => 'syncing'
    ]
);

// Sync terminée
emitWebSocketEvent(
    'ldap_sync_completed',
    'broadcast',
    null,
    [
        'users_added' => $added,
        'users_updated' => $updated,
        'users_disabled' => $disabled
    ]
);
```

### 12.6 Import/Export (sources/import.queries.php, sources/export.queries.php)

```php
// Progression import
emitWebSocketEvent(
    'import_progress',
    'user',
    (int) $userId,
    [
        'progress' => $current,
        'total' => $total,
        'errors' => $errorCount
    ]
);

// Export prêt
emitWebSocketEvent(
    'export_ready',
    'user',
    (int) $userId,
    [
        'export_id' => $exportId,
        'filename' => $filename,
        'download_url' => $downloadUrl
    ]
);
```

### 12.7 Session (sources/identify.php)

```php
// Session expirée (lors du check dans core.php)
emitWebSocketEvent(
    'session_expired',
    'user',
    (int) $userId,
    [
        'reason' => 'timeout'
    ]
);
```

---

## 13. Cas d'Usage Prioritaires (Phase 1)

Pour la première implémentation, prioriser ces cas d'usage :

| Priorité | Cas d'usage | Valeur utilisateur |
|----------|-------------|-------------------|
| **P1** | Notification modification item | Évite les conflits d'édition |
| **P1** | Progression tâches longues | Feedback immédiat |
| **P1** | Session expirée | Sécurité + UX |
| **P2** | Création/suppression items | Collaboration temps réel |
| **P2** | Clés utilisateur prêtes | Onboarding fluide |
| **P3** | Import/export terminé | Confort |
| **P3** | Sync LDAP terminée | Admin |

---

## 14. Plan d'Implémentation Détaillé

### Phase 1 : Infrastructure (Fondations)

| # | Tâche | Fichiers | Dépendances |
|---|-------|----------|-------------|
| 1.1 | Installer dépendances Composer | composer.json | - |
| 1.2 | Créer structure dossiers | websocket/* | 1.1 |
| 1.3 | Créer fichier de configuration | websocket/config/websocket.php | 1.2 |
| 1.4 | Implémenter Logger | websocket/src/Logger.php | 1.2 |
| 1.5 | Créer migration DB | install/scripts/websocket_migration.php | - |

### Phase 2 : Serveur WebSocket Core

| # | Tâche | Fichiers | Dépendances |
|---|-------|----------|-------------|
| 2.1 | Implémenter RateLimiter | websocket/src/RateLimiter.php | 1.4 |
| 2.2 | Implémenter ConnectionManager | websocket/src/ConnectionManager.php | 1.4 |
| 2.3 | Implémenter AuthValidator | websocket/src/AuthValidator.php | 1.4 |
| 2.4 | Implémenter MessageHandler | websocket/src/MessageHandler.php | 2.1, 2.2 |
| 2.5 | Implémenter WebSocketServer | websocket/src/WebSocketServer.php | 2.2, 2.3, 2.4 |
| 2.6 | Créer le daemon server.php | websocket/bin/server.php | 2.5 |

### Phase 3 : Bridge Événements

| # | Tâche | Fichiers | Dépendances |
|---|-------|----------|-------------|
| 3.1 | Implémenter EventBroadcaster | websocket/src/EventBroadcaster.php | 2.2 |
| 3.2 | Ajouter fonction emitWebSocketEvent | sources/main.functions.php | 1.5 |
| 3.3 | Intégrer polling dans server.php | websocket/bin/server.php | 3.1 |

### Phase 4 : Client JavaScript

| # | Tâche | Fichiers | Dépendances |
|---|-------|----------|-------------|
| 4.1 | Créer TeamPassWebSocket class | includes/js/teampass-websocket.js | - |
| 4.2 | Intégrer dans page principale | pages/items.js.php | 4.1 |
| 4.3 | Ajouter handlers d'événements | pages/items.js.php | 4.2 |

### Phase 5 : Intégration Backend

| # | Tâche | Fichiers | Dépendances |
|---|-------|----------|-------------|
| 5.1 | Intégrer events items | sources/items.queries.php | 3.2 |
| 5.2 | Intégrer events folders | sources/folders.queries.php | 3.2 |
| 5.3 | Intégrer events users | sources/users.queries.php | 3.2 |

### Phase 6 : DevOps & Documentation

| # | Tâche | Fichiers | Dépendances |
|---|-------|----------|-------------|
| 6.1 | Script check-requirements | websocket/bin/check-requirements.php | 2.6 |
| 6.2 | Script d'installation | websocket/bin/install.sh | 6.1 |
| 6.3 | Service systemd | docs/systemd-service.md | 6.2 |
| 6.4 | Config Apache/Nginx | docs/reverse-proxy.md | 6.3 |
| 6.5 | Documentation admin | docs/websocket-admin.md | 6.4 |

---

## 15. Prochaines Étapes Immédiates

1. [x] Compléter l'étude technique (ce document)
2. [x] **Valider l'architecture** avec le responsable projet
3. [x] Installer les dépendances : `composer require cboden/ratchet react/event-loop`
4. [ ] Créer la structure de fichiers `/websocket/`
5. [ ] Implémenter Phase 1 (Infrastructure)
6. [ ] Tester le daemon en mode développement

---

## 16. Critères de Validation

Avant de considérer l'implémentation comme terminée :

- [ ] Le serveur WebSocket démarre sans erreur
- [ ] L'authentification session fonctionne
- [ ] L'authentification JWT fonctionne
- [ ] Les connexions sont limitées par utilisateur
- [ ] Le rate limiting fonctionne
- [ ] Le heartbeat détecte les connexions mortes
- [ ] Les événements sont broadcast correctement (folder, user, broadcast)
- [ ] Le client JS se reconnecte automatiquement
- [ ] Les logs sont exploitables
- [ ] Le service systemd redémarre après crash
- [ ] La documentation admin est complète

---

## 17. Récapitulatif des Fichiers à Créer

### Structure complète :

```
/var/www/html/TeamPass/
├── websocket/
│   ├── bin/
│   │   ├── server.php              # Point d'entrée du daemon
│   │   ├── check-requirements.php  # Vérification des prérequis
│   │   └── install.sh              # Script d'installation
│   ├── src/
│   │   ├── WebSocketServer.php     # Serveur principal Ratchet
│   │   ├── ConnectionManager.php   # Gestion des connexions
│   │   ├── MessageHandler.php      # Routage des messages
│   │   ├── AuthValidator.php       # Validation session/JWT
│   │   ├── EventBroadcaster.php    # Polling et diffusion
│   │   ├── RateLimiter.php         # Limitation du débit
│   │   └── Logger.php              # Logging dédié
│   ├── config/
│   │   └── websocket.php           # Configuration
│   └── logs/
│       └── .gitkeep
├── includes/
│   └── js/
│       └── teampass-websocket.js   # Client JavaScript
├── install/
│   └── scripts/
│       └── websocket_migration.php # Migration DB
└── sources/
    └── main.functions.php          # + fonction emitWebSocketEvent()
```

### Modifications de fichiers existants :

| Fichier | Modification |
|---------|--------------|
| composer.json | Ajouter `cboden/ratchet`, `react/event-loop` |
| sources/main.functions.php | Ajouter `emitWebSocketEvent()` |
| sources/items.queries.php | Ajouter appels événements |
| sources/folders.queries.php | Ajouter appels événements |
| sources/users.queries.php | Ajouter appels événements |
| pages/items.js.php | Intégrer client WebSocket |
| index.php | Charger teampass-websocket.js |

---

## 18. Commandes de Démarrage Rapide

```bash
# 1. Installation des dépendances
cd /var/www/html/TeamPass
composer require cboden/ratchet:^0.4.4 react/event-loop:^1.4

# 2. Vérification des prérequis
php websocket/bin/check-requirements.php

# 3. Lancement en mode développement (foreground)
php websocket/bin/server.php

# 4. Lancement en production (systemd)
sudo systemctl start teampass-websocket
sudo systemctl enable teampass-websocket

# 5. Vérification du status
sudo systemctl status teampass-websocket
tail -f websocket/logs/websocket.log
```