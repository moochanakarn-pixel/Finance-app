<?php
// One-time import script — DELETE THIS FILE after running
header('Content-Type: text/html; charset=UTF-8');
include 'auth.php';
include 'config/db.php';
$userId = (int)$_SESSION['user_id'];

$notes = [
    // ── ข้อมูลส่วนตัว ──────────────────────────────────
    ['2025-05-27','diary','💖 ข้อมูลโฟล์ค — น.ส.ภูรินัฐ กระจ่างศรี',
     "ชื่อ: น.ส.ภูรินัฐ กระจ่างศรี\nเกิด: 9 พฤษภาคม 2544\nบ้าน: 155/248 หมู่บ้านสัมมากร รังสิต คลอง 7\nน้องชาย: ฟอร์ด (อายุห่างกัน 3 ปี)\nเลข 9 เป็นเลขสำคัญของครอบครัว ✨"],

    // ── จุดเริ่มต้น ─────────────────────────────────────
    ['2025-05-27','diary','🌱 วันแรกที่คุยกัน',
     "27 พฤษภาคม 2568\nเริ่มต้นบทสนทนาผ่าน IG\nจากคนแปลกหน้า → กลายเป็นคนสำคัญของกันและกัน"],

    // ── เดต ─────────────────────────────────────────────
    ['2025-06-02','memory','🍣 เจอกันครั้งแรก @ MBK — เดตแรก',
     "2 มิถุนายน 2568\n- กินบุฟเฟต์ Shinkanzen\n- กิน After You\n- ดูหนัง Final Destination\n\nวันแรกที่ได้ใช้เวลาด้วยกันจริงๆ ทั้งตื่นเต้น เขิน และอบอุ่นแบบที่จำได้ไม่ลืม"],

    ['2025-06-08','memory','🩵 เดตครั้งที่ 2 @ เซ็นทรัลลาดพร้าว',
     "8 มิถุนายน 2568\n- กินเทนยะ\n- เปิดกล่อง Twinkle ด้วยกัน\n- ให้พวงกุญแจ Stitch\n- ดูหนัง เบลลาลิน่า\n- เดินสวนจตุจักร\n- ปั่นเรือเป็ด\n- ปิดท้ายด้วย Eat am are\n\nเริ่มมี 'ของแทนใจ' และความรู้สึกที่ชัดขึ้นเรื่อยๆ 🩵"],

    ['2025-06-13','memory','😊 เดตกินด้ง + ดูมังกรเขี้ยวกุด',
     "13 มิถุนายน 2568\n- กินด้ง\n- ดู มังกรเขี้ยวกุด\n\nช่วงเวลาธรรมดาแต่กลายเป็นความสุขพิเศษเพราะมีอีกคนอยู่ข้างๆ"],

    ['2025-06-14','diary','💗 วันที่เราเป็นแฟนกัน ✨ One-Day Trip',
     "14 มิถุนายน 2568 — วันพิเศษที่สุด\n\nเส้นทาง: MRT หัวลำโพง → ถนนทรงวาด → ตลาดน้อย → River City → Asiatique\n\n- เดินเล่นถนนทรงวาด\n- กินข้าวหมูแดงหมูกรอบ\n- แวะพิพิธภัณฑ์ตลาดน้อย\n- นั่งคาเฟ่เฟงหวง\n- ไปงาน PARADOXXXIBITION ที่ River City\n- เดินเล่น Asiatique\n\n🎡 ขึ้นชิงช้าสวรรค์\n💗 ขอเป็นแฟน\n💋 First Kiss\n\nของขวัญ: ตุ๊กตา Stitch สีชมพู 'Angel' 🩷\nวันเดียวที่กลายเป็น 'จุดเริ่มต้นของคำว่าเรา'"],

    ['2025-06-19','memory','🍜 เซ็นทรัลพระราม 9 — KIANI + 28 Months Later',
     "19 มิถุนายน 2568\n- กิน KIANI\n- ดู 28 Months Later"],

    ['2025-06-22','memory','🚶 Day Trip เยาวราช',
     "22 มิถุนายน 2568\n- ก๋วยเตี๋ยวตรอกโรงหมู\n- คาเฟ่หมา FUN Celebistro\n- ไอติมผักชี\n- เมก้าสะพานเหล็ก\n- ตลาดสำเพ็ง\n- เยาวราช + ซื้อของฝาก\n\nวันธรรมดาที่เต็มไปด้วยเรื่องเล็กๆ น่าจดจำ"],

    ['2025-06-25','memory','🎬 ตลาดนัดหน้าเมเจอร์รัชโยธิน + ดู Stitch',
     "25 มิถุนายน 2568\n- เดินตลาดนัดหน้าเมเจอร์รัชโยธิน\n- ดูหนัง Stitch"],

    ['2025-06-28','memory','🏡 เดตแถวบ้าน — ฟิวเจอร์พาร์ค รังสิต',
     "28 มิถุนายน 2568\n- ฟิวเจอร์พาร์ค รังสิต\n- Neo Ramen\n- ฤดูกาลคาเฟ่\n- Shinkanzen Villa Market\n- ไปส่งโฟล์คที่บ้าน\n\nเริ่มคุ้นเคยกับ 'โลกของกันและกัน'"],

    ['2025-07-04','memory','🦕 บุฟเฟต์กองจู + Jurassic World',
     "4 กรกฎาคม 2568\n- บุฟเฟต์กองจู\n- ดู Jurassic World"],

    ['2025-07-06','memory','🥟 ซีคอนบางแค — ติ่มซำ + วัดปากน้ำ + F1',
     "6 กรกฎาคม 2568\n- ซีคอนบางแค: Mandarin ติ่มซำ\n- วัดปากน้ำ ภาษีเจริญ\n- บ้านศิลปิน\n- Siam Paragon สมัคร M Pass\n- ดู F1"],

    ['2025-07-08','memory','📷 เลือกกล้อง Nikon Z6 III + Yayoi',
     "8 กรกฎาคม 2568\n- เดินเลือกกล้อง Nikon Z6 III ด้วยกัน\n- กิน Yayoi"],

    ['2025-07-12','memory','🦸 Superman + เดินตลาดพลู',
     "12 กรกฎาคม 2568\n- ดู Superman\n- เดินตลาดพลู"],

    ['2025-07-15','diary','🤍 ครบรอบ 1 เดือน',
     "15 กรกฎาคม 2568 — ครบรอบ 1 เดือน 💕\n- กินคัตสึยะ\n- ได้ Ice Bear และ Post Card\n\nของเล็กๆ แต่เต็มไปด้วยความหมาย 🤍"],

    ['2025-07-18','memory','👻 ดูหนังผี Noise',
     "18 กรกฎาคม 2568\n- ดูหนังผี Noise"],

    ['2025-07-20','memory','🏸 ตีแบตด้วยกัน',
     "20 กรกฎาคม 2568\n- ตีแบตด้วยกัน"],

    ['2025-07-27','travel','✈️ ทริปเชียงใหม่ครั้งแรก (27–30 ก.ค. 2568)',
     "27–30 กรกฎาคม 2568 — หนึ่งในความทรงจำที่พิเศษที่สุด\n\nวันที่ 27:\n- บินไปเชียงใหม่\n- รับรถเช่า\n- กินข้าวซอยแม่นาย\n- FAR AWAY\n- ไร่ชาลุงเดช\n- พัก 'จูเลี๊ยะ แม่แตง'\n\nวันที่ 28:\n- น้ำตกบัวตอง\n- ส้มตำแม่โจ้\n- พัก Bee Forest\n- เย็นที่แม่กำปอง\n\nวันที่ 29:\n- เช้าแม่กำปอง + กิ่วฝิ่น\n- ดอยอินทนนท์\n- ชาบูช้างม่อน\n- พัก Maplewood\n\nวันที่ 30:\n- ปาท่องโก๋ไดโนเสาร์\n- ตลาดวโรรส\n- เซ็นทรัลเชียงใหม่\n\nทั้งเหนื่อย ทั้งสนุก และได้เห็นกันในอีกหลายมุม ☁️"],

    ['2025-08-03','memory','🦸‍♀️ Fantastic Four + Akiyoshi',
     "3 สิงหาคม 2568\n- ดู Fantastic Four\n- กิน Akiyoshi"],

    ['2025-08-11','memory','☕ Toechon บางใหญ่ + Cafe Ganicco',
     "11 สิงหาคม 2568\n- Toechon บางใหญ่\n- Cafe Ganicco"],

    ['2025-08-13','memory','⚔️ Kimetsu no Yaiba + ราเมง',
     "13 สิงหาคม 2568\n- ดู Kimetsu no Yaiba\n- กินราเมง"],

    ['2025-08-14','memory','🥩 หมูกระทะทวีโชค',
     "14 สิงหาคม 2568\n- หมูกระทะทวีโชค"],

    ['2025-08-16','memory','🎪 งาน Fu Me Fest',
     "16 สิงหาคม 2568\n- ไปงาน Fu Me Fest"],

    ['2025-08-19','memory','🍜 Benzaiten Ramen + มุมปัง โชคชัย 4',
     "19 สิงหาคม 2568\n- Benzaiten Ramen\n- มุมปัง โชคชัย 4"],

    ['2025-08-23','memory','🍜 Colorist 4 + บะหมี่ต้มยำกากหมู',
     "23 สิงหาคม 2568\n- Colorist 4\n- บะหมี่ต้มยำกากหมู"],

    ['2025-10-11','memory','🎵 คอนเสิร์ต Cocktail — สระบุรี',
     "11 ตุลาคม 2568\n- ไปสระบุรี\n- ดูคอนเสิร์ต Cocktail\n- พัก Hop Inn"],

    ['2025-10-21','travel','🚂 เชียงใหม่ครั้งที่ 2 ทริปรถไฟ (21–25 ต.ค. 2568)',
     "21–25 ตุลาคม 2568 — ทริปรถไฟกรุงเทพ–เชียงใหม่\n\n- ข้าวโซอิ\n- ออบหลวง\n- ป่าบงเปียง\n- ผาดอกเสี้ยว\n- อินทนนท์\n- Fernpresso\n- POR Hotel\n\nจาก 'คนคุย' กลายเป็น 'คู่เดินทาง' อย่างเต็มตัว 🤎"],

    ['2025-11-29','memory','🍜 Kutsu Midori',
     "29 พฤศจิกายน 2568\n- Kutsu Midori"],

    ['2026-02-14','diary','💘 Valentine\'s Day @ MoMo Paradise',
     "14 กุมภาพันธ์ 2569\nValentine's Day @ MoMo Paradise 💘"],

    ['2026-02-21','memory','🎵 คอนเสิร์ต G27',
     "21 กุมภาพันธ์ 2569\n- คอนเสิร์ต G27 🎵"],

    ['2026-03-07','memory','🎸 คอนเสิร์ต Three Man Down',
     "7 มีนาคม 2569\n- คอนเสิร์ต Three Man Down 🎸"],
];

