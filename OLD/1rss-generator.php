<?php
// RSS Mixer Generator - генерирует свежую RSS ленту из 14 источников

// Настройки
$max_items = 200;
$sources = [
    ['name' => 'Habr', 'url' => 'https://habr.com/ru/rss/hubs/all/'],
    ['name' => '3DNews', 'url' => 'https://3dnews.ru/news/rss/'],
    ['name' => 'Xakep', 'url' => 'https://xakep.ru/feed/'],
    ['name' => '3DNews Games', 'url' => 'https://feeds.feedburner.com/3dnews/elyv'],
    ['name' => '3DNews Soft', 'url' => 'https://feeds.feedburner.com/3dnews/fbuv'],
    ['name' => '3DNews Other', 'url' => 'https://feeds.feedburner.com/3dnews/halb'],
    ['name' => 'Zapier', 'url' => 'https://feeds.feedburner.com/zapier/tGin'],
    ['name' => 'FreeSteam', 'url' => 'http://feeds.feedburner.com/freesteam/lQDE'],
    ['name' => 'iXBT', 'url' => 'https://feeds.feedburner.com/ixbt/gSRD'],
    ['name' => 'iXBT Games v2', 'url' => 'https://politepol.com/fd/3vpeexJrRBn9'],
    ['name' => 'iXBT Games', 'url' => 'http://feeds.feedburner.com/gametech/dVHe'],
    ['name' => 'MMO13', 'url' => 'https://feeds.feedburner.com/mmo13/vBSwUWG0mTx'],
    ['name' => 'Player One', 'url' => 'https://feeds.feedburner.com/mail/zILX'],
    ['name' => 'Techimo', 'url' => 'https://techimo.ru/rss/'],
    ['name' => 'Steam Games', 'url' => 'http://feeds.feedburner.com/SteamOriginUplayGog'],
];

$all_items = [];

foreach ($sources as $source) {
    $items = fetch_rss($source['url'], $source['name']);
    $all_items = array_merge($all_items, $items);
}

// Сортировка по дате
usort($all_items, function($a, $b) {
    return strtotime($b['pubDate']) - strtotime($a['pubDate']);
});

$all_items = array_slice($all_items, 0, $max_items);

// Выводим RSS
header('Content-Type: application/rss+xml; charset=utf-8');
echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
?>
<rss version="2.0" xmlns:dc="http://purl.org/dc/elements/1.1/" 
     xmlns:content="http://purl.org/rss/1.0/modules/content/"
     xmlns:media="http://search.yahoo.com/mrss/">
<channel>
    <title>RSS Mixer - Все новости IT</title>
    <link>https://<?= $_SERVER['HTTP_HOST'] ?></link>
    <description>Смесь RSS лент из 14 источников</description>
    <language>ru-ru</language>
    <lastBuildDate><?= date(DATE_RSS) ?></lastBuildDate>

<?php foreach ($all_items as $item): ?>
    <item>
        <title><![CDATA[<?= $item['title'] ?>]]></title>
        <link><?= htmlspecialchars($item['link']) ?></link>
        <guid><?= htmlspecialchars($item['link']) ?></guid>
        <pubDate><?= $item['pubDate'] ?></pubDate>
        
        <description><![CDATA[
            <?php if (!empty($item['image'])): ?>
                <p><img src="<?= htmlspecialchars($item['image']) ?>" style="max-width:100%; border-radius:8px;"></p>
            <?php endif; ?>
            <p><strong>📰 <?= htmlspecialchars($item['source']) ?></strong> | <a href="<?= htmlspecialchars($item['source_url']) ?>">Источник</a></p>
            <?= $item['description'] ?>
        ]]></description>
        
        <content:encoded><![CDATA[
            <?php if (!empty($item['image'])): ?>
                <p><img src="<?= htmlspecialchars($item['image']) ?>" style="max-width:100%; border-radius:8px;"></p>
            <?php endif; ?>
            
            <hr/>
            <?= $item['content_encoded'] ?>
        ]]></content:encoded>
        
        <?php if (!empty($item['image'])): ?>
            <media:thumbnail url="<?= htmlspecialchars($item['image']) ?>" />
            <media:content url="<?= htmlspecialchars($item['image']) ?>" medium="image" />
        <?php endif; ?>
        
        <dc:creator><?= htmlspecialchars($item['source']) ?></dc:creator>
    </item>
<?php endforeach; ?>

</channel>
</rss>

