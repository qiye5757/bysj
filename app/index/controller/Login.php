<?php
namespace app\index\controller;
use think\Controller;
use think\Session;
use app\index\controller\coderClass;

class Login extends Controller{
    private $code;
    /**
     * 登陆页面
     * wq
     * 2016年12月29日0:51:28
     */
    public function login(){
        //Session::set('userId',1); 
        return $this->fetch('login');
    }
    /**
     * 登陆功能
     * wq
     * 2016年12月30日22:00:34
     */
    public function doLogin(){
        $username = $_POST['username'];
        $passwd = $_POST['password'];
        $coder = $_POST['code'];
        $Dcoder = Session::get('coder');
        if(strcasecmp($coder,$Dcoder) == 0){
            $res = db('system_user')
                    ->where('username','eq',$username)
                    ->where('passwd','eq',$passwd)
                    ->select();
            if($res != null){
                $data = array(
                    'res' => true
                ); 
                Session::set('userId',$res[0]['id']);
                Session::set('username',$res[0]['username']);
                Session::set('is_admin',$res[0]['is_admin']);
            }else{
                $data = array(
                    'res' => false,
                    'error' => '没有此登陆信息'
                );
            }
        }else{
            $data = array(
                'res' => false,
                'error' => '验证码输入错误'
            );
        }
        return $data;
    }
    /**
     * 注册页 面
     * wq
     * 2017年5月29日0:51:57
     */
    public function register(){
        //dump(db('system_user')->find());
        return $this->fetch('register');
    }
    /**
     * 注册功能
     * wq
     * 2017年1月29日23:56:06
     */
    public function doRegister(){
       $username = $_POST['username'];
       $password = $_POST['password'];
       $telephone = $_POST['telephone'];
       $register_time = date('y-m-d h:i:s',time());
       $coder_post = $_POST['coder'];
       $coder = Session::get('coder');
       if(strcasecmp($coder,$coder_post)==0){
           $data = array(
               'username' => $username,
               'passwd' => $password,
               'tel' => $telephone,
               'register_time' => $register_time
           );
           $res = db('system_user')->insert($data);
       }else{
           $res = -1;
       }       
       return $res;
    }
    /**
     * 检测用户名是否存在
     * wq
     * 2017年5月29日23:56:32
     */
    public function isHasUser() {
        $user = $_POST['username'];
        $user_list = db('system_user')->select();
        $is_has = false;
        if(count($user_list) > 1){
            foreach ($user_list as $list){
                if($user == $list['username']){
                    $is_has = true;
                }
            } 
        }else if (count($user_list) == 1){
            if($user == $user_list[0]['username']){
                $is_has = true;
            }
        }else{
            
        }        
        return $is_has;
    }
    /**
     * 设置验证码页面
     * wq
     * 2017年6月2日22:09:21
     */
    public function vcode(){
        $this->code = new coderClass(80, 30, 4);
        Session::set('coder',$this->code->getcode());
        return $this->code->outimg();
    }
}