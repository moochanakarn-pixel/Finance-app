# Finance App

แอปบันทึกรายรับรายจ่ายส่วนตัว พัฒนาด้วย PHP + MySQL

## Requirements

- PHP 8.0+
- MySQL 5.7+ / MariaDB 10.3+
- Web server: Apache / Nginx / IIS

## ติดตั้ง

1. **Clone โปรเจกต์**
   ```bash
   git clone https://github.com/moochanakarn-pixel/Finance-app.git
   cd Finance-app
   ```

2. **ตั้งค่า environment**
   ```bash
   cp .env.example .env
   ```
   แล้วแก้ไขค่าใน `.env` ให้ตรงกับ server ของคุณ

3. **สร้างฐานข้อมูล**
   ```bash
   mysql -u root -p < schema.sql
   ```

4. **สร้าง admin user** เปิดเบราว์เซอร์แล้วไปที่:
   ```
   http://localhost/finance-app/create_admin.php
   ```

## โครงสร้างโปรเจกต์

```
finance-app/
├── assets/          CSS, JS
├── config/          db.php, functions.php
├── partials/        header.php, footer.php
├── Picture/         รูปภาพ
├── .env             ตัวแปร environment (ไม่ถูก commit)
├── .env.example     template สำหรับ .env
├── schema.sql       โครงสร้าง database
└── index.php        Dashboard
```

## หน้าหลัก

| URL | หน้าที่ |
|-----|---------|
| `index.php` | Dashboard |
| `entries.php` | รายการทั้งหมด |
| `add.php` | เพิ่มรายการ (desktop) |
| `add_mobile.php` | เพิ่มรายการ (มือถือ) |
| `categories.php` | จัดการหมวดหมู่ |
| `report.php` | รายงานสรุป |
| `export_excel.php` | Export Excel |
