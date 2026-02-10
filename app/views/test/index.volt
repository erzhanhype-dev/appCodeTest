<form enctype="multipart/form-data" action="/test/excel_import" method="POST" autocomplete="off">
  <input type="hidden" name="csrfToken" value="{{ csrfToken }}">

  <div class="row">
    <div class="col">
      <div class="card mt-3">
        <div class="card-header bg-success text-light">{{ t._("Загрузить excel файл") }}</span></div>
        <div class="card-body">
          <div class="form-group" id="order">
            <label class="form-label">{{ t._("import-file") }}</label>
            <span class="help">{{ t._("csv-divide") }}</span>
            <div class="row">
              <div class="col-8">
                <div class="controls">
                  <input type="file" name="files_import" id="files_import" class="form-control-file">
                </div>
              </div>
              <div class="col-auto">
                <button type="submit" class="btn btn-success" name="button">{{ t._("Загрузить файл") }}</button>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
    <div class="col">
      <div class="card mt-3">
        <div class="card-header bg-info text-light">Внимание!</div>
        <div class="card-body">
          <p>Для корректной загрузки файла импорта - сохраните его как CSV-файл.</p>
          <p>Номер <b>VIN</b> должен быть в поле <b>B</b> !</p>
          <p>В контенте(в ячейку) не должна быть запятая !</p>
          <img src="/assets/img/checkByVinExample.png" width="100%" height="92">
        </div>
      </div>
    </div>
  </div>
</form>
<div class="row">
  <div class="col">
    <div class="card mt-3">
      <div class="card-header bg-dark text-light">{{ t._("Загруженные excel файлы(в папке /temp/excel_car_list)") }}</span></div>
      <div class="card-body">
        <!-- таблица -->
        <?php
          $dir = APP_PATH .'/storage/temp/excel_car_list/';
          if (file_exists($dir)) {
          $files = scandir($dir);
          if(count($files) > 0){
        ?>
        <div class="table-bordered">
          <table class="table table-hover">
            <thead>
              <tr>
                <th>{{ t._("num-symbol") }}</th>
                <th> Название файла</th>
                <th> {{ t._("Дата загрузки файла") }}</th>
                <th> {{ t._("operations") }}</th>
                <th> {{ t._("Результаты") }}</th>
              </tr>
            </thead>
            <tbody>
            <?php
            $count = 0;
            foreach($files as $key => $file){
              $ext = pathinfo($file, PATHINFO_EXTENSION);
              if($file == '.' || $file == '..' || $ext != 'csv') continue; $count++; ?>
              <tr>
                <td><?php echo $count;?></td>
                <td><a href="/test/download_excel/<?php echo $file;?>"><?php echo $file;?></a></td>
                <td><?php echo date("d.m.Y H:i", filemtime($dir."/".$file));?></td>
                <td>
                  <form action="/test/check_excel/" method="post" autocomplete="off">
                    <input type="hidden" name="csrfToken" value="{{ csrfToken }}">
                    <input type="hidden" name="file_name" value="<?php echo $file;?>">
                    <button type="submit" class="btn btn-primary ml-4 mb-2"><i data-feather="check-circle" width="20" height="14"></i> Проверить</button>
                    <a href="/test/delete_excel/<?php echo $file;?>" data-confirm='Вы действительно хотите удалить это?' class="btn btn-danger mb-2"><i data-feather="trash" width="14" height="14"></i></a>
                  </form>
                </td>
                <td>
                  <?php
                  $vin_result_excel = $file.'_byVIN.xlsx';
                  $vin_path_result =  $dir.$vin_result_excel;
                  if(file_exists($vin_path_result)) {?>
                    <a href="/test/download_excel/<?php echo $vin_result_excel;?>" class="btn btn-warning"><i data-feather="download" width="14" height="14"></i> проверенных(через VIN).xlsx</a><br><br>
                  <?php
                    }else{echo "Не проверен по VIN !<br><br>";}
                    ?>
                </td>
              </tr>
            <?php } ?>
            </tbody>
          </table>
        </div>
        <?php }} ?>
  </div>
</div>
