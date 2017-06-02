<?php
namespace app\index\controller;
use think\Controller;
use think\Session;

class Index2 extends Controller{
    private $userId;
    private $userName;
    /**
     * 判断用户是否登陆
     * wq
     * 2017年5月29日2:27:17
     */
    private function user(){
        if(Session::has('userId')){
            $this->userId = Session::get('userId');
            $this->getUserMess();
        }else{
            $this->userId = null;
        }
       // dump($this->userId);
       $this->assign('userId',$this->userId);
    }
    /**
     * 根据id获取用户信息
     * wq
     * 2017年5月29日3:00:39
     */
    private function getUserMess(){
        $res = db('system_user')->where('id',$this->userId)->find();
        $this->userName = $res['username'];
        //dump($this->userName);
        $this->assign('username',$this->userName);
    }
    /**
     * 主显示页面
     * wq
     * 2017年5月29日2:28:42
     */
    public function Index(){
        $this->user();
        return $this->fetch('index');
    }
    /**
     * 用户退出登陆
     * wq
     * 2017年5月29日22:20:22
     */
    public function layout() {
        Session::delete('userId');
    }
}