$inserted = 0;
$skipped  = 0;

foreach ($notes as [$date, $cat, $title, $content]) {
    // Skip if same title+date already exists
    $stCheck = mysqli_prepare($conn, "SELECT id FROM notes WHERE user_id=? AND title=? AND note_date=? LIMIT 1");
    mysqli_stmt_bind_param($stCheck, 'iss', $userId, $title, $date);
    mysqli_stmt_execute($stCheck);
    mysqli_stmt_store_result($stCheck);
    if (mysqli_stmt_num_rows($stCheck) > 0) { $skipped++; continue; }

    $st = mysqli_prepare($conn, "INSERT INTO notes (user_id,title,content,category,note_date) VALUES (?,?,?,?,?)");
    mysqli_stmt_bind_param($st, 'issss', $userId, $title, $content, $cat, $date);
    mysqli_stmt_execute($st);
    $inserted++;
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Import Timeline</title>
<style>
body{font-family:"Segoe UI",sans-serif;background:#f0f4ff;display:flex;align-items:center;justify-content:center;min-height:100vh;margin:0}
.box{background:#fff;border-radius:20px;padding:2rem;max-width:480px;width:90%;box-shadow:0 8px 32px rgba(99,102,241,.15);text-align:center}
.icon{font-size:3rem;margin-bottom:.75rem}
h2{font-size:1.3rem;font-weight:800;margin:0 0 .5rem;color:#1e1b4b}
p{color:#64748b;margin:.25rem 0}
.count{font-size:2rem;font-weight:800;color:#6366f1}
.btn{display:inline-block;margin-top:1.5rem;padding:.75rem 1.5rem;background:linear-gradient(135deg,#6366f1,#4f46e5);color:#fff;border-radius:12px;text-decoration:none;font-weight:700}
.warn{background:#fef3c7;border:1px solid #fde68a;border-radius:12px;padding:.75rem 1rem;margin-top:1rem;font-size:.85rem;color:#92400e;font-weight:600}
</style>
</head>
<body>
<div class="box">
    <div class="icon">💖</div>
    <h2>Timeline โฟล์ค & ภูรินัฐ</h2>
    <p>นำเข้าสำเร็จแล้ว</p>
    <div class="count"><?= $inserted ?> โน็ต</div>
    <?php if ($skipped > 0): ?>
        <p style="color:#94a3b8;font-size:.9rem">ข้าม <?= $skipped ?> รายการที่มีอยู่แล้ว</p>
    <?php endif; ?>
    <div class="warn">⚠️ กรุณาลบไฟล์ <strong>import_timeline.php</strong> ออกหลังใช้งาน</div>
    <a href="notes.php?cat=memory" class="btn">ดูโน็ตทั้งหมด →</a>
</div>
</body>
</html>
