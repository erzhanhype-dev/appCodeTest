<?php

use Phalcon\Mvc\Controller;
use Phalcon\Translate\Adapter\NativeArray;

class HelpController extends Controller
{

   public function indexAction()
   {
      $preview = [];
      $docs_files = [];

      // смотрим наличие файлов и превью
      $docs_list = Docs::find();
      foreach ($docs_list as $dl) {
         $v = array('files');

         foreach ($v as $value) {
            $d = opendir(APP_PATH.'/public/docs-'.$value);
            while ($file = readdir($d)) {
               if ($file != '.' && $file != '..') {
                  if ($dl->id == substr($file, 0, -4)) {
                     $docs_files[$dl->id] = $file;
                  }
               }
            }
         }
      }

      // запихиваем список документов в страницу
      $docs = Docs::find();
      $this->view->docs = $docs;
      $this->view->docs_files = $docs_files;

      // язык?
      $auth = $this->session->get("auth");
      $this->view->lang = $auth['lang'];
   }

   public function policyAction()
   {
   }

   public function publicOfferAction()
   {
   }

}
