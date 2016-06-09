<?php
/**
 */

class Tamper
{
    //开启图像变为base64
    public static $enable_image_to_base64=false;

    public static function get_image_to_base64DataUrl($url){
        $imageData=new DataTransport();
        $imageData->go($url);
        @preg_match_all('/Content-Type:(.*?)[\r\n]+/is',$imageData->header,$contentTypeMatch);

        return 'data:'.$contentTypeMatch[1][0].';base64, '.base64_encode($imageData->response);
    }

    private static function endWith($source, $checkstr) {

        $length = strlen($checkstr);
        if($length == 0)
        {
            return true;
        }
        return (substr($source, -$length) === $checkstr);
    }

    private static function startWith($source,$checkstr){
        return strpos($source, $checkstr) === 0;
    }

    public static function hook($url,$response) {
        $protocol = (isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == '443') ? 'https://' : 'http://';

        //解决因为请求资源而导致的异常
        if(strpos($url, '.css')||strpos($url, '.js')){
            // 获取当前url的根地址
            $hook_url_temp = $_SERVER['HTTP_REFERER'];
            while($hook_url_temp[strlen($hook_url_temp) - 1] != '/' && $hook_url_temp != '') {
                $hook_url_temp = substr($hook_url_temp,0,strlen($hook_url_temp) - 1);
            }

            if(Tamper::endWith($hook_url_temp,"://"))
            {
                $hook_target=$_SERVER['HTTP_REFERER'].'/';
            }
            else
            {
                $hook_target = $hook_url_temp;
            }

        }
        else
        {
            $hook_target =$_SERVER['PHP_SELF'] . '?url=';
            // 获取当前url的根地址
            $hook_url_temp = $url;
            while($hook_url_temp[strlen($hook_url_temp) - 1] != '/' && $hook_url_temp != '') {
                $hook_url_temp = substr($hook_url_temp,0,strlen($hook_url_temp) - 1);
            }
            $hook_target = $hook_target . $hook_url_temp;

            //解决因为例如https://github.com后面没有加/而导致的hook路径错误问题
            preg_match("~^(([^:/?#]+):)?(//([^/?#]*))?([^?#]*)(\?([^#]*))?(#(.*))?~i", $url, $urlmatches);

            @$checkrooturl=$hook_url_temp.$urlmatches[4];
            if(@$checkrooturl==$url){
                $hook_target = $hook_target . $urlmatches[4].'/';
            }

        }


        //URLENCODE！！！

        preg_match_all('/=("|\')[\/]{1,2}(.*?)("|\')/is',$response,$urlMatchs);
        $pageUrlArray=$urlMatchs[2];
        preg_match_all('/href=("|\')(.*?)("|\')/is',$response,$urlMatchs);
        $pageUrlArray=array_merge($pageUrlArray,$urlMatchs[2]);
        preg_match_all('/=("|\')http(.*?)("|\')/is',$response,$urlMatchs);
        $pageUrlArray=array_merge($pageUrlArray,$urlMatchs[2]);
        preg_match_all('/url(\("|\(\'|\()(.*?)("\)|\'\)|\))/is',$response,$urlMatchs);
        $pageUrlArray=array_merge($pageUrlArray,$urlMatchs[2]);

        if(!empty($pageUrlArray)){
            //解决某些相同的短字符串匹配后，影响后面比它更长的字符串的匹配
            $pageUrlArrayElementsLengthArray=array();
            foreach($pageUrlArray as $nowPageUrl){
                array_push($pageUrlArrayElementsLengthArray,strlen($nowPageUrl));
            }

            array_multisort($pageUrlArrayElementsLengthArray,SORT_DESC,SORT_NUMERIC,$pageUrlArray);

            foreach($pageUrlArray as $pageUrl){
                //解决错误替换了VIEWSTATE的问题,用于验证是否是比较合法的url
                if(strpos($pageUrl,'.')){
                    $response = str_replace($pageUrl, urlencode($pageUrl) , $response);
                }
            }
        }


        if(Tamper::$enable_image_to_base64) {
            $response = preg_replace('/background-image:url/is', '！！！replacebgimgurl！！！', $response);
            $response = preg_replace('/background:url/is', '！！！replacebgurl！！！', $response);
        }



        //因为需要篡改页面，所以需要去除integrity的限定
        $response = preg_replace('/integrity=(\'|\")\S*(\'|\")/i', '' , $response);

        //计划先换成其他字符，避免被后面的正则再次替换。
        $response = preg_replace('/=\'http/i', '！！！replacehttp1！！！' , $response);
        $response = preg_replace('/=\"http/i', '！！！replacehttp2！！！', $response);
        $response = preg_replace('/=\'\/\//is', '！！！replace1！！！' , $response);
        $response = preg_replace('/=\"\/\//is', '！！！replace2！！！' , $response);
        $response = preg_replace('/=\'.\//is', '！！！replacedot1！！！' , $response);
        $response = preg_replace('/=\".\//is', '！！！replacedot2！！！' , $response);
        $response = preg_replace('/href=\'/is', '！！！replacehref1！！！' , $response);
        $response = preg_replace('/href="/is', '！！！replacehref2！！！' , $response);



        // 替换基本的 / 根引用 成本网址的根引用

        $response = preg_replace('/=\'\//is', '=\'' . $hook_target , $response);
        $response = preg_replace('/=\"\//is', '="' . $hook_target , $response);
        $response = preg_replace("/url\('\//is", 'url(\'' . $hook_target , $response);
        $response = preg_replace('/url\(\"\//is', 'url("' . $hook_target , $response);

        // 替换 http绝对引用 为 本网址的相对引用
        $http_abs_ref =  $_SERVER['PHP_SELF'] . '?url=http';

        $response = preg_replace('/！！！replacehttp1！！！/is', '=\'' .$http_abs_ref , $response);
        $response = preg_replace('/！！！replacehttp2！！！/is', '="' .$http_abs_ref , $response);


        $response = preg_replace('/！！！replace1！！！/is', '=\'' . $_SERVER['PHP_SELF'] . '?url='.$protocol , $response);
        $response = preg_replace('/！！！replace2！！！/is', '="' . $_SERVER['PHP_SELF'] . '?url='.$protocol , $response);
        $response = preg_replace('/！！！replacedot1！！！/is', '=\'' . $hook_target , $response);
        $response = preg_replace('/！！！replacedot2！！！/is', '="' . $hook_target , $response);
        $response = preg_replace('/！！！replacehref1！！！/is', 'href=\'' . $hook_target , $response);
        $response = preg_replace('/！！！replacehref2！！！/is', 'href="' . $hook_target , $response);


        if(Tamper::$enable_image_to_base64)
        {
            $response = preg_replace('/！！！replacebgurl！！！/is', 'background:url' , $response);
            $response = preg_replace('/！！！replacebgimgurl！！！/is', 'background-image:url' , $response);
            parse_str(parse_url($hook_target)['query'],$refererUrlParse);
            $refererUrl=$refererUrlParse['url'];
            preg_match_all('/background:url(\("|\(\'|\()(.*?)("\)|\'\)|\))/is',$response,$bgMatchs);
            preg_match_all('/background-image:url(\("|\(\'|\()(.*?)("\)|\'\)|\))/is',$response,$bgimageMatchs);
            $images=array_merge($bgMatchs[2],$bgimageMatchs[2]);
            // start with /
            $parse_url_refererUrl=parse_url($refererUrl);
            $refererRootUrl=$parse_url_refererUrl['scheme'].'://'.$parse_url_refererUrl['host'].(array_key_exists('port',$parse_url_refererUrl)?(':'.$parse_url_refererUrl['port']):'');
            foreach($images as $imageurl){
                $readImgUrl='';
                //处理以/开始的url
                if(Tamper::startWith($imageurl,'/')){
                    $readImgUrl=$refererRootUrl.$imageurl;
                }
                else{
                    $readImgUrl=$refererUrl.$imageurl;
                }
                $imgDataBase64=Tamper::get_image_to_base64DataUrl($readImgUrl);

                //替换字符串需要处理不标准的写法，如没有使用‘或者“
                if(strpos($response,'background-image:url('.$imageurl.')')){
                    $response = str_replace($imageurl, '"'.$imgDataBase64.'"' , $response);
                }
                else{
                    $response = str_replace($imageurl, $imgDataBase64, $response);
                }
            }
        }

        return $response;
    }

