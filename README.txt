Finance App (PHP + MySQL)

ไฟล์ชุดนี้เป็นเวอร์ชันปรับปรุงแล้ว โดยแก้จุดสำคัญเรื่อง login, import, create admin, update entry และ schema ฐานข้อมูล

วิธีติดตั้ง
1) import schema.sql
2) เปิด create_admin.php เพื่อสร้างผู้ใช้แรก
3) login ผ่าน login.php
4) หลังสร้าง admin สำเร็จ แนะนำให้ลบหรือเปลี่ยนชื่อ create_admin.php

จุดที่แก้แล้ว
- import.php เช็ก login และผูกข้อมูลตาม user_id
- update_entry.php เช็กสิทธิ์และใช้ prepared statement
- login.php เพิ่ม session_regenerate_id()
- create_admin.php ล็อกไม่ให้สร้าง admin ซ้ำง่าย ๆ
- create_user.php ใช้ prepared statement
- add.php เรียก header/footer ครบ
- schema.sql จัดใหม่เป็นไฟล์ตั้งต้นสะอาด และเพิ่ม composite index

หมายเหตุ
- ถ้าใช้งานจริง ควรปิดการแสดง error บนหน้าเว็บ
- ถ้าจะใช้ import CSV ต้องมีหัวตาราง: date, category, amount, note
