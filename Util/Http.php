<?php
namespace Tool\Util;

class Http
{
    private static $boundary = '';

    /**
     * 错误通知到微信企业号上
     */
    public static function notice($log_path = "", $operation_name = "", $msg = "", $content = "")
    {
        if (!is_string($content)) {
            return false;
        }
        $wxqy_base_fx = config("myapp.wxqy_base_fx");
        if (empty($wxqy_base_fx)) {
            return false;
        }
        $data['agent_id'] = config("myapp.error_notice_agent_id");
        if (empty($data['agent_id'])) {
            return false;
        }
        $data['content'] =
            "项目：" . config('myapp.app_name') . "\n" .
            "环境：" . config('myapp.env') . "\n" .
            "日志路径：" . $log_path . "\n" .
            "操作名称：" . $operation_name . "\n" .
            "错误：" . $msg . "\n" .
            "内容：" . $content;
        self::post($wxqy_base_fx . "service/send-text/to-all", $data, [], false);
        return true;
    }

    public static function get($url, $params = [], $error_notice = true)
    {
        if (!empty($params)) {
            $url = $url . '?' . http_build_query($params);
        }
        $return = self::curl($url, 'GET');
        if ($return['httpCode'] != 200) {
            log_file("error/get", "GET非200", ["url" => $url, "params" => $params], $return['httpCode'], $return['response']);
            if ($error_notice) {
                self::notice("error/get", "GET非200", "url：$url", "httpCode:{$return['httpCode']}");
            }
            return _output($return, false);
        }
        return _output($return["response"]);
    }

    public static function post($url, $params = [], $files = [], $error_notice = true)
    {
        $headers = array();
        if (!$files) {
            $body = http_build_query($params);
        } else {
            $body_res = self::build_http_query_multi($params, $files);
            if (!$body_res['result']) {
                if ($error_notice) {
                    log_file("error/post", "build_http_query_multi", ["url" => $url, "params" => $params, "files" => $files], $body_res['data']);
                    self::notice("error/post", "build_http_query_multi", $body_res['data']);
                }
                return $body_res;
            }
            $body = $body_res['data'];
            $headers[] = "Content-Type: multipart/form-data; boundary=" . self::$boundary;
        }
        $return = self::curl($url, 'POST', $body, $headers);
        if ($return['httpCode'] != 200) {
            log_file("error/post", "POST非200", ["url" => $url, "params" => $params, "files" => $files], $return['httpCode'], $return['response']);
            if ($error_notice) {
                self::notice("error/post", "POST非200", "url：$url", "httpCode:{$return['httpCode']}");
            }
            return _output($return, false);
        }
        return _output($return["response"]);
    }


    public static function curl($url, $method, $postfields = NULL, $headers = array())
    {
        $ci = curl_init();
        curl_setopt($ci, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_0);
        curl_setopt($ci, CURLOPT_CONNECTTIMEOUT, 30);
        curl_setopt($ci, CURLOPT_TIMEOUT, 30);//设置超时
        curl_setopt($ci, CURLOPT_RETURNTRANSFER, TRUE);//要求结果为字符串且输出到屏幕上
        curl_setopt($ci, CURLOPT_ENCODING, "");
        curl_setopt($ci, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ci, CURLOPT_SSL_VERIFYHOST, 0);
        //curl_setopt($ci, CURLOPT_HEADERFUNCTION, array($this, 'getHeader'));
        curl_setopt($ci, CURLOPT_HEADER, FALSE);//设置header

        switch ($method) {
            case 'POST':
                curl_setopt($ci, CURLOPT_POST, TRUE);
                if (!empty($postfields)) {
                    curl_setopt($ci, CURLOPT_POSTFIELDS, $postfields);
                }
                break;
        }

        curl_setopt($ci, CURLOPT_URL, $url);
        curl_setopt($ci, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ci, CURLINFO_HEADER_OUT, TRUE);

        $response = curl_exec($ci);
        $httpCode = curl_getinfo($ci, CURLINFO_HTTP_CODE);
//        $httpInfo = curl_getinfo($ci);
        curl_close($ci);
        return ["response" => $response, "httpCode" => $httpCode];
    }

    private static function build_http_query_multi($params, $files)
    {
        if (!is_array($params)) {
            $params = [];
        }
        self::$boundary = $boundary = uniqid('------------------');
        $MPboundary = '--' . $boundary;
        $endMPboundary = $MPboundary . '--';
        $multipartbody = '';

        foreach ($params as $key => $value) {
            $multipartbody .= $MPboundary . "\r\n";
            $multipartbody .= 'content-disposition: form-data; name="' . $key . "\"\r\n\r\n";
            $multipartbody .= $value . "\r\n";
        }
        foreach ($files as $key => $value) {
            if (!$value) {
                continue;
            }

            if (is_array($value)) {
                $url = $value['url'];
                if (isset($value['name'])) {
                    $filename = $value['name'];
                } else {
                    $parts = explode('?', basename($value['url']));
                    $filename = $parts[0];
                }
                $field = isset($value['field']) ? $value['field'] : $key;
            } else {
                $url = $value;
                $parts = explode('?', basename($url));
                $filename = $parts[0];
                $field = $key;
            }
            try {
                $content = file_get_contents($url);
            } catch (\Exception $e) {
                return _output("url:" . $url . "错误:" . $e->getMessage(), false);
            }


            $multipartbody .= $MPboundary . "\r\n";
            $multipartbody .= 'Content-Disposition: form-data; name="' . $field . '"; filename="' . $filename . '"' . "\r\n";
            $multipartbody .= "Content-Type: image/unknown\r\n\r\n";
            $multipartbody .= $content . "\r\n";
        }

        $multipartbody .= $endMPboundary;
        return _output($multipartbody);
    }
}