<?php
namespace app\index\controller;
use think\Controller;
use think\Session;

class Index extends Controller
{
    private $redis;
    private $userId;
    public function __construct(){
        //获取session信息      
        $this->user();
    }
    /**
     * 判断用户是否登陆
     * wq
     * 2017年5月29日2:27:17
     */
    private function user(){
        if(Session::has('userId')){
            $this->userId = Session::get('userId');
        }else{
            $this->userId = null;
        }
        $this->assign('userId',$this->userId);
    }
    public function index2(){
        $this->redis = new \Redis();
        $this->redis->connect('127.0.0.1',6379);
        $this->redis->info();
        $this->redis->set('aaa',"333");
        echo $this->redis->get('aaa');
        $this->redis->close();
    }
    public function index()
    {
        return $this->fetch('index');
    }
    /**
     * AJAX获取所有该网站的链接，便于注入
     */
    public function get_mess(){
        //获取链接
//         /* $base_url = 'http://demo.niushop.com.cn/shop/helpcenter/index';
//         $ch = curl_init();
//         curl_setopt ($ch, CURLOPT_URL, $base_url);
//         curl_setopt ($ch, CURLOPT_RETURNTRANSFER, 1);
//         curl_setopt ($ch, CURLOPT_CONNECTTIMEOUT,10);
//         $dxycontent = curl_exec($ch); 
//         //带参数链接
//        // $result = preg_match_all('/(?<=a.href=")(.*?)(")/i', $dxycontent, $res_array);
//         //不带参数链接
//         $result = preg_match_all('/(?<=href=")[\w\d\.:\/]*/', $dxycontent, $res_array);
//         dump($res_array);  */
        $url = $_POST['url'];
        $is_base = true;
        $this->redis = new \Redis();
        $this->redis->connect('127.0.0.1',6379);
        //如果redis库有内容或者第一次
        while($this->redis->lSize('url_list') >0 || $is_base == true){
            //运行curl函数
            //获取其中的链接
            $curl = curl_init($url);
            //设置参数
            curl_setopt($curl,CURLOPT_RETURNTRANSFER,1);
            curl_setopt($curl,CURLOPT_HEADER,0);
            $res = curl_exec($curl);
            //不带参数的链接
            $result = preg_match_all('/(?<=href=")[\w\d\.:\/]*/', $res, $res_array);
            //筛选获取的结果
            if ($result >0){
                $result = array_unique($result);
            }else{
                curl_close($curl);
                continue;
            }            
            //带参数链接
            $result2 = preg_match_all('/(?<=a.href=")(.*?)(")/i', $res, $res_array2);            
            curl_close($curl); 
            //组掉最后一位多余的冒号
            $this->TrimArray($result2);
            //第一次筛选（去掉重复的）
            foreach($res_array2[1] as $key=>$array){
                $aaa = explode('?', $array);
                if(in_array($aaa[0], $result) == true){
                  $result4[$key]['url'] = $array;
                  $result4[$key]['base_url'] = $aaa[0];
                }
                $result3[$key]['url'] = $array;
                $result3[$key]['base_url'] = $aaa[0];
            }
            //将所有结果插入仅数据库，将所有链接存入redis库中
            foreach ($result3 as $key => $value){
                $data = array(
                    'base_url' => $url,
                    'url1' => $value['base_url'],
                    'url2' => $value['url']
                );
                db('url')->insert($data);
            }
            
            //批量入redis库
            foreach($result4 as $array){
                if($this->redis->sIsMember('url_list2', $array['url']) == FALSE){
                    $this->redis->lPush('url_list', $array['url']);
                }                
            }            
            //获取redis队列内容
            $url = $this->redis->lPop('url_list');
            $this->redis->sAdd('url_list2', $url);
            if($is_base == true && $result3 == null){
                $is_base = false;
            }
        }
        $all_url = $this->redis->sMembers('url_list2');
        $this->redis->close();
    }
    
    /**
     * 去掉数组中字符串的最后一位
     * @param unknown $Input
     * @return string
     */
    private function TrimArray($Input){
    if (!is_array($Input))
        return trim($Input);
    return array_map('TrimArray', $Input);
    }
}
