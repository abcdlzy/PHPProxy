<?php 

$list = array(
    'http://normal.com',
    'https://normals.com',
    'http:////a.com/',
    'https:////a.com////',
    'http:/a.com//////',
    'http://normal.com/test/page.html',
    'https://normals.com/test/page.html?req=hello',
    'http:////a.com/test/123/page/__f.aspx?req=test',
    'https:////a.com////test/123/page/__f.aspx?req=test',
    'http:/a.com//////test/123/page/__f.aspx?req=test',
);

function formatURL($url) {
    if (empty($url)) return '';
    $url_detail = array();
    $url = urldecode($url);
    if (!preg_match('/^(https|http):\/{0,}(.*)$/', $url, $url_detail) || count($url_detail) != 3) return '';
    $url = $url_detail[1] . '://' . preg_replace('/\/{2,}/', '/', $url_detail[2]);
    return $url;
}

foreach ($list as $val) {
    foreach ($list as $val) {
        echo 
            'origin : ' . $val . '<br />' .
            'new : ' . formatURL($val) . '<br />';

        $encode = urlencode($val);
        echo 
            'origin : ' . $encode . '<br />' .
            'new : ' . formatURL($encode) . '<br />';

        echo '<br /><br /><br /><br /><br /><br />';
    }
}
?>