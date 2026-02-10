<?php
namespace App\Controllers;

use AgentBasement;
use ControllerBase;
use Phalcon\Paginator\Adapter\QueryBuilder as QueryBuilderPaginator;

class AgentBasementController extends ControllerBase
{

   /**
    * Index action
    */
    public function indexAction()
    {
        // номер страницы, по умолчанию 1
        $numberPage = $this->request->getQuery("page", "int", 1);

        $builder = $this->modelsManager->createBuilder()
            ->from('AgentBasement'); // или AgentBasement::class

        // при необходимости можно добавить условия/сортировку
        // ->orderBy('id DESC')
        // ->where('status = :status:', ['status' => 1]);

        $paginator = new QueryBuilderPaginator([
            "builder" => $builder,
            "limit"   => 10,
            "page"    => $numberPage,
        ]);

        $this->view->page = $paginator->paginate();
    }

   /**
    * Displays the creation form
    */
   public function newAction()
   {
   }

   /**
    * Edits a ref_bank
    *
    * @param  string  $id
    */
   public function editAction($id)
   {

      if (!$this->request->isPost()) {
         $ab = AgentBasement::findFirstByid($id);
         if (!$ab) {
            $this->flash->error("Основание не найдено.");
            $this->response->redirect("/agent_basement/index/");
         }

         $this->view->id = $ab->id;

         $this->tag->setDefault("id", $ab->id);
         $this->tag->setDefault("user_id", $ab->user_id);
         $this->tag->setDefault("title", $ab->title);
         $this->tag->setDefault("wt", $ab->wt);
      }
   }

   /**
    * Creates a new ref_bank
    */
   public function createAction()
   {

      if (!$this->request->isPost()) {
         return $this->response->redirect("/agent_basement/index/");
      }

      $ab = new AgentBasement();

      $ab->user_id = $this->request->getPost("user_id");
      $ab->title = $this->request->getPost("title");
      $ab->wt = $this->request->getPost("wt");

      if (!$ab->save()) {
         foreach ($ab->getMessages() as $message) {
            $this->flash->error($message);
         }
         return $this->response->redirect("/agent_basement/new/");
      }

      $this->flash->success("Основание успешно создано.");

      return $this->response->redirect("/agent_basement/index/");
   }

   /**
    * Saves a ref_bank edited
    *
    */
   public function saveAction()
   {

      if (!$this->request->isPost()) {
         return $this->response->redirect("/agent_basement/index/");
      }

      $id = $this->request->getPost("id");

      $ab = AgentBasement::findFirstByid($id);
      if (!$ab) {
         $this->flash->error("Это основание не существует: #".$id);
         return $this->response->redirect("/agent_basement/index/");
      }

      $ab->user_id = $this->request->getPost("user_id");
      $ab->title = $this->request->getPost("title");
      $ab->wt = $this->request->getPost("wt");

      if (!$ab->save()) {
         foreach ($ab->getMessages() as $message) {
            $this->flash->error($message);
         }

         return $this->response->redirect("/agent_basement/edit/$ab->id");
      }

      $this->flash->success("Правки в данные основания внесены.");
      return $this->response->redirect("/agent_basement/index/");
   }

   /**
    * Deletes a ref_bank
    *
    * @param  string  $id
    */
   public function deleteAction($id)
   {

      $ab = AgentBasement::findFirstByid($id);
      if (!$ab) {
         $this->flash->error("Основание не найдено.");
         return $this->response->redirect("/agent_basement/index/");
      }

      if (!$ab->delete()) {
         foreach ($ab->getMessages() as $message) {
            $this->flash->error($message);
         }

         return $this->response->redirect("/agent_basement/index/");
      }

      $this->flash->success("Основание удалено успешно.");
      return $this->response->redirect("/agent_basement/index/");
   }
}