    public static function fix_request_url($url){
        $url=preg_replace('/http:\/\/\//i','http://',$url);
        $url=preg_replace('/https:\/\/\//i','https://',$url);
        return $url;
    }

}

class DataTransport
{
    public $response="";
    public $header="";
    public $errMsg="";
    public $CURL_enable_jsonp    = false;
    public $CURL_enable_native   = true;
    public $CURL_valid_url_regex = '/.*/';
    public $CURL_SendCookie="";
    public $CURL_SendSession="";
    public $CURL_mode="native";
    public $CURL_CallBack="";
    public $CURL_user_agent="";
    public $CURL_full_headers="1";
    public $CURL_full_status='1';



    public function go($url, $postdata='',$mode="native")
    {
        if(function_exists("curl_init")){
            return $this->Post_CURL($url, $postdata,$mode);
        }
        else
        {
            return $this->Post_FILE_GET_CONTENTS($url, $postdata);
        }
    }

    private function Post_CURL($url, $postdata=null,$mode="native"){
        $this->errMsg="";

        $this->response="";
        $this->header="";
        if ( !$url ) {

            // Passed url not specified.
            $contents = 'ERROR: url not specified';
            $status = array( 'http_code' => 'ERROR' );

        } else if ( !preg_match( $this->CURL_valid_url_regex, $url ) ) {

            // Passed url doesn't match $valid_url_regex.
            $contents = 'ERROR: invalid url';
            $status = array( 'http_code' => 'ERROR' );

        } else {
            $ch = curl_init( $url );

            // 设置post数据
            if ( $postdata!=null ) {
                curl_setopt( $ch, CURLOPT_POST, true );
                @curl_setopt( $ch, CURLOPT_POSTFIELDS, $postdata );
            }
            // 设置cookie数据
            if ($this->CURL_SendCookie ) {
                $cookie = array();
                foreach ( $_COOKIE as $key => $value ) {
                    $cookie[] = $key . '=' . $value;
                }
                if ( $this->CURL_SendSession ) {
                    $cookie[] = SID;
                }
                $cookie = implode( '; ', $cookie );

                curl_setopt( $ch, CURLOPT_COOKIE, $cookie );
            }
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_BINARYTRANSFER,true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, array("Expect:"));
            curl_setopt($ch, CURLOPT_HEADER, true);
            curl_setopt($ch, CURLOPT_HTTP_VERSION, '1.0'); // 使用 Http1.0 避免chunked
            curl_setopt($ch, CURLOPT_TIMEOUT, 60); // 设置超时

            curl_setopt( $ch, CURLOPT_USERAGENT, $this->CURL_user_agent ? $this->CURL_user_agent : @$_SERVER['HTTP_USER_AGENT'] );

            $getresponse = curl_exec($ch);
            list( $header, $contents ) = preg_split( '/([\r\n][\r\n])\\1/', $getresponse, 2 );



            if (curl_getinfo($ch, CURLINFO_HTTP_CODE) == '200') {
                $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
                $this->header = substr($getresponse, 0, $headerSize);
                $this->response = substr($getresponse, $headerSize);
            }

            $status = curl_getinfo( $ch );

            curl_close( $ch );
        }

