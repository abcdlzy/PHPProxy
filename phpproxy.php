<?php
/**
 */



class DataTransport
{
    public static $response="";
    public static $header="";
    public static $errMsg="";
    public static $CURL_enable_jsonp    = false;
    public static $CURL_enable_native   = true;
    public static $CURL_valid_url_regex = '/.*/';
    public static $CURL_SendCookie="";
    public static $CURL_SendSession="";
    public static $CURL_mode="native";
    public static $CURL_CallBack="";
    public static $CURL_user_agent="";
    public static $CURL_full_headers="1";
    public static $CURL_full_status='1';

    public static function hook_url($url,$response) {
        $hook_target = $_SERVER['PHP_SELF'] . '?url=';
        $hook_origin_url = $hook_target;
        $hook_form_target = $hook_target;
        // 获取当前url的根地址
        $hook_url_temp = $url;
        while($hook_url_temp[strlen($hook_url_temp) - 1] != '/' && $hook_url_temp != '') {
            $hook_url_temp = substr($hook_url_temp,0,strlen($hook_url_temp) - 1);
        }
        $hook_target = $hook_target . $hook_url_temp;

        // 替换基本的 / 根引用 成本网址的根引用
        $response = preg_replace('/href=\"\//is', 'href="' . $hook_target , $response);
        $response = preg_replace('/src=\"\//is', 'src="' . $hook_target , $response);
        $response = preg_replace("/url\('\//is", 'url(\'' . $hook_target , $response);
        // 替换 http绝对引用 为 本网址的相对引用
        $http_abs_ref = 'href="' . $_SERVER['PHP_SELF'] . '?url=http';
        $response = preg_replace('/href=\"http/i', $http_abs_ref , $response);

        return $response;
    }
    public static function go($url, $postdata='',$mode="native")
    {
        if(function_exists("curl_init")){
            return self::Post_CURL($url, $postdata,$mode);
        }
        else
        {
            return self::Post_FILE_GET_CONTENTS($url, $postdata);
        }
    }

    private static function Post_CURL($url, $postdata=null,$mode="native"){
        self::$errMsg="";

        self::$response="";
        self::$header="";
        if ( !$url ) {

            // Passed url not specified.
            $contents = 'ERROR: url not specified';
            $status = array( 'http_code' => 'ERROR' );

        } else if ( !preg_match( self::$CURL_valid_url_regex, $url ) ) {

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
            if ( self::$CURL_SendCookie ) {
                $cookie = array();
                foreach ( $_COOKIE as $key => $value ) {
                    $cookie[] = $key . '=' . $value;
                }
                if ( self::$CURL_SendSession ) {
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

            curl_setopt( $ch, CURLOPT_USERAGENT, self::$CURL_user_agent ? self::$CURL_user_agent : @$_SERVER['HTTP_USER_AGENT'] );

            $getresponse = curl_exec($ch);
            list( $header, $contents ) = preg_split( '/([\r\n][\r\n])\\1/', $getresponse, 2 );



            if (curl_getinfo($ch, CURLINFO_HTTP_CODE) == '200') {
                $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
                self::$header = substr($getresponse, 0, $headerSize);
                Self::$response = substr($getresponse, $headerSize);
            }

            $status = curl_getinfo( $ch );

            curl_close( $ch );
        }

        // Split header text into an array.
        $header_text = preg_split( '/[\r\n]+/', $header );

        if ( $mode == 'native') {
            if ( !self::$CURL_enable_native) {
                $contents = 'ERROR: invalid mode';
                $status = array( 'http_code' => 'ERROR' );
            }

            // Propagate headers to response.
            /* foreach ( $header_text as $header ) {
                 header( $header );
             }
 */
            return $contents;

        } else {

            // $data will be serialized into JSON data.
            $data = array();

            // Propagate all HTTP headers into the JSON data object.
            if (self::$CURL_full_headers) {
                $data['headers'] = array();

                foreach ( $header_text as $header ) {
                    preg_match( '/^(.+?):\s+(.*)$/', $header, $matches );
                    if ( $matches ) {
                        $data['headers'][ $matches[1] ] = $matches[2];
                    }
                }
            }

            // Propagate all cURL request / response info to the JSON data object.
            if ( self::$CURL_full_status) {
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
            $jsonp_callback = self::$CURL_enable_jsonp && isset(self::$CURL_CallBack) ? self::$CURL_CallBack : null;

            // Generate JSON/JSONP string
            $json = json_encode( $data );

            return $jsonp_callback ? "$jsonp_callback($json)" : $json;

        }
    }


    private static function Post_FILE_GET_CONTENTS($url, $post = null)
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
        self::$response=file_get_contents($url, false, stream_context_create($context));
        return self::$response;
    }
}


ob_start();

@$URL=$_REQUEST['url'];



if(!empty($URL))
{

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

//获取数据
    DataTransport::go($URL,$postArray);

//处理数据

    foreach ( preg_split( '/[\r\n]+/', DataTransport::$header ) as $headertext  ) {
        header($headertext);
    }
//Hook所有url
    DataTransport::$response = DataTransport::hook_url($URL, DataTransport::$response);
    print(DataTransport::$response);

//删除临时文件
    if(!empty($_FILES)){
        foreach($_FILES as $key=>$value){
            unlink($value['name']);
        }
    }
} else{
    echo 'url为空';
}

ob_end_flush();

?>
