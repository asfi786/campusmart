<?php
/**
 * Demo data seeder — makes the app look alive on first run.
 * Called by install.php when "include demo data" is ticked.
 * Idempotent: does nothing if listings already exist.
 */

function seed_demo_data(PDO $db): void
{
    $n = (int) $db->query('SELECT COUNT(*) FROM listings')->fetchColumn();
    if ($n > 0) {
        return; // already seeded
    }

    /* ---------------- Categories ---------------- */
    $categories = [
        ['Books & Notes',      'books-notes',      '#3B82F6'],
        ['Electronics',        'electronics',      '#8B5CF6'],
        ['Hostel Essentials',  'hostel-essentials','#F59E0B'],
        ['Furniture',          'furniture',        '#10B981'],
        ['Clothing',           'clothing',         '#EC4899'],
        ['Sports & Fitness',   'sports-fitness',   '#EF4444'],
        ['Stationery',         'stationery',       '#6366F1'],
        ['Others',             'others',           '#64748B'],
    ];
    $catIds = [];
    $st = $db->prepare('INSERT INTO categories (name, slug, color) VALUES (?, ?, ?)');
    foreach ($categories as [$name, $slug, $color]) {
        $st->execute([$name, $slug, $color]);
        $catIds[$slug] = (int) $db->lastInsertId();
    }

    /* ---------------- Users (password for all: password123) ---------------- */
    $students = [
        ['Ali Raza',    'ali@campus.edu.pk'],
        ['Sara Khan',   'sara@campus.edu.pk'],
        ['Bilal Ahmed', 'bilal@campus.edu.pk'],
        ['Fatima Noor', 'fatima@campus.edu.pk'],
        ['Usman Tariq', 'usman@campus.edu.pk'],
    ];
    $userIds = [];
    $st = $db->prepare(
        "INSERT INTO users (name, email, password_hash, is_verified) VALUES (?, ?, ?, 1)"
    );
    foreach ($students as [$name, $email]) {
        $st->execute([$name, $email, password_hash('password123', PASSWORD_DEFAULT)]);
        $userIds[$name] = (int) $db->lastInsertId();
    }

    /* ---------------- Listings ----------------
       [title, category slug, price, condition, campus, location, description, seller] */
    $items = [
        ['Calculus: Early Transcendentals (Stewart, 8th ed.)', 'books-notes', 2500, 'good', 'Main Campus', 'Central Library', 'No missing pages, some highlighting in chapter 3. Perfect for MTH101.', 'Ali Raza'],
        ['Data Structures handwritten notes (complete)', 'books-notes', 300, 'like_new', 'Main Campus', 'CS Dept lobby', 'Neat, colour-coded notes covering the whole CS201 syllabus with solved past papers.', 'Sara Khan'],
        ['HP Pavilion 15 — i5 11th Gen, 8GB RAM, 512GB SSD', 'electronics', 85000, 'good', 'Main Campus', 'Hostel Block C', 'Battery lasts ~5 hours. Fresh thermal paste, runs VS Code and Android Studio smoothly.', 'Ali Raza'],
        ['Casio fx-991ES Plus calculator', 'electronics', 2800, 'like_new', 'City Campus', 'Cafeteria', 'Barely used, selling because I got the 991EX. All buttons work.', 'Fatima Noor'],
        ['Sony WH-1000XM4 noise-cancelling headphones', 'electronics', 32000, 'good', 'Main Campus', 'Hostel Block A', 'Minor wear on earcups, sound and ANC are perfect. Comes with case and cable.', 'Bilal Ahmed'],
        ['Table lamp with USB charging port', 'hostel-essentials', 1200, 'new', 'Main Campus', 'Hostel Block B', 'Bought two by mistake. Warm/cool light modes, still in box.', 'Sara Khan'],
        ['Single mattress, 6 inch (hostel size)', 'hostel-essentials', 4500, 'good', 'Main Campus', 'Hostel Block C', 'Used one semester, always with protector. No stains or sagging.', 'Usman Tariq'],
        ['Electric kettle 1.8L (fast boil)', 'hostel-essentials', 2200, 'like_new', 'City Campus', 'Girls Hostel 2', 'Used twice — hostel warden does not allow it. My loss, your gain.', 'Fatima Noor'],
        ['High-speed table fan', 'hostel-essentials', 1800, 'fair', 'Main Campus', 'Hostel Block A', 'Works fine, a bit noisy on speed 3. Great for load-shedding backup on UPS.', 'Bilal Ahmed'],
        ['Wooden study table with drawers', 'furniture', 6500, 'good', 'Main Campus', 'Hostel Block B', 'Solid sheesham, two drawers. You arrange pickup (ground floor).', 'Ali Raza'],
        ['Office chair with armrest', 'furniture', 5500, 'fair', 'City Campus', 'Hostel Block D', 'Comfortable for long study sessions. One armrest slightly loose.', 'Fatima Noor'],
        ['Winter jacket, size M (barely worn)', 'clothing', 1800, 'good', 'Main Campus', 'Sports Complex', 'Warm padded jacket, zip works perfectly. Selling as it is a bit large for me.', 'Usman Tariq'],
        ['Cricket bat + tennis balls set', 'sports-fitness', 3500, 'good', 'Main Campus', 'Cricket Ground', 'English willow bat, knocked in and ready. 3 tennis balls included.', 'Bilal Ahmed'],
        ['Football, size 5 (match quality)', 'sports-fitness', 1500, 'like_new', 'Main Campus', 'Sports Complex', 'Used in two matches only. Proper grip and shape.', 'Sara Khan'],
        ['Drawing set + mini drafter', 'stationery', 2000, 'new', 'City Campus', 'Architecture Dept', 'Unopened set — dropped the course in week one.', 'Fatima Noor'],
        ['35L travel backpack', 'others', 1600, 'good', 'Main Campus', 'Main Gate', 'Sturdy zips, laptop sleeve fits 15.6". Used for one trip.', 'Usman Tariq'],
        ['Bicycle, 21 gear (fixable)', 'sports-fitness', 18000, 'fair', 'Main Campus', 'Cycle Stand', 'Gears need tuning and one brake pad replacement — priced accordingly.', 'Ali Raza'],
        ['Physics textbook bundle (FSc + BS level)', 'books-notes', 1800, 'fair', 'City Campus', 'Physics Dept', '5 books, some wear but all content readable. Great for entry test prep.', 'Bilal Ahmed'],
        ['Novels bundle — 5 bestsellers', 'books-notes', 1200, 'good', 'Main Campus', 'Central Library', 'Atomic Habits, Alchemist, Kite Runner + 2 more. Take all together.', 'Sara Khan'],
        ['Redmi Note 12 (6/128), with box', 'electronics', 28000, 'good', 'Main Campus', 'Hostel Block C', 'PTA approved, battery health excellent. Small scratch on frame, screen perfect.', 'Usman Tariq'],
    ];

    $listingIds = [];
    $lSt = $db->prepare(
        'INSERT INTO listings (user_id, category_id, title, description, price, `condition`, campus, location, status)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
    );
    $imgSt = $db->prepare(
        'INSERT INTO listing_images (listing_id, filename, sort_order) VALUES (?, ?, ?)'
    );

    // A few items are already sold so reviews and "sold" states are visible.
    $soldIdx = [3, 13, 18]; // calculator, football, novels

    foreach ($items as $i => [$title, $slug, $price, $cond, $campus, $loc, $desc, $seller]) {
        $status = in_array($i, $soldIdx, true) ? 'sold' : 'active';
        $lSt->execute([
            $userIds[$seller], $catIds[$slug], $title, $desc,
            $price, $cond, $campus, $loc, $status,
        ]);
        $lid = (int) $db->lastInsertId();
        $listingIds[] = $lid;

        // 1–3 generated placeholder photos per listing.
        $color = ['books-notes' => '#3B82F6', 'electronics' => '#8B5CF6',
                  'hostel-essentials' => '#F59E0B', 'furniture' => '#10B981',
                  'clothing' => '#EC4899', 'sports-fitness' => '#EF4444',
                  'stationery' => '#6366F1', 'others' => '#64748B'][$slug];
        $nImg = 1 + ($i % 3);
        for ($k = 0; $k < $nImg; $k++) {
            $rel = date('Y') . '/' . date('m') . '/' . bin2hex(random_bytes(8)) . '.jpg';
            make_placeholder($rel, $title, $color);
            $imgSt->execute([$lid, $rel, $k]);
        }
    }

    /* ---------------- Conversations + messages ---------------- */
    $laptopId = $listingIds[2];  // HP Pavilion (Ali's)
    $chairId  = $listingIds[10]; // Office chair (Fatima's)

    $cSt = $db->prepare(
        'INSERT INTO conversations (listing_id, buyer_id, seller_id, last_message_at)
         VALUES (?, ?, ?, ?)'
    );
    $mSt = $db->prepare(
        'INSERT INTO messages (conversation_id, sender_id, body, is_read) VALUES (?, ?, ?, ?)'
    );

    // Sara asks Ali about the laptop.
    $cSt->execute([$laptopId, $userIds['Sara Khan'], $userIds['Ali Raza'], date('Y-m-d H:i:s')]);
    $c1 = (int) $db->lastInsertId();
    $mSt->execute([$c1, $userIds['Sara Khan'], 'Salam! Is the laptop still available?', 1]);
    $mSt->execute([$c1, $userIds['Ali Raza'], 'Walaikum salam! Yes, it is. When would you like to see it?', 1]);
    $mSt->execute([$c1, $userIds['Sara Khan'], 'Can I come by tomorrow evening? Also, is the battery really 5 hours?', 0]);

    // Bilal asks Fatima about the chair.
    $cSt->execute([$chairId, $userIds['Bilal Ahmed'], $userIds['Fatima Noor'], date('Y-m-d H:i:s')]);
    $c2 = (int) $db->lastInsertId();
    $mSt->execute([$c2, $userIds['Bilal Ahmed'], 'Hi, is the chair comfortable for long coding sessions?', 1]);
    $mSt->execute([$c2, $userIds['Fatima Noor'], 'Hi! Yes, I used it all last semester. The armrest is slightly loose but it is an easy fix.', 0]);

    /* ---------------- Wishlists ---------------- */
    // (duplicates are silently ignored)
    foreach ([[$userIds['Sara Khan'], $laptopId], [$userIds['Bilal Ahmed'], $listingIds[16]],
              [$userIds['Fatima Noor'], $listingIds[5]], [$userIds['Ali Raza'], $listingIds[19]]] as [$u, $l]) {
        try {
            $db->prepare('INSERT INTO wishlists (user_id, listing_id) VALUES (?, ?)')
               ->execute([$u, $l]);
        } catch (PDOException $e) { /* already there */
        }
    }

    /* ---------------- Reviews (for the sold items) ---------------- */
    $rSt = $db->prepare(
        'INSERT INTO reviews (listing_id, reviewer_id, seller_id, rating, comment)
         VALUES (?, ?, ?, ?, ?)'
    );
    // Calculator: Fatima sold to Bilal.
    $rSt->execute([$listingIds[3], $userIds['Bilal Ahmed'], $userIds['Fatima Noor'],
        5, 'Exactly as described, met at the cafeteria. Smooth deal!']);
    // Football: Sara sold to Usman.
    $rSt->execute([$listingIds[13], $userIds['Usman Tariq'], $userIds['Sara Khan'],
        5, 'Great condition, quick handover at the sports complex.']);
    // Novels: Sara sold to Ali.
    $rSt->execute([$listingIds[18], $userIds['Ali Raza'], $userIds['Sara Khan'],
        4, 'Books were fine, one had a folded cover. Fair price though.']);

    /* ---------------- Reports (pending, for the admin demo) ---------------- */
    $repSt = $db->prepare(
        "INSERT INTO reports (reporter_id, listing_id, reported_user_id, reason, details, status)
         VALUES (?, ?, ?, ?, ?, 'pending')"
    );
    $repSt->execute([$userIds['Bilal Ahmed'], $listingIds[16], null,
        'Wrong category / misleading', 'The bicycle photo looks different from the description.']);
    $repSt->execute([$userIds['Sara Khan'], null, $userIds['Usman Tariq'],
        'Spam or scam', 'This user keeps posting the same mattress in every category.']);

    /* ---------------- Lost & Found ---------------- */
    $lfSt = $db->prepare(
        "INSERT INTO lost_found (user_id, type, title, description, location, status)
         VALUES (?, ?, ?, ?, ?, ?)"
    );
    $lfSt->execute([$userIds['Ali Raza'], 'lost', 'Black leather wallet near library',
        'Lost near the central library entrance yesterday evening. Has my ID card inside — please keep one detail secret to verify.',
        'Central Library', 'open']);
    $lfSt->execute([$userIds['Fatima Noor'], 'found', 'Casio calculator found in LH-3',
        'Found after the physics lecture. Tell me the model number to claim it.', 'Lecture Hall 3', 'open']);
    $lfSt->execute([$userIds['Bilal Ahmed'], 'lost', 'Silver bracelet, hostel gym',
        'Thin silver bracelet, lost near the gym lockers.', 'Hostel Gym', 'open']);
    $lfSt->execute([$userIds['Sara Khan'], 'found', 'USB drive with thesis files',
        'Blue 32GB drive found in the computer lab. Describe the folder names to claim.', 'Computer Lab 2', 'resolved']);

    /* ---------------- Notifications ---------------- */
    $nSt = $db->prepare(
        'INSERT INTO notifications (user_id, type, title, body, link) VALUES (?, ?, ?, ?, ?)'
    );
    $nSt->execute([$userIds['Ali Raza'], 'message', 'New message from Sara Khan',
        'Can I come by tomorrow evening?…', 'conversation.php?id=' . $c1]);
    $nSt->execute([$userIds['Fatima Noor'], 'message', 'New message from Bilal Ahmed',
        'Hi, is the chair comfortable…?', 'conversation.php?id=' . $c2]);
    $nSt->execute([$userIds['Sara Khan'], 'review', 'Usman Tariq rated you 5★',
        'For: Football, size 5', 'profile.php?id=' . $userIds['Sara Khan']]);

    /* ---------------- Allowed domains + settings ---------------- */
    foreach (['campus.edu.pk', 'student.campus.edu.pk'] as $d) {
        try {
            $db->prepare('INSERT INTO allowed_domains (domain) VALUES (?)')->execute([$d]);
        } catch (PDOException $e) { /* exists */
        }
    }
    $defaults = [
        'installed'            => '1',
        'email_verification'   => '0',
        'allowed_domains_only' => '0',
        'items_per_page'       => '12',
    ];
    foreach ($defaults as $k => $v) {
        $db->prepare('DELETE FROM settings WHERE setting_key = ?')->execute([$k]);
        $db->prepare('INSERT INTO settings (setting_key, setting_value) VALUES (?, ?)')->execute([$k, $v]);
    }
}
