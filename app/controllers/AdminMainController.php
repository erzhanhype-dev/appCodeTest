<?php

class AdminMainController extends ControllerBase
{

   public function indexAction()
   {

      $days = 7;
      $today = time();

      $car_line = array();
      $goods_line = array();
      $g_line = array();
      $d_line = array();
      $a_line = array();
      $count_line = array();
      $days_line = array();

      // // для дневных чартов
      // for($i = 0; $i < $days; $i++) {
      //   $_day = $today-(24*3600*$i);
      //   $_start = strtotime(date('d.m.Y', $_day).' 00:00:00');
      //   $_end = strtotime(date('d.m.Y', $_day).' 23:59:59');
      //   $tc = Transaction::count(['date < '.$_end.' AND date > '.$_start]);
      //   $g_c = Transaction::count(["approve = 'GLOBAL' AND date < ".$_end." AND date > ".$_start]);
      //   $d_c = Transaction::count(["approve = 'DECLINED' AND date < ".$_end." AND date > ".$_start]);
      //   $a_c = Transaction::count(["approve = 'APPROVE' AND date < ".$_end." AND date > ".$_start]);
      //   $car_c = Profile::count(["type = 'CAR' AND created < ".$_end." AND created > ".$_start]);
      //   $goods_c = Profile::count(["type = 'GOODS' AND created < ".$_end." AND created > ".$_start]);
      //   $g_line[] = $g_c;
      //   $d_line[] = $d_c;
      //   $a_line[] = $a_c;
      //   $car_line[] = $car_c;
      //   $goods_line[] = $goods_c;
      //   $count_line[] = $tc;
      //   $days_line[] = date('d.m.Y', $_day);
      // }

      // // круговушка 1
      // $car_pie = Profile::count(["type = 'CAR' AND created < ".$today." AND created > ".($today-($days*3600*24))]);
      // $goods_pie = Profile::count(["type = 'GOODS' AND created < ".$today." AND created > ".($today-($days*3600*24))]);

      // // круговушка 2
      // $car_pie_year = Profile::count(["type = 'CAR' AND created < ".$today." AND created > ".strtotime('01.01.'.date('Y', $today).' 00:00:00')]);
      // $goods_pie_year = Profile::count(["type = 'GOODS' AND created < ".$today." AND created > ".strtotime('01.01.'.date('Y', $today).' 00:00:00')]);

      // // круговушка 3
      // $car_pie_all = Profile::count(["type = 'CAR'"]);
      // $goods_pie_all = Profile::count(["type = 'GOODS'"]);

      $this->view->setVars(array(
         "car_goods_pie_year" => json_encode(array($car_pie_year, $goods_pie_year)),
         "car_goods_pie_all" => json_encode(array($car_pie_all, $goods_pie_all)),
         "car_goods_pie" => json_encode(array($car_pie, $goods_pie)),
         "car_line" => json_encode($car_line),
         "goods_line" => json_encode($goods_line),
         "g_line" => json_encode($g_line),
         "d_line" => json_encode($d_line),
         "a_line" => json_encode($a_line),
         "count_line" => json_encode($count_line),
         "days_line" => json_encode($days_line)
      ));
   }

}