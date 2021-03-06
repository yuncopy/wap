<?php

/* 
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

class ContentsModel extends BlueModel
{
    protected  $table  = 'contents';  // 表名
    private $table_menus = 'menus';
    protected $write_mode = true;  // 使用读写数据库
    static public $data = null;
    static public $third = null;  // 热度前三名
    protected $filed_name = ["id","cid","thumb","title","authorid","text","click","time_length","seeds","created_at"];


    // 效率很低
    public function groupTop($top = 12){
        //获取数据
        if(self::$data == null){
            $table = $this->table;
            $sql = "SELECT * FROM {$table} a WHERE {$top} > (SELECT count(*) FROM {$table} WHERE cid=a.cid and click>a.click)  ORDER BY a.cid,a.click DESC;";
            $data = $this->query($sql)->fetchAll();
        }
        return self::$data;
    }
    
    //随机取出数据
    public function getRand($num =10,$cid){
        if($cid){
            $table = $this->table;
            $max = $this->max($table,"id",['cid'=>$cid]);
            $min = $this->min($table,"id",['cid'=>$cid]);
            $arr=array();
            while(count($arr) < $num){
                $arr[]=rand($min,$max);
                $arr=array_unique($arr);
            }
            $data= $this->select($table, $this->filed_name,[
                "id"=>$arr
            ]);
            return $data;
        }
    }
    
    // 取出一条数据
    public function getRow($id){
        return $this->get($this->table,$this->filed_name, ['id'=>$id]);
    }
    
    // 次数累加
    public function addClick($id){
        return $this->update($this->table, [
            "click[+]" => 1
        ],["id" => $id]);
    }

    //获取所有内容 （效率比较低）
    public function getContents($cid = null){
        
        $filed_name = $this->filed_name;
        if(is_array($cid) && !empty($cid)){
            $contents = $this->select($this->table,$filed_name,["cid"=>$cid]);
            if($contents){
                $sort_content = bluepay_array_sort($contents,'click','desc'); // 排序
                $new_content = $new_content_top = array();
                $top_content = 8;  // 前排名
                $row_num = $top_content/2;
                foreach ($sort_content as $value){
                    $new_content[$value['cid']][] = $value;
                }
                // 获取排名
                foreach ($new_content as $kk => $vv){
                   $slice_array = array_slice ( $vv ,  0 ,  $top_content ); // 取出一段数据
                   $chunk_array = array_chunk($vv,$row_num);//分割数组
                   $new_content_top[$kk] = $chunk_array; // 组合数据
                }
                $contents = $new_content_top;
            }
        }else{
            $contents = $this->select($this->table,$filed_name);
        }
        return $contents;
    }
    


    // 前三视频内容
    public function videoTop3($cid){
        //获取数据
        if(self::$third == null){
            $table = $this->table;
            self::$third = $this->select($table, $this->filed_name,[
                "cid"=>$cid,
                "ORDER" => [
                    "click" => "DESC"
                ],
                "LIMIT" => [0, 3]
            ]);
        }
        return self::$third;
    }
    
    //幻灯片 ( Cricket ) 
    public function getSlideshow($cid,$site=1){
        $filed_name = $this->filed_name;
        if(is_array($cid) && !empty($cid)){
            $contents = $this->select($this->table,$filed_name,[
                "cid"=>$cid,
                "ORDER" => [
                    "click" => "DESC"
                ],
                "LIMIT" => [0, 16]]);
            if($site== 1){
                $columns = 4; 
                if($contents){
                   $content_columns = array_chunk($contents, floor(count($contents) / $columns));
                   array_pop ( $content_columns );
                }
            }else if($site== 2){
                $content_columns = $contents;
            }
            
        }
        return $content_columns;
    }
    
    
    //按照类别进行数据进行分页处理
    public function getCategory($cid,$p,$num=10,$col = 6,$g=false){
        
        $filed_name = $this->filed_name;
        if(!empty($cid)){
            //$count = $this->count($this->table);
            $count = count($this->select($this->table,$filed_name,["cid"=>$cid]));  // 计算页码
            $everypage = isset($num) ? $num : 10;//每页显示条数
            $tatolpage = ceil($count/$everypage);//总页数
            if($p > $tatolpage){ //最大值控制
                $p=intval($tatolpage);
            }
            $page = isset($p) && !empty($p)?$p:1;   //第几页
            $startpage = ($page-1)*$everypage;      //第几页，当前页，每页显示数关系
            //$sql  = "SELECT * FROM page limit {$startpage},{$everypage}"; //准备SQL语句
            $contents = $this->select($this->table,$filed_name,["cid"=>$cid,"ORDER"=>['created_at'=>'DESC'],"LIMIT" => [$startpage, $everypage]]);
           // dd($this->debug());
            if($g){
              return ['contents'=>$contents,'tatolpage'=>(int)$tatolpage];  
            }else{
                // 分割数组  //dd($contents);
                $number = count($contents);
                $group_array= array();
                for($i=0;$i< $number;$i+=$col){
                   $col_array =  array_slice($contents, $i, $col);
                   $group_array[] = $col_array;
                }
                $contents = $group_array;
                $str = $this->pageInfo($p,$cid,$num,$tatolpage);
                return ['contents'=>$contents,'page'=>$str];
            }
        }
    }
    
    
    // 分页信息显示
    public function  pageInfo($p,$cid,$num,$tatolpage){
         // 处理页码
        if($p && $cid && $num && $tatolpage){
            
            $showpage = 5; //显示页码数
            $page = isset($p) && !empty($p)?$p:1;   //第几页
            $php_self = strstr($_SERVER['REQUEST_URI'], '?', TRUE);
            $k = ['c','p','n'];
            $str = '<nav aria-label="Page navigation"><ul class="pagination">';
            if($page > 1){
                $str .=	"<li><a href='{$php_self}?{$k[1]}=1&{$k[2]}={$num}&{$k[0]}={$cid}'>首页</a></li>";
                $prev =  $p-1;
                $str .= "<li><a href='{$php_self}?{$k[1]}={$prev}&{$k[2]}={$num}&{$k[0]}={$cid}'><上一页</a></li>";
            }else{
                $str .= "<li><a><span>首页</span></a></li>";
                $str .= "<li><a><span>上一页</span></a></li>";
            }

            //初始化页码数据
            $start = 1;
            $end = $tatolpage;
            //计算偏移量
            $pageoffset = ($showpage-1)/2;
            if($tatolpage > $showpage){
                if($p > $pageoffset +1){
                    $str.='<li><a>...</a></li>';
                }
                if($p > $pageoffset){
                    $start = $p-$pageoffset;
                    $end = $tatolpage > $p + $pageoffset?$p + $pageoffset:$tatolpage;//判断是否显示最后一页
                }else{
                    $start = 1;
                    $end = $tatolpage > $p ? $showpage : $tatolpage;
                }
                if($p+$pageoffset >$tatolpage){
                    $start = $start-($p+$pageoffset-$end);
                }
            }

            //显示页码
            for ($i=$start; $i <= $end ; $i++) {
                if($p==$i){
                    $str .="<li class='active'><a  href='{$php_self}?{$k[1]}={$p}&{$k[2]}={$num}&{$k[0]}={$cid}'>{$p}</a></li>";
                }else{
                    $str .="<li><a href='{$php_self}?{$k[1]}={$i}&{$k[2]}={$num}&{$k[0]}={$cid}'>{$i}</a></li>";
                }	
            }

            if($tatolpage > $showpage && $tatolpage > $p+$pageoffset){
                $str.='<li><a>...</a></li>';
            }
            if($page < $tatolpage ){           
                $ps = $p+1;
                $str .= "<li><a href='{$php_self}?{$k[1]}={$ps}&{$k[2]}={$num}&{$k[0]}={$cid}'>下一页></a></li>";
                $str .= "<li><a href='{$php_self}?{$k[1]}={$tatolpage}&{$k[2]}={$num}&{$k[0]}={$cid}'>尾页</a></li>";
            }else{
                $str .=	'<li><a><span>上一页</span></a></li>';
                $str .= '<li><a><span>尾页</span></a></li>';
            }
            $str.= "</ul></nav>";
        }
        return $str;
    }

    

    // 类别TOP 按照点击数排序，考虑时间访问本周
    public function categoryTop($c,$n){
        $filed_name = $this->filed_name;
        if(!empty($c)){
            $contents = $this->select($this->table,$filed_name,[
                "cid"=>$c,
                "ORDER" => [
                    "click" => "DESC"
                ],
                "LIMIT" => [0, $n]]);
            return ['category_top'=>$contents];
        }
    }
    
    // 更新保存信息
    public function saveGetContent($where=null,$data=null){
        if(!empty($data) && is_array($where)){
            $where['LIMIT'] = 1;
            $contents = $this->update($this->table,$data,$where);
            return $contents->rowCount ();
        }else{
            return $this->get($this->table,$this->filed_name,$where);
        }
    }
    
    
    // // 游戏站点  Trending Games
    public function getTrending($cid,$limit = 3,$order='click'){
        //SELECT a.id,a.thumb,b.name FROM contents as a LEFT JOIN menus b ON a.cid = b.id WHERE a.cid in ('12','10','11') GROUP BY a.cid ORDER BY click DESC
        //SELECT a.id,a.thumb,b.name FROM contents as a LEFT JOIN menus b ON a.cid = b.id WHERE a.cid =12 ORDER BY click DESC LIMIT 1
        $data = [];
        if($cid && is_array($cid)){
            foreach ($cid as $val){
                $data[] = $this->select($this->table,
                        ["[>]".$this->table_menus => ["cid" => "id"]],
                        [$this->table.'.id',$this->table.'.thumb',$this->table_menus.'.name'],
                        [$this->table.'.cid'=>$val,"ORDER" => [$this->table.".".$order => "DESC"],"LIMIT" => $limit]
                    );
            }
            if($limit==1){
                return $data; // 首页
            }else{
                if($data){
                    $games= array();
                    foreach ($data as $kk => $vv){
                        array_map(function($v) use (&$games){
                           array_push($games,$v);
                        }, $vv);
                    }
                    // 随机排序
                    $array_random_assoc = function ($arr, $num) {
                        $keys = array_keys($arr);
                        shuffle($keys);
                        $r = array();
                        for ($i = 0; $i < $num; $i++) {
                            $r[$keys[$i]] = $arr[$keys[$i]];
                        }
                        return $r;
                    };
                }            
            } 
        }
        return $array_random_assoc($games,$limit);
    }
    
    


}

