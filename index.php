<?php
//okzyw下载m3u8文件
include_once 'Tool.php';
$okzyw = getOkzyw();
$tool = new Tool();
$tool->down($okzyw);

/**
 * 获取Okzyw TS文件列表
 * @return array
 */
function getOkzyw()
{
    $list = file_get_contents('okzyw.txt');
    $list = explode(PHP_EOL, $list);
    if (empty($list)) {
        echo 'okzyw：nothing to do' . PHP_EOL;
        return [];
    }

    $tsFiles = [];
    foreach ($list as $k => $v) {
        $ret = explode(PHP_EOL, file_get_contents($v));
        $url = str_replace('index.m3u8', $ret[(count($ret) - 1)], $v);
        $baseUrl = substr($url, 0, strrpos($url, "/") + 1);
        $indexPage = file_get_contents($url);
        preg_match_all('/.*\.ts/', $indexPage, $matches);

        if (empty($matches[0])) {
            echo 'm3u8 format error：' . $k . PHP_EOL;
            continue;
        }

        foreach ($matches[0] as $m) {
            $tsFiles[$k][] = $baseUrl . $m;
        }
    }

    if (empty($tsFiles)) {
        echo 'okzyw：m3u8 is empty' . PHP_EOL;
    }

    return $tsFiles;
}