<?php
// ФУНКЦИИ
function fetch_rss($url, $source_name) {
    $xml_content = @file_get_contents($url);
    if ($xml_content === false) {
        $xml_content = curl_get_contents($url);
    }
    if (empty($xml_content)) return [];
    
    $xml_content = fix_encoding($xml_content);
    libxml_use_internal_errors(true);
    $rss = simplexml_load_string($xml_content);
    if ($rss === false) return [];
    
    $items = [];
    
    if (isset($rss->channel->item)) {
        foreach ($rss->channel->item as $item) {
            $content_encoded = '';
            $namespaces = $item->getNameSpaces(true);
            if (isset($namespaces['content'])) {
                $children = $item->children($namespaces['content']);
                if (isset($children->encoded)) {
                    $content_encoded = (string)$children->encoded;
                }
            }
            if (empty($content_encoded)) {
                $content_encoded = (string)$item->description;
            }
            
            $image_url = extract_first_image($content_encoded);
            if (empty($image_url)) {
                $image_url = extract_image_from_media($item);
            }
            
            $clean_description = remove_all_images((string)$item->description);
            $clean_content = remove_all_images($content_encoded);
            
            $items[] = [
                'title' => clean_text((string)$item->title),
                'link' => (string)$item->link,
                'description' => clean_text($clean_description, 0),
                'content_encoded' => clean_text($clean_content, 0),
                'pubDate' => date(DATE_RSS, strtotime((string)$item->pubDate)),
                'source' => $source_name,
                'source_url' => $url,
                'image' => $image_url,
            ];
        }
    } elseif (isset($rss->entry)) {
        foreach ($rss->entry as $entry) {
            $content = '';
            if (isset($entry->content)) {
                $content = (string)$entry->content;
            } elseif (isset($entry->summary)) {
                $content = (string)$entry->summary;
            }
            
            $link = '';
            if (isset($entry->link['href'])) {
                $link = (string)$entry->link['href'];
            } elseif (isset($entry->link)) {
                $link = (string)$entry->link;
            }
            
            $image_url = extract_first_image($content);
            $clean_content = remove_all_images($content);
            
            $items[] = [
                'title' => clean_text((string)$entry->title),
                'link' => $link,
                'description' => clean_text($clean_content, 0),
                'content_encoded' => clean_text($clean_content, 0),
                'pubDate' => date(DATE_RSS, strtotime((string)$entry->updated)),
                'source' => $source_name,
                'source_url' => $url,
                'image' => $image_url,
            ];
        }
    }
    
    return $items;
}

function extract_first_image($html) {
    if (empty($html)) return '';
    if (preg_match('/<img[^>]+src=["\']([^"\']+)["\']/i', $html, $matches)) {
        $url = $matches[1];
        $exclude = ['/icon/i', '/logo/i', '/avatar/i', '/emoji/i', '/pixel/i', '/1x1/i'];
        foreach ($exclude as $pattern) {
            if (preg_match($pattern, $url)) return '';
        }
        return $url;
    }
    return '';
}

function extract_image_from_media($item) {
    $namespaces = $item->getNameSpaces(true);
    if (isset($namespaces['media'])) {
        $media = $item->children($namespaces['media']);
        if (isset($media->content)) {
            foreach ($media->content as $content) {
                $attrs = $content->attributes();
                if (!empty($attrs['url'])) return (string)$attrs['url'];
            }
        }
        if (isset($media->thumbnail)) {
            $attrs = $media->thumbnail->attributes();
            if (!empty($attrs['url'])) return (string)$attrs['url'];
        }
    }
    if (isset($item->enclosure)) {
        $attrs = $item->enclosure->attributes();
        if (!empty($attrs['url'])) return (string)$attrs['url'];
    }
    return '';
}

function remove_all_images($html) {
    if (empty($html)) return $html;
    $html = preg_replace('/<img[^>]*>/i', '', $html);
    $html = preg_replace('/<p>\s*<\/p>/i', '', $html);
    return $html;
}

function curl_get_contents($url) {
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_TIMEOUT => 15,
        CURLOPT_USERAGENT => 'RSS Mixer/1.0',
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_ENCODING => '',
    ]);
    $data = curl_exec($ch);
    curl_close($ch);
    return $data;
}

function fix_encoding($text) {
    $text = preg_replace('/^\xEF\xBB\xBF/', '', $text);
    $encoding = mb_detect_encoding($text, ['UTF-8', 'Windows-1251', 'KOI8-R'], true);
    if ($encoding && $encoding !== 'UTF-8') {
        $text = mb_convert_encoding($text, 'UTF-8', $encoding);
    }
    $text = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $text);
    return $text;
}

function clean_text($text, $max_len = 0) {
    if (empty($text)) return '';
    $text = html_entity_decode($text, ENT_QUOTES | ENT_XML1, 'UTF-8');
    $text = trim($text);
    if ($max_len > 0 && mb_strlen($text) > $max_len) {
        $text = mb_substr($text, 0, $max_len) . '…';
    }
    return $text;
}
?>