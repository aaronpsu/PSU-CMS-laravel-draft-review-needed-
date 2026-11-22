<?php
require __DIR__.'/bootstrap.php';
$types=['news'=>'News','announcements'=>'Announcements','events'=>'Events','colleges'=>'Colleges','departments'=>'Departments','programs'=>'Academic Programs','directory'=>'Faculty & Staff Directory','documents'=>'Documents','media'=>'Media Library','pages'=>'Pages'];

function public_content_rows(PDO $pdo, string $type = '', string $query = ''): array
{
    $sql = "
        SELECT 'news' AS type, n.news_id AS id, n.title, COALESCE(n.excerpt, '') AS summary,
            COALESCE(n.content, '') AS body, n.published_at AS display_date, n.created_at,
            m.file_path AS image_path, NULL AS link_url
        FROM news n
        LEFT JOIN media m ON m.media_id = n.cover_media_id
        WHERE n.status = 'published'
        UNION ALL
        SELECT 'announcements', a.announcement_id, a.title, COALESCE(a.content, ''),
            COALESCE(a.content, ''), a.published_at, a.created_at, NULL, NULL
        FROM announcements a
        WHERE a.status = 'published' AND (a.expires_at IS NULL OR a.expires_at >= NOW())
        UNION ALL
        SELECT 'events', e.event_id, e.title, COALESCE(e.description, ''),
            COALESCE(e.description, ''), e.start_at, e.created_at, m.file_path, NULL
        FROM events e
        LEFT JOIN media m ON m.media_id = e.cover_media_id
        WHERE e.status = 'published'
        UNION ALL
        SELECT 'pages', p.page_id, p.title, '', COALESCE(p.content, ''),
            p.published_at, p.created_at, NULL, NULL
        FROM pages p
        WHERE p.status = 'published'
        UNION ALL
        SELECT 'colleges', c.college_id, c.name, '',
            '', c.created_at, c.created_at, m.file_path, NULL
        FROM colleges c
        LEFT JOIN media m ON m.media_id = c.logo_media_id
        WHERE c.status = 'active'
        UNION ALL
        SELECT 'departments', d.department_id, d.name, COALESCE(d.description, ''),
            COALESCE(d.description, ''), d.created_at, d.created_at, NULL, NULL
        FROM departments d
        WHERE d.status = 'active'
        UNION ALL
        SELECT 'programs', p.program_id, p.name, COALESCE(p.description, ''),
            COALESCE(p.description, ''), p.created_at, p.created_at, NULL, NULL
        FROM programs p
        WHERE p.status = 'active'
        UNION ALL
        SELECT 'directory', f.faculty_id,
            TRIM(CONCAT_WS(' ', f.first_name, NULLIF(f.middle_name, ''), f.last_name)),
            COALESCE(f.position, ''), COALESCE(f.biography, ''), f.created_at, f.created_at,
            m.file_path, NULL
        FROM faculty_staff f
        LEFT JOIN media m ON m.media_id = f.photo_media_id
        WHERE f.status = 'active'
        UNION ALL
        SELECT 'documents', d.document_id, d.title, '',
            '', d.created_at, d.created_at, NULL, NULL
        FROM documents d
        WHERE d.access_level = 'public'
    ";

    $sql = 'SELECT * FROM (' . $sql . ') AS public_content WHERE 1 = 1';
    $params = [];
    if ($type === 'updates') {
        $sql .= " AND type IN ('news', 'announcements', 'events')";
    } elseif ($type !== '') {
        $sql .= ' AND type = ?';
        $params[] = $type;
    }

    if ($query !== '') {
        $sql .= " AND (title LIKE ? ESCAPE '!' OR summary LIKE ? ESCAPE '!' OR body LIKE ? ESCAPE '!')";
        $like = '%' . str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $query) . '%';
        array_push($params, $like, $like, $like);
    }

    $sql .= ' ORDER BY COALESCE(display_date, created_at) DESC LIMIT 100';
    $statement = $pdo->prepare($sql);
    $statement->execute($params);
    return $statement->fetchAll();
}

