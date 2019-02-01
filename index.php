<?php
//php下载m3u8文件
$list = file_get_contents('list.txt');
$list = explode(PHP_EOL, $list);
$num = 0;

if (!file_exists('./tmp/')) {
    if (!mkdir('./tmp/')) {
        die('请手动在当前目录创建tmp目录');
    }
}

if (!file_exists('./video/')) {
    if (!mkdir('./video/')) {
        die('请手动在当前目录创建video目录');
    }
}

down($list, $num);

function down($list, $num)
{
    $m3u8 = $list[($num)];
    $ret = file_get_contents($m3u8);
    $ret = explode(PHP_EOL, $ret);
    $url = str_replace('index.m3u8', $ret[(count($ret) - 1)], $m3u8);
    $baseUrl = substr($url, 0, strrpos($url, "/") + 1);
    $indexPage = file_get_contents($url);
    preg_match_all('/.*\.ts/', $indexPage, $matches);

    if (empty($matches)) {
        die('m3u8 文件格式错误');
    }

    go(function () use ($matches, $baseUrl, $list, $num) {
        $chan = new chan(100); //最大并发数
        foreach ($matches['0'] as $key => $value) {
            if (file_exists('./tmp/' . $key . '.ts')) {
                continue;
            }

            $chan->push('xx');
            go(function () use ($key, $value, $chan, $baseUrl) {
                echo "Add task:" . $key . PHP_EOL;
                while (1) {
                    $rs = co_curl($baseUrl . $value);
                    if (strlen($rs) > 0) {
                        file_put_contents('./tmp/' . $key . '.ts', $rs);
                        break;
                    }
                }

                echo "Task ok:" . $key . PHP_EOL;
                $chan->pop();
            });
        }

        //确保所有下载已经完成
        for ($i = 0; $i < 100; $i++) {
            $chan->push('over');
        }

        //合并文件
        $fileName = './video/'.($num + 1) . '.mp4';
        foreach ($matches['0'] as $key => $value) {
            file_put_contents($fileName, file_get_contents('./tmp/' . $key . '.ts'), FILE_APPEND);
            unlink('./tmp/' . $key . '.ts');
        }

        echo "下载完成，转换成功:" . $fileName . PHP_EOL;
        $num++;
        if ($num < count($list)) {
            down($list, $num);
        }
    });
}


function co_curl($url, $cookies = '', $data = array(), $userHeaders = array(), $retJson = 0)
{
    while (1) {
        $urlInfo = parse_url($url);
        $domain = $urlInfo['host'];
        if ($urlInfo['scheme'] == 'https') {
            $port = 443;
            $ssl = true;
        } else {
            $port = isset($urlInfo['port']) ? $urlInfo['port'] : 80;
            $ssl = false;
        }

        $filename = $urlInfo['path'];
        $filename .= isset($urlInfo['query']) ? '?' . $urlInfo['query'] : '';

        $cli = new Swoole\Coroutine\Http\Client($domain, $port, $ssl);
        $headers = [
            'Host' => $domain,
            "User-Agent" => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_13_4) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/66.0.3359.139 Safari/537.36',
            'Accept' => 'text/html,application/xhtml+xml,application/xml',
            'Accept-Encoding' => 'gzip',
        ];
        if ($userHeaders) {
            $headers = array_merge($headers, $userHeaders);
        }

        if ($cookies) {
            $headers['Cookie'] = $cookies;
        }

        $cli->setHeaders($headers);
        $cli->set(['timeout' => 60]);
        if ($data) {
            if ($data == 'post') {
                $data = '';
            }
            $cli->post($filename, $data);
        } else {
            $cli->get($filename);
        }

        $body = $cli->body;
        $cli->close();

        if ($cli->statusCode < 1 || ($retJson && empty(json_decode($body, true)))) {
            // echo "\n status code:" . $cli->statusCode;
            // echo "\n body: ".$body;
            // echo "\n retry...";
        } else {
            return $body;
        }
    }
}
