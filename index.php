<?php
/**
 * Discord Channel Messages API (PHP)
 * ดึงข้อความจากช่อง Discord ผ่าน REST API
 */

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Headers: X-API-Key, Content-Type');
header('Access-Control-Allow-Methods: GET, OPTIONS');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

$configFile = __DIR__ . '/config.php';
if (!is_file($configFile)) {
    http_response_code(503);
    echo json_encode([
        'error' => 'ยังไม่มี config.php — บนเซิร์ฟให้คัดลอก config.example.php เป็น config.php แล้วใส่ discord_bot_token',
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

$config = require $configFile;

// ─── Helpers ───────────────────────────────────────────────

function json_response($data, int $status = 200): void
{
    http_response_code($status);
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

function error_response(string $message, int $status = 400): void
{
    json_response(['error' => $message], $status);
}

function verify_api_key(array $config): void
{
    $keys = $config['api_keys'] ?? [];
    if (empty($keys)) {
        return;
    }
    $provided = $_SERVER['HTTP_X_API_KEY'] ?? '';
    if (!$provided || !in_array($provided, $keys, true)) {
        error_response('API Key ไม่ถูกต้องหรือไม่ได้ส่งมา', 401);
    }
}

function discord_get(string $path, string $token, array $params = []): array
{
    $base = 'https://discord.com/api/v10';
    $url = $base . $path;
    if ($params) {
        $url .= '?' . http_build_query($params);
    }

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_HTTPHEADER     => ["Authorization: Bot {$token}"],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 30,
        CURLOPT_SSL_VERIFYPEER => true,
    ]);

    $body = curl_exec($ch);
    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err = curl_error($ch);
    curl_close($ch);

    if ($err) {
        error_response("cURL error: {$err}", 502);
    }
    if ($status === 403) {
        error_response('บอทไม่มีสิทธิ์เข้าถึงทรัพยากรนี้', 403);
    }
    if ($status === 404) {
        error_response('ไม่พบทรัพยากร (ตรวจสอบ ID)', 404);
    }
    if ($status === 429) {
        $data = json_decode($body, true);
        $retry = $data['retry_after'] ?? 1;
        error_response("Rate limited — ลองใหม่ใน {$retry} วินาที", 429);
    }
    if ($status >= 400) {
        error_response("Discord API error ({$status}): {$body}", $status);
    }

    return json_decode($body, true) ?? [];
}

function fetch_all_messages(string $channel_id, string $token, ?int $limit = null): array
{
    $all = [];
    $before = null;
    $max = $limit ?? 999999;

    while (count($all) < $max) {
        $batch_size = min(100, $max - count($all));
        $params = ['limit' => $batch_size];
        if ($before) {
            $params['before'] = $before;
        }

        $batch = discord_get("/channels/{$channel_id}/messages", $token, $params);
        if (empty($batch)) {
            break;
        }

        $all = array_merge($all, $batch);
        $before = end($batch)['id'];

        if (count($batch) < $batch_size) {
            break;
        }
    }

    return array_reverse($all);
}

function format_message(array $msg): array
{
    $author = $msg['author'] ?? [];
    return [
        'id'                   => $msg['id'],
        'created_at'           => $msg['timestamp'] ?? null,
        'author'               => [
            'id'           => $author['id'] ?? null,
            'name'         => $author['username'] ?? null,
            'display_name' => $author['global_name'] ?? $author['username'] ?? null,
            'bot'          => $author['bot'] ?? false,
        ],
        'content'              => $msg['content'] ?? '',
        'attachments'          => array_map(function ($a) {
            return ['url' => $a['url'], 'filename' => $a['filename']];
        }, $msg['attachments'] ?? []),
        'embeds'               => $msg['embeds'] ?? [],
        'reference_message_id' => ($msg['message_reference'] ?? [])['message_id'] ?? null,
    ];
}

// ─── Routing ───────────────────────────────────────────────

$uri = $_SERVER['REQUEST_URI'];
$path = parse_url($uri, PHP_URL_PATH);
$path = rtrim($path, '/');

// Remove base directory prefix if running in subdirectory
$script_dir = dirname($_SERVER['SCRIPT_NAME']);
if ($script_dir !== '/' && $script_dir !== '\\') {
    $path = substr($path, strlen($script_dir)) ?: '/';
}

$token = $config['discord_bot_token'];

// GET /
if ($path === '' || $path === '/') {
    json_response([
        'service'   => 'Discord Messages API',
        'version'   => '1.0.0 (PHP)',
        'endpoints' => [
            'GET /api/guild/{guild_id}/channels',
            'GET /api/channels/{channel_id}/messages',
            'GET /api/channels/{channel_id}/messages/filter?keywords=...',
        ],
    ]);
}

// GET /api/guild/{guild_id}/channels
if (preg_match('#^/api/guild/(\d+)/channels$#', $path, $m)) {
    verify_api_key($config);
    $guild_id = $m[1];
    $channels = discord_get("/guilds/{$guild_id}/channels", $token);
    $text = array_values(array_filter($channels, fn($ch) => ($ch['type'] ?? -1) === 0));
    $result = array_map(fn($ch) => [
        'id'   => $ch['id'],
        'name' => $ch['name'],
        'type' => $ch['type'],
    ], $text);
    json_response([
        'guild_id' => $guild_id,
        'channels' => $result,
        'count'    => count($result),
    ]);
}

// GET /api/channels/{channel_id}/messages/filter
if (preg_match('#^/api/channels/(\d+)/messages/filter$#', $path, $m)) {
    verify_api_key($config);
    $channel_id = $m[1];
    $keywords_raw = $_GET['keywords'] ?? '';
    $keywords = array_filter(array_map('trim', explode(',', $keywords_raw)));

    if (empty($keywords)) {
        error_response('ต้องระบุ keywords อย่างน้อย 1 คำ', 400);
    }

    $limit = isset($_GET['limit']) ? (int) $_GET['limit'] : null;
    if ($limit !== null && ($limit < 1 || $limit > 10000)) {
        error_response('limit ต้องอยู่ระหว่าง 1-10000', 400);
    }

    $raw = fetch_all_messages($channel_id, $token, $limit);
    $all = array_map('format_message', $raw);

    $filtered = array_values(array_filter($all, function ($msg) use ($keywords) {
        foreach ($keywords as $kw) {
            if (mb_strpos($msg['content'], $kw) !== false) {
                return true;
            }
        }
        return false;
    }));

    json_response([
        'channel_id'     => $channel_id,
        'fetched_at'     => gmdate('c'),
        'keywords'       => array_values($keywords),
        'total_messages'  => count($all),
        'filtered_count' => count($filtered),
        'messages'       => $filtered,
    ]);
}

// GET /api/channels/{channel_id}/messages
if (preg_match('#^/api/channels/(\d+)/messages$#', $path, $m)) {
    verify_api_key($config);
    $channel_id = $m[1];

    $limit = isset($_GET['limit']) ? (int) $_GET['limit'] : null;
    if ($limit !== null && ($limit < 1 || $limit > 10000)) {
        error_response('limit ต้องอยู่ระหว่าง 1-10000', 400);
    }

    $raw = fetch_all_messages($channel_id, $token, $limit);
    $messages = array_map('format_message', $raw);

    json_response([
        'channel_id'    => $channel_id,
        'fetched_at'    => gmdate('c'),
        'message_count' => count($messages),
        'messages'      => $messages,
    ]);
}

// GET /api/generate-key
if ($path === '/api/generate-key') {
    $master = $_GET['master'] ?? '';
    $real_master = $config['master_key'] ?? '';
    if (!$real_master || !hash_equals($real_master, $master)) {
        error_response('Master key ไม่ถูกต้อง', 403);
    }
    $new_key = bin2hex(random_bytes(32));
    json_response([
        'api_key' => $new_key,
        'note'    => 'เก็บ key นี้ไว้ — จะไม่แสดงอีก (ต้องเพิ่มใน config.php เอง)',
    ]);
}

// Not found
error_response('ไม่พบ endpoint นี้', 404);