function public_content_item(PDO $pdo, string $type, int $id): ?array
{
    $queries = [
        'news' => "SELECT n.news_id AS id, n.title, n.excerpt AS summary, n.content AS body,
            n.published_at AS display_date, n.created_at, m.file_path AS image_path
            FROM news n LEFT JOIN media m ON m.media_id = n.cover_media_id
            WHERE n.news_id = ? AND n.status = 'published'",
        'announcements' => "SELECT a.announcement_id AS id, a.title, '' AS summary, a.content AS body,
            a.published_at AS display_date, a.created_at, NULL AS image_path
            FROM announcements a
            WHERE a.announcement_id = ? AND a.status = 'published'
                AND (a.expires_at IS NULL OR a.expires_at >= NOW())",
        'events' => "SELECT e.event_id AS id, e.title, '' AS summary, e.description AS body,
            e.start_at AS display_date, e.created_at, m.file_path AS image_path
            FROM events e LEFT JOIN media m ON m.media_id = e.cover_media_id
            WHERE e.event_id = ? AND e.status = 'published'",
        'pages' => "SELECT page_id AS id, title, '' AS summary, content AS body,
            published_at AS display_date, created_at, NULL AS image_path
            FROM pages WHERE page_id = ? AND status = 'published'",
    ];

    if (!isset($queries[$type])) {
        return null;
    }

    $statement = $pdo->prepare($queries[$type]);
    $statement->execute([$id]);
    $item = $statement->fetch();
    return $item ?: null;
}

function public_asset_url(?string $path, string $folder): ?string
{
    if ($path === null || $path === '') {
        return null;
    }
    $relative = ltrim($path, '/');
    if (!in_array($folder, ['media', 'docs'], true)
        || !preg_match('#^uploads/' . preg_quote($folder, '#') . '/[A-Za-z0-9._/-]+$#D', $relative)
        || str_contains($relative, '..')) {
        return null;
    }
    $fullPath = __DIR__ . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relative);
    return is_file($fullPath) ? $relative : null;
}

function public_homepage_settings(PDO $pdo): array
{
    $query = $pdo->query('SELECT setting_key, setting_value FROM homepage_settings');
    return $query->fetchAll(PDO::FETCH_KEY_PAIR);
}

function public_homepage_banner_image(PDO $pdo, int $id): void
{
    $query = $pdo->prepare('SELECT image_path FROM homepage_banners WHERE banner_id = ? AND is_active = 1');
    $query->execute([$id]);
    $path = $query->fetchColumn();
    $storageRoot = realpath(__DIR__ . '/laravel-admin-api/storage/app/public/homepage/banners');
    if (!is_string($path) || !preg_match('#^homepage/banners/[a-f0-9-]+\.(?:jpg|png|gif|webp)$#iD', $path)
        || $storageRoot === false) {
        http_response_code(404);
        exit;
    }

    $filePath = realpath($storageRoot . DIRECTORY_SEPARATOR . basename($path));
    if ($filePath === false || !str_starts_with($filePath, $storageRoot . DIRECTORY_SEPARATOR) || !is_readable($filePath)) {
        http_response_code(404);
        exit;
    }
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($filePath);
    if (!in_array($mime, ['image/jpeg', 'image/png', 'image/gif', 'image/webp'], true)) {
        http_response_code(404);
        exit;
    }

    header('Content-Type: ' . $mime);
    header('Cache-Control: public, max-age=300');
    header('X-Content-Type-Options: nosniff');
    readfile($filePath);
    exit;
}

