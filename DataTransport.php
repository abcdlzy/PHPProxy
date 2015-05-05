<?php
/**
 * Created by PhpStorm.
 * User: abcdlzy
 * Date: 14/12/3
 * Time: 下午6:13
 *
 * build on https://github.com/cowboy/php-simple-proxy/
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

            if ( $postdata!=null ) {
                curl_setopt( $ch, CURLOPT_POST, true );
                @curl_setopt( $ch, CURLOPT_POSTFIELDS, $postdata );
            }

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
?>