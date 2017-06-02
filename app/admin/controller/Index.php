<?php
namespace app\admin\controller;
use think\Controller;
class Index extends Controller
{
    public function index()
    {
        $test=[
            [
			    "id"=>9952,
				"name"=>"qiye",
				"time"=>"2010"
			],
			[
			    "id"=>8888,
				"name"=>"qiye2",
				"time"=>"2011"
			],
			[
			    "id"=>7777,
				"name"=>"qiye3",
				"time"=>"2012"
			]
      ]
$a=array_column($test,"name");
$b=array_column($test,"name","id");
echo $a;
echo $b;
    }
}
