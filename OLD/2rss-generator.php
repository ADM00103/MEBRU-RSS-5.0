<?php
ini_set('display_errors', '1');
error_reporting(E_ALL);
mb_internal_encoding('UTF-8');
date_default_timezone_set('Europe/Moscow');

$max_items = 200;
$items_per_source = 20;

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

function curl_get_contents($url) {
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS => 5,
        CURLOPT_TIMEOUT => 20,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_USERAGENT => 'RSS Mixer/1.0',
        CURLOPT_ENCODING => '',
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
    ]);
    $data = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($data === false || $http_code >= 400) {
        return false;
    }
    return $data;
}

function fix_encoding($text) {
    if ($text === false || $text === null) return '';
    $text = preg_replace('/^\xEF\xBB\xBF/', '', $text);
    $encoding = mb_detect_encoding($text, ['UTF-8', 'Windows-1251', 'KOI8-R'], true);
    if ($encoding && $encoding !== 'UTF-8') {
        $text = mb_convert_encoding($text, 'UTF-8', $encoding);
    }
    return preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $text);
}

function clean_html($html) {
    $html = trim($html);
    $html = preg_replace('~<p>\s*<strong>\s*📰.*?</p>~isu', '', $html);
    return trim($html);
}

function get_text_from_item($item) {
    $content = '';
    $namespaces = $item->getNamespaces(true);

    if (isset($namespaces['content'])) {
        $contentNode = $item->children($namespaces['content']);
        if (!empty($contentNode->encoded)) {
            $content = (string)$contentNode->encoded;
        }
    }

    if ($content === '' && !empty($item->description)) {
        $content = (string)$item->description;
    }

    $content = fix_encoding($content);
    $content = clean_html($content);

    return $content;
}

function get_pub_date($item) {
    if (!empty($item->pubDate)) {
        $ts = strtotime((string)$item->pubDate);
        if ($ts !== false) return date(DATE_RSS, $ts);
    }
    return date(DATE_RSS);
}

function fetch_rss($url, $source_name, $limit) {
    $xml = curl_get_contents($url);
    if ($xml === false || trim($xml) === '') return [];

    $xml = fix_encoding($xml);

    libxml_use_internal_errors(true);
    $rss = simplexml_load_string($xml, 'SimpleXMLElement', LIBXML_NOCDATA);
    if ($rss === false || !isset($rss->channel->item)) {
        libxml_clear_errors();
        return [];
    }
    libxml_clear_errors();

    $items = [];
    $count = 0;

    foreach ($rss->channel->item as $item) {
        if ($count >= $limit) break;

        $title = trim((string)$item->title);
        $link = trim((string)$item->link);
        if ($title === '' || $link === '') continue;

        $items[] = [
            'title' => $title,
            'link' => $link,
            'description' => get_text_from_item($item),
            'pubDate' => get_pub_date($item),
            'source' => $source_name,
        ];
        $count++;
    }

    return $items;
}

$all_items = [];
foreach ($sources as $source) {
    $items = fetch_rss($source['url'], $source['name'], $items_per_source);
    if (!empty($items)) $all_items = array_merge($all_items, $items);
}

usort($all_items, function($a, $b) {
    return strtotime($b['pubDate']) <=> strtotime($a['pubDate']);
});

$all_items = array_slice($all_items, 0, $max_items);

header('Content-Type: application/rss+xml; charset=utf-8');
echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
?>
<rss version="2.0">
<channel>
    <title>MEBRU RSS</title>
    <link>https://vk.ru/mebru</link>
    <description>Aggregated RSS feed</description>
    <language>ru</language>
    <lastBuildDate><?= htmlspecialchars(date(DATE_RSS), ENT_XML1 | ENT_QUOTES, 'UTF-8') ?></lastBuildDate>

<?php foreach ($all_items as $item): ?>
    <item>
        <title><?= htmlspecialchars($item['title'], ENT_XML1 | ENT_QUOTES, 'UTF-8') ?></title>
        <link><?= htmlspecialchars($item['link'], ENT_XML1 | ENT_QUOTES, 'UTF-8') ?></link>
        <guid isPermaLink="true"><?= htmlspecialchars($item['link'], ENT_XML1 | ENT_QUOTES, 'UTF-8') ?></guid>
        <pubDate><?= htmlspecialchars($item['pubDate'], ENT_XML1 | ENT_QUOTES, 'UTF-8') ?></pubDate>
        <description><![CDATA[<?= $item['description'] ?><p><strong>Подпишись vk.ru/mebru</strong></p>]]></description>
    </item>
<?php endforeach; ?>
</channel>
</rss>