$bannerId = filter_var($_GET['banner'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
if (array_key_exists('banner', $_GET)) {
    if ($bannerId === false) {
        http_response_code(404);
        exit;
    }
    public_homepage_banner_image($pdo, (int) $bannerId);
}

$feedbackError='';
if($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['feedback'])) {
    check_csrf();
    $name=trim((string)($_POST['name']??''));
    $email=trim((string)($_POST['email']??''));
    $subject=trim((string)($_POST['subject']??''));
    $message=trim((string)($_POST['message']??''));
    $errors=[];
    if($name===''||mb_strlen($name)>120) $errors[]='Enter a name (120 characters max).';
    if(!filter_var($email,FILTER_VALIDATE_EMAIL)||mb_strlen($email)>150) $errors[]='Enter a valid email address.';
    if($subject===''||mb_strlen($subject)>220) $errors[]='Enter a subject (220 characters max).';
    if($message===''||mb_strlen($message)>5000) $errors[]='Enter a message (5000 characters max).';
    if($errors) {
        http_response_code(422);
        $feedbackError=implode(' ',$errors);
    } else {
        // Persistent, server-enforced rate limit keyed by requester IP. Session
        // state cannot reset this; REMOTE_ADDR is only overridden when the
        // deployment really does sit behind a trusted proxy (see README).
        $feedbackLimit=max(1,(int)($config['feedback_limit']??5));
        $ip=client_ip();
        $allowed=true;
        try {
            $q=$pdo->prepare('SELECT COUNT(*) FROM feedback_attempts WHERE ip = ? AND attempted_at >= (NOW() - INTERVAL 15 MINUTE)');
            $q->execute([$ip]);
            if((int)$q->fetchColumn()>=$feedbackLimit) {
                $allowed=false;
                http_response_code(429);
                $feedbackError='Too many feedback submissions. Please wait and try again.';
                security_log('feedback_throttled',['ip'=>$ip]);
            }
        } catch(Throwable $e) {
            error_log('Feedback throttle unavailable: '.$e->getMessage());
            ensure_feedback_attempts_table($pdo);
            // Limiter storage unavailable: fail open and log loudly.
        }
        if($allowed) {
            try {
                $q=$pdo->prepare("INSERT INTO contact_submissions(full_name,email,subject,message,status,received_at) VALUES(?,?,?,?, 'unread', NOW())");
                $q->execute([$name,$email,$subject,$message]);
                try {
                    $a=$pdo->prepare('INSERT INTO feedback_attempts(ip) VALUES (?)');
                    $a->execute([$ip]);
                } catch(Throwable $e) {
                    error_log('Could not record feedback attempt: '.$e->getMessage());
                    ensure_feedback_attempts_table($pdo);
                }
                header('Location: index.php?contact=1&sent=1');
                exit;
            } catch(PDOException $e) {
                error_log('Feedback insert failed: '.$e->getMessage());
                http_response_code(500);
                $feedbackError='Could not send feedback. Please try again.';
            }
        }
    }
    $_GET['contact']='1';
}
$type=(string)($_GET['type']??''); $query=trim((string)($_GET['q']??''));
if(mb_strlen($query)>100) $query=mb_substr($query,0,100);
$itemId=filter_var($_GET['item']??null,FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);
site_header($types[$type]??'Home');
if(isset($_GET['contact'])) {
    echo '<section class="hero"><span class="eyebrow">GET IN TOUCH</span><h1>Contact & feedback</h1><p>Send a question or comment to the campus team.</p></section>';
    if($feedbackError!=='') echo '<div class="notice error">'.h($feedbackError).'</div>';
    if(isset($_GET['sent'])) echo '<div class="notice">Thank you. Your feedback has been received.</div>';
    echo '<form class="panel form" method="post"><input type="hidden" name="csrf" value="'.h(csrf()).'"><input type="hidden" name="feedback" value="1"><label>Name<input name="name" required maxlength="120"></label><label>Email<input name="email" type="email" required maxlength="190"></label><label>Subject<input name="subject" required maxlength="220"></label><label>Message<textarea name="message" rows="6" required maxlength="5000"></textarea></label><button>Send feedback</button></form>';
    site_footer();
    exit;
}
if($itemId!==false) {
    $item=public_content_item($pdo,$type,(int)$itemId);
    if(!$item){http_response_code(404);echo '<h1>Content not found</h1>';site_footer();exit;}
    echo '<article class="article"><span class="eyebrow">'.h($types[$type]??'Content').'</span><h1>'.h($item['title']).'</h1>';
    $date=$item['display_date']?:$item['created_at'];
    if($date) echo '<div class="meta">'.h(date('M j, Y',strtotime($date))).'</div>';
    $itemImage=public_asset_url($item['image_path']??null,'media');
    if($itemImage) echo '<img class="cover" src="'.h($itemImage).'" alt="">';
    if(!empty($item['summary'])) echo '<p>'.h($item['summary']).'</p>';
    echo '<div class="prose">'.nl2br(h($item['body']??'')).'</div>';
    echo '</article>';
    site_footer();
    exit;
}
if($query!=='') {
    $rows=public_content_rows($pdo,'',$query);
    echo '<section class="hero"><span class="eyebrow">SEARCH</span><h1>Results for “'.h($query).'”</h1></section>';
}
elseif(isset($types[$type])) {
    $rows=public_content_rows($pdo,$type);
    echo '<section class="hero"><span class="eyebrow">CAMPUS CONTENT</span><h1>'.h($types[$type]).'</h1><p>Explore the latest campus information.</p></section>';
}
else {
    $q=$pdo->query("SELECT title, content FROM pages WHERE slug='homepage' AND status='published' LIMIT 1");
    $home=$q->fetch();
    $siteSettings=public_site_settings($pdo);
    $homepageSettings=public_homepage_settings($pdo);
    $university=trim((string)($siteSettings['university_name']??''))?:'Pangasinan State University';
    $homeTitle=trim((string)($homepageSettings['welcome_heading']??''))?:trim((string)($home['title']??''))?:'Welcome to '.$university;
    $homeDescription=trim(preg_replace('/\s+/', ' ', strip_tags((string)($homepageSettings['homepage_description']??'')))??'');
    if($homeDescription==='') $homeDescription=trim(preg_replace('/\s+/', ' ', strip_tags((string)($home['content']??'')))??'');
    if($homeDescription==='') $homeDescription=trim((string)($siteSettings['website_description']??''))?:'Campus news, announcements, events, academic programs and community information in one place.';
    $callToAction=trim((string)($homepageSettings['call_to_action_text']??''));
    $buttonLabel=trim((string)($homepageSettings['cta_button_label']??''));
    echo '<section class="hero"><span class="eyebrow">'.h($university).' · URDANETA CITY CAMPUS</span><h1>'.h($homeTitle).'</h1><p>'.h($homeDescription).'</p>';
    if($callToAction!=='') echo '<p>'.h($callToAction).'</p>';
    if($buttonLabel!=='') echo '<p><a class="button" href="index.php?contact=1">'.h($buttonLabel).'</a></p>';
    echo '<form class="search" method="get"><input name="q" placeholder="Search campus content" aria-label="Search campus content" maxlength="100"><button>Search</button></form></section>';

    $universityIntroduction=trim(preg_replace('/\s+/', ' ', strip_tags((string)($homepageSettings['university_introduction']??'')))??'');
    $aboutPsu=trim(preg_replace('/\s+/', ' ', strip_tags((string)($homepageSettings['about_psu']??'')))??'');
    if($universityIntroduction!==''||$aboutPsu!=='') {
        echo '<section class="home-introduction">';
        if($universityIntroduction!=='') echo '<h2>Our University</h2><p>'.h($universityIntroduction).'</p>';
        if($aboutPsu!=='') echo '<h2>About PSU</h2><p>'.h($aboutPsu).'</p>';
        echo '</section>';
    }

    $bannerQuery=$pdo->query("SELECT banner_id, title, subtitle, button_label, button_url FROM homepage_banners WHERE is_active = 1 ORDER BY sort_order, banner_id");
    $banners=$bannerQuery->fetchAll();
    foreach($banners as $banner) {
        echo '<section class="home-banner"><img src="index.php?banner='.(int)$banner['banner_id'].'" alt=""><div><h2>'.h($banner['title']).'</h2>';
        if(!empty($banner['subtitle'])) echo '<p>'.h($banner['subtitle']).'</p>';
        $bannerUrl=trim((string)($banner['button_url']??''));
        if(!empty($banner['button_label'])&&preg_match('#^(https?://[^\s]+|mailto:[^\s]+|/[^\s]*|\#[^\s]*)$#i',$bannerUrl)) {
            echo '<a class="button secondary" href="'.h($bannerUrl).'">'.h($banner['button_label']).'</a>';
        }
        echo '</div></section>';
    }

    $featuredQuery=$pdo->query("SELECT content_type, content_id, section_title FROM homepage_featured_items WHERE is_active = 1 ORDER BY sort_order, featured_item_id LIMIT 12");
    $featuredGroups=[];
    foreach($featuredQuery->fetchAll() as $featured) {
        $item=public_content_item($pdo,(string)$featured['content_type'],(int)$featured['content_id']);
        if($item) {
            $sectionTitle=trim((string)($featured['section_title']??''))?:'Featured content';
            $item['type']=$featured['content_type'];
            $featuredGroups[$sectionTitle][]=$item;
        }
    }
    foreach($featuredGroups as $sectionTitle=>$featuredItems) {
        echo '<div class="section-head"><h2>'.h($sectionTitle).'</h2></div><div class="cards">';
        foreach($featuredItems as $item) {
            echo '<article class="card">';
            $image=public_asset_url($item['image_path']??null,'media');
            if($image) echo '<img src="'.h($image).'" alt="">';
            echo '<div class="card-in"><span class="eyebrow">'.h($types[$item['type']]??$item['type']).'</span><h2><a href="index.php?type='.rawurlencode($item['type']).'&amp;item='.(int)$item['id'].'">'.h($item['title']).'</a></h2>';
            $summary=trim(strip_tags((string)($item['summary']?:$item['body'])));
            if($summary!=='') echo '<p>'.h(mb_substr($summary,0,170)).'</p>';
            echo '</div></article>';
        }
        echo '</div>';
    }

    $featuredContent=$homepageSettings['featured_content']??'';
    $updateType=['latest-news'=>'news','latest-announcements'=>'announcements','upcoming-events'=>'events'][$featuredContent]??'updates';
    $rows=public_content_rows($pdo,$updateType);
    if($featuredContent==='upcoming-events') {
        $rows=array_values(array_filter($rows,static fn(array $row): bool => !empty($row['display_date'])&&strtotime((string)$row['display_date'])>=time()));
    }
    $rows=array_slice($rows,0,9);
    $browseType=$updateType==='updates'?'news':$updateType;
    echo '<div id="campus-updates" class="section-head"><h2>'.($updateType==='updates'?'Campus updates':'Latest '.h($types[$updateType]??'content')).'</h2><a href="index.php?type='.h($browseType).'">Browse '.h(strtolower($types[$browseType]??'content')).'</a></div>';
}
echo '<div class="cards">';
foreach($rows as $r) {
    echo '<article class="card">';
    $cardImage=public_asset_url($r['image_path']??null,'media');
    if($cardImage) echo '<img src="'.h($cardImage).'" alt="">';
    $title='<h2>'.h($r['title']).'</h2>';
    if(in_array($r['type'],['news','announcements','events','pages'],true)) {
        $title='<h2><a href="index.php?type='.rawurlencode($r['type']).'&amp;item='.(int)$r['id'].'">'.h($r['title']).'</a></h2>';
    }
    $summary=trim(strip_tags((string)($r['summary']?:$r['body'])));
    echo '<div class="card-in"><span class="eyebrow">'.h($types[$r['type']]??$r['type']).'</span>'.$title.'<p>'.h(mb_substr($summary,0,170)).'</p>';
    $link=public_asset_url($r['link_url']??null,'docs');
    if($link) echo '<p><a class="button secondary" href="'.h($link).'" download>Download document</a></p>';
    $date=$r['display_date']?:$r['created_at'];
    if($date) echo '<div class="meta">'.h(date('M j, Y',strtotime($date))).'</div>';
    echo '</div></article>';
}
if(!$rows) echo '<p class="empty">Nothing published here yet.</p>';
echo '</div>';
site_footer();
