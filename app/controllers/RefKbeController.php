<?php

namespace App\Controllers;

use ControllerBase;
use Phalcon\Paginator\Adapter\QueryBuilder as PaginatorQueryBuilder;
use RefKbe;

class RefKbeController extends ControllerBase
{
   /**
    * Index action
    */
    public function indexAction(): void
    {
        $page = $this->request->getQuery('page', 'int', 1);

        $builder = $this->modelsManager->createBuilder()
            ->from(['k' => RefKbe::class])
            ->where('k.id <> 0')
            ->orderBy('k.id');

        $paginator = new PaginatorQueryBuilder([
            'builder' => $builder,
            'limit'   => 20,
            'page'    => $page,
        ]);

        $this->view->setVar('page', $paginator->paginate());
    }

   /**
    * Displays the creation form
    */
   public function newAction()
   {
   }

   /**
    * Edits a ref_kbe
    *
    * @param  string  $id
    */
    public function editAction(int $id)
    {
        $refKbe = RefKbe::findFirstById($id);
        if (!$refKbe) {
            $this->flash->error('Запись не найдена.');
            return $this->response->redirect('/ref_kbe/index/');
        }

        if (!$this->request->isPost()) {
            $this->view->setVars([
                'id'   => (int)$refKbe->id,
                'kbe'  => (string)$refKbe->kbe,
                'name' => (string)$refKbe->name,
            ]);
        }
    }

   /**
    * Creates a new ref_kbe
    */
   public function createAction()
   {

      if (!$this->request->isPost()) {
         return $this->response->redirect("/ref_kbe/index/");
      }

      $ref_kbe = new RefKbe();

      $ref_kbe->kbe = $this->request->getPost("kbe");
      $ref_kbe->name = $this->request->getPost("name");

      if (!$ref_kbe->save()) {
         foreach ($ref_kbe->getMessages() as $message) {
            $this->flash->error($message);
         }

         return $this->response->redirect("/ref_kbe/new/");
      }

      $this->flash->success("ref_kbe was created successfully");

      return $this->response->redirect("/ref_kbe/index/");
   }

   /**
    * Saves a ref_kbe edited
    *
    */
   public function saveAction()
   {

      if (!$this->request->isPost()) {
         return $this->response->redirect("/ref_kbe/index/");
      }

      $id = $this->request->getPost("id");

      $ref_kbe = RefKbe::findFirstByid($id);
      if (!$ref_kbe) {
         $this->flash->error("ref_kbe does not exist ".$id);
         return $this->response->redirect("/ref_kbe/index/");
      }

      $ref_kbe->kbe = $this->request->getPost("kbe");
      $ref_kbe->name = $this->request->getPost("name");

      if (!$ref_kbe->save()) {
         foreach ($ref_kbe->getMessages() as $message) {
            $this->flash->error($message);
         }
         return $this->response->redirect("/ref_kbe/edit/$ref_kbe->id");
      }

      $this->flash->success("ref_kbe was updated successfully");
      return $this->response->redirect("/ref_kbe/index/");
   }

   /**
    * Deletes a ref_kbe
    *
    * @param  string  $id
    */
   public function deleteAction($id)
   {

      $ref_kbe = RefKbe::findFirstByid($id);
      if (!$ref_kbe) {
         $this->flash->error("ref_kbe was not found");
         return $this->response->redirect("/ref_kbe/index/");
      }

      if (!$ref_kbe->delete()) {
         foreach ($ref_kbe->getMessages() as $message) {
            $this->flash->error($message);
         }

         return $this->response->redirect("/ref_kbe/search/");
      }

      $this->flash->success("ref_kbe was deleted successfully");
      return $this->response->redirect("/ref_kbe/index/");
   }

}
