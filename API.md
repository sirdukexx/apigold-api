# Discord Messages API — เอกสาร API

ดึงข้อความจากช่อง Discord ผ่านบอท (REST) ฝั่งเซิร์ฟเวอร์เรียก Discord API v10

## Base URL

- **Production (ตัวอย่าง):** `https://phpstack-1050210-6313557.cloudwaysapps.com`
- แทนที่ด้วยโดเมนของคุณได้ (รวม path ย่อยถ้า deploy ใต้โฟลเดอร์)

ทุก response เป็น **JSON** (`Content-Type: application/json; charset=utf-8`)

---

## การยืนยันตัวตน (API Key)

- ถ้าใน `config.php` ตั้ง `'api_keys' => []` (ว่าง) → **ไม่ต้องส่ง key**
- ถ้ามีค่าใน `api_keys` → ทุก request ต้องส่ง header:

```http
X-API-Key: <คีย์ที่ตรงกับหนึ่งในค่าใน api_keys>
```

ตอบ `401` พร้อม `{ "error": "..." }` ถ้าไม่ส่งหรือ key ไม่ตรง

---

## CORS

- `Access-Control-Allow-Origin: *`
- รองรับ `OPTIONS` (preflight)
- Header ที่อนุญาต: `X-API-Key`, `Content-Type`

---

## Endpoints

### 1) ข้อมูลบริการ

| | |
|---|---|
| **Method** | `GET` |
| **Path** | `/` |

**Response 200**

```json
{
  "service": "Discord Messages API",
  "version": "1.0.0 (PHP)",
  "endpoints": [ "..." ]
}
```

---

### 2) รายชื่อช่องข้อความในเซิร์ฟเวอร์

| | |
|---|---|
| **Method** | `GET` |
| **Path** | `/api/guild/{guild_id}/channels` |

**Path parameters**

| พารามิเตอร์ | คำอธิบาย |
|------------|----------|
| `guild_id` | รหัสเซิร์ฟเวอร์ Discord (ตัวเลข) |

**Response 200**

```json
{
  "guild_id": "1217662250458943609",
  "channels": [
    { "id": "...", "name": "ทั่วไป", "type": 0 }
  ],
  "count": 12
}
```

คืนเฉพาะช่องประเภท **Text** (`type: 0`)

---

### 3) ดึงข้อความในช่อง

| | |
|---|---|
| **Method** | `GET` |
| **Path** | `/api/channels/{channel_id}/messages` |

**Path parameters**

| พารามิเตอร์ | คำอธิบาย |
|------------|----------|
| `channel_id` | รหัสช่องข้อความ |

**Query parameters**

| พารามิเตอร์ | บังคับ | คำอธิบาย |
|------------|--------|----------|
| `limit` | ไม่ | จำนวนข้อความสูงสุด (1–10000) ถ้าไม่ส่ง = ดึงให้ได้มากที่สุดตามที่ Discord มี |

**Response 200**

```json
{
  "channel_id": "1474254684171665438",
  "fetched_at": "2026-03-28T07:42:57+00:00",
  "message_count": 571,
  "messages": [ /* อ็อบเจ็กต์ข้อความ */ ]
}
```

---

### 4) ดึงข้อความที่มีคำค้นในข้อความ

| | |
|---|---|
| **Method** | `GET` |
| **Path** | `/api/channels/{channel_id}/messages/filter` |

**Path parameters**

| พารามิเตอร์ | คำอธิบาย |
|------------|----------|
| `channel_id` | รหัสช่องข้อความ |

**Query parameters**

| พารามิเตอร์ | บังคับ | คำอธิบาย |
|------------|--------|----------|
| `keywords` | **ใช่** | หลายคำคั่นด้วย **จุลภาค** `,` — ข้อความใดก็ตามที่ `content` **มี substring ตรงกับคำใดคำหนึ่ง** จะถูกคืน |
| `limit` | ไม่ | จำกัดจำนวนข้อความที่ดึงจาก Discord ก่อนกรอง (1–10000) |

**Response 200**

