# คู่มือใช้งาน API (ฉบับสั้น)

ดึงข้อความจากช่อง **Discord** ผ่าน HTTP ได้เป็น JSON

---

## Base URL

```
https://phpstack-1050210-6313557.cloudwaysapps.com
```

*(ถ้ามีโดเมนใหม่ ให้แทนที่บรรทัดนี้)*

---

## ถ้าเจ้าของระบบเปิด API Key

ทุก request ใส่ header:

```
X-API-Key: <คีย์ที่ได้จากเจ้าของ>
```

ถ้าไม่ได้เปิด key ไม่ต้องใส่ header นี้

---

## 1) เช็กว่า API ยังทำงาน

```
GET {BASE}/
```

ตัวอย่าง: เปิดในเบราว์เซอร์  
`https://phpstack-1050210-6313557.cloudwaysapps.com/`

---

## 2) ดูรายชื่อช่องแชทในเซิร์ฟ

```
GET {BASE}/api/guild/{GUILD_ID}/channels
```

| ส่วน | คืออะไร |
|------|---------|
| `GUILD_ID` | รหัสเซิร์ฟเวอร์ Discord (ตัวเลขยาว) |

ตัวอย่าง:

```
https://phpstack-1050210-6313557.cloudwaysapps.com/api/guild/1217662250458943609/channels
```

---

## 3) ดึงข้อความในช่อง (ทั้งหมดหรือจำกัดจำนวน)

```
GET {BASE}/api/channels/{CHANNEL_ID}/messages
```

| พารามิเตอร์ (ต่อท้าย URL) | คืออะไร |
|---------------------------|---------|
| `limit` | (ไม่บังคับ) จำนวนสูงสุด เช่น `limit=100` ถ้าไม่ใส่ = ดึงให้ได้มากที่สุด |

ตัวอย่าง:

```
https://phpstack-1050210-6313557.cloudwaysapps.com/api/channels/1474254684171665438/messages?limit=50
```

---

## 4) ดึงเฉพาะข้อความที่มีคำที่กำหนด

```
GET {BASE}/api/channels/{CHANNEL_ID}/messages/filter?keywords=คำที่1,คำที่2
```

| พารามิเตอร์ | คืออะไร |
|-------------|---------|
| `keywords` | **ต้องมี** — หลายคำคั่นด้วย **จุลภาค** `,` ข้อความใดที่มีคำใดคำหนึ่งจะถูกส่งกลับ |
| `limit` | (ไม่บังคับ) จำกัดจำนวนข้อความที่ไปดึงจาก Discord ก่อนกรอง |

ตัวอย่าง (เปิดออเดอร์ / ปิดออเดอร์):

```
https://phpstack-1050210-6313557.cloudwaysapps.com/api/channels/1474254684171665438/messages/filter?keywords=เปิดออเดอร์สำเร็จ,ปิดออเดอร์สำเร็จ
```

---

## สิ่งที่ได้กลับมา (โครงคร่าว)

- ทุกคำตอบเป็น **JSON**
- ข้อความอยู่ใน `messages` เป็น array
- แต่ละข้อความมีอย่างน้อย: `id`, `created_at`, `author`, `content`

ถ้า error จะมีฟิลด์ `error` พร้อมข้อความอธิบาย

---

## ตัวอย่างเรียกด้วยโค้ด (มี API Key)

```bash
curl -H "X-API-Key: YOUR_KEY" \
  "https://phpstack-1050210-6313557.cloudwaysapps.com/api/channels/1474254684171665438/messages?limit=10"
```

---

*เอกสารฉบับเต็ม (รายละเอียด technical): ดูไฟล์ `API.md` ใน repo เดียวกัน*
