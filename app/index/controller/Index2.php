<?php
namespace app\index\controller;
use think\Controller;
use think\Session;

class Index2 extends Controller{
    private $userId;
    private $userName;
    private $redis;
    private $is_admin = 0;
    /**
     * 判断用户是否登陆
     * wq
     * 2017年5月29日2:27:17
     */
    private function user(){
        if(Session::has('userId')){
            $this->userId = Session::get('userId');
            $this->getUserMess();
           // $this->getDefaultMess();
        }else{
            $this->userId = null;
        }
       // dump($this->userId);
        $this->assign('is_admin',$this->is_admin);
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
        $this->is_admin = $res['is_admin'];
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
        Session::delete('username');
        Session::delete('is_admin');
    }
    /**
     * 获取一进去首页默认显示的链接数量
     * wq
     * 2017年6月8日0:43:34
     */
    public function getDefaultMess(){
        //查询已经爬取链接的网络个数
        $base_url_count = db('totle_url')->where('userid',$this->userId)->count();
        //查询找寻后台的网站个数
        $admin_count = db('crawle_link')
            ->where('userid',Session::get('userId'))
            ->where('Host_type',2)
            ->count();
        //查询进行SQL自动注入的链接个数
        $url_count = db('url')->where('userid',$this->userId)->count();
        //存在诸如漏洞的个数
        $test = db('url')->where('userid',$this->userId)->count();
        $this->assign(array(
            'base_url_count' => $base_url_count,
            'admin_count' => $admin_count,
            'url_count' => $url_count,
            'test' => $test
        ));
    }
    /**
     * 爬取指定网址链接(查询链接是否能够访问)
     * wq
     * 2017年6月8日0:58:57
     */
    public function get_base_url() {
//        $url = $_POST['url'];  
        $url = 'http://www.baidu.com';
        $url_list = parse_url($url);
 //       dump($url_list);
        if($url_list['scheme'] == '' || isset($url_list) == false){
            $url = 'http://'.$url;
        }
        //爬取链接的过程
        $ch = curl_init($url);
        curl_setopt ($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch,CURLOPT_HEADER,0);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        $res = curl_exec($ch);
        $httpcode = curl_getinfo($ch);
        curl_close($ch);
        //返回的状态码
        $code = $httpcode['http_code'];
        $cookies = '';
        $server = '';        
        //cookie信息和server信息(必须能争取访问才行)
        $cookieandserver = get_headers($url);       
        foreach ($cookieandserver as $key => $value){
            if (strpos($value, 'Server') !== false){
                $server = trim(str_replace('Server:', '', $value));
            }
            if (strpos($value, 'Set-Cookie') !== false){
                $cookie = trim(str_replace('Set-Cookie:', '', $value));
                $cookies .= $cookie;
            }
        }
        //主机信息        
        $data = array(
            'userid' => Session::get('userId'),
            'host' => $url_list['host'],
            'start_link' => $url,
            'ipconfig' => $httpcode['primary_ip'],
            'content_type' => $httpcode['content_type'],
            'Host_type' => 1,
            'cookie' => $cookies,
            'service' => $server,
            'http_code' => $code
        );
        $tid = db('crawle_link')->insert($data);
        if($tid == 1){
            $now_tid = db('crawle_link') -> max('id');
        }
      //  dump($code);
        if( $code >= 400):
            $res = array(
                'code' => $code,
                'mess' => '访问失败，请重新输入域名',
                'res' => -1
            );
        elseif($code == 204 || $code == 205):
            $res = array(
                'code' => $code,
                'mess' => '网站内容为空，请重新输入域名',
                'res' => -1
            );
        elseif($code == 305):
            $res = array(
                'code' => $code,
                'mess' => '必须使用代理域名，请重新输入域名或使用代理访问',
                'res' => -1
            );
        elseif($code == 301):
            $url = $httpcode['redirect_url'];            
            $res = $this->do_get_base_url($url, $now_tid, $url_list['host']);
        else:
            $res = $this->do_get_base_url($url, $now_tid, $url_list['host']);
        endif;
        dump($res) ;
    }
    /**
     * 爬虫开始
     * wq
     * 2017年6月8日11:12:50
     */
    public function do_get_base_url($url, $now_tid, $base_host){
        $base_url = $url;
        $is_base = true;
        $this->redis = new \Redis();
        $this->redis->connect('127.0.0.1',6379);
        while($is_base == true || $this->redis->lSize('url_list') >0 ):
            //运行curl函数
            //获取其中的链接
            $curl = curl_init($url);
            curl_setopt($curl,CURLOPT_RETURNTRANSFER,1);
            curl_setopt($curl,CURLOPT_HEADER,0);
            curl_setopt($curl, CURLOPT_TIMEOUT, 10);
            $res = curl_exec($curl);
            if($res === false):
                if(curl_errno($curl) == CURLE_OPERATION_TIMEDOUT)  
                {  
                    continue;  
                }  
            endif;
            $http_code = curl_getinfo($curl);
            curl_close($curl);
            //
            if($is_base == true):
                $now_mess = parse_url($url);  
                if(!(isset($now_mess['path']))):
                    $now_mess['path'] = '';
                endif;
                $data = array(
                    't_id' => $now_tid,
                    'scheme' => $now_mess['scheme'],
                    'host' => $now_mess['host'],
                    'path' => $now_mess['path'],
                    'query' => '',
                    'totle_url' => $url,
                    'http_code'=>$http_code['http_code'],
                    'content_type'=>$http_code['content_type']
                );
            else:
                $now_mess = parse_url($url);
                if(!(isset($now_mess['path']))):
                    $now_mess['path'] = '';
                endif;
                db('minute_url')
                    ->where('path',$now_mess['path'])
                    ->where('t_id',$now_tid)
                    ->update(['http_code'=>$http_code['http_code'],'content_type'=>$http_code['content_type']]);
            endif;
            //不带参数的链接
//             $result = preg_match_all('/(?<=href=")[\w\d\.:\/]*/', $res, $res_array);
            //带参数链接
            $result2 = preg_match_all('/(?<=a.href=")(.*?)(")/i', $res, $res_array2);
            //筛选获取的结果(去掉相同的)
//             if ($result >0){
//                 $result = array_unique($result);
//             }  
            if($result2[1] >0){
                $result2[1] = array_unique($result2[1]);
            }
            //将已筛选的链接存入数据库和redis库中
            foreach($res_array2[1] as $key=>$array){
                if($array == 'javascript:;'){
                    continue;
                }
                if($array == 'javascript:void(0)'){
                    continue;
                }
                if($array == 'window.history.go(-1)'){
                    continue;
                }
                $url_list = parse_url($array);
                //设置协议名(如果不存在设为http)
                if(!(isset($url_list['scheme']))):             
                    $url_list['scheme'] = 'http';
                endif;
                //设置主机名  如果没有设为基础主机名
                if(!(isset($url_list['host']))):
                    $url_list['host'] = $base_host;
                endif;
                if(!(isset($url_list['path']))):
                    $url_list['path'] = '';
                endif;
                //若果没有参数设为空
                if(!(isset($url_list['query']))):
                    $url_list['query'] = '';
                endif;
                //拼装为完整url
                if ($url_list['query'] == ''):
                    $totle_url = $url_list['scheme'].'://'.$url_list['host'].$url_list['path'];
                else:
                    $totle_url = $url_list['scheme'].'://'.$url_list['host'].$url_list['path'].'?'.$url_list['query'];
                endif;
                //先将当前链接存入到数据库中
                $data = array(
                    't_id' => $now_tid,
                    'scheme' => $url_list['scheme'],
                    'host' => $url_list['host'],
                    'path' => $url_list['path'],
                    'query' => $url_list['query'],
                    'totle_url' => $totle_url
                );
                db('minute_url')->insert($data);
                //如果当前的host与基础的host相同 则存入redis库中
                 if($url_list['host'] == $base_host):
                     //当集合中没有相同的path
                     if($this->redis->sIsMember('has_path', $url_list['path']) == false):
                        //将该完整路径存进redis库中
                         $this->redis->sAdd('has_path', $url_list['path']);
                         $this->redis->lPush('url_list', $totle_url);
                     endif;
                 endif;
            }
            //获取新链接进行爬取
            $url = $this->redis->lPop('url_list');
            if($is_base == true){
                $is_base = false;
            }
        endwhile;
         while($this->redis->sCard('has_path') !=null):
           $this->redis->sPop('has_path');
         endwhile;
        $res = array(
            'mess' => '爬取成功',
            'res' => 1
        );
        return $res;
    }
    /**
     * 测试输入结果
     */
    public function test(){
//         $this->redis = new \Redis();
//         $this->redis->connect('127.0.0.1',6379);
//         $this->redis->set('aaa','111');
//         $this->redis->get('aaa');
//         $url = 'http://shop.linglingke.com/shop/index';
//         $curl = curl_init($url);
//         curl_setopt($curl,CURLOPT_RETURNTRANSFER,1);
//         curl_setopt($curl,CURLOPT_HEADER,0);
//         $res = curl_exec($curl);
//         curl_close($curl);
//         $result = preg_match_all('/(?<=a.href=")[\w\d\.:\/]*/i', $res, $res_array);
//         //带参数链接
//         $result2 = preg_match_all('/(?<=a.href=")(.*?)(")/i', $res, $res_array2);
//         dump($res_array);
//         dump($res_array2);
           $linshi_url = http_build_url("http://user@www.example.com/pub/index.php?a=b#files",
            array(
                'scheme' => 'http',
                'host' => '127.0.0.1',
                'path' => 'aaa/bbb',
                'query' => ''
            ),
            HTTP_URL_STRIP_AUTH | HTTP_URL_JOIN_PATH | HTTP_URL_JOIN_QUERY | HTTP_URL_STRIP_FRAGMENT
            );
           dump($linshi_url);
    }
    /**
     * 判断输入的爬取链接
     */
    public function set_admin_url(){
//         $url = 'http://localhost/bysj';
//         $true_http_code = '200,100';
//         $false_http_code = '404,302';
//         $time_out = 10;
//         $admin_ceshi_number = 10;
//         $admin_language = 'asp,php';
        $url = $_POST['get_admin_url'];  
           //正确状态码
        $true_http_code = $_POST['admin_success_code'];
        //错误状态码
        $false_http_code = $_POST['admin_error_code'];
        //服务器语言
        $admin_language = $_POST['admin_language'];
        //测试个数
        $admin_ceshi_number = $_POST['admin_ceshi_number'];
       // 设置超时时间
        $time_out = $_POST['admin_time_out'];
        if($time_out > 30){
            $time_out = 30;
        }
        $url_list = parse_url($url);
 //       dump($url_list);
        if($url_list['scheme'] == '' || isset($url_list) == false){
            $url = 'http://'.$url;
        }
        //爬取链接的过程
        $ch = curl_init($url);
        curl_setopt ($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch,CURLOPT_HEADER,0);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        $res = curl_exec($ch);
        $httpcode = curl_getinfo($ch);
        curl_close($ch);
        //返回的状态码
        $code = $httpcode['http_code'];
        $cookies = '';
        $server = '';        
        //cookie信息和server信息(必须能争取访问才行)
        $cookieandserver = get_headers($url);       
        foreach ($cookieandserver as $key => $value){
            if (strpos($value, 'Server') !== false){
                $server = trim(str_replace('Server:', '', $value));
            }
            if (strpos($value, 'Set-Cookie') !== false){
                $cookie = trim(str_replace('Set-Cookie:', '', $value));
                $cookies .= $cookie;
            }
        }
        //主机信息        
        $data = array(
            'userid' => Session::get('userId'),
            'host' => $url_list['host'],
            'start_link' => $url,
            'ipconfig' => $httpcode['primary_ip'],
            'content_type' => $httpcode['content_type'],
            'Host_type' => 2,
            'cookie' => $cookies,
            'service' => $server,
            'http_code' => $code
        );
        $tid = db('crawle_link')->insert($data);
        if($tid == 1){
            $now_tid = db('crawle_link') -> max('id');
        }
      //  dump($code);
        if( $code >= 400):
            $res = array(
                'code' => $code,
                'mess' => '访问失败，请重新输入域名',
                'res' => -1
            );
        elseif($code == 204 || $code == 205):
            $res = array(
                'code' => $code,
                'mess' => '网站内容为空，请重新输入域名',
                'res' => -1
            );
        elseif($code == 305):
            $res = array(
                'code' => $code,
                'mess' => '必须使用代理域名，请重新输入域名或使用代理访问',
                'res' => -1
            );
        elseif($code == 301):
            $url = $httpcode['redirect_url'];            
            $res = $this->get_admin_url($url, $now_tid, $time_out, $admin_ceshi_number, $true_http_code, $false_http_code, $admin_language);
        else:
            $res = $this->get_admin_url($url, $now_tid, $time_out, $admin_ceshi_number, $true_http_code, $false_http_code, $admin_language);
        endif;
        return $res;
    }
    /**
     * 找寻后台链接
     * wq
     * 2017年6月8日15:31:16
     */
    public function get_admin_url($url, $tid, $time_out,$admin_ceshi_number,$true_http_code,$false_http_code,$admin_language){
        $true_http_code_list = explode(',', $true_http_code);
        $false_http_code_list = explode(',', $false_http_code);
        $admin_language_list = explode(',', $admin_language);
        $where = '';
        //添加查询where条件
        if (count($admin_language_list) > 1){
            foreach ($admin_language_list as $key => $value){
                if($key == 0){
                    $templete = $this->languageToCode($value);
                    $where = $where.'type='.$templete;
                }else{
                    $templete = $this->languageToCode($value);
                    $where = $where.' OR type='.$templete;
                }
            }
        }else{
            $templete = $this->languageToCode($admin_language_list);
            $where = 'type = '.$templete;
        }
        //拼装的语言
        $base_string = db('findadminstring')
             ->where($where)
             ->limit($admin_ceshi_number)
             ->select();
        foreach ($base_string as $key => $aaa){
            $url2 ='';
            //拼接字符串
            $url2 = $url.$aaa['admin_string'];
            //执行curl函数
            $ch = curl_init($url);
            curl_setopt ($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch,CURLOPT_HEADER,0);
            //设置超时时间
            curl_setopt($ch, CURLOPT_TIMEOUT, $time_out);
            curl_exec($ch);
            $httpcode = curl_getinfo($ch);            
            curl_close($ch);
            //正确错误状态码
            $is_true = 0;
            if(in_array($httpcode['http_code'], $true_http_code_list) == true){
                $is_true = 1;
            }
            if (in_array($httpcode['http_code'], $false_http_code_list) == true){
                $is_true = 2;
            }
            $data = array(
                'pid' => $tid,
                'totle_url' => $url2,
                'http_code' => $httpcode['http_code'],
                'is_true' => $is_true
            );
            db('findadminlog')->insert($data);
            //返回显示
            $code['data'][$key]['url'] = $url2;
            $code['data'][$key]['code'] = $httpcode['http_code'];
            $code['data'][$key]['is_true'] = $is_true;
        }
        $code['res'] = 1;
        return $code;
    }
    /**
     * 通过输入的语言来重写状态
     * wq
     * 2017年6月11日12:11:37
     */
    public function languageToCode($language){
        if($language == 'asp'):
            $res = 1;
        elseif($language == 'php'):
            $res = 2;
        elseif($language == 'html'):
            $res = 3;
        else:
            $res = 4;
        endif;
        return $res;
    }
    /**
     * 获取已抓取的网站的信息
     * wq
     * 2017年6月11日16:51:34
     */
    public function get_admin_has_url() {
       $res = db('crawle_link')
            ->where('Host_type',2)
            ->where('userid',Session::get('userId'))
            ->select();
       foreach ($res as $key => $value){
           $res[$key]['success_code_num'] =
            db('findadminlog')
                ->where('is_true',1)
                ->where('pid',$value['id'])
                ->count();
           $res[$key]['fail_code_num'] =
            db('findadminlog')
                ->where('is_true',2)
                ->where('pid',$value['id'])
                ->count();
            $res[$key]['other_code_num'] =
            db('findadminlog')
            ->where('is_true',0)
            ->where('pid',$value['id'])
            ->count();
       }
       return $res;
    }
    /**
     * 根据pid获取后台获取信息
     * wq
     * 2017年6月11日20:41:24
     */
    public function get_admin_has_url_log(){
       $p_id = $_POST['pid']; 
       $res = db('findadminlog')
                ->where('pid',$p_id)
                ->select();
       return $res;
    }
        
    /**
     * 根据id获取爬取网址的信息
     * wq
     * 2017年6月12日11:57:52
     */
    public function get_has_url(){
       $t_id = $_POST['t_id'];
       $res = db('minute_url')
            ->where('t_id',$t_id)
            ->select();
       return $res;
    }
}