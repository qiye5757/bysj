<?php
namespace app\index\controller;
use think\Controller;

class coderClass extends Controller{
         private $width;    //宽度
		 private $height;   //高度
		 private $num;      //数量
         private $code;      //验证码
		 private $img;      //图像的资源

		//构造方法
	     function __construct($width,$height,$num){
		    $this->width = $width;
			$this->height = $height;
			$this->num = $num;
			$this->code = $this->createcode();
		 }

		 //获取字符的验证码
		 function getcode(){
		      return $this->code;
		 }

		 //输出图像
		 function outimg(){
		         //创建背景(颜色，大小，边框)
                 $this->createback();
				 //画字(大小，字体颜色)
                 $this->outstring();
				 //干扰元素(点，线条)
                 $this->setdisturbcolor();
				 //输出图像
				 $this->printimg();
		 }
        


		 //创建背景
         private function createback(){
			  //创建资源
		      $this ->img = imagecreatetruecolor($this->width,$this->height);
			  //设置随机的背景颜色
			  $bgcolor = imagecolorallocate($this->img,rand(225,255),rand(225,255),rand(225,255));
			  //设置背景填充
			  imagefill($this->img,0,0,$bgcolor);
			  //边框的颜色和设置
			  $bordercolor = imagecolorallocate($this->img,0,0,0);
			  imagerectangle($this->img,0,0,$this->width-1,$this->height-1,$bordercolor);

		 }

		 //画字
         private function outstring(){
			for($i=0;$i<$this->num;$i++){
		       $color = imagecolorallocate($this->img,rand(0,125),rand(0,125),rand(0,125));
               $x = 5+$i*($this->width/$this->num);
			   $y = 1+ rand(1,4);
			   imagechar($this->img,4,$x,$y,$this->code{$i},$color);
			   }
		 }

		 //设置干扰元素
		 private function setdisturbcolor(){

			 // 画点
		       for($i=0;$i<100;$i++){
			       $color = imagecolorallocate($this->img,rand(0,255),rand(0,255),rand(0,255));
				   imagesetpixel($this->img,rand(1,$this->width-1),rand(1,$this->height-1),$color);
			   }  

			//画线条
			   for($i=0;$i<2;$i++){
				   $colors = imagecolorallocate($this->img,rand(125,200),rand(125,200),rand(125,200));
			       imagearc($this->img,rand(10,$this->width-10),rand(5,$this->height-5),rand(50,$this->width),rand(20,$this->height),rand(-50,150),rand(80,280),$colors);
			   } 
		 }
        
		 //输出图像
		 private function printimg(){
		      if (function_exists("imagegif")) {
                  header("Content-type: image/gif");
                  imagegif($this->img);
               } elseif (function_exists("imagejpeg")) {
                  header("Content-type: image/jpeg");
                  imagejpeg($this->img);
              } elseif (function_exists("imagepng")) {
                  header("Content-type: image/png");
                  imagepng($this->img);
             }  else {
                    die("No image support in this PHP server");
              } 
		 }

		 //生成验证码字符串
		 private function createcode(){
		    $codes = "3456789abcdefghijkmnpqrstuvwxyABCDEFGHIJKMNPQRSTUVWXY";
			$code = "";

			for($i = 0;$i< $this->num ; $i++){
			      $code .=$codes{rand(0,strlen($codes)-1)};
			}
			return $code;
		 }
         

		 //析构方法  用于自动销毁资源
		 function __destruct(){
		       imagedestroy($this->img);
		 }
}