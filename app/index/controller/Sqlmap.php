<?php

class sqlmapapi {
    private $adminid='';
    private $sqlmapapi=SQLMAPAPI;
    private $tasknumber=0;
    function __construct($adminid=null) {
        if($adminid!=null){
            $this->adminid=$adminid;
        }

        $this->AutoTask();
        return 0;
    }
    //自动处理所有任务
    function AutoTask(){
        $tasklistarr=  $this->getTasklist();
        foreach ($tasklistarr as $taskid) {
            //查询结果并入库
            $this->Task2db($taskid);
        }
        return TRUE;
    }
    
    function getTasklist($adminid=null){
        if($adminid==null){
            $adminid=$this->adminid;
        }
        $jsonres=$this->doGet("/admin/".$this->adminid."/list");
        $jsonobj= json_decode($jsonres);
        $tasklist=$jsonobj->tasks;
        $tasknumber=$jsonobj->tasks_num;
        $this->tasknumber=$tasknumber;
        print_r($tasklist);
        return $tasklist;
    }
    function flushTask($adminid=null){
        if($adminid==null){
            $adminid=$this->adminid;
        }
        $jsonres=$this->doGet("/admin/".$this->adminid."/list");
        $res=  json_decode($jsonres);
        if($res['success']==true){
            return TRUE;
        }else{
            return FALSE;
        }
    }

    function Task2db($taskid){

        $jsonres=  $this->doGet("/scan/".$taskid."/status");
        print_r($jsonres);
        $jsonobj=  json_decode($jsonres);
        $taskstatus=$jsonobj->status;
        if($taskstatus=='terminated'){
            $jsonres=  $this->doGet("/scan/".$taskid."/data");
            $jsonobj=  json_decode($jsonres);
            $data=$jsonobj->data;
            if($data==null || empty($data)||count($data)==0){
                $this->delTask($taskid);
                return TRUE;
            }
            $error=$jsonobj->error;
            $taskoptionlist=  $this->getOptionList($taskid);
            $url=$taskoptionlist->url;
            $urlarr=parse_url($url);
            $schema=$urlarr['scheme'];
            $host=$urlarr['host'];
            $port=0;
            if(!isset($urlarr['port'])){
                if($urlarr['scheme']=='http'){
                    $port=80;
                }elseif($urlarr['scheme']=='https'){
                    $port=443;
                }
            }else{
                $port=$urlarr['port'];
            }
            $cookie=$taskoptionlist->cookie;
            $headers=$taskoptionlist->headers;
            $postdata=$taskoptionlist->data;
            $uasplit=split("User-Agent:", $headers);
            $ua=$uasplit[1];
            $taskscandata=  serialize($data);
            $taskscanlog=  $this->getTaskScanLog($taskid);
            $taskerror=  serialize($error);
            $save2dbres=$this->save2Db($host, $port, $schema, $url, $cookie, $postdata,$ua, serialize($taskoptionlist), $taskscandata, serialize($taskscanlog), $taskerror);
            if($save2dbres){
                $this->delTask($taskid);
                return TRUE;
            }else{
                return FALSE;
            }
            
        }elseif($taskstatus=='not running'){
            $this->delTask($taskid);
            return TRUE;
        }elseif ($taskstatus=="running") {
            return FALSE;
            
        }
        
    }
    function save2Db($host,$port,$schema,$url,$cookie,$postdata,$ua,$taskoptiondata,$taskscandata,$taskscanlog,$taskerror){
        global $mysqli;
        var_dump(mysqli_error($mysqli));
        if($num>0){
            return TRUE;
        }else{
            return FALSE;
        }
        
    }
    function getOptionList($taskid){
        $jsonres=  $this->doGet("/option/".$taskid."/list");
        $jsonobj=  json_decode($jsonres);//生成数组
        return $jsonobj->options;
    }
    function getTaskScanLog($taskid){
        $jsonres=  $this->doGet("/scan/".$taskid."/log");
        $jsonobj=  json_decode($jsonres);
        return $jsonobj->log;
    }
    function getUrl($taskid){
        
    }
    function delTask($taskid){
        $jsonres=$this->doGet('/task/'.$taskid."/delete");
        $jsonobj=  json_decode($jsonres);
        if($jsonobj->success=='true'){
            return TRUE;
        }else{
            return FALSE;
        }
    }
    
    
    function __get($name) {
        return $this->$name;
    }
    function __set($name, $value) {
        $this->$name=$value;
    }
    function doGet($api){
        $options = array(
                CURLOPT_URL =>  $this->sqlmapapi.$api ,
                CURLOPT_POST=>false,
                CURLOPT_RETURNTRANSFER=>true,
                CURLOPT_HEADER=>false,
                CURLOPT_USERAGENT=>'Mozilla/5.0 (Windows NT 5.1) AppleWebKit/537.17 (KHTML, like Gecko) Chrome/24.0.1312.57 Safari/537.17',

        );  
       $myres=$this->mycurl($options);
       return  $myres;
    }
    function doPost($api,$body){
        $header = array(
            'Content-Type: application/json',
        );
        $options = array(
                CURLOPT_URL =>$this->sqlmapapi.$api ,
                CURLOPT_POST=>true,
                CURLOPT_RETURNTRANSFER=>true,
                CURLOPT_POSTFIELDS=>$body,
                CURLOPT_HEADER=>$header,
                CURLOPT_USERAGENT=>'Mozilla/5.0 (Windows NT 5.1) AppleWebKit/537.17 (KHTML, like Gecko) Chrome/24.0.1312.57 Safari/537.17',
        );
        return $this->mycurl($options);
    }
    function mycurl($options){
        $c=curl_init();
        curl_setopt_array($c,$options);
        $result=curl_exec($c);
        curl_close($c);
        return $result;
    }

}