```json
{
  "channel_id": "1474254684171665438",
  "fetched_at": "2026-03-28T07:42:57+00:00",
  "keywords": ["เปิดออเดอร์สำเร็จ", "ปิดออเดอร์สำเร็จ"],
  "total_messages": 571,
  "filtered_count": 30,
  "messages": [ /* เฉพาะที่ตรงเงื่อนไข */ ]
}
```

**ตัวอย่าง URL (ต้อง encode ถ้าใช้ใน query string)**

```
GET /api/channels/1474254684171665438/messages/filter?keywords=เปิดออเดอร์สำเร็จ,ปิดออเดอร์สำเร็จ
```

---

### 5) สร้าง API Key ใหม่ (สำหรับผู้ดูแล)

| | |
|---|---|
| **Method** | `GET` |
| **Path** | `/api/generate-key` |

**Query parameters**

| พารามิเตอร์ | บังคับ | คำอธิบาย |
|------------|--------|----------|
| `master` | **ใช่** | ต้องตรงกับ `master_key` ใน `config.php` |

**Response 200**

```json
{
  "api_key": "hex_string_64_chars",
  "note": "เก็บ key นี้ไว้ — จะไม่แสดงอีก (ต้องเพิ่มใน config.php เอง)"
}
```

**คำเตือน:** endpoint นี้เปิดเผย key ใน URL (อาจติด log) — ใช้เฉพาะในที่ปลอดภัย แล้วนำค่าไปใส่ใน `api_keys` บนเซิร์ฟ

---

## รูปแบบอ็อบเจ็กต์ `messages[]`

| ฟิลด์ | ประเภท | คำอธิบาย |
|--------|--------|----------|
| `id` | string | รหัสข้อความ Discord |
| `created_at` | string \| null | ISO 8601 จาก Discord |
| `author` | object | `id`, `name`, `display_name`, `bot` |
| `content` | string | เนื้อหาข้อความ |
| `attachments` | array | `{ "url", "filename" }` |
| `embeds` | array | ตาม Discord |
| `reference_message_id` | string \| null | ถ้าเป็นการตอบกลับ |

---

## ข้อผิดพลาด

| HTTP | ตัวอย่าง |
|------|----------|
| 400 | พารามิเตอร์ไม่ถูกต้อง (เช่น ไม่มี `keywords`) |
| 401 | API Key ไม่ถูกต้อง |
| 403 | Master key ผิด / บอทไม่มีสิทธิ์ Discord |
| 404 | ไม่มี endpoint หรือไม่พบ channel/guild บน Discord |
| 429 | Discord rate limit |
| 502 | cURL / เครือข่าย |
| 503 | ยังไม่มี `config.php` บนเซิร์ฟ |

รูปแบบทั่วไป: `{ "error": "ข้อความภาษาไทยหรืออังกฤษ" }`

---

## ตัวอย่างเรียกใช้

### cURL (ไม่มี API Key)

```bash
curl -s "https://YOUR_HOST/api/channels/CHANNEL_ID/messages/filter?keywords=เปิดออเดอร์สำเร็จ,ปิดออเดอร์สำเร็จ"
```

### cURL (มี API Key)

```bash
curl -s -H "X-API-Key: YOUR_KEY" \
  "https://YOUR_HOST/api/channels/CHANNEL_ID/messages?limit=100"
```

### JavaScript (fetch)

```javascript
const base = "https://YOUR_HOST";
const channelId = "1474254684171665438";
const params = new URLSearchParams({
  keywords: ["เปิดออเดอร์สำเร็จ", "ปิดออเดอร์สำเร็จ"].join(","),
});
const res = await fetch(
  `${base}/api/channels/${channelId}/messages/filter?${params}`,
  { headers: { "X-API-Key": "YOUR_KEY" } }
);
const data = await res.json();
```

---

## หมายเหตุด้าน Discord

- บอทต้องอยู่ในเซิร์ฟและมีสิทธิ์อ่านช่อง + **Read Message History**
- มีขีดจำกัด **rate limit** ของ Discord — ดึงช่องใหญ่บ่อยๆ อาจได้ `429`
- ข้อความเก่ามากอาจไม่ครบตามนโยบาย Discord (ขึ้นกับช่องและ API)

---

*เอกสารนี้สอดคล้องกับ `index.php` เวอร์ชัน 1.0.0 (PHP)*