        // Split header text into an array.
        $header_text = preg_split( '/[\r\n]+/', $header );

        if ( $mode == 'native') {
            if ( !$this->CURL_enable_native) {
                $contents = 'ERROR: invalid mode';
                $status = array( 'http_code' => 'ERROR' );
            }

            // Propagate headers to response.
            /* foreach ( $header_text as $header ) {
                 header( $header );
             }
 */
            return $contents;

        }
        else
        {

            // $data will be serialized into JSON data.
            $data = array();

            // Propagate all HTTP headers into the JSON data object.
            if ($this->CURL_full_headers) {
                $data['headers'] = array();

                foreach ( $header_text as $header ) {
                    preg_match( '/^(.+?):\s+(.*)$/', $header, $matches );
                    if ( $matches ) {
                        $data['headers'][ $matches[1] ] = $matches[2];
                    }
                }
            }

            // Propagate all cURL request / response info to the JSON data object.
            if ( $this->CURL_full_status) {
                $data['status'] = $status;
            } else {
                $data['status'] = array();
                $data['status']['http_code'] = $status['http_code'];
            }

            // Set the JSON data object contents, decoding it from JSON if possible.
            $decoded_json = json_decode( $contents );
            $data['contents'] = $decoded_json ? $decoded_json : $contents;

            // Generate appropriate content-type header.
            $is_xhr = strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest';
            header( 'Content-type: application/' . ( $is_xhr ? 'json' : 'x-javascript' ) );

            // Get JSONP callback.
            $jsonp_callback = $this->CURL_enable_jsonp && isset($this->CURL_CallBack) ? $this->CURL_CallBack : null;

            // Generate JSON/JSONP string
            $json = json_encode( $data );

            return $jsonp_callback ? "$jsonp_callback($json)" : $json;

        }
    }

    private function Post_FILE_GET_CONTENTS($url, $post = null)
    {
        $context = array();
        if (is_array($post)) {
            ksort($post);
            $context['http'] = array (
                'timeout'=>10,
                'method' => 'POST',
                'header' => 'Content-type: application/x-www-form-urlencoded',
                'content' => http_build_query($post, '', '&'),
            );
        }
        $this->response=file_get_contents($url, false, stream_context_create($context));
        return $this->response;
    }

}


ob_start();



@$URL=$_REQUEST['url'];


if(!empty($URL))
{
    $dataTransport=new DataTransport();
    //处理出现https:///或者http:///的问题，不是根本解决办法，有点偷懒
    $URL=Tamper::fix_request_url($URL);
    $postArray=array();

//文件处理
    if(!empty($_FILES)){
        foreach($_FILES as $key=>$value){
            move_uploaded_file($value['tmp_name'], $value['name']);
            $postArray[$key]='@'.realpath($value['name']);
        }
    }

//处理POST数据
    foreach($_POST as $key=>$value){
            $postArray[$key]=$value;
    }

//处理GET
    foreach($_REQUEST as $key => $val){
        if($key!=='url'){
            $URL.='&'.$key.'='.$val;
        }
    }

//获取数据
    $dataTransport->go($URL,$postArray);

//处理数据

    foreach ( preg_split( '/[\r\n]+/', $dataTransport->header ) as $headertext  ) {

        //处理因为Content-Security-Policy而导致的资源不能加载的情况
        $pos = strpos($headertext, 'Content-Security-Policy');
        if ($pos === false) {
            header($headertext);
        }

    }
//Hook所有url
    $dataTransport->response = Tamper::hook($URL, $dataTransport->response);
    print($dataTransport->response);

//删除临时文件
    if(!empty($_FILES)){
        foreach($_FILES as $key=>$value){
            unlink($value['name']);
        }
    }

} else{
    echo 'url null';
}

ob_end_flush();

?>
