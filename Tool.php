<?php
/**
 * Created by PhpStorm.
 * User: user
 * Date: 2019/2/1
 * Time: 15:28
 */

class Tool
{
    private $tsDir;
    private $mp4Url;

    public function __construct($tsDir = './ts/', $mp4Url = './mp4/')
    {
        $this->tsDir = $tsDir;
        $this->mp4Url = $mp4Url;
        if (!file_exists($tsDir)) {
            if (!mkdir($tsDir)) {
                die('Please create directory：' . $tsDir);
            }
        }

        if (!file_exists($mp4Url)) {
            if (!mkdir($mp4Url)) {
                die('Please create directory：' . $mp4Url);
            }
        }
    }

    /**
     * 下载ts并合并生成mp4
     * @param $tsFiles
     * @param int $num
     */
    public function down($tsFiles, $num = 0)
    {
        go(function () use ($tsFiles, $num) {
            $chan = new chan(100); //最大并发数
            foreach ($tsFiles[$num] as $key => $value) {
                if (file_exists($this->tsDir . $key . '.ts')) {
                    continue;
                }

                $chan->push('xx');
                go(function () use ($key, $value, $chan) {
                    echo "Add task:" . $key . PHP_EOL;
                    while (1) {
                        $rs = $this->coCurl($value);
                        if (strlen($rs) > 0) {
                            file_put_contents($this->tsDir . $key . '.ts', $rs);
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
            $fileName = $this->mp4Url . ($num + 1) . '.mp4';
            foreach ($tsFiles[$num] as $key => $value) {
                file_put_contents($fileName, file_get_contents($this->tsDir . $key . '.ts'), FILE_APPEND);
                unlink($this->tsDir . $key . '.ts');
            }

            echo "下载完成，转换成功:" . $fileName . PHP_EOL;
            $num++;
            if ($num < count($tsFiles)) {
                $this->down($tsFiles, $num);
            }
        });
    }

    /**
     * go routine CURL
     * @param $url
     * @param string $cookies
     * @param array $data
     * @param array $userHeaders
     * @param int $retJson
     * @return mixed
     */
    public function coCurl($url, $cookies = '', $data = array(), $userHeaders = array(), $retJson = 0)
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
}