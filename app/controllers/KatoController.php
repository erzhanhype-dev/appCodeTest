<?php
use Phalcon\Http\Response;

class KatoController extends ControllerBase
{

    public function searchAction()
    {
        $this->view->disable(); // отключаем шаблонизатор для AJAX

        $query = $this->request->getQuery('term', 'string');

        if (!$query) {
            return $this->response->setJsonContent([]);
        }

        // Поиск по русскому и казахскому названиям
        $results = $this->modelsManager->createQuery("
            SELECT id, name_ru, name_kz, kato_code, region, city, district
            FROM Kato
            WHERE name_ru LIKE :query: OR name_kz LIKE :query:
            ORDER BY name_ru ASC
        ")->execute([
            'query' => '%' . $query . '%'
        ]);

        $data = [];
        foreach ($results as $row) {
            $data[] = [
                'id' => $row->id,
                'label' => $row->name_ru . ' / ' . $row->name_kz,
                'value' => $row->name_ru,
                'kato_code' => $row->kato_code,
                'region' => $row->region,
                'city' => $row->city,
                'district' => $row->district
            ];
        }

        $response = new Response();
        $response->setContentType('application/json', 'utf-8');
        $response->setJsonContent($data);
        return $response;
    }

    public function childrenAction()
    {
        $this->view->disable();
        $parentId = $this->request->getQuery('parent_id', 'int', null);

        $items = $this->modelsManager->createQuery("
        SELECT id, name_ru, kato_code
        FROM Kato
        WHERE parent_id = :parent_id:
        ORDER BY name_ru ASC
    ")->execute(['parent_id' => $parentId]);

        $data = [];
        foreach ($items as $item) {
            $data[] = [
                'id' => $item->id,
                'name' => $item->name_ru,
                'kato_code' => $item->kato_code
            ];
        }

        return $this->response->setJsonContent($data);
    }
}